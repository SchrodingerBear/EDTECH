@echo off
REM Download all external CDN dependencies for offline use
REM This script downloads all JavaScript and CSS libraries used in the project

echo === Downloading External Dependencies for Offline Use ===

REM Create directories
if not exist assets\css mkdir assets\css
if not exist assets\js mkdir assets\js
if not exist assets\fonts mkdir assets\fonts

echo Creating directory structure...

REM Download Bootstrap 5.3.3
echo Downloading Bootstrap 5.3.3...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' -OutFile 'assets\css\bootstrap.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js' -OutFile 'assets\js\bootstrap.bundle.min.js'"

REM Download Simple-DataTables 7.1.2
echo Downloading Simple-DataTables 7.1.2...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css' -OutFile 'assets\css\simple-datatables.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js' -OutFile 'assets\js\simple-datatables.min.js'"

REM Download Font Awesome 6.5.1
echo Downloading Font Awesome 6.5.1...
powershell -Command "Invoke-WebRequest -Uri 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css' -OutFile 'assets\css\font-awesome.min.css'"

REM Download Chart.js 4.4.1
echo Downloading Chart.js 4.4.1...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js' -OutFile 'assets\js\chart.min.js'"

REM Download Jodit 3.24.2
echo Downloading Jodit 3.24.2...
powershell -Command "Invoke-WebRequest -Uri 'https://cdnjs.cloudflare.com/ajax/libs/jodit/3.24.2/jodit.min.css' -OutFile 'assets\css\jodit.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdnjs.cloudflare.com/ajax/libs/jodit/3.24.2/jodit.min.js' -OutFile 'assets\js\jodit.min.js'"

REM Download Leaflet 1.9.4
echo Downloading Leaflet 1.9.4...
powershell -Command "Invoke-WebRequest -Uri 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' -OutFile 'assets\css\leaflet.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js' -OutFile 'assets\js\leaflet.min.js'"

REM Download Pannellum 2.5.6
echo Downloading Pannellum 2.5.6...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css' -OutFile 'assets\css\pannellum.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js' -OutFile 'assets\js\pannellum.min.js'"

REM Download jQuery
echo Downloading jQuery...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js' -OutFile 'assets\js\jquery.min.js'"

REM Download Slick Carousel
echo Downloading Slick Carousel...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/slick-carousel/slick/slick.css' -OutFile 'assets\css\slick.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/slick-carousel/slick/slick-theme.css' -OutFile 'assets\css\slick-theme.min.css'"
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/slick-carousel/slick/slick.min.js' -OutFile 'assets\js\slick.min.js'"

REM Download Popper.js
echo Downloading Popper.js...
powershell -Command "Invoke-WebRequest -Uri 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js' -OutFile 'assets\js\popper.min.js'"

REM Download Google Fonts (Inter)
echo Downloading Google Fonts (Inter)...
powershell -Command "Invoke-WebRequest -Uri 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap' -OutFile 'assets\css\inter-font.css'"

echo === Download Complete ===
echo All dependencies have been downloaded to the assets\ directory
