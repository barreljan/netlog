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

- PHP 7.4 or newer, prefered 8.0/8.1

  It is possible that it could work on earlier PHP versions. No guarantee.
  Required modules: php php-cli php-common php-gd php-pear php-memcache
  php-xml php-mysql(nd)

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
- 100GB storage for MySQL data

For heavy logging (200+ hosts, >100M lines logged per day):
- 4 vCPU's (sockets) - 2.2Ghz or more
- 8GB Memory or more
- 900GB storage for MySQL data or more

## General installation instructions

Make sure the above requirement are met and the needed services are running.

Next:

Log in to your system (make sure you are root or have sudo rights)<br />

Navigate to the source directory: ```cd /usr/local/src```<br />

Clone the GitHub repository: ```git clone https://github.com/barreljan/netlog``` <br />

Navigate to the installation directory: ```cd netlog/install/``` <br />

Start the installation: ```bash install.sh```

And there you have it. 

The installation script is made for CentOS 7 and is compatible with 
AlmaLinux 9.x and Ubuntu (22.04 to be precise), the so-called LAMP stack.
Anyone with a little knowledge of Bash/Shell could make it work for your
distribution. The script does several checks if software or locations are 
available, not in use, made or can be made. No rocket science.

### In a nutshell

Based on your distribution or setup, this is what you need to do:
- remove rsyslog
- install syslog-ng
- install httpd/apache2
- install php and req. modules
- make sure PHP has JPgraph installed
  - `php -r "require_once('jpgraph/jpgraph.php');"`
- make a symlink: `ln -s /usr/local/src/netlog /usr/share/netlog`
- adjust your http daemon, so /netlog is an alias to /usr/share/netlog
  - or use the install/httpd.conf as guide
- copy install/syslog.conf to your syslog-ng conf.d dir
- copy the install/cronjob to your desired cron location
- make sure `/var/log/syslog.fifo` is an available location
  - adjust core/logparser.php and etc/config.php if changed
- systemd systems: copy the logparser.service to designated location
  - adjust any location in it for your setup
- check if 'arial' is in your font list. Usually check: `fc-list | grep arial`
  - unpack install/ext/msttcorefonts.tar.gz and move file to appropiate
    location
  - adjust font dir in jpgraph
- upload the install/*.sql files to your database server
  - adjust if needed the first 2 lines if needed
  - copy install/netlog.conf.example to /usr/share/netlog/etc and adjust
- eh, what am I missing?


### Clean install

Perhaps you want the full help on a clean install. This should work out of 
the box with a normal new installation. Given that your enabled repo's provide 
PHP 7.4 or newer.

**CentOS 7 / AlmaLinux / RHEL-like**


```sudo yum remove -y rsyslog
sudo yum install -y syslog-ng
sudo yum install -y git php php-cli php-common php-gd php-pear php-memcache php-xml php-mysqlnd httpd
sudo yum install -y mariadb-server mariadb-server-utils mariadb
sudo mysql_secure_installation
sudo vi /root/.my.cnf
[client]
user=root
password="whatyouentered"


cd /usr/local/src
sudo git clone https://github.com/barreljan/netlog
cd netlog/install
sudo bash install.sh
```

**Ubuntu 22.xx**

```sudo apt remove rsyslog
sudo apt install syslog-ng
sudo apt install php php-cli php-common php-gd php-pear php-memcache php-xml php-mysql
sudo apt install mariadb-server mariadb-client
sudo mysql_secure_installation

sudo vi /root/.my.cnf
[client]
user=root
password="whatyouentered"


cd /usr/local/src
sudo git clone https://github.com/barreljan/netlog
cd netlog/install
sudo bash install.sh

```

