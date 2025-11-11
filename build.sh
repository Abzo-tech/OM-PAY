#!/bin/bash

# OM-PAY-API Build & Push Script
# Usage: ./build.sh [tag]

set -e

# Default tag
TAG=${1:-latest}
IMAGE_NAME="abzotech/om-pay-api:$TAG"

echo "Building Docker image: $IMAGE_NAME"
docker build -t $IMAGE_NAME .

echo "Pushing to Docker Hub..."
docker push $IMAGE_NAME

echo "✅ Successfully built and pushed: $IMAGE_NAME"
echo ""
echo "To deploy on Render, use image: $IMAGE_NAME"