#!/bin/bash
set -e

# Configure git to trust the working directory
# This fixes the "dubious ownership" error when running git commands in Docker
# Using --local instead of --global to avoid permission issues
if [ -d "/var/www/html/.git" ]; then
    cd /var/www/html
    git config --local --add safe.directory /var/www/html 2>/dev/null || true
fi

# Execute the original command
exec "$@"