# Arcanist Architecture Documentation

## Overview

Arcanist is Uber's fork of the Phabricator Arcanist command-line tool. It provides a comprehensive interface for interacting with Phabricator installations, enabling code review workflows, patch management, file transfers, status monitoring, API calls, and various other development operations.

## Project Structure

```
arcanist/
├── bin/                    # Executable entry points
│   ├── arc                # Main CLI wrapper script
│   └── arc.bat           # Windows batch file
├── scripts/               # Core PHP scripts
│   ├── arcanist.php      # Main entry point
│   ├── __init_script__.php # Initialization script
│   └── breakout.py       # Python utility script
├── src/                   # Main source code
│   ├── workflow/         # Command workflows
│   ├── differential/     # Differential (code review) functionality
│   ├── lint/            # Code linting system
│   ├── unit/            # Unit testing system
│   ├── upload/          # File upload functionality
│   ├── land/            # Code landing functionality
│   ├── configuration/   # Configuration management
│   ├── repository/      # Repository interaction
│   ├── parser/          # Various parsers
│   ├── events/          # Event system
│   ├── listener/        # Event listeners
│   ├── exception/       # Exception classes
│   ├── extensions/      # Extension system
│   ├── flow/            # Flow control
│   ├── internationalization/ # i18n support
│   ├── hgdaemon/        # Mercurial daemon
│   ├── ref/             # Reference management
│   ├── workingcopyidentity/ # Working copy management
│   └── docs/            # Documentation
├── externals/           # External dependencies
├── resources/           # Resource files
├── scripts/             # Additional scripts
└── .arcconfig          # Project configuration
```

## Core Architecture

### 1. Entry Point System

The application starts with the `bin/arc` wrapper script, which:
- Resolves the real location of the script through aliases and symlinks
- Executes the main PHP entry point at `scripts/arcanist.php`

### 2. Main Entry Point (`scripts/arcanist.php`)

The main entry point handles:
- **Environment Sanity Checks**: Validates PHP version and extensions
- **Argument Parsing**: Processes command-line arguments using `PhutilArgumentParser`
- **Configuration Loading**: Loads user, system, and runtime configurations
- **Library Loading**: Dynamically loads phutil libraries based on configuration
- **Workflow Selection**: Routes commands to appropriate workflow classes
- **Error Handling**: Provides comprehensive error reporting and debugging

### 3. Workflow System

The workflow system is the core of Arcanist's command processing:

#### Base Workflow Class (`src/workflow/ArcanistWorkflow.php`)
- Provides the foundation for all command workflows
- Handles common functionality like argument parsing, configuration management
- Implements the command execution lifecycle

#### Command Workflows
Each command has its own workflow class in `src/workflow/`:

**Code Review Workflows:**
- `ArcanistDiffWorkflow.php` - Creates and manages differentials (code reviews)
- `ArcanistUploadWorkflow.php` - Uploads files to Phabricator
- `ArcanistLandWorkflow.php` - Lands code changes to repositories
- `ArcanistPatchWorkflow.php` - Applies patches from differentials

**Development Workflows:**
- `ArcanistLintWorkflow.php` - Runs code linting
- `ArcanistUnitWorkflow.php` - Runs unit tests
- `ArcanistCoverWorkflow.php` - Generates code coverage reports
- `ArcanistCommitWorkflow.php` - Manages commits

**Configuration Workflows:**
- `ArcanistSetConfigWorkflow.php` - Sets configuration values
- `ArcanistGetConfigWorkflow.php` - Retrieves configuration values
- `ArcanistHelpWorkflow.php` - Provides help documentation

**Utility Workflows:**
- `ArcanistBrowseWorkflow.php` - Opens differentials in browser
- `ArcanistPasteWorkflow.php` - Creates pastes
- `ArcanistTasksWorkflow.php` - Manages tasks

### 4. Configuration System

The configuration system supports multiple levels:

**Configuration Sources:**
- **User Configuration**: `~/.arcrc` - User-specific settings
- **System Configuration**: System-wide settings
- **Project Configuration**: `.arcconfig` - Project-specific settings
- **Runtime Configuration**: Command-line overrides

**Configuration Manager (`src/configuration/`)**
- `ArcanistConfigurationManager.php` - Manages all configuration sources
- Handles configuration precedence and validation
- Supports dynamic configuration loading

### 5. Differential System (`src/differential/`)

The differential system handles code review functionality:

**Core Components:**
- `ArcanistDifferentialCommitMessage.php` - Parses and formats commit messages
- `ArcanistDifferentialDependencyGraph.php` - Manages review dependencies
- Constants and utilities for differential operations

### 6. Linting System (`src/lint/`)

The linting system provides code quality analysis:

**Architecture:**
- **Lint Engines** (`src/lint/engine/`) - Different linting engines
- **Linters** (`src/lint/linter/`) - Language-specific linters
- **Renderers** (`src/lint/renderer/`) - Output formatting
- **Core Classes:**
  - `ArcanistLintMessage.php` - Represents lint messages
  - `ArcanistLintResult.php` - Aggregates lint results
  - `ArcanistLintSeverity.php` - Defines severity levels
  - `ArcanistLintPatcher.php` - Applies lint fixes

### 7. Unit Testing System (`src/unit/`)

The unit testing system provides test execution:

**Architecture:**
- **Test Engines** (`src/unit/engine/`) - Different test runners
- **Parsers** (`src/unit/parser/`) - Test result parsing
- **Renderers** (`src/unit/renderer/`) - Test output formatting
- **Core Classes:**
  - `ArcanistUnitTestResult.php` - Represents test results

### 8. Repository Integration (`src/repository/`)

Handles interaction with version control systems:
- Working copy identity management
- Repository state detection
- VCS-specific operations

### 9. Event System (`src/events/` and `src/listener/`)

Provides extensibility through events:
- Event dispatching and handling
- Plugin system for custom functionality
- Hook points for workflow customization

### 10. Extension System (`src/extensions/`)

Supports custom extensions:
- Plugin architecture for adding new functionality
- Custom linters, test engines, and workflows
- Configuration extensions

## Key Design Patterns

### 1. Workflow Pattern
Each command is implemented as a workflow class that extends `ArcanistWorkflow`. This provides:
- Consistent command interface
- Shared functionality through inheritance
- Easy testing and maintenance

### 2. Configuration Hierarchy
Configuration follows a clear precedence order:
1. Runtime configuration (command-line)
2. Project configuration (`.arcconfig`)
3. User configuration (`~/.arcrc`)
4. System configuration
5. Default values

### 3. Plugin Architecture
The system supports extensibility through:
- Custom linters and test engines
- Workflow extensions
- Configuration overrides
- Event listeners

### 4. Error Handling
Comprehensive error handling with:
- Custom exception classes
- Detailed error messages
- Debugging support
- Graceful degradation

## Dependencies

### External Libraries
- **phutil**: Core utility library (included in externals/)
- **PHP Extensions**: Required extensions for various operations

### Internal Dependencies
- **libphutil**: Core library providing fundamental utilities
- **Configuration System**: Manages all settings and preferences
- **Event System**: Provides extensibility and hooks

## Configuration Files

### `.arcconfig`
Project-specific configuration file that defines:
- Phabricator instance URI
- Project settings
- Custom linters and test engines
- Library loading

### `.arcrc`
User-specific configuration containing:
- Authentication tokens
- Personal preferences
- Custom workflows

## Security Considerations

1. **Authentication**: Uses Conduit tokens for API authentication
2. **Configuration**: Sensitive data stored in user configuration
3. **Input Validation**: Comprehensive argument and input validation
4. **Error Handling**: Secure error reporting without exposing sensitive data

## Performance Considerations

1. **Lazy Loading**: Libraries and workflows loaded on demand
2. **Caching**: Configuration and working copy state caching
3. **Memory Management**: Configurable memory limits
4. **Parallel Execution**: Support for parallel linting and testing

## Testing

The project includes comprehensive testing:
- Unit tests in `src/__tests__/`
- Lint-specific tests in `src/lint/__tests__/`
- Unit test-specific tests in `src/unit/__tests__/`

## Contributing

When contributing to Arcanist:
1. Follow the existing code patterns and conventions
2. Add appropriate tests for new functionality
3. Update documentation for new features
4. Ensure backward compatibility
5. Follow the project's coding standards

## Future Considerations

1. **Modern PHP Support**: Ensure compatibility with newer PHP versions
2. **Enhanced Plugin System**: Improved extension capabilities
3. **Performance Optimization**: Continued performance improvements
4. **Security Enhancements**: Ongoing security improvements
5. **Documentation**: Maintain comprehensive documentation

---

*This documentation covers the architecture of Uber's Arcanist fork. For general guidance, refer to the upstream Phabricator documentation.*