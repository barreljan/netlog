#!/bin/bash

# Delete all lograte table entries older than 14 days
HOME=/root
mysql netlogconfig -e "delete from lograte where timestamp < (NOW()-INTERVAL 14 DAY)";
