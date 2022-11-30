# Netlog Advanced

## Table of contents

1. [Scavenger filtering](advanced.md#scavenger-filtering)


### Scavenger filtering

When there are events found by the Logscavenger module, it can be that there are
some false positives you do not want to see.

For instance, you look for the message '**foo**' and a device is logging '**foo**' and 
'**foo**bar'. This is also a event that you want, because some good reason. However, another 
device is logging '**foo**baz'. This message is not relevant and can be filtered out.

This is currently done within a include file next to the logscavenger.php module. The file
'scavengerfilter.inc.php' can be placed there. There will be an array defined within with 
primarily one or more keys:

* '%any_host%'
* 'some_host_name'

Each key has a array as value where certain thing can be filtered. The first is for **all hosts**
and must use the key name as stated above. The other can be host-specific. The hostname must
match _exactly_ the name as configured in the config section.

An example 'scavengerfilter.inc.php.dist' is provided in the install/ directory. You can copy
this to the core/ directory:

```shell
cd /usr/share/netlog/
cp install/scavengerfilter.inc.php.dist core/scavengerfilter.inc.php
```
