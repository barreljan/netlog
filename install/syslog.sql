CREATE DATABASE `syslog`;
GRANT ALL ON syslog.* TO 'netlog'@'localhost' IDENTIFIED BY '<<PASSWORD>>';

USE syslog;

--
-- Table structure for table `template`
--

CREATE TABLE `template` (
    `id` int(15) unsigned NOT NULL AUTO_INCREMENT,
    `HOST` varchar(39) NOT NULL,
    `FAC` varchar(32) NOT NULL,
    `PRIO` varchar(32) NOT NULL,
    `LVL` varchar(16) NOT NULL,
    `TAG` varchar(64) NOT NULL,
    `DAY` date NOT NULL,
    `TIME` time NOT NULL,
    `PROG` varchar(128) NOT NULL,
    `MSG` text NOT NULL,
    PRIMARY KEY (`id`),
    KEY `TIME` (`TIME`),
    KEY `LVL` (`LVL`),
    KEY `PROG` (`PROG`)
) ENGINE=InnoDB
  DEFAULT CHARSET=latin1;
