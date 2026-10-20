@echo off
setlocal
title BSD Design & Management System

cd /d "%~dp0BSD\bsd"

set PHP_BIN=php
where php >nul 2>&1
if %ERRORLEVEL% equ 0 goto found_php

if exist "C:\xampp\php\php.exe" (
    set PHP_BIN=C:\xampp\php\php.exe
    goto found_php
)

if exist "C:\php\php.exe" (
    set PHP_BIN=C:\php\php.exe
    goto found_php
)

echo [ERROR] PHP executable not found!
pause
exit /b 1

:found_php
echo ===================================================
echo   BSD DESIGN & MANAGEMENT SYSTEM
echo ===================================================
echo [OK] Using PHP: %PHP_BIN%
echo [INFO] Starting BSD System on http://localhost:8080/
echo [INFO] Press Ctrl+C to terminate the server.
echo ===================================================

"%PHP_BIN%" -S localhost:8080
pause
