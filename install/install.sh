#!/bin/bash

if [ ! -d /usr/share/syslog-ng ]; then
	printf "No Syslog-NG directory found at designated location, please correct manually\n";
	exit 1
elif [ -d /usr/share/syslog-ng ]; then
	printf "copying...\n";
	cp -R usrsharesyslog-ng/etc /usr/share/syslog-ng;
	cp -R usrsharesyslog-ng/php /usr/share/syslog-ng;
	cp -R usrsharesyslog-ng/sbin /usr/share/syslog-ng;
fi

if [ ! -d /var/www/html ]; then
	printf "No HTML directory found at designated location, please correct manually\n";
	exit 1
elif [ -d /var/www/html ]; then
	printf "copying...\n";
        cp -R varwww/html /var/www/;
fi

if [ ! -f /etc/crontab ]; then
	printf "No crontab file found, something is wrong with your system\n";
	exit 1
elif [ -f /etc/crontab ]; then
	printf "setting cronjobs, but not active in /etc/cron.d/netlog\n";
	cp cronjob > /etc/cron.d/netlog;
fi

if [ -d /usr/share/fonts ]; then
	tar xf extsrc/truetype.tgz -C /usr/share/fonts;
fi
if [ -d /usr/share/php ]; then
	tar xf extsrc/jpgraph-4.0.2.tar.gz -C extsrc;
	mv extsrc/jpgraph-4.0.2/src /usr/share/php/jpgraph;
	restorecon -R /usr/share/php/jpgraph
fi

if [ -f /root/.my.cnf ]; then
	mysql < db/database.sql
fi

printf "done!\n\nPlease make sure the webserver with php is working and the MySQL db is in order, correct the passwords in /usr/share/etc/logparser.conf and /var/www/html/config/config.php\n";
printf "then, uncomment the jobs in /etc/crontab\n";

exit 0
