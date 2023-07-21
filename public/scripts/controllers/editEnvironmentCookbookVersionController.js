angular.module('NeuMatic')

    .controller('EditEnvironmentCookbookVersionCtrl', function($scope, $stateParams, $log, $http, AuthedUserService, JiraService, AlertService) {


        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = $scope.authedUserService.authedUser;

        $scope.jiraService = JiraService;
        $scope.alertService = AlertService;


        $scope.alert = {
            show: false,
            title: 'Error!',
            type: 'danger',
            message: ''
        };

        $scope.modal = {
            title: '',
            message: ''
        };

        $scope.back = function() {
            history.back();
        };

        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            var expires = "expires=" + d.toGMTString();
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

        $scope.environmentName = $stateParams.env;

        $scope.cookbookName = $stateParams.cb;

        $scope.rowClass = "col-md-6";
        $scope.labelClass = "col-lg-3";
        $scope.inputClass = "col-lg-9";

        $scope.cookbooksLoading = true;
        $scope.versionsLoading = false;

        //**************************************************************

        $scope.disableEditCookbook = false;


        if ($scope.cookbookName != 'new') {
            $scope.disableEditCookbook = true;
            $scope.cookbookSelected = new Object();
            $scope.cookbookSelected.name = $scope.cookbookName;

            $http.get('/chef/getCookbook/' + $scope.cookbookName + '?chef_server=' + $scope.chefServer).success(function(data) {
                $scope.cbtemp = data.cookbook.versions;
                $scope.cookbookSelected.versions = [];
                angular.forEach(data.cookbook.versions, function(value, key) {

                    $scope.cookbookSelected.versions.push(value.version);
                });

            });
            $http.get('/chef/getEnvironmentCookbookVersion/' + $scope.environmentName + '/' + $scope.cookbookName + '?chef_server=' + $scope.chefServer).success(function(cookbookVersionData) {

                $scope.cookbookVersion = cookbookVersionData.version;
                $scope.cookbookVersionOperator = cookbookVersionData.operator;
                $scope.operatorSelected = $scope.cookbookVersionOperator;
                $scope.versionSelected = $scope.cookbookVersion;
            });
            $scope.cookbooks = [];
            $scope.cookbooks.push($scope.cookbookSelected);
            $scope.cookbooksLoading = false;
        }


        $http.get('/chef/getEnvironment/' + $scope.environmentName + '?chef_server=' + $scope.chefServer).success(function(data) {
            // check return status
            if (typeof data.success !== "undefined" && !data.success && data.environment !== 'undefined') {
                $scope.ajaxError(data);
                return;
            }

            $scope.environmentCookbookVersions = [];

            angular.forEach(data.environment.cookbook_versions, function(value, key) {
                $scope.environmentCookbookVersions.push(key);
            });

        });

        if ($scope.cookbookName == 'new') {
            $http.get('/chef/getCookbooks?chef_server=' + $scope.chefServer).success(function(cookbooksData) {

                $scope.cookbooks = cookbooksData.cookbooks;

                angular.forEach($scope.cookbooks, function(value, key) {
                    if ($scope.environmentCookbookVersions.indexOf(value.name) >= '0' && $scope.cookbookName == 'new') {
                        $scope.cookbooks.splice(key, 1);
                    }
                    if ($scope.cookbookName === value.name) {
                        $scope.cookbookSelected = $scope.cookbooks[key];
                        return false;
                    }

                });
                $scope.cookbooksLoading = false;

            }).error(function(cookbooksData) {
                $scope.ajaxError(cookbooksData);
            });
        }
        $scope.environmentCookbookVersionSaveButtonDisabled = true;

        $scope.$watch('authorizedEdit', function(newValue, oldValue) {
            if (newValue === oldValue) {
                return;
            }
            if ($scope.authorizedEdit == true && $scope.environmentName != "_default") {
                $scope.environmentCookbookVersionSaveButtonDisabled = false;

            } else {
                $scope.environmentCookbookVersionSaveButtonDisabled = true;

            }
        });

        $scope.saveCookbookVersion = function() {
            var data = $.param({});

            if ($scope.operatorSelected == '=') {
                $scope.operatorText = "eq";
            } else if ($scope.operatorSelected == '>') {
                $scope.operatorText = "gt";
            } else if ($scope.operatorSelected == '<') {
                $scope.operatorText = "lt";
            } else if ($scope.operatorSelected == '<=') {
                $scope.operatorText = "lteq";
            } else if ($scope.operatorSelected == '>=') {
                $scope.operatorText = "gteq";
            }

            $http({
                method: 'POST',
                url: '/chef/editEnvironmentCookbookVersion/' + $scope.environmentName + '/' + $scope.cookbookSelected.name + '/' + $scope.operatorText + '/' + $scope.versionSelected + '?chef_server=' + $scope.chefServer,
                data: data,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                }
            }).success(function() {
                //alert("saved");
                location = "/#/environments/" + $scope.environmentName;
            });
        };

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
    });
