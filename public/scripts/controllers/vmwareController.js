angular.module('NeuMatic')

    .controller('VMWareCtrl', function($scope, $log, $http, $location, AuthedUserService, NeuMaticService, JiraService, AlertService, nodeService) {
        $scope.nav = {admin: true};

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        // NeuMatic service
        NeuMaticService.setScope($scope);

        // nodeService keeps our node's information so that editServiceController can use it
        $scope.nodeService = nodeService;
        $scope.nodeService.setDefined(false);

        $scope.modal = {
            title: '',
            message: ''
        };

        $scope.dataCenters = [];
        $scope.selected = {name: "Nothing selected"};
        $scope.hierarchy = "1,11";

        //noinspection JSValidateTypes
        $http.get('/vmware/getDatacenters')
            .success(function(json) {
                if (typeof json.success !== "undefined" && !json.success) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/vmware/getDatacenters'
                    });
                } else {
                    $scope.dataCenters = json.nodes;
                    if (json.error) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/vmware/getDatacenters'
                        });
                    }
                }
            })
            .error(function(json) {
                AlertService.ajaxAlert({
                    json: json,
                    apiUrl: '/vmware/getDatacenters'
                });
            });

        $scope.loadChildren = function(nodeId, childrenType, vSphereSite) {
            return $http.get('/vmware/get' + childrenType + '/' + nodeId + "?vSphereSite=" + vSphereSite);
        };

        $scope.$on("nodeSelected", function(event, node) {
            $scope.selected = node;
            $scope.$broadcast("selectNode", node);
        });

        // redirect to the editServer controller with the site and cluster compute resource UID values
        $scope.$on("newVm", function(event, node) {
            $scope.nodeService.setNode(node);
            window.location.href = "/#/server/0";
        });

    });

