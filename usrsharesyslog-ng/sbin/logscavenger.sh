#!/bin/sh
PATH="/usr/local/bin:/usr/bin:/bin"
export PATH
LOCK_FILE=/var/run/logscavenger_dm.pid
LOGSCAVENGER_ENABLED=1

#if test -z ${AWSTATS_ENABLED}; then
#	AWSTATS_ENABLED=0
#fi

### SUB's
delete_lock_file() {
	_DELETE_LOCKF=`rm -f ${LOCK_FILE}`
}

run_logscavenger() {
	/usr/bin/php /usr/share/syslog-ng/php/logscavenger.php 1>/dev/null 2>/dev/null
}

pid_check() {
	_LOCK_PID=$(cat ${LOCK_FILE});
	
	if test -n "${_LOCK_PID}"
	then
		_PID_CHECK=`ps auxw|awk {'print \$2'}|grep ^${_LOCK_PID}\$|grep -v grep`;
		
		if test -n "${_PID_CHECK}" ; then
			if test -n "${TERM}" && test ${TERM} != "dumb" ; then
				echo -e "ERROR: lock-file ${LOCK_FILE} already exists! PID: ${_LOCK_PID}";
			fi
			exit;
		else
#			echo -e "DEBUG: DELETING LOCK_FILE"
			delete_lock_file ""
		fi
	fi
}

create_lockfile() {
	_CREATE_LOCKF=`echo $$ > ${LOCK_FILE}`
}

check_lockfile() {
        if test -f "${LOCK_FILE}" ; then
		pid_check ""
        fi
}
###

check_lockfile ""
create_lockfile ""
run_logscavenger ""
delete_lock_file ""
