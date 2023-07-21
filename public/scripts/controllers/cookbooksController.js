angular.module('NeuMatic')

    .controller('CookbooksCtrl', function($scope, $stateParams, $log, $http, $location, AuthedUserService, JiraService, AlertService) {

        $scope.ajaxError = "";

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = $scope.authedUserService.authedUser;

        $scope.jiraService = JiraService;
        $scope.alertService = AlertService;


        $scope.nav = {
            cookbooks: true
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
            //$scope.getCookbooks();
        };

        $scope.showLoading = function() {
            $('#loading-spinner').show();
        };

        $scope.hideLoading = function() {
            $('#loading-spinner').hide();
        };

        /**************************************************************************************/

        // get a list of cookbooks
        $scope.getCookbooks = function() {
            $scope.showLoading();
            $scope.cookbooksHash = [];

            $http.get('/chef/getCookbooks?chef_server=' + $scope.chefServer).success(function(data) {
                // check return status
                if (typeof data.success !== "undefined" && !data.success) {
                    $scope.ajaxError(data);
                    return;
                }
                $scope.cookbooks = data.cookbooks;

                for (var i = 0; i < $scope.cookbooks.length; i++) {
                    $scope.cookbooksHash[$scope.cookbooks[i].name] = $scope.cookbooks[i];
                }
                $scope.hideLoading();
            }).error(function(data) {
                $scope.ajaxError(data);
            });

        };


        /*************************************************************************************/
        $scope.ajaxError = function(data) {

            $scope.alert.show = true;
            // $scope.alert.message = "Something went terribly wrong";

            $scope.alert.message = data;
            if (typeof data.message !== "undefined") {
                $scope.alert.message = data.message.replace('\n\n', '<br>');
            }
            if (typeof data.trace !== "undefined") {
                $scope.alert.message += '<br><br>Trace:<br>' + data.trace.replace('\n\n', '<br>');
            }
        }

        $scope.go = function(path) {
            $location.path(path);
        };


        $scope.getChefServers();

        $scope.$watch("chefServer", function(newValue, oldValue) {
            if (newValue != undefined) {
                $scope.getCookbooks();
            }
        });


    });

