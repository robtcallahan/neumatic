angular.module('NeuMatic')

    .controller('BuildsReportCtrl', function($scope, $log, $http, $stateParams, AuthedUserService, JiraService, AlertService) {

        $scope.$log = $log;

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        $scope.nav = {reports: true};

        $scope.modal = {
            title: '',
            message: '',
            yesCallback: function() {
            },
            noCallback: function() {
            }
        };

        // sort info
        $scope.predicate = "dateBuilt";
        $scope.reverse = true;

        // ----------------------------------------------------------------------------------------------------
        // General purpose functions
        // ----------------------------------------------------------------------------------------------------

        $scope.showLoading = function() {
            $('#loading-spinner').show();
        };

        $scope.hideLoading = function() {
            $('#loading-spinner').hide();
        };

        // ----------------------------------------------------------------------------------------------------
        // On Page Load
        // ----------------------------------------------------------------------------------------------------

        // get the servers for display
        function getBuildMetrics() {
            $scope.showLoading();

            //noinspection JSValidateTypes
            $http.get('/neumatic/getBuildMetrics')
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getBuildMetrics'
                        });
                    } else {
                        $scope.hideLoading();
                        $scope.buildMetrics = json.buildMetrics;
                        $scope.avgBuildTimes = json.avgBuildTimes;
                        $scope.title = "Build Metrics";
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getBuildMetrics'
                    });
                });
        }

        getBuildMetrics();

    });

