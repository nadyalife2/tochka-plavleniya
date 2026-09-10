@echo off
cd /d "%~dp0site2"
"%USERPROFILE%\php_portable\php.exe" -S 127.0.0.1:8080 -t "%~dp0site2"
pause



