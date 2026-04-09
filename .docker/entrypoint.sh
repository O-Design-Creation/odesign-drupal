#!/bin/bash
set -e

DRUPAL_ROOT=/var/www/html
WEB_ROOT=${DRUPAL_ROOT}/web

# Attendre que la base de données soit disponible
echo "[entrypoint] Attente de la base de données..."
until php -r "new PDO('mysql:host=${DB_HOST};dbname=${DB_NAME}', '${DB_USER}', '${DB_PASS}');" 2>/dev/null; do
  sleep 2
done
echo "[entrypoint] Base de données disponible."

# Écrire settings.local.php depuis les variables d'environnement
cat > ${WEB_ROOT}/sites/default/settings.local.php << EOF
<?php
\$databases['default']['default'] = [
  'driver'    => 'mysql',
  'host'      => '${DB_HOST}',
  'port'      => '${DB_PORT:-3306}',
  'database'  => '${DB_NAME}',
  'username'  => '${DB_USER}',
  'password'  => '${DB_PASS}',
  'prefix'    => '',
  'namespace' => 'Drupal\Core\Database\Driver\mysql',
  'driver'    => 'mysql',
];
\$settings['hash_salt'] = '${DRUPAL_HASH_SALT}';
\$settings['trusted_host_patterns'] = ['^' . preg_quote('${DRUPAL_TRUSTED_HOST}') . '$'];
\$config['system.site']['mail'] = '${SITE_MAIL:-contact@atelierodesign.fr}';
EOF

# Drush post-déploiement
cd ${DRUPAL_ROOT}
echo "[entrypoint] drush updatedb..."
vendor/bin/drush updatedb -y --no-interaction 2>/dev/null || true

echo "[entrypoint] drush config:import..."
vendor/bin/drush config:import -y --no-interaction 2>/dev/null || true

echo "[entrypoint] drush cache:rebuild..."
vendor/bin/drush cache:rebuild --no-interaction 2>/dev/null || true

echo "[entrypoint] Démarrage des services..."
exec "$@"
