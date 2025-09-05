# 🚀 Quick Installation Guide

## After Cloning the Plugin

### Method 1: Automated Installation (Easiest)

```bash
# Make the script executable and run it
chmod +x install.sh
./install.sh
```

The script will:
- Ask for your Mautic installation path
- Copy plugin files automatically
- Create database tables
- Clear cache
- Set proper permissions

### Method 2: Manual Installation

```bash
# 1. Copy plugin to Mautic
cp -r . /path/to/your/mautic/plugins/MauticEmailThreadsBundle

# 2. Copy and run installation script
cp install_plugin.php /path/to/your/mautic/
cd /path/to/your/mautic
php install_plugin.php

# 3. Clear cache
php app/console cache:clear --env=prod

# 4. Restart your server/container
```

### Method 3: Docker Installation

```bash
# 1. Copy files into container
docker cp . your_mautic_container:/var/www/html/plugins/MauticEmailThreadsBundle
docker cp install_plugin.php your_mautic_container:/var/www/html/

# 2. Run installation
docker exec -it your_mautic_container php install_plugin.php

# 3. Clear cache
docker exec -it your_mautic_container php app/console cache:clear --env=prod

# 4. Restart container
docker restart your_mautic_container
```

## Verification

After installation:
1. Go to your Mautic admin panel
2. Look for "Email Threads" in the main menu
3. Configure the plugin in Settings → Plugins → Email Threads

## Troubleshooting

If you get errors:
- Check file permissions: `chmod -R 755 /path/to/mautic/plugins/MauticEmailThreadsBundle`
- Verify database tables: `SHOW TABLES LIKE 'mt_EmailThread%';`
- Check Mautic logs: `tail -f /path/to/mautic/var/logs/prod.log`

## Support

Contact: arc.mahir@gmail.com
