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
RUN apk -U add --no-cache oniguruma oniguruma-dev unixodbc unixodbc-dev autoconf gcc libc-dev yaml yaml-dev make mariadb-connector-odbc mysql-client

RUN docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr/

# Install extensions
#
# For convenience, each extension has been broken into a separate line, so
# that you can comment out unneeded database backends.
RUN pecl install yaml && echo "extension=yaml.so" > /usr/local/etc/php/conf.d/ext-yaml.ini
RUN docker-php-ext-install \
		mbstring \
		sockets \
		#odbc \
		## YAML might not exist on PECL due to inclusion
		#yaml \
		pdo \
		pdo_mysql  \
		pdo_odbc \
		#pdo_pgsql \
		## SQLite might be pre-installed
		#pdo_sqlite \
		&& echo "-DONE installing PHP extensions-"


RUN docker-php-ext-enable sockets pdo mbstring pdo_mysql pdo_odbc yaml

# install PECL extensions
# install EXT_YAML
#RUN apk add autoconf gcc libc-dev yaml yaml-dev make
#RUN pecl install yaml
#RUN docker-php-ext-enable yaml

# check that everything was indeed installed
RUN php -i|grep pdo \
  && php -i | grep mbstring \
  && php -i | grep odbc \
  && php -i | grep yaml

#delete sources and cache as we don't need them anymore
RUN rm -f /var/cache/apk/*

#FROM php_with_addons AS composer_install

# install composer to our image from Docker image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# install composer directly (if you want)
#RUN curl -sS https://getcomposer.org/installer | php && \
#    chmod +x composer.phar && \
#    mv composer.phar /usr/bin/composer

#FROM composer_install AS liquidms_install

# Install liquidMS as an app
# dist directory must be mounted as a volume
COPY dist/apis/srb2http /var/www/liquidms

RUN apk -U add --no-cache mariadb-connector-odbc
#ADD odbcinst.ini /etc/odbcinst.ini
RUN mkdir /docker-entrypoint-initdb.d && \
	mkdir /scripts
#ADD run-satellite.sh /scripts/run.sh
ADD docker/satellite/install_odbc.sh /scripts/install_odbc.sh
#ADD docker/satellite/install_config.sh /scripts/install_config.sh
#RUN chmod +x /scripts/install_odbc.sh /scripts/install_config.sh
RUN chmod +x /scripts/install_odbc.sh
#ADD odbc.ini /etc/odbc.ini

ARG ODBC_HOST=$ODBC_HOST \
ODBC_PORT=$ODBC_PORT \
MYSQL_DATABASE=$MYSQL_DATABASE \
MYSQL_USER=$MYSQL_USER \
MYSQL_PASSWORD=$MYSQL_PASSWORD \
MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD

#RUN /scripts/install_odbc.sh
#RUN /scripts/install_config.sh

RUN chown -R www-data:www-data /var/www
USER www-data
WORKDIR /var/www/liquidms
# should be redundant given the dir copy
RUN composer install
#RUN composer update
#RUN composer dump-autoload
EXPOSE 9000
