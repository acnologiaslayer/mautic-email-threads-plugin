#!/bin/bash

# EmailThreads Plugin Installation Script
# This script automates the installation process

set -e  # Exit on any error

echo "🚀 EmailThreads Plugin Installation Script"
echo "=========================================="

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
    print_error "Please cd into the plugin directory and run: ./install.sh"
    exit 1
fi

# Get Mautic installation path
echo "Please enter the path to your Mautic installation:"
read -p "Mautic path: " MAUTIC_PATH

# Validate Mautic path
if [ ! -d "$MAUTIC_PATH" ]; then
    print_error "Mautic directory does not exist: $MAUTIC_PATH"
    exit 1
fi

# Check for bootstrap.php in different possible locations
BOOTSTRAP_FOUND=false
if [ -f "$MAUTIC_PATH/app/bootstrap.php" ]; then
    BOOTSTRAP_FOUND=true
    MAUTIC_ROOT="$MAUTIC_PATH"
elif [ -f "$MAUTIC_PATH/docroot/app/bootstrap.php" ]; then
    BOOTSTRAP_FOUND=true
    MAUTIC_ROOT="$MAUTIC_PATH/docroot"
elif [ -f "$MAUTIC_PATH/bootstrap.php" ]; then
    BOOTSTRAP_FOUND=true
    MAUTIC_ROOT="$MAUTIC_PATH"
fi

if [ "$BOOTSTRAP_FOUND" = false ]; then
    print_error "Invalid Mautic directory. bootstrap.php not found in:"
    print_error "  - $MAUTIC_PATH/app/bootstrap.php"
    print_error "  - $MAUTIC_PATH/docroot/app/bootstrap.php"
    print_error "  - $MAUTIC_PATH/bootstrap.php"
    exit 1
fi

print_status "Mautic directory validated: $MAUTIC_PATH"
print_status "Mautic root found at: $MAUTIC_ROOT"

# Get current plugin directory
PLUGIN_DIR=$(pwd)
PLUGIN_NAME="MauticEmailThreadsBundle"

echo ""
echo "📋 Installation Summary:"
echo "  Plugin Directory: $PLUGIN_DIR"
echo "  Mautic Directory: $MAUTIC_PATH"
echo "  Mautic Root: $MAUTIC_ROOT"
echo "  Target Plugin Path: $MAUTIC_ROOT/plugins/$PLUGIN_NAME"
echo ""

read -p "Continue with installation? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_warning "Installation cancelled"
    exit 0
fi

# Step 1: Copy plugin files
echo ""
echo "📁 Step 1: Copying plugin files..."
if [ -d "$MAUTIC_ROOT/plugins/$PLUGIN_NAME" ]; then
    print_warning "Plugin directory already exists. Backing up..."
    mv "$MAUTIC_ROOT/plugins/$PLUGIN_NAME" "$MAUTIC_ROOT/plugins/${PLUGIN_NAME}.backup.$(date +%Y%m%d_%H%M%S)"
fi

cp -r "$PLUGIN_DIR" "$MAUTIC_ROOT/plugins/$PLUGIN_NAME"
print_status "Plugin files copied successfully"

# Step 2: Copy installation script
echo ""
echo "📄 Step 2: Copying installation script..."
cp "$PLUGIN_DIR/install_plugin.php" "$MAUTIC_ROOT/"
print_status "Installation script copied"

# Step 3: Run database installation
echo ""
echo "🗄️  Step 3: Creating database tables..."
cd "$MAUTIC_ROOT"

if php install_plugin.php; then
    print_status "Database tables created successfully"
else
    print_error "Database installation failed"
    print_warning "You may need to run the SQL script manually:"
    print_warning "mysql -u username -p database < $PLUGIN_DIR/install_tables.sql"
    exit 1
fi

# Step 4: Clear cache
echo ""
echo "🧹 Step 4: Clearing Mautic cache..."
if php app/console cache:clear --env=prod; then
    print_status "Cache cleared successfully"
else
    print_warning "Cache clear failed, but plugin should still work"
fi

# Step 5: Set permissions
echo ""
echo "🔐 Step 5: Setting file permissions..."
if command -v chown >/dev/null 2>&1; then
    # Try to set proper ownership (may require sudo)
    if sudo chown -R www-data:www-data "$MAUTIC_ROOT/plugins/$PLUGIN_NAME" 2>/dev/null; then
        print_status "File ownership set to www-data"
    else
        print_warning "Could not set file ownership (may need sudo)"
    fi
fi

chmod -R 755 "$MAUTIC_ROOT/plugins/$PLUGIN_NAME"
print_status "File permissions set"

# Cleanup
echo ""
echo "🧹 Step 6: Cleaning up..."
rm -f "$MAUTIC_ROOT/install_plugin.php"
print_status "Installation script removed"

echo ""
echo "🎉 Installation Complete!"
echo "========================"
echo ""
print_status "Plugin installed successfully"
echo ""
echo "Next steps:"
echo "1. Restart your web server or Docker container"
echo "2. Go to your Mautic admin panel"
echo "3. Look for 'Email Threads' in the main menu"
echo "4. Configure the plugin in Settings → Plugins → Email Threads"
echo ""
echo "If you're using Docker, restart your container:"
echo "  docker restart your_mautic_container"
echo ""
echo "For support, contact: arc.mahir@gmail.com"
