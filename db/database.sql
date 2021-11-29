CREATE DATABASE `syslog`;
CREATE DATABASE `netlogconfig`;
 
GRANT ALL ON syslog.* TO 'syslog'@'localhost' IDENTIFIED BY 'WonFaznu$(s#3nCi';
GRANT ALL ON netlogconfig.* TO 'syslog'@'localhost' IDENTIFIED BY 'WonFaznu$(s#3nCi';
 
USE syslog;
CREATE TABLE `template` (
  `id` int(15) unsigned NOT NULL AUTO_INCREMENT,
  `HOST` varchar(39) NOT NULL,
  `FAC` varchar(255) NOT NULL,
  `PRIO` varchar(255) NOT NULL,
  `LVL` varchar(255) NOT NULL,
  `TAG` varchar(255) NOT NULL,
  `DAY` varchar(10) NOT NULL,
  `TIME` varchar(8) NOT NULL,
  `PROG` varchar(255) NOT NULL,
  `MSG` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `HOST` (`HOST`),
  KEY `DAY` (`DAY`),
  KEY `TIME` (`TIME`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
USE netlogconfig;
CREATE TABLE `hostnames` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `hostip` text NOT NULL,
  `hostname` text NOT NULL,
  `hosttype` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
CREATE TABLE `hosttype` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;
 
CREATE TABLE `lograte` (
  `id` int(10) NOT NULL auto_increment,
  `hostnameid` int(10) NOT NULL,
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `1min` float default NULL,
  `5min` float default NULL,
  `10min` float default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;
 
CREATE TABLE `lograteconf` (
  `hostnameid` int(10) NOT NULL,
  `samplerate` int(10) default NULL
) ENGINE=MyISAM;

CREATE TABLE `logscavenger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(100) DEFAULT NULL,
  `dateadded` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active` int(1) NOT NULL,
  `datedeleted` timestamp NULL DEFAULT NULL,
  `emailrcpt` varchar(255) DEFAULT NULL,
  `emailgroup` int(2) DEFAULT NULL,
  KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `emailgroups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(40) NOT NULL,
  `recepients` text NOT NULL,
  `active` int(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `logcache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `host` varchar(39) NOT NULL,
  `msg` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `host` (`host`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
