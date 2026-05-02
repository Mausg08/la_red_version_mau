#!/usr/bin/env bash
# ============================================================
# UniLink — setup.sh
# Quick installer for development environment
# ============================================================

set -e

CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${CYAN}"
echo "  ██╗   ██╗███╗   ██╗██╗██╗     ██╗███╗   ██╗██╗  ██╗"
echo "  ██║   ██║████╗  ██║██║██║     ██║████╗  ██║██║ ██╔╝"
echo "  ██║   ██║██╔██╗ ██║██║██║     ██║██╔██╗ ██║█████╔╝ "
echo "  ██║   ██║██║╚██╗██║██║██║     ██║██║╚██╗██║██╔═██╗ "
echo "  ╚██████╔╝██║ ╚████║██║███████╗██║██║ ╚████║██║  ██╗"
echo "   ╚═════╝ ╚═╝  ╚═══╝╚═╝╚══════╝╚═╝╚═╝  ╚═══╝╚═╝  ╚═╝"
echo -e "${NC}"
echo -e "${CYAN}  Red Social Universitaria — Setup Script${NC}\n"

# ---- Check Docker ----
if ! command -v docker &>/dev/null; then
  echo -e "${RED}✗ Docker no encontrado. Instálalo desde https://docker.com${NC}"
  exit 1
fi
echo -e "${GREEN}✓ Docker encontrado: $(docker --version)${NC}"

if ! command -v docker-compose &>/dev/null && ! docker compose version &>/dev/null 2>&1; then
  echo -e "${RED}✗ Docker Compose no encontrado.${NC}"
  exit 1
fi
echo -e "${GREEN}✓ Docker Compose encontrado${NC}"

# ---- Create .env if not exists ----
if [ ! -f ".env" ]; then
  echo -e "${YELLOW}→ Creando .env desde .env.example...${NC}"
  cp .env.example .env
  # Generate a random JWT secret
  JWT_SECRET=$(openssl rand -hex 32 2>/dev/null || cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)
  sed -i "s/CHANGE_THIS_TO_A_SECURE_32_CHAR_STRING_HERE/${JWT_SECRET}/" .env
  echo -e "${GREEN}✓ .env creado con JWT_SECRET aleatorio${NC}"
else
  echo -e "${GREEN}✓ .env ya existe${NC}"
fi

# ---- Create storage directory ----
mkdir -p storage/{posts,marketplace,avatars}
chmod -R 755 storage/
echo -e "${GREEN}✓ Directorio storage/ creado${NC}"

# ---- Create log directory ----
mkdir -p logs
echo -e "${GREEN}✓ Directorio logs/ creado${NC}"

# ---- Start containers ----
echo -e "\n${CYAN}→ Iniciando contenedores Docker...${NC}"
cd docker
docker-compose up -d --build

echo -e "\n${CYAN}→ Esperando que MySQL inicie (30s)...${NC}"
sleep 30

# ---- Run migrations ----
echo -e "\n${CYAN}→ Ejecutando migraciones de base de datos...${NC}"

docker exec unilink_db_feed mysql -u unilink -psecurepassword -e "CREATE DATABASE IF NOT EXISTS unilink_db;" 2>/dev/null || true
docker exec -i unilink_db_feed mysql -u unilink -psecurepassword unilink_db < ../database/migrations/001_schema.sql
echo -e "${GREEN}✓ Migración 001 completada${NC}"

docker exec -i unilink_db_feed mysql -u unilink -psecurepassword < ../database/migrations/002_academic_and_extras.sql
echo -e "${GREEN}✓ Migración 002 completada${NC}"

# ---- Summary ----
echo -e "\n${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  ✅ UniLink instalado correctamente!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "  🌐 App:          ${CYAN}http://localhost${NC}"
echo -e "  🔌 WebSocket:    ${CYAN}http://localhost:3001${NC}"
echo -e "  🗄  MinIO:        ${CYAN}http://localhost:9001${NC}"
echo ""
echo -e "  Para detener:    ${YELLOW}docker-compose down${NC}"
echo -e "  Ver logs:        ${YELLOW}docker-compose logs -f web${NC}"
echo ""
