#!/bin/bash

# Sync migrations table metadata - only necessary once after upgrade,
# can be removed after that
/usr/bin/php bin/console doctrine:migrations:sync-metadata-storage
# Run migrations
/usr/bin/php bin/console doctrine:migrations:migrate --no-debug --no-interaction