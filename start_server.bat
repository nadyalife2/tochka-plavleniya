@echo off
rem start_server.bat — локальный автономный сервер темы (PHP built-in)
cd /d "%~dp0theme4"
if not exist "%~dp0theme4\router.php" (
  echo Router not found: %~dp0theme4\router.php
  pause
  exit /b 1
)
"%USERPROFILE%\php_portable\php.exe" -S 127.0.0.1:8080 -t "%~dp0theme4" "%~dp0theme4\router.php"
pause
