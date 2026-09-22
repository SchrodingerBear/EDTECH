#!/bin/bash

# Setup Apache configuration for PHP without file extensions
# This script provides commands to configure Apache for .htaccess and PHP clean URLs

echo "=== Apache Configuration for PHP Clean URLs ==="
echo ""
echo "Please run the following commands manually to configure Apache:"
echo ""
echo "# Enable mod_rewrite"
echo "sudo a2enmod rewrite"
echo ""
echo "# Enable mod_speling for case-insensitive URLs"
echo "sudo a2enmod speling"
echo ""
echo "# Configure Apache to allow .htaccess overrides"
echo "sudo sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf"
echo ""
echo "# Restart Apache"
echo "sudo systemctl restart apache2"
echo ""
echo "=== After running these commands ==="
echo "Apache will be configured to:"
echo "1. Handle .htaccess files"
echo "2. Support clean URLs without .php extension"
echo "3. Support case-insensitive URLs"
