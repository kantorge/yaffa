#!/usr/bin/env bash

set -euo pipefail

SERVICE_NAME="app"
TEST_LOG_MESSAGE="Local CI smoke test"
HEALTH_CHECK_RETRIES=30
HEALTH_CHECK_INTERVAL=2
COMPOSE_FILE="docker/docker-compose.yml"

print_step() {
  echo
  echo "============================================================"
  echo "$1"
  echo "============================================================"
}

cleanup() {
  print_step "Cleaning up containers"
  docker compose -f "$COMPOSE_FILE" down -v || true
}

trap cleanup EXIT

print_step "Building Docker image"
docker build -f docker/Dockerfile -t kantorge/yaffa:latest .

print_step "Setting up environment"
cp .env.example docker/.env
sed -i 's|^DB_HOST=.*|DB_HOST=db|' docker/.env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" docker/.env

print_step "Starting core services"
docker compose -f "$COMPOSE_FILE" up -d db redis app

print_step "Waiting for services to start"
for i in $(seq 1 "$HEALTH_CHECK_RETRIES"); do
  if docker compose -f "$COMPOSE_FILE" ps --filter status=running --services 2>/dev/null | grep -q "^${SERVICE_NAME}$"; then
    echo "Service '$SERVICE_NAME' is running (attempt $i)"
    break
  fi
  if [ "$i" -eq "$HEALTH_CHECK_RETRIES" ]; then
    echo "ERROR: Service '$SERVICE_NAME' did not start after $((HEALTH_CHECK_RETRIES * HEALTH_CHECK_INTERVAL)) seconds"
    docker compose -f "$COMPOSE_FILE" logs "$SERVICE_NAME" --tail 30
    exit 1
  fi
  echo "Waiting for service '$SERVICE_NAME'... (attempt $i/$HEALTH_CHECK_RETRIES)"
  sleep "$HEALTH_CHECK_INTERVAL"
done

print_step "Checking running containers"
docker compose -f "$COMPOSE_FILE" ps

APP_CONTAINER=$(docker compose -f "$COMPOSE_FILE" ps --filter status=running -q "$SERVICE_NAME")

if [ -z "$APP_CONTAINER" ]; then
  echo "ERROR: Could not find running container for service '$SERVICE_NAME'"
  exit 1
fi

print_step "Verifying Apache configuration"
docker exec "$APP_CONTAINER" apache2ctl configtest

print_step "Verifying homepage responds"
for i in $(seq 1 "$HEALTH_CHECK_RETRIES"); do
  if curl --fail --silent --show-error --max-time 5 http://localhost > /dev/null 2>&1; then
    echo "Homepage responded successfully (attempt $i)"
    break
  fi
  if [ "$i" -eq "$HEALTH_CHECK_RETRIES" ]; then
    echo "ERROR: Homepage did not respond after $((HEALTH_CHECK_RETRIES * HEALTH_CHECK_INTERVAL)) seconds"
    exit 1
  fi
  echo "Waiting for homepage... (attempt $i/$HEALTH_CHECK_RETRIES)"
  sleep "$HEALTH_CHECK_INTERVAL"
done

print_step "Triggering homepage request for access log verification"
curl --silent --max-time 5 http://localhost > /dev/null
sleep 2

print_step "Checking access logs"
ACCESS_LOGS=$(docker logs "$APP_CONTAINER" 2>&1 | grep 'GET /' || true)

if [ -z "$ACCESS_LOGS" ]; then
  echo "ERROR: No access log entries found"
  exit 1
fi

echo "$ACCESS_LOGS"

ACCESS_LOG_COUNT=$(echo "$ACCESS_LOGS" | wc -l | tr -d ' ')

echo
if [ "$ACCESS_LOG_COUNT" -gt 2 ]; then
  echo "WARNING: Multiple access log entries detected. Please verify there are no duplicate Apache log streams."
else
  echo "Access logging appears healthy"
fi

print_step "Triggering Laravel log entry"
LOG_OUTPUT=$(docker exec -e TEST_LOG_MESSAGE="$TEST_LOG_MESSAGE" "$APP_CONTAINER" php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class); $kernel->bootstrap(); Illuminate\\Support\\Facades\\Log::error(getenv("TEST_LOG_MESSAGE"));' 2>&1 || true)
echo "$LOG_OUTPUT"

if echo "$LOG_OUTPUT" | grep -q "$TEST_LOG_MESSAGE"; then
  echo "Laravel stderr logging verified via docker exec output"
else
  echo "Log entry not printed directly by docker exec; checking container logs"
fi

print_step "Checking Laravel stderr logging"
for i in $(seq 1 10); do
  LARAVEL_LOGS=$(docker logs "$APP_CONTAINER" 2>&1 | grep "$TEST_LOG_MESSAGE" || true)
  if [ -n "$LARAVEL_LOGS" ]; then
    break
  fi
  sleep 1
done

if [ -z "$LARAVEL_LOGS" ]; then
  echo "WARNING: Laravel log entry was not found in container logs"
  echo "This may be expected if LOG_STACK=single"
else
  echo "$LARAVEL_LOGS"
  echo
  echo "Laravel stderr logging appears healthy"
fi

print_step "Inspecting Laravel log files"
docker exec "$APP_CONTAINER" sh -c 'ls -lah storage/logs || true'

print_step "Last 20 lines of container logs"
docker logs "$APP_CONTAINER" --tail 20 2>&1

print_step "Smoke test completed successfully"
