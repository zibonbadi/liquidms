#!/bin/sh
echo "Init fetch (checking connection)..." && \
su liquidms_user -c "php /var/www/liquidms/liquidms/fetch.php" && \
echo "Starting crond..." && \
crond -f -l 8

#su liquidms_user -c "php /var/www/liquidms/liquidms/liquidanacron.php"
#/etc/init.d/rsyslogd start
#/etc/init.d/cron start

