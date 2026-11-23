@echo off
title Tochka Plavleniya x Triad-Omni Ecosystem
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0start_ecosystem.ps1"
if %ERRORLEVEL% neq 0 (
    echo.
    echo [FALLBACK] Direct launch via Python...
    start "" "http://127.0.0.1:8765"
    python "seo\scripts\server.py" --port 8765
    pause
)
