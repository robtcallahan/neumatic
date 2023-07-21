angular.module('NeuMatic')
/**
 * Uses the timeout function to get called every 15 seconds to obtain the list of neumatic servers,
 * their chef info and their status values.
 */
    .factory('ServersStatusService', function($interval, $http, AlertService) {
        var serviceRunning = false,
            queryRunning = false,
            cancelQuery = false,
            servers = [],
            timer = null,
            adminOn = false,
            scope = null,
            ldapUserGroup = 0,
            listType = 'all',
            sortBy = 'hostname',
            sortDir = 'asc',
            interval = 15000; // 15 seconds

        var getStatus = function() {
            if (!queryRunning) {
                queryRunning = true;
                //noinspection JSValidateTypes
                $http.get('/neumatic/getAllServerStatus/' + adminOn + '/' + listType + '/' + ldapUserGroup)
                    .success(function(json) {
                        queryRunning = false;
                        var serversBuilding = false;

                        if (cancelQuery) {
                            cancelQuery = false;
                            return;
                        }

                        if (typeof json.success !== "undefined" && !json.success) {
                            AlertService.ajaxAlert({
                                json: json,
                                apiUrl: '/neumatic/getAllServerStatus/' + adminOn + '/' + listType + '/' + ldapUserGroup
                            });
                        } else {
                            servers = json.servers;
                            scope.servers = [];
                            for (var i = 0; i < servers.length; i++) {
                                scope.servers[i] = servers[i];
                                if (servers[i].status === 'Building' || servers[i].status === 'Cooking') {
                                    serversBuilding = true;
                                }
                            }
                        }
                        if (!serversBuilding && listType === 'building') {
                            $interval.cancel(timer);
                            serviceRunning = false;

                            if (queryRunning) {
                                cancelQuery = true;
                            }
                            window.location.href = '/#/servers/current/0';
                        }
                    })
                    .error(function(json) {
                        queryRunning = false;
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getAllServerStatus/' + adminOn + '/' + listType + '/' + ldapUserGroup
                        });
                    });
            }
        };
        return {
            start: function(options) {
                scope = options.scope;
                adminOn = options.adminOn;
                listType = options.listType;
                ldapUserGroup = options.ldapUserGroup;
                sortBy = options.sortBy;
                sortDir = options.sortDir;
                serviceRunning = true;
                this.setTimer($interval(getStatus, interval));
            },
            getStatus: function() {
                getStatus();
            },
            setAdminOn: function(value) {
                adminOn = value;
            },
            changeListType: function(type) {
                listType = type;
            },
            changeLdapUserGroup: function(ldapUserGroup) {
                ldapUserGroup = ldapUserGroup;
            },
            changeSortBy: function(field) {
                sortBy = field;
            },
            changeSortDir: function(dir) {
                sortDir = dir;
            },
            getSortBy: function() {
                return sortBy;
            },
            getSortDir: function() {
                return sortDir;
            },
            isRunning: function() {
                return serviceRunning;
            },
            runningOn: function() {
                serviceRunning = true;
            },
            runningOff: function() {
                serviceRunning = false;
            },
            setTimer: function(t) {
                timer = t;
                serviceRunning = true;
            },
            stop: function() {
                $interval.cancel(timer);
                serviceRunning = false;

                if (queryRunning) {
                    cancelQuery = true;
                }
            }
        };
    })

