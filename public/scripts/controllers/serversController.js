angular.module('NeuMatic')

    .controller('ServersCtrl', function($scope, $log, $http, $location, $stateParams, AuthedUserService, NeuMaticService, JiraService, AlertService, ServersStatusService) {

        $scope.$log = $log;

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        // NeuMaticService service
        NeuMaticService.setScope($scope);

        $scope.nav = {servers: true};

        $scope.showLog = false;
        $scope.serverLog = '';
        $scope.logLoading = false;

        // delete flags
        var deleteConfig = false,
            deleteCmdb = false;

        $scope.modal = {
            title: '',
            message: '',
            yesCallback: function() {
            },
            noCallback: function() {
            }
        };

        $scope.dialog = {
            title: '',
            username: $scope.authedUser.username,
            submitCallback: function() {
            },
            cancelCallback: function() {
            }
        };

        $scope.dialog = {
            title:   '',
            username: $scope.authedUser.username,
            submitCallback: function() {},
            cancelCallback: function() {}
        };

        $scope.hostNameMenuItems = [
            'System Details',
            'Chef Details',
            'Audit History'
        ];


        // sort info
        $scope.predicate = "hostname";
        $scope.reverse = false;


        // list types struct
        $scope.listTypes = [
            {
                title: 'Systems: Current',
                value: 'current',
                name: 'Current'
            }, {
                title: 'Systems: Building',
                value: 'building',
                name: 'Building'
            }, {
                title: 'Systems: Archived',
                value: 'archived',
                name: 'Archived'
            }, {
                title: 'Systems: All',
                value: 'all',
                name: 'All'
            }
        ];


        // $stateParams = {listType: x, teamId} from the URL

        // default listType
        $scope.listType = $scope.listTypes[0];
        // set the listType from the URL
        angular.forEach($scope.listTypes, function(o, key) {
            if ($stateParams.listType == o.value) {
                $scope.listType = $scope.listTypes[key];
                $scope.selectedListType = key;
            }
        });
        $scope.title = $scope.listType.title;

        /**
         * select listType when user clicks. list types are current, archived, building, all
         * @param listType
         * @param index
         */
        $scope.selectListType = function(listType, index) {
            $scope.listType = listType;
            $scope.selectedListType = index;
            $scope.getServers();
        };


        // define "myTeam" which is my systems list rather than a named team.
        // same format as 'team' object in authedUser.teams[x]
        $scope.myGroup = 'My Systems';

        // default team
        $scope.ldapUserGroup = $scope.myGroup;
        $scope.selectedLdapUserGroup = $scope.myGroup;
        $scope.authedUserService.setCallback(function() {
            if ($stateParams.ldapUserGroup === 'My Systems') {
                $scope.ldapUserGroup = $scope.myGroup;
                $scope.selectedLdapUserGroup = 'first';
            } else {
                var index = 0;
                angular.forEach($scope.authedUser.ldapUserGroups, function(o) {
                    if ($stateParams.ldapUserGroup == o) {
                        $scope.ldapUserGroup = o;
                        $scope.selectedLdapUserGroup = o;
                    }
                    index++;
                });
            }
        });

        /**
         * select ldapUserGroup when user clicks. note the ldapUserGroup (obj) and
         * selectedLdapUserGroup (bool) are different.
         * the latter is to highlight the "selected" ldapUserGroup in the UI
         * @param ldapUserGroup
         * @param index
         */
        $scope.selectLdapUserGroup = function(ldapUserGroup, index) {
            // default 'mySystems' team preceeds ng-repeat. must take that into account here
            $scope.ldapUserGroup = ldapUserGroup;
            $scope.selectedLdapUserGroup = ldapUserGroup;

            $scope.getServers();
        };


        /**
         * Set the status value color
         * @param status
         * @returns {string}
         */
        $scope.statusColor = function(status) {
            if (status.search(/Built/) !== -1) {
                return 'ok';
            } else if (status.search(/Building/) !== -1) {
                return 'warning';
            } else {
                return 'error';
            }
        };

        $scope.navTo = function(server, index, path, attr) {
            $(".dropdown.open").removeClass('open');
            server.index = index;
            window.location.href = path + server[attr];
        };

        $scope.setChefServer = function(server) {
            setCookie('chef_server', server.chefServerFqdn, 1);
        };

        $scope.showChefNodeDetails = function(server) {
            $(".dropdown.open").removeClass('open');
            /** @namespace server.chefServerFqdn */
            /** @namespace server.fqdn */
            setCookie('chef_server', server.chefServerFqdn, 1);
            window.location.href = '#/nodes/' + server.fqdn;
        };

        $scope.showConsoleOutput = function(server) {
            $(".dropdown.open").removeClass('open');
            $scope.serverLog = "";
            $scope.showLog = true;
            $http.get('/neumatic/getConsoleLog/' + server.id)
                .success(function(json) {
                    var log = '', output = '';
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getConsoleLog'
                        });
                        return;
                    }
                    log = json.log;
                    output += '<h2>Console output log</h2>';
                    output += String(log).replace(/\n/g, '<br>');
                    output += '<br><br>';
                    $scope.serverLog = output;
                })
                .error(function(json) {
                    $scope.logLoading = false;
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getChefLog'
                    });
                });
        };

        $scope.quickBuild = function(server, index) {
            $(".dropdown.open").removeClass('open');
            server.index = index;
            if (server.serverType !== 'remote') {
                return;
            }

            updateStatus(server, 'Building', 'System reset...');
            $scope.servers[server.index].buildStep = 0;
            $scope.servers[server.index].buildSteps = 0;

            $http.get('/neumatic/resetStartTime/' + server.id)
                .success(function() {
                    //noinspection JSValidateTypes
                    $http.get('/hardware/resetRemoteSystem/' + server.id)
                        .success(function(json) {
                            // check return status
                            if (typeof json.success !== "undefined" && !json.success) {
                                updateStatus(server, 'Failed', 'Could not reset system');
                                AlertService.ajaxAlert({
                                    json: json,
                                    apiUrl: state
                                });
                            } else {
                                updateStatus(server, 'Building', 'System starting...');
                            }
                        })
                        .error(function(json) {
                            updateStatus(server, 'Failed', 'Could not reset system');
                            AlertService.ajaxAlert({
                                json: json,
                                apiUrl: state
                            });
                        });
                });
        };

        $scope.showServerLog = function(server) {
            $scope.serverLog = "";
            $scope.showLog = true;
            $scope.logLoading = true;
            //noinspection JSValidateTypes
            $http.get('/neumatic/getChefLog/' + server.id)
                .success(function(json) {
                    var data, output = '';

                    $scope.logLoading = false;

                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getChefLog'
                        });
                        return;
                    }

                    data = json.data;
                    output += '<h2>/var/log/chef-initial-run.log</h2>';
                    /** @namespace data.initialRun.date */
                    /** @namespace data.initialRun.log */
                    output += '<h4>' + data.initialRun.date + '</h4>';
                    output += String(data.initialRun.log).replace(/\n/g, '<br>');

                    output += '<h2>/var/log/chef/client.log</h2>';
                    /** @namespace data.clientLog.date */
                    /** @namespace data.clientLog.log */
                    output += '<h4>' + data.clientLog.date + '</h4>';
                    output += String(data.clientLog.log).replace(/\n/g, '<br>');

                    output += '<h2>/var/chef/cache/chef-stacktrace.out</h2>';
                    /** @namespace data.stackTrace.date */
                    /** @namespace data.stackTrace.log */
                    output += '<h4>' + data.stackTrace.date + '</h4>';
                    output += String(data.stackTrace.log).replace(/\n/g, '<br>');

                    output += '<br><br>';
                    $scope.serverLog = output;
                })
                .error(function(json) {
                    $scope.logLoading = false;
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getChefLog'
                    });
                });

        };

        $scope.dismissLogWindow = function() {
            $scope.showLog = false;
        };

        $scope.assignLdapUserGroup = function(server, index) {
            $(".dropdown.open").removeClass('open');
            server.index = index;
            $scope.server = server;
            //noinspection JSUnresolvedFunction
            $('#userGroupAssignDialog').modal('show');
            $('#ldapUserGroup').focus();
        };

        $scope.assignOwner = function(server, index) {
            $(".dropdown.open").removeClass('open');
            server.index = index;
            $scope.server = server;
            //noinspection JSUnresolvedFunction
            $('#ownerAssignDialog').modal('show');
            $('#owner').focus();
        };


        // ----------------------------------------------------------------------------------------------------
        // Watchers
        // ----------------------------------------------------------------------------------------------------

        // watch for the admin switch being turned on or off
        $scope.$watch('authedUserService.authedUser.adminOn', function(newValue, oldValue) {
            // reload the list of servers if admin was turned on or off
            // first, be sure that old and new values are defined and have actually changed
            if (typeof oldValue !== "undefined" && typeof newValue !== "undefined" && oldValue !== newValue) {
                ServersStatusService.setAdminOn(newValue);
                $scope.getServers();
            }
        });

        // ----------------------------------------------------------------------------------------------------
        // Button functions
        // ----------------------------------------------------------------------------------------------------

        $scope.buttonNewServer = function() {
            window.location.href = "/#/servers/0";
        };

        $scope.buttonStopServer = function(server, index) {
            // hide the dropdown menu since it doesn't do it by itself when you click on an option
            $(".dropdown.open").removeClass('open');
            server.index = index;
            confirmStop(server);
        };

        /**
         * Called when the Archive button is pressed
         * Set's the archive bit in the server table to 0 so that it show ups in the archived server list
         *
         * @param server
         * @param index
         */
        $scope.buttonArchiveServer = function(server, index) {
            // hide the dropdown menu since it doesn't do it by itself when you click on an option
            $(".dropdown.open").removeClass('open');

            server.index = index;

            //noinspection JSValidateTypes
            $http.get('/neumatic/archiveServer/' + server.id)
                .success(function(json) {
                    // check return status
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/archiveServer'
                        });
                        return;
                    }
                    $scope.getServers();
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/archiveServer'
                    });
                });
        };

        /**
         * Called when the Unarchive button is pressed
         * Set's the archive bit in the server table to 1 so that it show ups in the current server list
         *
         * @param server
         * @param index
         */
        $scope.buttonUnarchiveServer = function(server, index) {
            // hide the dropdown menu since it doesn't do it by itself when you click on an option
            $(".dropdown.open").removeClass('open');

            server.index = index;

            //noinspection JSValidateTypes
            $http.get('/neumatic/unarchiveServer/' + server.id)
                .success(function(json) {
                    // check return status
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/unarchiveServer'
                        });
                        return;
                    }
                    $scope.getServers();
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/unarchiveServer'
                    });
                });
        };

        $scope.buttonRebuildServer = function(server, index) {
            // hide the dropdown menu since it doesn't do it by itself when you click on an option
            $(".dropdown.open").removeClass('open');
            server.index = index;
            confirmRebuild(server);
        };

        $scope.buttonBuildServer = function(server, index) {
            // hide the dropdown menu since it doesn't do it by itself when you click on an option
            $(".dropdown.open").removeClass('open');
            server.index = index;
            confirmBuild(server);
        };

        $scope.buttonDeleteServer = function(server, index) {
            // hide the dropdown menu since it doesn't do it by itself when you click on an option
            $(".dropdown.open").removeClass('open');
            server.index = index;
            confirmDelete(server);
        };

        // ----------------------------------------------------------------------------------------------------
        // Private functions
        // ----------------------------------------------------------------------------------------------------

        function confirmBuild(server) {
            var vmText = server.serverType === 'vmware' ? 'VM, ' : '';
            var descr = server.description ? '(' + server.description + ')' : '';
            var msg = 'Are you sure you want to <strong>build</strong> ' + server.hostname + '?<br>' + descr + '<br><br>' +
                'This <strong>will</strong> update the ' + vmText + 'DNS, LDAP and CMDB entries. Then reinstall the OS and run Chef.';

            $scope.modal = {
                title: 'Confirm Build',
                message: msg,
                yesCallback: function() {
                    // set the server in the NeuMatic servers so we can change views/controllers and pass the info along
                    NeuMaticService.setServer(server);
                    NeuMaticService.setRebuild(false);
                    setTimeout(function() {
                        buildServer(server);
                    }, 500);
                }
            };
            // show the popup modal dialog
            //noinspection JSUnresolvedFunction
            $('#promptModal').modal('show');
        }

        function buildServer(server) {
            if (server.hostname.search(/stlabvnode/) !== -1) {
                server.poolServer = true;
            } else {
                server.poolServer = false;
            }
            NeuMaticService.setServer(server);
            window.location.href = '#/selfService/selfService/build';
        }

        function confirmRebuild(server) {
            var vmText = server.serverType === 'vmware' ? 'VM, ' : '';
            var msg = 'Are you sure you want to <strong>rebuild</strong> ' + server.hostname + '?<br><br>' +
                'This <strong>will not</strong> delete and recreate the ' + vmText + 'DNS, LDAP or CMDB entries.<br>' +
                'It will just load the OS and run Chef.';

            $scope.modal = {
                title: 'Confirm Rebuild',
                message: msg,
                yesCallback: function() {
                    // set the server in the NeuMatic servers so we can change views/controllers and pass the info along
                    NeuMaticService.setServer(server);
                    NeuMaticService.setRebuild(true);
                    setTimeout(function() {
                        buildServer(server);
                    }, 500);
                }
            };
            // show the popup modal dialog
            //noinspection JSUnresolvedFunction
            $('#promptModal').modal('show');
        }

        function confirmStop(server) {
            $scope.modal = {
                title: 'Confirm Stop/Abort',
                message: 'Are you sure you want to stop building ' + server.name + '?',
                yesCallback: function() {
                    updateStatus(server, 'Aborted', "Build stopping...");

                    NeuMaticService.stopBuild(server.id, function() {
                        updateStatus(server, 'Aborted', "Aborted");
                    })
                }
            };
            //noinspection JSUnresolvedFunction
            $('#promptModal').modal('show');
        }

        function confirmDelete(server) {
            var msg = 'Are you sure you want to delete ' + server.name + '?';
            if (server.description) {
                msg += '<br>(' + server.description + ')';
            }
            $scope.modal = {
                title: 'Confirm Delete',
                message: msg,
                yesCallback: function() {
                    promptDeleteConfig(server);
                }
            };
            //noinspection JSUnresolvedFunction
            $('#promptModal').modal('show');
        }

        /**
         * Called from delete server request to see if config should be removed as well
         *
         */
        function promptDeleteConfig(server) {
            deleteConfig = false;
            $scope.modal = {
                title: 'Confirm Delete',
                message: 'Do you want to delete the saved configuration as well?',
                showCancelButton: true,
                yesCallback: function() {
                    deleteConfig = true;
                    if (!server.serverPoolId) {
                        promptDeleteCmdb(server);
                    } else {
                        deleteCmdb = true;
                        deleteCobblerProfile(server);
                    }
                },
                noCallback: function() {
                    deleteConfig = false;
                    if (!server.serverPoolId) {
                        promptDeleteCmdb(server);
                    } else {
                        deleteCmdb = true;
                        deleteCobblerProfile(server);
                    }
                },
                cancelCallback: function() {
                    // Delete cancelled, nothing done
                }
            };
            setTimeout(function() {
                //noinspection JSUnresolvedFunction
                $('#promptModal').modal('show');
            }, 1000);
        }

        /**
         * Called from delete config request to see if the CMDB entry should be deleted
         *
         */
        function promptDeleteCmdb(server) {
            $scope.modal = {
                title: 'Confirm Delete',
                message: 'Do you want to delete the CMDB entry?',
                showCancelButton: true,
                yesCallback: function() {
                    deleteCmdb = true;
                    deleteCobblerProfile(server);
                },
                noCallback: function() {
                    deleteCmdb = false;
                    deleteCobblerProfile(server);
                },
                cancelCallback: function() {
                    // Delete cancelled, nothing done
                }
            };
            setTimeout(function() {
                //noinspection JSUnresolvedFunction
                $('#promptModal').modal('show');
            }, 1000);
        }

        function deleteCobblerProfile(server) {
            updateStatus(server, 'Deleting', 'Deleting Cobbler entry');
            NeuMaticService.deleteCobblerProfile(server.id,
                deleteFromChef(server)
            );
        }

        /**
         * Called from deleteCobblerProfile() to delete the Chef node and client
         *
         */
        function deleteFromChef(server) {
            updateStatus(server, 'Deleting', 'Deleting Chef entry');
            NeuMaticService.deleteChefNode(server.name, server.chefServer,
                NeuMaticService.deleteChefClient(server.name, server.chefServer,
                    deleteFromLdap(server)
                )
            )
        }

        /**
         * Called from deleteCobblerProfile() to delete the LDAP entry
         *
         */
        function deleteFromLdap(server) {
            updateStatus(server, 'Deleting', 'Deleting LDAP entry');
            NeuMaticService.deleteLdapHost(server.id,
                function() {
                    deleteVMwareVM(server);
                })
        }

        /**
         * Called from deleteFromLdap() to delete the VMware VM
         *
         */
        function deleteVMwareVM(server) {
            updateStatus(server, 'Deleting', 'Deleting VMware VM');
            NeuMaticService.deleteVMwareVM(server.id,
                function() {
                    if (!server.serverPoolId) {
                        deleteFromDNS(server);
                    } else {
                        if (deleteCmdb) {
                            deleteFromCmdb(server);
                        } else {
                            if (deleteConfig) {
                                releaseToServerPool(server);
                            } else {
                                updateStatus(server, 'New', ' ');
                                ServersStatusService.getStatus();
                            }
                        }
                    }
                })
        }

        /**
         * Called from deleteFromChef() to delete the DNS entry
         *
         */
        function deleteFromDNS(server) {
            updateStatus(server, 'Deleting', 'Deleting DNS Entry');
            NeuMaticService.deleteFromDNS(server.id,
                function() {
                    if (deleteCmdb) {
                        deleteFromCmdb(server);
                    } else {
                        if (deleteConfig) {
                            if (!server.serverPoolId) {
                                deleteConfiguration(server);
                            } else {
                                releaseToServerPool(server);
                            }
                        } else {
                            updateStatus(server, 'New', ' ');
                            ServersStatusService.getStatus();
                        }
                    }
                })
        }

        /**
         * Called from deleteFromDns() if deleteCmdb flag is true to delete from CDMB
         *
         */
        function deleteFromCmdb(server) {
            updateStatus(server, 'Deleting', 'Deleting CMDB entry');
            NeuMaticService.deleteFromCmdb(server.id,
                function() {
                    if (deleteConfig) {
                        if (!server.serverPoolId) {
                            deleteConfiguration(server);
                        } else {
                            releaseToServerPool(server);
                        }
                    } else {
                        updateStatus(server, 'New', ' ');
                        ServersStatusService.getStatus();
                    }
                })
        }

        /**
         * Called from deleteFromDns() or deleteFromCmdb() if deleteConfig flag is true
         * to delete the VM from the server pool
         *
         */
        function releaseToServerPool(server) {
            updateStatus(server, 'Deleting', 'Releasing to pool');
            NeuMaticService.releaseBackToPool(server.id,
                function() {
                    ServersStatusService.getStatus();
                })
        }

        $scope.saveDescription = function(server) {
            $scope.editMode = false;

            // create a data structure with all the values of the server
            var data = $.param({
                id: server.id,
                description: server.description
            });

            // call a POST to the neumatic controller passing all the server values
            //noinspection JSValidateTypes
            $http({
                method: 'POST',
                url: '/neumatic/saveDescription',
                data: data,
                // content type is required here so that the data is formatted correctly
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            })

                // on success, return to the servers page
                .success(function(json) {
                    hideLoading();
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/saveDescription'
                        });
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/saveDescription'
                    });
                });
        };


        /**
         * Delete this server's configuration
         */
        function deleteConfiguration(server) {
            updateStatus(server, 'Deleting', 'Deleting config');
            NeuMaticService.deleteConfiguration(server.id,
                function() {
                    ServersStatusService.getStatus();
                })
        }

        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            var expires = "expires=" + d.toUTCString();
            document.cookie = String(cname + "=" + cvalue + "; " + expires);
        }

        function showLoading() {
            $('#loading-spinner').show();
        }

        function hideLoading() {
            $('#loading-spinner').hide();
        }

        /*
         function showLoading() {
         //$scope.showQuote = true;
         NeuMaticService.apiGet('/neumatic/getQuoteOfTheDay',
         function(json) {
         $scope.quote = json.quote;
         $scope.author = json.author;
         $scope.showQuote = true;
         },
         function() {
         $scope.showQuote = false;
         }
         );
         }

         function hideLoading() {
         $scope.showQuote = false;
         }
         */

        /**
         * Given a server, status (Building, Built, Failed, etc) and statusText values, update
         * the entry in the server table
         *
         * @param server
         * @param status
         * @param statusText
         */
        function updateStatus(server, status, statusText) {
            $scope.servers[server.index].status = status;
            $scope.servers[server.index].statusText = statusText;

            var data = $.param({
                serverId: server.id,
                status: status,
                statusText: statusText
            });

            //noinspection JSValidateTypes
            $http({
                method: 'POST',
                url: '/neumatic/updateStatus',
                data: data,
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            })

                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/updateStatus'
                        });
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/updateStatus'
                    });
                });
        }

        // ----------------------------------------------------------------------------------------------------
        // On Page Load
        // ----------------------------------------------------------------------------------------------------

        // get the servers for display
        $scope.getServers = function() {
            showLoading();

            // check if status check is already running. start if not
            if (ServersStatusService.isRunning()) {
                ServersStatusService.stop();
            }

            //noinspection JSValidateTypes
            $http.get('/neumatic/getAllServerStatus/' + $scope.authedUserService.authedUser.adminOn + '/' + $scope.listType.value + '/' + $scope.ldapUserGroup)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getAllServerStatus'
                        });
                        return;
                    }
                    hideLoading();

                    $scope.servers = [];
                    for (var i = 0; i < json.servers.length; i++) {
                        if (json.servers[i].hostname.search(/^stlabvnode/) !== -1) {
                            json.servers[i].labHost = true;
                        } else {
                            json.servers[i].labHost = false;
                        }
                        $scope.servers.push(json.servers[i]);
                    }

                    ServersStatusService.start({
                        scope: $scope,
                        ldapUserGroup: $scope.ldapUserGroup,
                        listType: $scope.listType.value,
                        adminOn: $scope.authedUserService.authedUser.adminOn,
                        sortBy: $scope.predicate,
                        sortDir: $scope.reverse ? 'desc' : 'asc'
                    });
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getAllServerStatus'
                    });
                });
        };

        $scope.changeSort = function(field) {
            $scope.predicate = field;
            $scope.reverse = !$scope.reverse;
            ServersStatusService.changeSortBy(field);
            ServersStatusService.changeSortDir($scope.reverse ? 'desc' : 'asc');
        };

        $scope.$on('$stateChangeStart',
            function() {
                ServersStatusService.stop();
            }
        );

        $scope.getServers();

    });

