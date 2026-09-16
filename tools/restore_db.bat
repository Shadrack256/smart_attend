@echo off
REM ============================================================
REM  Smart Attend — Database Restore
REM  Double-click to restore from a backup file. You will be
REM  asked which .sql file to restore.
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

echo.
echo ============================================================
echo   Smart Attend - Restore Database
echo ============================================================
echo.

REM Make sure the backups folder exists
if not exist "%BACKUP_ROOT%\db" (
    echo ERROR: No backup folder found at %BACKUP_ROOT%\db
    echo Run backup_db.bat first.
    pause
    exit /b 1
)

REM List available backups
echo Available backups in %BACKUP_ROOT%\db:
echo.
dir /b /o-d "%BACKUP_ROOT%\db\*.sql" 2>nul
echo.

if not exist "%BACKUP_ROOT%\db\*.sql" (
    echo No .sql backup files found.
    pause
    exit /b 1
)

REM Prompt for filename
set /p BACKUP_FILE=Type the exact filename to restore (or drag it into this window):
set BACKUP_FILE=%BACKUP_FILE:"=%

REM If the user dragged a file, they may have included the full path. Extract just the filename.
for %%F in ("%BACKUP_FILE%") do set BACKUP_FILE=%%~nxF

set FULL_PATH=%BACKUP_ROOT%\db\%BACKUP_FILE%

if not exist "%FULL_PATH%" (
    echo.
    echo ERROR: File not found: %FULL_PATH%
    pause
    exit /b 1
)

REM ---- Confirm ----
echo.
echo ============================================================
echo   WARNING
echo ============================================================
echo   This will OVERWRITE the current database: %DB_NAME%
echo   All current data will be lost.
echo.
echo   Restoring from: %BACKUP_FILE%
echo.
set /p CONFIRM=Type YES to continue:

if /i not "%CONFIRM%"=="YES" (
    echo Restore cancelled.
    pause
    exit /b 0
)

echo.
echo Dropping existing tables...
"%MYSQL_BIN%\mysql.exe" -u %DB_USER% %DB_PASS% -e "DROP DATABASE IF EXISTS %DB_NAME%; CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if errorlevel 1 (
    echo ERROR: Could not recreate the database.
    pause
    exit /b 1
)

echo Importing backup...
"%MYSQL_BIN%\mysql.exe" -u %DB_USER% %DB_PASS% %DB_NAME% < "%FULL_PATH%"

if errorlevel 1 (
    echo ERROR: Import failed. The database may be empty.
    pause
    exit /b 1
)

echo.
echo ============================================================
echo   Restore complete
echo   Database: %DB_NAME% has been restored from %BACKUP_FILE%
echo ============================================================
echo.

REM ---- Optional: restore uploads too ----
set /p RESTORE_UP=Also restore uploaded files? (Y/N):
if /i "%RESTORE_UP%"=="Y" (
    echo.
    echo Available uploads zips:
    dir /b /o-d "%BACKUP_ROOT%\uploads\*.zip" 2>nul
    echo.
    set /p UP_FILE=Type the uploads zip filename to restore:
    set UP_FILE=%UP_FILE:"=%
    for %%F in ("%UP_FILE%") do set UP_FILE=%%~nxF

    if exist "%BACKUP_ROOT%\uploads\%UP_FILE%" (
        echo Backing up current uploads to uploads_before_restore.zip ...
        powershell -NoProfile -Command ^
            "if (Test-Path '%PROJECT_ROOT%\uploads') { Compress-Archive -Path '%PROJECT_ROOT%\uploads\*' -DestinationPath '%PROJECT_ROOT%\uploads_before_restore.zip' -Force }"
        echo Extracting...
        powershell -NoProfile -Command ^
            "Expand-Archive -Path '%BACKUP_ROOT%\uploads\%UP_FILE%' -DestinationPath '%PROJECT_ROOT%\uploads' -Force"
        echo Uploads restored.
    ) else (
        echo File not found: %BACKUP_ROOT%\uploads\%UP_FILE%
    )
)

echo.
echo Done.
pause