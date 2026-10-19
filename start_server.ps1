# start_server.ps1 — локальный автономный сервер темы (PHP built-in)
# Использует router.php темы: /, /interactive.php, /{slug}/, /category/{slug}/, legal pages.
$dir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$phpExe = Join-Path $env:USERPROFILE "php_portable\php.exe"
if (-not (Test-Path $phpExe)) { $phpExe = "php" }

$themeDir = Join-Path $dir "theme4"
if (-not (Test-Path $themeDir)) {
    Write-Host "Тема не найдена: $themeDir" -ForegroundColor Red
    exit 1
}

$router = Join-Path $themeDir "router.php"
if (-not (Test-Path $router)) {
    Write-Host "Router не найден: $router" -ForegroundColor Red
    exit 1
}

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " Запуск сервера ТЧП (Точка Плавления)" -ForegroundColor Green
Write-Host " URL: http://127.0.0.1:8080" -ForegroundColor Yellow
Write-Host " Папка: $themeDir" -ForegroundColor Gray
Write-Host " Остановка: Ctrl+C" -ForegroundColor Gray
Write-Host "=========================================" -ForegroundColor Cyan

Set-Location $themeDir
& $phpExe -S 127.0.0.1:8080 -t $themeDir $router
