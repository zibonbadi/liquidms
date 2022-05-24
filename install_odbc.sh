#!/bin/sh

mkdir /docker-entrypoint-initdb.d/

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

