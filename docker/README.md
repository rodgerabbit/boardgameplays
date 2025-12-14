# Docker Development Environment

This project includes a complete Docker Compose setup for local development.

## Quick Start

1. **Start all services:**
   ```bash
   docker-compose up -d
   ```

2. **Install PHP dependencies:**
   ```bash
   docker-compose exec app composer install
   ```

3. **Install Node dependencies:**
   ```bash
   docker-compose exec node npm install
   ```

4. **Copy environment file:**
   ```bash
   cp .env.example .env
   ```

5. **Generate application key:**
   ```bash
   docker-compose exec app php artisan key:generate
   ```

6. **Run migrations:**
   ```bash
   docker-compose exec app php artisan migrate
   ```

7. **Build frontend assets:**
   ```bash
   docker-compose exec node npm run build
   ```

8. **Access the application:**
   - Web: http://localhost:8080
   - Meilisearch: http://localhost:7700
   - PostgreSQL: localhost:5432
   - Redis: localhost:6379

## Services

- **app**: PHP-FPM container with Laravel application
- **nginx**: Web server
- **postgres**: PostgreSQL database
- **redis**: Redis cache and queue
- **meilisearch**: Full-text search engine
- **node**: Node.js for frontend asset compilation

## Common Commands

### Run Artisan commands
```bash
docker-compose exec app php artisan [command]
```

### Run Composer commands
```bash
docker-compose exec app composer [command]
```

### Run NPM commands
```bash
docker-compose exec node npm [command]
```

### Run tests
```bash
docker-compose exec app php artisan test
```

### View logs
```bash
docker-compose logs -f [service_name]
```

### Stop all services
```bash
docker-compose down
```

### Stop and remove volumes (clean slate)
```bash
docker-compose down -v
```

### Rebuild containers
```bash
docker-compose build --no-cache
```

## Environment Variables

The Docker Compose setup uses the following default environment variables:

- `DB_CONNECTION=pgsql`
- `DB_HOST=postgres`
- `DB_PORT=5432`
- `DB_DATABASE=boardgameplays`
- `DB_USERNAME=boardgameplays`
- `DB_PASSWORD=boardgameplays`
- `REDIS_HOST=redis`
- `REDIS_PORT=6379`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `MEILISEARCH_HOST=http://meilisearch:7700`
- `MEILISEARCH_KEY=masterKey`

You can override these in your `.env` file or create a `docker-compose.override.yml` file.

## Troubleshooting

### Permission Issues
If you encounter permission issues, you may need to fix file ownership:
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```

### Container won't start
Check logs:
```bash
docker-compose logs app
```

### Database connection issues
Ensure the postgres container is running:
```bash
docker-compose ps
```

### Clear cache
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

