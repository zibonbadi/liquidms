FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
	libonig-dev \
	unixodbc \
	unixodbc-dev \
	autoconf \
	gcc \
	#libyaml \
	libyaml-dev \
	make \
	odbc-mariadb

RUN docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr/

# Install extensions
#
# For convenience, each extension has been broken into a separate line, so
# that you can comment out unneeded database backends.

# install PECL extensions
# install EXT_YAML
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

# Enable RewriteEngine for Apaache HTTPd
RUN a2enmod rewrite

# check that everything was indeed installed
RUN php -i|grep pdo \
  && php -i | grep mbstring \
  && php -i | grep odbc \
  && php -i | grep yaml

RUN mkdir /docker-entrypoint-initdb.d && \
	mkdir /scripts
ADD docker/satellite/install_odbc.sh /scripts/install_odbc.sh
RUN chmod +x /scripts/install_odbc.sh

EXPOSE 9000
