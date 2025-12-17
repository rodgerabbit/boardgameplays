#!/bin/bash

# Boardgame Plays & Statistics Platform - Setup Script
# This script helps initialize the repository from scratch

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_step() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check prerequisites for Docker setup
check_docker_prerequisites() {
    print_step "Checking Docker Prerequisites"
    
    local missing=0
    
    if ! command_exists docker; then
        print_error "Docker is not installed"
        print_info "Please install Docker: https://docs.docker.com/get-docker/"
        missing=1
    else
        if ! docker info >/dev/null 2>&1; then
            print_error "Docker is not running"
            print_info "Please start Docker and try again"
            missing=1
        else
            print_success "Docker is installed and running"
        fi
    fi
    
    if ! command_exists docker-compose && ! docker compose version >/dev/null 2>&1; then
        print_error "Docker Compose is not installed"
        print_info "Please install Docker Compose: https://docs.docker.com/compose/install/"
        missing=1
    else
        print_success "Docker Compose is available"
    fi
    
    if [ $missing -eq 1 ]; then
        exit 1
    fi
}

# Check prerequisites for local setup
check_local_prerequisites() {
    print_step "Checking Local Prerequisites"
    
    local missing=0
    
    # Check PHP
    if ! command_exists php; then
        print_error "PHP is not installed"
        print_info "Please install PHP 8.2 or higher"
        missing=1
    else
        PHP_VERSION=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
        REQUIRED_VERSION="8.2"
        if [ "$(printf '%s\n' "$REQUIRED_VERSION" "$PHP_VERSION" | sort -V | head -n1)" != "$REQUIRED_VERSION" ]; then
            print_error "PHP version $PHP_VERSION is installed, but PHP 8.2+ is required"
            missing=1
        else
            print_success "PHP $PHP_VERSION is installed"
        fi
    fi
    
    # Check Composer
    if ! command_exists composer; then
        print_error "Composer is not installed"
        print_info "Please install Composer: https://getcomposer.org/download/"
        missing=1
    else
        print_success "Composer is installed"
    fi
    
    # Check Node.js
    if ! command_exists node; then
        print_error "Node.js is not installed"
        print_info "Please install Node.js 20.19+ or 22.12+: https://nodejs.org/"
        missing=1
    else
        NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d. -f1,2)
        REQUIRED_VERSION="20.19"
        if [ "$(printf '%s\n' "$REQUIRED_VERSION" "$NODE_VERSION" | sort -V | head -n1)" != "$REQUIRED_VERSION" ]; then
            print_warning "Node.js version $NODE_VERSION detected. Recommended: 20.19+ or 22.12+"
        else
            print_success "Node.js $NODE_VERSION is installed"
        fi
    fi
    
    # Check npm
    if ! command_exists npm; then
        print_error "npm is not installed"
        missing=1
    else
        print_success "npm is installed"
    fi
    
    # Check PostgreSQL (optional warning)
    if ! command_exists psql; then
        print_warning "PostgreSQL client not found (optional, but required for database operations)"
    else
        print_success "PostgreSQL client is available"
    fi
    
    # Check Redis (optional warning)
    if ! command_exists redis-cli; then
        print_warning "Redis client not found (optional, but required for cache/queues)"
    else
        print_success "Redis client is available"
    fi
    
    if [ $missing -eq 1 ]; then
        exit 1
    fi
}

# Setup using Docker
setup_with_docker() {
    print_step "Setting Up with Docker"
    
    # Set UID and GID if not already set (for docker-compose user mapping)
    if [ -z "$UID" ]; then
        export UID=$(id -u)
    fi
    if [ -z "$GID" ]; then
        export GID=$(id -g)
    fi
    print_info "Using UID: $UID, GID: $GID for container user mapping"
    
    # Check if .env.example exists
    if [ ! -f ".env.example" ]; then
        print_error ".env.example file not found"
        print_info "Please ensure you're in the project root directory"
        exit 1
    fi
    
    # Start Docker containers
    print_info "Starting Docker containers..."
    if docker compose version >/dev/null 2>&1; then
        docker compose up -d
    else
        docker-compose up -d
    fi
    
    # Wait for services to be ready
    print_info "Waiting for services to be ready..."
    sleep 10
    
    # Check if containers are running
    if docker compose ps 2>/dev/null | grep -q "Up" || docker-compose ps 2>/dev/null | grep -q "Up"; then
        print_success "Docker containers are running"
    else
        print_error "Some Docker containers failed to start"
        print_info "Check logs with: docker compose logs"
        exit 1
    fi
    
    # Install PHP dependencies
    print_info "Installing PHP dependencies..."
    if docker compose version >/dev/null 2>&1; then
        docker compose exec -T app composer install --no-interaction
    else
        docker-compose exec -T app composer install --no-interaction
    fi
    print_success "PHP dependencies installed"
    
    # Install Node dependencies
    print_info "Installing Node dependencies..."
    if docker compose version >/dev/null 2>&1; then
        docker compose exec -T node npm install
    else
        docker-compose exec -T node npm install
    fi
    print_success "Node dependencies installed"
    
    # Setup environment file
    if [ ! -f ".env" ]; then
        print_info "Creating .env file from .env.example..."
        cp .env.example .env
        print_success ".env file created"
        
        # Generate application key
        print_info "Generating application key..."
        if docker compose version >/dev/null 2>&1; then
            docker compose exec -T app php artisan key:generate
        else
            docker-compose exec -T app php artisan key:generate
        fi
        print_success "Application key generated"
    else
        print_info ".env file already exists, skipping..."
    fi
    
    # Set proper permissions
    print_info "Setting storage and cache permissions..."
    if docker compose version >/dev/null 2>&1; then
        docker compose exec -T app chmod -R 775 storage bootstrap/cache || true
        docker compose exec -T app chown -R www-data:www-data storage bootstrap/cache || true
    else
        docker-compose exec -T app chmod -R 775 storage bootstrap/cache || true
        docker-compose exec -T app chown -R www-data:www-data storage bootstrap/cache || true
    fi
    print_success "Permissions set"
    
    # Run migrations
    print_info "Running database migrations..."
    if docker compose version >/dev/null 2>&1; then
        docker compose exec -T app php artisan migrate --force
    else
        docker-compose exec -T app php artisan migrate --force
    fi
    print_success "Database migrations completed"
    
    # Ask about seeding
    read -p "Do you want to seed the database with sample data? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_info "Seeding database..."
        if docker compose version >/dev/null 2>&1; then
            docker compose exec -T app php artisan db:seed
        else
            docker-compose exec -T app php artisan db:seed
        fi
        print_success "Database seeded"
    fi
    
    # Build frontend assets
    print_info "Building frontend assets..."
    if docker compose version >/dev/null 2>&1; then
        docker compose exec -T node npm run build
    else
        docker-compose exec -T node npm run build
    fi
    print_success "Frontend assets built"
    
    # Clear caches
    print_info "Clearing application caches..."
    if docker compose version >/dev/null 2>&1; then
        docker compose exec -T app php artisan config:clear
        docker compose exec -T app php artisan cache:clear
        docker compose exec -T app php artisan route:clear
        docker compose exec -T app php artisan view:clear
    else
        docker-compose exec -T app php artisan config:clear
        docker-compose exec -T app php artisan cache:clear
        docker-compose exec -T app php artisan route:clear
        docker-compose exec -T app php artisan view:clear
    fi
    print_success "Caches cleared"
}

# Setup locally (without Docker)
setup_local() {
    print_step "Setting Up Locally"
    
    # Check if .env.example exists
    if [ ! -f ".env.example" ]; then
        print_error ".env.example file not found"
        print_info "Please ensure you're in the project root directory"
        exit 1
    fi
    
    # Install PHP dependencies
    print_info "Installing PHP dependencies..."
    composer install --no-interaction
    print_success "PHP dependencies installed"
    
    # Install Node dependencies
    print_info "Installing Node dependencies..."
    npm install
    print_success "Node dependencies installed"
    
    # Setup environment file
    if [ ! -f ".env" ]; then
        print_info "Creating .env file from .env.example..."
        cp .env.example .env
        print_success ".env file created"
        
        # Generate application key
        print_info "Generating application key..."
        php artisan key:generate
        print_success "Application key generated"
        
        print_warning "Please configure your .env file with:"
        print_info "  - Database credentials (PostgreSQL)"
        print_info "  - Redis connection details"
        print_info "  - Meilisearch connection (if using)"
        print_info "  - Other service credentials"
        
        read -p "Press Enter to continue after configuring .env..."
    else
        print_info ".env file already exists, skipping..."
    fi
    
    # Set proper permissions
    print_info "Setting storage and cache permissions..."
    chmod -R 775 storage bootstrap/cache || true
    print_success "Permissions set"
    
    # Test database connection
    print_info "Testing database connection..."
    if php artisan migrate:status >/dev/null 2>&1; then
        print_success "Database connection successful"
    else
        print_error "Database connection failed"
        print_info "Please check your .env file and ensure PostgreSQL is running"
        exit 1
    fi
    
    # Run migrations
    print_info "Running database migrations..."
    php artisan migrate --force
    print_success "Database migrations completed"
    
    # Ask about seeding
    read -p "Do you want to seed the database with sample data? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_info "Seeding database..."
        php artisan db:seed
        print_success "Database seeded"
    fi
    
    # Build frontend assets
    print_info "Building frontend assets..."
    npm run build
    print_success "Frontend assets built"
    
    # Clear caches
    print_info "Clearing application caches..."
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    print_success "Caches cleared"
}

# Main execution
main() {
    # Check if we're in the project root
    if [ ! -f "artisan" ] && [ ! -f "composer.json" ]; then
        print_error "This script must be run from the project root directory"
        print_info "Please navigate to the project root and run: ./setup.sh"
        exit 1
    fi
    
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  Boardgame Plays & Statistics Platform - Setup        ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    # Ask user for setup method
    echo "How would you like to set up the application?"
    echo "  1) Docker (Recommended for development)"
    echo "  2) Local installation (requires manual service setup)"
    echo ""
    read -p "Enter your choice (1 or 2): " -n 1 -r
    echo ""
    
    if [[ $REPLY =~ ^[1]$ ]]; then
        check_docker_prerequisites
        setup_with_docker
        SETUP_METHOD="Docker"
        APP_URL="http://localhost:8080"
        MEILISEARCH_URL="http://localhost:7700"
    elif [[ $REPLY =~ ^[2]$ ]]; then
        check_local_prerequisites
        setup_local
        SETUP_METHOD="Local"
        APP_URL="http://localhost:8000"
        MEILISEARCH_URL="http://localhost:7700"
    else
        print_error "Invalid choice. Please run the script again and select 1 or 2."
        exit 1
    fi
    
    # Final summary
    print_step "Setup Complete!"
    
    echo ""
    print_success "Your application is ready to use!"
    echo ""
    echo -e "${BLUE}Setup Method:${NC} $SETUP_METHOD"
    echo -e "${BLUE}Application URL:${NC} $APP_URL"
    if [ "$SETUP_METHOD" = "Docker" ]; then
        echo -e "${BLUE}Meilisearch Dashboard:${NC} $MEILISEARCH_URL"
    fi
    echo ""
    echo -e "${YELLOW}Next Steps:${NC}"
    echo "  1. Access the application at: $APP_URL"
    if [ "$SETUP_METHOD" = "Docker" ]; then
        echo "  2. View logs: docker compose logs -f"
        echo "  3. Run artisan commands: docker compose exec app php artisan [command]"
        echo "  4. Run npm commands: docker compose exec node npm [command]"
        echo "  5. Stop containers: docker compose down"
    else
        echo "  2. Start the development server: composer run dev"
        echo "  3. Or start manually: php artisan serve"
        echo "  4. Run tests: composer test"
    fi
    echo ""
    echo -e "${GREEN}Happy coding! 🎲${NC}"
    echo ""
}

# Run main function
main
