# Mautic Email Threads Plugin

A comprehensive plugin for **Mautic 5.x** that enables email threading functionality, allowing emails sent to leads to be displayed as conversation threads on the recipient's end. Works with all email types including one-to-one, campaigns, and broadcasts.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Mautic](https://img.shields.io/badge/mautic-5.x-orange.svg)
![PHP](https://img.shields.io/badge/php-8.1+-blue.svg)
![Symfony](https://img.shields.io/badge/symfony-7.3-green.svg)

## ✨ Features

- ✅ **Universal Email Threading**: Works with all Mautic email types
- ✅ **Automatic Thread Creation**: Groups emails by contact and subject
- ✅ **Public Thread View**: Shareable URLs for email conversations  
- ✅ **Embed Support**: Embeddable threads for external websites
- ✅ **Responsive Design**: Mobile-friendly interface
- ✅ **Admin Interface**: Complete management panel
- ✅ **Modern Architecture**: Built for Mautic 5.x with modern PHP practices
- ✅ **Content Cleaning**: Removes tracking pixels for clean display
- ✅ **Security Features**: XSS protection and secure access

## 📋 Requirements

- **Mautic 5.x** or higher
- **PHP 8.1** or higher  
- **Symfony 7.3** or higher
- **MySQL 5.7+** or **MariaDB 10.2+**

## 🚀 Installation

### Direct Installation

1. **Download** or clone this repository
2. **Copy the plugin** to your Mautic installation:
   ```bash
   cp -r MauticEmailThreadsBundle /path/to/mautic/plugins/
   ```

3. **Set proper permissions**:
   ```bash
   cd /path/to/mautic
   chown -R www-data:www-data plugins/MauticEmailThreadsBundle/
   chmod -R 755 plugins/MauticEmailThreadsBundle/
   ```

4. **Clear Mautic cache**:
   ```bash
   php bin/console cache:clear --env=prod
   ```

5. **Install the plugin** through Mautic admin:
   - Go to **Settings → Plugins**
   - Find "Email Threads" and click **Install/Upgrade**
   - Configure the plugin settings

## ⚡ What's New in This Version

This plugin has been completely updated for **Mautic 5.x** with:

- **Modern PHP 8.1+** with strict typing and attributes
- **Doctrine ORM attributes** instead of legacy metadata methods
- **Twig templates** instead of PHP templates
- **Dependency injection** in controllers
- **Event-driven architecture** with proper subscribers
- **Security enhancements** and permission integration
- **Responsive Twig-based** admin interface
- **Database Integration**: Full integration with Mautic's database system
- **Event-Driven Architecture**: Uses Mautic's event system for seamless integration
- **Responsive Design**: Mobile-friendly interface for both admin and public views
- **Security**: Proper permission controls and secure public access

## Installation

### Manual Installation

1. **Download/Clone the Plugin**
   ```bash
   cd /path/to/mautic/plugins/
   git clone [repository-url] MauticEmailThreadsBundle
   ```

2. **Set Permissions**
   ```bash
   chmod -R 755 MauticEmailThreadsBundle/
   chown -R www-data:www-data MauticEmailThreadsBundle/
   ```

3. **Clear Cache**
   ```bash
   php app/console cache:clear --env=prod
   ```

4. **Install Plugin Database Tables**
   - Go to Mautic Admin → Settings → Plugins
   - Find "Email Threads" in the plugin list
   - Click "Install/Upgrade" to create the database tables

## Configuration

### Plugin Settings

Navigate to **Settings → Plugins → Email Threads** to configure:

- **Enable Email Threads**: Toggle plugin functionality on/off
- **Public Domain**: Set custom domain for public thread URLs
- **Auto-create Threads**: Automatically create threads for all emails
- **Thread Lifetime**: Set how long threads remain active (default: 30 days)
- **Include Unsubscribe**: Add unsubscribe links to thread views

### Database Tables

The plugin creates two main tables:

#### `email_threads`
- Stores thread metadata and aggregated information
- Links to Mautic leads
- Tracks thread status and statistics

#### `email_thread_messages`
- Stores individual email messages within threads
- Links to original Mautic emails and statistics
- Preserves email content and metadata

## Usage

### For Administrators

1. **View All Threads**
   - Navigate to **Channels → Email Threads**
   - Browse all active conversation threads
   - Filter by status, contact, or date range

2. **Thread Details**
   - Click any thread to view complete message history
   - See sender information, timestamps, and email types
   - Access public URLs and embed codes

3. **Configuration Management**
   - Adjust plugin settings as needed
   - Run maintenance cleanup for old threads
   - Monitor thread statistics and performance

### For Recipients

1. **Public Thread Access**
   - Recipients receive emails with thread links
   - Click links to view complete conversation history
   - Clean, responsive interface for easy reading

2. **Thread Features**
   - Chronological message display
   - Sender identification
   - Message type indicators (template, campaign, broadcast)
   - Mobile-friendly responsive design

## API Endpoints

### Public Endpoints
- `GET /email-thread/{threadId}` - Public thread view
- `GET /email-thread/{threadId}/embed` - Embeddable thread view

### Admin Endpoints
- `GET /s/emailthreads` - Thread management interface
- `GET /s/emailthreads/view/{id}` - Thread details
- `POST /s/emailthreads/config` - Configuration management
- `POST /s/emailthreads/cleanup` - Maintenance operations

## Technical Architecture

### Event Integration

The plugin integrates with Mautic's email system through event listeners:

- **EmailEvents::EMAIL_ON_SEND**: Modifies email content to include thread links
- **EmailEvents::EMAIL_POST_SEND**: Creates/updates thread records after sending

### Models

- **EmailThreadModel**: Manages thread entities and business logic
- **EmailThreadMessageModel**: Handles individual message operations

### Controllers

- **DefaultController**: Admin interface and configuration
- **PublicController**: Public-facing thread views

### Security

- **Permission System**: Integrates with Mautic's permission framework
- **Public Access Control**: Secure thread access without authentication
- **XSS Protection**: Proper content sanitization and escaping

## Customization

### Styling

The plugin includes comprehensive CSS that can be customized:

```css
/* Custom thread styling */
.emailthreads-container {
    /* Your custom styles */
}
```

### Templates

Override default templates by copying to your theme:

```
themes/your-theme/html/MauticEmailThreadsBundle/
├── Default/
│   ├── index.html.php
│   └── view.html.php
└── Public/
    ├── thread.html.php
    └── thread_embed.html.php
```

### JavaScript

Extend functionality with custom JavaScript:

```javascript
// Access plugin functionality
EmailThreads.config.refreshInterval = 60000; // Custom refresh interval
EmailThreads.loadThreads(true); // Force refresh
```

## Troubleshooting

### Common Issues

1. **Threads Not Creating**
   - Verify plugin is enabled in configuration
   - Check that emails are being sent successfully
   - Ensure database tables were created during installation

2. **Public Links Not Working**
   - Verify public domain configuration
   - Check web server URL rewriting rules
   - Ensure proper file permissions

3. **Database Errors**
   - Run `php app/console cache:clear`
   - Check database table creation
   - Verify database user permissions

### Debug Mode

Enable debug logging by adding to your local.php:

```php
'emailthreads_debug' => true,
```

### Performance Optimization

For high-volume installations:

1. **Database Indexing**: Ensure proper indexes on thread and message tables
2. **Cache Strategy**: Consider implementing thread caching
3. **Cleanup Schedule**: Set up regular cleanup of old threads

## Development

### File Structure

```
MauticEmailThreadsBundle/
├── Assets/                 # CSS, JS, and other assets
├── Config/                 # Plugin configuration
├── Controller/             # Controllers for admin and public interfaces
├── Entity/                 # Database entities and repositories
├── EventListener/          # Event subscribers for Mautic integration
├── Form/                   # Form types for configuration
├── Model/                  # Business logic models
├── Translation/            # Internationalization files
├── Views/                  # Template files
└── MauticEmailThreadsBundle.php # Main plugin class
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Testing

```bash
# Run plugin tests
php app/console mautic:plugins:test MauticEmailThreadsBundle
```

## License

This plugin is released under the same license as Mautic (GPL v3).

## Support

For support and questions:

1. Check the documentation above
2. Search existing issues in the repository
3. Create a new issue with detailed information
4. Contact the maintainers

## Changelog

### Version 1.0.0
- Initial release
- Core threading functionality
- Admin interface
- Public thread views
- Configuration management
- Embeddable threads
- Multi-language support

---

**Note**: This plugin is designed for Mautic 4.x and later. For earlier versions, compatibility updates may be required.
