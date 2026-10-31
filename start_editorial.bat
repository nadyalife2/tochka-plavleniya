@echo off
chcp 65001 > nul
cd /d "%~dp0"
echo ========================================================
echo   ТОЧКА ПЛАВЛЕНИЯ — Редакционный конвейер и верстак
echo ========================================================
echo.
echo [1/2] Запуск сервера API и дашборда на порту 8765 ...
echo (Для остановки сервера нажмите Ctrl+C)
echo.
start "" cmd /c "timeout /t 1 /nobreak > nul && start http://127.0.0.1:8765"
python seo/scripts/server.py --port 8765
pause
