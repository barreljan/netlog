# Troubleshooting

## Table of contents

1. [Disk usage grows rapidly](troubleshoot.md#disk-usage-grows-rapidly)
2. [Issues with background jobs](troubleshoot.md#issues-with-background-jobs)
3. [No Lograte graphs shown](troubleshoot.md#Lograte-graph-issues)


### Disk usage grows rapidly

When your system is running for a while, it can be that a newly added host is logging a lot
of lines to Netlog. This can be disk intensive and cause some issues like a full disk. Even 
without additions of new logging hosts, it can be difficult to see what host is doing the most
and causes the usage, certainly when you have 100+ hosts.

There is a small utility _bigtables_ provided in the utils/ directory. This provides a quick
glance of the biggest tables.

Syntax
```shell
[root@netlog utils]# ./bigtables -h
bigtables: Show the biggest tables

Usage: bigtables <int>          Where int represents the top 'x' of the list
```
Default is 10.

An example:

```shell
[root@netlog ~]# cd /usr/share/netlog/utils
[root@netlog utils]# ./bigtables
Shows a top 10 of big tables

15G     HST_10_99_0_1_DATE_2022_Jun
14G     HST_10_99_0_1_DATE_2022_Jul
8.1G    HST_10_99_0_1_DATE_2022_May
6.6G    HST_172_32_254_1_DATE_2022_Jul
5.5G    HST_172_32_254_1_DATE_2022_Jun
3.0G    HST_172_32_254_1_DATE_2022_May
848M    HST_172_16_0_18_DATE_2022_Jun
669M    HST_172_16_0_18_DATE_2022_Jul
657M    HST_10_21_13_3_DATE_2022_May
257M    HST_10_21_13_3_DATE_2022_Apr
```

Now you know what hosts are log intensive. You can either clean up a seperate table for quick
resolving a full disk, or enabling the 'lograte' function to monitor the rate of logging for 
that host(s). See also the [lograte](overview.md#lograte) in the overview.

Deletion of the table can be done via:
```shell
mysql -A -p -u syslog -e 'DROP TABLE HST_10_99_0_1_DATE_2022_Jun;'
```
Or your prefered way (phpMyAdmin, etc).


### Issues with background jobs

In certain circumstances a background job will fail. These are uncommon and can happen
when you are doing some custom work. The most core modules do log a small amount of errors
to the system log. So you will see them in '/var/log/messages' or you Netlog system itself.

### Lograte graph issues

Although the basic installation comes with TrueType fonts, you may end up with a blank page
when opening the Lograte viewer. This could be an issue with the PHP installation (review
your log files) but most of the time this is due to the TTF definition being wrong.

Open the following file with your prefered editor:

```vim /usr/share/php/jpgraph/jpg-config.inc.php```

And look for the line (mostly around line #39)

```define(\'TTF_DIR\', <somelocation>)```

Make sure this points to the correct location where the 'arial.ttf' file is located.
