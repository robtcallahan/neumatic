# define the target os so that I can build on my Mac and install on RedHat or CentOS
%define _target_os linux

# define the installation directory
%define toplevel_dir /var/www/html
%define install_target %{toplevel_dir}/%{name}

%define _binaries_in_noarch_packages_terminate_build   0

# doc_root will get a pointer to the install target
%define doc_root /var/www/html

# cron files get installed here
%define cron_dir /etc/cron.d

# http conf dir
%define http_conf_dir /etc/httpd/conf.d

# Vmwarephp cache dir
%define vmwarephp_cache_file %{toplevel_dir}/%{name}/vendor/Vmwarephp/.clone_ticket.cache

# _topdir, appName, version, and release aliases are provided by Phing

Summary:   %{summary}
Name:      %{name}
Version:   %{version}
Release:   %{release}
#BuildRoot: %{_topdir}/buildroot
%define buildroot %{_topdir}/buildroot
BuildArch: noarch
License:   Neustar/Restricted
Group:     neustar/sts

################################################################################
%description
%{summary}

################################################################################
%prep

################################################################################
%install
export RPM_BUILD_DIR=`pwd`
#export RPM_BUILD_ROOT=pkg/rpmbuild/buildroot

mkdir -p $RPM_BUILD_ROOT/%{install_target}

# copy files to build root
#cp -R $RPM_BUILD_DIR/* $RPM_BUILD_ROOT/%{install_target}
cp -R * $RPM_BUILD_ROOT/%{install_target}

# Tag the release and version info into the ABOUT file
echo 'NeuMatic Version %{version}-%{release}, Built %{release_name}' > $RPM_BUILD_ROOT/%{install_target}/public/ABOUT

# insure we have a Vmwarephp .cache file
touch $RPM_BUILD_ROOT/%{vmwarephp_cache_file}

# install the cron jobs
install -m 755 -d $RPM_BUILD_ROOT/%{cron_dir}
install -m 644 $RPM_BUILD_DIR/%{cron_dir}/neumatic_get_chef_status $RPM_BUILD_ROOT/%{cron_dir}/neumatic_get_chef_status
install -m 644 $RPM_BUILD_DIR/%{cron_dir}/neumatic_load_mongodb $RPM_BUILD_ROOT/%{cron_dir}/neumatic_load_mongodb
install -m 644 $RPM_BUILD_DIR/%{cron_dir}/neumatic_lease_reaper $RPM_BUILD_ROOT/%{cron_dir}/neumatic_lease_reaper
install -m 644 $RPM_BUILD_DIR/%{cron_dir}/neumatic_update_ldap_cache $RPM_BUILD_ROOT/%{cron_dir}/neumatic_update_ldap_cache
install -m 644 $RPM_BUILD_DIR/%{cron_dir}/neumatic_log_cleanup $RPM_BUILD_ROOT/%{cron_dir}/neumatic_log_cleanup

# create the log directory
install -m 777 -d $RPM_BUILD_ROOT/%{toplevel_dir}/%{name}/data
install -m 755 -d $RPM_BUILD_ROOT/%{toplevel_dir}/%{name}/data/cache
install -m 755 -d $RPM_BUILD_ROOT/%{toplevel_dir}/%{name}/log
install -m 777 -d $RPM_BUILD_ROOT/%{toplevel_dir}/%{name}/watcher_log

# create the neumatic web server log directory
install -m 755 -d $RPM_BUILD_ROOT/var/log/neumatic

# copy the pem files to /var/chef since they are not in git
install -m 755 -d $RPM_BUILD_ROOT/var/chef
cd $RPM_BUILD_DIR/var/chef
tar -cf - * | (cd $RPM_BUILD_ROOT/var/chef; tar -xf -)

# copy the chef server files over since they are not it git
cd $RPM_BUILD_DIR/../../../public
tar -cpf - clientconfig | (cd $RPM_BUILD_ROOT/%{install_target}/public; tar -xpf -)


################################################################################
%clean
rm -rf $RPM_BUILD_ROOT

################################################################################
%post

# create a sym link to /opt
if [ ! -h %{doc_root}/%{name} ]; then
    ln -s %{install_target} %{doc_root}
fi

# restart Apache since we have an updated vhost file
echo "Restarting Apache"
/etc/init.d/httpd restart

################################################################################
%files

%defattr(644,root,root,755)
%{install_target}
%{cron_dir}/*

%attr(644,apache,apache) %{vmwarephp_cache_file}

%attr(755,root,root) %{install_target}/bin/*
%attr(755,root,root) %{install_target}/module/Neumatic/bin/*.php
%attr(755,root,root) %{install_target}/vendor/websocketd/bin/*

%dir %attr(777,stsapps,stsapps) %{install_target}/data
%dir %attr(777,apache,apache) %{install_target}/data/cache
%dir %attr(755,stsapps,stsapps) %{install_target}/log
%dir %attr(777,apache,apache) %{install_target}/watcher_log

%dir %attr(755,apache,apache) /var/log/neumatic

#%dir %attr(755,apache,apache) /var/www/.ssh
#%attr(600,apache,apache) /var/www/.ssh/*.pem
#%attr(600,apache,apache) /var/www/.ssh/id_dsa
#%attr(644,apache,apache) /var/www/.ssh/id_dsa.pub
#%attr(644,apache,apache) /var/www/.ssh/known_hosts

%attr(755,stsapps,stsapps) /var/chef/*


