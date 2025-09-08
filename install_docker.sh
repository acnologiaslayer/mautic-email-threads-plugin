#!/bin/bash

# EmailThreads Plugin Docker Installation Script
# This script is specifically designed for Docker installations

set -e  # Exit on any error

echo "🐳 EmailThreads Plugin Docker Installation Script"
echo "================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if we're in the plugin directory
if [ ! -f "install_plugin.php" ]; then
    print_error "This script must be run from the EmailThreads plugin directory"
    print_error "Please cd into the plugin directory and run: ./install_docker.sh"
    exit 1
fi

# Get Docker container name
echo "Please enter your Mautic Docker container name:"
read -p "Container name: " CONTAINER_NAME

# Validate container exists and is running
if ! docker ps | grep -q "$CONTAINER_NAME"; then
    print_error "Container '$CONTAINER_NAME' is not running or doesn't exist"
    print_error "Please check your container name and ensure it's running"
    exit 1
fi

print_status "Container '$CONTAINER_NAME' found and running"

# Get current plugin directory
PLUGIN_DIR=$(pwd)
PLUGIN_NAME="MauticEmailThreadsBundle"

echo ""
echo "📋 Installation Summary:"
echo "  Plugin Directory: $PLUGIN_DIR"
echo "  Container Name: $CONTAINER_NAME"
echo "  Target Plugin Path: /var/www/html/docroot/plugins/$PLUGIN_NAME"
echo ""

read -p "Continue with installation? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_warning "Installation cancelled"
    exit 0
fi

# Step 1: Copy plugin files to container
echo ""
echo "📁 Step 1: Copying plugin files to container..."
if docker exec "$CONTAINER_NAME" test -d "/var/www/html/docroot/plugins/$PLUGIN_NAME"; then
    print_warning "Plugin directory already exists. Backing up..."
    docker exec "$CONTAINER_NAME" mv "/var/www/html/docroot/plugins/$PLUGIN_NAME" "/var/www/html/docroot/plugins/${PLUGIN_NAME}.backup.$(date +%Y%m%d_%H%M%S)"
fi

docker cp "$PLUGIN_DIR" "$CONTAINER_NAME:/var/www/html/docroot/plugins/$PLUGIN_NAME"
print_status "Plugin files copied to container"

# Step 2: Copy installation script to container
echo ""
echo "📄 Step 2: Copying installation script to container..."
docker cp "$PLUGIN_DIR/install_plugin.php" "$CONTAINER_NAME:/var/www/html/docroot/"
print_status "Installation script copied to container"

# Step 3: Run database installation inside container
echo ""
echo "🗄️  Step 3: Creating database tables..."
if docker exec "$CONTAINER_NAME" php /var/www/html/docroot/install_plugin.php; then
    print_status "Database tables created successfully"
else
    print_error "Database installation failed"
    print_warning "You may need to run the SQL script manually:"
    print_warning "docker exec -i $CONTAINER_NAME mysql -u username -p database < $PLUGIN_DIR/install_tables.sql"
    exit 1
fi

# Step 4: Clear cache inside container
echo ""
echo "🧹 Step 4: Clearing Mautic cache..."
if docker exec "$CONTAINER_NAME" php /var/www/html/docroot/app/console cache:clear --env=prod; then
    print_status "Cache cleared successfully"
else
    print_warning "Cache clear failed, but plugin should still work"
fi

# Step 5: Set permissions inside container
echo ""
echo "🔐 Step 5: Setting file permissions..."
docker exec "$CONTAINER_NAME" chmod -R 755 "/var/www/html/docroot/plugins/$PLUGIN_NAME"
print_status "File permissions set"

# Cleanup
echo ""
echo "🧹 Step 6: Cleaning up..."
docker exec "$CONTAINER_NAME" rm -f "/var/www/html/docroot/install_plugin.php"
print_status "Installation script removed from container"

echo ""
echo "🎉 Docker Installation Complete!"
echo "================================"
echo ""
print_status "Plugin installed successfully in container '$CONTAINER_NAME'"
echo ""
echo "Next steps:"
echo "1. Restart your Docker container: docker restart $CONTAINER_NAME"
echo "2. Go to your Mautic admin panel"
echo "3. Look for 'Email Threads' in the main menu"
echo "4. Configure the plugin in Settings → Plugins → Email Threads"
echo ""
echo "For support, contact: arc.mahir@gmail.com"
