@echo off
chcp 65001 > nul
cd /d "%~dp0"

echo =========================================================================
echo   ТОЧКА ПЛАВЛЕНИЯ x TRIAD-OMNI x OMNIROUTE — ЕДИНАЯ ЭКОСИСТЕМА
echo =========================================================================
echo.

:: [1/3] Проверка и запуск OmniRoute (D:\настройка gemini, порт 20128)
echo [1/3] Проверка шлюза OmniRoute (порт 20128)...
netstat -ano | findstr ":20128 " | findstr "LISTENING" > nul
if %ERRORLEVEL% equ 0 (
    echo   [  OK  ] OmniRoute уже активен на порту 20128.
) else (
    echo   [ ИНФО ] Запуск OmniRoute из D:\настройка gemini...
    if exist "D:\настройка gemini\2-Запуск-OmniRoute.bat" (
        start "OmniRoute-20128" /min cmd /c "cd /d \"D:\настройка gemini\" && call \"2-Запуск-OmniRoute.bat\""
        timeout /t 2 /nobreak > nul
    ) else (
        echo   [ ВНИМ ] Файл D:\настройка gemini\2-Запуск-OmniRoute.bat не найден, пропускаем автостарт.
    )
)

:: [2/3] Проверка и запуск Triad-Omni Router (порт 8000)
echo.
echo [2/3] Проверка шлюза Triad-Omni Edge AI (порт 8000)...
netstat -ano | findstr ":8000 " | findstr "LISTENING" > nul
if %ERRORLEVEL% equ 0 (
    echo   [  OK  ] Triad-Omni Router уже активен на порту 8000.
) else (
    echo   [ ИНФО ] Запуск локального моста Triad-Omni Web UI / Router...
    if exist "triad_omni_web_ui_bundle\router.py" (
        start "Triad-Omni-8000" /min cmd /c "cd /d \"%~dp0triad_omni_web_ui_bundle\" && python router.py"
        timeout /t 2 /nobreak > nul
    ) else (
        echo   [ ИНФО ] Поиск внешнего смартфона Node-3 (192.168.31.48:8000)...
    )
)

:: [3/3] Запуск Редакционного Сервера «Точка Плавления» (порт 8765)
echo.
echo [3/3] Запуск Редакционного Дашборда и API (порт 8765)...
echo   [ ИНФО ] Открытие браузера: http://127.0.0.1:8765
start "" cmd /c "timeout /t 2 /nobreak > nul && start http://127.0.0.1:8765"

python seo/scripts/server.py --port 8765
pause
