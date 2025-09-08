# EmailThreads Plugin Installation

## Simple Installation Process

### Step 1: Copy Plugin Files
```bash
# Copy the plugin to your Mautic plugins directory
cp -r "Mautic Email Threads Plugin Bundle" /path/to/mautic/plugins/MauticEmailThreadsBundle
```

### Step 2: Create Database Tables
```bash
# Navigate to the plugin directory
cd /path/to/mautic/plugins/MauticEmailThreadsBundle

# Run the database installation script
php install_tables.php
```

The script will prompt you for your database credentials:
- Database Host (usually `localhost` or `db` in Docker)
- Database Port (usually `3306`)
- Database Name (usually `mautic`)
- Database User (usually `root`)
- Database Password

### Step 3: Clear Cache and Restart
```bash
# Clear Mautic cache
php app/console cache:clear --env=prod

# Restart your web server or Docker container
```

### Step 4: Configure the Plugin
1. Go to your Mautic admin panel
2. Look for "Email Threads" in the main menu
3. Configure the plugin in Settings → Plugins → Email Threads

## Docker Installation

If you're using Docker:

```bash
# Copy plugin files into container
docker cp "Mautic Email Threads Plugin Bundle" your_mautic_container:/var/www/html/plugins/MauticEmailThreadsBundle

# Run installation inside container
docker exec -it your_mautic_container php /var/www/html/plugins/MauticEmailThreadsBundle/install_tables.php

# Clear cache
docker exec -it your_mautic_container php /var/www/html/app/console cache:clear --env=prod

# Restart container
docker restart your_mautic_container
```

## Troubleshooting

### Database Connection Issues
- Check your database credentials
- Ensure the database server is running
- Verify network connectivity (for Docker setups)

### Plugin Not Showing
- Clear Mautic cache
- Restart your web server/container
- Check file permissions

### Tables Already Exist
- The script uses `CREATE TABLE IF NOT EXISTS` so it's safe to run multiple times
- Configuration values use `INSERT IGNORE` so they won't be duplicated

## What Gets Created

### Database Tables
- `mt_EmailThread` - Stores email thread information
- `mt_EmailThreadMessage` - Stores individual messages in threads

### Configuration Values
- `emailthreads_enabled` - Enable/disable the plugin
- `emailthreads_domain` - Your domain for thread URLs
- `emailthreads_auto_thread` - Automatically create threads
- `emailthreads_thread_lifetime` - How long to keep threads active (days)
- `emailthreads_include_unsubscribe` - Include unsubscribe links
- `emailthreads_inject_previous_messages` - Include previous messages as quotes

## Support

For issues or questions, contact: arc.mahir@gmail.com
