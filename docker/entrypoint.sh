#!/bin/sh
set -e

echo "Running Laravel upgrade scripts..."

# Wait for the database to be ready
if [ -n "$DB_HOST" ]; then
  echo "Waiting for database connection..."
  until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'DB connected'; } catch (Exception \$e) { echo '...'; sleep(3); }"; do
    echo "Retrying database connection..."
  done
fi

# Auto-generate APP_KEY on first boot if it wasn't supplied via the environment.
# The key is persisted in the storage volume (shared with the scheduler container)
# so restarts and container recreation keep using the same key instead of
# invalidating existing sessions / encrypted data.
APP_KEY_FILE="/var/www/html/storage/app/.app_key"
if [ -z "$APP_KEY" ]; then
  if [ -s "$APP_KEY_FILE" ]; then
    APP_KEY=$(cat "$APP_KEY_FILE")
  else
    echo "No APP_KEY set, generating one..."
    APP_KEY=$(php artisan key:generate --show --no-interaction)
    mkdir -p "$(dirname "$APP_KEY_FILE")"
    # Write to a temp file and rename into place so a killed/interrupted boot
    # never leaves a truncated key file for the next boot to blindly reuse.
    APP_KEY_TMP="$APP_KEY_FILE.$$.tmp"
    printf '%s' "$APP_KEY" > "$APP_KEY_TMP"
    chmod 600 "$APP_KEY_TMP"
    mv "$APP_KEY_TMP" "$APP_KEY_FILE"
  fi
  export APP_KEY
fi

# Only the main app container runs migrations/optimization; the scheduler
# container (RUNS_SCHEDULER=TRUE) waits for it via depends_on: service_healthy,
# so by the time it starts the schema is already up to date.
if [ "$RUNS_SCHEDULER" != "TRUE" ]; then
  # migrate --force is safe to run on every boot: Laravel tracks applied
  # migrations and skips them, so this is a no-op after the first run.
  php artisan down || true
  php artisan migrate --force
  php artisan app:import:sync-system-profiles --no-interaction
  php artisan optimize:clear
  php artisan optimize
  php artisan up
fi

echo "Adjusting storage permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
# The broad chmod above would otherwise leave the encryption key group/world-readable.
if [ -f "$APP_KEY_FILE" ]; then
  chmod 600 "$APP_KEY_FILE"
fi

if [ "$RUNS_SCHEDULER" = "TRUE" ]; then
  echo "Startup complete. Launching scheduler/queue supervisor..."
  exec /usr/bin/supervisord -c /etc/supervisord.conf
else
  echo "Startup complete. Launching Apache..."
  exec apache2-foreground
fi
