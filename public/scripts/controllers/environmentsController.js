angular.module('NeuMatic')

    .controller('EnvironmentsCtrl', function($scope, $log, $http, $location, NeuMaticService, AuthedUserService, JiraService, AlertService) {

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = $scope.authedUserService.authedUser;

        $scope.jiraService = JiraService;
        $scope.alertService = AlertService;

        $scope.nav = {
            environments: true
        };

        $scope.modal = {
            title: '',
            message: ''
        };

        $scope.rowClass = "col-md-6";
        $scope.labelClass = "col-lg-3";
        $scope.inputClass = "col-lg-9";

        $scope.predicate = 'name';

        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            var expires = "expires=" + d.toUTCString();
            document.cookie = cname + "=" + cvalue + "; " + expires;
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


        $scope.chefServer = getCookie('chef_server');

        $scope.getChefServers = function(callback) {
            $http.get('/chef/getServers').success(function(data) {
                $scope.chefServers = [];
                $scope.chefServer = getCookie('chef_server');

                if ($scope.chefServer == "" || $scope.chefServer == "undefined") {
                    $scope.chefServer = data.servers[0].name;
                    setCookie('chef_server', $scope.chefServer, 1);
                }

                for (var i = 0; i < data.servers.length; i++) {
                    if (data.servers[i].name === $scope.chefServer) {
                        $scope.chefServerSelected = data.servers[i];
                    }
                    if (data.servers[i].name != 'targetVersion' && data.servers[i].allowChef != false) {
                        $scope.chefServers[i] = data.servers[i];
                    }
                }
                $scope.chefServers = $scope.chefServers.filter(function(n) {
                    return n != undefined
                });

            });
        }

        $scope.chefServerChange = function() {
            $scope.chefServer = $scope.chefServerSelected.name;
            setCookie('chef_server', $scope.chefServer, 1);
            //$scope.getEnvironments();
        };

        $scope.showLoading = function() {
            $('#loading-spinner').show();
        };

        $scope.hideLoading = function() {
            $('#loading-spinner').hide();
        };

        $scope.environmentNewButtonDisabled = true;

        $scope.$watch('authorizedEdit', function(newValue, oldValue) {
            if (newValue === oldValue) {
                return;
            }
            if ($scope.authorizedEdit == true) {
                $scope.environmentNewButtonDisabled = false;

            } else {
                $scope.environmentNewButtonDisabled = true;

            }
        });

        /***********************************************************************************************************/

        // get a list of environments
        $scope.getEnvironments = function() {
            $scope.environments = [];
            $scope.showLoading();

            NeuMaticService.apiGet('/chef/getEnvironmentsDetailed?chef_server=' + $scope.chefServer,
                // success function
                function(json) {
                    $scope.hideLoading();
                    $scope.environments = json.environments;
                },
                // failure function
                function(json) {
                    $scope.hideLoading()
                    NeuMaticService.ajaxError(json, '/chef/getEnvironmentsDetailed?chef_server=' + $scope.chefServer)
                }
            );
        }

        $scope.deleteEnvironment = function(environmentName) {
            var data = $.param({
                environmentName: environmentName
            });

            $scope.modal = {
                title: 'Delete Environment?',
                message: 'Are you sure you want to delete the environment ' + environmentName + '?<br>' +
                'This is probably a very bad idea.',
                yesCallback: function() {
                    $http({
                        method: 'POST',
                        url: '/chef/deleteEnvironment/' + environmentName + '?chef_server=' + $scope.chefServer,
                        data: data,
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        }
                    }).success(function() {
                        //location = "/#/environments";
                        $scope.getEnvironments();
                    });
                }
            };
            $('#promptModal').modal('show');
        };

        $scope.go = function(path) {

            $location.path(path);
        };

        // on page load

        $scope.getChefServers();
        $scope.$watch("chefServer", function(newValue, oldValue) {
            $scope.getEnvironments();
        });

    });

