# Netlog installation

## Requirements

There is a small list of minimal required software packages which Netlog
relies on. Those should be installed on your system prior to installing
Netlog. Next to this a decent hardware specification is needed, but
depending on the number of hosts logging and sheer number of lines logged.

### Software
- Syslog-NG 3.3 or newer

  It was originaly written for Syslog-NG 2.x, so as long as it can send
  formatted lines to the FiFo file, you're good.

- PHP 7.4 or newer

  It is possible that it could work on earlier PHP versions. No guarantee.

- MySQL 8.0 or equivalent (like MariaDB 10.x)

  It could work on earlier versions as there are no MySQL >8.0 specific
  database or table settings. No guarantee.

### Hardware
There is no specific requirement other than it should be size to your need.
The lines logged to the database do not take much space, but the expected
volume should be accounted for. More lines is more space.
The frontend (or GUI) is lightweight enough to be run at the bare minimum.
If however you have multiple users that do searches it can be smart to
enable more than 1 (v)CPU so PHP and your HTTP daemon can offer a better
performance.

Considerations:
It is recommended that flash storage (SSD/NVMe) is used for the database
storage.

#### Suggested specs

For starters (<100 hosts, <50M lines logged per day):
- 2 vCPU's (sockets) - 2.2Ghz or more
- 4GB Memory
- 60GB storage for MySQL data

For heavy logging (200+ hosts, >100M lines logged per day):
- 4 vCPU's (sockets) - 2.2Ghz or more
- 8GB Memory or more
- 600GB storage for MySQL data or more

## General installation instructions

Make sure the above requirement are met and the needed services are running.

Next:

Log in to your system (make sure you are root or have sudo rights)<br />

Navigate to the source directory: ```cd /usr/local/src```<br />

Clone the GitHub repository: ```git clone https://github.com/barreljan/netlog``` <br />

Navigate to the installation directory: ```cd netlog/install/``` <br />

Start the installation: ```bash install.sh```

And there you have it. 

The installation script is made for CentOS 7 (LAMP-stack) at the moment. Anyone with a 
little knowledge of Bash could make it work for your distribution. The script 
does several checks if software or locations are available, not in use, made
or can be made. No rocket science.

### In a nutshell

Based on your distribution or setup, this is what you need to do:

- make use PHP has JPgraph installed
  - `php -r "require_once('jpgraph/jpgraph.php');"`
- make a symlink: `ln -s /usr/local/src/netlog /usr/share/netlog`
- adjust your http daemon, so /netlog is an alias to /usr/share/netlog
  - or use the install/httpd.conf
- copy install/syslog.conf to your syslog-ng conf.d dir
- copy the install/cronjob to your desired cron location
- make sure `/var/log/syslog.fifo` is an available location
  - adjust core/logparser.php and etc/config.php if changed
- systemd systems: copy the logparser.service to designated location
  - adjust any location in it for your setyup
- check if 'arial' is in your font list. Usually check: `fc-list | grep arial`
  - unpack install/ext/msttcorefonts.tar.gz and move file to appropiate
    location
- upload the install/*.sql files to your database server
  - adjust if needed the first 2 lines if needed
  - copy install/netlog.conf.example to /usr/share/netlog/etc and adjust
- eh, what am I missing?






