@echo off
title Push ABC School ERP to GitHub
set GIT_BIN=%~dp0..\mingit\cmd\git.exe
if not exist "%GIT_BIN%" set GIT_BIN=git

echo ==========================================================
echo        ABC School Nashik ERP - Push to GitHub
echo ==========================================================
echo.
set /p REPO_URL="Enter your GitHub Repository URL (e.g. https://github.com/USERNAME/abc-school-erp.git): "

if "%REPO_URL%"=="" (
    echo [ERROR] No repository URL entered. Exiting.
    pause
    exit /b
)

"%GIT_BIN%" remote remove origin 2>nul
"%GIT_BIN%" remote add origin %REPO_URL%
"%GIT_BIN%" branch -M main
echo.
echo Pushing codebase to %REPO_URL% ...
"%GIT_BIN%" push -u origin main

if %ERRORLEVEL% EQU 0 (
    echo.
    echo [SUCCESS] Code successfully pushed to GitHub!
) else (
    echo.
    echo [NOTICE] If prompted for authentication, enter your GitHub Username and Personal Access Token (PAT).
)
echo.
pause
