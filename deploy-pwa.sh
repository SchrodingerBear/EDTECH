#!/bin/bash
# Deploy script for Lavadora PWA
# Run this on your PHP hosting after uploading files

set -e

echo "🚀 Deploying Lavadora PWA..."

# 1. Build the PWA
echo "📦 Building PWA..."
cd /var/www/html/G7-4D-THESIS/pwa
npm ci
npm run build

# 2. Copy built files to web root
echo "📋 Copying built files..."
rsync -av dist/ /var/www/html/G7-4D-THESIS/pwa/

# 3. Set permissions
echo "🔐 Setting permissions..."
chmod -R 755 /var/www/html/G7-4D-THESIS/pwa/
find /var/www/html/G7-4D-THESIS/pwa/ -type f -name "*.php" -exec chmod 644 {} \;

# 4. Clear caches
echo "🧹 Clearing caches..."
php /var/www/html/G7-4D-THESIS/artisan cache:clear 2>/dev/null || true

echo "✅ Deployment complete!"
echo ""
echo "🌐 PWA available at: https://yourdomain.com/G7-4D-THESIS/pwa/"
echo "📱 Install as PWA on mobile: Open in browser → Add to Home Screen"