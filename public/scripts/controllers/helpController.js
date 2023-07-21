angular.module('NeuMatic')

    .controller('HelpCtrl', function($scope, $q, $http, $log, $modal, AuthedUserService, AlertService, JiraService) {
        $scope.nav = {help: true};

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;
    });

