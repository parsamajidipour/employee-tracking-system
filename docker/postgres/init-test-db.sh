#!/bin/sh
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -c "CREATE DATABASE api_testing;"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "api_testing" \
    -c "CREATE EXTENSION IF NOT EXISTS postgis;"
