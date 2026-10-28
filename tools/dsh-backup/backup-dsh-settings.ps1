<#
.SYNOPSIS
    Резервная копия ВСЕХ настроек DeepSeek Harness (DSH): профили, плагины,
    DeepSeek OAuth, ключи провайдеров, а также Google/Antigravity OAuth ("Google Pro").

.DESCRIPTION
    Собирает в один timestamp-ZIP (опционально AES-256 зашифрованный) настройки:

      dsh      $env:DSH_HOME (.dsh):
                 .credentials.yaml  — DeepSeek OAuth (device + account token),
                                      browser-session, OPENROUTER_API_KEY, DASHSCOPE_API_KEY
                 profiles\<name>\   — список плагинов (package.json), pnpm-lock.yaml,
                                      cordis.patch.yml (провайдеры/модели), patches/
                 storages\          — workspace.json (список проектов и сессий)
                 sessions\          — история чатов (можно отключить -NoSessions)
                 web-login\         — аккаунт DeepSeek web-login (без браузерного профиля)
                 llm-deepseek\, attachments\

      gemini   $HOME\.gemini:
                 oauth_creds.json       — Google OAuth (access/refresh/id token)
                 google_accounts.json   — активный Google-аккаунт
                 mcp-oauth-tokens.json  — OAuth-токены MCP-серверов (Figma и др.)
                 settings.json, config\mcp_config.json — MCP-серверы Antigravity IDE
                 antigravity-cli\       — настройки Antigravity CLI (без кешей и чатов)

      claude   $HOME\.claude: settings.json, .credentials.json, plugins
      config   $HOME\.config: cagent, free-claude-code, data-agent-kit
      agents   <workspace>\.agents: локальные skills и .mcp.json проекта
      env      пользовательские переменные окружения с ключами (GEMINI_API_KEY и т.п.)

    Исключаются: node_modules, браузерные профили Chrome/Edge (сотни МБ),
    кеши, логи, IDE-расширения. Восстанавливаются одной командой restore-dsh-settings.ps1.

.PARAMETER OutDir
    Куда класть архив. По умолчанию: $HOME\dsh-backups

.PARAMETER Encrypt
    Зашифровать архив паролем (AES-256-CBC + PBKDF2-SHA256 120k + HMAC-SHA256).
    Пароль спросит интерактивно (или передайте -Password).

.PARAMETER NoSessions
    Не включать историю сессий (sessions, web-login/ledger, history.jsonl).

.PARAMETER IncludeNodeModules
    Включить node_modules профилей (не нужно: переустанавливаются из package.json).

.PARAMETER DryRun
    Только показать, что будет скопировано, ничего не создавая.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\backup-dsh-settings.ps1

.EXAMPLE
    .\backup-dsh-settings.ps1 -OutDir D:\backups -Encrypt

.EXAMPLE
    .\backup-dsh-settings.ps1 -NoSessions -DryRun
#>
[CmdletBinding()]
param(
    [string]$OutDir = $(if ($env:USERPROFILE) { Join-Path $env:USERPROFILE 'dsh-backups' }),
    [string]$DshHome = $(if ($env:DSH_HOME) { $env:DSH_HOME } else { Join-Path $env:USERPROFILE '.dsh' }),
    [string]$GeminiHome = (Join-Path $env:USERPROFILE '.gemini'),
    [string]$ClaudeHome = (Join-Path $env:USERPROFILE '.claude'),
    [string]$ConfigHome = (Join-Path $env:USERPROFILE '.config'),
    [string]$Workspace = '',
    [switch]$NoSessions,
    [switch]$IncludeNodeModules,
    [switch]$Encrypt,
    [string]$Password,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

# ---------------------------------------------------------------- helpers ----

function Write-Step($text) { Write-Host "`n== $text ==" -ForegroundColor Cyan }
function Write-Info($text) { Write-Host "   $text" -ForegroundColor DarkGray }

function Copy-SourceItem {
    param(
        [string]$Source,
        [string]$Destination,
        [string[]]$ExcludeDirs = @(),
        [string[]]$ExcludeFiles = @()
    )
    if (-not (Test-Path -LiteralPath $Source)) {
        Write-Verbose "missing, skipped: $Source"
        return 0
    }
    if ($DryRun) {
        Write-Host ("   [dry-run] {0}  -->  {1}" -f $Source, $Destination)
        return 0
    }
    $parent = Split-Path -Parent $Destination
    if ($parent -and -not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Force -Path $parent | Out-Null
    }
    $item = Get-Item -LiteralPath $Source -Force
    if (-not $item.PSIsContainer) {
        if (Test-Path -LiteralPath $Destination) { Remove-Item -LiteralPath $Destination -Force }
        Copy-Item -LiteralPath $Source -Destination $Destination -Force
        return 1
    }
    $rcArgs = @($Source, $Destination, '/E', '/R:1', '/W:1', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
    if ($ExcludeDirs.Count -gt 0) { $rcArgs += '/XD'; $rcArgs += $ExcludeDirs }
    if ($ExcludeFiles.Count -gt 0) { $rcArgs += '/XF'; $rcArgs += $ExcludeFiles }
    & robocopy.exe @rcArgs | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed (code $LASTEXITCODE) for $Source" }
    $count = @(Get-ChildItem -LiteralPath $Destination -Recurse -File -Force -ErrorAction SilentlyContinue).Count
    return $count
}

function Get-RandomBytes {
    param([int]$Count)
    $buf = New-Object byte[] $Count
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($buf) } finally { $rng.Dispose() }
    return ,$buf
}

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

function Protect-BackupArchive {
    param([string]$InFile, [string]$OutFile, [string]$Password)
    $salt = Get-RandomBytes 16
    $iv = Get-RandomBytes 16
    $keys = Get-BackupKeys -Password $Password -Salt $salt
    $cipherFile = "$OutFile.cipher"
    $aes = [System.Security.Cryptography.Aes]::Create()
    try {
        $aes.Key = $keys.Enc
        $aes.IV = $iv
        $aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
        $aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7
        $in = [System.IO.File]::OpenRead($InFile)
        $out = [System.IO.File]::Create($cipherFile)
        try {
            $cs = [System.Security.Cryptography.CryptoStream]::new(
                $out, $aes.CreateEncryptor(), [System.Security.Cryptography.CryptoStreamMode]::Write)
            try { $in.CopyTo($cs); $cs.FlushFinalBlock() } finally { $cs.Dispose() }
        } finally { $in.Dispose(); $out.Dispose() }

        $hmac = [System.Security.Cryptography.HMACSHA256]::new($keys.Mac)
        $final = [System.IO.File]::Create($OutFile)
        try {
            $bw = [System.IO.BinaryWriter]::new($final)
            $bw.Write([System.Text.Encoding]::ASCII.GetBytes('DSHBK001'))
            $bw.Write($salt)
            $bw.Write($iv)
            $hmac.TransformBlock($iv, 0, $iv.Length, $iv, 0) | Out-Null
            $cf = [System.IO.File]::OpenRead($cipherFile)
            $buf = New-Object byte[] 1048576
            try {
                while (($n = $cf.Read($buf, 0, $buf.Length)) -gt 0) {
                    $bw.Write($buf, 0, $n)
                    $hmac.TransformBlock($buf, 0, $n, $buf, 0) | Out-Null
                }
            } finally { $cf.Dispose() }
            $hmac.TransformFinalBlock([byte[]]@(), 0, 0) | Out-Null
            $bw.Write($hmac.Hash)
            $bw.Flush()
        } finally { $final.Dispose(); $hmac.Dispose() }
    } finally {
        $aes.Dispose()
        if (Test-Path -LiteralPath $cipherFile) { Remove-Item -LiteralPath $cipherFile -Force }
    }
}

function Read-BackupPassword {
    $s1 = Read-Host 'Пароль для шифрования архива' -AsSecureString
    $s2 = Read-Host 'Повторите пароль' -AsSecureString
    $p1 = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s1))
    $p2 = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s2))
    if ($p1 -ne $p2) { throw 'Пароли не совпадают.' }
    if ([string]::IsNullOrEmpty($p1)) { throw 'Пустой пароль недопустим.' }
    return $p1
}

# ------------------------------------------------------------------ start ----

if (-not $Workspace) {
    $probe = (Get-Location).Path
    for ($i = 0; $i -lt 5 -and $probe; $i++) {
        if (Test-Path -LiteralPath (Join-Path $probe '.agents')) {
            $Workspace = $probe
            break
        }
        $parent = [System.IO.Directory]::GetParent($probe)
        $probe = $(if ($parent) { $parent.FullName } else { $null })
    }
    if (-not $Workspace) { $Workspace = (Get-Location).Path }
}

$stamp = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'
$stageRoot = Join-Path ([System.IO.Path]::GetTempPath()) "dshbk-$stamp"
$excludedDirs = @('node_modules', 'cache', 'Cache', 'Code Cache', 'GPUCache', 'GrShaderCache',
                  'ShaderCache', 'Crashpad', 'logs', 'log', 'tmp', 'crashes', 'scratch',
                  'browser-profile', 'transport-profile', 'antigravity-browser-profile',
                  'antigravity-backup', 'conversations', 'brain', 'bin')
if ($IncludeNodeModules) { $excludedDirs = $excludedDirs | Where-Object { $_ -ne 'node_modules' } }

Write-Host "Резервная копия настроек DSH" -ForegroundColor Green
Write-Host "DSH_HOME : $DshHome"
Write-Host "Google   : $GeminiHome"
Write-Host "Архив    : $OutDir"
if ($DryRun) { Write-Host "Режим    : DRY-RUN (ничего не создаётся)" -ForegroundColor Yellow }

$copiedFiles = 0
$sources = [ordered]@{}

# --- 1. DSH: профили, плагины, OAuth DeepSeek, ключи провайдеров -------------
Write-Step 'DSH (.dsh): OAuth DeepSeek, ключи провайдеров, профили и плагины'
$dshTarget = Join-Path $stageRoot 'dsh'
$copiedFiles += Copy-SourceItem -Source (Join-Path $DshHome '.credentials.yaml') -Destination (Join-Path $dshTarget '.credentials.yaml')
$copiedFiles += Copy-SourceItem -Source (Join-Path $DshHome '.anonymous-user-id') -Destination (Join-Path $dshTarget '.anonymous-user-id')
$copiedFiles += Copy-SourceItem -Source (Join-Path $DshHome 'profiles') -Destination (Join-Path $dshTarget 'profiles') -ExcludeDirs $excludedDirs
$copiedFiles += Copy-SourceItem -Source (Join-Path $DshHome 'storages') -Destination (Join-Path $dshTarget 'storages') -ExcludeDirs $excludedDirs
$copiedFiles += Copy-SourceItem -Source (Join-Path $DshHome 'llm-deepseek') -Destination (Join-Path $dshTarget 'llm-deepseek')
$copiedFiles += Copy-SourceItem -Source (Join-Path $DshHome 'attachments') -Destination (Join-Path $dshTarget 'attachments')
$copiedFiles += Copy-SourceItem -Source (Join-Path $DshHome 'web-login') -Destination (Join-Path $dshTarget 'web-login') -ExcludeDirs $excludedDirs
if (-not $NoSessions) {
    Write-Info 'история сессий: включена (отключить: -NoSessions)'
    $copiedFiles += Copy-SourceItem -Source (Join-Path $DshHome 'sessions') -Destination (Join-Path $dshTarget 'sessions') -ExcludeDirs $excludedDirs
}
$sources['dsh'] = $DshHome

# --- 2. Google / Antigravity ("Google Pro") ----------------------------------
Write-Step 'Google / Antigravity: OAuth, аккаунты, MCP-серверы, Antigravity CLI'
$gemTarget = Join-Path $stageRoot 'gemini'
$gemFiles = @(
    'oauth_creds.json', 'google_accounts.json', 'mcp-oauth-tokens.json',
    'settings.json', 'state.json', 'projects.json',
    'extension_integrity.json', 'trustedFolders.json', 'installation_id'
)
foreach ($f in $gemFiles) {
    $copiedFiles += Copy-SourceItem -Source (Join-Path $GeminiHome $f) -Destination (Join-Path $gemTarget $f)
}
$gemDirs = @('skills', 'config', 'antigravity', 'antigravity-cli')
foreach ($d in $gemDirs) {
    $copiedFiles += Copy-SourceItem -Source (Join-Path $GeminiHome $d) -Destination (Join-Path $gemTarget $d) -ExcludeDirs $excludedDirs
}
# CLI: только конфигурация, без бинарей, чатов и кешей
$cliTarget = Join-Path $gemTarget 'antigravity-cli'
if (-not $DryRun) {
    foreach ($junk in @('bin', 'brain', 'conversations', 'cache', 'crashes', 'log', 'updater', 'presence')) {
        $p = Join-Path $cliTarget $junk
        if (Test-Path -LiteralPath $p) { Remove-Item -LiteralPath $p -Recurse -Force }
    }
}
$copiedFiles += Copy-SourceItem -Source (Join-Path $HOME '.antigravity-ide\argv.json') -Destination (Join-Path $gemTarget '.antigravity-ide\argv.json')
$sources['gemini'] = $GeminiHome

# --- 3. Claude ---------------------------------------------------------------
Write-Step 'Claude: настройки, credentials, плагины'
$claudeTarget = Join-Path $stageRoot 'claude'
foreach ($f in @('settings.json', '.credentials.json')) {
    $copiedFiles += Copy-SourceItem -Source (Join-Path $ClaudeHome $f) -Destination (Join-Path $claudeTarget $f)
}
$copiedFiles += Copy-SourceItem -Source (Join-Path $ClaudeHome 'plugins') -Destination (Join-Path $claudeTarget 'plugins') -ExcludeDirs $excludedDirs
$sources['claude'] = $ClaudeHome

# --- 4. Прочие агентские настройки ------------------------------------------
Write-Step 'Прочие агенты (~/.config) и skills проекта (.agents)'
$configTarget = Join-Path $stageRoot 'config'
if (Test-Path -LiteralPath $ConfigHome) {
    $copiedFiles += Copy-SourceItem -Source $ConfigHome -Destination $configTarget -ExcludeDirs $excludedDirs
}
$sources['config'] = $ConfigHome

$agentsSrc = Join-Path $Workspace '.agents'
$agentsTarget = Join-Path $stageRoot 'agents'
if (Test-Path -LiteralPath $agentsSrc) {
    $copiedFiles += Copy-SourceItem -Source $agentsSrc -Destination $agentsTarget -ExcludeDirs $excludedDirs
    $sources['agents'] = $agentsSrc
}
$sources['workspace'] = $Workspace

# --- 5. Переменные окружения с ключами ---------------------------------------
if (-not $DryRun) {
    $envObj = [ordered]@{}
    $userEnv = [Environment]::GetEnvironmentVariables('User')
    foreach ($key in $userEnv.Keys) {
        if ($key -match '(API_KEY|TOKEN|SECRET|OAUTH)') { $envObj[$key] = $userEnv[$key] }
    }
    if ($envObj.Count -gt 0) {
        New-Item -ItemType Directory -Force -Path $stageRoot | Out-Null
        ($envObj | ConvertTo-Json -Depth 5) | Set-Content -Path (Join-Path $stageRoot 'env-user.json') -Encoding UTF8
        Write-Info ("переменные окружения с ключами: " + ($envObj.Keys -join ', '))
    }
}

# --- 6. Манифест и инструкция ------------------------------------------------
if (-not $DryRun) {
    $versions = [ordered]@{}
    try { $versions['dsh'] = (& dsh --version 2>$null | Select-Object -First 1) } catch { }
    try { $versions['node'] = (& node -v 2>$null) } catch { }
    try { $versions['pnpm'] = (& pnpm -v 2>$null) } catch { }
    try { $versions['powershell'] = $PSVersionTable.PSVersion.ToString() } catch { }

    $manifest = [ordered]@{
        tool        = 'backup-dsh-settings.ps1'
        createdAt   = (Get-Date).ToString('o')
        computer    = $env:COMPUTERNAME
        user        = $env:USERNAME
        versions    = $versions
        sources     = $sources
        sessions    = (-not $NoSessions)
        nodeModules = [bool]$IncludeNodeModules
        excluded    = @('node_modules', 'браузерные профили Chrome/Edge', 'кеши', 'логи', 'IDE-расширения')
    }
    $manifest | ConvertTo-Json -Depth 10 | Set-Content -Path (Join-Path $stageRoot 'manifest.json') -Encoding UTF8

    $restoreText = @'
DSH SETTINGS BACKUP — как восстановить
======================================

1. Закройте DeepSeek Harness (dsh web) и Antigravity IDE.
2. Запустите:
     powershell -ExecutionPolicy Bypass -File .\restore-dsh-settings.ps1 `
       -Archive <путь к этому zip (или .zip.enc)>
   (для зашифрованного архива скрипт спросит пароль)
3. Переустановите плагины профиля:
     dsh plugin --profile web install --frozen-lockfile
4. Запустите: dsh web

Если не подхватился вход в DeepSeek web-login — войдите заново один раз
(браузерный профиль в архив не входит). OAuth DeepSeek (account/device),
ключи OPENROUTER/DASHSCOPE и Google OAuth восстанавливаются из архива.

Подробности: README.md рядом со скриптами.
'@
    Set-Content -Path (Join-Path $stageRoot 'RESTORE.md') -Value $restoreText -Encoding UTF8
}

# --- 7. Упаковка и шифрование ------------------------------------------------
if (-not $DryRun) {
    $totalBytes = 0
    $fileCount = 0
    foreach ($f in Get-ChildItem -LiteralPath $stageRoot -Recurse -File -Force) {
        $totalBytes += $f.Length
        $fileCount++
    }
    Write-Step ("Упаковка: {0} файлов, {1:N1} МБ" -f $fileCount, ($totalBytes / 1MB))

    if (-not (Test-Path -LiteralPath $OutDir)) {
        New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
    }
    $zipPath = Join-Path $OutDir "dsh-settings-$stamp.zip"
    $items = @(Get-ChildItem -LiteralPath $stageRoot -Force | Select-Object -ExpandProperty FullName)
    Compress-Archive -LiteralPath $items -DestinationPath $zipPath -CompressionLevel Optimal

    $resultPath = $zipPath
    if ($Encrypt) {
        if (-not $Password) { $Password = Read-BackupPassword }
        $encPath = "$zipPath.enc"
        Write-Info 'шифрование AES-256 + HMAC-SHA256 ...'
        Protect-BackupArchive -InFile $zipPath -OutFile $encPath -Password $Password
        Remove-Item -LiteralPath $zipPath -Force
        $resultPath = $encPath
    }

    $resultSize = (Get-Item -LiteralPath $resultPath).Length
    Remove-Item -LiteralPath $stageRoot -Recurse -Force

    Write-Host ''
    Write-Host 'ГОТОВО' -ForegroundColor Green
    Write-Host ("  Архив : {0}" -f $resultPath)
    Write-Host ("  Размер: {0:N1} МБ" -f ($resultSize / 1MB))
    Write-Host ("  Пароль: {0}" -f $(if ($Encrypt) { 'задан (AES-256)' } else { 'нет — храните файл в защищённом месте!' }))
    Write-Host ''
    Write-Host '  Внутри: DeepSeek OAuth + ключи провайдеров, профили с плагинами,'
    Write-Host '          Google OAuth (Antigravity), MCP-серверы, skills проекта.'
    Write-Host ''
    Write-Host '  Проверить архив без восстановления:' -ForegroundColor DarkGray
    Write-Host ("    .\restore-dsh-settings.ps1 -Archive '{0}' -DryRun" -f $resultPath) -ForegroundColor DarkGray
} else {
    Write-Host ''
    Write-Host 'DRY-RUN завершён: файлы не создавались.' -ForegroundColor Yellow
}
