# Podman Windows Networking Issues

## Problem
Podman on Windows has known networking issues where containers cannot be reached from the host using `localhost` or `127.0.0.1`, even with `-p 5000:5000` or `--network=host`.

## Solutions

### Option 1: Use Docker Desktop (Recommended for Windows)
```powershell
# Install Docker Desktop from https://www.docker.com/products/docker-desktop

# Build and run
docker build -t yaffa-nlp:latest -f docker/nlp-service/Dockerfile docker/nlp-service
docker run -d -p 5000:5000 --name yaffa-nlp yaffa-nlp:latest

# Test
curl http://localhost:5000/health
```

### Option 2: Run Python Flask App Directly on Windows
```powershell
# 1. Install Python 3.11+ from https://www.python.org/downloads/

# 2. Install dependencies
cd docker/nlp-service
pip install -r requirements.txt

# 3. Run the app
python app.py

# Service will be available at http://localhost:5000
```

### Option 3: Use WSL2 with Podman
```bash
# In WSL2:
cd /mnt/c/Users/thean/Herd/jaffa
podman build -t yaffa-nlp:latest -f docker/nlp-service/Dockerfile docker/nlp-service
podman run -d -p 5000:5000 yaffa-nlp:latest

# Access from Windows using WSL2 IP address
```

### Option 4: Use Docker Compose (When using Docker Desktop)
```powershell
# In .env file, ensure:
NLP_SERVICE_URL=http://nlp-service:5000

# Start all services
docker-compose up -d nlp-service

# The service will be accessible from the Laravel container via the 'nlp-service' hostname
```

## Testing the Service

Once running correctly, test with:

```powershell
# Health check
curl http://localhost:5000/health

# From Laravel
php artisan payees:find-duplicates
php artisan transactions:identify-transfers
```

## Current Issue
Podman on Windows doesn't properly bridge container ports to localhost, causing timeouts when Laravel tries to connect to the NLP service.
