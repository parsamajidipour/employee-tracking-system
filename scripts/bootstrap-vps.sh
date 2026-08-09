#!/usr/bin/env bash
set -Eeuo pipefail

DEPLOY_USER="${1:?deploy user is required}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run this script as root." >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  apt-get update
  apt-get install -y ca-certificates curl git openssl ufw
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  . /etc/os-release
  printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu %s stable\n' \
    "$(dpkg --print-architecture)" "$VERSION_CODENAME" > /etc/apt/sources.list.d/docker.list
  apt-get update
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

systemctl enable --now docker
usermod -aG docker "$DEPLOY_USER"

if command -v ufw >/dev/null 2>&1; then
  ufw allow 22/tcp
  ufw allow 3000/tcp
  ufw allow 8000/tcp
  ufw allow 8080/tcp
  ufw --force enable
fi
