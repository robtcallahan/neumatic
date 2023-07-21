angular.module('NeuMatic')

    .controller('EditCookbookCtrl', function($scope, $stateParams, $log, $http, AuthedUserService, JiraService, AlertService) {

        $scope.ajaxError = "";

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = $scope.authedUserService.authedUser;

        $scope.jiraService = JiraService;
        $scope.alertService = AlertService;

        $scope.authorizedEdit = false;
        if ($scope.authedUser.adminOn == true) {
            $scope.authorizedEdit = true;
        }

        $scope.$watch('authedUser.adminOn', function(newValue, oldValue) {
            if (newValue === oldValue) {
                return;
            }
            if ($scope.authedUser.adminOn == true) {
                $scope.authorizedEdit = true;
            } else {
                $scope.authorizedEdit = false;
            }
        });

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

        $scope.rowClass = "col-md-6";
        $scope.labelClass = "col-lg-3";
        $scope.inputClass = "col-lg-9";

        $scope.showLoading = function() {
            $('#loading-spinner').show();
        };

        $scope.hideLoading = function() {
            $('#loading-spinner').hide();
        };
        /**************************************************************************/

        $scope.cookbookName = $stateParams.id;
        $scope.showLoading();
        $scope.checkAuthorized = function() {
            $http.get('/chef/checkAuthorizedCookbookEdit/' + $scope.cookbookName + '?chef_server=' + $scope.chefServer).success(function(data) {
                if (typeof data.success !== "undefined" && !data.success) {
                    $scope.ajaxError(data);
                    return;
                }
                $scope.authorizedEdit = data.authorized;

            });
        }
        $scope.getCookbookVersions = function() {
            // get the Cookbook Versions
            $http.get('/chef/getCookbookVersions/' + $scope.cookbookName + '?chef_server=' + $scope.chefServer).success(function(data) {
                // check return status
                if (typeof data.success !== "undefined" && !data.success && data.versions !== 'undefined') {
                    $scope.ajaxError(data);
                    return;
                }

                $scope.versions = data.versions;
                if (typeof data.versions !== "undefined" && !data.success) {
                    $scope.versionsCount = data.versions.length;
                } else {
                    $scope.versionsCount = 0;
                }

            }).error(function(data) {
                $scope.ajaxError(data);
            });
        }

        $scope.getProjectDetails = function() {
            $http.get('/git/getProjectDetails/' + $scope.cookbookName + '?chef_server=' + $scope.chefServer).success(function(data) {
                $scope.gitProject = data.project;

                $scope.cookbookGitId = $scope.gitProject.id;
            });

        }

        $scope.getCookbookTags = function() {
            $http.get('/git/getProjectTags/' + $scope.cookbookName + '?chef_server=' + $scope.chefServer).success(function(data) {
                $scope.cookbookGitTags = data.tags;
                if ($scope.cookbookGitTags.length < '11') {
                    $scope.hideShowAllTags = true;
                    $scope.hideShowTenTags = true;
                } else {
                    $scope.hideShowAllTags = false;
                    $scope.hideShowTenTags = true;
                }
                $scope.hideLoading();
            });
        }
        /***** show/hide buttons *****/

        $scope.getCookbookVersions();
        $scope.versionsLimit = 10;
        $scope.tagsLimit = 10;

        $scope.hideShowAllVersions = true;
        $scope.hideShowTenVersions = true;
        $scope.hideShowAllTags = false;
        $scope.hideShowTenTags = true;

        $scope.toggleAllVersions = function() {
            if ($scope.versionsLimit == '10') {

                $scope.versionsLimit = '9999';
                $scope.hideShowAllVersions = true;
                $scope.hideShowTenVersions = false;
            } else {
                $scope.versionsLimit = '10';
                $scope.hideShowAllVersions = false;
                $scope.hideShowTenVersions = true;
            }
        }

        $scope.toggleAllTags = function() {
            if ($scope.tagsLimit == '10') {

                $scope.tagsLimit = '9999';
                $scope.hideShowAllTags = true;
                $scope.hideShowTenTags = false;
            } else {
                $scope.TagsLimit = '10';
                $scope.hideShowAllTags = false;
                $scope.hideShowTenTags = true;
            }
        }
        /*****   *****/

        $scope.deleteCookbookVersion = function(version) {
            if (confirm("Delete version " + version + "? This will probably break something!")) {
                $scope.showLoading();
                $http.get('/chef/deleteCookbookVersion/' + $scope.cookbookName + '/' + version + '?chef_server=' + $scope.chefServer).success(function(data) {
                    // check return status
                    if (typeof data.success !== "undefined" && !data.success) {
                        $scope.ajaxError(data);
                        $scope.hideLoading();
                        return;
                    }

                    $scope.getCookbookVersions();
                    $scope.hideLoading();
                });

            }
        };

        $scope.importGitTag = function(tagName) {

            $scope.showLoading();
            $http.get('/git/importGitTag/' + $scope.cookbookGitId + '/' + tagName + '?chef_server=' + $scope.chefServer + '&git_group=neustar-cookbooks')
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/git/importGitTag'
                        });
                        $scope.hideLoading();
                        return;
                    }
                    $scope.getCookbookVersions();
                    $scope.hideLoading();
                    return;
                })
                .error(function(json) {
                    $scope.hideLoading();
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/git/importGitTag'
                    });
                    return;
                });

        };


        $scope.getCookbookTags();
        $scope.getProjectDetails();
        $scope.checkAuthorized();
        /**************************************************************************/

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
