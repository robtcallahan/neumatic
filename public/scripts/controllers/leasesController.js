angular.module('NeuMatic')

    .controller('LeasesCtrl', function($scope, $log, $http, $stateParams, NeuMaticService, AuthedUserService, JiraService, AlertService) {
        $scope.nav = {admin: true};

        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        $scope.modal = {
            title: '',
            message: ''
        };

        var serverId = parseInt($stateParams.id);

        // sort defs
        $scope.predicate = 'serverName';
        $scope.reverse = false;

        $scope.save = function() {
            var lease = $scope.lease;

            var data = $.param({
                lease: lease
            });

            $scope.statusText = "Saving....";

            // call a POST to the neumatic controller passing all the server values
            //noinspection JSValidateTypes
            $http({
                method: 'POST',
                url: '/neumatic/saveLease',
                data: data,
                // content type is required here so that the data is formatted correctly
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            })

                // on success, return to the servers page
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        $scope.statusText = "";
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/saveLease'
                        });
                    } else {
                        window.location.href = "/#/leases";
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/saveLease'
                    });
                });
        };

        $scope.cancel = function() {
            window.location.href = "/#/leases";
        };

        // page load
        if (serverId) {
            $scope.user = $scope.authedUser;
            NeuMaticService.apiGet('/neumatic/getLease/' + serverId, function(json) {
                $scope.server = json.server;
                $scope.lease = json.lease;
            });
        } else {
            NeuMaticService.apiGet('/neumatic/getLeases', function(json) {
                $scope.leases = json.leases;
            })
        }
    });

