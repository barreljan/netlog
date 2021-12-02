CREATE DATABASE `syslog`;
GRANT ALL ON netlogconfig.* TO 'syslog'@'localhost' IDENTIFIED BY 'WonFaznu$(s#3nCi';
USE netlogconfig;

--
-- Table structure for table `emailgroup`
--

DROP TABLE IF EXISTS `emailgroup`;
CREATE TABLE `emailgroup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `groupname` varchar(40) NOT NULL,
  `recepients` text NOT NULL,
  `active` int(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `groupname` (`groupname`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `emailgroup`
--

LOCK TABLES `emailgroup` WRITE;
INSERT INTO `emailgroup` VALUES (1,'Default','johndoe@domain.tld',1);
UNLOCK TABLES;

--
-- Table structure for table `hostnames`
--

DROP TABLE IF EXISTS `hostnames`;
CREATE TABLE `hostnames` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hostip` text NOT NULL,
  `hostname` text NOT NULL,
  `hosttype` int(10) unsigned NOT NULL,
  `lograte` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `hostip` (`hostip`(1000)),
  KEY `hosttype` (`hosttype`),
  CONSTRAINT `hostnames_ibfk_1` FOREIGN KEY (`hosttype`) REFERENCES `hosttype` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `hostnames`
--

LOCK TABLES `hostnames` WRITE;
INSERT INTO `hostnames` VALUES (1,'127.0.0.1','localhost',2,1);
UNLOCK TABLES;

--
-- Table structure for table `hosttype`
--

DROP TABLE IF EXISTS `hosttype`;
CREATE TABLE `hosttype` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`(1000))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `hosttype`
--

LOCK TABLES `hosttype` WRITE;
INSERT INTO `hosttype` VALUES (1,'Unnamed'),(2,'Servers');
UNLOCK TABLES;

--
-- Table structure for table `logcache`
--

DROP TABLE IF EXISTS `logcache`;
CREATE TABLE `logcache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `host` varchar(39) NOT NULL,
  `msg` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `host` (`host`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Table structure for table `lograte`
--

DROP TABLE IF EXISTS `lograte`;
CREATE TABLE `lograte` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `hostnameid` int(10) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `1min` float DEFAULT NULL,
  `5min` float DEFAULT NULL,
  `10min` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Table structure for table `lograteconf`
--

DROP TABLE IF EXISTS `lograteconf`;
CREATE TABLE `lograteconf` (
  `hostnameid` int(10) NOT NULL,
  `samplerate` int(10) DEFAULT NULL,
  UNIQUE KEY `id` (`hostnameid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `lograteconf`
--

LOCK TABLES `lograteconf` WRITE;
INSERT INTO `lograteconf` VALUES (1,1);
UNLOCK TABLES;

--
-- Table structure for table `logscavenger`
--

DROP TABLE IF EXISTS `logscavenger`;
CREATE TABLE `logscavenger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(100) NOT NULL,
  `dateadded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active` int(1) NOT NULL,
  `datedeleted` timestamp NULL DEFAULT NULL,
  `emailrcpt` varchar(255) DEFAULT NULL,
  `emailgroupid` int(1) unsigned DEFAULT 1,
  KEY `id` (`id`),
  KEY `emailgroupid` (`emailgroupid`),
  CONSTRAINT `logscavenger_ibfk_1` FOREIGN KEY (`emailgroupid`) REFERENCES `emailgroup` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `logscavenger`
--

LOCK TABLES `logscavenger` WRITE;
INSERT INTO `logscavenger` (id, keyword, active, emailgroup) VALUES (1,'reboot',1,,1);
UNLOCK TABLES;

