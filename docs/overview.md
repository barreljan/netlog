# Netlog overview

## Table of contents

1. [Logging](overview.md#logging)
2. [Config](configuration.md#netlog-configuration)
3. [Lograte](overview.md#lograte)
4. [Netalert](overview.md#netalert)
5. [Retention](overview.md#retention)
6. [Components](overview.md#components)


### Retention

There is currently a relative decent retention. This however depends on the
volume your hosts are logging to the Netlog system. The default value is to
keep 3 months.

Depending on that you can estimate how quickly your disk is growing in
usage and set a retention policy accordingly. The setting is in the Global
section of the configuration panel.
