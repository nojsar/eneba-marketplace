#!/bin/sh
# Runs every time the container starts, before Apache accepts any traffic.
# If a step fails, the container stops and Render keeps the previous version live.
set -e

php bin/migrate.php          # apply new migrations; does nothing if already up to date
php bin/seed.php --if-empty  # first start only: fill an empty database with demo data

# Replace this shell with Apache, so Apache receives Render's stop signal directly.
exec apache2-foreground
