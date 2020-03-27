![Overall diagram](./docs/images/kerberos-flow.png "Dockerized KerberosIO flow")

**Overview**

This project is an attempt at creating universal platform for service-based sensor/motion detection and notification system on Raspberry Pi 2/3.

The goal was to create platform for separated services that can be easily managed/updated/modified/developed/enabled/disabled.

This project was successfully tested with **Raspberry Pi 2** and **Raspberry Pi 3**.

**How the platform works ?**

* Each service runs in a separate docker container
* The core services are
  * Kerberos-io motion detection service (https://github.com/kerberos-io/)
  * MQTT server/broker service (https://mosquitto.org/)
  * Configurator (web based user interface to change services configuration and restart services)
  * Email sender 
* Docker containers are managed by docker-compose
* Containers have access to some shared file space (so that they can access media files etc.) 
* A service may publish MQTT topics or subscribe to a topic to react accordingly. 
* Services communicate with each other using the local MQTT server (topics with JSON payloads).
* A service may interact with some input/output hardware device (like camera, audio output, temperature sensors etc.) 
* A service may use remote services (like external MQTT server, SMTP server, IFTTT server etc.) to get its job done (like sending emails via a SMTP server).
* Want to know more what a service does ? Check out the /services directory and relevant README files

Enjoy! :-)
 

**Raspberry preparation**

* Grab the newest Raspbian (Buster Lite) from https://downloads.raspberrypi.org/ , install it on a SD card (8GB at least, 16GB would be nice).
* Update packages: `sudo apt-get -y update && sudo apt-get -y upgrade` 
* Configure your time zone (`raspi-config -> localisation -> change timezone`)
* If you want to use camera then enable the camera module support (`raspi-config -> interfacing -> camera`)
* If RAM is less than 500MB set video memory to 8MB (`raspi-config -> advanced -> memory split`)
* If camera will be used set video memory to 128MB (`raspi-config -> advanced -> memory split`)
* If RAM is less than 500MB increase the swap space (edit the `/etc/dphys-swapfile` , set `CONF_SWAPSIZE=400`)
* Reboot

**(OPTIONAL) Raspberry tuning**
* Set CPU overclocking to max available value (`raspi-config -> overclock`)
* Check filesystem on every boot (put `fsck.mode=force` at the end of line in `/boot/cmdline.txt`) 
* Harden against brute-force ssh password guessing attacks (`apt-get install fail2ban`) 
* Reboot

**(OPTIONAL) Configure startup scripts to send an email on each reboot**
* Install email tools: `apt-get install ssmtp mailutils` 
* Edit ssmtp config: `nano /etc/ssmtp/ssmtp.conf` with the following config (gmail.com as an example):
`````
root=YOUR_GMAIL_USER@gmail.com
mailhub=smtp.gmail.com:587
hostname=gmail.com
AuthUser=YOUR_GMAIL_USER@gmail.com
AuthPass=YOUR_GMAIL_PASSWORD
FromLineOverride=YES
UseSTARTTLS=YES
````` 
* Edit local startup script `nano /etc/rc.local` , add the following snippet:
`````
_IP=$(hostname -I) || true
if [ "$_IP" ]; then
  printf "My IP address is %s\n" "$_IP"
fi

echo "sending emails..."
SUBJECT="$(hostname) restarted"
BODY=" $(hostname) restarted. Local IP is $_IP "
echo "$BODY" | mail -s "$SUBJECT" YOUR_GMAIL_USER@gmail.com 
````` 

**Garda Installation**

1. Clone this repository to a directory of your choice (preferably `$HOME/raspberry-garda/`)
1. Rename the file `configs/services.conf.template` to `configs/services.conf` then edit it and update with your configuration (like SMTP host/password etc.)
1. Run `sudo ./garda.sh install` to install everything needed
1. Run `sudo ./garda.sh check` to check environment and hardware
1. (optional) Run `sudo ./garda.sh watchdog install` to install watchdog scripts to reboot host or perform some other "last resort" operations when something is wrong (i.e. internet connection is lost)


**Starting up**

1. Run `sudo ./garda.sh start`
1. Go to the kerberos installation page at `http://_YOUR_RASPBERRY_PI_ADDRESS_`
1. Check the video stream at `http://_YOUR_RASPBERRY_PI_ADDRESS_/stream`

Note: The application services will be automatically restarted on reboot, unless you explicitely stop it (see instructions below).

**Configuration (shell)**

1. Edit the `configs/services.conf` file (if it doesn't exist then create it and copy the content from `configs/services.conf.template` file)
1. Restart the services:
  `./garda.sh restart`

**Configuration (web GUI)**

1. Edit `configs/services.conf` and set the `KD_UI_USER` `KD_UI_PASSWORD` values with a password of your choice
1. Go to `http://_YOUR_RASPBERRY_PI_ADDRESS_/configurator`   

**Stop the system**
`````
./garda.sh stop 
`````

**Start up the system again**
`````
./garda.sh start 
`````

**Show containers output/logs (last 10 lines, then follow the output)**
`````
./garda.sh logs
`````

**Show service logs**
`````
./garda.sh log [SERVICE]
`````
Example:
`````
./garda.sh log kerberos
`````


**Run bash inside SERVICE container**
`````
./garda.sh shell [SERVICE]
`````
Example:
`````
./garda.sh shell kerberos
`````

**Hardening (optional)**

* Change default passwords
* Use unattended upgrades
* Use Fail2Ban 
* Put your IoT device behind second NAT/router 

