#!/bin/sh
set -eu

: "${POSTGRES_PASSWORD:?Falta POSTGRES_PASSWORD}"
: "${POSTGRES_USER:?Falta POSTGRES_USER}"
: "${POSTGRES_DB:?Falta POSTGRES_DB}"
: "${DB_USERNAME:?Falta DB_USERNAME}"

export PGPASSWORD="$POSTGRES_PASSWORD"
psql -h postgres -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
  -v ON_ERROR_STOP=1 -v db_name="$POSTGRES_DB" -v app_user="$DB_USERNAME" <<'SQL'
SELECT format('REVOKE CREATE ON DATABASE %I FROM %I', :'db_name', :'app_user') \gexec
SQL
