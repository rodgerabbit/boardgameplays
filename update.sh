#!/bin/bash

# Boardgame Plays & Statistics Platform - Update Script
# This script prepares the application after updates are made

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# UID and Group ID variables
export UID=$(id -u)
export GID=$(id -g)

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

# Detect if using Docker
is_docker_setup() {
    if [ -f "docker-compose.yml" ] || [ -f "docker-compose.yaml" ]; then
        if docker compose ps 2>/dev/null | grep -q "boardgameplays" || docker-compose ps 2>/dev/null | grep -q "boardgameplays"; then
            return 0
        fi
    fi
    return 1
}

# Get docker compose command
docker_compose_cmd() {
    if docker compose version >/dev/null 2>&1; then
        echo "docker compose"
    else
        echo "docker-compose"
    fi
}

# Update using Docker
update_with_docker() {
    local DOCKER_COMPOSE=$(docker_compose_cmd)
    
    # Ensure containers are running
    print_info "Ensuring Docker containers are running..."
    $DOCKER_COMPOSE up -d
    sleep 3
    
    # Pull latest code (if git repo)
    if [ -d ".git" ]; then
        print_step "Updating Code from Git"
        if git diff --quiet HEAD; then
            print_info "Pulling latest changes..."
            git pull || print_warning "Git pull failed or not on a branch"
        else
            print_warning "You have uncommitted changes. Skipping git pull."
            read -p "Continue anyway? (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                exit 1
            fi
        fi
    fi
    
    # Update PHP dependencies
    print_step "Updating PHP Dependencies"
    print_info "Updating Composer dependencies..."
    $DOCKER_COMPOSE exec -T app composer update --no-interaction --prefer-dist --optimize-autoloader
    print_success "PHP dependencies updated"
    
    # Update Node dependencies
    print_step "Updating Node Dependencies"
    print_info "Updating npm dependencies..."
    $DOCKER_COMPOSE exec -T node npm update
    print_success "Node dependencies updated"
    
    # Run database migrations
    print_step "Running Database Migrations"
    print_info "Running migrations..."
    $DOCKER_COMPOSE exec -T app php artisan migrate --force
    print_success "Database migrations completed"
    
    # Clear all caches
    print_step "Clearing Application Caches"
    print_info "Clearing configuration cache..."
    $DOCKER_COMPOSE exec -T app php artisan config:clear
    print_info "Clearing application cache..."
    $DOCKER_COMPOSE exec -T app php artisan cache:clear
    print_info "Clearing route cache..."
    $DOCKER_COMPOSE exec -T app php artisan route:clear
    print_info "Clearing view cache..."
    $DOCKER_COMPOSE exec -T app php artisan view:clear
    print_info "Clearing event cache..."
    $DOCKER_COMPOSE exec -T app php artisan event:clear || true
    print_success "All caches cleared"
    
    # Rebuild frontend assets
    print_step "Rebuilding Frontend Assets"
    print_info "Building production assets..."
    $DOCKER_COMPOSE exec -T node npm run build
    print_success "Frontend assets rebuilt"
    
    # Optimize application
    print_step "Optimizing Application"
    print_info "Caching configuration..."
    $DOCKER_COMPOSE exec -T app php artisan config:cache
    print_info "Caching routes..."
    $DOCKER_COMPOSE exec -T app php artisan route:cache
    print_info "Caching views..."
    $DOCKER_COMPOSE exec -T app php artisan view:cache
    print_success "Application optimized"
    
    # Restart Horizon if it exists
    if $DOCKER_COMPOSE exec -T app php artisan horizon:status >/dev/null 2>&1; then
        print_info "Restarting Laravel Horizon..."
        $DOCKER_COMPOSE exec -T app php artisan horizon:terminate || true
        print_success "Horizon will restart automatically"
    fi
    
    # Restart queue workers (if not using Horizon)
    print_info "Queue workers will pick up changes automatically"
    
    return 0
}

# Update locally (without Docker)
update_local() {
    # Pull latest code (if git repo)
    if [ -d ".git" ]; then
        print_step "Updating Code from Git"
        if git diff --quiet HEAD; then
            print_info "Pulling latest changes..."
            git pull || print_warning "Git pull failed or not on a branch"
        else
            print_warning "You have uncommitted changes. Skipping git pull."
            read -p "Continue anyway? (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                exit 1
            fi
        fi
    fi
    
    # Update PHP dependencies
    print_step "Updating PHP Dependencies"
    print_info "Updating Composer dependencies..."
    composer update --no-interaction --prefer-dist --optimize-autoloader
    print_success "PHP dependencies updated"
    
    # Update Node dependencies
    print_step "Updating Node Dependencies"
    print_info "Updating npm dependencies..."
    npm update
    print_success "Node dependencies updated"
    
    # Run database migrations
    print_step "Running Database Migrations"
    print_info "Running migrations..."
    php artisan migrate --force
    print_success "Database migrations completed"
    
    # Clear all caches
    print_step "Clearing Application Caches"
    print_info "Clearing configuration cache..."
    php artisan config:clear
    print_info "Clearing application cache..."
    php artisan cache:clear
    print_info "Clearing route cache..."
    php artisan route:clear
    print_info "Clearing view cache..."
    php artisan view:clear
    print_info "Clearing event cache..."
    php artisan event:clear || true
    print_success "All caches cleared"
    
    # Rebuild frontend assets
    print_step "Rebuilding Frontend Assets"
    print_info "Building production assets..."
    npm run build
    print_success "Frontend assets rebuilt"
    
    # Optimize application
    print_step "Optimizing Application"
    print_info "Caching configuration..."
    php artisan config:cache
    print_info "Caching routes..."
    php artisan route:cache
    print_info "Caching views..."
    php artisan view:cache
    print_success "Application optimized"
    
    # Restart Horizon if it exists
    if php artisan horizon:status >/dev/null 2>&1; then
        print_info "Restarting Laravel Horizon..."
        php artisan horizon:terminate || true
        print_success "Horizon will restart automatically"
    else
        print_info "If you're running queue workers manually, restart them to pick up changes"
    fi
    
    return 0
}

# Run tests (optional)
run_tests() {
    print_step "Running Tests"
    
    if is_docker_setup; then
        local DOCKER_COMPOSE=$(docker_compose_cmd)
        print_info "Running test suite..."
        if $DOCKER_COMPOSE exec -T app php artisan test; then
            print_success "All tests passed"
        else
            print_error "Some tests failed"
            return 1
        fi
    else
        print_info "Running test suite..."
        if php artisan test; then
            print_success "All tests passed"
        else
            print_error "Some tests failed"
            return 1
        fi
    fi
}

# Main execution
main() {
    # Check if we're in the project root
    if [ ! -f "artisan" ] && [ ! -f "composer.json" ]; then
        print_error "This script must be run from the project root directory"
        print_info "Please navigate to the project root and run: ./update.sh"
        exit 1
    fi
    
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  Boardgame Plays & Statistics Platform - Update      ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    # Detect setup method
    if is_docker_setup; then
        print_info "Detected Docker setup"
        UPDATE_METHOD="Docker"
        update_with_docker
    else
        print_info "Detected local setup"
        UPDATE_METHOD="Local"
        update_local
    fi
    
    # Ask about running tests
    echo ""
    read -p "Do you want to run tests? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        if ! run_tests; then
            print_warning "Tests failed, but update completed"
        fi
    fi
    
    # Final summary
    print_step "Update Complete!"
    
    echo ""
    print_success "Your application has been updated and is ready to use!"
    echo ""
    echo -e "${BLUE}Update Method:${NC} $UPDATE_METHOD"
    echo ""
    echo -e "${YELLOW}What was updated:${NC}"
    echo "  ✓ Code (if git repository)"
    echo "  ✓ PHP dependencies (Composer)"
    echo "  ✓ Node dependencies (npm)"
    echo "  ✓ Database migrations"
    echo "  ✓ Application caches cleared"
    echo "  ✓ Frontend assets rebuilt"
    echo "  ✓ Application optimized"
    echo ""
    
    if [ "$UPDATE_METHOD" = "Docker" ]; then
        echo -e "${YELLOW}Next Steps:${NC}"
        echo "  - Application is available at: http://localhost:8080"
        echo "  - View logs: docker compose logs -f"
        echo "  - Check container status: docker compose ps"
    else
        echo -e "${YELLOW}Next Steps:${NC}"
        echo "  - Start development server: composer run dev"
        echo "  - Or start manually: php artisan serve"
    fi
    echo ""
    echo -e "${GREEN}Happy coding! 🎲${NC}"
    echo ""
}

# Run main function
main
