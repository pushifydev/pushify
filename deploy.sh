#!/bin/bash

#################################################
# Pushify Deployment Script
# Usage: ./deploy.sh [environment]
# Environments: production, beta, staging
#################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENT=${1:-production}
COMPOSE_FILE="docker-compose.prod.yml"
APP_VERSION=$(cat VERSION 2>/dev/null || echo "latest")

echo -e "${GREEN}🚀 Pushify Deployment Script${NC}"
echo -e "${YELLOW}Environment: ${ENVIRONMENT}${NC}"
echo -e "${YELLOW}Version: ${APP_VERSION}${NC}"
echo ""

# Function: Check prerequisites
check_prerequisites() {
    echo -e "${YELLOW}📋 Checking prerequisites...${NC}"

    if ! command -v docker &> /dev/null; then
        echo -e "${RED}❌ Docker is not installed${NC}"
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        echo -e "${RED}❌ Docker Compose is not installed${NC}"
        exit 1
    fi

    if [ ! -f ".env.prod" ]; then
        echo -e "${RED}❌ .env.prod file not found${NC}"
        echo -e "${YELLOW}💡 Copy .env.prod.example to .env.prod and configure it${NC}"
        exit 1
    fi

    echo -e "${GREEN}✅ Prerequisites checked${NC}"
}

# Function: Pull latest code
pull_code() {
    echo -e "${YELLOW}📥 Pulling latest code...${NC}"

    git fetch --all --tags

    if [ "$ENVIRONMENT" == "production" ]; then
        git checkout master
        git pull origin master
    elif [ "$ENVIRONMENT" == "beta" ]; then
        git checkout beta
        git pull origin beta
    else
        echo -e "${RED}❌ Unknown environment: ${ENVIRONMENT}${NC}"
        exit 1
    fi

    echo -e "${GREEN}✅ Code updated${NC}"
}

# Function: Pull Docker images
pull_images() {
    echo -e "${YELLOW}🐳 Pulling Docker images...${NC}"

    export APP_VERSION
    docker-compose -f ${COMPOSE_FILE} pull

    echo -e "${GREEN}✅ Images pulled${NC}"
}

# Function: Stop old containers
stop_containers() {
    echo -e "${YELLOW}⏸️  Stopping old containers...${NC}"

    docker-compose -f ${COMPOSE_FILE} down --remove-orphans

    echo -e "${GREEN}✅ Containers stopped${NC}"
}

# Function: Start new containers
start_containers() {
    echo -e "${YELLOW}🚀 Starting new containers...${NC}"

    export APP_VERSION
    docker-compose -f ${COMPOSE_FILE} up -d

    echo -e "${GREEN}✅ Containers started${NC}"
}

# Function: Run database migrations
run_migrations() {
    echo -e "${YELLOW}🔄 Running database migrations...${NC}"

    docker-compose -f ${COMPOSE_FILE} exec -T app \
        php bin/console doctrine:migrations:migrate --no-interaction

    echo -e "${GREEN}✅ Migrations completed${NC}"
}

# Function: Clear cache
clear_cache() {
    echo -e "${YELLOW}🧹 Clearing cache...${NC}"

    docker-compose -f ${COMPOSE_FILE} exec -T app \
        php bin/console cache:clear --env=prod

    docker-compose -f ${COMPOSE_FILE} exec -T app \
        php bin/console cache:warmup --env=prod

    echo -e "${GREEN}✅ Cache cleared${NC}"
}

# Function: Health check
health_check() {
    echo -e "${YELLOW}🏥 Running health check...${NC}"

    sleep 5

    if curl -f http://localhost/health > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Application is healthy${NC}"
    else
        echo -e "${RED}❌ Health check failed${NC}"
        echo -e "${YELLOW}📋 Showing container logs:${NC}"
        docker-compose -f ${COMPOSE_FILE} logs --tail=50 app
        exit 1
    fi
}

# Function: Show status
show_status() {
    echo -e "${YELLOW}📊 Container status:${NC}"
    docker-compose -f ${COMPOSE_FILE} ps
}

# Main deployment flow
main() {
    check_prerequisites
    pull_code
    pull_images
    stop_containers
    start_containers

    echo -e "${YELLOW}⏳ Waiting for containers to start...${NC}"
    sleep 10

    run_migrations
    clear_cache
    health_check
    show_status

    echo ""
    echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
    echo -e "${GREEN}🎉 Pushify ${APP_VERSION} is now running in ${ENVIRONMENT}${NC}"
    echo ""
}

# Run main function
main
