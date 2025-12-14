# Boardgame Plays & Statistics Platform

A Laravel-based boardgame plays and statistics platform for groups.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Inertia.js + Vue 3
- **Database**: PostgreSQL
- **Cache/Sessions/Queues**: Redis
- **Search**: Meilisearch (full-text and faceted search)
- **Object Storage**: Local storage
- **Realtime**: Laravel Echo + WebSockets
- **Background Jobs**: Laravel Queue Workers with Supervisor/Horizon
- **Notifications**: Laravel Notifications (Postmark/SendGrid for email, Discord webhooks, in-app via Redis + DB)
- **API Documentation**: OpenAPI/Swagger
- **Monitoring**: Sentry (errors), Prometheus/Grafana (metrics), Grafana Loki (logs)

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js 20.19+ or 22.12+ and npm
- PostgreSQL
- Redis
- Meilisearch (optional, for search functionality)

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd boardgameplays
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install JavaScript dependencies:
```bash
npm install
```

4. Copy the environment file:
```bash
cp .env.example .env
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Configure your `.env` file with:
   - Database credentials (PostgreSQL)
   - Redis connection details
   - Meilisearch connection (if using)
   - Other service credentials

7. Run migrations:
```bash
php artisan migrate
```

8. Build frontend assets:
```bash
npm run build
```

## Development

### Running the Development Server

```bash
composer run dev
```

This will start:
- Laravel development server
- Queue worker
- Log viewer (Pail)
- Vite dev server

### Running Tests

```bash
composer test
```

Or with PHPUnit directly:
```bash
php artisan test
```

### Code Style

The project uses Laravel Pint for code formatting:
```bash
./vendor/bin/pint
```

## Project Structure

```
app/
  Console/          # Artisan commands
  Exceptions/       # Custom exceptions
  Http/
    Controllers/
      Api/
        V1/         # API v1 controllers
    Middleware/     # Custom middleware
    Requests/       # Form request validation
    Resources/      # API resources
  Models/           # Eloquent models
  Services/         # Business logic services
  Repositories/     # Data access repositories
  DTOs/             # Data transfer objects
  Jobs/             # Queue jobs
  Notifications/    # Notification classes
  Events/           # Event classes
  Listeners/        # Event listeners
  Policies/         # Authorization policies
tests/
  Unit/             # Unit tests
  Feature/          # Feature tests
  Integration/      # Integration tests
resources/
  js/
    components/     # Vue components
    pages/          # Inertia pages
    composables/    # Vue composables
    types/          # TypeScript types
  views/            # Blade templates
database/
  factories/        # Model factories
  migrations/       # Database migrations
  seeders/          # Database seeders
routes/
  api.php           # API routes
  web.php           # Web routes
```

## Key Principles

1. **Clear, Full Names**: Every identifier must be self-documenting
2. **Comprehensive Testing**: Every feature must have tests
3. **Code Quality**: Follow Laravel best practices and PSR standards
4. **API-First**: Design APIs before frontend
5. **Observability**: Monitor, log, and track errors

## License

MIT License - see LICENSE file for details
