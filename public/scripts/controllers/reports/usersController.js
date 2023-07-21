angular.module('NeuMatic')

    .filter("calculateIndexField", function() {
        var count = 0;
        return function() {
            count++;
            return count;
        };
    })

    .controller('UsersReportCtrl', function($scope, $log, $http, $stateParams, AuthedUserService, JiraService, AlertService, filterFilter, pagingFilter) {

        $scope.index = 0;

        $scope.nav = {reports: true};

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        $scope.modal = {
            title: '',
            message: ''
        };

        $scope.$stateParams = $stateParams;
        $scope.userId = parseInt($stateParams.id);

        $scope.processing = false;
        $scope.showKeyAndProcedure = false;

        $scope.loading = false;

        $scope.accountCreatedOnServer = "";

        // Searching, paging and sorting properties
        $scope.users = [];
        $scope.pagedData = [];

        $scope.sortField = "lastLogin";
        $scope.sortReverse = true;

        $scope.createChefAccount = function(user, chefServer) {
            // hide buttons while processing
            $scope.processing = true;

            // call chef controller to create the account and obtain the private key
            //noinspection JSValidateTypes
            $http.get('/chef/createUser/' + user.username + '?chef_server=' + chefServer.name)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/chef/createUser'
                        });
                        return;
                    }
                    $scope.privateKey = json.privateKey;
                    $scope.showKeyAndProcedure = true;
                    $scope.processing = false;

                    for (var i = 0; i < $scope.user.chefServers.length; i++) {
                        if ($scope.user.chefServers[i].name === chefServer.name) {
                            $scope.user.chefServers[i].isUser = true;
                        }
                    }
                    $scope.accountCreatedOnServer = chefServer.name;
                })
                .error(function(json) {
                    $scope.processing = false;
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/chef/createUser'
                    });
                });
        };

        $scope.regenPrivateKey = function(user, chefServer) {
            // hide buttons while processing
            $scope.processing = true;

            // call chef controller to create the account and obtain the private key
            //noinspection JSValidateTypes
            $http.get('/chef/deleteUser/' + user.username + '?chef_server=' + chefServer.name)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/chef/deleteUser'
                        });
                        return;
                    }
                    $scope.createChefAccount(user, chefServer);
                })
                .error(function(json) {
                    $scope.processing = false;
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/chef/deleteUser'
                    });
                });
        };

        $scope.deleteChefAccount = function(user, chefServer) {
            // hide buttons while processing
            $scope.processing = true;

            // call chef controller to create the account and obtain the private key
            //noinspection JSValidateTypes
            $http.get('/chef/deleteUser/' + user.username + '?chef_server=' + chefServer.name)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/chef/deleteUser'
                        });
                        return;
                    }
                    $scope.processing = false;
                })
                .error(function(json) {
                    $scope.processing = false;
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/chef/deleteUser'
                    });
                });
        };

        /**
         * On Page Load
         */
        if ($scope.userId) {
            if ($scope.userId !== $scope.authedUser.id) {
                //noinspection JSValidateTypes
                $http.get('/users/getUser/' + $scope.userId)
                    .success(function(json) {
                        if (typeof json.success !== "undefined" && !json.success) {
                            AlertService.ajaxAlert({
                                json: json,
                                apiUrl: '/users/getUser'
                            });
                            return;
                        }
                        $scope.user = json.user;
                        if ($scope.authedUser.userType === 'Admin') {
                            showLoading();
                            //noinspection JSValidateTypes
                            $http.get('/users/getUsersChefServers/' + $scope.user.id)
                                .success(function(json) {
                                    hideLoading();
                                    if (typeof json.success !== "undefined" && !json.success) {
                                        AlertService.ajaxAlert({
                                            json: json,
                                            apiUrl: '/users/getUsersChefServers'
                                        });
                                    } else {
                                        $scope.user.chefServers = json.chefServers;
                                    }
                                })
                                .error(function(json) {
                                    hideLoading();
                                    AlertService.ajaxAlert({
                                        json: json,
                                        apiUrl: '/users/getUsersChefServers'
                                    });
                                });
                        }
                    })
                    .error(function(json) {
                        $scope.ajaxError(json);
                    });
            } else {
                $scope.user = $scope.authedUser;
                showLoading();
                //noinspection JSValidateTypes
                $http.get('/users/getUsersChefServers/' + $scope.user.id)
                    .success(function(json) {
                        hideLoading();
                        if (typeof json.success !== "undefined" && !json.success) {
                            AlertService.ajaxAlert({
                                json: json,
                                apiUrl: '/users/getUsersChefServers'
                            });
                        } else {
                            $scope.authedUserService.setChefServers(json.chefServers);
                            $scope.user.chefServers = json.chefServers;
                        }

                    })
                    .error(function(json) {
                        hideLoading();
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/users/getUsersChefServers'
                        });
                    });
            }
        } else {
            showLoading();
            //noinspection JSValidateTypes
            $http.get('/users/')
                .success(function(json) {
                    hideLoading();
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/users/'
                        });
                    } else {
                        // convert numLogins to float so that sorting works properly
                        angular.forEach(json.users, function(user) {
                            user.numLogins = parseFloat(user.numLogins);
                            user.numServerBuilds = parseFloat(user.numServerBuilds);
                            user.numServers = parseFloat(user.numServers);
                        });
                        $scope.users = json.users;
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/users/'
                    });
                });
        }

        // ----------------------------------------------------------------------------------------------------
        // Error processing functions
        // ----------------------------------------------------------------------------------------------------

        function showLoading() {
            $('#loading-spinner').show();
        }

        function hideLoading() {
            $('#loading-spinner').hide();
        }
    });


