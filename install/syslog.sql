CREATE DATABASE `syslog`;
GRANT ALL ON syslog.* TO 'netlog'@'localhost' IDENTIFIED BY 'WonFaznu$(s#3nCi';

USE syslog;

--
-- Table structure for table `template`
--

CREATE TABLE `template`
(
    `id`   int(15) unsigned NOT NULL AUTO_INCREMENT,
    `HOST` varchar(39)      NOT NULL,
    `FAC`  varchar(255)     NOT NULL,
    `PRIO` varchar(255)     NOT NULL,
    `LVL`  varchar(255)     NOT NULL,
    `TAG`  varchar(255)     NOT NULL,
    `DAY`  varchar(10)      NOT NULL,
    `TIME` varchar(8)       NOT NULL,
    `PROG` varchar(255)     NOT NULL,
    `MSG`  text             NOT NULL,
    PRIMARY KEY (`id`),
    KEY `HOST` (`HOST`),
    KEY `DAY` (`DAY`),
    KEY `TIME` (`TIME`)
) ENGINE = MyISAM
  DEFAULT CHARSET = latin1;
 
