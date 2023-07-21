#!/bin/bash

cd /var/chef/$2

knife ssh --ssh-user stsapps --ssh-password k8btJS\$aVrs "name:$1" 'echo "k8btJS$aVrs" | sudo -S chef-client'
       

