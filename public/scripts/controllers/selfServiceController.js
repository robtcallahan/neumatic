angular.module('NeuMatic')

    .controller('SelfServiceCtrl', function($scope, $q, $log, $http, $state, $timeout, AuthedUserService, NeuMaticService, BuildService, JiraService, AlertService) {

        $scope.nav = {neuServer: true};
        window.scope = $scope;
        var ldapUserGroups = $http.get('/ldap/getUserGroups/' + AuthedUserService.authedUser.username, {}).then(function(res) {
            if (!res.data.success) {
                ldapUserGroups = [];
            } else {
                ldapUserGroups = res.data.asObject;
            }
        });
        var ldapHostGroups = $http.get('/ldap/getHostGroups', {}).then(function(res) {
            if (!res.data.success) {
                ldapUserGroups = [];
            } else {
                ldapHostGroups = res.data.asObject;
            }
        });

        function stringFilter(needle, haystack) {
            var results = [];
            var re = new RegExp(needle, "i");
            haystack.forEach(function(straw) {
                if (straw.name.search(re) !== -1) {
                    results.push(straw);
                }
            });
            return results;
        }


        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        // NeuMaticService service
        NeuMaticService.setScope($scope);

        $scope.nav = {servers: true};

        $scope.modal = {
            title: '',
            message: ''
        };

        $scope.getLdapUserGroupsForTypeAhead = NeuMaticService.getLdapUserGroupsForTypeAhead;
        $scope.getLdapHostGroupsForTypeAhead = NeuMaticService.getLdapHostGroupsForTypeAhead;

        // layout classes
        $scope.rowClass = "col-lg-10";
        $scope.labelClass = "col-xs-3 col-xs-offset-2";
        $scope.inputClass = "col-xs-5";

        // disable next button for screens
        $scope.nextDisabled = {
            cmdb: true,
            name: true,
            groups: true,
            vmSize: true,
            chef: true,
            build: true
        };

        // list of busines services that are allowed to use full self-service
        var selfServiceBusinessServices = [];

        // field structures
        $scope.businessServices = [];
        $scope.subsystems = [];
        $scope.cmdbEnvironments = [];
        $scope.locations = ['Sterling', 'Charlotte', 'Lab'];
        $scope.computeClusters = [];
        $scope.dataStores = [];
        $scope.luns = [];
        $scope.vlans = [];
        $scope.cobblerServers = [];
        $scope.cobblerDistros = [];
        $scope.cobblerKickstarts = [
            '/var/lib/cobbler/kickstarts/baseline_6.ks',
            '/var/lib/cobbler/kickstarts/baseline.ks'
        ];
        $scope.chefEnvs = [];
        $scope.chefRoles = [];

        // we will store all of our form data in this object
        $scope.server = {
            name: '',
            serverType: 'vmware',
            businessService: {
                name: '',
                sysId: ''
            },
            subsystem: {
                name: '',
                sysId: ''
            },
            cmdbEnvironment: 'Lab',
            description: '',
            location: 'Lab',
            selfService: false,

            poolServer: true,
            serverPoolId: 0,
            standalone: {
                id: 0
            },

            dataCenter: {
                vSphereSite: 'lab',
                vSphereServer: 'stlabvcenter02.cis.neustar.com',
                name: 'LAB',
                uid: 'datacenter-401'
            },
            ccr: {
                name: 'LAB_Cluster',
                uid: 'domain-c406'
            },
            rpUid: 'resgroup-407',

            vlan: {
                name: 'VLAN32',
                id: 'dvportgroup-417',
                network: '172.30.32.0',
                subnetMask: '255.255.255.0',
                gateway: '172.30.32.1',
                macAddress: '',
                ipAddress: ''
            },

            vmSize: {
                name: 'Small',
                numCPUs: 1,
                memoryGB: 2,
                luns: [{
                    id: 0,
                    lunSizeGb: 50
                }],
                displayValue: 'Small: 1CPU, 2GB Mem, 50GB HD'
            },


            cobblerServer: 'stlabvmcblr01.va.neustar.com',
            cobblerDistro: {
                name: "CentOS-6.3-x86_64",
                warn: false
            },
            cobblerKickstart: '/var/lib/cobbler/kickstarts/baseline_6.ks',
            cobblerMetadata: '',

            chefServer: 'stopcdvvcm01.va.neustar.com',
            chefRole: 'neu_collection',
            chefEnv: 'ST_CORE_LAB'
        };

        $scope.name = {
            site: '',
            service: '',
            virtual: '',
            env: '',
            func: ''
        };

        $scope.$watch('server.name', function(newValue, oldValue) {
            if (newValue === oldValue) {
                return;
            }
            var nextChar = 5;

            // Site
            var siteAbbrev = newValue.substr(0, 2);
            if (siteAbbrev === 'ch') {
                $scope.name.site = "Charlotte";
            } else if (siteAbbrev === 'st') {
                $scope.name.site = "Sterling";
            } else if (siteAbbrev === 'de') {
                $scope.name.site = "Denver";
            } else {
                $scope.name.site = '';
            }

            // Service
            var serviceAbbrev = newValue.substr(2, 2);
            if (serviceAbbrev === 'at') {
                $scope.name.service = "Automation-Tools";
            } else if (serviceAbbrev === 'om') {
                $scope.name.service = "OMS";
            } else if (serviceAbbrev === 'np') {
                $scope.name.service = "NPAC";
            } else {
                $scope.name.service = serviceAbbrev;
            }

            // Virtual
            if (newValue.substr(4, 1) === 'v') {
                $scope.name.virtual = 'True';
            } else {
                $scope.name.virtual = 'False';
                nextChar = 4;
            }

            // Environment
            var envAbbrev = newValue.substr(nextChar, 2);
            if (envAbbrev === 'dv') {
                $scope.name.env = "Development";
            } else if (envAbbrev === 'qa') {
                $scope.name.env = "QA";
            } else if (envAbbrev === 'pr') {
                $scope.name.env = "Production";
            } else {
                $scope.name.env = envAbbrev;
            }

            // Function
            $scope.name.func = newValue.substr(nextChar + 2, newValue.indexOf('.') - nextChar - 2);

            $scope.checkFields('name');
        });

        $scope.checkFields = function(screen) {
            console.log("checkFields()");
            var server = $scope.server,
                name = $scope.name;
            if (screen === 'cmdb') {
                if (typeof server.businessService !== 'undefined' && server.businessService.sysId &&
                    typeof server.subsystem !== 'undefined' && server.subsystem.sysId &&
                    typeof server.cmdbEnvironment !== 'undefined' && server.cmdbEnvironment) {
                    $scope.nextDisabled.cmdb = false;
                } else {
                    $scope.nextDisabled.cmdb = true;
                }
            } else if (screen === 'groups') {
                if (typeof server.ldapUserGroups !== 'undefined' && typeof server.ldapUserGroups === 'object' && server.ldapUserGroups.length !== 0
                    && typeof server.ldapHostGroups !== 'undefined' && typeof server.ldapHostGroups === 'object' && server.ldapHostGroups.length !== 0) {
                    $scope.nextDisabled.groups = false;
                } else {
                    $scope.nextDisabled.groups = true;
                }
                console.log("nextDisabled.groups=" + $scope.nextDisabled.groups);
            } else if (screen === 'name') {
                if (typeof server.name != 'undefined' &&
                    name.site != '' &&
                    name.service != '' &&
                    name.virtual != '' &&
                    name.env != '' &&
                    name.func != '' &&
                    name.func.indexOf('.') !== -1) {
                    $scope.nextDisabled.name = false;
                } else {
                    $scope.nextDisabled.name = true;
                }
            } else if (screen === 'vmSize') {
                if (typeof server.vmSize !== 'undefined' && server.vmSize.name &&
                    server.vmSize.numCPUs && server.vmSize.memoryGB && server.vmSize.luns.length > 0) {
                    $scope.nextDisabled.vmSize = false;
                } else {
                    $scope.nextDisabled.vmSize = true;
                }
            } else if (screen === 'chef') {
                if (typeof server.cobblerDistro !== 'undefined' && server.cobblerDistro.name &&
                    typeof server.chefRole !== 'undefined' && server.chefRole &&
                    typeof server.chefEnv !== 'undefined' && server.chefEnv) {
                    $scope.nextDisabled.chef = false;
                } else {
                    $scope.nextDisabled.chef = true;
                }
            }
            if (!$scope.nextDisabled.cmdb && !$scope.nextDisabled.vmSize && !$scope.nextDisabled.chef && !$scope.nextDisabled.groups) {
                $scope.nextDisabled.build = false;
            } else {
                $scope.nextDisabled.build = true;
            }

        };

        $scope.loadLdapUserGroups = function(query) {
            var deferred = $q.defer();
            deferred.resolve(stringFilter(query, ldapUserGroups));
            return deferred.promise;
        };

        $scope.loadLdapHostGroups = function(query) {
            var deferred = $q.defer();
            deferred.resolve(stringFilter(query, ldapHostGroups));
            return deferred.promise;
        };

        /**
         * Used in conjunction with the bootstrap typeahead function
         *
         * @param val the substring value typed into the field
         * @returns {*}
         */
        $scope.getBusServicesForTypeAhead = function(val) {
            return $http.get('/cmdb/getBusinessServicesBySubstring/' + val, {}).then(
                function(res) {
                    return res.data.businessServices;
                });
        };

        /**
         * Get a list of subsystems for a business service given its sysId
         * Called when a business service is selected
         */
        $scope.getSubsystems = function() {
            $scope.subsystems = [{
                name: 'Loading...',
                sysId: ''
            }];
            $scope.server.subsystem = $scope.subsystems[0];
            NeuMaticService.getSubsystemsByBSId($scope.server.businessService.sysId,
                function(subsystems) {
                    $scope.server.subsystem = {
                        name: '',
                        sysId: ''
                    };
                    $scope.subsystems = subsystems;
                });
        };

        /**
         * Get the list of CMDB environments so the user can choose one
         *
         */
        function getCmdbEnvironments() {
            $scope.cmdbEnvironments = ["Loading..."];
            $scope.server.cmdbEnvironment = $scope.cmdbEnvironments[0];
            NeuMaticService.getCmdbEnvironments(
                function(environments) {
                    $scope.server.cmdbEnvironment = 'Lab';
                    $scope.cmdbEnvironments = environments;
                }
            );
        }


        /**
         * Get a list of VM sizes (small, medium, large) and their configs (cpu, mem, disk, etc)
         * Called when the page loads
         * VMWare VMs only
         *
         */
        function getVMSizes() {
            NeuMaticService.getVMSizes(
                function(vmSizes) {
                    $scope.vmSizes = vmSizes;
                    $scope.serverSizeClick($scope.server.vmSize.name);
                }
            );
        }

        /**
         * Called when a user clicks on a VM size
         * Highlights the size button and calls vmSizeSelected to assign the mem, cpu and disk sizes
         * @param vmSize
         */
        $scope.serverSizeClick = function(vmSize) {
            var sizes = ['small', 'medium', 'large', 'custom'];
            for (var s in sizes) {
                if (sizes.hasOwnProperty(s)) {
                    angular.element("#button-" + sizes[s]).removeClass("server-btn-pressed");
                }
            }
            angular.element("#button-" + vmSize.toLowerCase()).addClass("server-btn-pressed");

            $scope.server.vmSize.name = vmSize;
            if (vmSize !== 'Custom') {
                // this will assign the proper cpu, mem and disk for the vmSize (small, medium, large)
                vmSizeSelected();
            }

            // we're automatically selecting 'Small' so we need to enable the "Next" button
            $scope.checkFields('vmSize');
        };


        /**
         * Triggered when a VM size is selected
         * Make assignments for CPU, memory and LUNs from the size spec
         * VMWare VMs only
         *
         */
        function vmSizeSelected() {
            for (var i = 0; i < $scope.vmSizes.length; i++) {
                if ($scope.vmSizes[i].name === $scope.server.vmSize.name) {
                    $scope.server.vmSize = {
                        name: $scope.vmSizes[i].name,
                        numCPUs: $scope.vmSizes[i].numCPUs,
                        memoryGB: $scope.vmSizes[i].memoryGB,
                        luns: []
                    };
                    for (var j = 0; j < $scope.vmSizes[i].luns.length; j++) {
                        $scope.server.vmSize.luns[j] = {
                            id: 0,
                            lunSizeGb: $scope.vmSizes[i].luns[j].lunSizeGb
                        }
                    }
                }
            }
        }

        /**
         * Get a list of cobbler distributions (OSs)
         * Called on page load by getCobblerServers() and when a cobbler server is selected
         */
        function getCobblerDistros() {
            NeuMaticService.getCobblerDistros($scope.server.cobblerServer,
                function(distros) {
                    var d;
                    $scope.cobblerDistros = distros;
                    for (var i = 0; i < distros.length; i++) {
                        d = distros[i];
                        if (d.name === $scope.server.cobblerDistro.name) {
                            $scope.server.cobblerDistro = d;
                        }
                    }
                    $scope.checkFields('chef');
                }
            );
        }

        /**
         * Triggered by selecting a cobbler distribution
         * Automatically sets the kickstart file based on the distribution (OS)
         */
        $scope.cobblerDistroSelected = function() {
            if ($scope.server.cobblerDistro.name.search(/6\.[0-9]/) !== -1) {
                $scope.server.cobblerKickstart = '/var/lib/cobbler/kickstarts/baseline_6.ks';
            } else {
                $scope.server.cobblerKickstart = '/var/lib/cobbler/kickstarts/baseline.ks';
            }
        };

        /**
         * Get a list of chef roles
         * Called on page load by getChefServers() and when a chef server is selected
         */
        function getChefRoles() {
            NeuMaticService.getChefRoles($scope.server.chefServer,
                function(roles) {
                    $scope.chefRoles = roles;
                    $scope.checkFields('chef');
                }
            );
        }

        /**
         * Get a list of chef environments
         * Called on page load by getChefServers() and when a chef server is selected
         */
        function getChefEnvironments() {
            NeuMaticService.getChefEnvironments($scope.server.chefServer,
                function(environments) {
                    $scope.chefEnvs = environments;
                    $scope.checkFields('chef');
                }
            );
        }

        /**
         * Get a list of chef environments
         * Called on page load by getChefServers() and when a chef server is selected
         */
        function getSelfServiceBusinessServices() {
            NeuMaticService.getSelfServiceBusinessServices(
                function(businessServices) {
                    selfServiceBusinessServices = businessServices;
                }
            );
        }


        // ----------------------------------------------------------------------------------------------------
        // Save and Build Server
        // ----------------------------------------------------------------------------------------------------

        // define our build status values so that we can display the status during the build process
        $scope.buildStatus = {};
        $scope.buildStates = ['getPoolServer', 'saving', 'lookupCmdbCi', 'gettingMac', 'makingCoffee', 'checkExisting', 'chefDelete',
            'deleteVM', 'createVM', 'createCmdbCi', 'ldapUpdate', 'dnsUpdate',
            'cobblerUpdate', 'powerOffVm', 'setNetbootVM', 'chefClientDelete', 'powerOnVm',
            'resetSystem', 'startWatcher'];
        for (var i = 0; i < $scope.buildStates.length; i++) {
            $scope.buildStatus[$scope.buildStates[i]] = {
                text: '',
                running: false,
                complete: false,
                error: false
            };
        }

        function build() {
            console.log("build()");

            if ($scope.server.serverType === 'standalone') {
                buildStandalone($scope.server);
            } else if ($scope.server.serverType === 'remote') {
                buildRemoteStandalone($scope.server);
            } else if ($scope.server.serverType === 'blade') {
                buildBlade($scope.server);
            } else if ($scope.server.serverType === 'vmware') {
                if (!$scope.server.serverPoolId && $scope.server.poolServer) {
                    buildVMwareVMFromPool($scope.server);
                } else {
                    buildVMwareVMFromTemplate($scope.server);
                }

            } else if ($scope.server.serverType === 'vmwareCobbler') {
            	buildVMwareVM($scope.server);
            }
        }

        /*********************************************************************************
         * Build Standalone
         *********************************************************************************/
        function buildStandalone(server) {
            BuildService.getServer(server)
                .then(function(server) {
                    /** @namespace $scope.buildStatus.lookupCmdbCi */
                    $scope.buildStatus.lookupCmdbCi.text = 'Looking for host in CMDB...';
                    $scope.buildStatus.lookupCmdbCi.running = true;
                    return BuildService.lookupCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.lookupCmdbCi.running = false;
                    $scope.buildStatus.lookupCmdbCi.complete = true;

                    return BuildService.resetStartTime(server)
                })
                .then(function(server) {
                    /** @namespace $scope.buildStatus.chefDelete */
                    $scope.buildStatus.chefDelete.text = 'Deleting from Chef...';
                    $scope.buildStatus.chefDelete.running = true;
                    return BuildService.deleteChef(server, server.name, server.chefServer)
                })
                .then(function(server) {
                    $scope.buildStatus.chefDelete.running = false;
                    $scope.buildStatus.chefDelete.complete = true;

                    /** @namespace $scope.buildStatus.gettingMac */
                    $scope.buildStatus.gettingMac.text = 'Getting MAC address...';
                    $scope.buildStatus.gettingMac.running = true;
                    if (server.vlan.macAddress === "") {
                        return BuildService.getMacAddress(server);
                    } else {
                        return server;
                    }
                })
                .then(function(server) {
                    $scope.buildStatus.gettingMac.running = false;
                    $scope.buildStatus.gettingMac.complete = true;

                    /** @namespace $scope.buildStatus.cobblerUpdate */
                    $scope.buildStatus.cobblerUpdate.text = 'Creating Cobbler profile...';
                    $scope.buildStatus.cobblerUpdate.running = true;
                    return BuildService.createCobblerProfile(server);
                })
                .then(function(server) {
                    $scope.buildStatus.cobblerUpdate.running = false;
                    $scope.buildStatus.cobblerUpdate.complete = true;

                    /** @namespace $scope.buildStatus.createCmdbCi */
                    $scope.buildStatus.createCmdbCi.text = 'Creating CMDB CI...';
                    $scope.buildStatus.createCmdbCi.running = true;
                    return BuildService.createCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createCmdbCi.running = false;
                    $scope.buildStatus.createCmdbCi.complete = true;

                    /** @namespace $scope.buildStatus.makingCoffee */
                    $scope.buildStatus.makingCoffee.text = 'Making coffee...';
                    $scope.buildStatus.makingCoffee.running = true;
                    return BuildService.makeCoffee(server);
                })
                .then(function(server) {
                    $scope.buildStatus.makingCoffee.running = false;
                    $scope.buildStatus.makingCoffee.complete = true;

                    /** @namespace $scope.buildStatus.ldapUpdate */
                    $scope.buildStatus.ldapUpdate.text = 'Adding host to LDAP...';
                    $scope.buildStatus.ldapUpdate.running = true;
                    return BuildService.updateLdap(server);
                })
                .then(function(server) {
                    $scope.buildStatus.ldapUpdate.running = false;
                    $scope.buildStatus.ldapUpdate.complete = true;

                    /** @namespace $scope.buildStatus.dnsUpdate */
                    $scope.buildStatus.dnsUpdate.text = 'Adding host to DNS...';
                    $scope.buildStatus.dnsUpdate.running = true;
                    return BuildService.updateDNS(server);
                })
                .then(function(server) {
                    $scope.buildStatus.dnsUpdate.running = false;
                    $scope.buildStatus.dnsUpdate.complete = true;

                    /** @namespace $scope.buildStatus.resetSystem */
                    $scope.buildStatus.resetSystem.text = 'Resetting system...';
                    $scope.buildStatus.resetSystem.running = true;
                    return BuildService.resetSystem(server);
                })
                .then(function(server) {
                    $scope.buildStatus.resetSystem.running = false;
                    $scope.buildStatus.resetSystem.complete = true;

                    /** @namespace $scope.buildStatus.startWatcher */
                    $scope.buildStatus.startWatcher.text = 'Starting Cobbler watcher...';
                    $scope.buildStatus.startWatcher.running = true;
                    return BuildService.startCobblerWatcher(server);
                })
                .then(function(server) {
                    BuildService.updateStatus(server.id, 'Building', 'Kickstarting...');
                    $scope.buildStatus.startWatcher.running = false;
                    $scope.buildStatus.startWatcher.complete = true;
                    hidePageMask();

                    setTimeout(function() {
                        window.location.href = "/#/servers/building/0";
                    }, 2500);
                });
        }


        /*********************************************************************************
         * Build Remote Standalone
         *********************************************************************************/
        function buildRemoteStandalone(server) {
            BuildService.getServer(server)
                .then(function(server) {
                    /** @namespace $scope.buildStatus.lookupCmdbCi */
                    $scope.buildStatus.lookupCmdbCi.text = 'Looking for host in CMDB...';
                    $scope.buildStatus.lookupCmdbCi.running = true;
                    return BuildService.lookupCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.lookupCmdbCi.running = false;
                    $scope.buildStatus.lookupCmdbCi.complete = true;

                    return BuildService.resetStartTime(server)
                })
                .then(function(server) {
                    /** @namespace $scope.buildStatus.chefDelete */
                    $scope.buildStatus.chefDelete.text = 'Deleting from Chef...';
                    $scope.buildStatus.chefDelete.running = true;

                    return BuildService.deleteChef(server, server.name, server.chefServer)
                        .then(function(server) {
                            var found = server.chefServer.match(/([\w\d-]+\.)(chef\.ops\.neustar\.biz.*)/);
                            if (found !== null && typeof found[2] !== 'undefined') {
                                var chefServer = found[2];
                                return BuildService.deleteChef(server, server.name, chefServer);
                            } else {
                                return server;
                            }
                        });
                })
                .then(function(server) {
                    $scope.buildStatus.chefDelete.running = false;
                    $scope.buildStatus.chefDelete.complete = true;

                    /** @namespace $scope.buildStatus.gettingMac */
                    $scope.buildStatus.gettingMac.text = 'Getting MAC address...';
                    $scope.buildStatus.gettingMac.running = true;
                    if (server.vlan.macAddress === "") {
                        return BuildService.getMacAddress(server);
                    } else {
                        return server;
                    }
                })
                .then(function(server) {
                    $scope.buildStatus.gettingMac.running = false;
                    $scope.buildStatus.gettingMac.complete = true;

                    /** @namespace $scope.buildStatus.createCmdbCi */
                    $scope.buildStatus.createCmdbCi.text = 'Creating CMDB CI...';
                    $scope.buildStatus.createCmdbCi.running = true;
                    return BuildService.createCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createCmdbCi.running = false;
                    $scope.buildStatus.createCmdbCi.complete = true;

                    /** @namespace $scope.buildStatus.makingCoffee */
                    $scope.buildStatus.makingCoffee.text = 'Making coffee...';
                    $scope.buildStatus.makingCoffee.running = true;
                    return BuildService.makeCoffee(server);
                })
                .then(function(server) {
                    $scope.buildStatus.makingCoffee.running = false;
                    $scope.buildStatus.makingCoffee.complete = true;

                    /** @namespace $scope.buildStatus.resetSystem */
                    $scope.buildStatus.resetSystem.text = 'Resetting system...';
                    $scope.buildStatus.resetSystem.running = true;
                    return BuildService.resetSystem(server);
                })
                .then(function(server) {
                    $scope.buildStatus.resetSystem.running = false;
                    $scope.buildStatus.resetSystem.complete = true;
                    BuildService.updateStatus(server.id, 'Building', 'Booting ISO image...');
                    hidePageMask();
                    setTimeout(function() {
                        window.location.href = "/#/servers/building/0";
                    }, 2500);
                });
        }


        /*********************************************************************************
         * Build Blade
         *********************************************************************************/
        function buildBlade(server) {
            BuildService.getServer(server)
                .then(function(server) {
                    /** @namespace $scope.buildStatus.lookupCmdbCi */
                    $scope.buildStatus.lookupCmdbCi.text = 'Looking for host in CMDB...';
                    $scope.buildStatus.lookupCmdbCi.running = true;
                    return BuildService.lookupCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.lookupCmdbCi.running = false;
                    $scope.buildStatus.lookupCmdbCi.complete = true;

                    return BuildService.resetStartTime(server)
                })
                .then(function(server) {
                    /** @namespace $scope.buildStatus.chefDelete */
                    $scope.buildStatus.chefDelete.text = 'Deleting from Chef...';
                    $scope.buildStatus.chefDelete.running = true;

                    return BuildService.deleteChef(server, server.name, server.chefServer)
                        .then(function(server) {
                            return BuildService.deleteChef(server, server.name, server.chefServer)
                        });
                })
                .then(function(server) {
                    $scope.buildStatus.chefDelete.running = false;
                    $scope.buildStatus.chefDelete.complete = true;

                    /** @namespace $scope.buildStatus.gettingMac */
                    $scope.buildStatus.gettingMac.text = 'Getting MAC address...';
                    $scope.buildStatus.gettingMac.running = true;
                    if (server.vlan.macAddress === "") {
                        return BuildService.getBladeMacAddress(server);
                    } else {
                        return server;
                    }
                })
                .then(function(server) {
                    $scope.buildStatus.gettingMac.running = false;
                    $scope.buildStatus.gettingMac.complete = true;

                    /** @namespace $scope.buildStatus.cobblerUpdate */
                    $scope.buildStatus.cobblerUpdate.text = 'Creating Cobbler profile...';
                    $scope.buildStatus.cobblerUpdate.running = true;
                    return BuildService.createCobblerProfile(server);
                })
                .then(function(server) {
                    $scope.buildStatus.cobblerUpdate.running = false;
                    $scope.buildStatus.cobblerUpdate.complete = true;

                    /** @namespace $scope.buildStatus.createCmdbCi */
                    $scope.buildStatus.createCmdbCi.text = 'Creating CMDB CI...';
                    $scope.buildStatus.createCmdbCi.running = true;
                    return BuildService.createCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createCmdbCi.running = false;
                    $scope.buildStatus.createCmdbCi.complete = true;

                    /** @namespace $scope.buildStatus.makingCoffee */
                    $scope.buildStatus.makingCoffee.text = 'Making coffee...';
                    $scope.buildStatus.makingCoffee.running = true;
                    return BuildService.makeCoffee(server);
                })
                .then(function(server) {
                    $scope.buildStatus.makingCoffee.running = false;
                    $scope.buildStatus.makingCoffee.complete = true;

                    /** @namespace $scope.buildStatus.ldapUpdate */
                    $scope.buildStatus.ldapUpdate.text = 'Adding host to LDAP...';
                    $scope.buildStatus.ldapUpdate.running = true;
                    return BuildService.updateLdap(server);
                })
                .then(function(server) {
                    $scope.buildStatus.ldapUpdate.running = false;
                    $scope.buildStatus.ldapUpdate.complete = true;

                    /** @namespace $scope.buildStatus.dnsUpdate */
                    $scope.buildStatus.dnsUpdate.text = 'Adding host to DNS...';
                    $scope.buildStatus.dnsUpdate.running = true;
                    return BuildService.updateDNS(server);
                })
                .then(function(server) {
                    $scope.buildStatus.dnsUpdate.running = false;
                    $scope.buildStatus.dnsUpdate.complete = true;

                    /** @namespace $scope.buildStatus.resetSystem */
                    $scope.buildStatus.resetSystem.text = 'Resetting system...';
                    $scope.buildStatus.resetSystem.running = true;
                    return BuildService.restartBlade(server);
                })
                .then(function(server) {
                    $scope.buildStatus.resetSystem.running = false;
                    $scope.buildStatus.resetSystem.complete = true;

                    /** @namespace $scope.buildStatus.startWatcher */
                    $scope.buildStatus.startWatcher.text = 'Starting Cobbler watcher...';
                    $scope.buildStatus.startWatcher.running = true;
                    return BuildService.startCobblerWatcher(server);
                })
                .then(function(server) {
                    BuildService.updateStatus(server.id, 'Building', 'Kickstarting...');
                    $scope.buildStatus.startWatcher.running = false;
                    $scope.buildStatus.startWatcher.complete = true;

                    hidePageMask();
                    setTimeout(function() {
                        window.location.href = "/#/servers/building/0";
                    }, 2500);
                });
        }

        /*********************************************************************************
         * Build VMWare VM
         *********************************************************************************/
        function buildVMwareVM(server) {
            BuildService.getServer(server)
                .then(BuildService.resetStartTime(server))
                .then(function(server) {
                    /** @namespace $scope.buildStatus.checkExisting */
                    $scope.buildStatus.checkExisting.text = 'Checking for existing VM...';
                    $scope.buildStatus.checkExisting.running = true;
                    return BuildService.checkIfVMwareVMExists(server)
                })
                .then(function(server) {
                    $scope.buildStatus.checkExisting.running = false;
                    $scope.buildStatus.checkExisting.complete = true;

                    if (server.vmExists) {
                        var deferred = $q.defer();
                        hidePageMask();
                        $scope.modal = {
                            title: 'VM Exists',
                            message: 'The VM, ' + server.name + ', already exists.<br><br>' +
                            'Are you sure you want to rebuild it?',
                            yesCallback: function() {
                                showPageMask();
                                /** @namespace $scope.buildStatus.deleteVM */
                                $scope.buildStatus.deleteVM.text = 'Deleting Existing VMware VM...';
                                $scope.buildStatus.deleteVM.running = true;
                                BuildService.deleteVMwareVM(server)
                                    .then(function() {
                                        deferred.resolve(server);
                                    });
                            },
                            noCallback: function() {
                                hidePageMask();
                                // ok, we're not deleting, so we're not continuing on.
                                BuildService.updateStatus(server.id, 'New', ' ');
                                deferred.reject();
                                window.location.href = '#/server/' + $scope.server.id;
                            }
                        };
                        //noinspection JSUnresolvedFunction
                        $('#promptModal').modal('show');
                        return deferred.promise;
                    } else {
                        return server;
                    }
                })
                .then(function(server) {
                    if (server.vmExists) {
                        $scope.buildStatus.deleteVM.running = false;
                        $scope.buildStatus.deleteVM.complete = true;
                    }

                    /** @namespace $scope.buildStatus.chefDelete */
                    $scope.buildStatus.chefDelete.text = 'Deleting from Chef...';
                    $scope.buildStatus.chefDelete.running = true;
                    return BuildService.deleteChef(server, server.name, server.chefServer);
                })
                .then(function(server) {
                    $scope.buildStatus.chefDelete.running = false;
                    $scope.buildStatus.chefDelete.complete = true;

                    /** @namespace $scope.buildStatus.createVM */
                    $scope.buildStatus.createVM.text = 'Creating New VMware VM...';
                    $scope.buildStatus.createVM.running = true;
                    return BuildService.createVMWareCobblerVM(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createVM.running = false;
                    $scope.buildStatus.createVM.complete = true;

                    /** @namespace $scope.buildStatus.cobblerUpdate */
                    $scope.buildStatus.cobblerUpdate.text = 'Creating Cobbler profile...';
                    $scope.buildStatus.cobblerUpdate.running = true;
                    return BuildService.createCobblerProfile(server);
                })
                .then(function(server) {
                    $scope.buildStatus.cobblerUpdate.running = false;
                    $scope.buildStatus.cobblerUpdate.complete = true;

                    /** @namespace $scope.buildStatus.createCmdbCi */
                    $scope.buildStatus.createCmdbCi.text = 'Creating CMDB CI...';
                    $scope.buildStatus.createCmdbCi.running = true;
                    return BuildService.createCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createCmdbCi.running = false;
                    $scope.buildStatus.createCmdbCi.complete = true;

                    /** @namespace $scope.buildStatus.makingCoffee */
                    $scope.buildStatus.makingCoffee.text = 'Making coffee...';
                    $scope.buildStatus.makingCoffee.running = true;
                    return BuildService.makeCoffee(server);
                })
                .then(function(server) {
                    $scope.buildStatus.makingCoffee.running = false;
                    $scope.buildStatus.makingCoffee.complete = true;

                    /** @namespace $scope.buildStatus.ldapUpdate */
                    $scope.buildStatus.ldapUpdate.text = 'Adding host to LDAP...';
                    $scope.buildStatus.ldapUpdate.running = true;
                    return BuildService.updateLdap(server);
                })
                .then(function(server) {
                    $scope.buildStatus.ldapUpdate.running = false;
                    $scope.buildStatus.ldapUpdate.complete = true;

                    /** @namespace $scope.buildStatus.dnsUpdate */
                    $scope.buildStatus.dnsUpdate.text = 'Adding host to DNS...';
                    $scope.buildStatus.dnsUpdate.running = true;
                    return BuildService.updateDNS(server);
                })
                .then(function(server) {
                    $scope.buildStatus.dnsUpdate.running = false;
                    $scope.buildStatus.dnsUpdate.complete = true;

                    /** @namespace $scope.buildStatus.powerOnVm */
                    $scope.buildStatus.powerOnVm.text = 'Powering on VM...';
                    $scope.buildStatus.powerOnVm.running = true;
                    return BuildService.powerOnVM(server);
                })
                .then(function(server) {
                    $scope.buildStatus.powerOnVm.running = false;
                    $scope.buildStatus.powerOnVm.complete = true;

                    /** @namespace $scope.buildStatus.startWatcher */
                    $scope.buildStatus.startWatcher.text = 'Starting Cobbler watcher...';
                    $scope.buildStatus.startWatcher.running = true;
                    return BuildService.startCobblerWatcher(server);
                })
                .then(function(server) {
                    BuildService.updateStatus(server.id, 'Building', 'Kickstarting...');
                    $scope.buildStatus.startWatcher.running = false;
                    $scope.buildStatus.startWatcher.complete = true;
                    hidePageMask();

                    setTimeout(function() {
                        window.location.href = "/#/servers/building/0";
                    }, 2500);
                });
        }

        /*********************************************************************************
         * Build VMWare VM From Template
         *********************************************************************************/
        function buildVMwareVMFromTemplate(server) {
            BuildService.getServer(server)
                .then(BuildService.resetStartTime(server))
                .then(function(server) {
                    /** @namespace $scope.buildStatus.checkExisting */
                    $scope.buildStatus.checkExisting.text = 'Checking for existing VM...';
                    $scope.buildStatus.checkExisting.running = true;
                    return BuildService.checkIfVMwareVMExists(server);
                })
                .then(function(server) {
                    $scope.buildStatus.checkExisting.running = false;
                    $scope.buildStatus.checkExisting.complete = true;

                    if (server.vmExists) {
                        var deferred = $q.defer();
                        hidePageMask();
                        $scope.modal = {
                            title: 'VM Exists',
                            message: 'The VM, ' + server.name + ', already exists.<br><br>' +
                            'Are you sure you want to rebuild it?',
                            yesCallback: function() {
                                showPageMask();
                                /** @namespace $scope.buildStatus.deleteVM */
                                $scope.buildStatus.deleteVM.text = 'Deleting Existing VMware VM...';
                                $scope.buildStatus.deleteVM.running = true;
                                BuildService.deleteVMwareVM(server)
                                    .then(function() {
                                        deferred.resolve(server);
                                    });
                            },
                            noCallback: function() {
                                hidePageMask();
                                // ok, we're not deleting, so we're not continuing on.
                                BuildService.updateStatus(server.id, 'New', ' ');
                                deferred.reject();
                                window.location.href = '#/server/' + $scope.server.id;
                            }
                        };
                        //noinspection JSUnresolvedFunction
                        $('#promptModal').modal('show');
                        return deferred.promise;
                    } else {
                        return server;
                    }
                })

                .then(function(server) {
                    if (server.vmExists) {
                        $scope.buildStatus.deleteVM.running = false;
                        $scope.buildStatus.deleteVM.complete = true;
                    }

                    /** @namespace $scope.buildStatus.chefDelete */
                    $scope.buildStatus.chefDelete.text = 'Deleting from Chef...';
                    $scope.buildStatus.chefDelete.running = true;
                    return BuildService.deleteChef(server, server.name, server.chefServer);
                })
                .then(function(server) {
                    $scope.buildStatus.chefDelete.running = false;
                    $scope.buildStatus.chefDelete.complete = true;

                    /** @namespace $scope.buildStatus.createVM */
                    $scope.buildStatus.createVM.text = 'Creating New VMware VM...';
                    $scope.buildStatus.createVM.running = true;
                    return BuildService.createVMWareVM(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createVM.running = false;
                    $scope.buildStatus.createVM.complete = true;

                    /** @namespace $scope.buildStatus.createCmdbCi */
                    $scope.buildStatus.createCmdbCi.text = 'Creating CMDB CI...';
                    $scope.buildStatus.createCmdbCi.running = true;
                    return BuildService.createCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createCmdbCi.running = false;
                    $scope.buildStatus.createCmdbCi.complete = true;

                    /** @namespace $scope.buildStatus.makingCoffee */
                    $scope.buildStatus.makingCoffee.text = 'Making coffee...';
                    $scope.buildStatus.makingCoffee.running = true;
                    return BuildService.makeCoffee(server);
                })
                .then(function(server) {
                    $scope.buildStatus.makingCoffee.running = false;
                    $scope.buildStatus.makingCoffee.complete = true;

                    /** @namespace $scope.buildStatus.ldapUpdate */
                    $scope.buildStatus.ldapUpdate.text = 'Adding host to LDAP...';
                    $scope.buildStatus.ldapUpdate.running = true;
                    return BuildService.updateLdap(server);
                })
                .then(function(server) {
                    $scope.buildStatus.ldapUpdate.running = false;
                    $scope.buildStatus.ldapUpdate.complete = true;

                    /** @namespace $scope.buildStatus.dnsUpdate */
                    $scope.buildStatus.dnsUpdate.text = 'Adding host to DNS...';
                    $scope.buildStatus.dnsUpdate.running = true;
                    return BuildService.updateDNS(server);
                })
                .then(function(server) {
                    $scope.buildStatus.dnsUpdate.running = false;
                    $scope.buildStatus.dnsUpdate.complete = true;

                    /** @namespace $scope.buildStatus.startWatcher */
                    $scope.buildStatus.startWatcher.text = 'Starting VMWare Template watcher...';
                    $scope.buildStatus.startWatcher.running = true;
                    return BuildService.startTemplateWatcher(server);
                })
                .then(function(server) {
                    BuildService.updateStatus(server.id, 'Building', 'Booting VM...');
                    $scope.buildStatus.startWatcher.running = false;
                    $scope.buildStatus.startWatcher.complete = true;
                    hidePageMask();

                    setTimeout(function() {
                        window.location.href = "/#/servers/building/0";
                    }, 2500);
                });
        }

        /*********************************************************************************
         * Build VMware VM from Server Pool
         *********************************************************************************/
        function buildVMwareVMFromPool(server) {
            /** @namespace $scope.buildStatus.getPoolServer */
            $scope.buildStatus.getPoolServer.text = "Getting server from pool...";
            $scope.buildStatus.getPoolServer.running = true;

            BuildService.getNextPoolServer(server)
                .then(function(server) {
                    // this is a pool server with all fields populated so we're ok to build
                    $scope.server.okToBuild = true;
                    $scope.buildStatus.getPoolServer.running = false;
                    $scope.buildStatus.getPoolServer.complete = true;

                    /** @namespace $scope.buildStatus.saving */
                    $scope.buildStatus.saving.text = 'Saving config...';
                    $scope.buildStatus.saving.running = true;
                    return BuildService.saveServer(server)
                })
                .then(function(server) {
                    $scope.buildStatus.saving.running = false;
                    $scope.buildStatus.saving.complete = true;

                    /** @namespace $scope.buildStatus.deleteVM */
                    $scope.buildStatus.deleteVM.text = 'Deleting Existing VMware VM...';
                    $scope.buildStatus.deleteVM.running = true;
                    return BuildService.deleteVMwareVM(server);
                })
                .then(function(server) {
                    $scope.buildStatus.deleteVM.running = false;
                    $scope.buildStatus.deleteVM.complete = true;

                    /** @namespace $scope.buildStatus.chefDelete */
                    $scope.buildStatus.chefDelete.text = 'Deleting from Chef...';
                    $scope.buildStatus.chefDelete.running = true;
                    return BuildService.deleteChef(server, server.name, server.chefServer);
                })
                .then(function(server) {
                    $scope.buildStatus.chefDelete.running = false;
                    $scope.buildStatus.chefDelete.complete = true;
                    return BuildService.resetStartTime(server);
                })
                .then(function(server) {
                    /** @namespace $scope.buildStatus.createVM */
                    $scope.buildStatus.createVM.text = 'Creating New VMware VM...';
                    $scope.buildStatus.createVM.running = true;
                    return BuildService.createVMWareVM(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createVM.running = false;
                    $scope.buildStatus.createVM.complete = true;

                    /** @namespace $scope.buildStatus.createCmdbCi */
                    $scope.buildStatus.createCmdbCi.text = 'Creating CMDB CI...';
                    $scope.buildStatus.createCmdbCi.running = true;
                    return BuildService.createCmdbCi(server);
                })
                .then(function(server) {
                    $scope.buildStatus.createCmdbCi.running = false;
                    $scope.buildStatus.createCmdbCi.complete = true;

                    /** @namespace $scope.buildStatus.makingCoffee */
                    $scope.buildStatus.makingCoffee.text = 'Making coffee...';
                    $scope.buildStatus.makingCoffee.running = true;
                    return BuildService.makeCoffee(server);
                })
                .then(function(server) {
                    $scope.buildStatus.makingCoffee.running = false;
                    $scope.buildStatus.makingCoffee.complete = true;

                    /** @namespace $scope.buildStatus.ldapUpdate */
                    $scope.buildStatus.ldapUpdate.text = 'Adding host to LDAP...';
                    $scope.buildStatus.ldapUpdate.running = true;
                    return BuildService.updateLdap(server);
                })
                .then(function(server) {
                    $scope.buildStatus.ldapUpdate.running = false;
                    $scope.buildStatus.ldapUpdate.complete = true;

                    /** @namespace $scope.buildStatus.startWatcher */
                    $scope.buildStatus.startWatcher.text = 'Starting VMWare Template watcher...';
                    $scope.buildStatus.startWatcher.running = true;
                    return BuildService.startTemplateWatcher(server);
                })
                .then(function(server) {
                    BuildService.updateStatus(server.id, 'Building', 'Booting VM...');
                    $scope.buildStatus.startWatcher.running = false;
                    $scope.buildStatus.startWatcher.complete = true;
                    hidePageMask();

                    setTimeout(function() {
                        window.location.href = "/#/servers/building/0";
                    }, 2500);
                });
        }


        // ------------------------------------------------------------------------------------------------------------
        // General Purpose Methods
        // ------------------------------------------------------------------------------------------------------------

        function showPageMask() {
            $('#page-mask').show();
        }

        function hidePageMask() {
            $('#page-mask').hide();
        }

        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            var expires = "expires=" + d.toUTCString();
            //noinspection JSValidateTypes
            document.cookie = cname + "=" + cvalue + "; " + expires;
        }

        /**
         * Each time the user clicks 'Next' this function is called to obtain the necessary data from the WS API
         * @param state
         */
        $scope.next = function(state) {
            if (state === 'selfService.chefInfo' && $scope.server.vmSize.name === 'Custom') {
                // custom vm build was selected. set some cookies with BS, SubSys and Env and then call the full editor
                setCookie('businessServiceSysId', $scope.server.businessService.sysId, 1);
                setCookie('businessServiceName', $scope.server.businessService.name, 1);
                setCookie('subsystemSysId', $scope.server.subsystem.sysId, 1);
                setCookie('subsystemName', $scope.server.subsystem.name, 1);
                setCookie('cmdbEnvironment', $scope.server.cmdbEnvironment, 1);
                setCookie('description', $scope.server.description, 1);
                setCookie('ldapUserGroups', JSON.stringify($scope.server.ldapUserGroups), 1);
                setCookie('ldapHostGroups', JSON.stringify($scope.server.ldapHostGroups), 1);
                window.location.href = "/#/server/0";
            }

            $state.go(state);
        };


        /**
         * When we change to the last state of 'selfService.build' we want to call build()
         * this listener allows us to do that by checking the toState.name value
         */
        $scope.$on('$stateChangeSuccess',
            //function(evt, toState, toParams, fromState, fromParams) {
            function(evt, toState, o, fromState) {
                if (fromState.name === "") {
                    //user reloaded the page, go to current server list
                    window.location.href = "/#/servers/current/0";
                }
                else if (toState.name === 'selfService.build') {
                    // We can prevent this state from completing
                    evt.preventDefault();
                    $scope.buildIndicator = "active";

                    console.log("Calling build()");
                    build();
                } else if (toState.name === 'selfService.serverIdent') {
                    // check if BS is in allowed list of BSs that can perform full self-service
                    // if not, then move on to serverSize
                    if (selfServiceBusinessServices.indexOf($scope.server.businessService.name) === -1) {
                        evt.preventDefault();
                        $state.go('selfService.serverSize')
                    }
                    $scope.server.selfService = true;
                } else if (toState.name === 'selfService.serverSize') {
                    getVMSizes();
                }
            });

        /**
         * If called from editServer, then the server info will be saved in the NeuMatic service
         * and selfServer.build will be called via the URL
         */
        var server = JSON.parse(JSON.stringify(NeuMaticService.getServer()));
        if (server) {
            console.log("Getting server from NeuMaticSevice");
            $scope.server = JSON.parse(JSON.stringify(server));
            NeuMaticService.setServer(null);
        }

        /**
         * On initial page load, call the cmdbInfo view
         */
        $scope.buildIndicator = "";
        if ($state.current.name !== 'selfService.cmdbInfo' && !server) {
            getCmdbEnvironments();
            getCobblerDistros();
            getChefRoles();
            getChefEnvironments();
            getSelfServiceBusinessServices();
            $state.go('selfService.cmdbInfo');
        }
    });

