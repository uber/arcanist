# Arcanist - Uber Fork

[![Build Status](https://travis-ci.com/uber/arcanist.svg?branch=master)](https://travis-ci.com/uber/arcanist)

## ⚠️ Important Notice

**This is Uber's fork of Phabricator Arcanist tooling** and the **only Arcanist version supported by Uber's Phabricator installation**. Make sure you install this version and not upstream! Other than that you can refer to upstream documentation for help and general guidance.

## Overview

Arcanist is the command-line tool for [Phabricator](http://phabricator.org). It allows you to interact with Phabricator installations to:

- 📝 Send code for review (differential)
- 📥 Download patches and apply changes
- 📤 Transfer files and manage uploads
- 📊 View status and track progress
- 🔌 Make API calls to Phabricator
- 🚀 Land commits with advanced workflows
- 🔍 Run linting and unit tests
- 📋 Manage code quality and standards

## Installation

### Prerequisites
- PHP 5.2 or later
- Git, Mercurial, or Subversion
- Access to a Phabricator installation

### Quick Setup
```bash
# Clone this repository
git clone https://github.com/uber/arcanist.git
cd arcanist

# Add to your PATH
export PATH="$PWD/bin:$PATH"

# Verify installation
arc help
```

### Configuration
```bash
# Set up your Phabricator instance
arc set-config default https://your-phabricator-instance.com

# Install certificate for authentication
arc install-certificate
```

## Key Features

### 🔧 Core Workflows
- **`arc diff`** - Create or update differential revisions
- **`arc land`** - Land approved changes with Uber's enhanced landing engine
- **`arc lint`** - Run comprehensive linting with 300+ linters
- **`arc unit`** - Execute unit tests and report results
- **`arc patch`** - Apply patches from differential revisions

### 🎯 Uber-Specific Enhancements
- **Stack-based Git landing engine** (`UberArcanistStackGitLandEngine`)
- **Submit queue integration** for automated landing
- **Enhanced flow management** with IC Flow support
- **Advanced linting pipeline** with extensive linter collection
- **Pre/post-push event listeners** for workflow automation

### 🔍 Linting & Quality
This fork includes 300+ specialized linters in `src/lint/linter/`:
- Language-specific linters (PHP, JavaScript, Python, Go, etc.)
- Style and formatting checks
- Security vulnerability detection
- Custom Uber coding standards
- Performance and best practice validation

## Project Structure

```
arcanist/
├── bin/                    # Executable scripts (arc, arc.bat)
├── src/                    # Core PHP source code
│   ├── workflow/          # Arc command implementations
│   ├── lint/              # Linting engine and linters
│   ├── unit/              # Unit testing framework
│   ├── land/              # Landing engines (including Uber's)
│   ├── differential/      # Code review functionality
│   └── uber/              # Uber-specific extensions
├── resources/             # Configuration examples and shell completion
├── scripts/               # Helper scripts and daemons
└── externals/             # External dependencies
```

## Usage Examples

### Basic Workflow
```bash
# Create a new feature branch
git checkout -b feature/awesome-feature

# Make your changes
# ... edit files ...

# Create differential revision
arc diff

# After approval, land the change
arc land
```

### Advanced Linting
```bash
# Run all applicable linters
arc lint

# Run specific linter
arc lint --lintall --engine ArcanistConfigurationDrivenLintEngine

# Use custom lint configuration
arc lint --config .arclint
```

### Submit Queue Integration
```bash
# Land via submit queue (Uber-specific)
arc land --use-submit-queue

# Check submit queue status
arc submit-queue-status
```

## Configuration

### `.arcconfig` Example
```json
{
  "project_id": "your-project",
  "conduit_uri": "https://your-phabricator.com",
  "lint.engine": "ArcanistConfigurationDrivenLintEngine",
  "unit.engine": "ArcanistUnitTestEngine",
  "land.engine": "UberArcanistStackGitLandEngine"
}
```

### `.arclint` Example
```json
{
  "linters": {
    "php": {
      "type": "php",
      "include": "(\\.php$)"
    },
    "js": {
      "type": "jshint",
      "include": "(\\.js$)"
    }
  }
}
```

## Development

### Running Tests
```bash
# Run Arcanist's own tests
arc unit

# Run specific test
php -f scripts/arcanist.php unit src/__tests__/
```

### Contributing
1. Follow Uber's coding standards
2. Add appropriate unit tests
3. Run linting: `arc lint`
4. Submit via differential: `arc diff`

## Documentation

- [User Guide](https://secure.phabricator.com/book/phabricator/article/arcanist/) - Comprehensive usage guide
- [Phabricator Documentation](http://phabricator.org/) - Main Phabricator docs
- [Linter Documentation](src/lint/linter/) - Individual linter documentation
- [Workflow Documentation](src/workflow/) - Command-specific help

## Troubleshooting

### Common Issues
- **Certificate errors**: Run `arc install-certificate`
- **PHP version**: Ensure PHP 5.2+ is installed
- **Path issues**: Verify `arc` is in your PATH
- **Permissions**: Check repository and file permissions

### Getting Help
```bash
# General help
arc help

# Command-specific help
arc help diff
arc help land
arc help lint
```

## License

Arcanist is released under the **Apache 2.0 license** except as otherwise noted. See [LICENSE](LICENSE) for full details.

---

**For more information about Phabricator, visit [phabricator.org](http://phabricator.org/)**
