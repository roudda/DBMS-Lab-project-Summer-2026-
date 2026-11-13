@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo ========================================================
echo   Starting MediCare+ Hospital Management System...
echo ========================================================

:: 1. Find PHP
set PHP_BIN=php
where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] PHP executable not found.
        echo Please ensure XAMPP or PHP is installed on this PC.
        pause
        exit /b 1
    )
)

:: 2. Check and start MySQL if not currently running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%" neq "0" (
    echo [INFO] MySQL is not running. Starting MySQL from XAMPP...
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
        timeout /t 2 >nul
    ) else if exist "C:\xampp\mysql_start.bat" (
        start "" "C:\xampp\mysql_start.bat"
        timeout /t 2 >nul
    )
) else (
    echo [OK] MySQL is active and running.
)

:: 3. Launch browser
echo Opening browser at http://localhost:5000/frontend/login.html ...
start http://localhost:5000/frontend/login.html

:: 4. Start PHP server with router
echo.
echo ========================================================
echo   MediCare+ is running at http://localhost:5000
echo   Press Ctrl+C in this window to stop the server.
echo ========================================================
echo.
"%PHP_BIN%" -S localhost:5000 router.php
pause
