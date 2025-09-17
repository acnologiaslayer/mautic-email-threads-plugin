#!/bin/bash

# EmailThreads Plugin Installation Script for Mautic 6.0.3
# This script installs the plugin database tables and configuration

echo "EmailThreads Plugin Installation for Mautic 6.0.3"
echo "================================================="

# Check if we're in a Docker environment
if [ -f /.dockerenv ] || [ -n "$DOCKER_CONTAINER" ]; then
    echo "Running in Docker container..."
    PHP_CMD="php"
else
    echo "Running on host system..."
    PHP_CMD="php"
fi

# Run the installation script
echo "Running installation script..."
$PHP_CMD install_mautic6.php

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Installation completed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Clear Mautic cache: php /var/www/html/bin/console cache:clear"
    echo "2. Test the plugin by sending an email"
    echo "3. Check error logs if you encounter any issues"
else
    echo ""
    echo "❌ Installation failed!"
    echo "Please check the error messages above and try again."
    exit 1
fi
