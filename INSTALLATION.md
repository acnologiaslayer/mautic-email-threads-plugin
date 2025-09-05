# EmailThreads Plugin Installation Guide

## Quick Installation

### Method 1: Using the Installation Script (Recommended)

1. **Copy the plugin files** to your Mautic installation:
   ```bash
   cp -r "Mautic Email Threads Plugin Bundle" /path/to/mautic/plugins/
   ```

2. **Copy the installation script** to your Mautic root directory:
   ```bash
   cp "Mautic Email Threads Plugin Bundle/install_plugin.php" /path/to/mautic/
   ```

3. **Run the installation script** from your Mautic root directory:
   ```bash
   cd /path/to/mautic
   php install_plugin.php
   ```

4. **Clear Mautic cache**:
   ```bash
   php app/console cache:clear --env=prod
   ```

5. **Restart your web server/Docker container**

### Method 2: Manual Database Setup

If the installation script doesn't work, you can manually create the tables:

1. **Copy the plugin files** to your Mautic installation:
   ```bash
   cp -r "Mautic Email Threads Plugin Bundle" /path/to/mautic/plugins/
   ```

2. **Run the SQL script** in your MySQL database:
   ```bash
   mysql -u your_username -p your_database < "Mautic Email Threads Plugin Bundle/install_tables.sql"
   ```

3. **Clear Mautic cache**:
   ```bash
   php app/console cache:clear --env=prod
   ```

4. **Restart your web server/Docker container**

### Method 3: Using Doctrine Schema Tool

1. **Copy the plugin files** to your Mautic installation:
   ```bash
   cp -r "Mautic Email Threads Plugin Bundle" /path/to/mautic/plugins/
   ```

2. **Copy the table creation script** to your Mautic root directory:
   ```bash
   cp "Mautic Email Threads Plugin Bundle/create_tables.php" /path/to/mautic/
   ```

3. **Run the table creation script**:
   ```bash
   cd /path/to/mautic
   php create_tables.php
   ```

4. **Clear Mautic cache**:
   ```bash
   php app/console cache:clear --env=prod
   ```

## Verification

After installation, verify the plugin is working:

1. **Check the admin interface**: Go to your Mautic admin panel and look for "Email Threads" in the menu
2. **Check the database**: Verify the tables exist:
   ```sql
   SHOW TABLES LIKE 'mt_EmailThread%';
   ```
3. **Check the logs**: Look for any errors in your Mautic logs

## Troubleshooting

### Common Issues

1. **"Table doesn't exist" errors**: The database tables weren't created. Run the installation script again.

2. **Permission errors**: Make sure your web server has write permissions to the Mautic directory.

3. **Cache issues**: Clear the Mautic cache after installation:
   ```bash
   php app/console cache:clear --env=prod
   ```

4. **Plugin not showing in menu**: Check that the plugin files are in the correct location and clear cache.

### Docker Installation

If you're using Docker:

1. **Copy files into the container**:
   ```bash
   docker cp "Mautic Email Threads Plugin Bundle" your_mautic_container:/var/www/html/plugins/
   docker cp "Mautic Email Threads Plugin Bundle/install_plugin.php" your_mautic_container:/var/www/html/
   ```

2. **Run installation inside container**:
   ```bash
   docker exec -it your_mautic_container php install_plugin.php
   ```

3. **Clear cache**:
   ```bash
   docker exec -it your_mautic_container php app/console cache:clear --env=prod
   ```

4. **Restart container**:
   ```bash
   docker restart your_mautic_container
   ```

## Configuration

After installation, you can configure the plugin:

1. Go to **Settings** → **Plugins** → **Email Threads**
2. Configure the following options:
   - **Enable Email Threads**: Turn the plugin on/off
   - **Domain**: Set your domain for thread URLs
   - **Auto Thread**: Automatically create threads for emails
   - **Thread Lifetime**: How long to keep threads active (days)
   - **Include Unsubscribe**: Include unsubscribe links in threads
   - **Inject Previous Messages**: Include previous messages as quotes in new emails

## Support

If you encounter issues:

1. Check the Mautic logs for error messages
2. Verify the database tables exist
3. Ensure proper file permissions
4. Clear cache and restart services

For additional support, contact: arc.mahir@gmail.com
