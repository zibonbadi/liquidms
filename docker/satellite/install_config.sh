#!/bin/sh

cat << EOF >> /var/www/liquidms/config.yaml
---
db: # liquidMS DB connection settings
   dsn: "${MYSQL_DATABASE:-""}" # ODBC DSN string; by name
   user: "${MYSQL_USER:-""}"
   password: "${MYSQL_PASSWORD:-""}" #Keep this secret
...
EOF
