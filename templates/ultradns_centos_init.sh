#!/bin/bash


fqdn=
env=

usage()
{
cat<<EOF
usage: $0 options

This script initializes a server. It sets the hostname, yum repos, resolv.conf and installs puppet.

OPTIONS:
    -H fqdn
    -e environment (production, qa, development)
    -p puppetmaster

EOF
}

while getopts "H:e:p:" OPTION
do
    case $OPTION in
        H)
            fqdn=$OPTARG
            ;;
        e)
            env=$OPTARG
            ;;
        p)
            pm=$OPTARG
            ;;
        ?)
            usage
            exit 1
            ;;
    esac
done
if [[ -z $fqdn ]] || [[ -z $env ]] || [[ -z $pm ]]; then
    usage
    exit 1
fi

#Get version of centos
vstring=`/bin/cat /etc/redhat-release`

echo $vstring
yum_extension=""
if [[ "$vstring" == *6.1* ]]; then
    version="6.1"
    majorver="6"
    minorver="1"
elif [[ "$vstring" == *5.3* ]]; then
    version="5.3"
    majorver="5"
    minorver="3"
else
    echo "This script needs to be modified to handle ${vstring}."
    exit 1
fi

# Are we on a VM?
/usr/sbin/dmidecode | grep "Product Name: KVM" > /dev/null
VM=$? #VM == 0 means we're on a VM. 1 means physical host.

if [ $VM -eq 1 ]; then
    #Physical server
    # append ' vga=normal nomodeset 3' to the end of the kernel arguments
  grep "vga=normal nomodeset" /boot/grub/grub.conf
  RETURN=$?
  if [[ $RETURN == 0 ]];then
    /usr/bin/perl -pi -e "s/(^\s*kernel.*)$/\1 vga=normal nomodeset 3/" /boot/grub/grub.conf
  fi
fi
# Finished with grub.conf file



cat > /etc/resolv.conf <<EOF
search ultradns.net
nameserver 8.8.8.8
nameserver 8.8.4.4
EOF

cat > /etc/hosts <<EOF
127.0.0.1   localhost localhost.localdomain localhost4 localhost4.localdomain4
::1         localhost localhost.localdomain localhost6 localhost6.localdomain6
EOF

cat > /etc/sysconfig/network <<EOF
NETWORKING=yes
HOSTNAME=$fqdn
EOF

hostname $fqdn

/usr/sbin/ntpdate 0.centos.pool.ntp.org
/sbin/hwclock --systohc

#Make sure that the ethernet interface with a link is configured and set to eth0
mac_addr=`/sbin/ip link show | /bin/grep ether | /bin/awk '{print $2}' | /usr/bin/tr 'a-z' 'A-Z'|head -n1`
/bin/rm /etc/sysconfig/network-scripts/ifcfg-eth*
cat > /etc/sysconfig/network-scripts/ifcfg-eth0 <<EOF
DEVICE=eth0
BOOTPROTO=dhcp
IPV6INIT=yes
NM_CONTROLLED=no
ONBOOT=yes
TYPE=Ethernet
HWADDR=${mac_addr}
EOF
cat /dev/null > /etc/udev/rules.d/70-persistent-net.rules
#End eth0 configuration


/bin/rm /etc/yum.repos.d/*

cat > /etc/yum.repos.d/CentosBase.repo <<EOF
[centos_${majorver}${minorver}]
name=Centos ${version}
baseurl=http://repo.prod.ultradns.net/os/centos/${version}/os/x86_64
gpgcheck=0
enabled=1
EOF

if [[ $majorver == "5" ]];then
  rm /etc/enterprise-release
  yum clean all
  yum -y install yum-security 
  echo "rpm -e..."
  rpm -e selinux-policy xorg-x11-server-Xorg xorg-x11-drv-evdev  xorg-x11-drv-keyboard rhpxl xorg-x11-drv-void xorg-x11-drv-vesa anaconda up2date xorg-x11-drv-mouse anaconda-runtime up2date-gnome pirut system-config-kickstart selinux-policy-targeted
  echo "second rpm -e..."
  rpm -e nrpe ultradns-mrtg-probes nrpe-cfg-ultra
  echo "userdel nagios..."
  userdel nagios
  echo "userdel dthurston..."
  userdel dthurston
  echo "assigning yum_extension variable"
  yum_extension="/el5"
fi
echo "made it through the majorver if statement"

cat > /etc/yum.repos.d/neustar_common.repo <<EOF
[neustar_common_el${majorver}]
name=Neustar common RPMs
baseurl=http://repo.prod.ultradns.net/common/el${majorver}
gpgcheck=0
enabled=1

[neustar_common_independent]
name=Neustar common RPMs
baseurl=http://repo.prod.ultradns.net/common/independent
gpgcheck=0
enabled=1
EOF

cat > /etc/yum.repos.d/epel.repo <<EOF
[epel]
name=epel mirror
baseurl=http://repo.prod.ultradns.net/epel/${majorver}/x86_64/
gpgcheck=0
enabled=1
EOF

cat > /etc/yum.repos.d/puppet.repo <<EOF
[puppet]
name=epel mirror
baseurl=http://repo.prod.ultradns.net/common/puppet${yum_extension}
gpgcheck=0
enabled=1
EOF

cat > /etc/yum.conf <<EOF
[main]
cachedir=/var/cache/yum/$basearch/$releasever
keepcache=0
debuglevel=2
logfile=/var/log/yum.log
exactarch=1
obsoletes=1
gpgcheck=1
plugins=1
installonly_limit=5
bugtracker_url=http://bugs.centos.org/set_project.php?project_id=16&ref=http://bugs.centos.org/bug_report_page.php?category=yum
distroverpkg=centos-release
EOF

yum clean all
yum -y install ruby-devel gcc make rubygems ruby-augeas
yum -y install puppet 

cat > /etc/puppet/puppet.conf <<EOF
[main]
 # The Puppet log directory.
 # The default value is '\$vardir/log'.
 logdir = /var/log/puppet
 # Where Puppet PID files are kept.
 # The default value is '\$vardir/run'.
 rundir = /var/run/puppet
 # Where SSL certificates are kept.
 # The default value is '\$confdir/ssl'.
 ssldir = \$vardir/ssl
[agent]
 # The file in which puppetd stores a list of the classes
 # associated with the retrieved configuratiion. Can be loaded in
 # the separate ``puppet`` executable using the ``--loadclasses``
 # option.
 # The default value is '\$confdir/classes.txt'.
 classfile = \$vardir/classes.txt
 # Where puppetd caches the local configuration. An
 # extension indicating the cache format is added automatically.
 # The default value is '\$confdir/localconfig'.
 localconfig = \$vardir/localconfig
 report = true
 listen = false
 server = $pm
 ca_server = puppetca.prod.ultradns.net
 plugindest = /var/lib/puppet/lib
 pluginsync = true
 environment = ultradns_production 
EOF

cat > /etc/puppet/namespaceauth.conf<<EOF
[fileserver]
 allow *.ultradns.net
[puppetmaster]
 allow *.ultradns.net
[puppetrunner]
 allow puppetmaster.prod.ultradns.net
[puppetbucket]
 allow *.ultradns.net
[puppetreports]
 allow *.ultradns.net
[resource]
 allow puppetmaster.prod.ultradns.net
EOF

chkconfig puppet on

read -r -p "Finished... reboot host? [Y/n] " response
case $response in 
     [yY][eE][sS]|[yY]) 
        echo "rebooting host now..."
        /sbin/shutdown -r now
        ;;
    *)
      echo "exiting script without rebooting..." 
      exit
        ;;
esac 
