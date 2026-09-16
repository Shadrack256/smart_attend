@echo off
REM ============================================================
REM  Smart Attend — Database Backup
REM  Double-click this file to create a timestamped SQL backup
REM  of the smart_attend database plus a zip of uploaded files.
REM ============================================================

setlocal

REM ---- Configuration ----
set DB_NAME=smart_attend
set DB_USER=root
set DB_PASS=
set MYSQL_BIN=C:\xampp\mysql\bin
set PROJECT_ROOT=C:\xampp\htdocs\smart_attend
set BACKUP_ROOT=%PROJECT_ROOT%\backups
REM -----------------------

REM Build a timestamp: YYYY-MM-DD_HHMMSS
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set DATETIME=%%I
set DATE=%DATETIME:~0,4%-%DATETIME:~4,2%-%DATETIME:~6,2%
set TIME=%DATETIME:~8,2%%DATETIME:~10,2%%DATETIME:~12,2%
set STAMP=%DATE%_%TIME%

REM Make sure folders exist
if not exist "%BACKUP_ROOT%" mkdir "%BACKUP_ROOT%"
if not exist "%BACKUP_ROOT%\db" mkdir "%BACKUP_ROOT%\db"
if not exist "%BACKUP_ROOT%\uploads" mkdir "%BACKUP_ROOT%\uploads"

echo.
echo ============================================================
echo   Smart Attend - Backup
echo ============================================================
echo.
echo   Database: %DB_NAME%
echo   Timestamp: %STAMP%
echo.

REM ---- 1) Dump the database ----
echo [1/2] Dumping database...
"%MYSQL_BIN%\mysqldump.exe" -u %DB_USER% %DB_PASS% --routines --triggers --single-transaction %DB_NAME% > "%BACKUP_ROOT%\db\%DB_NAME%_%STAMP%.sql"

if errorlevel 1 (
    echo.
    echo ERROR: mysqldump failed. Check that MySQL is running and your credentials are correct.
    echo.
    pause
    exit /b 1
)

echo        OK -> db\%DB_NAME%_%STAMP%.sql

REM ---- 2) Zip the uploads folder (user photos + brand assets) ----
echo [2/2] Backing up uploaded files...
powershell -NoProfile -Command ^
    "Compress-Archive -Path '%PROJECT_ROOT%\uploads\*' -DestinationPath '%BACKUP_ROOT%\uploads\uploads_%STAMP%.zip' -Force"

if errorlevel 1 (
    echo WARNING: uploads zip failed. Database backup was still created.
) else (
    echo        OK -> uploads\uploads_%STAMP%.zip
)

REM ---- Cleanup: keep only the last 30 backups ----
echo.
echo Cleaning old backups (keeping last 30)...
powershell -NoProfile -Command ^
    "Get-ChildItem '%BACKUP_ROOT%\db\*.sql' | Sort-Object LastWriteTime -Descending | Select-Object -Skip 30 | Remove-Item -Force; Get-ChildItem '%BACKUP_ROOT%\uploads\*.zip' | Sort-Object LastWriteTime -Descending | Select-Object -Skip 30 | Remove-Item -Force"

echo.
echo ============================================================
echo   Backup complete
echo   Location: %BACKUP_ROOT%
echo ============================================================
echo.
pause