angular.module('NeuMatic')

    .controller('NodesCtrl', function($scope, $stateParams, $log, $http, $window, AuthedUserService, JiraService, AlertService) {
        $scope.nav = {nodes: true};

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;
        $scope.window = $window;

        // define default sort
        $scope.predicate = 'hostname';
        $scope.reverse = false;

        /** @namespace $stateParams.chef_server */
        if (typeof $stateParams.chef_server != 'undefined') {
            $scope.chefServer = $stateParams.chef_server;
        } else {
            $scope.chefServer = getCookie('chef_server');
        }


        $scope.chefServerChange = function() {
            $scope.chefServer = $scope.chefServerSelected.name;
            setCookie('chef_server', $scope.chefServer, 1);
            getNodes();
        };

        $scope.editNode = function(node) {
            setCookie('chef_server', node.chefServerFqdn, 1);
            $window.location.href = "/#/nodes/" + node.fqdn;
        };


        // ----------------------------------------------------------------------------------------------------
        // General purpose functions
        // ----------------------------------------------------------------------------------------------------

        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            var expires = "expires=" + d.toUTCString();
            document.cookie = String(cname + "=" + cvalue + "; " + expires);
        }

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

        function getChefServers() {
            //noinspection JSValidateTypes
            $http.get('/chef/getServers')
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/chef/getServers'
                        });
                        return;
                    }

                    $scope.chefServers = [];
                    $scope.chefServer = getCookie('chef_server');

                    if ($scope.chefServer == "" || $scope.chefServer == "undefined") {
                        $scope.chefServer = json.servers[0].name;
                        setCookie('chef_server', $scope.chefServer, 1);
                    }

                    for (var i = 0; i < json.servers.length; i++) {
                        if (json.servers[i].name === $scope.chefServer) {
                            $scope.chefServerSelected = json.servers[i];
                        }
                        if (json.servers[i].name != 'targetVersion') {
                            $scope.chefServers[i] = json.servers[i];
                        }
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/chef/getServers'
                    });
                });
        }

        function getNodes() {
            var url;
            showLoading();
            if ($scope.chefServer) {
                url = '/mongo/getNodes?chef_server=' + $scope.chefServer;
            } else {
                url = '/mongo/getNodes';
            }
            //noinspection JSValidateTypes
            $http.get(url)
                .success(function(json) {
                    hideLoading();
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: url
                        });
                    } else {
                        $scope.nodes = json.nodes;
                        $scope.chefServer = json.chefServer;
                        setCookie('chef_server', $scope.chefServer, 1);
                    }
                })
                .error(function(json) {
                    hideLoading();
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: url
                    });
                });
        }

        function showLoading() {
            $('#loading-spinner').show();
        }

        function hideLoading() {
            $('#loading-spinner').hide();
        }


        // ----------------------------------------------------------------------------------------------------
        // On Page Load
        // ----------------------------------------------------------------------------------------------------

        getChefServers();
        getNodes();

    });

