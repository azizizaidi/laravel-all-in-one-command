# Changelog

All notable changes to `laravel-all-in-one-command` will be documented in this file.

## [Unreleased]

### Added
- Initial release of Laravel All-in-One Command package
- Interactive `make:feature` command for generating complete CRUD functionality
- Support for generating:
  - Models with proper structure
  - Migrations with table creation
  - Factories with model binding
  - Seeders with optional DatabaseSeeder integration
  - Controllers (Resource, Invokable, Basic)
  - Form Requests (Store/Update)
  - Service classes with optional interfaces
  - Policies with AuthServiceProvider registration
  - Tests (Unit/Feature)
  - Blade views (CRUD templates)
  - Routes (Web/API) with automatic registration
  - Scheduled task commands
- Smart namespace handling for organized code structure
- Comprehensive error handling and validation
- File existence checking to prevent overwrites
- Automatic directory creation
- Service interface binding in AppServiceProvider

### Features
- Interactive prompts for selective file generation
- Custom namespace support for controllers and services
- Automatic route registration in web.php and api.php
- Bootstrap-friendly view templates
- PHPDoc comments and proper code formatting
- Comprehensive test suite with Orchestra Testbench

### Requirements
- PHP 8.1 or higher
- Laravel 10.0 or 11.0
- Illuminate packages: console, support, filesystem
