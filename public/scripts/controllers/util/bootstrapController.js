angular.module('NeuMatic')

.controller('BootstrapCtrl', function ($scope, $log, $state, $http, $timeout, AuthedUserService, JiraService, AlertService) {

        $scope.$log = $log;

        // authenticated user services
        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        // define the nav object that specifies the active menu item at the top
        $scope.nav = { chef: true };

        // init the prompt modal config
        $scope.modal = {
            title:   '',
            message: '',
            yesCallback: function() {},
            noCallback: function() {},
            showCancelButton: false,
            cancelCallback: function() {}
        };

        // layout classes
        $scope.rowClass = "col-md-6";
        $scope.labelClass = "col-lg-3";
        $scope.inputClass = "col-lg-9";
        $scope.lunSizeInputClass = "col-lg-6";
        $scope.lunAddButtonClass = "col-lg-3";

        $scope.defaultRole = 'neu_collection';
        $scope.defaultEnv = 'ST_CORE_LAB';

        // init the form field value
        $scope.username = "";
        $scope.password = "";
        $scope.passwordCompare = "";
        //$scope.hostList = "stlabvnode49.va.neustar.com\nstlabvnode50.va.neustar.com\nstlabvnode51.va.neustar.com";
        $scope.hostList = "";

        // don't show run lists initially, show the form instead
        $scope.showRunList = false;
        $scope.showRunForm = true;

        $scope.isRunning = false;
        $scope.runStatusText = "";

        $scope.output = "";
        $scope.hasOutput = true;

        $scope.delimter = "<span style='font-weight:bold;color:#0099FF'>-------------------------------------------------------------</span>\n";

        // ------------------------------------------------------------------------------------------------------------
        // Data Providers
        // ------------------------------------------------------------------------------------------------------------

        /**
         * Get a list of chef servers
         * Called on page load
         *
         */
        $scope.getChefServers = function() {
            //noinspection JSValidateTypes
            $http.get('/chef/getServers')
                .success(function (json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/chef/getServers'
                        });
                    } else {
                        $scope.chefServers = json.servers;
                    }
                })
                .error(function (json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/chef/getServers'
                    });
                });
        };

        /**
         * Get a list of chef roles
         * Called on page load by getChefServers() and when a chef server is selected
         *
         * @param chefServer
         */
        $scope.getChefRoles = function(chefServer) {
            var i;
            if (chefServer) {
                //noinspection JSValidateTypes
                $http.get('/chef/getRoles?chef_server=' + chefServer)
                    .success(function (json) {
                        var roles;

                        if (typeof json.success !== "undefined" && !json.success) {
                            AlertService.ajaxAlert({
                                json: json,
                                apiUrl: '/chef/getRoles?chef_server=' + chefServer
                            });
                            return;
                        }

                        $scope.chefRoles = [];
                        roles = json.roles;
                        for (i=0; i<roles.length; i++) {
                            if (roles[i].search(/neu_base/) === -1) {
                                $scope.chefRoles.push(roles[i])
                            }
                        }
                        $scope.chefRole = $scope.defaultRole;
                    })
                    .error(function (json) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/chef/getRoles?chef_server=' + chefServer
                        });
                    });
            }
        };

        /**
         * Get a list of chef environments
         * Called on page load by getChefServers() and when a chef server is selected
         *
         * @param chefServer
         */
        $scope.getChefEnvironments = function(chefServer) {
            if (chefServer) {
                //noinspection JSValidateTypes
                $http.get('/chef/getEnvironments?chef_server=' + chefServer)
                    .success(function (json) {
                        var defaultEnv = null, i;
                        if (typeof json.success !== "undefined" && !json.success) {
                            AlertService.ajaxAlert({
                                json: json,
                                apiUrl: '/chef/getEnvironments?chef_server=' + chefServer
                            });
                            return;
                        }
                        $scope.chefEnvs = [];
                        for (i = 0; i < json.environments.length; i++) {
                            if (json.environments[i].name.search(/ST_CORE_LAB$/) !== -1 || $scope.authedUser.userType === "Admin") {
                                $scope.chefEnvs.push(json.environments[i])
                            }
                            if (json.environments[i].name.search(/ST_CORE_LAB$/) !== -1) {
                                defaultEnv = json.environments[i];
                            }
                        }
                        $scope.chefEnv = defaultEnv;
                    })
                    .error(function (json) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/chef/getEnvironments?chef_server=' + chefServer
                        });
                    });
            }
        };


        // ----------------------------------------------------------------------------------------------------
        // Form field functions
        // ----------------------------------------------------------------------------------------------------

        /**
         * Triggered by selecting a chef server
         * Calls methods to get the chef environments and roles
         */
        $scope.chefServerSelected = function() {
            $scope.getChefEnvironments($scope.chefServer.name);
            $scope.getChefRoles($scope.chefServer.name);
        };

        $scope.passwordsMatch = function() {
            if ($scope.password === $scope.passwordCompare) {
                return true;
            } else {
                return false;
            }
        };

        // ----------------------------------------------------------------------------------------------------
        // Button functions
        // ----------------------------------------------------------------------------------------------------

        $scope.buttonRun = function() {
            var i,
                hosts = $scope.hostList.split("\n");

            // turn on the running flags and status
            $scope.isRunning = true;
            $scope.runStatusText = "Running...";

            $scope.showRunForm = false;
            $scope.showRunList = true;

            // create the hosts object array to display on the page
            $scope.hosts = [];
            for (i=0; i<hosts.length; i++) {
                $scope.hosts[i] = {
                    name: hosts[i],
                    running: false,
                    hasRun: false,
                    status: "Queued"
                };
            }

            $scope.launchBootstrap();
        };

        $scope.nextToRun = 0;
        $scope.launchBootstrap = function() {
            var i,
                isRunning = false,
                allDone = true;

            $log.debug("launchBootstrap(): nextToRun=" + $scope.nextToRun);

            for (i=0; i<$scope.hosts.length; i++) {
                if ($scope.hosts[i].running) {
                    $log.debug($scope.hosts[i].name + " is running");
                    isRunning = true;
                    $scope.nextToRun = i+1;
                    break;
                }
                allDone = allDone && $scope.hosts[i].hasRun;
            }
            if (isRunning) {
                $log.debug("Setting timeout");
                window.setTimeout($scope.launchBootstrap, 1000);
            } else if (allDone) {
                $scope.isRunning = false;
                $scope.displayStatus("Done", "");
            } else {
                $log.debug("Starting next bootstrap, host(i)=" + $scope.hosts[$scope.nextToRun].name + "(" + $scope.nextToRun + ")");
                $scope.runBootstrap($scope.nextToRun);
                $log.debug("Setting timeout");
                window.setTimeout($scope.launchBootstrap, 1000);
            }
        };

        $scope.runBootstrap = function(hostIndex) {
            $log.debug("runBootstrap()");

            $scope.hosts[hostIndex].running = true;
            $scope.hosts[hostIndex].status = "Running";

            $scope.output += "\n\n";
            $scope.output += $scope.delimter;
            $scope.output += '<span style="font-weight: bold; color: #0099FF;">' + $scope.hosts[hostIndex].name + '</span>' + "\n";
            $scope.output += $scope.delimter;

            // create a data structure with all the values of the server
            var data = $.param({
                chefServer: $scope.chefServer.name,
                chefRole: $scope.chefRole,
                chefEnv: $scope.chefEnv.name,

                username: $scope.username,
                password: $scope.password,

                hostName: $scope.hosts[hostIndex].name
            });

            // call a POST to the neumatic controller passing all the server values
            //noinspection JSValidateTypes
            $http({
                method:  'POST',
                url:     '/knife/bootstrap',
                data:    data,
                // content type is required here so that the data is formatted correctly
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}})

                // on success, return to the servers page
                .success(function (json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/knife/bootstrap'
                        });
                        return;
                    }
                    $scope.hasOutput = true;
                    $scope.output += json.output + ">\n";

                    $scope.hosts[hostIndex].running = false;
                    $scope.hosts[hostIndex].hasRun = true;
                    $scope.hosts[hostIndex].status = "Complete";
                })
                .error(function (json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/knife/bootstrap'
                    });
                });
        };

        // ------------------------------------------------------------------------------------------------------------
        // General Purpose Methods
        // ------------------------------------------------------------------------------------------------------------

        $scope.showLoading = function () {
            //$('#page-mask').show();
            //$('#mask-spinner').show();
        };

        $scope.hideLoading = function () {
            //$('#page-mask').hide();
            //$('#mask-spinner').hide();
        };

        /**
         * Show the initial status text for a couple of seconds and then change to final status
         *
         * @param initialStatus
         * @param finalStatus
         */
        $scope.displayStatus = function(initialStatus, finalStatus) {
            // set the initial status
            $scope.runStatusText = initialStatus;

            // call $timeout to wait for a bit and then change to final status
            // $timeout returns a promise that is processed next
            var promise = $timeout(function() { return finalStatus; }, 2000);

            // process the promise. It is in the format success, error & notify (similar to try, catch and throw)
            promise.then(
                function (statusText) {
                    // success
                    $scope.runStatusText = statusText;
                },
                function () {
                    // error
                    $scope.runStatusText = '';
                },
                function (update) {
                    // notify
                }
            );
        };

        // ------------------------------------------------------------------------------------------------------------
        // On Page Load
        // ------------------------------------------------------------------------------------------------------------

        $scope.getChefServers();

    });
