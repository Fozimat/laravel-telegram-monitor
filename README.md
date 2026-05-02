# Laravel Telegram Monitor

Lightweight Telegram error monitoring for Laravel applications. This package hooks into Laravel's exception reporting flow and sends important error alerts directly to your Telegram chat or group.

## Features

- Zero extra runtime dependencies beyond Laravel's built-in HTTP client
- Laravel package auto-discovery support
- Duplicate error throttling to reduce alert spam
- Respects Laravel's normal exception reporting flow
- Keeps request payloads, headers, and cookies out of Telegram messages

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, 12.x, or 13.x

## Installation

If the package has already been published to Packagist:

```bash
composer require fozimat/laravel-telegram-monitor
```

If you want to install it directly from GitHub before publishing to Packagist, add this repository entry to your Laravel project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/fozimat/laravel-telegram-monitor.git"
    }
  ]
}
```

Then install the package:

```bash
composer require fozimat/laravel-telegram-monitor
```

## Configuration

The package is auto-discovered by Laravel, so there is no need to register a service provider manually.

Optional: publish the config file if you want to keep package settings in your app config directory:

```bash
php artisan vendor:publish --tag=telegram-monitor-config
```

Add these variables to your application's `.env` file:

```env
ERROR_MONITORING_ENABLED=true
ERROR_MONITORING_LEVEL=error
TELEGRAM_BOT_TOKEN=1234567890:ABCDefGhIjKlMnOpQrStUvWxYz
TELEGRAM_CHAT_ID=-100123456789
APP_NAME="My Laravel App"
APP_ENV=production
```

`ERROR_MONITORING_LEVEL` works as the minimum threshold for reported exceptions. Exceptions handled by this package are treated as `error` severity events.

## Usage

After installation and environment setup, the package will automatically send reportable exceptions to Telegram.

Example notification:

```text
Laravel Telegram Monitor
Environment: production
Level: ERROR
Time: 2026-05-02 10:30:00
URL: https://example.com/api/v1/sync
User ID: 42
Message: SQLSTATE[HY000] [2002] Connection refused
File: /var/www/app/Services/DatabaseService.php:45
```

## Testing

You can trigger a test exception with a temporary route:

```php
use Illuminate\Support\Facades\Route;

Route::get('/test-telegram-error', function () {
    throw new \Exception('This is a test exception for Laravel Telegram Monitor.');
});
```

Visit `/test-telegram-error` and confirm the message arrives in Telegram.
