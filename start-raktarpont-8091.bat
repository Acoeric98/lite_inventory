@echo off
cd /d "%~dp0"

set "PHP_EXE=R:\xampp\php\php.exe"
set "PORT=8091"

if not exist "%PHP_EXE%" (
    echo PHP not found: %PHP_EXE%
    pause
    exit /b 1
)

if not exist "%~dp0public" (
    echo Public directory not found: %~dp0public
    pause
    exit /b 1
)

echo Starting RaktarPont...
echo Address: http://localhost:%PORT%
echo.

"%PHP_EXE%" -S 0.0.0.0:%PORT% -t "%~dp0public"

pause
