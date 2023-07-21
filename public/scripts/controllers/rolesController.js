angular.module('NeuMatic')

    .controller('RolesCtrl', function($scope, $stateParams, $log, $http, $location, AuthedUserService, JiraService, AlertService) {

        $scope.ajaxError = "";

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = $scope.authedUserService.authedUser;

        $scope.jiraService = JiraService;
        $scope.alertService = AlertService;

        $scope.nav = {
            roles: true
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

        if (typeof $stateParams.chef_server != 'undefined') {
            $scope.chefServer = $stateParams.chef_server;
        } else {
            $scope.chefServer = getCookie('chef_server');
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
            //$scope.getRoles();
        };

        $scope.showLoading = function() {
            $('#loading-spinner').show();
        };

        $scope.hideLoading = function() {
            $('#loading-spinner').hide();
        };

        /**************************************************************************************/

        $scope.getRoles = function() {
            $http.get('/chef/getRolesWithDetails?chef_server=' + $scope.chefServer).success(function(data) {

                $scope.roles = data.roles;
                $scope.hideLoading();
            });
        }

        $scope.deleteRole = function(roleName) {
            var data = $.param({
                roleName: roleName
            });

            var confirmDelete = confirm("Are you sure you want to delete the role " + roleName + "? This is probably a very bad idea.");

            if (confirmDelete == true) {

                $http({
                    method: 'POST',
                    url: '/chef/deleteRole/' + roleName + '?chef_server=' + $scope.chefServer,
                    data: data,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    }
                }).success(function() {

                    alert("Role Deleted");
                    //location = "/#/environments";
                    $scope.getRoles();

                });
            }
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

        /************** On Load **************************************************************/

        $scope.getChefServers();
        $scope.$watch("chefServer", function(newValue, oldValue) {
            $scope.getRoles();
        });

    });
