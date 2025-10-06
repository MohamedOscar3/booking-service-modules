# Service Booking API

A comprehensive service booking management system built with Laravel 12 using a modular architecture. This application allows customers to book services, providers to manage their availability and services, and administrators to access detailed analytics and reports.

![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)
![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)
![API Documentation](https://img.shields.io/badge/API%20Docs-Scribe-orange.svg)

## 🚀 Features

- **Multi-Role Authentication**: Support for customers, service providers, and administrators
- **Service Management**: Complete CRUD operations for services and categories
- **Availability Management**: Flexible availability scheduling with recurring and one-time slots
- **Booking System**: Full booking lifecycle with status management (pending, confirmed, completed, cancelled)
- **Admin Analytics**: Comprehensive reporting system with export capabilities
- **API Documentation**: Auto-generated documentation with Scribe
- **Modular Architecture**: Clean, maintainable code structure using Laravel Modules
- **Export System**: Excel exports with queue processing for large datasets
- **Docker Support**: Fully containerized development environment

## 📋 Table of Contents

- [Project Structure](#-project-structure)
- [Installation](#-installation)
  - [Local Installation](#local-installation)
  - [Docker Installation](#docker-installation-recommended)
- [Configuration](#-configuration)
- [Modules Overview](#-modules-overview)
- [Adding New Modules](#-adding-new-modules)
- [API Documentation](#-api-documentation)
- [PHPDoc Generation](#-phpdoc-generation)
- [Code Quality](#-code-quality)
- [Testing](#-testing)
- [Docker Usage](#-docker-usage)
- [Contributing](#-contributing)

## 🏗️ Project Structure

```
service_booking/
├── app/                          # Core Laravel application
│   ├── Http/Controllers/         # Base controllers
│   ├── Services/                 # Shared services
│   └── Policies/                 # Authorization policies
├── Modules/                      # Modular architecture
│   ├── Auth/                     # Authentication module
│   ├── AvailabilityManagement/   # Provider availability management
│   ├── Booking/                  # Booking system and admin reports
│   ├── Category/                 # Service categories
│   └── Service/                  # Service management
├── docker/                       # Docker configuration
│   ├── nginx/                    # Nginx configuration
│   ├── php/                      # PHP-FPM configuration
│   └── supervisor/               # Supervisor configuration
├── config/                       # Configuration files
├── database/                     # Database migrations and seeders
├── resources/                    # Views and assets
└── storage/                      # Logs, cache, and generated files
```

### Module Structure

Each module follows a consistent structure:

```
Modules/{ModuleName}/
├── app/
│   ├── DTOs/                     # Data Transfer Objects
│   ├── Enums/                    # Enumerations
│   ├── Exports/                  # Excel export classes
│   ├── Http/
│   │   ├── Controllers/Api/      # API Controllers
│   │   ├── Requests/             # Form Request validation
│   │   └── Resources/            # API Resources
│   ├── Jobs/                     # Queued jobs
│   ├── Models/                   # Eloquent models
│   ├── Policies/                 # Authorization policies
│   ├── Providers/                # Service providers
│   └── Services/                 # Business logic services
├── config/                       # Module configuration
├── database/
│   ├── factories/                # Model factories
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Database seeders
├── resources/
│   └── views/                    # Blade templates
├── routes/
│   ├── api.php                   # API routes
│   └── web.php                   # Web routes
└── tests/                        # Module tests
```

## 🚀 Installation

### Local Installation

#### Prerequisites

- PHP 8.3 or higher
- Composer
- MySQL 8.0 or higher
- Node.js & NPM (for frontend assets)

#### Step 1: Clone Repository

```bash
git clone https://github.com/your-repo/service_booking.git
cd service_booking
```

#### Step 2: Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install
```

#### Step 3: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

#### Step 4: Database Setup

```bash
# Configure your database in .env file
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=service_booking
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed the database (optional)
php artisan db:seed
```

#### Step 5: Additional Configuration

```bash
# Generate Sanctum secret key
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

#### Step 6: Start Development Server

```bash
# Start Laravel development server
php artisan serve

# In another terminal, start asset compilation
npm run dev
```

### Docker Installation (Recommended)

The project includes a complete Docker setup with the following services:

- **Laravel App** - PHP 8.3-FPM with Supervisor (runs queue workers and scheduler)
- **Nginx** - Web server (accessible on port 8000)
- **MySQL** - Main database server (port 3306)
- **MySQL Test** - Test database server (port 3307)
- **Redis** - Cache and queue driver (port 6379)
- **MailHog** - Email testing tool (SMTP: 1025, Web UI: 8025)

#### Quick Start with Docker

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Build and start all containers
docker-compose up -d --build

# 3. Install Composer dependencies
docker-compose exec app composer install

# 4. Generate application key
docker-compose exec app php artisan key:generate

# 5. Run migrations
docker-compose exec app php artisan migrate

# 6. (Optional) Seed database
docker-compose exec app php artisan db:seed
```

#### Access Your Application

- **Laravel Application**: http://localhost:8000
- **API Documentation**: http://localhost:8000/docs
- **PHP Documentation**: http://localhost:8000/developer-docs/php
- **MailHog UI**: http://localhost:8000/mailhog (Username: `admin`, Password: `mailhog123`)
- **MySQL (Main)**: localhost:3308
- **MySQL (Test)**: localhost:3307

## ⚙️ Configuration

### Authentication

The application uses Laravel Sanctum for API authentication. Configure the following in your `.env`:

```env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,::1
```

### Queue Configuration

For background job processing (Excel exports):

```env
QUEUE_CONNECTION=redis  # when using Docker
# or
QUEUE_CONNECTION=database  # for local setup
```

Run the queue worker:
```bash
# Local
php artisan queue:work

# Docker
docker-compose exec app supervisorctl status
```

### Mail Configuration

**Local Setup:**
```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

**Docker Setup:**
```env
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
```

Access MailHog UI at http://localhost:8000/mailhog to view sent emails. (Username: `admin`, Password: `mailhog123`)

### Database Configuration

**Local Setup:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=service_booking
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

**Docker Setup:**
```env
# Main Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=service_booking
DB_USERNAME=service_booking
DB_PASSWORD=secret

# Test Database
DB_TEST_CONNECTION=mysql
DB_TEST_HOST=mysql_test
DB_TEST_PORT=3306
DB_TEST_DATABASE=service_booking_test
DB_TEST_USERNAME=service_booking_test
DB_TEST_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_STORE=redis
```

## 📦 Modules Overview

### 🔐 Auth Module
- User registration and authentication
- Role-based access control (Customer, Provider, Admin)
- JWT token management

### 📋 Category Module
- Service category management
- CRUD operations for categories

### 🛠️ Service Module
- Service creation and management
- Provider-specific services
- Service pricing and duration

### 📅 AvailabilityManagement Module
- Provider availability scheduling
- Recurring and one-time availability slots
- Time slot management

### 📖 Booking Module
- Complete booking lifecycle
- Status management
- Customer and provider notes
- Admin reporting and analytics
- Excel export functionality

## ➕ Adding New Modules

### Using Artisan Command

```bash
# Create a new module
php artisan module:make ModuleName

# Create module with specific components
php artisan module:make ModuleName --api
```

### Manual Module Structure

1. **Create Module Directory Structure**:
```bash
mkdir -p Modules/YourModule/{app,config,database,resources,routes,tests}
mkdir -p Modules/YourModule/app/{DTOs,Http/Controllers/Api,Http/Requests,Http/Resources,Models,Services,Policies}
mkdir -p Modules/YourModule/database/{factories,migrations,seeders}
```

2. **Create Service Provider**:
```php
<?php

namespace Modules\YourModule\Providers;

use Illuminate\Support\ServiceProvider;

class YourModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

3. **Register in config/app.php**:
```php
'providers' => [
    // Other providers...
    Modules\YourModule\Providers\YourModuleServiceProvider::class,
],
```

4. **Create Routes**:
```php
// Modules/YourModule/routes/api.php
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('your-resource', YourController::class);
});
```

### Module Development Best Practices

1. **Follow naming conventions**:
   - Controllers: `{Resource}Controller`
   - Models: `{Resource}`
   - Services: `{Resource}Service`
   - DTOs: `{Action}{Resource}DTO`

2. **Use proper documentation**:
   - Add PHPDoc blocks to all methods
   - Use Scribe annotations for API documentation
   - Include `@group` annotations for organization

3. **Implement proper validation**:
   - Create Form Request classes
   - Add `bodyParameters()` method for API documentation

4. **Follow authorization patterns**:
   - Create policies for resource authorization
   - Use middleware for route protection

## 📖 API Documentation

### Generate Documentation

```bash
# Local
php artisan scribe:generate

# Docker
docker-compose exec app php artisan scribe:generate
```

### View Documentation

- **HTML Documentation**: http://localhost:8000/docs
- **Postman Collection**: http://localhost:8000/docs.postman
- **OpenAPI Specification**: http://localhost:8000/docs.openapi

### Documentation Configuration

The API documentation is configured in `config/scribe.php`. Key settings:

```php
'title' => 'Service Booking API Documentation',
'description' => 'A comprehensive service booking API...',
'auth' => [
    'enabled' => true,
    'default' => true,
    'in' => 'bearer',
    'name' => 'Authorization',
    'placeholder' => '{YOUR_AUTH_TOKEN}',
],
```

### Adding Documentation to New Endpoints

1. **Use Scribe annotations**:
```php
/**
 * @group Resource Management
 *
 * @authenticated
 *
 * @queryParam filter string Filter results
 * @response 200 {"data": [...]}
 */
public function index(Request $request)
{
    // Implementation
}
```

2. **Add bodyParameters to Form Requests**:
```php
public function bodyParameters(): array
{
    return [
        'name' => [
            'description' => 'Resource name',
            'example' => 'Example Name',
        ],
    ];
}
```

## 📝 PHPDoc Generation

### Install PHPDocumentor

```bash
# Install globally
composer global require phpdocumentor/phpdocumentor

# Or use the included version
composer install
```

### Generate PHPDoc

```bash
# Local
vendor/bin/phpdoc

# Docker
docker-compose exec app phpdoc

# Generate documentation for specific modules
vendor/bin/phpdoc -d Modules/ModuleName -t docs/api/ModuleName
```

### View PHPDoc

- **PHP Documentation**: http://localhost:8000/developer-docs/php

### PHPDoc Configuration

The `phpdoc.xml` configuration is included in the project root.

## 🧪 Code Quality

### Laravel Pint (Code Formatting)

```bash
# Local
composer format
# or
vendor/bin/pint

# Docker
docker-compose exec app vendor/bin/pint

# Check formatting without making changes
vendor/bin/pint --test
```

### Larastan (Static Analysis)

```bash
# Local
composer analyse
# or
vendor/bin/phpstan analyse

# Docker
docker-compose exec app vendor/bin/phpstan analyse

# Run with specific level (0-9)
vendor/bin/phpstan analyse --level=5
```

### Configuration Files

- **Pint**: `pint.json`
- **PHPStan**: `phpstan.neon`

## 🧪 Testing

### Run Tests

```bash
# Local
php artisan test

# Docker
docker-compose exec app php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run tests with coverage
php artisan test --coverage

# Run module-specific tests
php artisan test Modules/ModuleName/tests/
```

### Writing Tests

Create tests in module directories:

```bash
# Create a feature test
php artisan make:test --feature Modules/ModuleName/BookingTest

# Create a unit test
php artisan make:test --unit Modules/ModuleName/Services/BookingServiceTest
```

## 🐳 Docker Usage

### Container Management

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f app
docker-compose logs -f nginx
```

### Application Commands

```bash
# Run Artisan commands
docker-compose exec app php artisan [command]

# Access container shell
docker-compose exec app bash

# Run Composer
docker-compose exec app composer [command]

# Run migrations
docker-compose exec app php artisan migrate

# Generate documentation
docker-compose exec app php artisan scribe:generate
docker-compose exec app phpdoc

# Clear cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan view:clear
```

### Queue Management

Queue workers are managed by Supervisor and run automatically inside the app container:

```bash
# View supervisor status
docker-compose exec app supervisorctl status

# Restart workers
docker-compose exec app supervisorctl restart all

# View worker logs
docker-compose exec app tail -f /var/www/storage/logs/worker.log

# View scheduler logs
docker-compose exec app tail -f /var/www/storage/logs/scheduler.log
```

### Database Access

**Using Docker:**
```bash
# Access main database MySQL CLI
docker-compose exec mysql mysql -u service_booking -psecret service_booking

# Access test database MySQL CLI
docker-compose exec mysql_test mysql -u service_booking_test -psecret service_booking_test
```

**Using External Tools:**

Main Database:
- **Host**: localhost
- **Port**: 3308
- **Database**: service_booking
- **Username**: service_booking
- **Password**: secret

Test Database:
- **Host**: localhost
- **Port**: 3307
- **Database**: service_booking_test
- **Username**: service_booking_test
- **Password**: secret

### File Permissions

If you encounter permission issues:

```bash
docker-compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker-compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

### Rebuilding Containers

If you make changes to Dockerfile or need to rebuild:

```bash
docker-compose down
docker-compose up -d --build
```

### Troubleshooting

**Container won't start:**
```bash
# Check logs
docker-compose logs [service-name]

# Restart specific service
docker-compose restart [service-name]
```

**Database connection failed:**
```bash
# Ensure MySQL is ready
docker-compose exec mysql mysqladmin ping -h localhost

# Check database exists
docker-compose exec mysql mysql -u root -psecret -e "SHOW DATABASES;"
```

**Permission denied errors:**
```bash
# Fix storage permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**Queue not processing:**
```bash
# Check supervisor status
docker-compose exec app supervisorctl status

# Restart queue workers
docker-compose exec app supervisorctl restart laravel-worker:*

# Check if supervisor is running
docker-compose exec app ps aux | grep supervisor
```

## 🔧 Development Commands

### Useful Artisan Commands

```bash
# Clear all caches
php artisan optimize:clear

# List all routes
php artisan route:list

# List all available artisan commands
php artisan list

# Generate IDE helper files
php artisan ide-helper:generate
php artisan ide-helper:models
```

### Module Commands

```bash
# List all modules
php artisan module:list

# Enable/disable modules
php artisan module:enable ModuleName
php artisan module:disable ModuleName

# Create module components
php artisan module:make-controller ControllerName ModuleName
php artisan module:make-model ModelName ModuleName
php artisan module:make-migration create_table_name ModuleName
```

## 🤝 Contributing

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/new-feature`
3. **Follow coding standards**: Run `composer format` before committing
4. **Write tests**: Ensure new features have appropriate test coverage
5. **Update documentation**: Add/update API documentation for new endpoints
6. **Commit changes**: Use conventional commit messages
7. **Push to branch**: `git push origin feature/new-feature`
8. **Create Pull Request**

### Coding Standards

- Follow PSR-12 coding standards
- Use type hints for all method parameters and return types
- Write comprehensive PHPDoc blocks
- Maintain test coverage above 80%
- Use dependency injection instead of facades where possible

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🚀 Deployment

### Production Checklist

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false`
3. Configure proper database credentials
4. Set up queue workers with supervisor
5. Configure proper mail settings
6. Set up SSL certificates
7. Configure proper CORS settings
8. Run `php artisan optimize` for production optimizations

### Environment Variables

```env
# Production settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-production-host
DB_DATABASE=your-production-db

# Queue
QUEUE_CONNECTION=redis
REDIS_HOST=your-redis-host

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
```

### Production Considerations for Docker

Before deploying to production:

1. Change all default passwords (database, MailHog)
2. Use proper SSL certificates for Nginx
3. Set appropriate PHP memory limits and timeouts
4. Configure MySQL for production workloads
5. Use Redis password authentication
6. Disable MailHog (use real SMTP service)
7. Remove or secure the test database

### Changing MailHog Password

To change the MailHog basic auth password:

```bash
# Generate new password hash
docker run --rm httpd:alpine htpasswd -nb admin your_new_password

# Update docker/nginx/.htpasswd with the output
# Then restart nginx
docker-compose restart nginx
```
