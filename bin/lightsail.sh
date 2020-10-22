#! /bin/sh

# Web Server Ready
sudo apt-get update
sudo apt-get install -y apache2
sudo apt-get install -y php7.4 php-fpm php-cli php-xml php-mbstring php-gd php-curl php-zip

# Http 2

sudo a2enmod proxy_fcgi setenvif
sudo a2enconf php7.4-fpm
sudo a2enmod http2

sudo apachectl -k restart


# Certbot Ready: https://certbot.eff.org/lets-encrypt/ubuntufocal-apache
sudo snap install core; sudo snap refresh core
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot

# still need to run > `sudo certbot --apache` once site is configured

# Configure Webserver