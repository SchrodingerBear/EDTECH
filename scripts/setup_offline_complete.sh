#!/bin/bash

# Complete Offline Setup Script
# This script downloads all external dependencies for offline use
# Apache configuration must be done manually (see OFFLINE_SETUP_README.md)

echo "=== Complete Offline Setup for Innovatech PH ==="
echo "This will download all external dependencies for offline use"
echo ""
echo "Note: Apache configuration must be done manually. See OFFLINE_SETUP_README.md"
echo ""

# Step 1: Download dependencies
echo "Step 1: Downloading external dependencies..."

# Create directories
mkdir -p assets/css
mkdir -p assets/js
mkdir -p assets/fonts

# Download Bootstrap 5.3.3
echo "Downloading Bootstrap 5.3.3..."
curl -L -o assets/css/bootstrap.min.css https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css
curl -L -o assets/js/bootstrap.bundle.min.js https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js

# Download Simple-DataTables 7.1.2
echo "Downloading Simple-DataTables 7.1.2..."
curl -L -o assets/css/simple-datatables.min.css https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css
curl -L -o assets/js/simple-datatables.min.js https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js

# Download Font Awesome 6.5.1
echo "Downloading Font Awesome 6.5.1..."
curl -L -o assets/css/font-awesome.min.css https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css

# Download Chart.js 4.4.1
echo "Downloading Chart.js 4.4.1..."
curl -L -o assets/js/chart.min.js https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js

# Download Jodit 3.24.2
echo "Downloading Jodit 3.24.2..."
curl -L -o assets/css/jodit.min.css https://cdnjs.cloudflare.com/ajax/libs/jodit/3.24.2/jodit.min.css
curl -L -o assets/js/jodit.min.js https://cdnjs.cloudflare.com/ajax/libs/jodit/3.24.2/jodit.min.js

# Download Leaflet 1.9.4
echo "Downloading Leaflet 1.9.4..."
curl -L -o assets/css/leaflet.min.css https://unpkg.com/leaflet@1.9.4/dist/leaflet.css
curl -L -o assets/js/leaflet.min.js https://unpkg.com/leaflet@1.9.4/dist/leaflet.js

# Download Pannellum 2.5.6
echo "Downloading Pannellum 2.5.6..."
curl -L -o assets/css/pannellum.min.css https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css
curl -L -o assets/js/pannellum.min.js https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js

# Download jQuery
echo "Downloading jQuery..."
curl -L -o assets/js/jquery.min.js https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js

# Download Slick Carousel
echo "Downloading Slick Carousel..."
curl -L -o assets/css/slick.min.css https://cdn.jsdelivr.net/npm/slick-carousel/slick/slick.css
curl -L -o assets/css/slick-theme.min.css https://cdn.jsdelivr.net/npm/slick-carousel/slick/slick-theme.css
curl -L -o assets/js/slick.min.js https://cdn.jsdelivr.net/npm/slick-carousel/slick/slick.min.js

# Download Popper.js
echo "Downloading Popper.js..."
curl -L -o assets/js/popper.min.js https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js

# Download Google Fonts (Inter)
echo "Downloading Google Fonts (Inter)..."
curl -L -o assets/css/inter-font.css "https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"

echo ""
echo "=== Setup Complete ==="
echo "All external dependencies have been downloaded to the assets/ directory"
echo ""
echo "Next steps:"
echo "1. Configure Apache manually (see OFFLINE_SETUP_README.md)"
echo "2. Enable mod_rewrite and mod_speling"
echo "3. Allow .htaccess overrides in Apache config"
echo "4. Restart Apache"
echo ""
echo "The system can then work offline without internet connection"
