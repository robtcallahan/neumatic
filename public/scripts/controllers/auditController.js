angular.module('NeuMatic')

    .controller('AuditCtrl', function($scope, $log, $http, $stateParams, AuthedUserService, JiraService, AlertService) {

        $scope.$log = $log;

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        $scope.modal = {
            title: '',
            message: '',
            yesCallback: function() {
            },
            noCallback: function() {
            }
        };

        $scope.$stateParams = $stateParams;
        var serverId = parseInt($stateParams.id);

        // sort info
        $scope.predicate = "dateTime";
        $scope.reverse = false;

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
        function getAuditLog() {
            $scope.showLoading();

            //noinspection JSValidateTypes
            $http.get('/neumatic/getAuditLogEntries/' + serverId)
                .success(function(json) {
                    $scope.hideLoading();
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getAuditLogEntries/' + serverId
                        });
                    } else {
                        $scope.logEntries = json.logEntries;
                        $scope.title = json.serverName + ": Audit Log";
                    }
                })
                .error(function(json) {
                    $scope.hideLoading();
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getAuditLogEntries/' + serverId
                    });
                });
        }

        getAuditLog();

    });

