#!/bin/bash

# Download Google Fonts for offline use
# This script downloads the Inter font family

echo "=== Downloading Google Fonts for Offline Use ==="

# Create fonts directory
mkdir -p assets/fonts

echo "Downloading Inter font family..."

# Download Inter font files (normal weights)
curl -L -o assets/fonts/inter-400.woff2 "https://fonts.gstatic.com/s/inter/v13/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuLyfAZ9hjp-Ek-_EeA.woff2"
curl -L -o assets/fonts/inter-500.woff2 "https://fonts.gstatic.com/s/inter/v13/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuI6fAZ9hjp-Ek-_EeA.woff2"
curl -L -o assets/fonts/inter-600.woff2 "https://fonts.gstatic.com/s/inter/v13/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuGKYAZ9hjp-Ek-_EeA.woff2"
curl -L -o assets/fonts/inter-700.woff2 "https://fonts.gstatic.com/s/inter/v13/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuG3YAZ9hjp-Ek-_EeA.woff2"
curl -L -o assets/fonts/inter-800.woff2 "https://fonts.gstatic.com/s/inter/v13/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuAuYAZ9hjp-Ek-_EeA.woff2"

# Create a local CSS file for the fonts
cat > assets/css/inter-font.css << 'EOF'
/* Inter Font - Local version for offline use */
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url('../fonts/inter-400.woff2') format('woff2');
}

@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url('../fonts/inter-500.woff2') format('woff2');
}

@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url('../fonts/inter-600.woff2') format('woff2');
}

@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url('../fonts/inter-700.woff2') format('woff2');
}

@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 800;
  font-display: swap;
  src: url('../fonts/inter-800.woff2') format('woff2');
}
EOF

echo "=== Font Download Complete ==="
echo "Inter font has been downloaded to assets/fonts/"
