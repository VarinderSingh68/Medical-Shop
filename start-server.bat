@echo off
title Medix Pharmacy & Home Delivery Server
echo ==============================================================
echo       Medix 24/7 Super-Specialty Home Pharmacy Server
echo ==============================================================
echo.

set PHP_BIN=E:\php\php.exe

if not exist "%PHP_BIN%" (
    where php >nul 2>nul
    if %errorlevel% equ 0 (
        set PHP_BIN=php
    ) else (
        echo [ERROR] PHP executable not found.
        pause
        exit /b 1
    )
)

echo Starting PHP web server at http://localhost:8000 ...
echo Public Pharmacy Store: http://localhost:8000
echo Pharmacist Admin Portal: http://localhost:8000/backend/admin/login.php
echo.
echo Press Ctrl+C in this window anytime to stop the server.
echo ==============================================================

start "" http://localhost:8000
"%PHP_BIN%" -S localhost:8000 router.php
pause
