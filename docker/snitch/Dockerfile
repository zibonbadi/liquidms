# Using multi-stage builds! https://docs.docker.com/develop/develop-images/multistage-build/
FROM php:8.1-fpm-alpine

#of course we can use this installer https://github.com/mlocati/docker-php-extension-installer
#but let's keep our docker images minimal
#and not use any packages provided by alpine linux to increase portability

#copy default config
#RUN docker-php-source extract
#RUN cp /usr/src/php/php.ini-production "${PHP_INI_DIR}/php.ini"

# You have to install all of these packages. look if your linux distro has these in their repos
#RUN apk -U upgrade
#autoconf gcc
RUN echo "https://dl-4.alpinelinux.org/alpine/edge/community" >> /etc/apk/repositories
RUN echo "https://dl-4.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories
RUN apk -U add --no-cache oniguruma oniguruma-dev libxml2-dev unixodbc unixodbc-dev autoconf gcc libc-dev yaml yaml-dev make mariadb-connector-odbc mysql-client sqlite

# Install extensions
# Note: Script docker-php-ext-configure executed automatically in docker-php-ext-install!
# So no need to run it directly unless you want to pass through specific flags for ./configure

### # "WINDOWS.H does not exist error" drives me crazy
### #HACK
### RUN set -ex; \
	### docker-php-source extract; \
	### { \
		### echo '# https://github.com/docker-library/php/issues/103#issuecomment-271413933'; \
		### echo 'AC_DEFUN([PHP_ALWAYS_SHARED],[])dnl'; \
		### echo; \
		### cat /usr/src/php/ext/odbc/config.m4; \
	### } > temp.m4; \
	### mv temp.m4 /usr/src/php/ext/odbc/config.m4; \
	### docker-php-ext-configure odbc --with-unixODBC=shared,/usr --with-adabas=yes; \
	### docker-php-ext-install odbc; \
	### docker-php-source delete
### #HACK

# Preconfigure extension
#
# For convenience, each extension has been broken into a separate line, so
# that you can comment out unneeded database backends.
#RUN docker-php-ext-configure odbc --with-unixODBC=shared,/usr/
RUN docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr/
#RUN docker-php-ext-configure pdo_mssql
#RUN docker-php-ext-configure pdo_mssql
#RUN docker-php-ext-configure pdo_pgsql
## SQLite is already installed and configured
#RUN docker-php-ext-configure pdo_sqlite --with-pdo-sqlite=shared,/usr

# Install extensions
#
# For convenience, each extension has been broken into a separate line, so
# that you can comment out unneeded database backends.
RUN pecl install yaml && echo "extension=yaml.so" > /usr/local/etc/php/conf.d/ext-yaml.ini
RUN docker-php-ext-install \
		ctype \
		dom \
		#odbc \
		#pdo_pgsql \
		## SQLite might be pre-installed
		#pdo_sqlite \
		## YAML might not exist on PECL due to inclusion
		#yaml \
		mbstring \
		pdo \
		pdo_mysql  \
		pdo_odbc \
		sockets \
		&& echo "-DONE installing PHP extensions-"

#RUN docker-php-ext-enable sockets mbstring yaml
RUN docker-php-ext-enable \
		ctype \
		dom \
		#odbc \
		#pdo_mssql \
		#pdo_pgsql \
		#pdo_sqlite \
		yaml \
		mbstring \
		pdo \
		pdo_mysql \
		pdo_odbc \
		sockets \
		&& echo "-DONE enabling PHP extensions-"

#RUN docker-php-ext-install mbstring sockets

# Checks that everything was indeed installed. Please make them match the
# installed extensions above.
RUN echo "=== Checking for PHP extensions ===" \
	&& php -i | grep -e "^mbstring" \
	&& php -i | grep -e "sockets" \
	#&& php -i | grep -e "odbc" \
	#&& php -i | grep -e "yaml"\
	#&& php -i | grep -e "pdo_mssql" \
	#&& php -i | grep -e "pdo_pgsql" \
	&& php -i | grep -e "pdo_sqlite" \
	&& php -i | grep -e "pdo_mysql"  \
	&& echo "-DONE checking for PHP-"

#delete sources and cache as we don't need them anymore
RUN rm -f /var/cache/apk/*

# Create transfer directories
ARG ODBC_HOST=$ODBC_HOST \
	ODBC_PORT=$ODBC_PORT \
	MYSQL_DATABASE=$MYSQL_DATABASE \
	MYSQL_USER=$MYSQL_USER \
	MYSQL_PASSWORD=$MYSQL_PASSWORD \
	MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD

RUN mkdir /docker-entrypoint-initdb.d && \
	mkdir /scripts
ADD docker/snitch/run-snitch.sh /scripts/run.sh
ADD docker/snitch/install_odbc.sh /scripts/install_odbc.sh
ADD docker/snitch/install_config.sh /scripts/install_config.sh
RUN chmod +x /scripts/install_odbc.sh /scripts/install_config.sh

#install liquidMS as an app
#it must be already cloned from "https://github.com/zibonbadi/liquidms.git"
COPY dist/snitch /var/www/liquidms
COPY docker/snitch/config.yaml /var/www/liquidms/config.yaml
#COPY docker/snitch/fetch.crontab /etc/crontabs/liquidms_fetch
#RUN cat /etc/crontabs/liquidms_fetch >> /etc/crontabs/root

RUN /scripts/install_odbc.sh
RUN /scripts/install_config.sh


# install composer to our image from Docker image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Set container user for Composer
RUN chown -R www-data:www-data /var/www
USER www-data
WORKDIR /var/www/liquidms

RUN composer install

#launch init fetch with cronjob
#ENTRYPOINT ["/scripts/run.sh"]
ENTRYPOINT ["php", "/var/www/liquidms/liquidanacron.php"]

