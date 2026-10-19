@echo off
title Push ABC School ERP to GitHub (tanmay2321)
set GIT_BIN=%~dp0..\mingit\cmd\git.exe
if not exist "%GIT_BIN%" set GIT_BIN=git

echo ==========================================================
echo        ABC School Nashik ERP - Push to GitHub
echo ==========================================================
echo.
echo Target Repository: https://github.com/tanmay2321/ABC-SCHOOL-NASHIK-ERP.git
echo Target Branch: main
echo.
echo When prompted for:
echo   1. Username: Enter your GitHub username (tanmay2321)
echo   2. Password: Paste your GitHub Personal Access Token (PAT)
echo      (Create token at: https://github.com/settings/tokens)
echo.
echo ==========================================================
echo.

"%GIT_BIN%" remote remove origin 2>nul
"%GIT_BIN%" remote add origin https://github.com/tanmay2321/ABC-SCHOOL-NASHIK-ERP.git
"%GIT_BIN%" branch -M main
"%GIT_BIN%" push -u origin main

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ==========================================================
    echo [SUCCESS] Code successfully pushed to GitHub!
    echo Visit: https://github.com/tanmay2321/ABC-SCHOOL-NASHIK-ERP
    echo ==========================================================
) else (
    echo.
    echo [NOTICE] Push failed or authentication was cancelled.
    echo Make sure your GitHub Personal Access Token has 'repo' permissions.
)
echo.
pause
