#!/usr/bin/env bash
# shellcheck disable=SC1090,SC2120
#
# This installation script provides a decent checked out setup for Netlog.
#
# Currently working (and tested) for: Centos 7
#
# However:
# You running this script/function means you will not blame the author(s)
# if this breaks your stuff. This script/function is provided AS IS without
# warranty of any kind. Author(s) disclaim all implied warranties including,
# without limitation, any implied warranties of merchantability or of
# fitness for a particular purpose. The entire risk arising out of the use
# or performance of the sample scripts and documentation remains with you.
# In no event shall author(s) be held liable for any damages whatsoever
# (including, without limitation, damages for loss of business profits,
# business interruption, loss of business information, or other pecuniary
# loss) arising out of the use of or inability to use the script or
# documentation. Neither this script/function, nor any part of it other
# than those parts that are explicitly copied from others, may be
# republished without author(s) express written permission. Author(s)
# retain the right to alter this disclaimer at any time.

# -e option instructs bash to immediately exit if any command [1] has a
# non-zero exit status. We do not want users to end up with a partially
# working install, so we exit the script instead of continuing the
# installation with something broken
set -e

# Generates a random password
function random_pass() {
  local PASS
  PASS=$(
    tr </dev/urandom -dc _A-Z-a-z-0-9\!@#$%^\& | head -c"${1:-16}"
    echo
  )
  echo "$PASS"
}

# Find our location
SCRIPTPATH="$(
  cd -- "$(dirname "$0")" >/dev/null 2>&1
  pwd -P
)"

# Set these values so the installer can run in color
COL_NC='\e[0m' # No Color
COL_LIGHT_GREEN='\e[1;32m'
COL_LIGHT_RED='\e[1;31m'
TICK="[${COL_LIGHT_GREEN}✓${COL_NC}]"
CROSS="[${COL_LIGHT_RED}✗${COL_NC}]"
INFO="[i]"

# Installation directories
SRC_DIR=$(dirname "$SCRIPTPATH")
INSTALL_DIR=/usr/share/netlog
FONTS_DIR=/usr/share/fonts
PHP_SHARED_DIR=/usr/share/php
SYSLOGNG_DIR=/etc/syslog-ng/conf.d
HTTPD_CONFD_DIR=/etc/httpd/conf.d
CROND_DIR=/etc/cron.d
NETLOG_PASS="$(random_pass)"
JPGRAPH_VER="4.4.1"
# MySQL setup
TEMPDIR=$(mktemp -d)
CNFFILE="/root/.my.cnf"

# For cleanup, or not if something in between fails
TEMP_CLEAN=true

## Start
printf "Starting installation of Netlog\\n"

## Main checks
if [[ -z "${USER}" ]]; then
  USER="$(id -un)"
fi

# Verify if we are running from the correct src_dir
if [[ "${SCRIPTPATH:0:${#SRC_DIR}}" != "$SRC_DIR" ]]; then
  printf "%b Installation from alternate source. Can not proceed\\n" "${CROSS}"
  printf "  %b Correct \$SRC_DIR in this file\\n" "${INFO}"
  exit 1
fi
# Running with the right permissions (or elevated)?
printf "  Check for permissions...\\t\\t"
if [[ "$EUID" -eq 0 ]]; then
  printf "%b Running as %s \\n" "${TICK}" "${USER}"
else
  printf "%b Installation must be done as 'root'. Can not proceed\\n" "${CROSS}"
  exit 1
fi

## Blocking & software checks

# Check if installation directory is not present
printf "  Check installation directory\\t\\t"
if [[ ! -L "$INSTALL_DIR" && ! -d "$INSTALL_DIR" ]]; then
  printf "%b No symlink or directory exists\\n" "${TICK}"
else
  printf "%b Symlink or directory exists\\n" "${CROSS}"
  printf "  %b Check existence and fix %s\\n" "${INFO}" "$INSTALL_DIR"
  exit 1
fi
# Check if Apache httpd is there
printf "  Check for Httpd...\\t\\t\\t"
if [[ "$(command -v httpd)" ]]; then
  printf "%b Httpd found (%s)\\n" "${TICK}" "$(httpd -v | grep -oP '(?<=version: )[^ ]*')"
else
  printf "%b No Httpd found. Can not proceed\\n" "${CROSS}"
  exit 1
fi
# Check if PHP is there
printf "  Check for PHP...\\t\\t\\t"
if [[ "$(command -v php)" ]]; then
  printf "%b PHP found (%s)\\n" "${TICK}" "$(php <<<"<?php echo PHP_VERSION ?>")"
else
  printf "%b No PHP found. Can not proceed\\n" "${CROSS}"
  exit 1
fi
# Check if accompanying directory is there
printf "  Check shared PHP directory...\\t\\t"
if [[ -d "$PHP_SHARED_DIR" ]]; then
  printf "%b shared PHP directory found\\n" "${TICK}"
else
  printf "%b No shared PHP directory found. Can not proceed\\n" "${CROSS}"
  exit 1
fi
# Check is Syslog-NG is there
printf "  Check for Syslog-NG...\\t\\t"
if [[ "$(command -v syslog-ng)" ]]; then
  printf "%b Syslog-NG found %s\\n" "${TICK}" "$(syslog-ng --version | grep -oP '\(.*\)')"
else
  printf "%b No Syslog-NG found. Can not proceed\\n" "${CROSS}"
  printf "  %b Install Syslog-NG manually, see https://github.com/syslog-ng/syslog-ng\\n" "${INFO}"
  exit 1
fi
# Check if accompanying directory is there
printf "  Check Syslog-NG directory...\\t\\t"
if [[ -d "$SYSLOGNG_DIR" ]]; then
  printf "%b Syslog-NG conf.d directory found\\n" "${TICK}"
else
  printf "%b No Syslog-NG conf.d directory found. Can not proceed\\n" "${CROSS}"
  exit 1
fi
# Check if MySQL is there
printf "  Check for MySQL...\\t\\t\\t"
if [[ "$(command -v mysqld)" ]]; then
  printf "%b MySQL found (%s)\\n" "${TICK}" "$($(command -v mysqld) --version | grep -oP '(?<=Ver )[^ ]*')"
else
  printf "%b No MySQL found. Can not proceed\\n" "${CROSS}"
  exit 1
fi
# Check if we can login automatically, if not prompt credentials
printf "  Check MySQL login credentials...\\t"
if [[ -f "$CNFFILE" ]]; then
  printf "%b Local .my.cnf found\\n" "${TICK}"
else
  printf "%b No local .my.cnf found, prompting\\n" "${CROSS}"
  printf "    Username: " && read -r MYSQLUSER
  printf "    Password: " && read -rs MYSQLPASS
  printf "\\n"
  # Put it in a temp file
  CNFFILE="$TEMPDIR/mycnf"
  printf "[client]\\nuser=%s\\npassword=\"%s\"\\n" "$MYSQLUSER" "$MYSQLPASS" >"$CNFFILE"
fi
# Check if we can login
printf "  Verify MySQL login\\t\\t\\t"
if mysql --defaults-file="$CNFFILE" -A -e 'exit' 1>/dev/null 2>&1; then
  printf "%b Login successful\\n" "${TICK}"
else
  printf "%b Login unsuccessful. Can not proceed\\n" "${CROSS}"
  exit 1
fi
# Check if the DBs do not exist, so it is a clean install
printf "  Check for the databases...\\t\\t"
if ! mysql --defaults-file="$CNFFILE" -A -e 'USE netlogconfig; USE syslog' 1>/dev/null 2>&1; then
  printf "%b Databases not found\\n" "${TICK}"
else
  printf "%b One or more databases found\\n" "${CROSS}"
  printf "  %b Check existence and fix or delete the databases. Either 'syslog' \\n" "${INFO}"
  printf "      or 'netlogconfig' found. Can not proceed\\n"
  exit 1
fi

## Non-blocking checks

printf "  Check for available font...\\t\\t"
if grep -q 'arial.ttf' <<<"$(fc-list)"; then
  printf "%b Required font found\\n" "${TICK}"
else
  if [[ -d "$FONTS_DIR" ]]; then
    printf "%b Fonts directory found\\n" "${TICK}"
  else
    printf "%b Fonts directory not found\\n" "${CROSS}"
    printf "  %b Unpack the %s/ext/msttcorefonts.tar.gz in your\\n" "${INFO}" "$SCRIPTPATH"
    printf "      fonts directory and correct the defined TrueType (TTF)\\n"
    printf "      variable in 'jpg-config.inc.php' of the JPGraph package\\n\\n"
  fi
fi
# Check httpd conf.d dir
printf "  Check Httpd conf.d directory...\\t"
if [[ -d "$HTTPD_CONFD_DIR" ]]; then
  printf "%b Httpd conf.d found\\n" "${TICK}"
else
  printf "%b No conf.d '%s' found\\n" "${CROSS}" "$HTTPD_CONFD_DIR"
  printf "  %b Copy config in %s/httpd.conf to the designated\\n" "${INFO}" "$SCRIPTPATH"
  printf "      directory of your Httpd daemon\\n"
fi
# Check cron.d dir
printf "  Check cron.d directory...\\t\\t"
if [[ -d "$CROND_DIR" ]]; then
  printf "%b cron.d found\\n" "${TICK}"
else
  printf "%b No cron.d '%s' found\\n" "${CROSS}" "$CROND_DIR"
  printf "  %b Copy jobs in %s/cronjob to your desired cron.\\n" "${INFO}" "$SCRIPTPATH"
fi

## Prompt for proceeding the installation and setup
while true; do
  printf "\\nDo you wish proceed with installing? (y/n default=y): " && read -r yn
  yn=${yn:-y}
  case $yn in
  [Yy]*) break ;;
  [Nn]*) rm -rf "$TEMPDIR"; exit 0 ;;
  *) printf "Please answer (y)es or (n)o.\n" ;;
  esac
done
printf "\\n"

printf "Installing...\\n\\n"

# Unpack fonts
if ! grep -q 'arial.ttf' <<<"$(fc-list)"; then
  if [[ -d "$FONTS_DIR" ]]; then
    printf "  Unpacking fonts...\\t\\t\\t"
    if [[ ! -d "$FONTS_DIR/truetype" ]]; then
      mkdir -p "$FONTS_DIR/truetype"
    fi
    tar -xf "$SCRIPTPATH/ext/msttcorefonts.tar.gz" -C "$FONTS_DIR/truetype"
    printf "%b TrueType fonts unpacked\\n" "${TICK}"
  fi
fi
# Check or unpack JPGraph
if [[ -d "$PHP_SHARED_DIR" ]]; then
  printf "  Check presence JPGraph...\\t\\t"
  if php -r "require_once('jpgraph/jpgraph.php');" 1>/dev/null 2>&1 <<<echo; then
    printf "%b Package JPGraph already present\\n" "${TICK}"
  else
    if [[ ! -d "$PHP_SHARED_DIR/" ]]; then
      mkdir -p "$PHP_SHARED_DIR/truetype"
    fi
    tar -xf "$SCRIPTPATH/ext/jpgraph-$JPGRAPH_VER.tar.gz" -C /usr/local/src/
    chown root:root "/usr/local/src/jpgraph-$JPGRAPH_VER"
    ln -s /usr/local/src/jpgraph-$JPGRAPH_VER/src /usr/share/php/jpgraph
    sed -i "40i define(\'TTF_DIR\',\'$FONTS_DIR/truetype/msttcorefonts/\');" /usr/share/php/jpgraph/jpg-config.inc.php
    printf "%b Package JPGraph unpacked\\n" "${TICK}"
  fi
fi

# Check and setup logparser daemon
printf "  Check presence Logparser daemon\\t"
if [[ ! -f /usr/lib/systemd/system/logparser.service ]]; then
  cp "$SCRIPTPATH/logparser.service" /usr/lib/systemd/system/
  /bin/systemctl daemon-reload 1>/dev/null 2>&1
  /bin/systemctl enable logparser 1>/dev/null 2>&1
  printf "%b Logparser daemon installed\\n" "${TICK}"
else
  printf "%b Logparser daemon found\\n" "${CROSS}"
  printf "  %b The /usr/lib/systemd/system/logparser.service already\\n" "${INFO}"
  printf "      exists. Compare differences with the file located at\\n"
  printf "      %s for differences\\n" "$SCRIPTPATH/logparser.service"
fi

# Copy the SQL files to the temp directory
cp "$SCRIPTPATH"/*.sql "$TEMPDIR/"
sed -i "s/<<PASSWORD>>/$NETLOG_PASS/" "$TEMPDIR"/*.sql

printf "  Importing SQL data into database\\t"
SYSLOG_IN=false
NLCONFIG_IN=false
if mysql --defaults-file="$CNFFILE" -A <"$TEMPDIR/syslog.sql" 1>/dev/null 2>&1; then
  SYSLOG_IN=true
fi
if mysql --defaults-file="$CNFFILE" -A <"$TEMPDIR/netlogconfig.sql" 1>/dev/null 2>&1; then
  NLCONFIG_IN=true
fi
if $SYSLOG_IN && $NLCONFIG_IN; then
  printf "%b SQL imported successful\\n" "${TICK}"
else
  printf "%b SQL import unsuccessful\\n" "${CROSS}"
  printf "  %b Importfiles in %s\\n" "${INFO}" "$TEMPDIR"
  TEMP_CLEAN=false
fi

# Make installation target as a symlink
# This is easy for future upgrades or releases. Just switch-out the symlink
# with the new release, test and if failed; return quick
if [[ ! -L "$INSTALL_DIR" && ! -d "$INSTALL_DIR" ]]; then
  # Symlink and name does not exist, make it
  ln -s "$SRC_DIR" "$INSTALL_DIR"
fi

# Copy the Netlog conf file to its location
printf "  Copying the netlog config\\t\\t"
cp "$SCRIPTPATH/netlog.conf.example" "$TEMPDIR/netlog.conf"
sed -i "s/<<PASSWORD>>/$NETLOG_PASS/" "$TEMPDIR/netlog.conf"
if [[ ! -f "$INSTALL_DIR/etc/netlog.conf" ]]; then
  cp "$TEMPDIR/netlog.conf" "$INSTALL_DIR/etc/netlog.conf"
  printf "%b Netlog.conf successfuly copied\\n" "${TICK}"
else
  printf "%b Config already exists\\n" "${CROSS}"
  printf "  %b Verify existence of %s and compare\\n" "${INFO}" "$INSTALL_DIR/etc/netlog.conf"
  printf "      with %s\\n" "$TEMPDIR/netlog.conf"
  TEMP_CLEAN=false
fi

# Copy the Httpd conf file to its location
printf "  Copying the Httpd config\\t\\t"
if [[ ! -f "$HTTPD_CONFD_DIR/netlog.conf" ]]; then
  cp "$SCRIPTPATH/httpd.conf" "$HTTPD_CONFD_DIR/netlog.conf"
  printf "%b Httpd config successfuly copied\\n" "${TICK}"
else
  printf "%b Config already exists\\n" "${CROSS}"
  printf "  %b Verify existence of %s and compare\\n" "${INFO}" "$INSTALL_DIR/httpd.conf"
  printf "      with %s\\n" "$HTTPD_CONFD_DIR/netlog.conf"
fi

# Copy the Syslog-NG conf file to its location
printf "  Copying the Syslog-NG config\\t\\t"
if [[ ! -f "$SYSLOGNG_DIR/netlog.conf" ]]; then
  cp "$SCRIPTPATH/syslog.conf" "$SYSLOGNG_DIR/netlog.conf"
  printf "%b Syslog-NG config successfuly copied\\n" "${TICK}"
else
  printf "%b Config already exists\\n" "${CROSS}"
  printf "  %b Verify existence of %s and compare\\n" "${INFO}" "$INSTALL_DIR/syslog.conf"
  printf "      with %s\\n" "$SYSLOGNG_DIR/netlog.conf"
fi

if [[ "$(command -v getenforce)" ]]; then
  # Selinux is there, reset labels
  restorecon -rF "$PHP_SHARED_DIR"/jpgraph "$FONTS_DIR"/truetype/msttcorefonts
  restorecon -rF "$SYSLOGNG_DIR"/netlog.conf "$HTTPD_CONFD_DIR"/netlog.conf
fi

printf "  Restarting daemons:\\n"
systemctl restart syslog-ng httpd logparser
# Give it some time..
sleep 2
if [[ "$(systemctl is-active logparser)" == "active" ]]; then
  printf "  %b Logparser running\\n" "${TICK}"
else
  printf "  %b Logparser failed. Check your system logs\\n" "${CROSS}"
fi
if [[ "$(systemctl is-active httpd)" == "active" ]]; then
  printf "  %b Httpd running\\n" "${TICK}"
else
  printf "  %b Httpd failed. Check your system logs\\n" "${CROSS}"
fi
if [[ "$(systemctl is-active syslog-ng)" == "active" ]]; then
  printf "  %b Syslog-NG running\\n" "${TICK}"
else
  printf "  %b Syslog-NG failed. Check your system logs\\n" "${CROSS}"
fi

# Finally
# Copy the cronjob to it destination
printf "  Copying the cron to /etc/cron.d\\t"
if [[ ! -f "$CROND_DIR"/netlog ]]; then
  cp "$SCRIPTPATH/netlog.cronjob" "$CROND_DIR"/netlog
  printf "%b Cron successfuly copied\\n" "${TICK}"
else
  printf "%b Cron alreay exists\\n" "${CROSS}"
  printf "  %b Verify existence of %s/netlog and compare\\n" "${INFO}" "$CROND_DIR"
  printf "      with %s\\n" "$SCRIPTPATH/netlog.cronjob"
fi

printf "  Cleaning up temporary files\\t\\t"
if $TEMP_CLEAN; then
  rm -rf "$TEMPDIR"
  printf "%b Temporary directory removed\\n" "${TICK}"
else
  printf "%b Cleanup failed\\n" "${CROSS}"
  printf "  %b Temporary directory not removed due to previous errors\\n" "${INFO}"
  printf "      Directory is: %s\\n" "$TEMPDIR"
fi

printf "\\n\\nIf all went OK then you can open your browser and point towards\\n"
printf "the FQDN/IP-address of this server followed by '/netlog' \\n"
printf "If needed corrections should be made to those items pointed out.\\n"
printf "Make sure any firewall rules (UDP/TCP 514) are added to your system\\n"
printf "to receive the remote syslog messages.\\n"

exit 0
