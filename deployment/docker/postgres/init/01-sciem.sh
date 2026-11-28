#!/bin/sh
set -eu

: "${DB_USERNAME:?Falta DB_USERNAME}"
: "${DB_PASSWORD:?Falta DB_PASSWORD}"
case "$DB_PASSWORD" in
  REEMPLAZAR*|CLAVE_*) echo 'DB_PASSWORD aún es un marcador de ejemplo.' >&2; exit 1 ;;
esac
if [ "$DB_USERNAME" = "$POSTGRES_USER" ]; then
  echo 'DB_USERNAME debe ser distinto del superusuario de PostgreSQL.' >&2
  exit 1
fi

psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  -v ON_ERROR_STOP=1 -v app_user="$DB_USERNAME" \
  -v app_pass="$DB_PASSWORD" -v db_name="$POSTGRES_DB" <<'SQL'
CREATE EXTENSION IF NOT EXISTS pgcrypto;
SELECT format('CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L', :'app_user', :'app_pass')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_user') \gexec
SELECT format('GRANT CONNECT, CREATE ON DATABASE %I TO %I', :'db_name', :'app_user') \gexec
SELECT format('GRANT USAGE, CREATE ON SCHEMA public TO %I', :'app_user') \gexec
SQL
