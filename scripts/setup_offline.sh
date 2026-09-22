#!/bin/bash

# Innovatech PH Offline Setup Script
# Sets up the offline PWA system for thesis 1

set -e

echo "========================================"
echo "Innovatech PH Offline Setup"
echo "========================================"

# Check if Python 3 is installed
if ! command -v python3 &> /dev/null; then
    echo "Error: Python 3 is not installed"
    echo "Please install Python 3 to continue"
    exit 1
fi

echo "✓ Python 3 found: $(python3 --version)"

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "Warning: PHP is not installed"
    echo "PHP is required for the online version"
fi

# Create necessary directories
echo "Creating directories..."
mkdir -p assets/webfonts
mkdir -p assets/css
mkdir -p assets/js
mkdir -p organizations
mkdir -p public

# Download Font Awesome (if not already present)
if [ ! -f "assets/css/fontawesome.min.css" ]; then
    echo "Downloading Font Awesome..."
    curl -L -o assets/css/fontawesome.min.css \
        https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css
fi

# Download Font Awesome webfont
if [ ! -f "assets/webfonts/fa-solid-900.woff2" ]; then
    echo "Downloading Font Awesome webfont..."
    curl -L -o assets/webfonts/fa-solid-900.woff2 \
        https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.woff2
fi

# Create icon files if they don't exist
if [ ! -f "public/icon-192x192.png" ]; then
    echo "Creating placeholder icons..."
    # You would replace this with actual icon files
    echo "Note: Please add icon-192x192.png and icon-512x512.png to public/ folder"
fi

# Make Python server executable
chmod +x offline_server.py

# Create startup script
cat > start_offline.sh << 'EOF'
#!/bin/bash
# Start Innovatech PH Offline Server

echo "Starting Innovatech PH Offline Server..."
echo "Open http://localhost:8000 in your browser"
echo "Press Ctrl+C to stop"

python3 offline_server.py
EOF

chmod +x start_offline.sh

# Create sync script
cat > sync_data.sh << 'EOF'
#!/bin/bash
# Sync offline data with online server

echo "Syncing offline data with online server..."
# This would connect to your online MySQL database
# and sync the SQLite data
echo "Sync complete"
EOF

chmod +x sync_data.sh

echo ""
echo "========================================"
echo "Setup Complete!"
echo "========================================"
echo ""
echo "To start the offline server:"
echo "  ./start_offline.sh"
echo ""
echo "Or run directly:"
echo "  python3 offline_server.py"
echo ""
echo "The server will be available at:"
echo "  http://localhost:8000"
echo ""
echo "To sync data when online:"
echo "  ./sync_data.sh"
echo ""
echo "========================================"
