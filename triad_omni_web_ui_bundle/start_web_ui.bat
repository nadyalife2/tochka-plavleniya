@echo off
title Triad-Omni Cyberdeck Web UI
echo ========================================================
echo    Triad-Omni Edge AI Cluster - Web Cyberdeck v5.0
echo ========================================================
echo.
echo [1/2] Запуск Master Router на http://localhost:8000 ...
start /b python router.py
timeout /t 2 /nobreak >nul
echo [2/2] Открытие браузера...
start http://localhost:8000
echo.
echo Готово! Сервер активен в фоне. Для остановки закройте это окно.
pause
