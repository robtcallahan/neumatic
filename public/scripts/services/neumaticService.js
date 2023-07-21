angular.module('NeuMatic')
    .service('Browser', function($window) {

        return function() {
            var userAgent = $window.navigator.userAgent;
            var browsers = {chrome: /chrome/i, safari: /safari/i, firefox: /firefox/i, ie: /internet explorer|MSIE/i};

            for (var key in browsers) {
                if (browsers.hasOwnProperty(key) && browsers[key].test(userAgent)) {
                    return key;
                }
            }
            return 'unknown';
        }

    })

    .factory('NeuMaticService', function($q, $resource, $http, AlertService, AuthedUserService) {
        var scope = null,
            server = null,
            rebuild = false;

        function ajaxError(json, apiUrl) {
            AlertService.ajaxAlert({
                json: json,
                apiUrl: apiUrl
            });
        }

        function httpGet(url, onFailure) {
            onFailure = onFailure || function() {
            };

            var resource = $resource(url, {}, {query: {method: 'GET', isArray: false}}),
                deferred = $q.defer();
            resource.get(
                function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        onFailure(json, url);
                        deferred.reject();
                    } else {
                        deferred.resolve(json);
                    }
                },
                function(json) {
                    onFailure(json, url);
                    deferred.reject();
                });
            return deferred.promise;
        }

        function httpPost(url, data, onFailure) {
            onFailure = onFailure || function() {
            };

            var resource = $resource(url),
                deferred = $q.defer();
            resource.save({}, data,
                function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        onFailure(json, url);
                        deferred.reject();
                    } else {
                        deferred.resolve(json);
                    }
                },
                function(json) {
                    onFailure(json, url);
                    deferred.reject();
                });
            return deferred.promise;
        }

        return {
            setScope: function(ctrlScope) {
                scope = ctrlScope;
            },

            setServer: function(_server_) {
                // here we perform a deep copy of the server structure, instead of creating a reference
                server = JSON.parse(JSON.stringify(_server_));
            },

            getServer: function() {
                return server;
            },

            setRebuild: function(value) {
                rebuild = value;
            },

            getRebuild: function() {
                return rebuild;
            },

            ajaxError: function(json, apiUrl) {
                ajaxError(json, apiUrl);
            },

            apiGet: function(apiUrl, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            updateServer: function(serverId, property, value, onFailure) {
                onFailure = onFailure || ajaxError;
                var url = '/neumatic/updateServer/' + serverId,
                    resource = $resource(url, {}, {update: {method: 'POST'}}),
                    deferred = $q.defer(),
                    data = {
                        property: property,
                        value: value
                    };

                resource.update({}, data,
                    function(json) {
                        if (typeof json.success !== "undefined" && !json.success) {
                            onFailure(json, url);
                            deferred.reject();
                        } else {
                            deferred.resolve(json);
                        }
                    },
                    function(json) {
                        onFailure(json, url);
                        deferred.reject();
                    });
                return deferred.promise;
            },

            getBusServicesForTypeAhead: function(val) {
                return $http.get('/cmdb/getBusinessServicesBySubstring/' + val, {}).then(function(res) {
                    if (!res.data.success) {
                        return [];
                    } else {
                        return res.data.businessServices;
                    }
                });
            },

            getLocationsForTypeAhead: function(val) {
                return $http.get('/cmdb/getLocationsBySubstring/' + val, {}).then(function(res) {
                    return res.data.locations;
                });
            },

            getLdapUserGroupsForTypeAhead: function(val) {
                var results = [],
                    apiUrl = '/ldap/getUserGroups/' + AuthedUserService.authedUser.username,
                    groupsProp = "groups";

                if (AuthedUserService.authedUser.userType === 'Admin') {
                    apiUrl = '/ldap/getUsergroupList';
                    groupsProp = "usergroups";
                }

                return $http.get(apiUrl, {}).then(function(res) {
                    var groups = res.data[groupsProp];
                    groups.forEach(function(item) {
                        var re = new RegExp(val, "i");
                        if (item.search(re) !== -1) {
                            results.push(item);
                        }
                    });
                    return results;
                });
            },

            getLdapHostGroupsForTypeAhead: function(val) {
                var results = [];
                return $http.get('/ldap/getHostGroups', {}).then(function(res) {
                    res.data.hostGroups.forEach(function(item) {
                        var re = new RegExp(val, "i");
                        if (item.search(re) !== -1) {
                            results.push(item);
                        }
                    });
                    return results;
                });
            },

            getBusinessServices: function(onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cmdb/getBusinessServices';

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.businessServices);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getBusinessServicesBySubstring: function(subString, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cmdb/getBusinessServicesBySubstring/' + subString;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.businessServices);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getSubsystemsByBSId: function(bsId, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cmdb/getBusinessServiceSubsystems/' + bsId;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.subsystems);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getCmdbEnvironments: function(onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cmdb/getEnvironments';

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        var envs = [];
                        for (var i = 0; i < json.environments.length; i++) {
                            envs.push(json.environments[i].name);
                        }
                        onSuccess(envs);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getVMSizes: function(onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/neumatic/getVMSizes';

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.vmSizes);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getCobblerServers: function(onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cobbler/getServers';

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.servers);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getCobblerDistros: function(cobblerServer, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cobbler/getDistributions/' + cobblerServer;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        /** @namespace json.distros */
                        onSuccess(json.distros);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getCobblerKickstarts: function(cobblerServer, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cobbler/getKickstartTemplates/' + cobblerServer;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        var kickstarts = [];
                        /** @namespace json.kickstarts */
                        for (var i = 0; i < json.kickstarts.length; i++) {
                            if (json.kickstarts[i].search(/sample/) === -1 || scope.authedUser.userType === "Admin") {
                                kickstarts.push(json.kickstarts[i])
                            }
                        }
                        onSuccess(kickstarts);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getISOs: function(onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/neumatic/getISOs/';

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        /** @namespace json.isos */
                        onSuccess(json.isos);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getSelfServiceBusinessServices: function(onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/neumatic/getSelfServiceBusinessServices/';

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        /** @namespace json.businessServices */
                        onSuccess(json.businessServices);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getChefServers: function(onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/chef/getServers';

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.servers);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getChefRoles: function(chefServer, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/chef/getRoles?chef_server=' + chefServer;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        var roles = [];
                        for (var i = 0; i < json.roles.length; i++) {
                            // exclude neu_base roles
                            if (json.roles[i].search(/neu_base/) === -1) {
                                roles.push(json.roles[i])
                            }
                        }
                        onSuccess(roles);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getChefEnvironments: function(chefServer, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/chef/getEnvironments?chef_server=' + chefServer;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        var envs = [];
                        for (var i = 0; i < json.environments.length; i++) {
                            // exclude _default
                            if (json.environments[i].name.search(/_default/) === -1) {
                                envs.push(json.environments[i].name)
                            }
                        }
                        onSuccess(envs);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getVmwareDataCenters: function(onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/vmware/getDataCenters';

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.nodes);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getVmwareComputeClusters: function(dcUid, vSphereSite, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/vmware/getClusterComputeResources/' + dcUid + '?vSphereSite=' + vSphereSite;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.nodes);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getVmwareClusterComputeResourceNetworks: function(ccrUid, vSphereSite, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/vmware/getClusterComputeResourceNetworks/' + ccrUid + '?vSphereSite=' + vSphereSite;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.networks);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },

            getVmwareTemplates: function(vSphereSite, dcName, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/vmware/getTemplateList?vSphereSite=' + vSphereSite + '&dcName=' + dcName;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json.templates);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    };
            },
            getNextAvailableIPAddress: function(network, subnetMask, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/ip/getNextAvailableIP/' + network + '/' + subnetMask;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            deleteCobblerProfile: function(serverId, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cobbler/deleteSystem/' + serverId;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            deleteChefNode: function(nodeName, chefServer, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/chef/deleteNode/' + nodeName + '?chef_server=' + chefServer;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            deleteChefClient: function(nodeName, chefServer, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/chef/deleteClient/' + nodeName + '?chef_server=' + chefServer;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            deleteLdapHost: function(hostName, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/ldap/deleteHost/' + hostName;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            deleteFromDNS: function(serverId, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/ip/deleteFromDNS/' + serverId;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            deleteVMwareVM: function(serverId, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/vmware/deleteVM/' + serverId;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            deleteFromCmdb: function(serverId, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/cmdb/deleteServer/' + serverId;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            releaseBackToPool: function(serverId, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/neumatic/releaseBackToPool/' + serverId;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            deleteConfiguration: function(serverId, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/neumatic/deleteServer/' + serverId;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            stopBuild: function(serverId, onSuccess, onFailure) {
                onSuccess = onSuccess || function() {
                };
                onFailure = onFailure || ajaxError;
                var apiUrl = '/neumatic/stopBuild/' + serverId;

                //noinspection CommaExpressionJS
                httpGet(apiUrl, onFailure).then(
                    function(json) {
                        onSuccess(json);
                    }),
                    function() {
                        onFailure(json, apiUrl);
                    }
            },

            chefDeleteNode: function(node) {
                var apiUrl = "/neumatic/getVMSizes";
                return $http.get(apiUrl)
                    .then(
                    function(result) {
                        if (!result.data.success) {
                            AlertService.ajaxAlert({
                                json: result.data,
                                result: result,
                                apiUrl: apiUrl
                            });
                            return $q.reject();
                        }
                        return node;
                    },
                    function(result) {
                        AlertService.ajaxAlert({
                            json: result.data,
                            result: result,
                            apiUrl: apiUrl
                        });
                        return $q.reject();
                    }
                )
            },
            chefDeleteClient: function(node) {
                var apiUrl = "/neumatic/getVMSizes";
                return $http.get(apiUrl)
                    .then(
                    function(result) {
                        if (!result.data.success) {
                            AlertService.ajaxAlert({
                                json: result.data,
                                result: result,
                                apiUrl: apiUrl
                            });
                            return $q.reject();
                        }
                        return node;
                    },
                    function(result) {
                        AlertService.ajaxAlert({
                            json: result.data,
                            result: result,
                            apiUrl: apiUrl
                        });
                        return $q.reject();
                    }
                )
            }
        };
    });
