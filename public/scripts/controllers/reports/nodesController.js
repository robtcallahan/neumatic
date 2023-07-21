angular.module('NeuMatic')

    .filter("calculateIndexField", function() {
        var count = 0;
        return function() {
            count++;
            return count;
        };
    })

    .controller('NodesReportCtrl', function($scope, $log, $http, $stateParams, AuthedUserService, JiraService, AlertService, filterFilter, pagingFilter) {

        $scope.index = 0;

        $scope.nav = {reports: true};

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        $scope.modal = {
            title: '',
            message: ''
        };

        $scope.$stateParams = $stateParams;

        $scope.processing = false;
        $scope.showKeyAndProcedure = false;

        $scope.loading = false;

        // Searching, paging and sorting properties
        $scope.nodes = [];
        $scope.pagedData = [];

        /**
         * On Page Load
         */
        showLoading();
        //noinspection JSValidateTypes
        $http.get('/reports/getNodes')
            .success(function(json) {
                hideLoading();
                if (typeof json.success !== "undefined" && !json.success) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/reports/nodes/'
                    });
                } else {
                    $scope.nodes = json.nodes;
                    $scope.sortField = "name";
                    $scope.sortReverse = false;
                }
            })
            .error(function(json) {
                AlertService.ajaxAlert({
                    json: json,
                    apiUrl: '/reports/nodes/'
                });
            });

        // ----------------------------------------------------------------------------------------------------
        // Error processing functions
        // ----------------------------------------------------------------------------------------------------

        function showLoading() {
            $('#loading-spinner').show();
        }

        function hideLoading() {
            $('#loading-spinner').hide();
        }
    });


