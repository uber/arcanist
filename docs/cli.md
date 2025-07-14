# Arcanist CLI Commands

This document provides a comprehensive overview of all available CLI commands in Arcanist (`arc`).

## Overview

Arcanist is a code review and revision management utility that provides a command-line interface for interacting with Phabricator. The `arc` command supports various workflows for managing code reviews, linting, testing, and repository operations.

## Core Commands

### Code Review & Differential

#### `arc diff`
Generate a Differential diff or revision from local changes.

**Usage:**
```bash
arc diff [paths] (svn)
arc diff [commit] (git, hg)
```

**Key Options:**
- `--message, -m`: Use specified message instead of prompting
- `--message-file, -F`: Read revision information from file
- `--use-commit-message, -C`: Read revision information from a specific commit
- `--edit`: Edit revision information before updating (git/hg)
- `--create`: Always create a new revision
- `--update`: Always update a specific revision
- `--nolint`: Do not run lint
- `--nounit`: Do not run unit tests
- `--autoland`: Autoland this change (skips interactive prompt)
- `--noautoland`: Do not autoland this change (skips interactive prompt)

**Supports:** git, svn, hg

#### `arc submit`
Submit a revision for review.

**Usage:**
```bash
arc submit [revision]
```

**Supports:** git, svn, hg

#### `arc upload`
Upload a diff to Differential.

**Usage:**
```bash
arc upload [paths]
```

### Code Quality & Testing

#### `arc lint`
Run static analysis on changes to check for mistakes.

**Usage:**
```bash
arc lint [options] [paths]
arc lint [options] --rev [rev]
```

**Key Options:**
- `--lintall`: Show all lint warnings, not just those on changed lines
- `--only-changed`: Show lint warnings just on changed lines
- `--rev`: Lint changes since a specific revision
- `--output`: Output format (summary, json, none, compiler, xml)
- `--outfile`: Output results to a file
- `--apply-patches`: Apply patches suggested by lint without prompting
- `--never-apply-patches`: Never apply patches suggested by lint
- `--amend-all`: Amend HEAD with all patches suggested by lint
- `--amend-autofixes`: Amend HEAD with autofix patches suggested by lint
- `--everything`: Lint all tracked files in the working copy
- `--severity`: Set minimum message severity
- `--cache`: Enable/disable cache

**Supports:** git, svn, hg

#### `arc unit`
Run unit tests.

**Usage:**
```bash
arc unit [options] [paths]
```

**Supports:** git, svn, hg

#### `arc cover`
Generate code coverage reports.

**Usage:**
```bash
arc cover [options]
```

### Repository Management

#### `arc commit`
Commit changes to the repository.

**Usage:**
```bash
arc commit [options]
```

#### `arc amend`
Amend the most recent commit.

**Usage:**
```bash
arc amend [options]
```

#### `arc branch`
Create or switch to a branch.

**Usage:**
```bash
arc branch [branch-name]
```

#### `arc bookmark`
Create or switch to a bookmark (Mercurial).

**Usage:**
```bash
arc bookmark [bookmark-name]
```

#### `arc revert`
Revert changes in the working copy.

**Usage:**
```bash
arc revert [paths]
```

#### `arc backout`
Backout a commit.

**Usage:**
```bash
arc backout [commit]
```

### Feature Management

#### `arc feature`
Manage feature branches.

**Usage:**
```bash
arc feature [subcommand]
```

#### `arc stack`
Manage revision stacks.

**Usage:**
```bash
arc stack [subcommand]
```

#### `arc land`
Land a revision to the repository.

**Usage:**
```bash
arc land [revision]
```

### Configuration & Setup

#### `arc set-config`
Set configuration values.

**Usage:**
```bash
arc set-config [key] [value]
```

#### `arc get-config`
Get configuration values.

**Usage:**
```bash
arc get-config [key]
```

#### `arc alias`
Manage command aliases.

**Usage:**
```bash
arc alias [alias] [command]
```

#### `arc upgrade`
Upgrade Arcanist to the latest version.

**Usage:**
```bash
arc upgrade
```

### Information & Utilities

#### `arc help`
Show help information.

**Usage:**
```bash
arc help [command]
arc help --full
```

#### `arc version`
Show Arcanist version.

**Usage:**
```bash
arc version
```

#### `arc which`
Show which Arcanist installation is being used.

**Usage:**
```bash
arc which
```

#### `arc list`
List available commands.

**Usage:**
```bash
arc list
```

#### `arc browse`
Open a revision in the web browser.

**Usage:**
```bash
arc browse [revision]
```

#### `arc paste`
Create a paste.

**Usage:**
```bash
arc paste [file]
```

#### `arc export`
Export a revision.

**Usage:**
```bash
arc export [revision]
```

#### `arc download`
Download a revision.

**Usage:**
```bash
arc download [revision]
```

### Patch Management

#### `arc patch`
Apply a patch.

**Usage:**
```bash
arc patch [patch]
```

#### `arc bulk-patch`
Apply multiple patches.

**Usage:**
```bash
arc bulk-patch [patches]
```

### Time Tracking (Phrequent)

#### `arc start`
Start tracking time.

**Usage:**
```bash
arc start [task]
```

#### `arc stop`
Stop tracking time.

**Usage:**
```bash
arc stop
```

#### `arc time`
Show time tracking information.

**Usage:**
```bash
arc time
```

### Task Management

#### `arc tasks`
List tasks.

**Usage:**
```bash
arc tasks [options]
```

#### `arc todo`
Show TODO items.

**Usage:**
```bash
arc todo
```

### Revision Management

#### `arc close`
Close a revision.

**Usage:**
```bash
arc close [revision]
```

#### `arc close-revision`
Close a specific revision.

**Usage:**
```bash
arc close-revision [revision]
```

### File Operations

#### `arc weld`
Weld files together.

**Usage:**
```bash
arc weld [files]
```

#### `arc liberate`
Liberate a library.

**Usage:**
```bash
arc liberate [library]
```

### Development & Debugging

#### `arc call-conduit`
Make a Conduit API call.

**Usage:**
```bash
arc call-conduit [method] [params]
```

#### `arc shell-complete`
Generate shell completion.

**Usage:**
```bash
arc shell-complete [shell]
```

#### `arc anoid`
Generate an anoid.

**Usage:**
```bash
arc anoid
```

#### `arc flag`
Manage flags.

**Usage:**
```bash
arc flag [subcommand]
```

### Installation & Setup

#### `arc install-certificate`
Install SSL certificate.

**Usage:**
```bash
arc install-certificate
```

### Linter Management

#### `arc linters`
List available linters.

**Usage:**
```bash
arc linters
```

## Global Options

All commands support these global options:

- `--trace`: Show underlying commands and full stack traces
- `--no-ansi`: Output in plain ASCII text only
- `--ansi`: Use formatting even in environments that probably don't support it
- `--load-phutil-library`: Load a specific library
- `--conduit-uri`: Connect to a specific Phabricator install
- `--conduit-token`: Use a specific authentication token
- `--conduit-version`: Mock client version in protocol handshake
- `--conduit-timeout`: Set Conduit timeout (in seconds)
- `--config`: Specify runtime configuration values
- `--skip-arcconfig`: Skip reading .arcconfig
- `--arcrc-file`: Use a specific arcrc file

## Getting Help

For detailed help on any command:

```bash
arc help [command]
```

For a full list of commands with descriptions:

```bash
arc help --full
```

## Examples

### Basic Workflow
```bash
# Create a diff for review
arc diff

# Run lint on your changes
arc lint

# Run unit tests
arc unit

# Submit for review
arc submit
```

### Advanced Usage
```bash
# Create a diff with a specific message
arc diff --message "Fix bug in login system"

# Lint only changed lines
arc lint --only-changed

# Apply lint patches automatically
arc lint --apply-patches

# Create a new feature branch
arc feature start new-feature

# Land a revision
arc land D123
```

## Notes

- Most commands require a working copy of a supported version control system (git, svn, hg)
- Many commands require authentication with a Phabricator instance
- Commands may have different behavior depending on the VCS being used
- Use `arc help [command]` for detailed information about specific commands and their options