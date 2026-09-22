#!/bin/bash

# Download Font Awesome webfont files for offline use
# These are required for Font Awesome icons to display properly

echo "=== Downloading Font Awesome Webfonts ==="

# Create webfonts directory
mkdir -p assets/webfonts

echo "Downloading Font Awesome 6.5.1 webfonts..."

# Download Font Awesome Solid font
curl -L -o assets/webfonts/fa-solid-900.woff2 https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.woff2
curl -L -o assets/webfonts/fa-solid-900.ttf https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.ttf

# Download Font Awesome Regular font
curl -L -o assets/webfonts/fa-regular-400.woff2 https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-regular-400.woff2
curl -L -o assets/webfonts/fa-regular-400.ttf https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-regular-400.ttf

# Download Font Awesome Brands font
curl -L -o assets/webfonts/fa-brands-400.woff2 https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-brands-400.woff2
curl -L -o assets/webfonts/fa-brands-400.ttf https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-brands-400.ttf

echo "=== Font Awesome Webfonts Download Complete ==="
echo "Font files have been downloaded to assets/webfonts/"
