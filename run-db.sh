#!/bin/sh

# Returns true once mysql can connect.
    mysql_ready() {
        mysqladmin ping --host=localhost --user=root --password=${MYSQL_ROOT_PASSWORD:-""} > /dev/null 2>&1
    }

echo "[i] Access data: $MYSQL_ROOT_PASSWORD MYSQL_DATABASE=${MYSQL_DATABASE:-""} MYSQL_USER=${MYSQL_USER:-""} MYSQL_PASSWORD=${MYSQL_PASSWORD:-""}"

# execute any pre-init scripts
for i in /scripts/pre-init.d/*sh
do
	if [ -e "${i}" ]; then
		echo "[i] pre-init.d - processing $i"
		. "${i}"
	fi
done

if [ -d "/run/mysqld" ]; then
	echo "[i] mysqld already present, skipping creation"
	chown -R mysql:mysql /run/mysqld
else
	echo "[i] mysqld not found, creating...."
	mkdir -p /run/mysqld
	chown -R mysql:mysql /run/mysqld
fi

if [ -d /var/lib/mysql/mysql ]; then
	echo "[i] MySQL directory already present, skipping creation"
	chown -R mysql:mysql /var/lib/mysql
else
	echo "[i] MySQL data directory not found, creating initial DBs"

	chown -R mysql:mysql /var/lib/mysql

# supress output
	mysql_install_db --user=mysql --ldata=/var/lib/mysql/ > /dev/null
	
	if [ "$MYSQL_ROOT_PASSWORD" = "" ]; then
		MYSQL_ROOT_PASSWORD=`pwgen 16 1`
	else
		MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-""}
	fi

	echo "[i] Access data: $MYSQL_ROOT_PASSWORD MYSQL_DATABASE=${MYSQL_DATABASE:-""} MYSQL_USER=${MYSQL_USER:-""} MYSQL_PASSWORD=${MYSQL_PASSWORD:-""}"

	tfile=`mktemp`
	if [ ! -f "$tfile" ]; then
	    return 1
	fi

	cat << EOF > $tfile
USE mysql;
FLUSH PRIVILEGES;
SET PASSWORD FOR root@'localhost' = PASSWORD('$MYSQL_ROOT_PASSWORD');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' identified by '$MYSQL_ROOT_PASSWORD' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' identified by '$MYSQL_ROOT_PASSWORD' WITH GRANT OPTION;
DROP DATABASE test;
EOF
#UPDATE user SET password=PASSWORD("") WHERE user='root' AND host='localhost';

	if [ "$MYSQL_DATABASE" != "" ]; then
	    echo "[i] Creating database: $MYSQL_DATABASE"
	    echo "CREATE DATABASE IF NOT EXISTS \`$MYSQL_DATABASE\` CHARACTER SET utf8 COLLATE utf8_general_ci;" >> $tfile

	    if [ "$MYSQL_USER" != "" ]; then
		echo "[i] Creating user: $MYSQL_USER with password $MYSQL_PASSWORD"
		echo "CREATE USER '$MYSQL_USER' IDENTIFIED BY '$MYSQL_PASSWORD';" >> $tfile
		echo "SET PASSWORD FOR '$MYSQL_USER'@'%' = PASSWORD('$MYSQL_PASSWORD');" >> $tfile
		echo "GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* to '$MYSQL_USER'@'%' IDENTIFIED BY '$MYSQL_PASSWORD';" >> $tfile
		echo "GRANT ALL PRIVILEGES ON \`$MYSQL_DATABASE\`.* to '$MYSQL_USER'@'localhost' IDENTIFIED BY '$MYSQL_PASSWORD';" >> $tfile
	    fi
	fi

	echo "[i] Trying to do all of the above"
	/usr/bin/mysqld --user=mysql --bootstrap --skip-name-resolve < $tfile
	echo "[i] Removing mktemp file"
	rm -f $tfile

	echo
        echo 'MySQL init process done. Ready for start up.'
        echo
	nohup /usr/bin/mysqld --user=mysql --console --skip-name-resolve "$@" &

	while !(mysql_ready)
        do
           sleep 3
	   echo "[i] Waiting for mysql to come up..."
        done

	for f in /docker-entrypoint-initdb.d/*; do
		case "$f" in
			*.sql)    echo "$0: running $f"; mysql -u root -p$MYSQL_ROOT_PASSWORD $MYSQL_DATABASE < "$f"; echo ;;
#			*.sql)    echo "$0: running $f"; mysql -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE < "$f"; echo ;;
#			*.sql)    echo "$0: running $f"; /usr/bin/mysqld --verbose=4 --user=mysql --bootstrap --skip-name-resolve < "$f"; echo ;;
#			*.sql)    echo "$0: running $f"; mysqld --user=mysql --bootstrap --skip-name-resolve < "$f"; echo ;;
			*.sql.gz) echo "$0: running $f"; gunzip -c "$f" | mysql -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE < "$f"; echo ;;
			*)        echo "$0: ignoring or entrypoint initdb empty $f" ;;
		esac
		echo
	done

#	exec killall mysqld
fi

# execute any pre-exec scripts
for i in /scripts/pre-exec.d/*sh
do
	if [ -e "${i}" ]; then
		echo "[i] pre-exec.d - processing $i"
		. ${i}
	fi
done

if !(mysql_ready); then
	exec /usr/bin/mysqld --user=mysql --console --skip-name-resolve $@
fi

while (mysql_ready)
do
	sleep 5
done
