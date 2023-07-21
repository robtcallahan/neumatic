angular.module('NeuMatic')

    .controller('EditServerCtrl', function($scope, $log, $stateParams, $http, $q, $timeout, AuthedUserService, NeuMaticService, JiraService, AlertService, nodeService) {

        var ldapUserGroups = [];

        function loadLdapUserGroups() {
            ldapUserGroups = $http.get('/ldap/getUserGroups/' + AuthedUserService.authedUser.username, {}).then(function(res) {
                if (!res.data.success) {
                    ldapUserGroups = [];
                } else {
                    /** @namespace res.data.asObject */
                    ldapUserGroups = res.data.asObject;
                }
            });
        }

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

        // authenticated user services
        AuthedUserService.setCallback(loadLdapUserGroups);
        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;
        $scope.alertService = AlertService;
        $scope.$stateParams = $stateParams;
        $scope.neumaticService = NeuMaticService;

        // NeuMatic service
        NeuMaticService.setScope($scope);

        // nodeService saves our selected node's information from vmwareController so that we can retrieve it here
        $scope.nodeService = nodeService;

        // define the nav object that specifies the active menu item at the top
        $scope.nav = {servers: true};

        $scope.modal = {
            title: '',
            message: '',
            yesCallback: function() {
            },
            noCallback: function() {
            },
            showCancelButton: false,
            cancelCallback: function() {
            }
        };

        // Used to show what's going on
        $scope.statusText = "";
        // shows/hides the lovely spinner
        $scope.buildRunning = false;

        // delete flags
        var deleteConfig = false,
            deleteCmdb = false;


        // initialize drop downs
        $scope.businessServices = [];
        $scope.subsystems = [{
            name: "Loading...",
            sysId: ""
        }];
        $scope.cmdbEnvironments = ["Loading..."];

        $scope.userGroups = [];
        $scope.hostGroups = [];

        $scope.locations = [
            {
                name: 'Sterling-VA-NSR-B8',
                sysId: '252770b80a0a3cac01a23e2b410dd37d'
            }, {
                name: 'Charlotte-NC-CLT-1',
                sysId: '25276f670a0a3cac01e34ffb0d7c6b2d'
            }, {
                name: 'Denver-CO-NSR',
                sysId: 'de01420325c56dc07fa22d7bfbab57f1'
            }
        ];

        $scope.distSwitches = ["Loading..."];
        $scope.chassises = [{
            id: 0,
            name: 'Loading...'
        }];
        $scope.blades = [{
            id: 0,
            name: '',
            slot: '',
            fqdn: '',
            isInventory: true,
            displayValue: 'Loading...'
        }];

        $scope.dataCenters = [{
            vSphereSite: '',
            vSphereServer: '',
            name: 'Loading...',
            displayValue: 'Loading...',
            uid: ''
        }];
        $scope.computeClusters = [{
            "vSphereSite": "",
            "vSphereServer": "",
            "dcUid": "",
            "dcName": "",
            "uid": "",
            "ccrUid": "",
            "name": "Loading...",
            "ccrName": "",
            "rpUid": ""
        }];
        $scope.dataStores = [];
        $scope.luns = [];

        $scope.vlans = [{
            name: 'Loading...',
            displayValue: 'Loading...',
            vlanId: '',
            network: '',
            subnetMask: 'Loading...',
            gateway: 'Loading...',
            macAddress: '',
            ipAddress: ''
        }];
        $scope.template = "";
        $scope.macAddresses = [];
        $scope.macAddress = "";

        $scope.cobblerServers = [{
            name: 'Loading...',
            env: '',
            displayValue: 'Loading...'
        }];
        $scope.cobblerDistros = [{
            name: 'Loading...',
            warn: false
        }];
        $scope.cobblerKickstarts = ['Loading...'];
        $scope.isos = ["Loading..."];

        $scope.chefServers = [{
            name: "Loading...",
            env: "",
            fqdn: "",
            displayValue: "Loading...",
            allowBuild: true,
            allowChef: true
        }];

        $scope.chefEnvs = ["Loading..."];
        $scope.chefRoles = ["Loading..."];

        $scope.vmSizes = [];

        // initialize the server
        var saveAttrs = {};
        $scope.server = {
            id: parseInt($stateParams.id),
            serverType: 'vmware',
            name: '',

            okToBuild: false,

            // cmdb info
            sysId: '',
            businessService: {
                name: '',
                sysId: ''
            },
            subsystem: {
                name: '',
                sysId: ''
            },
            cmdbEnvironment: '',

            description: '',

            location: $scope.locations[0],

            // blade info
            distSwitch: null,
            chassis: {
                name: '',
                id: ''
            },
            blade: {
                name: '',
                id: '',
                slot: ''
            },

            // standalone info
            standalone: {
                id: 0,
                distSwitch: '',
                ilo: '',
                iso: ''
            },

            // network info
            vlan: {
                name: 'VLAN32',
                vlanId: 'dvportgroup-417',
                network: '172.30.32.0',
                subnetMask: '255.255.255.0',
                gateway: '172.30.32.1',
                macAddress: '',
                ipAddress: ''
            },

            ldapUserGroups: [{name: "CoreSA_user"}],
            ldapHostGroups: [{name: "core_hv_host"}],

            // server pool info
            poolServer: false,
            serverPoolId: 0,

            // VMWare stuff
            dataCenter: {
                vSphereSite: 'lab',
                vSphereServer: 'stlabvcenter03.va.neustar.com',
                name: 'LAB',
                uid: 'datacenter-401'
            },
            ccr: {
                name: 'LAB_Cluster',
                uid: 'domain-c406',
                rpUid: 'resgroup-407'
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

            // cobbler
            cobblerServer: 'stlabvmcblr01.va.neustar.com',
            cobblerDistro: {
                name: "CentOS-6.3-x86_64",
                warn: false
            },
            cobblerKickstart: '/var/lib/cobbler/kickstarts/baseline_6.ks',
            cobblerMetadata: '',
            isXen: false,

            // chef
            chefServer: 'stopcdvvcm01.va.neustar.com',
            chefRole: 'neu_collection',
            chefEnv: 'ST_CORE_LAB'
        };

        /**
         * New stuff here
         */

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
         * New stuff here
         */

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

        // ------------------------------------------------------------------------------------------------------------
        // Scope functions available in the view
        // ------------------------------------------------------------------------------------------------------------

        /**
         * Used in conjunction with the bootstrap typeahead function
         */
        $scope.getBusServicesForTypeAhead = NeuMaticService.getBusServicesForTypeAhead;
        $scope.getLocationsForTypeAhead = NeuMaticService.getLocationsForTypeAhead;
        $scope.getLdapUserGroupsForTypeAhead = NeuMaticService.getLdapUserGroupsForTypeAhead;
        $scope.getLdapHostGroupsForTypeAhead = NeuMaticService.getLdapHostGroupsForTypeAhead;

        $scope.$watch('authedUserService.authedUser.adminOn', function(newValue, oldValue) {
            if (typeof oldValue !== "undefined" && typeof newValue !== "undefined" && oldValue !== newValue) {
                getCobblerDistros();
            }
        });


        /**
         * Get the MAC address of a standalone host. Assumes that <hostname>-con.x.neustar.com exists in DNS
         * Called when "Retrieve MAC" button is clicked
         * Blades only
         */
        $scope.getStandaloneMacAddress = function() {
            var apiUrl;
            $scope.server.vlan.macAddress = "Querying...";
            if ($scope.server.id) {
                apiUrl = '/hardware/getMacAddress/' + $scope.server.id;
            } else {
                apiUrl = '/hardware/getMacAddressByName/' + $scope.server.name
            }
            //noinspection JSValidateTypes
            $http.get(apiUrl)
                .success(function(json) {
                    $scope.server.vlan.macAddress = "";
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/hardware/getMacAddress/' + $scope.server.id
                        });
                    } else {
                        $scope.server.vlan.macAddress = json.macAddress;
                    }
                })
                .error(function(json) {
                    $scope.server.vlan.macAddress = "";
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/hardware/getMacAddress/' + $scope.server.id
                    });
                });
        };

        $scope.getStandaloneMacAddresses = function() {
            if ($scope.macAddresses.length != 0) return;

            var apiUrl;
            $scope.macAddress = "Querying...";
            if ($scope.server.id) {
                apiUrl = '/hardware/getMacAddresses/' + $scope.server.id;
            } else {
                apiUrl = '/hardware/getMacAddressByName/' + $scope.server.name
            }
            //noinspection JSValidateTypes
            $http.get(apiUrl)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/hardware/getMacAddresses/' + $scope.server.id
                        });
                    } else {
                        $scope.macAddresses = json.macAddresses;
                        for (var i = 0; i < json.macAddresses.length; i++) {
                            if (json.macAddresses[i].address === $scope.server.vlan.macAddress) {
                                $scope.macAddress = json.macAddresses[i];
                            }
                        }
                    }
                })
                .error(function(json) {
                    $scope.server.vlan.macAddress = "";
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/hardware/getMacAddresses/' + $scope.server.id
                    });
                });
        };

        $scope.macAddressSelected = function() {
            $scope.server.vlan.macAddress = $scope.macAddress.address;
        };

        /**
         * Get the MAC address of a blade given a blade name and chassis id
         * Called when "Retrieve MAC" button is clicked
         * Blades only
         */
        $scope.getBladeMacAddress = function() {
            //noinspection JSValidateTypes
            $http.get('/chassis/getMacAddress/' + $scope.server.name + '/' + $scope.server.chassis.id)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/chassis/getMacAddress/' + $scope.server.name + '/' + $scope.server.chassis.id
                        });
                    } else {
                        $scope.server.vlan.macAddress = json.macAddress;
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/chassis/getMacAddress/' + $scope.server.name + '/' + $scope.server.chassis.id
                    });
                });
        };

        /**
         * Get the next available IP address in a network give the network name and subnetmask
         * Called by getVLanDetailsByVLanIdAndDistSwitchName() when a VLAN is selected
         */
        $scope.getNextAvailableIPAddress = function() {
            if ($scope.server.vlan.ipAddress === '' || typeof $scope.server.vlan.ipAddress === "undefined") {
                NeuMaticService.getNextAvailableIPAddress($scope.server.vlan.network, $scope.server.vlan.subnetMask,
                    function(json) {
                        $scope.server.vlan.ipAddress = json.ipAddress;
                    }
                );
            }
        };

        // ------------------------------------------------------------------------------------------------------------
        // Field Changes
        // ------------------------------------------------------------------------------------------------------------

        /**
         * Called when the server type is changed
         * Assign value to $scope.server
         */
        $scope.onServerTypeChange = function() {
            $scope.server.serverPoolId = 0;
            if ($scope.server.serverType === "blade") {
                if ($scope.distSwitches.length === 0) {
                    getDistSwitches();
                }
                updateCobblerServer();
            } else if ($scope.server.serverType === "standalone") {
                if ($scope.distSwitches.length === 0) {
                    $scope.vlans = [];
                    getDistSwitches();
                }
	    } else if ($scope.server.serverType === "vmwareCobbler") {
	    	getDataCenters();
            } else if ($scope.server.serverType === "vmware") {
                getDataCenters();
                getVmwareTemplates();
            } else {
                // nothing
            }
        };

        /**
         * When the host name is changed, select the appropriate location based on the first
         * 2 characters of the host name (st or ch)
         * Also, if the name is changed, we can assume that this is no longer going to be a server
         * from the pool, so we need to clear that bit so future logic operates correctly
         *
         */
        $scope.onHostNameChange = function() {
            var loc, fields;

            if (typeof $scope.server.name !== "undefined" && $scope.server.name !== "") {
                loc = $scope.server.name.substring(0, 2);

                if ($scope.server.serverType === "blade" || $scope.server.serverType === "standalone") {
                    if (loc === "st") {
                        $scope.server.location = $scope.locations[0];
                        $scope.locationSelected();
                    } else if (loc === "ch") {
                        $scope.server.location = $scope.locations[1];
                        $scope.locationSelected();
                    } else if (loc === "de") {
                        $scope.server.location = $scope.locations[2];
                        $scope.locationSelected();
                    } else {
                        $scope.server.location = {name: '', sysId: ''};
                    }
                }
                // set ilo name if not already set
                if ($scope.server.serverType === "standalone" &&
                    (typeof $scope.server.standalone.iLo === 'undefined' || $scope.server.standalone.iLo === '')) {
                    fields = $scope.server.name.split('.');
                    fields[0] += '-con';
                    $scope.server.standalone.iLo = fields.join('.');
                }

                if ($scope.server.name.search(/xm/) !== -1) {
                    $scope.server.isXen = true;
                    $scope.server.cobblerMetadata = 'server_type=xendom0';
                }
                checkForExistingDNSEntry(checkForExistingCMDBEntry);
            }
            //$scope.server.serverPoolId = 0;
        };

        /**
         * Called when a business service is selected
         * Assign value to $scope.server
         */
        $scope.businessServiceSelected = function() {
            getSubsystems();
        };

        /**
         * Called when the location is changed
         * Get the list of distribution switches and updates the cobbler server
         * Blades only
         *
         */
        $scope.locationSelected = function() {
            if ($scope.server.serverType === "blade" || $scope.server.serverType === 'standalone') {
                getDistSwitches();
                updateCobblerServer();
            }
        };

        /**
         * Get the Cluster Compute Resources and update the cobbler server when a
         * vSphere datacenter is selected
         * VMWare VMs only
         *
         */
        $scope.dataCenterSelected = function() {
            if ($scope.server.serverType === "vmware" || $scope.server.serverType === "vmwareCobbler") {
                // clear the cluster and VLAN on this change
                $scope.server.vlan = {};
                $scope.server.ccr = {};
                getComputeClusters();
                getVmwareTemplates();
                updateCobblerServer();
            }
        };

        /**
         * Triggered by selecting a cluster compute resource
         * VMWare VMs only
         *
         */
        $scope.computeClusterSelected = function() {
            if ($scope.server.serverType === "vmware" || $scope.server.serverType === "vmwareCobbler") {
                getCCRNetworks();
            }
        };

        /**
         * Triggered by selecting a distribution switch
         * Blades only
         *
         */
        $scope.distSwitchSelected = function() {
            if ($scope.server.serverType === "blade") {
                getSwitchVLans('hpsim');
                getChassis();
            } else {
                getSwitchVLans('ip');
            }
            /*
             if ($scope.server.serverType === "blade") {
             getChassis();
             }
             getVLansByDistSwitch();
             */
        };

        /**
         * Triggered by selecting a chassis
         * Call getBlade()
         * Blades only
         *
         */
        $scope.chassisSelected = function() {
            getBlade();
        };

        /**
         * Triggered by selecting a blade
         * Call getBladeMacAddress()
         * Blades only
         *
         */
        $scope.bladeSelected = function() {
            if ($scope.server.name && $scope.server.chassis.id) {
                $scope.getBladeMacAddress();
            }
        };

        /**
         * Triggered by selecting a VLAN
         * Assign subnet mask and gateway if available from VLAN
         *
         */
        $scope.vlanSelected = function() {
            if ($scope.server.serverType === "vmware" || $scope.server.serverType === "vmwareCobbler") {
                getVLanDetailsByVLanIdAndDistSwitchName($scope.server.vlan.name, $scope.server.ccr.name);
            } else if ($scope.server.serverType === "blade" || $scope.server.serverType === "standalone") {
                getVLanDetailsByVLanIdAndDistSwitchName($scope.server.vlan.vlanId, $scope.server.distSwitch);
            }
        };
        /**
         * Triggered by selecting a Template
         *
         */
        $scope.templateSelected = function() {
            if ($scope.server.serverType === "vmware") {
                getVmwareTemplates();
            }
        };

        /**
         * Triggered by changing the cobbler server
         * Calls methods to get the distributions and kickstart files
         */
        $scope.cobblerServerSelected = function() {
            getCobblerDistros();
            getCobblerKickstarts();
        };

        /**
         * Triggered by selecting a cobbler distribution
         * Automatically sets the kickstart file based on the distribution (OS)
         */
        $scope.cobblerDistroSelected = function() {
            // TODO: this needs to be changed to a 'superuser' user type and not a specific user
            if ($scope.authedUser.username !== 'sclark') {
                if ($scope.server.cobblerDistro.name.search(/6\.[0-9]/) !== -1) {
                    $scope.server.cobblerKickstart = '/var/lib/cobbler/kickstarts/baseline_6.ks';
                } else {
                    $scope.server.cobblerKickstart = '/var/lib/cobbler/kickstarts/baseline.ks';
                }
            }

            if (typeof $scope.server.cobblerDistro.warn !== "undefined" && $scope.server.cobblerDistro.warn) {
                $scope.cobblerDistroWarning = 'This OS distribution is only provided for Chef cookbook development and is not available for new production instances.';
            } else {
                $scope.cobblerDistroWarning = '';
            }

        };

        $scope.remoteServerChanged = function() {
            if ($scope.server.remoteServer == 1 && $scope.isos.length === 0) {
                getISOs();
            }
        };

        $scope.isXenChanged = function() {
            if ($scope.server.isXen) {
                $scope.server.cobblerMetadata = 'server_type=xendom0';
            } else {
                $scope.server.cobblerMetadata = '';
            }
        };

        /**
         * Triggered by selecting a chef server
         * Calls methods to get the chef environments and roles
         */
        $scope.chefServerSelected = function() {
            getChefEnvironments();
            getChefRoles();
        };

        // ------------------------------------------------------------------------------------------------------------
        // Buttons
        // ------------------------------------------------------------------------------------------------------------

        /**
         * Triggered by the Add LUN button
         */
        $scope.buttonAddLun = function() {
            $scope.server.vmSize.luns.push({
                id: null,
                lunSizeGb: 0
            });
        };

        /**
         * Triggered by the Delete LUN button
         *
         * @param index
         */
        $scope.buttonDeleteLun = function(index) {
            $scope.server.vmSize.luns.splice(index, 1);
        };

        /**
         * Triggered by the New Server button
         */
        $scope.buttonNewServer = function() {
            window.location.href = "/#/server/0";
        };

        /**
         * Save this server
         * Triggered by the Save button
         */
        $scope.buttonSaveServer = function() {
            saveServer();
        };

        /**
         * Start the build process
         * Triggered by the Build button
         *
         */
        $scope.buttonBuildServer = function() {
	    if ($scope.server.serverType === "vmware" || $scope.server.serverType === "vmwareCobbler") {
            	var vmText =  'delete and recreate the VM and ';
	    }else{
	        var vmText = '';
	    }
            var descr = $scope.server.description ? '(' + $scope.server.description + ')' : '';
            var msg = 'Are you sure you want to <strong>build</strong> ' + $scope.server.name + '?<br>' + descr + '<br><br>' +
                'This <strong>will</strong> ' + vmText + ' update or create the DNS, LDAP and CMDB entries. Then reinstall the OS and run Chef.';

            $scope.modal = {
                title: 'Confirm Build',
                message: msg,
                yesCallback: function() {
                    NeuMaticService.setRebuild(false);
                    setTimeout(function() {
                        saveServer({build: true});
                    }, 500);
                }
            };
            //noinspection JSUnresolvedFunction
            $('#promptModal').modal('show');
        };

        $scope.buttonRebuildServer = function() {
            if ($scope.server.serverType === "vmware" || $scope.server.serverType === "vmwareCobbler") {
            	var vmText =  'VM, ';
	    }else{
	        var vmText = '';
	    }
	    
	    var msg = 'Are you sure you want to <strong>rebuild</strong> ' + $scope.server.name + '?<br><br>' +
                'This <strong>will not</strong> delete and recreate the ' + vmText + 'DNS, LDAP or CMDB entries.<br>' +
                'It will just load the OS and run Chef.';

            $scope.modal = {
                title: 'Confirm Rebuild',
                message: msg,
                yesCallback: function() {
                    NeuMaticService.setRebuild(true);
                    setTimeout(function() {
                        saveServer({build: true});
                    }, 500);
                }
            };
            //noinspection JSUnresolvedFunction
            $('#promptModal').modal('show');
        };

        /**
         * Triggered by the Copy button
         * Clear out the name and IP address
         * If not an admin user then get the next pool server
         */
        $scope.buttonCopyServer = function() {
            if ($scope.authedUserService.authedUser.userType === "Admin") {
                // admin user so just clear out name and IP and server id
                $scope.server.name = "";
                $scope.server.vlan.ipAddress = "";
                $scope.server.vlan.macAddress = "";

                $scope.server.id = 0;
                $scope.server.name = "";
                $scope.server.vlan.ipAddress = "";
                $scope.server.vlan.macAddress = "";

                // clear the ids from all the luns otherwise we'll overwrite the same rows in the storage table
                if (typeof $scope.server.luns !== 'undefined') {
                    for (var i = 0; i < $scope.server.luns.length; i++) {
                        $scope.server.luns[i].id = 0;
                    }
                }
            } else {
                // general user so get another server from the pool
                getNextPoolServer(true);
            }
        };

        /**
         * Called by the Cancel button
         * Don't save and return to the servers page
         */
            // TODO: Need to show a confirmation box if changes where made
        $scope.buttonCancelServerEdit = function() {
            window.location.href = "/#/servers/current/0";
        };

        /**
         * Triggered by the Delete button to delete a VMWare VM
         * Confirm user really wants to do this
         */
        $scope.buttonDeleteServer = function() {
            var msg = 'Are you sure you want to delete ' + $scope.server.name + '?';
            if ($scope.server.description) {
                msg += '(' + $scope.server.description + ')';
            }
            $scope.modal = {
                title: 'Confirm Delete',
                message: msg,
                yesCallback: function() {
                    promptDeleteConfig();
                }
            };
            //noinspection JSUnresolvedFunction
            $('#promptModal').modal('show');
        };

        /**
         * Triggered by the Extend Lease button
         */
        $scope.buttonExtendLease = function() {
            extendLease();
        };

        // ----------------------------------------------------------------------------------------------------
        // Private functions
        // ----------------------------------------------------------------------------------------------------

        // ------------------------------------------------------------------------------------------------------------
        // Data Providers
        // ------------------------------------------------------------------------------------------------------------

        /**
         * Get a list of subsystems for a business service given its sysId
         * Called when a business service is selected
         */
        function getSubsystems() {
            NeuMaticService.getSubsystemsByBSId($scope.server.businessService.sysId,
                function(subsystems) {
                    $scope.subsystems = subsystems;
                    if (typeof saveAttrs.subsystem !== 'undefined') {
                        $scope.server.subsystem = saveAttrs.subsystem;
                    }

                    // even though our server subsystem is defined, we have to perform the following
                    // in order to get the correct option to appear in the select field
                    subsystems.forEach(function(item) {
                        if (item.name === $scope.server.subsystem.name) {
                            $scope.server.subsystem = item;
                        }
                    });
                    for (var i = 0; i < subsystems.length; i++) {
                        if (subsystems[i].name === $scope.server.subsystem.name) {
                            $scope.server.subsystem = subsystems[i];
                        }
                    }
                });
        }

        /**
         * Get the list of CMDB environments so the user can choose one
         */
        function getCmdbEnvironments() {
            NeuMaticService.getCmdbEnvironments(
                function(environments) {
                    $scope.cmdbEnvironments = environments;
                    if (typeof saveAttrs.cmdbEnvironment !== 'undefined') {
                        $scope.server.cmdbEnvironment = saveAttrs.cmdbEnvironment;
                    }
                }
            );
        }

        /**
         * Get a list of VMWare data centers
         * Called at the beginning of the page load
         * VMWare VMs only
         */
        function getDataCenters() {
            NeuMaticService.getVmwareDataCenters(
                function(dataCenters) {
                    var dc;
                    $scope.dataCenters = dataCenters;
                    if (typeof saveAttrs.dataCenter !== 'undefined') {
                        $scope.server.dataCenter = saveAttrs.dataCenter;
                    }
                    for (var i = 0; i < dataCenters.length; i++) {
                        dc = dataCenters[i];
                        if (dc.uid === $scope.server.dataCenter.uid && dc.vSphereServer === $scope.server.dataCenter.vSphereServer) {
                            $scope.server.dataCenter = dc;
                        }
                    }
                    getComputeClusters();
                    getVmwareTemplates();
                }
            );
        }

        /**
         * Get a list of distribution switches and select the switch for this server
         * Called at the beginning of the page load
         * Blades only
         */
        function getDistSwitches() {
            NeuMaticService.apiGet('/ip/getDistSwitchesByLocation/' + $scope.server.location.name,
                function(json) {
                    $scope.distSwitches = json.distSwitches;
                    if (typeof saveAttrs.distSwitch !== 'undefined') {
                        $scope.server.distSwitch = saveAttrs.distSwitch;
                    }
                    for (var i = 0; i < json.distSwitches.length; i++) {
                        if (json.distSwitches[i] === $scope.server.distSwitch) {
                            $scope.server.distSwitch = json.distSwitches[i];
                        }
                    }
                    if ($scope.server.distSwitch) {
                        if ($scope.server.serverType === "blade") {
                            getSwitchVLans('hpsim');
                            getChassis();
                        } else {
                            getSwitchVLans('ip');
                        }
                    }
                }
            );
        }

        /**
         * Get a list of chassis for dist switch and select the chassis for this sever
         * Called from getDistSwitches()
         * Blades only
         */
        function getChassis() {
            //noinspection JSValidateTypes
            $http.get('/hpsim/getChassisByDistributionSwitch/' + $scope.server.distSwitch)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/hpsim/getChassisByDistributionSwitch/' + $scope.server.distSwitch
                        });
                    } else {
                        var c;
                        $scope.chassises = json.chassis;
                        if (typeof saveAttrs.chassis !== 'undefined') {
                            $scope.server.chassis = saveAttrs.chassis;
                        }
                        for (var i = 0; i < json.chassis.length; i++) {
                            c = json.chassis[i];
                            if (c.name === $scope.server.chassis.name) {
                                $scope.server.chassis = c;
                                getBlade();
                            }
                        }
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/hpsim/getChassisByDistributionSwitch/' + $scope.server.distSwitch
                    });
                });
        }

        /**
         * Get a list of inventory blades for a chassis and select the blade for this server
         * Called from getChassis()
         * Blades only
         */
        function getBlade() {
            //noinspection JSValidateTypes
            $http.get('/hpsim/getChassisBlades/' + $scope.server.chassis.id)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/hpsim/getChassisBlades/' + $scope.server.chassis.id
                        });
                    } else {
                        var b;
                        $scope.blades = [];
                        if (typeof saveAttrs.blade !== 'undefined') {
                            $scope.server.blade = saveAttrs.blade;
                        }
                        for (var i = 0; i < json.blades.length; i++) {
                            b = json.blades[i];
                            /** @namespace j.isInventory */
                            /** @namespace j.slot */
                            if (parseInt(b.slot) === parseInt($scope.server.blade.slot)) {
                                $scope.server.blade = b;
                                $scope.blades.push(b);
                            } else if (b.isInventory || b.name.search(/^use|^mxq/) !== -1) {
                                $scope.blades.push(b);
                            } else {
                                // TODO: this is a temporary workaround to list all blades
                                $scope.blades.push(b);
                            }
                        }
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/hpsim/getChassisBlades/' + $scope.server.chassis.id
                    });
                });
        }

        /**
         * Get a list of VLANs for a given distribution switch
         * Called at the beginning of a page load from getDistSwitches()
         * Blades only
         * @param controller string
         */
        function getSwitchVLans(controller) {
            NeuMaticService.apiGet('/' + controller + '/getSwitchVLans/' + $scope.server.distSwitch,
                function(json) {
                    var v;
                    $scope.vlans = json.vlans;
                    if (typeof saveAttrs.vlan !== 'undefined') {
                        $scope.server.vlan = saveAttrs.vlan;
                    }
                    for (var i = 0; i < json.vlans.length; i++) {
                        v = json.vlans[i];
                        if (v.name === $scope.server.vlan.name) {
                            var cache = $scope.server.vlan;
                            $scope.server.vlan = v;
                            $scope.server.vlan.network = v.ipSubnet;
                            $scope.server.vlan.ipAddress = cache.ipAddress;
                            $scope.server.vlan.macAddress = cache.macAddress;

                            if (cache.network !== '') {
                                $scope.server.vlan.network = cache.network;
                            }
                            if (cache.subnetMask !== '') {
                                $scope.server.vlan.subnetMask = cache.subnetMask;
                            }
                            if (cache.gateway !== '') {
                                $scope.server.vlan.gateway = cache.gateway;
                            }
                        }
                    }
                    if ($scope.server.serverType === 'blade' && $scope.blades.length === 0) {
                        getBlade();
                    }
                }
            );
        }

        /**
         * Get a list of VLANs given a distribution switch
         * Called when a distribution switch is selected
         * Blades only
         */
        /*
         no longer used
         function getVLansByDistSwitch() {
         $scope.vlans = [{name:"Loading...", displayValue: "Loading..."}];
         $scope.server.vlan = $scope.vlans[0];
         //noinspection JSValidateTypes
         $http.get('/hpsim/getSwitchVLans/' + $scope.server.distSwitch)
         .success(function (json) {
         if (typeof json.success !== "undefined" && !json.success) {
         AlertService.ajaxAlert({
         json: json,
         apiUrl: '/hpsim/getSwitchVLans/' + $scope.server.distSwitch
         });
         } else {
         $scope.vlans = json.vlans;
         $scope.server.vlan = {};
         }
         })
         .error(function (json) {
         AlertService.ajaxAlert({
         json: json,
         apiUrl: '/hpsim/getSwitchVLans/' + $scope.server.distSwitch
         });
         });
         }
         */

        /**
         * Get a list of VM sizes (small, medium, large) and their configs (cpu, mem, disk, etc)
         * Called when the page loads
         * VMWare VMs only
         */
        function getVMSizes() {
            NeuMaticService.getVMSizes(
                function(vmSizes) {
                    var j, s,
                        defaultSize = false,
                        size = $scope.server.vmSize;
                    $scope.vmSizes = [];

                    vmSizes.forEach(function(vmSize) {
                        if (vmSize.name !== "Large" || $scope.authedUser.userType === 'Admin') {
                            $scope.vmSizes.push(vmSize);
                        }

                        // check for a default value of small, med or large
                        if (vmSize.name === size.name && vmSize.numCPUs === size.numCPUs
                            && vmSize.memoryGB === size.memoryGB) {

                            defaultSize = vmSize.luns.length === size.luns.length;
                            for (j = 0; j < vmSize.luns.length; j++) {
                                if (vmSize.luns[j].lunSizeGb !== size.luns[j].lunSizeGb) {
                                    defaultSize = false;
                                    break;
                                }
                            }
                            if (defaultSize) $scope.server.vmSize = vmSize;
                        }
                    });

                    // not default size. Set dropdown to custom
                    if (!defaultSize) {
                        s = $scope.server.vmSize;

                        for (j = 0; j < $scope.vmSizes.length; j++) {
                            if ($scope.vmSizes[j].name === 'Custom') {
                                $scope.vmSizes[j] = {
                                    name: 'Custom',
                                    displayValue: 'Custom',
                                    numCPUs: s.numCPUs,
                                    memoryGB: s.memoryGB,
                                    luns: s.luns
                                };
                                $scope.server.vmSize = $scope.vmSizes[j];
                            }
                        }
                    }
                }
            );
        }

        /**
         * Get a list of cluster computer resources for a given data center and vsphere site
         * Called on a page load and when a data center is selected
         * VMWare VMs only
         */
        function getComputeClusters() {
            if ($scope.server.dataCenter.uid) {
                NeuMaticService.getVmwareComputeClusters($scope.server.dataCenter.uid, $scope.server.dataCenter.vSphereSite,
                    function(computeClusters) {
                        var cc;
                        $scope.computeClusters = computeClusters;
                        if (typeof saveAttrs.ccr !== 'undefined') {
                            $scope.server.ccr = saveAttrs.ccr;
                        }
                        for (var i = 0; i < computeClusters.length; i++) {
                            cc = computeClusters[i];
                            if (cc.name === $scope.server.ccr.name) {
                                $scope.server.ccr = cc;
                            }
                        }
                        getCCRNetworks();
                    }
                );
            } else {
                $scope.computeClusters = [];
                $scope.vlans = [];
                getCCRNetworks();
            }
        }

        /**
         * Get a list of templates for a given data center and vsphere site
         * Called on a page load and when a data center is selected
         * VMWare VMs only
         */
        function getVmwareTemplates() {
            if ($scope.server.dataCenter.uid) {
                NeuMaticService.getVmwareTemplates($scope.server.dataCenter.vSphereSite, $scope.server.dataCenter.dcName,
                    function(templates) {
                        $scope.templates = templates;

                        if (typeof $scope.server.template == 'undefined') {
                            $scope.server.template = $scope.templates[0];
                        } else {
                            for (var i = 0; i < templates.length; i++) {
                                var template = templates[i];
                                if (template.id === $scope.server.template.id) {
                                    $scope.server.template = templates[i];

                                }
                            }
                        }
                    }
                );
            } else {
                $scope.templates = [];
            }
        }

        /**
         * Get a list of Cluster Compute Resource networks (VLANs)
         * Called on page load and when a Cluster Compute Resource is selected
         * VMWare VMs only
         */
        function getCCRNetworks() {
            var cache;
            if ($scope.server.ccr.uid) {
                NeuMaticService.getVmwareClusterComputeResourceNetworks($scope.server.ccr.uid, $scope.server.dataCenter.vSphereSite,
                    function(vlans) {
                        var vlan;
                        $scope.vlans = vlans;
                        if (typeof saveAttrs.vlan !== 'undefined') {
                            $scope.server.vlan = saveAttrs.vlan;
                        }
                        for (var i = 0; i < vlans.length; i++) {
                            vlan = vlans[i];
                            if (vlan.vlanName === $scope.server.vlan.name) {
                                cache = $scope.server.vlan;
                                $scope.server.vlan = vlan;
                                $scope.server.vlan.network = cache.network;
                                $scope.server.vlan.subnetMask = cache.subnetMask;
                                $scope.server.vlan.gateway = cache.gateway;
                                $scope.server.vlan.ipAddress = cache.ipAddress;
                                $scope.server.vlan.macAddress = cache.macAddress;
                            }
                        }
                    }
                );
            } else {
                $scope.vlans = [];
                $scope.server.vlan = saveAttrs.vlan;
            }
        }

        /**
         * Get a list of cobbler servers
         * Called on page load
         */
        function getCobblerServers() {
            NeuMaticService.getCobblerServers(
                function(cobblerServers) {
                    var c;
                    $scope.cobblerServers = cobblerServers;
                    if (typeof saveAttrs.cobblerServer !== 'undefined') {
                        $scope.server.cobblerServer = saveAttrs.cobblerServer;
                    }
                    for (var i = 0; i < cobblerServers.length; i++) {
                        c = cobblerServers[i];
                        if (c.name === $scope.server.cobblerServer) {
                            $scope.server.cobblerServer = c.name;
                        }
                    }
                    if ($scope.server.cobblerServer === saveAttrs.cobblerServer && saveAttrs.cobblerServer === "Loading...") {
                        $scope.server.cobblerServer = "";
                    } else {
                        getCobblerKickstarts();
                        getCobblerDistros();
                    }
                }
            );
        }

        /**
         * Get a list of cobbler distributions (OSs)
         * Called on page load by getCobblerServers() and when a cobbler server is selected
         */
        function getCobblerDistros() {
            if ($scope.server.cobblerServer) {
                NeuMaticService.getCobblerDistros($scope.server.cobblerServer,
                    function(distros) {
                        var d;
                        $scope.cobblerDistros = distros;
                        if (typeof saveAttrs.cobblerDistro !== 'undefined') {
                            $scope.server.cobblerDistro = saveAttrs.cobblerDistro;
                        }
                        for (var i = 0; i < distros.length; i++) {
                            d = distros[i];
                            if (d.name === $scope.server.cobblerDistro.name) {
                                $scope.server.cobblerDistro = d;
                            }
                        }
                        $scope.cobblerDistroSelected();
                    }
                );
            }
        }

        /**
         * Get a list of cobbler kickstart files
         * Called on page load by getCobblerServers() and when a cobbler server is selected
         * Note that we are not displaying this to the user now. The kickstart is selected
         * automatically based upon the distribution. This may change in the future.
         */
        function getCobblerKickstarts() {
            if ($scope.server.cobblerServer) {
                NeuMaticService.getCobblerKickstarts($scope.server.cobblerServer,
                    function(kickstarts) {
                        if (typeof saveAttrs.cobblerKickstart !== 'undefined') {
                            $scope.server.cobblerKickstart = saveAttrs.cobblerKickstart;
                        }
                        $scope.cobblerKickstarts = kickstarts;
                    }
                );
            }
        }

        /**
         * Get a list of ISOs on the yum repo server
         */
        function getISOs() {
            NeuMaticService.getISOs(
                function(isos) {
                    $scope.isos = isos;
                    if (typeof saveAttrs.iso !== 'undefined') {
                        $scope.server.standalone.iso = saveAttrs.iso;
                    }
                    isos.forEach(function(iso) {
                        if (iso === $scope.server.standalone.iso) {
                            console.log("setting iso to " + iso);
                            $scope.server.standalone.iso = iso;
                        }
                    })
                }
            );
        }

        /**
         * Get a list of chef servers
         * Called on page load
         */
        function getChefServers() {
            NeuMaticService.getChefServers(
                function(chefServers) {
                    var s;
                    $scope.chefServers = chefServers;
                    for (var i = 0; i < chefServers.length; i++) {
                        s = chefServers[i];
                        if (s.name === $scope.server.chefServer) {
                            $scope.server.chefServer = s.name;
                        }
                    }
                    if (typeof saveAttrs.chefServer !== 'undefined') {
                        $scope.server.chefServer = saveAttrs.chefServer;
                    }
                    getChefRoles();
                    getChefEnvironments();
                }
            );
        }

        /**
         * Get a list of chef roles
         * Called on page load by getChefServers() and when a chef server is selected
         */
        function getChefRoles() {
            if ($scope.server.chefServer) {
                NeuMaticService.getChefRoles($scope.server.chefServer,
                    function(roles) {
                        $scope.chefRoles = roles;
                        if (typeof saveAttrs.chefRole !== 'undefined') {
                            $scope.server.chefRole = saveAttrs.chefRole;
                        }
                    }
                );
            }
        }

        /**
         * Get a list of chef environments
         * Called on page load by getChefServers() and when a chef server is selected
         */
        function getChefEnvironments() {
            if ($scope.server.chefServer) {
                NeuMaticService.getChefEnvironments($scope.server.chefServer,
                    function(environments) {
                        $scope.chefEnvs = environments;
                        if (typeof saveAttrs.chefEnv !== 'undefined') {
                            $scope.server.chefEnv = saveAttrs.chefEnv;
                        }
                    }
                );
            }
        }

        /**
         * Get the details of a VLAN (network, subnetmask and gateway) give a VLAN ID and dist switch name
         * Called when a VLAN is selected
         */
        function getVLanDetailsByVLanIdAndDistSwitchName(vlanId, switchName) {

        	var vlanId = vlanId.split("/")[0];

            NeuMaticService.apiGet('/ip/getVlanDetailsByVlanIdAndDistSwitchName/' + vlanId + '/' + switchName,
                function(json) {
                    var vlanData;
                    /** @namespace json.vlanData */
                    /** @namespace json.vlanData.ipSubnet */
                    /** @namespace json.vlanData.subnetMask */
                    /** @namespace json.vlanData.gateway */
                    vlanData = json.vlanData;
                    $scope.server.vlan.network = vlanData.ipSubnet;
                    $scope.server.vlan.subnetMask = vlanData.subnetMask;
                    $scope.server.vlan.gateway = vlanData.gateway;

                    checkForExistingDNSEntry();
                },
                function() {
                    $scope.alertService.setAlertType('danger');
                    $scope.alertService.setAlertTitle('Error');
                    $scope.alertService.setAlertMessage("Could not determine VLAN information. You have to enter it manually. If you provide <a href='mailto:Rob.Callahan@neustar.biz'>Rob Callahan</a> with the VLAN details, he can add it to the NeuMatic database.");
                    $scope.alertService.setAlertShow(true);

                    $scope.server.vlan.network = '';
                    $scope.server.vlan.subnetMask = '';
                    $scope.server.vlan.gateway = '';
                }
            );
        }

        /**
         * Update the cobbler server given a location name
         * Called by locationSelected() when the location is changed
         */
        function updateCobblerServer() {
            // update the cobbler server appropriate for the location selected
            for (var i = 0; i < $scope.cobblerServers.length; i++) {
                var cServer = $scope.cobblerServers[i],
                    locationTest;

                if ($scope.server.serverType === 'vmwareCobbler') {
                    locationTest = $scope.server.dataCenter.name;
                } else {
                    locationTest = $scope.server.location.name;
                }
                if (cServer.env === locationTest) {
                    $scope.server.cobblerServer = cServer.name;
                    getCobblerDistros();
                    return;
                }
            }
        }

        function saveServer() {
            var data,
                server = $scope.server,
                options = {
                    build: false
                };

            if (arguments.length > 0 && typeof arguments[0] === "object") {
                options = arguments[0];
            }

            showLoading();

            // display the saving status
            $scope.statusText = "Saving...";

            var ldapUserGroups = [];
            $scope.server.ldapUserGroups.forEach(function(group) {
                ldapUserGroups.push(group.name);
            });
            var ldapHostGroups = [];
            $scope.server.ldapHostGroups.forEach(function(group) {
                ldapHostGroups.push(group.name);
            });

            // create a data structure with all the values of the server
            data = $.param({
                name: server.name,
                id: server.id,
                serverType: server.serverType,
                serverPoolId: server.serverPoolId,

                businessServiceId: server.businessService ? server.businessService.sysId : 0,
                businessServiceName: server.businessService.name ? server.businessService.name : '',
                subsystemId: server.subsystem.sysId ? server.subsystem.sysId : 0,
                subsystemName: server.subsystem.name ? server.subsystem.name : '',
                cmdbEnvironment: server.cmdbEnvironment,

                description: server.description,

                ldapUserGroups: ldapUserGroups,
                ldapHostGroups: ldapHostGroups,

                location: server.location.name,
                locationId: server.location.sysId,

                distSwitch: server.distSwitch,

                chassisName: server.chassis.name,
                chassisId: server.chassis.id,
                bladeName: server.blade.name,
                bladeId: server.blade.id,
                bladeSlot: server.blade.slot,

                standaloneId: server.standalone.id,

                vmSize: server.vmSize.name,
                numCPUs: server.vmSize.numCPUs,
                memoryGB: server.vmSize.memoryGB,
                luns: angular.toJson(server.vmSize.luns),

                vSphereSite: server.dataCenter.vSphereSite,
                vSphereServer: server.dataCenter.vSphereServer,
                dcName: server.dataCenter.name,
                dcUid: server.dataCenter.uid,
                ccrName: server.ccr.name,
                ccrUid: server.ccr.uid,
                rpUid: server.ccr.rpUid,
                templateId: server.template.id,
                templateName: server.template.name,

                vlanName: server.vlan.name,
                vlanId: server.vlan.vlanId,
                network: server.vlan.network,
                subnetMask: server.vlan.subnetMask,
                gateway: server.vlan.gateway,
                macAddress: server.vlan.macAddress,
                ipAddress: server.vlan.ipAddress,

                cobblerServer: server.cobblerServer,
                cobblerDistro: server.cobblerDistro.name,
                cobblerKickstart: server.cobblerKickstart,
                cobblerMetadata: server.cobblerMetadata,

                remoteServer: server.remoteServer,
                iLo: server.standalone.iLo,
                iso: server.standalone.iso,

                chefServer: server.chefServer,
                chefRole: server.chefRole,
                chefEnv: server.chefEnv
            });

            // call a POST to the neumatic controller passing all the server values
            //noinspection JSValidateTypes
            $http({
                method: 'POST',
                url: '/neumatic/saveServer',
                data: data,
                // content type is required here so that the data is formatted correctly
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            })

                // on success, return to the servers page
                .success(function(json) {
                    var firstSave = $scope.server.id ? false : true;

                    if (typeof json.success !== "undefined" && !json.success) {
                        $scope.statusText = "";
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/saveServer'
                        });
                        return;
                    }

                    // call function to display new status for a spell
                    displayStatus("Saved", "");

                    $scope.server.id = json.server.id;
                    $scope.server.okToBuild = json.server.okToBuild;
                    $scope.server.oldName = json.server.oldName;

                    if (firstSave) {
                        hideLoading();

                        //noinspection JSValidateTypes
                        $http.get('/neumatic/getServer/' + $scope.server.id)
                            .success(function(json) {
                                if (typeof json.success !== "undefined" && !json.success) {
                                    AlertService.ajaxAlert({
                                        json: json,
                                        apiUrl: '/neumatic/getServer/' + $scope.server.id
                                    });
                                } else {
                                    $scope.server = json.server;

                                    // Change the location bar to point to the new serverId
                                    var location = document.location;
                                    location.hash = "#/server/" + $scope.server.id;
                                }
                            })
                            .error(function(json) {
                                AlertService.ajaxAlert({
                                    json: json,
                                    apiUrl: '/neumatic/getServer/' + $scope.server.id
                                });
                            });
                    } else {
                        if (options.build) {
                            if ($scope.server.okToBuild) {
                                buildServer();
                            } else {
                                hideLoading();
                                AlertService.showAlert({
                                    type: 'danger',
                                    title: 'Error',
                                    message: 'Not all fields have been filled out. Please enter data for all fields, press Save and then press Build.'
                                });
                            }
                        } else {
                            hideLoading();
                        }
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/saveServer'
                    });
                });
        }

        // ----------------------------------------------------------------------------------------------------
        // Build functions
        // ----------------------------------------------------------------------------------------------------

        function checkForExistingDNSEntry(callback) {
            NeuMaticService.apiGet('ip/hostInDNS/' + $scope.server.name,
                function(json) {
                    if (json.ipAddress) {
                        if (json.ipAddress !== $scope.server.vlan.ipAddress) {
                            $scope.modal = {
                                title: 'IP Address in Use',
                                message: $scope.server.name + ' is tied to IP address ' + json.ipAddress + ' in DNS. Do you want to use this address?',
                                yesCallback: function() {
                                    $scope.server.vlan.ipAddress = json.ipAddress;
                                    if (callback) callback();
                                },
                                noCallback: function() {
                                    if (callback) callback();
                                }
                            };
                            //noinspection JSUnresolvedFunction
                            $('#promptModal').modal('show');
                        }
                        if (callback) callback();
                    } else {
                        $scope.getNextAvailableIPAddress();
                        if (callback) callback();
                    }
                }
            );
        }

        function checkForExistingCMDBEntry() {
            NeuMaticService.apiGet('cmdb/getServerByName/' + $scope.server.name,
                function(json) {
                    if (json.server.name) {
                        if (json.server.businessServices !== $scope.server.businessService.name
                            || json.server.subsystemList !== $scope.server.subsystem.name
                            || json.server.environment !== $scope.server.cmdbEnvironment
                            || json.server.location !== $scope.server.location.name) {
                            /** @namespace json.server.businessServices */
                            /** @namespace json.server.businessServicesIds */
                            /** @namespace json.server.subsystemList */
                            /** @namespace json.server.subsystemListIds */
                            /** @namespace json.server.environment */
                            /** @namespace json.server.location */
                            $scope.modal = {
                                title: 'Server found in CMDB',
                                message: 'The following was found in the CMDB for ' + $scope.server.name + ':<br>' +
                                '<br>' +
                                'Business Service: ' + json.server.businessServices + '<br>' +
                                'Subsystem: ' + json.server.subsystemList + '<br>' +
                                'Environment: ' + json.server.environment + '<br>' +
                                'Location: ' + json.server.location + '<br>' +
                                '<br>' +
                                'Do you want to use these values?',
                                yesCallback: function() {
                                    $scope.server.businessService = {
                                        name: json.server.businessServices,
                                        sysId: json.server.businessServicesIds
                                    };
                                    /** @namespace json.server.subsystemListId */
                                    $scope.server.subsystem = {
                                        name: json.server.subsystemList,
                                        sysId: json.server.subsystemListId
                                    };
                                    $scope.server.cmdbEnvironment = json.server.environment;
                                    $scope.server.location = {
                                        name: json.server.location,
                                        sysId: json.server.locationId
                                    };
                                    getSubsystems();
                                }
                            };
                            //noinspection JSUnresolvedFunction
                            $('#promptModal').modal('show');
                        }
                    }
                }
            );
        }

        function buildServer() {
            // if this is a pool server we need to specify so that the build knows in selfServiceController
            $scope.server.poolServer = $scope.server.name.search(/stlabvnode/) !== -1;
            NeuMaticService.setServer($scope.server);
            window.location.href = '#/selfService/selfService/build';
        }

        // ----------------------------------------------------------------------------------------------------
        // Delete methods
        // ----------------------------------------------------------------------------------------------------

        /**
         * Called from delete server request to see if config should be removed as well
         *
         */
        function promptDeleteConfig() {
            deleteConfig = false;
            $scope.modal = {
                title: 'Confirm Delete',
                message: 'Do you want to delete the saved configuration as well?',
                showCancelButton: true,
                yesCallback: function() {
                    deleteConfig = true;
                    if (!$scope.server.serverPoolId) {
                        promptDeleteCmdb();
                    } else {
                        deleteCmdb = true;
                        deleteCobblerProfile();
                    }
                },
                noCallback: function() {
                    deleteConfig = false;
                    if (!$scope.server.serverPoolId) {
                        promptDeleteCmdb();
                    } else {
                        deleteCmdb = true;
                        deleteCobblerProfile();
                    }
                },
                cancelCallback: function() {
                    // TODO: show confirm splash here
                }
            };
            setTimeout(function() {
                $('#promptModal').modal('show');
            }, 1000);
        }

        /**
         * Called from delete config request to see if the CMDB entry should be deleted
         *
         */
        function promptDeleteCmdb() {
            $scope.modal = {
                title: 'Confirm Delete',
                message: 'Do you want to delete the CMDB entry?',
                showCancelButton: true,
                yesCallback: function() {
                    deleteCmdb = true;
                    deleteCobblerProfile();
                },
                noCallback: function() {
                    deleteCmdb = false;
                    deleteCobblerProfile();
                },
                cancelCallback: function() {
                    // TODO: show confirm splash here
                }
            };
            setTimeout(function() {
                $('#promptModal').modal('show');
            }, 1000);
        }

        /**
         * Called from deleteVM() to delete the cobbler profile
         *
         */
        function deleteCobblerProfile() {
            $scope.statusText = "Deleting Cobbler profile...";
            NeuMaticService.deleteCobblerProfile($scope.server.id,
                deleteFromChef()
            );
        }

        /**
         * Called from deleteFromLdap() to delete the Chef node and client
         *
         */
        function deleteFromChef() {
            $scope.statusText = "Deleting from Chef...";
            NeuMaticService.deleteChefNode($scope.server.name, $scope.server.chefServer,
                NeuMaticService.deleteChefClient($scope.server.name, $scope.server.chefServer,
                    deleteFromLdap()
                )
            )
        }

        /**
         * Called from deleteCobblerProfile() to delete the LDAP entry
         *
         */
        function deleteFromLdap() {
            $scope.statusText = "Deleting from LDAP...";
            NeuMaticService.deleteLdapHost($scope.server.id,
                function() {
                    deleteVMwareVM();
                })
        }

        /**
         * Called from deleteFromLdap() to delete the VMware VM
         *
         */
        function deleteVMwareVM() {
            $scope.statusText = 'Deleting VMware VM';
            NeuMaticService.deleteVMwareVM($scope.server.id,
                function() {
                    if (!$scope.server.serverPoolId) {
                        deleteFromDNS();
                    } else {
                        if (deleteCmdb) {
                            deleteFromCmdb();
                        } else {
                            if (deleteConfig) {
                                releaseToServerPool();
                            } else {
                                hideLoading();
                                updateStatus('New', ' ');
                                window.location.href = "/#/servers/current/0";
                            }
                        }
                    }
                })
        }

        /**
         * Called from deleteFromChef() to delete the DNS entry
         *
         */
        function deleteFromDNS() {
            // Do not remove from DNS if this is a server from the pool
            $scope.statusText = "Deleting from DNS...";
            NeuMaticService.deleteFromDNS($scope.server.id,
                function() {
                    if (deleteCmdb) {
                        deleteFromCmdb();
                    } else {
                        if (deleteConfig) {
                            if (!$scope.server.serverPoolId) {
                                deleteConfiguration();
                            } else {
                                releaseToServerPool();
                            }
                        } else {
                            hideLoading();
                            updateStatus('New', ' ');
                            window.location.href = "/#/servers/current";
                        }
                    }
                })
        }

        /**
         * Called from deleteFromDns() if deleteCmdb flag is true to delete from CDMB
         *
         */
        function deleteFromCmdb() {
            $scope.statusText = "Deleting from CMDB...";
            NeuMaticService.deleteFromCmdb($scope.server.id,
                function() {
                    if (deleteConfig) {
                        if (!$scope.server.serverPoolId) {
                            deleteConfiguration();
                        } else {
                            releaseToServerPool();
                        }
                    } else {
                        hideLoading();
                        updateStatus('New', ' ');
                        window.location.href = "/#/servers/current";
                    }
                })
        }

        /**
         * Called from deleteFromDns() or deleteFromCmdb() if deleteConfig flag is true
         * to delete the VM from the server pool
         *
         */
        function releaseToServerPool() {
            $scope.statusText = "Releasing back to pool...";
            NeuMaticService.releaseBackToPool($scope.server.id,
                function() {
                    hideLoading();
                    window.location.href = "/#/servers/current/0";
                })
        }

        /**
         * Delete this server's configuration
         */
        function deleteConfiguration() {
            $scope.statusText = "Deleting config...";
            NeuMaticService.deleteConfiguration($scope.server.id,
                function() {
                    hideLoading();
                    window.location.href = "/#/servers/current/0";
                })
        }

        // ------------------------------------------------------------------------------------------------------------
        // Lease Methods
        // ------------------------------------------------------------------------------------------------------------

        /**
         * Call the extendLease backend function and retrieve the latest values
         */
        function extendLease() {
            $scope.statusText = "Extending lease...";
            //noinspection JSValidateTypes
            $http.get('/neumatic/extendLease/' + $scope.server.id)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/extendLease/' + $scope.server.id
                        });
                    } else {
                        /** @namespace json.lease */
                        /** @namespace lease.daysToLeaseEnd */
                        /** @namespace lease.leaseAlertClass */
                        /** @namespace lease.leaseDuration */
                        /** @namespace lease.extensionInDays */
                        /** @namespace lease.numExtensionsAllowed */
                        /** @namespace lease.numTimesExtended */
                        /** @namespace lease.numExtensionsRemaining */
                        var lease = json.lease;
                        hideLoading();
                        displayStatus("Lease extended by " + $scope.server.extensionInDays, " ");

                        $scope.server.daysToLeaseEnd = lease.daysToLeaseEnd;
                        $scope.server.leaseAlertClass = lease.leaseAlertClass;
                        $scope.server.leaseDuration = lease.leaseDuration;
                        $scope.server.extenionInDays = lease.extensionInDays;
                        $scope.server.numExtensionsAllowed = lease.numExtensionsAllowed;
                        $scope.server.numTimesExtended = lease.numTimesExtended;
                        $scope.server.numExtensionsRemaining = lease.numExtensionsRemaining;
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/extendLease/' + $scope.server.id
                    });
                });
        }

        // ------------------------------------------------------------------------------------------------------------
        // General Purpose Methods
        // ------------------------------------------------------------------------------------------------------------

        /**
         * Show the page mask and set build flag to true (enables spinning gif)
         */
        function showLoading() {
            $('#page-mask').show();
            $scope.buildRunning = true;
        }

        /**
         * Remove the page mask and set build flag to false (disables spinning gif)
         */
        function hideLoading() {
            $('#page-mask').hide();
            $scope.buildRunning = false;
        }


        /**
         * Show the initial status text for a couple of seconds and then change to final status
         *
         * @param initialStatus
         * @param finalStatus
         */
        function displayStatus(initialStatus, finalStatus) {
            // set the initial status
            $scope.statusText = initialStatus;

            // call $timeout to wait for a bit and then change to final status
            // $timeout returns a promise that is processed next
            var promise = $timeout(function() {
                return finalStatus;
            }, 2000);

            // process the promise. It is in the format success, error & notify (similar to try, catch and throw)
            promise.then(
                function(statusText) {
                    // success
                    $scope.statusText = statusText;
                },
                function() {
                    // error
                    $scope.statusText = '';
                },
                function(update) {
                    // notify
                }
            );
        }

        /**
         * Given a server, status (Building, Built, Failed, etc) and statusText values, update
         * the entry in the server table
         *
         * @param status
         * @param statusText
         */
        function updateStatus(status, statusText) {
            $scope.statusText = statusText;
            var data = $.param({
                serverId: $scope.server.id,
                status: status,
                statusText: statusText
            });

            //noinspection CommaExpressionJS
            $http({
                method: 'POST',
                url: '/neumatic/updateStatus',
                data: data,
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            }).then(
                function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/updateStatus'
                        });
                    }
                }),
                function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/updateStatus'
                    });
                };
        }

        /**
         *
         * @param cname
         * @returns {string}
         */
        function getCookie(cname) {
            var name = cname + "=";
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i].trim();
                if (c.indexOf(name) == 0)
                    return c.substring(name.length, c.length);
            }
            return "";
        }

        // ------------------------------------------------------------------------------------------------------------
        // Page Load methods
        // ------------------------------------------------------------------------------------------------------------

        /**
         * Get the next available pool server
         * Called on page load if user is not admin or adminOn is false and when Copy button is pressed
         *
         * @param copy
         */
        function getNextPoolServer(copy) {
            //noinspection JSValidateTypes
            $http.get('/neumatic/getNextFreePoolServer')
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        // check if the pool is empty or the user is trying to create more than max per user
                        // if so, then display a message and then return to the servers page
                        if (typeof json.code !== "undefined") {
                            AlertService.showAlert({
                                type: 'danger',
                                title: 'Error: ' + json.code,
                                message: json.message,
                                callback: function() {
                                    window.location.href = "/#/servers/current";
                                }
                            });
                            return;
                        } else {
                            AlertService.ajaxAlert({
                                json: json,
                                apiUrl: '/neumatic/getNextFreePoolServer'
                            });
                            return;
                        }
                    }

                    $scope.server.name = json.server.name;
                    $scope.server.vlan.ipAddress = json.server.ipAddress;
                    $scope.server.serverPoolId = json.server.serverPoolId;

                    // if we're not doing a copy then get all the drop downs
                    if (!copy) {
                        getSubsystems();
                        getCmdbEnvironments();
                        getDataCenters();
                        getVMSizes();
                        getComputeClusters();
                        getCCRNetworks();
                        getVmwareTemplates();
                        $scope.useTemplate = false;
                        // getCobblerServers();
                        getChefServers();
                    }

                    // this is a pool server with all fields populated so we're ok to build
                    $scope.server.okToBuild = false;
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getNextFreePoolServer'
                    });
                });
        }

        /**
         * Get CMDB info from cookies if available
         */
        function getCmdbInfoFromCookies() {
            if (getCookie('businessServiceSysId') && getCookie('businessServiceName')) {
                $scope.server.businessService.sysId = getCookie('businessServiceSysId');
                $scope.server.businessService.name = getCookie('businessServiceName');
            }

            if (getCookie('subsystemSysId') && getCookie('subsystemName')) {
                $scope.server.subsystem.sysId = getCookie('subsystemSysId');
                $scope.server.subsystem.name = getCookie('subsystemName');
            }
            if (getCookie('cmdbEnvironment')) {
                $scope.server.cmdbEnvironment = getCookie('cmdbEnvironment');
            }
            if (getCookie('description')) {
                $scope.server.description = getCookie('description');
            }
            if (getCookie('ldapUserGroups')) {
                $scope.server.ldapUserGroups = JSON.parse(getCookie('ldapUserGroups'));
            }
            if (getCookie('ldapHostGroups')) {
                $scope.server.ldapHostGroups = JSON.parse(getCookie('ldapHostGroups'));
            }
        }

        /**
         * Loads all the drop down menus from various and sundry sources
         */
        function getMenuData() {
            getCmdbEnvironments();
            getVMSizes();
            getDataCenters();
            getVmwareTemplates();
            getComputeClusters();
            getCCRNetworks();
            getDistSwitches();
            getCobblerServers();
            getChefServers();
        }


        // ------------------------------------------------------------------------------------------------------------
        // Page Load
        // ------------------------------------------------------------------------------------------------------------

        // If we're retrieving a server then the id will not be 0
        if ($scope.server.id !== 0) {
            // we've read this from the database, so disregard any stored values in the node service
            $scope.nodeService.setDefined(false);

            // get the server data from the database
            //noinspection JSValidateTypes
            $http.get('/neumatic/getServer/' + $scope.server.id)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getServer/' + $scope.server.id
                        });
                        return;
                    }
                    $scope.server = json.server;
                    $scope.server.owner = json.owner;

                    if ($scope.server.ldapUserGroups === null || typeof $scope.server.ldapUserGroups === "undefined") {
                        $scope.server.ldapUserGroups = [];
                    }
                    if ($scope.server.ldapHostGroups === null || typeof $scope.server.ldapHostGroups === "undefined") {
                        $scope.server.ldapHostGroups = [];
                    }

                    $scope.server.businessService = {
                        sysId: json.server.businessServiceId,
                        name: json.server.businessServiceName
                    };
                    $scope.server.subsystem = {
                        sysId: json.server.subsystemId,
                        name: json.server.subsystemName
                    };

                    if (json.server.location === 'Sterling') {
                        $scope.server.location = $scope.locations[0];
                    } else if (json.server.location === 'Charlotte') {
                        $scope.server.location = $scope.locations[1];
                    } else if (json.server.location === 'Denver') {
                        $scope.server.location = $scope.locations[2];
                    } else {
                        $scope.server.location = {
                            name: json.server.location,
                            sysId: json.server.locationId
                        };
                        if ($scope.server.serverType !== 'remote') {
                            for (var i = 0; i < $scope.locations.length; i++) {
                                if ($scope.locations[i].name === $scope.server.location.name) {
                                    $scope.server.location = $scope.locations[i];
                                }
                            }
                        }
                    }

                    $scope.server.vlan = {
                        vlanId: json.server.vlanId,
                        name: json.server.vlanName,
                        network: json.server.network,
                        subnetMask: json.server.subnetMask,
                        gateway: json.server.gateway,
                        ipAddress: json.server.ipAddress,
                        macAddress: json.server.macAddress
                    };
                    $scope.server.cobblerDistro = {
                        name: json.server.cobblerDistro,
                        warn: false
                    };

                    if (json.server.cobblerMetadata === null) {
                        json.server.cobblerMetadata = '';
                    }
                    $scope.server.isXen = json.server.cobblerMetadata.search(/server_type=xendom0/) !== -1;

                    // vm data. populate if found, otherwise empty
                    $scope.server.dataCenter = {
                        name: json.server.dcName || '',
                        uid: json.server.dcUid || '',
                        vSphereSite: json.server.vSphereSite || '',
                        vSphereServer: json.server.vSphereServer || ''
                    };
                    $scope.server.ccr = {
                        name: json.server.ccrName || '',
                        uid: json.server.ccrUid || ''
                    };
                    $scope.server.vmSize = {
                        name: json.server.vmSize || '',
                        numCPUs: json.server.numCPUs || '',
                        memoryGB: json.server.memoryGB || '',
                        luns: json.server.luns || []
                    };

                    $scope.server.template = {
                        name: 'Centos-6.5-Template-Latest' || '',
                        id: json.server.templateId || ''

                    };
                    $scope.template = $scope.server.template;
                    // standalone data
                    $scope.server.standalone = {
                        id: json.server.standaloneId || 0,
                        iLo: json.server.iLo || '',
                        iso: json.server.iso || ''
                    };

                    // blade data. populate if found otherwise empty
                    $scope.server.chassis = {
                        id: json.server.chassisId || 0,
                        name: json.server.chassisName || ''
                    };
                    $scope.server.blade = {
                        id: json.server.bladeId || 0,
                        name: json.server.bladeName || '',
                        slot: json.server.bladeSlot || ''
                    };

                    // set ilo name if not already set
                    if ($scope.server.serverType === "standalone" &&
                        (typeof $scope.server.standalone.iLo === 'undefined' || $scope.server.standalone.iLo === '')) {
                        var fields = $scope.server.name.split('.');
                        fields[0] += '-con';
                        $scope.server.standalone.iLo = fields.join('.');
                    }

                    saveAttrs.subsystem = $scope.server.subsystem;
                    $scope.server.subsystem = $scope.subsystems[0];
                    getSubsystems();

                    saveAttrs.cmdbEnvironment = $scope.server.cmdbEnvironment;
                    $scope.server.cmdbEnvironment = "Loading...";
                    getCmdbEnvironments();

                    saveAttrs.cobblerServer = $scope.server.cobblerServer;
                    $scope.server.cobblerServer = 'Loading...';
                    saveAttrs.cobblerDistro = $scope.server.cobblerDistro;
                    $scope.server.cobblerDistro = $scope.cobblerDistros[0];
                    saveAttrs.cobblerKickstart = $scope.server.cobblerKickstart;
                    $scope.server.cobblerKickstart = $scope.cobblerKickstarts[0];
                    getCobblerServers();

                    // allows us to put "Loading..." in the input field while the data is retrieved
                    saveAttrs.chefRole = $scope.server.chefRole;
                    $scope.server.chefRole = "Loading...";

                    saveAttrs.chefEnv = $scope.server.chefEnv;
                    $scope.server.chefEnv = "Loading...";

                    saveAttrs.chefServer = $scope.server.chefServer;
                    $scope.server.chefServer = $scope.chefServers[0].name;
                    getChefServers();

                    if ($scope.server.serverType === 'vmware' || $scope.server.serverType === 'vmwareCobbler') {
                        saveAttrs.dataCenter = $scope.server.dataCenter;
                        $scope.server.dataCenter = $scope.dataCenters[0];
                        saveAttrs.ccr = $scope.server.ccr;
                        $scope.server.ccr = $scope.computeClusters[0];
                        saveAttrs.vlan = $scope.server.vlan;
                        $scope.server.vlan = $scope.vlans[0];
                        getDataCenters();
                    	if ($scope.server.serverType === 'vmware') {
				getVmwareTemplates();
                    	}
		        getVMSizes();
                    }
                    else if ($scope.server.serverType === 'blade') {
                        saveAttrs.distSwitch = $scope.server.distSwitch;
                        $scope.server.distSwitch = $scope.distSwitches[0];
                        saveAttrs.vlan = $scope.server.vlan;
                        $scope.server.vlan = $scope.vlans[0];
                        saveAttrs.chassis = $scope.server.chassis;
                        $scope.server.chassis = $scope.chassises[0];
                        saveAttrs.blade = $scope.server.blade;
                        $scope.server.blade = $scope.blades[0];
                        getDistSwitches();
                    }
                    else if ($scope.server.serverType === 'standalone') {
                        saveAttrs.distSwitch = $scope.server.distSwitch;
                        $scope.server.distSwitch = $scope.distSwitches[0];

                        saveAttrs.vlan = $scope.server.vlan;
                        $scope.server.vlan = $scope.vlans[0];

                        getDistSwitches();
                        $scope.getStandaloneMacAddresses();
                    }
                    else if ($scope.server.serverType === 'remote') {
                        $scope.getStandaloneMacAddresses();
                    }

                    if ($scope.server.serverType === 'remote') {
                        saveAttrs.iso = $scope.server.standalone.iso;
                        $scope.server.standalone.iso = $scope.isos[0];
                        getISOs();
                    }

                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getServer/' + $scope.server.id
                    });
                });
        } else {
            // this is a new server. if called from the VMWare page, then nodeService will be defined

            // nav tag to highlight the menu
            $scope.nav = {neuServer: true};

            if ($scope.nodeService.isDefined()) {
                var vmNode = $scope.nodeService.getNode();

                // update for vmware vm
                $scope.server.serverType = "vmware";
                // TODO: cobbler server should be selected by an index like prod, lab, etc
                if (vmNode.dcName === "Sterling") {
                    $scope.server.cobblerServer = "sonic.va.neustar.com";
                } else {
                    $scope.server.cobblerServer = "chopvprcblr01.nc.neustar.com";
                }

                $scope.server.id = 0;
                $scope.server.serverType = "vmware";
                $scope.server.dataCenter = {
                    vSphereSite: vmNode.vSphereSite,
                    vSphereServer: vmNode.vSphereServer,
                    name: vmNode.dcName,
                    uid: vmNode.dcUid
                };
                $scope.server.ccr = {
                    name: vmNode.ccrName,
                    uid: vmNode.ccrUid,
                    rpUid: vmNode.rpUid
                };

                $scope.server.businessService = {
                    sysId: '',
                    name: ''
                };
                $scope.server.subsystem = {
                    sysId: '',
                    name: ''
                };
                $scope.server.cmdbEnvironment = '';

                getSubsystems();
                getCmdbEnvironments();
                getDataCenters();
                getVmwareTemplates();
                getVMSizes();
                getCobblerServers();
                getChefServers();
                getComputeClusters();
                getCCRNetworks();
            }

            // we're not an admin or admin is off so get a pool server
            else if ($scope.authedUserService.authedUser.userType !== 'Admin' || ($scope.authedUserService.authedUser.userType === 'Admin' && !$scope.authedUserService.authedUser.adminOn)) {
                // getting data from the lab pool, so disregard any stored values in the node service
                $scope.nodeService.setDefined(false);
                // new server request by user so get the server data from the database
                getCmdbInfoFromCookies();
                getNextPoolServer(false);
            }

            // ok we're priviledged to just clear out all the values or use defaults
            else {
                // brand new server, don't use the node service
                $scope.nodeService.setDefined(false);
                // new server request by admin user with admin ON
                getCmdbInfoFromCookies();
                if ($scope.server.subsystem.id) getSubsystems();
                getMenuData();
            }
        }
    });
