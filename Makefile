.PHONY: help up down build restart logs shell composer npm artisan migrate test

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

up: ## Start all Docker containers
	docker-compose up -d

down: ## Stop all Docker containers
	docker-compose down

build: ## Build Docker containers
	docker-compose build --no-cache

restart: ## Restart all Docker containers
	docker-compose restart

logs: ## Show logs from all containers
	docker-compose logs -f

shell: ## Open shell in app container
	docker-compose exec app bash

composer: ## Run composer install
	docker-compose exec app composer install

npm: ## Run npm install
	docker-compose exec node npm install

artisan: ## Run artisan command (usage: make artisan cmd="migrate")
	docker-compose exec app php artisan $(cmd)

migrate: ## Run database migrations
	docker-compose exec app php artisan migrate

test: ## Run tests
	docker-compose exec app php artisan test

setup: ## Initial setup (install dependencies, generate key, migrate)
	docker-compose up -d
	docker-compose exec app composer install
	docker-compose exec node npm install
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker-compose exec app php artisan key:generate
	docker-compose exec app php artisan migrate
	@echo "Setup complete! Access the app at http://localhost:8080"

