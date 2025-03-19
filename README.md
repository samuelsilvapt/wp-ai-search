# AI Search Plugin

Replaces the default search with an intelligent search system.

## Installation

Clone the repository and install dependencies:

```bash
composer install
```

## Coding Standards

This project uses [WordPress Coding Standards (WPCS)](https://github.com/WordPress/WordPress-Coding-Standards) via PHP\_CodeSniffer.

### Commands

- Check for coding standards issues:

```bash
composer lint
```

- Automatically fix fixable coding standards issues:

```bash
composer fix
```

### Configuration

Coding standards are defined in the `phpcs.xml` file. The following directories are excluded:

- `vendor/`
- `node_modules/`
- `build/`
- `dist/`
