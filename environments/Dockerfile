FROM ubuntu:16.04
ENV DEBIAN_FRONTEND noninteractive
MAINTAINER Oscar Jara <oajara@gmail.com>

WORKDIR /var/www/smaw

RUN apt-get update && \
    apt-get -y install language-pack-en-base && \
    apt-get -y install python-software-properties && \
    apt-get -y install software-properties-common && \
    export LC_ALL=en_US.UTF-8 && \
    export LANG=en_US.UTF-8 && \
    add-apt-repository -y ppa:ondrej/php && \
    apt-get update && \
    apt-get -y install git && \
    apt-get -y install unzip && \
    apt-get -y install vim && \   
    apt-get -y install curl && \ 
    apt-get -y install apache2 libapache2-mod-php7.0 ssl-cert --no-install-recommends && \
    apt-get -y install mysql-client && \    
    apt-get -y install php7.0 php7.0-xml php7.0-curl php7.0-json php7.0-mysql php7.0-mbstring php7.0-mcrypt php7.0-soap php7.0-xdebug && \    
    apt-get -y install php-memcached && \
    rm -rf /var/lib/apt/lists/* && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install New Relic daemon
RUN apt-get update && \
    apt-get -yq install wget && \
    wget -O - https://download.newrelic.com/548C16BF.gpg | apt-key add - && \
    echo "deb http://apt.newrelic.com/debian/ newrelic non-free" > /etc/apt/sources.list.d/newrelic.list
 
 
# Setup environment variables for initializing New Relic
ENV NR_INSTALL_SILENT 1
#ENV NR_INSTALL_KEY 8c5c266b5909955933574169093a97a1cac8878d
ENV NR_INSTALL_KEY cea1d9405109fe3412eae3a8754aebe4c4912c89
ENV NR_APP_NAME 'Smaw API (container)'

RUN apt-get update && \
    apt-get -yq install newrelic-php5

RUN newrelic-install install
RUN sed -i "s/newrelic.appname = \"PHP Application\"/newrelic.appname = \"${NR_APP_NAME}\"/" /etc/php/7.0/apache2/conf.d/newrelic.ini

    
ENV APACHE_CONFDIR /etc/apache2
ENV APACHE_ENVVARS $APACHE_CONFDIR/envvars

# PHP files should be handled by PHP, and should be preferred over any other file type
RUN { \
		echo '<FilesMatch \.php$>'; \
		echo '\tSetHandler application/x-httpd-php'; \
		echo '</FilesMatch>'; \
		echo; \
		echo 'DirectoryIndex disabled'; \
		echo 'DirectoryIndex index.php index.html'; \
		echo; \
		echo '<Directory /var/www/>'; \
		echo '\tOptions -Indexes'; \
		echo '\tAllowOverride All'; \
		echo '</Directory>'; \
	} | tee "$APACHE_CONFDIR/conf-available/docker-php.conf" \
	&& a2enconf docker-php

# Copy the conf file
COPY environments/smaw_vhost.conf /etc/apache2/sites-available/smaw.conf
COPY environments/smaw_php.ini /etc/php/7.0/apache2/conf.d/21-smawdev.ini
COPY environments/smaw_php.ini /etc/php/7.0/cli/conf.d/21-smawdev.ini

# We use our own version for xdebug.ini
COPY environments/xdebug_apache2.ini /etc/php/7.0/mods-available/xdebug_apache2.ini
COPY environments/xdebug_cli.ini /etc/php/7.0/mods-available/xdebug_cli.ini

# logs should go to stdout / stderr
RUN mkdir logs && set -ex \
        && . "$APACHE_ENVVARS" \
    && ln -sfT /dev/stderr /var/www/smaw/logs/smaw-error.log \
        && ln -sfT /dev/stdout /var/www/smaw/logs/smaw-access.log \
        && ln -sfT /dev/stdout /var/log/apache2/other_vhosts_access.log \
    && ln -sfT /dev/stderr /var/log/apache2/error.log \
        && ln -sfT /dev/stdout /var/log/apache2/access.log 



RUN a2dissite 000-default && a2ensite smaw && a2enmod rewrite

COPY environments/apache2-foreground /usr/local/bin/
COPY environments/entry.sh /usr/local/bin/


EXPOSE 8080

CMD ["/usr/local/bin/entry.sh"]

