# Contributing to Mautic Email Threads Plugin

Thank you for considering contributing to this project! This document provides guidelines and information for contributors.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code.

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in the [Issues](../../issues)
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - Mautic version, PHP version, browser info
   - Screenshots if applicable

### Suggesting Features

1. Check existing [Issues](../../issues) and [Discussions](../../discussions)
2. Create a feature request with:
   - Clear description of the feature
   - Use case and benefits
   - Possible implementation approach

### Code Contributions

1. **Fork the repository**
2. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Follow coding standards**
   - PSR-12 coding standard
   - Symfony best practices
   - Mautic plugin conventions
   - Type declarations for PHP 8.4+

4. **Write tests**
   - Unit tests for new functionality
   - Integration tests where appropriate
   - Maintain or improve test coverage

5. **Update documentation**
   - Update README if needed
   - Add/update inline code comments
   - Update CHANGELOG.md

6. **Commit your changes**
   ```bash
   git commit -m "Add feature: description of your feature"
   ```

7. **Push and create Pull Request**
   ```bash
   git push origin feature/your-feature-name
   ```

### Development Setup

```bash
# Clone your fork
git clone https://github.com/mahir/mautic-email-threads-plugin.git

# Install dependencies
composer install

# Set up pre-commit hooks (optional)
composer run-script setup-hooks
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Integration

# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

### Coding Standards

- Follow PSR-12 coding standard
- Use type declarations for all parameters and return types
- Write clear, descriptive variable and method names
- Add PHPDoc blocks for all public methods
- Use proper exception handling
- Follow Symfony and Mautic conventions

### Pull Request Process

1. Ensure all tests pass
2. Update documentation as needed
3. Follow the PR template
4. Request review from maintainers
5. Address any feedback promptly

### Development Guidelines

#### Database Changes
- Always include migration scripts
- Check for existing data before modifications
- Test upgrades from previous versions
- Document schema changes

#### Frontend Changes
- Test on multiple browsers
- Ensure responsive design
- Follow accessibility guidelines
- Optimize for performance

#### Security Considerations
- Validate all user inputs
- Sanitize output data
- Use parameterized queries
- Follow OWASP guidelines
- Report security issues privately

## Project Structure

```
MauticEmailThreadsBundle/
├── Config/           # Plugin configuration
├── Controller/       # Request handling
├── Entity/          # Database entities
├── EventListener/   # Event subscribers
├── Form/           # Form types
├── Model/          # Business logic
├── Views/          # Templates
├── Assets/         # CSS/JS files
├── Translation/    # Language files
└── Tests/          # Test files
```

## Getting Help

- 📚 Check the [Wiki](../../wiki) for documentation
- 💬 Join [GitHub Discussions](../../discussions) for questions
- 🐛 Report bugs in [Issues](../../issues)
- 📧 Contact maintainers: mahir@example.com

## Recognition

Contributors will be recognized in:
- CHANGELOG.md
- README.md contributors section
- GitHub contributors page

Thank you for contributing! 🎉
