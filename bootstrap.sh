#!/usr/bin/env bash

# become root
sudo su

# update the repos
cd /var/www/html/neumatic
cp -r .vagrant/.bash* /home/vagrant
cp -r .vagrant/autotools-* .vagrant/epel.repo /etc/yum.repos.d

# clean up and install httpd / php
yum clean all
yum install -y --nogpgcheck php php-ZendFramework2
mkdir -p /usr/share/php/ZF2/library
ln -s /usr/share/php/Zend /usr/share/php/ZF2/library/Zend

yum install -y httpd
yum install -y mysql.x86_64 mysql-libs.x86_64 mysql-server.x86_64 php-mysql.x86_64
yum install -y freetype-devel fontconfig-devel

cp .vagrant/sts.env .vagrant/vhost-neumatic.conf /etc/httpd/conf.d
service mysqld start
service httpd start

# put selinux in permissive mode for to play nice
setenforce 0

# link our public directory for apache serivce
#ln -sf /vagrant/public /var/www/html/public

yum install -y sts-lib
ln -s /var/www/html/sts-lib /opt/sts-lib
find /var/www/html/sts-lib -type d -exec chmod 755 {} \;
find /var/www/html/sts-lib -type f -exec chmod 644 {} \;

mkdir /var/log/neumatic
chown apache.apache /var/log/neumatic
touch /var/www/html/neumatic/vendor/Vmwarephp/.clone_ticket.cache
chmod 777 /var/www/html/neumatic/vendor/Vmwarephp/.clone_ticket.cache
chmod 777 /var/www/html/neumatic/vendor/Vmwarephp/.wsdl_class_map.cache

.vagrant/node-install.sh

