#!/bin/bash

set -e

echo "🚀 Starting Boardgame Plays & Statistics Platform..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker and try again."
    exit 1
fi

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "❌ docker-compose is not installed. Please install Docker Compose."
    exit 1
fi

# Start containers
echo "📦 Starting Docker containers..."
docker-compose up -d

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
sleep 5

# Install PHP dependencies if vendor doesn't exist
if [ ! -d "vendor" ]; then
    echo "📥 Installing PHP dependencies..."
    docker-compose exec -T app composer install
fi

# Install Node dependencies if node_modules doesn't exist
if [ ! -d "node_modules" ]; then
    echo "📥 Installing Node dependencies..."
    docker-compose exec -T node npm install
fi

# Create .env if it doesn't exist
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    docker-compose exec -T app php artisan key:generate
fi

# Run migrations
echo "🗄️  Running database migrations..."
docker-compose exec -T app php artisan migrate --force || true

echo ""
echo "✅ Setup complete!"
echo ""
echo "🌐 Access the application at: http://localhost:8080"
echo "🔍 Meilisearch dashboard: http://localhost:7700"
echo ""
echo "Useful commands:"
echo "  - View logs: docker-compose logs -f"
echo "  - Stop containers: docker-compose down"
echo "  - Run artisan: docker-compose exec app php artisan [command]"
echo "  - Run npm: docker-compose exec node npm [command]"

