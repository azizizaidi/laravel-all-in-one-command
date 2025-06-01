# Laravel All-in-One Command

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azizizaidi/laravel-all-in-one-command.svg?style=flat-square)](https://packagist.org/packages/azizizaidi/laravel-all-in-one-command)
[![Total Downloads](https://img.shields.io/packagist/dt/azizizaidi/laravel-all-in-one-command.svg?style=flat-square)](https://packagist.org/packages/azizizaidi/laravel-all-in-one-command)

A Laravel package that generates all necessary files for a feature with a single command. This package helps you quickly scaffold complete CRUD functionality including models, migrations, controllers, form requests, services, tests, policies, routes, and views.

## Features

- 🚀 **One Command, Complete Feature**: Generate all related files with a single artisan command
- 🎯 **Interactive Setup**: Choose exactly what you need through interactive prompts
- 📁 **Smart Organization**: Automatically organizes files with proper namespacing
- 🔧 **Customizable**: Supports custom namespaces and directory structures
- 🧪 **Test Ready**: Generates both unit and feature tests
- 🛡️ **Security First**: Includes form requests and policies
- 🎨 **View Templates**: Basic Blade templates for quick prototyping

## Installation

You can install the package via composer:

```bash
composer require azizizaidi/laravel-all-in-one-command --dev
```

The package will automatically register itself via Laravel's package discovery.

## Usage

### Basic Usage

Generate a complete feature with the interactive command:

```bash
php artisan make:feature Order
```

This will prompt you to choose which components to generate:

- Model
- Migration
- Factory
- Seeder
- Controller (Resource/Invokable/Basic)
- Form Requests (Store/Update)
- Service Class with optional Interface
- Web Routes
- API Routes
- Tests (Unit/Feature)
- Policy
- Scheduled Task Command
- Blade Views (CRUD)

### Example Output

When you run `php artisan make:feature Order`, the command will generate:

```
app/Models/Order.php
database/migrations/2024_01_01_000000_create_orders_table.php
database/factories/OrderFactory.php
database/seeders/OrderSeeder.php
app/Http/Controllers/OrderController.php
app/Http/Requests/StoreOrderRequest.php
app/Http/Requests/UpdateOrderRequest.php
app/Services/OrderService.php
app/Services/Contracts/OrderServiceInterface.php
app/Policies/OrderPolicy.php
tests/Unit/OrderTest.php
tests/Feature/OrderTest.php
resources/views/orders/index.blade.php
resources/views/orders/create.blade.php
resources/views/orders/edit.blade.php
resources/views/orders/show.blade.php
```

### Advanced Usage

#### Custom Namespaces

You can specify custom controller namespaces:

```bash
php artisan make:feature Shop/Product
```

This will create controllers in `App\Http\Controllers\Shop\` namespace.

#### What Gets Generated

**Models**: Basic Eloquent model with HasFactory trait
**Migrations**: Standard Laravel migration with proper table naming
**Controllers**: Resource, Invokable, or Basic controllers with proper model binding
**Form Requests**: Store and Update request classes with basic validation structure
**Services**: Service classes with optional interfaces and automatic binding
**Tests**: PHPUnit test classes with basic test structure
**Policies**: Model policies with standard CRUD methods
**Views**: Basic Blade templates with Bootstrap-friendly markup
**Routes**: Automatic route registration in web.php and/or api.php

## Configuration

The package works out of the box with sensible defaults, but you can customize:

- Controller namespaces
- Service organization
- Route paths
- View directories

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or 11.0

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email azizikuis@gmail.com instead of using the issue tracker.

## Credits

- [Azizi Zaidi](https://github.com/azizizaidi)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
