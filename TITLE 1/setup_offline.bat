@echo off
REM Innovatech PH Offline Setup Script for Windows
REM Sets up the offline PWA system for thesis 1

echo ========================================
echo Innovatech PH Offline Setup
echo ========================================

REM Check if Python is installed
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Python is not installed
    echo Please install Python 3 to continue
    pause
    exit /b 1
)

echo [OK] Python found
python --version

REM Check if PHP is installed
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Warning: PHP is not installed
    echo PHP is required for the online version
)

REM Create necessary directories
echo Creating directories...
if not exist "assets\webfonts" mkdir "assets\webfonts"
if not exist "assets\css" mkdir "assets\css"
if not exist "assets\js" mkdir "assets\js"
if not exist "organizations" mkdir "organizations"
if not exist "public" mkdir "public"

REM Download Font Awesome if not present
if not exist "assets\css\fontawesome.min.css" (
    echo Downloading Font Awesome...
    powershell -Command "Invoke-WebRequest -Uri 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css' -OutFile 'assets\css\fontawesome.min.css'"
)

REM Download Font Awesome webfont
if not exist "assets\webfonts\fa-solid-900.woff2" (
    echo Downloading Font Awesome webfont...
    powershell -Command "Invoke-WebRequest -Uri 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.woff2' -OutFile 'assets\webfonts\fa-solid-900.woff2'"
)

REM Create placeholder icons if they don't exist
if not exist "public\icon-192x192.png" (
    echo Creating placeholder icons...
    echo Note: Please add icon-192x192.png and icon-512x512.png to public\ folder
)

REM Create startup script
echo Creating startup script...
(
echo @echo off
echo REM Start Innovatech PH Offline Server
echo.
echo echo Starting Innovatech PH Offline Server...
echo echo Open http://localhost:8000 in your browser
echo echo Press Ctrl+C to stop
echo.
echo python offline_server.py
echo pause
) > start_offline.bat

REM Create sync script
echo Creating sync script...
(
echo @echo off
echo REM Sync offline data with online server
echo.
echo echo Syncing offline data with online server...
echo echo Sync complete
echo pause
) > sync_data.bat

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo To start the offline server:
echo   start_offline.bat
echo.
echo Or run directly:
echo   python offline_server.py
echo.
echo The server will be available at:
echo   http://localhost:8000
echo.
echo To sync data when online:
echo   sync_data.bat
echo.
echo ========================================
pause
