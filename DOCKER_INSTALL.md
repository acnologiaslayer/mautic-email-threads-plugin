# 🐳 Docker Installation Guide

## For Your Docker Setup

Since you're using Docker, here are the **simplest ways** to install:

### Method 1: Docker-Specific Script (Recommended)

```bash
# Run the Docker installation script
./install_docker.sh
```

The script will ask for your container name and handle everything automatically.

### Method 2: Manual Docker Commands

```bash
# 1. Copy plugin to container
docker cp . d3e6f69b0d32:/var/www/html/docroot/plugins/MauticEmailThreadsBundle

# 2. Copy installation script
docker cp install_plugin.php d3e6f69b0d32:/var/www/html/docroot/

# 3. Run installation inside container
docker exec -it d3e6f69b0d32 php /var/www/html/docroot/install_plugin.php

# 4. Clear cache
docker exec -it d3e6f69b0d32 php /var/www/html/docroot/app/console cache:clear --env=prod

# 5. Restart container
docker restart d3e6f69b0d32
```

### Method 3: One-Liner for Your Setup

```bash
# Copy and install in one go
docker cp . d3e6f69b0d32:/var/www/html/docroot/plugins/MauticEmailThreadsBundle && \
docker cp install_plugin.php d3e6f69b0d32:/var/www/html/docroot/ && \
docker exec d3e6f69b0d32 php /var/www/html/docroot/install_plugin.php && \
docker exec d3e6f69b0d32 php /var/www/html/docroot/app/console cache:clear --env=prod && \
docker restart d3e6f69b0d32
```

## What Each Method Does

1. **Copies plugin files** to the container
2. **Creates database tables** using the installation script
3. **Clears Mautic cache** to recognize the new plugin
4. **Restarts the container** to ensure everything loads properly

## After Installation

1. Go to your Mautic admin panel
2. Look for "Email Threads" in the main menu
3. Configure the plugin in Settings → Plugins → Email Threads

## Troubleshooting

If you get errors:
- Check container is running: `docker ps`
- Check logs: `docker logs d3e6f69b0d32`
- Verify tables: `docker exec -it d3e6f69b0d32 mysql -u root -p -e "SHOW TABLES LIKE 'mt_EmailThread%';"`
