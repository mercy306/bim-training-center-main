@echo off
setlocal
title BSD BIM Training Center (PHP Server)

echo ===================================================
echo   BSD BIM TRAINING CENTER - PHP SERVER
echo ===================================================

cd /d "%~dp0"

REM Find PHP executable
set PHP_BIN=php
where php >nul 2>&1
if %ERRORLEVEL% equ 0 goto found_php

if exist "C:\xampp\php\php.exe" (
    set PHP_BIN=C:\xampp\php\php.exe
    goto found_php
)

if exist "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe" (
    set PHP_BIN=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
    goto found_php
)

if exist "C:\php\php.exe" (
    set PHP_BIN=C:\php\php.exe
    goto found_php
)

echo [ERROR] PHP executable not found!
echo Please ensure XAMPP or PHP is installed.
pause
exit /b 1

:found_php
echo [OK] Using PHP: %PHP_BIN%
echo [INFO] Starting BSD BIM Training Center on http://localhost:3000/
echo [INFO] Press Ctrl+C to terminate the server.
echo ===================================================

"%PHP_BIN%" -S localhost:3000 server.php
pause
