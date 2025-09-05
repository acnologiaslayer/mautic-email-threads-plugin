# Quick Start Guide - EmailThreads Plugin

## After Cloning the Plugin

### Step 1: Copy Plugin to Mautic

```bash
# Navigate to your Mautic installation directory
cd /path/to/your/mautic/installation

# Copy the plugin folder to Mautic's plugins directory
cp -r "/path/to/Mautic Email Threads Plugin Bundle" ./plugins/

# Or if you're already in the plugin directory:
cp -r . /path/to/your/mautic/installation/plugins/MauticEmailThreadsBundle
```

### Step 2: Install Database Tables

**Option A: Using the Installation Script (Recommended)**

```bash
# Copy the installation script to Mautic root
cp "/path/to/Mautic Email Threads Plugin Bundle/install_plugin.php" ./

# Run the installation script
php install_plugin.php
```

**Option B: Using SQL Script**

```bash
# Run the SQL script directly in your database
mysql -u your_username -p your_database_name < "/path/to/Mautic Email Threads Plugin Bundle/install_tables.sql"
```

### Step 3: Clear Cache and Restart

```bash
# Clear Mautic cache
php app/console cache:clear --env=prod

# Restart your web server or Docker container
# For Docker:
docker restart your_mautic_container_name

# For Apache:
sudo systemctl restart apache2

# For Nginx:
sudo systemctl restart nginx
```

## Docker-Specific Instructions

If you're using Docker:

```bash
# 1. Copy plugin files into the container
docker cp "/path/to/Mautic Email Threads Plugin Bundle" your_mautic_container:/var/www/html/plugins/

# 2. Copy installation script
docker cp "/path/to/Mautic Email Threads Plugin Bundle/install_plugin.php" your_mautic_container:/var/www/html/

# 3. Run installation inside container
docker exec -it your_mautic_container php install_plugin.php

# 4. Clear cache
docker exec -it your_mautic_container php app/console cache:clear --env=prod

# 5. Restart container
docker restart your_mautic_container
```

## Verification

After installation, verify everything works:

1. **Check admin interface**: Go to your Mautic admin panel
2. **Look for "Email Threads"** in the main menu
3. **Check database tables exist**:
   ```sql
   SHOW TABLES LIKE 'mt_EmailThread%';
   ```

## Troubleshooting

If you get permission errors:
```bash
# Fix file permissions
sudo chown -R www-data:www-data /path/to/your/mautic/installation/plugins/
sudo chmod -R 755 /path/to/your/mautic/installation/plugins/
```

If the installation script fails:
```bash
# Check PHP version (needs 8.1+)
php -v

# Check if you're in the right directory
pwd
ls -la app/bootstrap.php
```

## Next Steps

1. **Configure the plugin**: Go to Settings → Plugins → Email Threads
2. **Test email threading**: Send a test email
3. **Check the admin interface**: View created threads

## Support

If you encounter issues:
- Check Mautic logs: `tail -f var/logs/prod.log`
- Verify database tables: `SHOW TABLES LIKE 'mt_EmailThread%';`
- Ensure proper file permissions
