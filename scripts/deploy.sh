#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly COMPOSE_FILE=docker-compose.prod.yml
readonly ENV_FILE=.env
readonly SERVER_IP=164.90.163.27

docker_compose() {
  if docker info >/dev/null 2>&1; then
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  else
    sudo docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  fi
}

random_hex() {
  openssl rand -hex "$1"
}

if [ ! -f "$ENV_FILE" ]; then
  umask 077
  cat > "$ENV_FILE" <<EOF
POSTGRES_DB=inspection
POSTGRES_USER=inspection
POSTGRES_PASSWORD=$(random_hex 32)
POSTGRES_PORT=5432
REDIS_PORT=6379
API_PORT=8000
REVERB_PORT=8080
PANEL_PORT=3000
SERVER_HOST=$SERVER_IP
SERVER_SCHEME=http
APP_KEY=base64:$(openssl rand -base64 32 | tr -d '\n')
REVERB_APP_ID=$(random_hex 12)
REVERB_APP_KEY=$(random_hex 16)
REVERB_APP_SECRET=$(random_hex 32)
SEED_ADMIN_NAME=Administrator
SEED_ADMIN_EMAIL=admin@inspection.local
SEED_ADMIN_PASSWORD=$(random_hex 20)
SESSION_SECURE_COOKIE=false
TRACKING_RETENTION_DAYS=7
EOF
  chmod 600 "$ENV_FILE"
fi

chmod 600 "$ENV_FILE"

required_values=(
  POSTGRES_PASSWORD APP_KEY SERVER_HOST REVERB_APP_ID REVERB_APP_KEY
  REVERB_APP_SECRET SEED_ADMIN_EMAIL SEED_ADMIN_PASSWORD
)
for key in "${required_values[@]}"; do
  grep -Eq "^${key}=.+$" "$ENV_FILE" || {
    echo "Missing required value: $key" >&2
    exit 1
  }
done

bash scripts/install-basemap.sh

if docker_compose ps --status running postgres --quiet 2>/dev/null | grep -q .; then
  mkdir -p backups
  chmod 700 backups
  backup_file="backups/postgres-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
  docker_compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip -9 > "$backup_file"
  find backups -type f -name 'postgres-*.sql.gz' -mtime +7 -delete
fi

docker_compose config --quiet
docker_compose build --pull
docker_compose up -d --remove-orphans --wait

curl --fail --silent --show-error --retry 12 --retry-delay 5 "http://127.0.0.1:8000/up" >/dev/null
curl --fail --silent --show-error --retry 12 --retry-delay 5 "http://127.0.0.1:3000/" >/dev/null

docker_compose ps
echo "Deployment completed successfully."
