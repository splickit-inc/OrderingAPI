#!/bin/bash
set -e

if [ "$ENABLE_NEWRELIC" != "true" ]
then
        echo "Disabling NewRelic..."
	rm -f /etc/php/7.0/apache2/conf.d/newrelic.ini
	rm -f /etc/php/7.0/cli/conf.d/newrelic.ini
fi

if [ "$SMAW_ENV" == "development" ]
then
        echo "Enabling Xdebug PHP Module..."
	ln -sf /etc/php/7.0/mods-available/xdebug_apache2.ini /etc/php/7.0/apache2/conf.d/20-xdebug.ini
	ln -sf /etc/php/7.0/mods-available/xdebug_cli.ini /etc/php/7.0/cli/conf.d/20-xdebug.ini
else
	echo "Disabling Xdebug PHP Module..."
	rm -f /etc/php/7.0/apache2/conf.d/20-xdebug.ini /etc/php/7.0/cli/conf.d/20-xdebug.ini
fi

exec "apache2-foreground"
