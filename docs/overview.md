# Netlog overview

## Table of contents

1. [Logging](overview.md#logging)
2. [Config](configuration.md#netlog-configuration)
3. [Lograte](overview.md#lograte)
4. [Netalert](overview.md#netalert)
5. [Retention](overview.md#retention)


### Logging

On the main page, the default view is the actual **logging** view. Here you
can see line by line the log entries that the device has sent to Netlog. 
There are a few options and filters available. 

- device type
- device
- day
- level
- search
- page
- lines per page
- if 'day' is today the refresh option is available

The first two, device type and device, are names that can be controlled by
configuring these in the [config](configuration.md#netlog-configuration) 
section. You can easilly navigate to your group of devices and the specific
device you want. 
There is also a 'UHO' device. This is a specific table where log entries
can emerge if something in tcp/udp or syslog-level went wrong and the 
host-ip field is somehow missing.

The day option is self explainatory. Select the appropriate day you want. 
Or select the month. The aggregation is done in the background and can be
regulated by the global setting `logarchive_interval`. See 
[logarchive_interval](configuration.md#logarchive_interval) for more detail.

The level option defines what severity levels you want to display. This
works as **'selected and worse'**. If you have selected 'warning' then all 
warnings, errors, critical and worse are displayed. The notice, info and 
debug are not displayed. 

The search field is a user-input field. This searches for any string in
the 'PROG' and 'MSG' (message) field. This is a SQL `LIKE %your string%` 
based input so 'down' also finds 'DOWN'.

The page navigation and lines per page are self explainatory. Navigate
easilly through the found lines and your desired lines per page. The latter
is regulated by the global setting `show_lines`. There is also a shortcut
possible to use a time to navigate as a page-search. Use the format 
_"hh:mm"_ to go to the corresponding page. Another possibility is to use 
the format _"yyyy-mm-dd hh:mm"_. This can be useful in the aggregated 
months but also works in a day view. 
See also [show_lines](configuration.md#show_lines) for more detail.

If you have selected the currect day (as in; today) then the refresh option
will be available. Here you can let the page refresh every # seconds to 
keep monitoring certain events. If the refresh is enabled a 'stop' button 
emerges to obviously stop the refreshing. The options are regulated by the
global setting `refresh`. See [refresh](configuration.md#refresh) for more 
detail.


### Lograte

On the lograte page will graph(s) be displayed if one or more hosts are 
enabled with the 'lograte' option. This gives a quick glance of how
much a host is sending in syslog messages. For firewalls and other
security-related devices a possible indicator something unwantend is 
happening.

Every minute a sample will be taken with how many lines are logged over
3 periods: 1min average, 5min average and 10min average. These samples 
make the graph over a user selectable time window. The options are 
regulated by the global setting `lograte_history`. See
[lograte_history](configuration.md#lograte_history) for more detail.


### Netalert

The Netalert page displays, if there are any, the messages found matching 
one or more keywords configurered in the config section See also the
[scavenger](configuration.md#scavenger) documentation.

This functionality give a quick glance of very important events from your
network or servers. Every minute the systems scans all the tables of the
today's logging hosts for entries matching your keywords. There is a cache
present so older entries are not duplicated. From experience this way of
detecting events is what some call an 'early warning system'.

The page can be customized (e.g.: fields can be selected) and the number of
lines to be displayed as well as the time to 'normalize' the coloring. 
See the [global](configuration.md#global) section for more on this.


### Retention

There is currently a relative decent retention. This however depends on the
volume your hosts are logging to the Netlog system. The default value is to
keep 3 months.

Depending on that you can estimate how quickly your disk is growing in
usage and set a retention policy accordingly. The setting is in the Global
section of the configuration panel.
