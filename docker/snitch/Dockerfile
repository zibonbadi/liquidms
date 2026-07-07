FROM php:8.5-fpm-alpine

# Adding extra Alpine repositories to get what we need
RUN echo "https://dl-4.alpinelinux.org/alpine/edge/community" >> /etc/apk/repositories
RUN echo "https://dl-4.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories
RUN apk -U add --no-cache \
	autoconf \
	gcc \
	libc-dev \
	libxml2-dev \
	linux-headers \
	make \
	oniguruma \
	oniguruma-dev \
	yaml \
	yaml-dev

# Install extensions
#
# For convenience, each extension has been broken into a separate line, so
# that you can comment out unneeded database backends.
#
# Note: Script docker-php-ext-configure executed automatically in docker-php-ext-install!
RUN pecl install yaml && echo "extension=yaml.so" > /usr/local/etc/php/conf.d/ext-yaml.ini
RUN docker-php-ext-install \
		ctype \
		## YAML might not exist on PECL due to inclusion
		#yaml \
		mbstring \
		sockets \
		&& echo "-DONE installing PHP extensions-"

RUN docker-php-source extract \
		ctype \
		## YAML might not exist on PECL due to inclusion
		yaml \
		mbstring \
		sockets \
		&& echo "-DONE extracting PHP extension sources-" \
	&& docker-php-ext-enable \
		ctype \
		yaml \
		mbstring \
		sockets \
		&& echo "-DONE enabling PHP extensions-" \
	&& docker-php-source delete

# Checks that everything was indeed installed. Please make them match the
# installed extensions above.
RUN echo "=== Checking for PHP extensions ===" \
	&& php -i | grep -e "ctype" \
	&& php -i | grep -e "mbstring" \
	&& php -i | grep -e "yaml" \
	&& php -i | grep -e "sockets" \
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
ADD docker/snitch/install_config.sh /scripts/install_config.sh
RUN chmod +x /scripts/install_config.sh

# Install LiquidAnacron as an app
COPY src/liquidanacron /var/www/liquidms

RUN /scripts/install_config.sh

# Install composer to our image from Docker image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Set container user for Composer
RUN chown -R www-data:www-data /var/www
USER www-data
WORKDIR /var/www/liquidms

# Install composer dependencies
RUN composer install

# Configure launch script
ENTRYPOINT ["php", "/var/www/liquidms/liquidanacron.php"]

