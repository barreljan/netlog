# Netlog configuration

## Table of contents

1. [Host names](configuration.md#host-names)
2. [Host types](configuration.md#host-types)
3. [Scavenger](configuration.md#scavenger)
4. [Global](configuration.md#global)

### Host names

You can set a name per host that is actively logging to your Netlog system.
The host type can be set accordingly as well if you want lograte or not for
this host.

There are 3 views available:
- All
- Unnamed (default view)
- Unused

The first is self explainatory.

The _unnamed_ are hosts that are actively logging but do not have an entry and
a host type set.

The _unused_ view gives hostnames in the
system of those that are not logging actively anymore. These only appear when (older)
tables are removed, for instance due to renention threshold and thus a cleanup,
or when you manually remove all the tables of a host yourself.

Deletion can only be done with _unused_ hosts.

### Host types

You can set a host type for easy grouping of your hosts. Any name will do.

Deletion can only be done if there are no hosts related so the host type is
empty.

### Scavenger

Add any string that can be searched in the **MSG** field to add these events to the Netalert page or your NMS.

### Global

In this section you will find explainations about the global settings. These
settings control the way Netlog works, looks and operates in the background.
Be carefull with the options, as it may have some unwanted results.

#### Summary

- **[cron_mail_from | cron_mail_rcpt](configuration.md#cron_mail_from--cron_mail_rcpt)** - These options are the _from_ and
_recepient_ you can use for background notifications.
- **[default_view](configuration.md#default_view)** - This is default host type to be used in viewing the
logging.
- **[logarchive_interval](configuration.md#logarchive_interval)** - Set after how many days the separate day-tables
will be combined into a single month-table.
- **[lograte_days](configuration.md#lograte_days)** - Set how long the lograte statistics are kept in the
database. Work in conjunction with _lograte_history_.
- **[lograte_graph_height | lograte_graph_width](configuration.md#lograte_graph_height--lograte_graph_width)** - Set the default graph
size for the lograte page.
- **[lograte_history](configuration.md#lograte_history)** - Sets the option-list on the lograte page. Use integer
  comma separated values.
- **[lograte_history_default](configuration.md#lograte_history_default)** - Sets the default 'selected' as a value set
in the _lograte_history_.
- **[log_fields](configuration.md#log_fields)** - Sets the available field. For internal use in logparser
module. Use with care!
- **[log_fifo](configuration.md#log_fifo)** - Set the FiFo filename
- **[log_levels](configuration.md#log_levels)** - Set the available log levels. Sets which field to be shown.
- **[netalert_fields](configuration.md#netalert_fields)** - Set the available fields for the Netalert page.
Normally a brief set of field is used.
- **[netalert_show_lines](configuration.md#netalert_show_lines)** - Sets the amount of lines shown on the Netalert
page.
- **[netalert_time_threshold](configuration.md#netalert_time_threshold)** - Sets the threshold in which time messages are
normalized.
- **[netalert_to_nms](configuration.md#netalert_to_nms)** - Set the NMS module to active/disable
- **[refresh](configuration.md#refresh)** - Set the option-list to your need to be used in viewing the
logging. Use integer comma separated values.
- **[retention](configuration.md#retention)** - Set the number of months to keep and older to drop.
- **[scavenger_history](configuration.md#scavenger_history)** - Set the time threshold of syslog messages to be
found in the logscavenger module
- **[show_lines](configuration.md#show_lines)** - Sets the option-list of the number of lines per page to be
shown. Use comma separated values.
- **[show_lines_default](configuration.md#show_lines_default)** - Sets the default 'selected' as a value set in
_show_lines_.
- **[batch_max_age](configuration.md#batch_max_age)** - Set the logparser batching mechanism maximum age of buffering, in seconds.
- **[batch_size](configuration.md#batch_size)** - Set the batch size in number of lines.

###### cron_mail_from | cron_mail_rcpt

Seems obvious, ain't they? To be used for cron/systemd alerting/notices. Not yet implemented.

###### default_view

- **Value**: string
- **Default**: 'Server'

This is default host type to be used in viewing the logging for a new session. Can be any type
you have added to the host types.

###### logarchive_interval

- **Value**: single integer
- **Default**: 14

Set after how many days the separate day-tables will be combined into a
single month-table. This is used in the logarchiver module

###### lograte_days

- **Value**: single integer
- **Default**: 14

Set how long the lograte statistics are kept in the  database. Work in
conjunction with _lograte_history_. The more statistics per days you have,
the greater the value can be in _lograte_history_.

###### lograte_graph_height | lograte_graph_width

- **Value**: single integer
- **Default**: 250 height, 600 width

Set the default graph size for the lograte page.

###### lograte_history

- **Value**: strings, comma separated
- **Default**: '30,60,120,240,480,1440,2880,4320,10080'

Sets the option-list on the lograte page. Use integer comma separated values.
The values are in _minutes_.

###### lograte_history_default

- **Value**: single integer
- **Default**: 20

Sets the default 'selected' as a value set in the _lograte_history_.

###### log_fields

- **Value**: strings, comma separated
- **Default**: 'HOST,FAC,PRIO,LVL,TAG,DAY,TIME,PROG,MSG'

Set the available fields for Netlog. This includes the main page but also the
logparser module. The field names must comply with the [syslog fields](configuration.md#syslog-fields)
Best practice is to leave it at the default.

The order can be changed without issues.

###### log_fifo

- **Value**: string
- **Default**: '/var/log/syslog.fifo'

Set the FiFo file location. The Logparser module creates this if it does not exist. If the Logparser
module is running from a non-priviledged user (not root) make sure additional permissions are set.

###### log_levels

- **Value**: strings, comma separated
- **Default**: 'debug,info,notice,warning,error,critical,alert,emergency,panic'

Set the available log levels. Sets which field to be shown.

###### netalert_fields

- **Value**: strings, comma separated
- **Default**: 'DAY,TIME,LVL,MSG,PROG'

Set the available fields for the Netalert page. Normally a brief set of
field is used. The field names must comply with the [syslog fields](configuration.md#syslog-fields)

###### netalert_show_lines

- **Value**: single integer
- **Default**: 20

Limits the lines on the Netalert page.

###### netalert_time_threshold

- **Value**: single integer
- **Default**: 3600 (seconds)

Sets the threshold in which time messages are normalized. After the set time
the lines are not colored anymore.

###### netalert_to_nms

- **Value**: single integer
- **Default**: 0

Enables or disables the Log2nms module that events are pushed directly to the LibreNMS database.
This requires the LibreNMS database to be reachable for Netlog and credentials provided in the
netlog.conf file with the `$nms_database` array. See the _install/netlog.conf.example_ for what
is needed.

###### refresh

- **Value**: string and integers, comma separated
- **Default**: 'off,1,2,5,10'

The 'off' is a special value and should be kept in the values.

###### retention

- **Value**: single integer
- **Default**: 3 (months)

Set the number of months to keep and older to drop. This is calculated of
the first day of the month so when ran at 25th May it will drop tables
from Feb.

###### scavenger_history

- **Value**: single integer
- **Default**: 300 (seconds)

Set the time threshold of syslog messages to be found in the logscavenger
module. Basically it tells how much to look back in time to search over the
syslog messages you want to find any of the keywords.
Longer times can impact your system performance. It is only necessary to set
this to a long period if your logscavenger module has not run for a while. In
this way you can catch up. Do not forget to put the setting back to a low
number.

###### show_lines

- **Value**: integers, comma separated
- **Default**: '50,100,250,500,1000'

Sets the option-list of the number of lines per page to be shown.

###### show_lines_default

- **Value**: single integer
- **Default**: 50

Sets the default 'selected' as a value set in show_lines.

##### batch_max_age

- **Value**: integer
- **Default**: 1 (second)

Set the maximum wait till lines are committed into the database. This setting
works together with [batch_size](configuration.md#batch_size). Warning: setting
this or the 'batch_size' to very low setting can result in poor performance.
This depends on your system (CPU vs storage and how MySQL is configured). The
setting will commit lines when either the maximum age is met OR the number of
lines is met, so **what comes first** will be deciding when inserts are committed.
Keep in mind that this is very dependent on how many lines are logged per
second that results in a very I/O heavy system.

A change in one of these settings **requires** a restart of the logparser daemon

##### batch_size

- **Value**: integer
- **Default**: 1000

See [batch_max_age](configuration.md#batch_max_age).


### Syslog fields

Mandatory fields:

`HOST` `FAC` `PRIO` `LVL` `TAG` `DAY` `TIME` `PROG` `MSG`

These fields are also used in the Syslog-NG configuration as a format to pass
it to the logparser module.
