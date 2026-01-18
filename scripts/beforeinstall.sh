#!/bin/bash
set +e
sudo rm -r  /var/www/html/yaffa_old
sudo mv /var/www/html/yaffa /var/www/html/yaffa_old
sudo mkdir /var/www/html/yaffa
