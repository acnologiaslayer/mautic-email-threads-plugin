# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Support for Mautic 6.0.3+
- Auto-detection of table prefixes
- Idempotent installation process
- Non-blocking error handling
- Gmail/Outlook style collapsible messages

### Changed
- Removed admin interface and sidebar navigation
- Simplified plugin to focus on core threading functionality
- Updated installation process for better compatibility
- Improved error handling and logging

### Removed
- Admin interface and sidebar navigation
- Configuration forms and management panels
- Unused assets and templates
- Redundant documentation files

## [1.0.0] - 2024-12-19

### Added
- Email threading functionality for all Mautic email types
- Public thread view with shareable URLs
- Embeddable thread views for external websites
- Automatic detection of Mautic table prefixes
- Idempotent installation script
- Gmail/Outlook style message formatting
- Content cleaning and signature removal
- Non-blocking error handling
- Comprehensive logging and debugging

### Technical Details
- Built for Mautic 6.0.3+ with PHP 8.1+
- Uses modern Symfony 7.3+ architecture
- PDO-based database operations for maximum compatibility
- Event-driven architecture with proper subscribers
- Responsive design for mobile and desktop
- Security features with XSS protection