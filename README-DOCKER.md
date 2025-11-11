# OM-PAY-API - Docker Setup

## Configuration Docker Simplifiée

### Fichiers de configuration
- `Dockerfile` - Configuration de l'image Docker
- `start.sh` - Script de démarrage de l'application
- `docker-compose.yml` - Configuration pour développement local
- `build.sh` - Script pour build et push vers Docker Hub

## Développement Local

### Démarrer l'application
```bash
docker-compose up --build
```

L'application sera accessible sur `http://localhost:8000`

### Arrêter l'application
```bash
docker-compose down
```

## Build et Déploiement

### Build et push vers Docker Hub
```bash
# Avec tag par défaut (latest)
./build.sh

# Avec tag personnalisé
./build.sh v1.0.0
```

### Configuration Render
Utilisez l'image Docker Hub dans votre configuration Render :
```
Image: abzotech/om-pay-api:latest
```

## Variables d'environnement

### Production (.env.production)
- `APP_ENV=production`
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Configuration PostgreSQL (Neon)

### Développement (docker-compose.yml)
- `APP_ENV=local`
- Base de données PostgreSQL locale

## Fonctionnalités

- ✅ PHP 8.3 avec extensions nécessaires
- ✅ Nginx + PHP-FPM
- ✅ PostgreSQL support
- ✅ Migrations et seeders automatiques
- ✅ Documentation Swagger générée
- ✅ Cache optimisé
- ✅ Permissions sécurisées

## Debug

### Logs de l'application
```bash
docker-compose logs app
```

### Accès au container
```bash
docker-compose exec app sh
```

### Test de l'API
```bash
curl http://localhost:8000/api/health