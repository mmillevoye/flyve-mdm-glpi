[![Build Status](https://travis-ci.org/flyve-mdm/flyve-mdm-glpi.svg?branch=master)](https://travis-ci.org/flyve-mdm/flyve-mdm-glpi)

# Abstract

Flyve MDM Plugin for GLPi is a subproject of Flyve MDM. Flyve MDM is a mobile
device management software.

# Installation

## General view of the infrastructure

You need several servers to run Flyve MDM:
* a server running Linux, Apache, Mysql/MariaDB and PHP (a LAMP server),
* a server running Mosquitto,
* a server running the web interface.

## Installation overview

Flyve MDM runs on GLPi 9.1.1 and later. It depends on inventory features of FusionInventory for GLPi. You need FusionInventory 9.1+1.0 or later. The version depends on the version of GLPi you're planning to setup.

The general steps to properly configure the whole infrastructure are :
* install GLPi
* install FusionInventory and Flyve MDM plugin for GLPi
* configure Flyve MDM plugin for GLPi
* configure your DBMS
* Install and configure Mosquitto
* Install and configure the web application

## Dependencies

This plugin is depends on GLPi, FusionInventory for GLPi and a some packages

* Download our specific version of GLPi 9.1.1 (please refer to its documentation to install)
* Download FusionInventory 9.1+1.0 for GLPi and put it in glpi/plugins/
* Donwload Flyve MDM for GLPi and put it in glpi/plugins/

You will probably ask why you need a specific version of GLPi. Flyve MDM relies on a rest API GLPi developed recently. Flyve MDM requires some improvements which are not in the latest stable relase of GLPi. The specific version of GLPi we provide is the latest stable version, with a few backports from the development versions, to satisfy our needs.

You should have a directory structure like this :

```
glpi
|
+ plugins
  |
  + fusioninventory
  + storkmdm
```

* Go in the directory  glpi/plugins/storkmdm
* Run composer install --no-dev

## Configuration of GLPi

These steps are mandatory.

### Cron

Ensure the system has PHP CLI, then setup a cron job similar to the example below.

```
*/1 * * * * /usr/bin/php5 /var/www/glpi/front/cron.php &>/dev/null
```

Adjust the **path to PHP** and the **path to cron.php**

### Notifications

Login in GLPi with a super admin account
In the menu **Setup > Notifications** click on Enable followup via email. The page refreshes itself. Click on **Email followups configuration** and setup the form depending on your requirements to send emails.

In **Setup > Automatic actions** open queuedmail. Set Run mode to **CLI**. This action is now triggered by the cron job every minute.

To ensure the cronjob is properly configured, check the log **glpi/files/_log/cron.log**. If a log entry contains the word **External** then the job fired from cron. Jobs manually fired from the UI would show **Internal** instead.

Example of a log entry
```
External #1: Launch queuedmail
2016-12-21 10:22:02 [@my-server]
```

### Enabling the rest API

* Open the menu **Setup > General** and select the tab **API**
* enable rest API
* enable **login with credentials**
* enable **login with external tokens**
* Check there is a full access API client able to use the API from any IPv4 or IPv6 address (click it to read and/or edit)

### Configuration of FusionIventory

#### Fusioninventory greater than **9.1+1.0**

* Open **Administration > Rules > FusionInventory - Equipment import and link rules**

* If a rule named **Computer constraint (name)** exists, then open it and disable it.

Missing this will make FusionInventory reject inventories from devices.

### Configuration of Flyve MDM for GLPi

* Open **Configuration > Plugins**
* Click on **Stork Mobile Device Management**

* **mqtt broker address** is the public hostname or IP address of Mosquitto. It is sent to devices to tell them where is your Mosquitto server on the Internet. (*mandatory*).
* **mqtt broker internal address** is the private hostname or IP address of Mosquitto. It is used to tell GLPi where is your Mosquitto server in your local network. (*mandatory*).
* **mqtt broker port** is the port used by your mobile devices *and* GLPi (*mandatory*).
* **use TLS** enables TLS communication for mobile devices *and* GLPi.
* **CA certificate** is the certificate of an authority to verify the Mosquitto server.
* **Cipher suite** is used to limit the ciphers used with TLS.

* **use client certificates** (*not working yet*) is used to allow mobile devices to verify the Mosquitto server
* **Ssl certificate server for MQTT clients** (*not working yet*) is a server which signs certificate requests of devices. This is for a future and stronger authentication method of devices with Mosquitto.

* **Enable explicit enrolment failures** sends to devices the exact reason of an enrollment failure. For debug purpose only.
* **Disable token expiration on successful enrolment** is to prevent a token to expire when a device successfully enrolls. For debug purpose only.

* **Android bug collector URL** is the URL of a ACRA server. This server collects crash reports sent by Flyve MDM for Android.
* **Android bug collector user** is the username used by devices when they send a crash report.
* **Android bug collector password** is the password used by devices when they send a ccrash report.

* **Default device limit per entity** is the maximum uantity of devices allowed in an entity. Designed for the demo mode, but might be useful to enhance security. 
* **Service's User Token** is the token to put un config.js when setting up the web application.

### Security

FlyveMDM needs only the REST API feature of GLPi to work with devices and its web interface.
Expose to the world only the API of GLPi, and keep inacessible GLPi's user interface.

Have a look into **glpi/.htaccess** if you can use  Apache's mod_rewrite.

The directory **glpi/plugins/storkmdm/scripts** must be inaccessible from the webserver.

* If running Apache, the .htaccess file in this directory will do the job.
* If running an other server like Nginx, please configure the host properly.

## Mysql / MariaDB

The DBMS must provide an access to the message queuing server for the authentication process of its clients. This server will be configured below; let's focus on the DBMS for now.

Assuming your DBMS server is not exposed to the world and the message queuing is on an other server, edit **my.cnf** to listen on **0.0.0.0** instead of 127.0.0.1.

```
bind-address = 0.0.0.0
```

Create a new user in the DBMS able to read only the GLPI's database, and restrict this user to the IP of the future message queuing server.

## Mosquitto

### Version considerations
Use Mosquitto **v1.4.8** or greater. You may encouteer crashes with older versions.

Flyve MDM needs an authentication plugin for Mosquitto to authenticate MQTT clients against users stored in GLPi's DBMS.

### Compile Mosquitto (needs improvement)

If you need to compile Mosquitto use the official sources

[mosquitto repository](https://github.com/eclipse/mosquitto)


* Edit **config.mk** and change WITH_SRV:=NO

* Install dependencies and compile

* Install Mosquitto

* Copy /etc/mosquitto/mosquitto.conf.example to /etc/mosquitto/mosquitto.conf
```
cp /etc/mosquitto/mosquitto.conf.example /etc/mosquitto/mosquitto.conf
```

* Create /var/lib/mosquitto
```
mkdir /var/lib/mosquitto
```

Create a user to run Mosquitto
```
adduser mosquitto --home /var/lib/mosquitto --shell /usr/sbin/nologin --no-create-home --system --group
```

* Make a init.d startup script

### Configuration

If you installed Mosquitto from your distribution package, its settings may be located in several files.
The main configuration file is **/etc/mosquitto/mosquitto.conf**

Official documentation to configure Mosquitto : http://mosquitto.org/man/mosquitto-conf-5.html

We recomend you setup Mosquitto without encryption frist, validate its configuration, then enable encryption. Of course, don't expose your setup to the world without encryption !

#### default unencrypted listener

Check the following settings to setup an unencrypted communication :
* pid_file /var/run/mosquitto.pid
* user mosquitto
* persistent_client_expiration 2m
* persistence true
* persistence_file mosquitto.db
* persistence_location /var/lib/mosquitto
* port 1883
* allow_anonymous false


#### TLS listener
Assuming you successfully configured Mosquitto without encryption, use the following :

This  example assumes cachain.crt contains the CA chain and your certificate.

* Copy in /etc/mosquitto/certs your certificate your certificate authority chain and your private key. 
* Secure your private key
```
chmod 600 /etc/mosquitto/certs/private-key.key
chown mosquitto:root /etc/mosquitto/certs/private-key.key
```
* refresh hash and symlinks to your certificates
```
c_rehash /etc/mosquitto/certs
```

You should use an certificate signed by a certified authority or you may have trouble with the android devices. Using android devices with custom certification authorities might not work (not tested).

```
listener 8883

cafile /etc/mosquitto/certs/cachain.crt
certfile /etc/mosquitto/certs/cachain.crt
keyfile /etc/mosquitto/certs/private-key.key
tls_version tlsv1.2
```

Note : **you should NOT use tls_version lower than tlsv1.2**. TLS version 1.0 and 1.1 are no longer considered safe.

Restart Mosquitto

#### disable default unencrypted listener
Assuming you successfully enabled TLS, remove the following settings :
* bind_address
* port

Restart Mosquitto

## Mosquitto authentication plugin

### Compile the plugin if needed

If you need to compile the plugin, use the official sources.

[mosquitto-auth-plugin repository](https://github.com/jpmens/mosquitto-auth-plug)

* copy **config.mk.in** to **config.mk**
```
cp config.mk.in config.mk
```
* Edit config.mk to customize the path to Mosquitto's sources if you had to compile it
* Compile the plugin
* Copy the module auth-plug.so into /usr/local/lib/libmosquitto-auth-plug.so

### Configure the plugin

If you compiled the plugin edit **/etc/mosquitto/mosquitto.conf**. If you installed it from your distro's packages you should edit **/etc/mosquitto/conf.d/auth-plug.conf**

Add / edit the following settings

```
auth_plugin /usr/local/lib/libmosquitto-auth-plug.so
auth_opt_backends mysql
auth_opt_host backend-server-ip-or-fqdn
auth_opt_port 3306
auth_opt_user database-user
auth_opt_dbname glpi
auth_opt_pass StrongPassword
#auth_opt_superquery
auth_opt_userquery SELECT password FROM glpi_plugin_storkmdm_mqttusers WHERE user='%s' AND enabled='1'
auth_opt_aclquery SELECT topic FROM glpi_plugin_storkmdm_mqttacls a LEFT JOIN glpi_plugin_storkmdm_mqttusers u ON (a.plugin_storkmdm_mqttusers_id = u.id) WHERE u.user='%s' AND u.enabled='1' AND (a.access_level & %d)
auth_opt_cacheseconds 300

```
Adapt the server, port and credentials to your setup.
* The server is your MySQL or MariaDB IP or hostname
* the port is  the listening port of your DBMS
* user and password should be a user able to read only your DB. No need to grant any write access. You created these credentials while setting up the DBMS.

# Contributing

## Tests

* Go to the folder containing GLPi
* Run composer install
* Run php tools/cliinstall.php --tests --user=database-user --pass=database-pass --db=glpi-test
* Go to plugins/storkmdm
* Run php tools/cliinstall.php --tests
* Run phpunit
