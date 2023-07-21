angular.module('NeuMatic')
    .factory('JiraService', function($log, $modal, $http, AlertService) {

            var alertService = AlertService,
                issueTypes;

            var ModalInstanceCtrl = function($scope, $modalInstance, bugReport) {

                $scope.bugReport = bugReport;
                $http.get('/jira/getIssueTypes')
                    .success(function(data) {
                        issueTypes = data.issueTypes;
                        $scope.issueTypes = data.issueTypes;
                        $scope.bugReport.issueType = data.issueTypes[0];
                    });


                $scope.submit = function() {
                    $modalInstance.close($scope.bugReport);
                };

                $scope.cancel = function() {
                    $modalInstance.dismiss($scope);
                };
            };

            return {
                getIssueTypes: function() {
                    return issueTypes;
                },

                openBugReport: function() {
                    var modalInstance = $modal.open({
                        templateUrl: 'views/templates/bug_report.html',
                        controller: ModalInstanceCtrl,
                        resolve: {
                            bugReport: function() {
                                return {
                                    summary: '',
                                    description: '',
                                    acceptanceCriteria: ''
                                };
                            }
                        }
                    });

                    modalInstance.result.then(function(bugReport) {
                        // create a data structure with all the values of the server
                        var data = $.param({
                            issueTypeId: bugReport.issueType.id,
                            summary: bugReport.summary,
                            description: bugReport.description,
                            acceptanceCriteria: bugReport.acceptanceCriteria
                        });

                        // call a POST to the neumatic controller passing all the server values
                        $http({
                            method: 'POST',
                            url: '/jira/submitBugReport',
                            data: data,
                            // content type is required here so that the data is formatted correctly
                            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}})

                            // on success, return to the servers page
                            .success(function(json) {
                                if (typeof json.success !== "undefined" && !json.success) {
                                    alertService.ajaxAlert({
                                        apiUrl: '/jira/submitBugReport',
                                        json: json
                                    });
                                } else {
                                    var key = json.key,
                                        link = 'https://jira.nexgen.neustar.biz/browse/' + key;
                                    alertService.showAlert({
                                        type: 'success',
                                        title: 'Info',
                                        message: 'JIRA issue <a href="' + link + '" title="Click to go to JIRA issue" target="_blank">' + key + '</a> has been created.'
                                    });
                                }
                            })
                            // on success, return to the servers page
                            .error(function(json) {
                                alertService.ajaxAlert({
                                    apiUrl: '/jira/submitBugReport',
                                    json: json
                                });
                            });
                    }, function() {
                        // don't really have anything to do since the user canceled
                    });
                }
            };
        })
