# =========================================================================
#   ТОЧКА ПЛАВЛЕНИЯ x TRIAD-OMNI x OMNIROUTE — ЕДИНАЯ ЭКОСИСТЕМА
# =========================================================================
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

Write-Host "=========================================================================" -ForegroundColor Cyan
Write-Host "  ТОЧКА ПЛАВЛЕНИЯ x TRIAD-OMNI x OMNIROUTE — ЕДИНАЯ ЭКОСИСТЕМА" -ForegroundColor Yellow
Write-Host "=========================================================================" -ForegroundColor Cyan
Write-Host ""

# [1/3] Проверка шлюза OmniRoute (порт 20128)
Write-Host "[1/3] Проверка шлюза OmniRoute (порт 20128)..." -ForegroundColor White
$omniPort = Get-NetTCPConnection -LocalPort 20128 -ErrorAction SilentlyContinue
if ($omniPort) {
    Write-Host "  [  OK  ] OmniRoute уже активен на порту 20128." -ForegroundColor Green
} else {
    Write-Host "  [ РЕЖИМ ] OmniRoute в режиме ожидания (standby)." -ForegroundColor DarkGray
}

# [2/3] Проверка шлюза Triad-Omni Edge AI (порт 8000)
Write-Host ""
Write-Host "[2/3] Проверка шлюза Triad-Omni Edge AI (порт 8000)..." -ForegroundColor White
$triadPort = Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue
if ($triadPort) {
    Write-Host "  [  OK  ] Triad-Omni Router уже активен на порту 8000." -ForegroundColor Green
} else {
    $routerScript = Join-Path $Root "triad_omni_web_ui_bundle\router.py"
    if (Test-Path $routerScript) {
        Write-Host "  [ ИНФО ] Запуск локального моста Triad-Omni Router на порту 8000..." -ForegroundColor Yellow
        Start-Process -FilePath "python" -ArgumentList "triad_omni_web_ui_bundle\router.py" -WorkingDirectory $Root -WindowStyle Minimized
        Start-Sleep -Seconds 2
    } else {
        Write-Host "  [ ИНФО ] Поиск внешнего смартфона Node-3 (192.168.31.48:8000)..." -ForegroundColor DarkGray
    }
}

# [3/3] Запуск Редакционного Сервера «Точка Плавления» (порт 8765)
Write-Host ""
Write-Host "[3/3] Запуск Редакционного Дашборда и API (порт 8765)..." -ForegroundColor White
Write-Host "  [ ИНФО ] Открытие браузера: http://127.0.0.1:8765" -ForegroundColor Green

# Открываем браузер через 2 секунды
Start-Job -ScriptBlock { Start-Sleep -Seconds 2; Start-Process "http://127.0.0.1:8765" } | Out-Null

Write-Host "  [ СТАРТ ] Сервер запущен. Нажмите Ctrl+C для остановки.`n" -ForegroundColor Yellow
& python "seo/scripts/server.py" --port 8765
