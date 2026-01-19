#!/bin/bash

#
# VitalPBX Asterisk Wallboard - Uninstall Script
#

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║        VitalPBX Asterisk Wallboard Uninstaller             ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Check for instance name
INSTANCE_NAME="${1:-}"

if [ -z "$INSTANCE_NAME" ]; then
    echo "Usage: ./uninstall.sh <instance-name>"
    echo ""
    echo "Available installations:"
    ls -d /opt/wallboard/*/ 2>/dev/null | xargs -n1 basename || echo "  None found"
    echo ""
    exit 1
fi

INSTALL_DIR="/opt/wallboard/$INSTANCE_NAME"

if [ ! -d "$INSTALL_DIR" ]; then
    echo -e "${RED}Installation not found: $INSTALL_DIR${NC}"
    exit 1
fi

echo -e "${YELLOW}This will completely remove the '$INSTANCE_NAME' wallboard installation.${NC}"
echo ""
echo "This includes:"
echo "  - Docker containers"
echo "  - Database and all data"
echo "  - Configuration files"
echo "  - Log files"
echo ""
read -p "Are you sure? Type 'yes' to confirm: " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Uninstall cancelled."
    exit 0
fi

cd "$INSTALL_DIR/docker"

# Determine docker compose command
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi

echo ""
echo -e "${YELLOW}Stopping containers...${NC}"
$DOCKER_COMPOSE down -v 2>/dev/null || true

echo -e "${YELLOW}Removing installation directory...${NC}"
sudo rm -rf "$INSTALL_DIR"

echo ""
echo -e "${GREEN}✓ Uninstall complete${NC}"
echo ""
echo "The '$INSTANCE_NAME' wallboard has been removed."
