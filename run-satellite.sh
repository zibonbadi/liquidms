#!/bin/sh

# odbc data source, same info used for creating the db
cat << EOF > /docker-entrypoint-initdb.d/odbc.ini
[liquidms]
Description = LiquidMS database with ODBC
Driver = MariaDB
Server = ${ODBC_HOST:-"127.0.0.1"}
Database = ${MYSQL_DATABASE:-""}
Port = ${ODBC_PORT:-"3306"}
User = ${MYSQL_USER:-""}
Password = ${MYSQL_PASSWORD:-""}
EOF

# Find MariaDB ODBC driver, just in case
cat << EOF > /docker-entrypoint-initdb.d/odbcinst.ini
[MariaDB]
Description=MariaDB ODBC Connector
Driver=$(find /usr -name "libmaodbc.so" )
EOF

# Use proper tools to install ODBC configs
odbcinst -i -f /docker-entrypoint-initdb.d/odbcinst.ini -d -n "MariaDB ODBC Connector"
odbcinst -i -f /docker-entrypoint-initdb.d/odbc.ini -s -l

# Returns true once mysql can connect.
    mysql_ready() {
        mysqladmin ping --host=localhost --user=root --password=${MYSQL_ROOT_PASSWORD:-""} > /dev/null 2>&1
    }

# execute any pre-init scripts
for i in /scripts/pre-init.d/*sh
do
	if [ -e "${i}" ]; then
		echo "[i] pre-init.d - processing $i"
		. "${i}"
	fi
done
# execute any pre-exec scripts
for i in /scripts/pre-exec.d/*sh
do
	if [ -e "${i}" ]; then
		echo "[i] pre-exec.d - processing $i"
		. ${i}
	fi
done

su liquidms_user -c "php -S 0.0.0.0:8080 /var/www/liquidms/public/index.php"
