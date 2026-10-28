<#
.SYNOPSIS
    Восстановление настроек DeepSeek Harness из архива backup-dsh-settings.ps1.

.DESCRIPTION
    Распаковывает архив (обычный .zip или зашифрованный .zip.enc), возвращает на место:
      - DeepSeek OAuth и ключи провайдеров  -> $DSH_HOME\.credentials.yaml
      - профили и список плагинов           -> $DSH_HOME\profiles\<профиль>\
      - рабочие пространства и сессии       -> $DSH_HOME\storages, sessions, web-login
      - Google OAuth / Antigravity          -> $HOME\.gemini
      - Claude, ~/.config, skills проекта   -> соответствующие папки

    Существующие файлы по умолчанию переименовываются в *.bak-<дата>, а не затираются.
    node_modules намеренно не входит в архив: после восстановления выполните
      dsh plugin --profile <профиль> install --frozen-lockfile

.PARAMETER Archive
    Путь к dsh-settings-*.zip или dsh-settings-*.zip.enc

.PARAMETER Password
    Пароль для .zip.enc. Если не указан — спросит интерактивно.

.PARAMETER Overwrite
    Перезаписывать файлы без создания .bak-копий (по умолчанию .bak создаются).

.PARAMETER DryRun
    Ничего не менять: только показать, что и куда будет восстановлено.

.PARAMETER RestoreEnv
    Дополнительно восстановить пользовательские переменные окружения из env-user.json.

.EXAMPLE
    .\restore-dsh-settings.ps1 -Archive .\dsh-settings-2026-10-28_14-00-00.zip -DryRun

.EXAMPLE
    .\restore-dsh-settings.ps1 -Archive "$HOME\dsh-backups\dsh-settings-2026-10-28_14-00-00.zip.enc"
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Archive,
    [string]$Password,
    [string]$DshHome,
    [string]$GeminiHome,
    [string]$ClaudeHome,
    [string]$ConfigHome,
    [string]$Workspace,
    [switch]$Overwrite,
    [switch]$DryRun,
    [switch]$RestoreEnv
)

$ErrorActionPreference = 'Stop'

function Write-Step($text) { Write-Host "`n== $text ==" -ForegroundColor Cyan }
function Write-Info($text) { Write-Host "   $text" -ForegroundColor DarkGray }

function Get-BackupKeys {
    param([string]$Password, [byte[]]$Salt, [int]$Iterations = 120000)
    $kdf = [System.Security.Cryptography.Rfc2898DeriveBytes]::new(
        $Password, $Salt, $Iterations, [System.Security.Cryptography.HashAlgorithmName]::SHA256)
    try { $material = $kdf.GetBytes(64) } finally { $kdf.Dispose() }
    $encKey = New-Object byte[] 32
    $macKey = New-Object byte[] 32
    [Array]::Copy($material, 0, $encKey, 0, 32)
    [Array]::Copy($material, 32, $macKey, 0, 32)
    return @{ Enc = $encKey; Mac = $macKey }
}

function Unprotect-BackupArchive {
    param([string]$InFile, [string]$OutFile, [string]$Password)
    $fs = [System.IO.File]::OpenRead($InFile)
    try {
        $br = [System.IO.BinaryReader]::new($fs)
        $magic = [System.Text.Encoding]::ASCII.GetString($br.ReadBytes(8))
        if ($magic -ne 'DSHBK001') { throw 'Это не зашифрованный архив DSH (.zip.enc).' }
        $salt = $br.ReadBytes(16)
        $iv = $br.ReadBytes(16)
        $dataLength = $fs.Length - 8 - 16 - 16 - 32
        if ($dataLength -lt 1) { throw 'Файл повреждён или обрезан.' }
        $keys = Get-BackupKeys -Password $Password -Salt $salt
        $hmac = [System.Security.Cryptography.HMACSHA256]::new($keys.Mac)
        $hmac.TransformBlock($iv, 0, 16, $iv, 0) | Out-Null
        $buf = New-Object byte[] 1048576
        $remaining = $dataLength
        $cipherFile = "$OutFile.cipher"
        $tmp = [System.IO.File]::Create($cipherFile)
        try {
            while ($remaining -gt 0) {
                $n = $fs.Read($buf, 0, [int][Math]::Min($buf.Length, $remaining))
                if ($n -le 0) { throw 'Неожиданный конец файла архива.' }
                $tmp.Write($buf, 0, $n)
                $hmac.TransformBlock($buf, 0, $n, $buf, 0) | Out-Null
                $remaining -= $n
            }
        } finally { $tmp.Dispose() }
        $expected = $br.ReadBytes(32)
        $hmac.TransformFinalBlock([byte[]]@(), 0, 0) | Out-Null
        $actual = $hmac.Hash
        $hmac.Dispose()
        $same = $true
        for ($i = 0; $i -lt 32; $i++) { if ($expected[$i] -ne $actual[$i]) { $same = $false } }
        if (-not $same) { throw 'Неверный пароль или архив повреждён.' }

        $aes = [System.Security.Cryptography.Aes]::Create()
        try {
            $aes.Key = $keys.Enc
            $aes.IV = $iv
            $aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
            $aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7
            $cfs = [System.IO.File]::OpenRead($cipherFile)
            $out = [System.IO.File]::Create($OutFile)
            try {
                $cs = [System.Security.Cryptography.CryptoStream]::new(
                    $out, $aes.CreateDecryptor(), [System.Security.Cryptography.CryptoStreamMode]::Write)
                try { $cfs.CopyTo($cs); $cs.FlushFinalBlock() } finally { $cs.Dispose() }
            } finally { $cfs.Dispose(); $out.Dispose() }
        } finally {
            $aes.Dispose()
            if (Test-Path -LiteralPath $cipherFile) { Remove-Item -LiteralPath $cipherFile -Force }
        }
    } finally { $fs.Dispose() }
}

# ---------------------------------------------------------------- распаковка --

$archivePath = (Resolve-Path -LiteralPath $Archive).Path
if (-not (Test-Path -LiteralPath $archivePath)) { throw "Архив не найден: $Archive" }

$tmpRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("dshrestore-" + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $tmpRoot | Out-Null
$zipPath = $archivePath

try {
    if ($archivePath.ToLower().EndsWith('.enc')) {
        if (-not $Password) {
            $sec = Read-Host 'Пароль архива' -AsSecureString
            $Password = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
                [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
        }
        Write-Step 'Расшифровка архива (AES-256, проверка HMAC)'
        $zipPath = Join-Path $tmpRoot 'payload.zip'
        Unprotect-BackupArchive -InFile $archivePath -OutFile $zipPath -Password $Password
        Write-Info 'пароль верный, целостность подтверждена'
    }

    Write-Step 'Распаковка'
    $extractDir = Join-Path $tmpRoot 'content'
    Expand-Archive -LiteralPath $zipPath -DestinationPath $extractDir -Force

    $manifestPath = Join-Path $extractDir 'manifest.json'
    if (-not (Test-Path -LiteralPath $manifestPath)) { throw 'В архиве нет manifest.json — это архив DSH-настроек?' }
    $manifest = Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json

    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $rootMap = [ordered]@{}
    $srcs = $manifest.sources
    function Get-Override([string]$name, [string]$manifestValue) {
        if ($name -eq 'dsh' -and $DshHome) { return $DshHome }
        if ($name -eq 'gemini' -and $GeminiHome) { return $GeminiHome }
        if ($name -eq 'claude' -and $ClaudeHome) { return $ClaudeHome }
        if ($name -eq 'config' -and $ConfigHome) { return $ConfigHome }
        if ($name -eq 'agents') {
            if ($Workspace) { return (Join-Path $Workspace '.agents') }
            $probe = (Get-Location).Path
            for ($i = 0; $i -lt 5 -and $probe; $i++) {
                if (Test-Path -LiteralPath (Join-Path $probe '.agents')) {
                    return (Join-Path $probe '.agents')
                }
                $parent = [System.IO.Directory]::GetParent($probe)
                $probe = $(if ($parent) { $parent.FullName } else { $null })
            }
        }
        return $manifestValue
    }
    foreach ($prop in $srcs.PSObject.Properties) {
        $name = $prop.Name
        if ($name -eq 'workspace') { continue }
        $value = $prop.Value
        if (-not $value) { continue }
        $rootMap[$name] = Get-Override $name $value
    }

    Write-Step 'План восстановления'
    Write-Host ("   архив создан: {0}" -f $manifest.createdAt)
    Write-Host ("   версии      : dsh {0}, node {1}" -f $manifest.versions.dsh, $manifest.versions.node)
    foreach ($k in $rootMap.Keys) { Write-Host ("   {0,-8} -> {1}" -f $k, $rootMap[$k]) }

    $restored = 0
    $backedUp = 0
    $skipped = 0
    foreach ($key in $rootMap.Keys) {
        $srcDir = Join-Path $extractDir $key
        if (-not (Test-Path -LiteralPath $srcDir)) { continue }
        $destRoot = $rootMap[$key]
        $files = @(Get-ChildItem -LiteralPath $srcDir -Recurse -File -Force)
        foreach ($file in $files) {
            $rel = $file.FullName.Substring($srcDir.Length).TrimStart('\')
            $dest = Join-Path $destRoot $rel
            if ($DryRun) {
                Write-Host ("   [dry-run] {0}" -f $dest) -ForegroundColor DarkGray
                $restored++
                continue
            }
            $destParent = Split-Path -Parent $dest
            if ($destParent -and -not (Test-Path -LiteralPath $destParent)) {
                New-Item -ItemType Directory -Force -Path $destParent | Out-Null
            }
            if ((Test-Path -LiteralPath $dest) -and -not $Overwrite) {
                Move-Item -LiteralPath $dest -Destination "$dest.bak-$stamp" -Force
                $backedUp++
            }
            Copy-Item -LiteralPath $file.FullName -Destination $dest -Force
            $restored++
        }
    }

    # Переменные окружения (по желанию)
    $envFile = Join-Path $extractDir 'env-user.json'
    if ($RestoreEnv -and (Test-Path -LiteralPath $envFile)) {
        Write-Step 'Переменные окружения пользователя'
        $envObj = Get-Content -LiteralPath $envFile -Raw -Encoding UTF8 | ConvertFrom-Json
        foreach ($prop in $envObj.PSObject.Properties) {
            if ($DryRun) { Write-Host ("   [dry-run] {0}" -f $prop.Name) -ForegroundColor DarkGray; continue }
            [Environment]::SetEnvironmentVariable($prop.Name, $prop.Value, 'User')
            Write-Info ("{0} = <восстановлено>" -f $prop.Name)
        }
    } elseif (Test-Path -LiteralPath $envFile) {
        Write-Info 'в архиве есть env-user.json — при необходимости запустите с -RestoreEnv'
    }

    Write-Host ''
    if ($DryRun) {
        Write-Host ("DRY-RUN: будет восстановлено файлов: {0}. Ничего не изменено." -f $restored) -ForegroundColor Yellow
    } else {
        Write-Host 'ВОССТАНОВЛЕНО' -ForegroundColor Green
        Write-Host ("  файлов     : {0}" -f $restored)
        Write-Host ("  сохранено в .bak-{0}: {1}" -f $stamp, $backedUp)
        Write-Host ''
        Write-Host 'Дальше:' -ForegroundColor Cyan
        Write-Host '  1) Переустановите плагины профиля (node_modules в архив не входят):'
        Write-Host '       dsh plugin --profile web install --frozen-lockfile'
        Write-Host '  2) Запустите DSH:  dsh web'
        Write-Host '  3) Если DeepSeek web-login не подхватился — войдите один раз заново.'
    }
} finally {
    if (Test-Path -LiteralPath $tmpRoot) { Remove-Item -LiteralPath $tmpRoot -Recurse -Force -ErrorAction SilentlyContinue }
}
