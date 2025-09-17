# Mautic Email Threads Plugin

A lightweight plugin for **Mautic 6.0.3+** that enables email threading functionality, automatically displaying previous messages in new emails sent to contacts. Works seamlessly with all email types including campaigns, templates, broadcasts, and trigger emails.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Mautic](https://img.shields.io/badge/mautic-6.0.3+-orange.svg)
![PHP](https://img.shields.io/badge/php-8.1+-blue.svg)
![Symfony](https://img.shields.io/badge/symfony-7.3+-green.svg)

## ✨ Features

- ✅ **Automatic Email Threading**: Automatically displays previous messages in new emails
- ✅ **Gmail/Outlook Style**: Collapsible previous messages with nesting and rich formatting
- ✅ **Universal Compatibility**: Works with all Mautic email types (campaigns, templates, broadcasts, triggers)
- ✅ **Public Thread View**: Shareable URLs for email conversations  
- ✅ **Embed Support**: Embeddable threads for external websites
- ✅ **Responsive Design**: Mobile-friendly interface
- ✅ **Auto-Detection**: Automatically detects table prefix from your Mautic installation
- ✅ **Idempotent Installation**: Safe to run multiple times without breaking functionality
- ✅ **Content Cleaning**: Removes email client signatures and cleans HTML
- ✅ **Non-Blocking**: Won't break email sending if there are any errors

## 📋 Requirements

- **Mautic 6.0.3** or higher
- **PHP 8.1** or higher  
- **Symfony 7.3** or higher
- **MySQL 5.7+** or **MariaDB 10.2+**

## 🚀 Installation

### Quick Installation (Recommended)

1. **Copy the plugin** to your Mautic installation:
   ```bash
   cp -r MauticEmailThreadsBundle /path/to/mautic/plugins/
   ```

2. **Run the installation script**:
   ```bash
   cd /path/to/mautic/plugins/MauticEmailThreadsBundle
   ./install.sh
   ```

3. **Clear Mautic cache**:
   ```bash
   php /var/www/html/bin/console cache:clear
   ```

The installation script will:
- ✅ Auto-detect your table prefix
- ✅ Create required database tables
- ✅ Insert default configuration
- ✅ Verify installation
- ✅ Work with any Mautic installation type

### Manual Installation

If you prefer manual installation:

1. **Copy the plugin** to your Mautic installation:
   ```bash
   cp -r MauticEmailThreadsBundle /path/to/mautic/plugins/
   ```

2. **Run the PHP installation script**:
   ```bash
   cd /path/to/mautic/plugins/MauticEmailThreadsBundle
   php install_mautic6.php
   ```

3. **Clear Mautic cache**:
   ```bash
   php /var/www/html/bin/console cache:clear
   ```

## 🎯 How It Works

The plugin works automatically in the background:

1. **Email Sending**: When Mautic sends an email, the plugin intercepts the process
2. **Thread Detection**: It checks if there are previous messages for the same contact
3. **Content Injection**: If previous messages exist, it adds them as collapsible quotes
4. **Email Delivery**: The email is sent with the threading content included

**No configuration needed** - it works out of the box with all email types!

## 📁 Plugin Structure

```
MauticEmailThreadsBundle/
├── Config/                 # Plugin configuration
├── Controller/             # Public thread controllers
├── Entity/                 # Database entities
├── EventListener/          # Email event subscribers
├── Model/                  # Business logic models
├── Translation/            # Internationalization
├── Views/                  # Twig templates
├── install_mautic6.php     # Installation script
├── install.sh              # Shell installation script
├── test_installation.php   # Installation test script
└── README.md               # This file
```

## 🔧 Configuration

The plugin works with default settings, but you can customize behavior by modifying the configuration in `Config/config.php`:

```php
'parameters' => [
    'emailthreads_enabled' => true,
    'emailthreads_domain' => '',
    'emailthreads_auto_thread' => true,
    'emailthreads_thread_lifetime' => 30, // days
    'emailthreads_include_unsubscribe' => true,
    'emailthreads_inject_previous_messages' => true,
],
```

## 🗄️ Database Tables

The plugin creates two main tables:

### `{prefix}EmailThread`
- Stores thread metadata and aggregated information
- Links to Mautic leads
- Tracks thread status and statistics

### `{prefix}EmailThreadMessage`
- Stores individual email messages within threads
- Links to original Mautic emails and statistics
- Preserves email content and metadata

*Note: `{prefix}` is automatically detected from your Mautic installation (e.g., `mt_`, `mautic_`, or no prefix)*

## 🌐 Public Thread Views

The plugin provides public URLs for viewing email threads:

- **Public View**: `/email-thread/{threadId}` - Full thread view
- **Embed View**: `/email-thread/{threadId}/embed` - Embeddable version

## 🐛 Troubleshooting

### Common Issues

1. **Plugin not working after installation**
   ```bash
   # Clear cache
   php /var/www/html/bin/console cache:clear
   
   # Check if tables exist
   php test_installation.php
   ```

2. **Database connection errors**
   - Verify database credentials in your Mautic configuration
   - Ensure the database user has CREATE and INSERT permissions

3. **Previous messages not showing**
   - Check Mautic error logs: `tail -f /var/www/html/var/logs/prod.log`
   - Verify the plugin is enabled in configuration

### Debug Mode

Enable debug logging by checking the Mautic error logs:
```bash
tail -f /var/www/html/var/logs/prod.log | grep EmailThreads
```

## 🔄 Updates

To update the plugin:

1. **Backup your data** (optional but recommended)
2. **Replace plugin files** with the new version
3. **Run installation script** (it's idempotent - safe to run multiple times):
   ```bash
   ./install.sh
   ```
4. **Clear cache**:
   ```bash
   php /var/www/html/bin/console cache:clear
   ```

## 📝 License

This plugin is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## 🤝 Support

For support and questions:

1. Check the troubleshooting section above
2. Review the installation logs
3. Check Mautic error logs for specific error messages
4. Contact: arc.mahir@gmail.com

## 📈 Changelog

### Version 1.0.0
- Initial release for Mautic 6.0.3+
- Automatic email threading functionality
- Gmail/Outlook style collapsible messages
- Public thread views and embed support
- Auto-detection of table prefixes
- Idempotent installation process
- Non-blocking error handling

---

**Note**: This plugin is designed for Mautic 6.0.3 and later. For earlier versions, compatibility updates may be required.