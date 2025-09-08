# 🐳 Quick Docker Installation

## For Your Specific Setup

Since you're in `/var/www/html/docroot`, here's the **simplest way** to install:

### Method 1: Use the Simple Docker Script

```bash
# Copy the simple script to your current directory
cp /var/www/html/docroot/plugins/MauticEmailThreadsBundle/install_docker_simple.php ./

# Run it
php install_docker_simple.php
```

### Method 2: Use the Fixed Main Script

```bash
# The main script should now work from your current location
php install_plugin.php
```

### Method 3: One-Liner from Plugin Directory

```bash
# From the plugin directory
cd /var/www/html/docroot/plugins/MauticEmailThreadsBundle
php install_docker_simple.php
```

### Method 4: Manual SQL (If PHP scripts fail)

```bash
# Run the SQL directly in MySQL
mysql -u root -p mautic < /var/www/html/docroot/plugins/MauticEmailThreadsBundle/install_tables.sql
```

## After Installation

```bash
# Clear cache
php app/console cache:clear --env=prod

# Restart container (if needed)
docker restart d3e6f69b0d32
```

## Verification

```bash
# Check if tables were created
mysql -u root -p mautic -e "SHOW TABLES LIKE 'mt_EmailThread%';"
```

## What Each Method Does

1. **Creates database tables** (`mt_EmailThread` and `mt_EmailThreadMessage`)
2. **Inserts default configuration** values
3. **Sets up proper indexes** and foreign keys
4. **Makes the plugin ready** to use

The plugin should now work without the "Table doesn't exist" errors!
