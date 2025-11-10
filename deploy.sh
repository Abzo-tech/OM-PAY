#!/bin/bash

# OM PAY API Deployment Script
# Usage: ./deploy.sh [build|deploy|restart|logs|cleanup]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOCKER_IMAGE_NAME="om-pay-api"
DOCKER_TAG="latest"
DOCKER_HUB_USERNAME="${DOCKER_HUB_USERNAME:-your-dockerhub-username}"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
}

# Generate application key
generate_app_key() {
    if [ ! -f .env.docker ]; then
        log_error ".env.docker file not found!"
        exit 1
    fi

    # Generate Laravel app key
    APP_KEY=$(docker run --rm -v $(pwd):/app -w /app php:8.2-cli php artisan key:generate --show)

    # Update .env.docker with the generated key
    sed -i "s/APP_KEY=.*/APP_KEY=$APP_KEY/" .env.docker

    log_success "Application key generated and updated in .env.docker"
}

# Build Docker images
build_images() {
    log_info "Building Docker images..."

    # Copy environment file
    cp .env.docker .env

    # Build the application image
    docker-compose build --no-cache

    log_success "Docker images built successfully"
}

# Start services
start_services() {
    log_info "Starting Docker services..."

    # Start all services
    docker-compose up -d

    # Wait for services to be healthy
    log_info "Waiting for services to be ready..."
    sleep 10

    # Check if services are running
    if docker-compose ps | grep -q "Up"; then
        log_success "All services are running"
        show_status
    else
        log_error "Some services failed to start"
        show_logs
        exit 1
    fi
}

# Stop services
stop_services() {
    log_info "Stopping Docker services..."
    docker-compose down
    log_success "Services stopped"
}

# Show status
show_status() {
    log_info "Service Status:"
    docker-compose ps
}

# Show logs
show_logs() {
    log_info "Service Logs:"
    docker-compose logs -f --tail=100
}

# Run database migrations
run_migrations() {
    log_info "Running database migrations..."
    docker-compose exec app php artisan migrate --force
    log_success "Migrations completed"
}

# Run database seeders
run_seeders() {
    log_info "Running database seeders..."
    docker-compose exec app php artisan db:seed --force
    log_success "Seeders completed"
}

# Clear cache
clear_cache() {
    log_info "Clearing application cache..."
    docker-compose exec app php artisan config:clear
    docker-compose exec app php artisan cache:clear
    docker-compose exec app php artisan route:clear
    docker-compose exec app php artisan view:clear
    log_success "Cache cleared"
}

# Backup database
backup_database() {
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_FILE="backup_${TIMESTAMP}.sql"

    log_info "Creating database backup: $BACKUP_FILE"
    docker-compose exec db pg_dump -U om_pay_user om_pay_db > $BACKUP_FILE
    log_success "Database backup created: $BACKUP_FILE"
}

# Push to Docker Hub
push_to_dockerhub() {
    if [ -z "$DOCKER_HUB_USERNAME" ] || [ "$DOCKER_HUB_USERNAME" = "your-dockerhub-username" ]; then
        log_error "Please set DOCKER_HUB_USERNAME environment variable"
        exit 1
    fi

    IMAGE_NAME="${DOCKER_HUB_USERNAME}/${DOCKER_IMAGE_NAME}:${DOCKER_TAG}"

    log_info "Tagging image for Docker Hub..."
    docker tag ${DOCKER_IMAGE_NAME}:latest $IMAGE_NAME

    log_info "Pushing to Docker Hub..."
    docker push $IMAGE_NAME

    log_success "Image pushed to Docker Hub: $IMAGE_NAME"
}

# Deploy to production
deploy_production() {
    log_info "Deploying to production..."

    # Pull latest images
    docker-compose pull

    # Stop services
    docker-compose down

    # Start services with new images
    docker-compose up -d

    # Run migrations
    run_migrations

    # Clear cache
    clear_cache

    log_success "Production deployment completed"
}

# Cleanup
cleanup() {
    log_info "Cleaning up Docker resources..."

    # Remove stopped containers
    docker container prune -f

    # Remove unused images
    docker image prune -f

    # Remove unused volumes
    docker volume prune -f

    log_success "Cleanup completed"
}

# Health check
health_check() {
    log_info "Running health checks..."

    # Check if API is responding
    if curl -f http://localhost/api/health > /dev/null 2>&1; then
        log_success "API health check passed"
    else
        log_error "API health check failed"
        exit 1
    fi

    # Check database connection
    if docker-compose exec -T db pg_isready -U om_pay_user -d om_pay_db > /dev/null 2>&1; then
        log_success "Database health check passed"
    else
        log_error "Database health check failed"
        exit 1
    fi
}

# Main script
case "${1:-help}" in
    "build")
        check_docker
        generate_app_key
        build_images
        ;;
    "deploy")
        check_docker
        start_services
        run_migrations
        run_seeders
        clear_cache
        health_check
        ;;
    "restart")
        stop_services
        start_services
        ;;
    "stop")
        stop_services
        ;;
    "status")
        show_status
        ;;
    "logs")
        show_logs
        ;;
    "migrate")
        run_migrations
        ;;
    "seed")
        run_seeders
        ;;
    "cache")
        clear_cache
        ;;
    "backup")
        backup_database
        ;;
    "push")
        push_to_dockerhub
        ;;
    "production")
        deploy_production
        ;;
    "cleanup")
        cleanup
        ;;
    "health")
        health_check
        ;;
    "full-deploy")
        check_docker
        generate_app_key
        build_images
        start_services
        run_migrations
        run_seeders
        clear_cache
        health_check
        log_success "Full deployment completed successfully!"
        ;;
    "help"|*)
        echo "OM PAY API Deployment Script"
        echo ""
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  build        Build Docker images"
        echo "  deploy       Deploy and start services"
        echo "  restart      Restart all services"
        echo "  stop         Stop all services"
        echo "  status       Show service status"
        echo "  logs         Show service logs"
        echo "  migrate      Run database migrations"
        echo "  seed         Run database seeders"
        echo "  cache        Clear application cache"
        echo "  backup       Create database backup"
        echo "  push         Push images to Docker Hub"
        echo "  production   Deploy to production"
        echo "  cleanup      Clean up Docker resources"
        echo "  health       Run health checks"
        echo "  full-deploy  Complete deployment (build + deploy)"
        echo "  help         Show this help message"
        echo ""
        echo "Environment variables:"
        echo "  DOCKER_HUB_USERNAME  Your Docker Hub username"
        ;;
esac