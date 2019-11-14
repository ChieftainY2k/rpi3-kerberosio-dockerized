#!/bin/bash


# Parts taken from https://gist.githubusercontent.com/ecampidoglio/5009512/raw/2efdb8535b30c2f8f9a391f055216c2a7f37e28b/cpustatus.sh

#helper function
log_message()
{
    LOGPREFIX="[$(date '+%Y-%m-%d %H:%M:%S')][healthreporter]"
    MESSAGE=$1
    echo "$LOGPREFIX $MESSAGE"
}

#check for errors
check_errors()
{
    EXITCODE=$1
    if [[ ${EXITCODE} -ne 0 ]]; then
        log_message "ERROR: there were some errors, check the ouput for details."
        exit 1
    fi
}

function convert_to_MHz {
    let value=$1/1000
    echo "$value"
}

function calculate_overvolts {
    # We can safely ignore the integer
    # part of the decimal argument
    # since it's not realistic to run the Pi
    # at voltages higher than 1.99 V
    let overvolts=${1#*.}-20
    echo "$overvolts"
}

#load services configuration
export $(grep -v '^#' /service-configs/services.conf | xargs -d '\n')

temp=$(vcgencmd measure_temp)
temp=${temp:5:4}

volts=$(vcgencmd measure_volts)
volts=${volts:5:4}

if [[ ${volts} != "1.20" ]]; then
    overvolts=$(calculate_overvolts ${volts})
fi

minFreq=$(cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_min_freq)
minFreq=$(convert_to_MHz ${minFreq})

maxFreq=$(cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_max_freq)
maxFreq=$(convert_to_MHz ${maxFreq})

freq=$(cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq)
freq=$(convert_to_MHz ${freq})

governor=$(cat /sys/devices/system/cpu/cpu0/cpufreq/scaling_governor)

DATE=`date '+%Y-%m-%d %H:%M:%S'`

echo -n "[$DATE] current hardware stats: "
echo -n "Temperature: $temp C"
echo -n ", voltage: $volts V"
[[ ${overvolts} ]] && echo -n " (+0.$overvolts overvolt)"
echo -n ", min speed: $minFreq MHz"
echo -n ", max speed: $maxFreq MHz"
echo -n ", current speed: $freq MHz"
echo -n ", governor: $governor"

# @FIXME use env/config var to get media directory here
availableDiskSpaceKb=$(df / | tail -1  | awk '{print $4/1}')
totalDiskSpaceKb=$(df /  | tail -1 | awk '{print $2}')

echo -n ", total disk space: $totalDiskSpaceKb kb"
echo ", available disk space: $availableDiskSpaceKb kb"

imagedir=/etc/opt/kerberosio/capture/

uptimeSeconds=$(echo $(awk '{print $1}' /proc/uptime) *100 /100 | bc)
timestamp=$(date +%s)
localTime=$(date '+%Y-%m-%d %H:%M:%S')
totalFilesSizeKb=$(du ${imagedir} | tail -1 | awk '{print $1}') # total size of captured files

#check the health of the kerberos stream
#streamFFprobeOutput=$(ffprobe http://kerberos:8889 2>&1 | tail -1)
#log_message "FFProbe output = $streamFFprobeOutput"

#@FIXME make it universal, do not assume any services' names
NGROK_SERVICE_REPORT_JSON=$(cat /data-services-health-reports/ngrok/report.json)
NGROK_SERVICE_REPORT_JSON=${NGROK_SERVICE_REPORT_JSON:-"{}"}

KERBEROS_SERVICE_REPORT_JSON=$(cat /data-services-health-reports/kerberos/report.json)
KERBEROS_SERVICE_REPORT_JSON=${KERBEROS_SERVICE_REPORT_JSON:-"{}"}

# prepare JSON message
messageJson=$(cat <<EOF
{
    "version":"2",
    "system_name":"${KD_SYSTEM_NAME}",
    "timestamp":"$timestamp",
    "local_time":"$localTime",
    "cpu_temp":"$temp",
    "cpu_voltage":"$volts",
    "uptime_seconds":"$uptimeSeconds",
    "disk_space_available_kb":"$availableDiskSpaceKb",
    "disk_space_total_kb":"$totalDiskSpaceKb",
    "images_size_kb":"$totalFilesSizeKb",
    "services":{
        "alpr":{"is_enabled":"${KD_ALPR_ENABLED}"},
        "email_notification":{"is_enabled":"${KD_EMAIL_NOTIFICATION_ENABLED}"},
        "mqtt_bridge":{"is_enabled":"${KD_MQTT_BRIDGE_ENABLED}"},
        "ngrok":${NGROK_SERVICE_REPORT_JSON},
        "kerberos":${KERBEROS_SERVICE_REPORT_JSON}
    }
}
EOF
)

#messageJson=$(echo $messageJson | sed -z 's/\n/ /g' | sed -z 's/\"/\\\"/g')
messageJson=$(echo ${messageJson} | sed -z 's/\n/ /g' | sed -z 's/"/\"/g')
messageTopic="healthcheck/report"

#publish it
log_message "Attempting to publish MQTT topic $messageTopic with message $messageJson"
mosquitto_pub -h mqtt-server --retain -t "$messageTopic" -m "$messageJson"
check_errors $?

