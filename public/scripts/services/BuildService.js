angular.module('NeuMatic')
    .factory('BuildService', function($q, $http, AlertService) {

        function hidePageMask() {
            $('#page-mask').hide();
        }

        function apiError(result, apiUrl) {
            hidePageMask();
            AlertService.ajaxAlert({
                json: result.data,
                result: result,
                apiUrl: apiUrl,
                callback: function() {
                    window.location.href = "/#/servers/current/0";
                }
            });
        }

        function updateStatus(id, status, statusText) {
            var apiUrl = '/neumatic/updateStatus';
            var data = $.param({
                serverId: id,
                status: status,
                statusText: statusText
            });

            //noinspection CommaExpressionJS
            return $http({
                method: 'POST',
                url: '/neumatic/updateStatus',
                data: data,
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            })
                .then(
                function(result) {
                    if (!result.data.success) {
                        apiError(result, apiUrl);
                    }
                },
                function(result) {
                    apiError(result, apiUrl);
                }
            )
        }

        function httpGet(server, apiUrl) {
            return $http.get(apiUrl)
                .then(
                function(result) {
                    if (!result.data.success) {
                        updateStatus(server.id, 'Failed', 'Failed');
                        apiError(result, apiUrl);
                        return $q.reject();
                    }
                    return server;
                },
                function(result) {
                    updateStatus(server.id, 'Failed', 'Failed');
                    apiError(result, apiUrl);
                    return $q.reject();
                }
            )
        }

        return {
            updateStatus: function(id, status, statusText) {
                var apiUrl = '/neumatic/updateStatus';
                var data = $.param({
                    serverId: id,
                    status: status,
                    statusText: statusText
                });

                //noinspection CommaExpressionJS
                return $http({
                    method: 'POST',
                    url: '/neumatic/updateStatus',
                    data: data,
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
                })
                    .then(
                    function(result) {
                        if (!result.data.success) {
                            apiError(result, apiUrl);
                        }
                    },
                    function(result) {
                        apiError(result, apiUrl);
                    }
                )
            },

            saveServer: function(server) {
                console.log("BuildService::saveServer()");

                var ldapUserGroups = [];
                server.ldapUserGroups.forEach(function(group) {
                    ldapUserGroups.push(group.name);
                });
                var ldapHostGroups = [];
                server.ldapHostGroups.forEach(function(group) {
                    ldapHostGroups.push(group.name);
                });

                if (typeof $scope !== 'undefined') {

                    if (typeof $scope.templateId === 'undefined') {
                        server.template.id = '';
                        server.template.name = '';
                    } else {
                        server.template.id = $scope.template.id;
                        server.template.name = $scope.template.name;
                    }
                }
                if (typeof server.template == 'undefined') {
                    server.template = [];
                    server.template.id = "";
                    server.template.name = "";
                }

                var apiUrl = '/neumatic/saveServer',
                    data = $.param({
                        name: server.name,
                        id: server.id,
                        serverType: server.serverType,
                        serverPoolId: server.serverPoolId,

                        businessServiceId: server.businessService.sysId,
                        businessServiceName: server.businessService.name,
                        subsystemId: server.subsystem.sysId,
                        subsystemName: server.subsystem.name,
                        cmdbEnvironment: server.cmdbEnvironment,

                        description: server.description,

                        location: server.location,
                        distSwitch: server.distSwitch,

                        ldapUserGroups: ldapUserGroups,
                        ldapHostGroups: ldapHostGroups,

                        chassisName: '',
                        chassisId: '',
                        bladeName: '',
                        bladeId: '',
                        bladeSlot: '',

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
                        rpUid: server.rpUid,
                        templateId: server.template.id,
                        templateName: server.template.name,

                        vlanName: server.vlan.name,
                        vlanId: server.vlan.id,
                        network: server.vlan.network,
                        subnetMask: server.vlan.subnetMask,
                        gateway: server.vlan.gateway,
                        ipAddress: server.vlan.ipAddress,
                        macAddress: server.vlan.macAddress,

                        cobblerServer: server.cobblerServer,
                        cobblerDistro: server.cobblerDistro.name,
                        cobblerKickstart: server.cobblerKickstart,

                        chefServer: server.chefServer,
                        chefRole: server.chefRole,
                        chefEnv: server.chefEnv
                    });

                // call a POST to the neumatic controller passing all the server values
                //noinspection JSValidateTypes
                return $http({
                    method: 'POST',
                    url: apiUrl,
                    data: data,
                    // content type is required here so that the data is formatted correctly
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
                })
                    .then(
                    function(result) {
                        if (!result.data.success) {
                            apiError(result, apiUrl);
                            return $q.reject();
                        }
                        server.id = result.data.server.id;
                        return server;
                    },
                    function(result) {
                        apiError(result, apiUrl);
                        return $q.reject();
                    }
                )
            },

            getServer: function(server) {
                var apiUrl = '/neumatic/getServer/' + server.id;

                console.log("BuildService::getServer(id=" + server.id + ")");
                // get the server data from the database
                //noinspection JSValidateTypes
                return $http.get(apiUrl)
                    .then(
                    function(result) {
                        var json = result.data;
                        if (!result.data.success) {
                            apiError(result, apiUrl);
                            return $q.reject();
                        }
                        server = json.server;
                        server.owner = json.owner;

                        server.businessService = {
                            sysId: json.server.businessServiceId,
                            name: json.server.businessServiceName
                        };
                        server.subsystem = {
                            sysId: json.server.subsystemId,
                            name: json.server.subsystemName
                        };

                        server.vlan = {
                            id: json.server.vlanId,
                            name: json.server.vlanName,
                            network: json.server.network,
                            subnetMask: json.server.subnetMask,
                            gateway: json.server.gateway,
                            ipAddress: json.server.ipAddress,
                            macAddress: json.server.macAddress
                        };
                        server.cobblerDistro = {
                            name: json.server.cobblerDistro,
                            warn: false
                        };

                        // vm data. populate if found, otherwise empty
                        server.dataCenter = {
                            name: json.server.dcName || '',
                            uid: json.server.dcUid || '',
                            vSphereSite: json.server.vSphereSite || '',
                            vSphereServer: json.server.vSphereServer || ''
                        };
                        server.ccr = {
                            name: json.server.ccrName || '',
                            uid: json.server.ccrUid || ''
                        };
                        server.vmSize = {
                            name: json.server.vmSize || '',
                            numCPUs: json.server.numCPUs || '',
                            memoryGB: json.server.memoryGB || '',
                            luns: json.server.luns || []
                        };

                        server.templateId = json.server.templateId;

                        // standalone data
                        server.standalone = {
                            id: json.server.standaloneId || 0
                        };

                        // blade data. populate if found otherwise empty
                        server.chassis = {
                            id: json.server.chassisId || 0,
                            name: json.server.chassisName || ''
                        };
                        server.blade = {
                            id: json.server.bladeId || 0,
                            name: json.server.bladeName || '',
                            slot: json.server.bladeSlot || ''
                        };
                        return server;
                    },
                    function(result) {
                        apiError(result, apiUrl);
                        return $q.reject();
                    }
                )
            },

            lookupCmdbCi: function(server) {
                var apiUrl = '/cmdb/getServerByName/' + server.name;
                console.log("BuildService::lookupCmdbCi()");
                return $http.get(apiUrl)
                    .then(
                    function(result) {
                        if (!result.data.success) {
                            updateStatus(server.id, 'Failed', 'Failed');
                            apiError(result, apiUrl);
                            return $q.reject();
                        }
                        if (result.data.server.sysId !== null) {
                            return server;
                        }
                        updateStatus(server.id, 'Failed', 'Failed');
                        hidePageMask();
                        AlertService.ajaxAlert({
                            json: {
                                message: "Could not find " + server.name
                                    + " in the ServiceNow CMDB. Please go to ServiceNow and insure that there is a CI named "
                                    + server.name
                                    + ". Then start the build again."
                            },
                            callback: function() {
                                window.location.href = "/#/server/" + server.id;
                            }
                        });
                        return $q.reject();
                    },
                    function(result) {
                        updateStatus(server.id, 'Failed', 'Failed');
                        apiError(result, apiUrl);
                        return $q.reject();
                    }
                )
            },

            resetStartTime: function(server) {
                console.log("BuildService::resetStartTime()");
                return httpGet(server, '/neumatic/resetStartTime/' + server.id);
            },

            getNextPoolServer: function(server) {
                console.log("BuildService::getNextPoolServer()");
                var apiUrl = '/neumatic/getNextFreePoolServer';
                return $http.get(apiUrl)
                    .then(
                    function(result) {
                        var json = result.data;
                        if (!result.data.success) {
                            apiError(result, apiUrl);
                            return $q.reject();
                        }
                        //server = JSON.parse(JSON.stringify(json.server));
                        server.serverPoolId = json.server.serverPoolId;
                        server.name = json.server.name;
                        server.serverType = json.server.serverType;
                        server.vlan.ipAddress = json.server.ipAddress;

                        return server;
                    },
                    function(result) {
                        apiError(result, apiUrl);
                        return $q.reject();
                    }
                )
            },

            makeCoffee: function(server) {
                console.log("BuildService::makeCoffee()");
                this.updateStatus(server.id, 'Building', 'Making coffee...');

                var deferred = $q.defer();
                setTimeout(function() {
                    deferred.resolve(server);
                }, 2000);
                return deferred.promise;
            },

            checkIfVMwareVMExists: function(server) {
                console.log("BuildService::checkIfVMwareVMExists()");
                var apiUrl = '/vmware/checkIfVMExists/' + server.id;
                this.updateStatus(server.id, 'Building', 'Checking for existing VM...');
                return $http.get(apiUrl)
                    .then(
                    function(result) {
                        if (!result.data.success) {
                            apiError(result, apiUrl);
                            return $q.reject();
                        }
                        server.vmExists = result.data.vmExists;
                        return server;
                    },
                    function(result) {
                        apiError(result, apiUrl);
                        return $q.reject();
                    }
                )
            },

            deleteVMwareVM: function(server) {
                console.log("BuildService::deleteVMwareVM()");
                this.updateStatus(server.id, 'Building', 'Deleting VM...');
                return httpGet(server, '/vmware/deleteVM/' + server.id);
            },

            createVMWareVM: function(server) {
                console.log("BuildService::createVMWareVM()");
                this.updateStatus(server.id, 'Building', 'Creating VMWare VM...');
                return httpGet(server, 'vmware/createVM/' + server.id);
            },
			createVMWareCobblerVM: function(server) {
                console.log("BuildService::createVMWareCobblerVM()");
                this.updateStatus(server.id, 'Building', 'Creating VMWare VM...');
                return httpGet(server, 'vmware/createCobblerVM/' + server.id);
            },
            deleteChefNode: function(server, hostname, chefServer) {
                console.log("BuildService::deleteChefNode()");
                return httpGet(server, '/chef/deleteNode/' + hostname + '?chef_server=' + chefServer);
            },

            deleteChefClient: function(server, hostname, chefServer) {
                console.log("BuildService::deleteChefClient()");
                return httpGet(server, '/chef/deleteClient/' + hostname + '?chef_server=' + chefServer);
            },

            deleteChef: function(server, hostname, chefServer) {
                console.log("BuildService::deleteChef()");
                this.updateStatus(server.id, 'Building', 'Deleting from Chef...');
                return $q.all([
                    this.deleteChefNode(server, hostname, chefServer),
                    this.deleteChefClient(server, hostname, chefServer)
                ])
                    .then(function() {
                        return server;
                    });
            },

            /**
             * Standalone servers only
             * Connects to the iLO (<servername>-con.*) and obtains the MAC address of the server
             */
            getMacAddress: function(server) {
                console.log("BuildService::getMacAddress()");
                this.updateStatus(server.id, 'Building', 'Getting MAC address...');
                return httpGet(server, '/hardware/getMacAddress/' + server.id);
            },

            getBladeMacAddress: function(server) {
                console.log("BuildService::getBladeMacAddress()");
                var apiUrl = '/chassis/getMacAddress/' + server.name + '/' + server.chassis.id;
                this.updateStatus(server.id, 'Building', 'Getting MAC address...');
                return $http.get(apiUrl)
                    .then(
                    function(result) {
                        if (!result.data.success) {
                            apiError(result, apiUrl);
                            return $q.reject();
                        }
                        server.vlan.macAddress = result.data.macAddress;
                        return server;
                    },
                    function(result) {
                        apiError(result, apiUrl);
                        return $q.reject();
                    }
                )
            },

            createCmdbCi: function(server) {
                console.log("BuildService::createCmdbCi()");
                this.updateStatus(server.id, 'Building', 'Creating CMDB CI...');
                return httpGet(server, 'cmdb/createServer/' + server.id);
            },

            updateLdap: function(server) {
                console.log("BuildService::updateLdap()");
                this.updateStatus(server.id, 'Building', 'Adding host to LDAP...');
                return httpGet(server, 'ldap/createHost/' + server.id);
            },

            deleteLdapHost: function(server, hostname) {
                console.log("BuildService::deleteLdapHost()");
                return httpGet(server, '/ldap/deleteHost/' + hostname);
            },

            updateDNS: function(server) {
                console.log("BuildService::updateDNS()");
                this.updateStatus(server.id, 'Building', 'Adding host to DNS...');
                return httpGet(server, 'ip/addToDNS/' + server.id);
            },

            deleteFromDNS: function(server) {
                console.log("BuildService::deleteFromDNS()");
                return httpGet(server, '/ip/deleteFromDNS/' + server.id);
            },

            createCobblerProfile: function(server) {
                console.log("BuildService::createCobblerProfile()");
                this.updateStatus(server.id, 'Building', 'Creating Cobbler profile...');
                return httpGet(server, '/cobbler/createSystem/' + server.id);
            },

            powerOnVM: function(server) {
                console.log("BuildService::powerOnVM()");
                this.updateStatus(server.id, 'Building', 'Powering on VM...');
                return httpGet(server, '/vmware/powerOnVM/' + server.id);
            },

            startCobblerWatcher: function(server) {
                console.log("BuildService::startCobblerWatcher()");
                this.updateStatus(server.id, 'Building', 'Starting Cobbler watcher...');
                return httpGet(server, "/cobbler/startWatcher/" + server.id);
            },
            startTemplateWatcher: function(server) {
                console.log("BuildService::startTemplateWatcher()");
                this.updateStatus(server.id, 'Building', 'Starting Template watcher...');
                return httpGet(server, "/vmware/startWatcher/" + server.id);
            },

            /**
             * Standalone systems only
             * Connects to the iLO, sets the boot order for network first and then resets the hardware
             */
            resetSystem: function(server) {
                console.log("BuildService::resetSystem()");
                var apiUrl;
                this.updateStatus(server.id, 'Building', 'Resetting system...');
                if (server.serverType === 'remote') {
                    apiUrl = '/hardware/resetRemoteSystem/' + server.id;
                } else {
                    apiUrl = '/hardware/resetSystem/' + server.id;
                }
                return httpGet(server, apiUrl);
            },

            /**
             * Restart the blade by connecting to the chassis
             */
            restartBlade: function(server) {
                console.log("BuildService::restartBlade()");
                this.updateStatus(server.id, 'Building', 'Resetting blade...');
                return httpGet(server, '/chassis/restartSystem/' + server.id);
            }

        };
    });

