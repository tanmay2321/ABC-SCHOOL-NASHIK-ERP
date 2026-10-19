@echo off
title ABC School Nashik ERP Server
echo ===================================================
echo     ABC School Nashik - Enterprise Resource Planning
echo ===================================================
echo.
echo Starting local PHP web server on http://localhost:8000 ...
echo.

IF EXIST "C:\xampp\php\php.exe" (
    SET PHP_BIN=C:\xampp\php\php.exe
) ELSE (
    SET PHP_BIN=php
)

echo Using PHP executable: %PHP_BIN%
echo Press Ctrl+C anytime to stop the server.
echo.
"%PHP_BIN%" -S localhost:8000
pause
