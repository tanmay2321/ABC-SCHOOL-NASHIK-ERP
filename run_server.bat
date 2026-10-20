@echo off
title ABC School Nashik ERP - Web Server
cd /d "%~dp0"

echo ==========================================================
echo        ABC School Nashik - Enterprise Resource Planning
echo ==========================================================
echo.

REM Detect PHP executable
IF EXIST "C:\xampp\php\php.exe" (
    SET PHP_BIN="C:\xampp\php\php.exe"
) ELSE (
    SET PHP_BIN=php
)

echo [1/2] Launching browser at http://localhost:8000 ...
start "" "http://localhost:8000"

echo [2/2] Starting local PHP server on port 8000...
echo.
echo ==========================================================
echo   Server is LIVE! Keep this black command window open.
echo   To STOP the server, close this window or press Ctrl + C.
echo ==========================================================
echo.

%PHP_BIN% -S localhost:8000 router.php

pause
