$dir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$phpExe = "$env:USERPROFILE\php_portable\php.exe"
$siteDir = Join-Path $dir "site2"



Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " Запуск сервера ТЧП (Точка Плавления)" -ForegroundColor Green
Write-Host " URL: http://127.0.0.1:8080" -ForegroundColor Yellow
Write-Host " Папка: $siteDir" -ForegroundColor Gray
Write-Host "=========================================" -ForegroundColor Cyan

Set-Location $siteDir
& $phpExe -S 127.0.0.1:8080 -t $siteDir

