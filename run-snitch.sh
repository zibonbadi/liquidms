#!/bin/sh

# Use proper tools to install ODBC configs
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

echo "Init fetch (checking connection)..."
su liquidms_user -c "php /var/www/liquidms/liquidms/fetch.php"
#su liquidms_user -c "php /var/www/liquidms/liquidms/liquidanacron.php"

echo "Starting crond..."
#/etc/init.d/rsyslogd start
#/etc/init.d/cron start
crond -f -l 8
