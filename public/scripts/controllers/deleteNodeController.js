angular.module('NeuMatic')

    .controller('DeleteNodeCtrl', function($scope, NeuMaticService, AuthedUserService, JiraService, AlertService) {
        $scope.nav = {admin: true};

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        $scope.nodeName = "";
        $scope.servers = [];

        $scope.search = function() {
            $scope.servers = [];
            showLoading();
            NeuMaticService.apiGet('/chef/checkNodeExistsAllServers/' + $scope.nodeName,
                function(json) {
                    $scope.servers = json.servers;
                    hideLoading();
                },
                function() {
                    hideLoading();
                }
            );
        };

        $scope.deleteNode = function(server) {
            showLoading();
            NeuMaticService.apiGet('/chef/deleteNode/' + $scope.nodeName + '?chef_server=' + server.fqdn,
                function() {
                    NeuMaticService.apiGet('/chef/deleteClient/' + $scope.nodeName + '?chef_server=' + server.fqdn,
                        function() {
                            hideLoading();
                            $scope.search();
                        },
                        function(json) {
                            hideLoading();
                            AlertService.ajaxAlert({
                                json: json,
                                apiUrl: '/chef/deleteClient/' + $scope.nodeName + '?chef_server=' + server.fqdn
                            });
                        }
                    );
                },
                function(json) {
                    hideLoading();
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/chef/deleteNode/' + $scope.nodeName + '?chef_server=' + server.fqdn
                    });
                }
            );
        };

        function showLoading() {
            $('#loading-spinner').show()
        }

        function hideLoading() {
            $('#loading-spinner').hide();
        }

    });

