param(
    [Parameter(Mandatory = $true)][string]$Prompt,
    [int]$TimeoutSec = 900,
    [switch]$NoTools
)

$ErrorActionPreference = 'Stop'

$srcPath = Join-Path $env:LOCALAPPDATA 'Programs\src\src.exe'
if (-not (Test-Path $srcPath)) {
    $cmd = Get-Command src -ErrorAction SilentlyContinue
    if ($cmd) { $srcPath = $cmd.Source } else { throw "SourceCraft CLI не найден. Установите: iex (New-Object System.Net.WebClient).DownloadString('https://s3.yandexcloud.net/sourcecraft-cli/install.ps1')" }
}

$status = & $srcPath auth status 2>&1 | Out-String
if ($status -match 'Authentication required' -or $LASTEXITCODE -ne 0) {
    throw "SourceCraft CLI не авторизован. Выполните: `"$srcPath`" auth login (войдите в браузере Яндекс-аккаунтом)."
}

$args = @('do', $Prompt, '--json', '--skip-update')
if ($NoTools) { $args += '--no-tools' }

Write-Host "[sourcecraft-consult] Запрос к SourceCraft (Free-тариф, нейрокредиты)..." -ForegroundColor Cyan
$job = Start-Job -ScriptBlock {
    param($exe, $a)
    & $exe @a 2>&1 | Out-String
} -ArgumentList $srcPath, $args

if (-not (Wait-Job $job -Timeout $TimeoutSec)) {
    Stop-Job $job -ErrorAction SilentlyContinue
    Remove-Job $job -Force -ErrorAction SilentlyContinue
    throw "SourceCraft не ответил за $TimeoutSec секунд."
}

$output = Receive-Job $job
Remove-Job $job -Force -ErrorAction SilentlyContinue

if (-not $output -or $output.Trim().Length -eq 0) {
    throw "SourceCraft вернул пустой ответ."
}

Write-Output $output.Trim()
