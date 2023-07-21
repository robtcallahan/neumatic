angular.module('NeuMatic')

    .controller('EditNodeCtrl', function($scope, $stateParams, $log, $http, AuthedUserService, JiraService, AlertService) {

        $scope.ajaxError = "";

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
            //var expires = "expires=" + d.toGMTString();
            var expires = "expires=" + d.toUTCString();
            document.cookie = cname + "=" + cvalue + "; " + expires;
        }

        function getCookie(cname) {
            var name = cname + "=";
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i].trim();
                if (c.indexOf(name) == 0) {
                    return c.substring(name.length, c.length);
                }
            }
            return "";
        }

        if (typeof $stateParams.chef_server != 'undefined') {
            $scope.chefServer = $stateParams.chef_server;
        } else {
            $scope.chefServer = getCookie('chef_server');
        }

        $scope.rowClass = "col-md-6";
        $scope.labelClass = "col-lg-3";
        $scope.inputClass = "col-lg-9";

        /****************************************************************************/

        $scope.nodeName = $stateParams.id;

        $scope.getNode = function() {
            $scope.nodeLoading = true;

            $http.get('/chef/getNode/' + $scope.nodeName + '?chef_server=' + $scope.chefServer).success(function(data) {

                $scope.node = data.node;
                $scope.environment = $scope.node.chef_environment;
                $scope.attributesTree = returnTreeChildren($scope.node.automatic);
                $scope.nodeLoading = false;

                $scope.environmentsLoading = true;

                $scope.runlist = [];
                angular.forEach($scope.node.run_list, function(value, key) {
                    value = value.replace(']', '');
                    var runItem = new Object();
                    var runItemSplit = value.split("[");
                    runItem.type = runItemSplit[0];
                    runItem.name = runItemSplit[1];
                    $scope.runlist[key] = runItem
                });
                $http.get('/chef/getEnvironments?chef_server=' + $scope.chefServer).success(function(data) {

                    $scope.environments = data.environments;

                    angular.forEach($scope.environments, function(value, key) {
                        if ($scope.environment === value.name) {
                            $scope.environmentSelected = $scope.environments[key];
                            return false;
                        }
                        return true;
                    });
                    $scope.environmentsLoading = false;

                });
            });
        };

        $scope.getRoles = function() {
            $http.get('/chef/getRoles?chef_server=' + $scope.chefServer).success(function(data) {

                $scope.roles = [];
                angular.forEach(data.roles, function(value, key) {
                    var role = new Object();
                    role.name = value;
                    role.type = "role";

                    $scope.roles[key] = role;

                });

            });
        }

        $scope.getRecipes = function() {
            $http.get('/chef/getAllRecipes?chef_server=' + $scope.chefServer).success(function(data) {

                $scope.recipes = [];
                angular.forEach(data.recipes, function(value, key) {
                    var recipe = new Object();
                    recipe.name = value;
                    recipe.type = "recipe";

                    $scope.recipes[key] = recipe;

                });

            });
        }
        $scope.saveNode = function() {

            $http.get('/chef/setNodeEnvironment/' + $scope.nodeName + '/' + $scope.environmentSelected.name + '?chef_server=' + $scope.chefServer).success(function(data) {
                // check return status
                if (typeof data.success !== "undefined" && !data.success) {
                    $scope.ajaxError(data);
                }

                $scope.saverunlist = [];
                angular.forEach($scope.runlist, function(value, key) {
                    var runitem = value.type + "[" + value.name + "]";
                    $scope.saverunlist.push(runitem);
                });

                var data = $.param({
                    runList: $scope.saverunlist
                });

                $http({
                    method: 'POST',
                    url: '/chef/setNodeRunList/' + $scope.nodeName + '?chef_server=' + $scope.chefServer,
                    data: data,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    }
                }).success(function() {

                    //alert("Node Saved.");
                    //location = "/#/environments/" + $scope.environment;
                    window.history.back();

                });

            });

        };

        $scope.dropSuccessHandler = function($event, index, array) {
            array.splice(index, 1);
        };

        $scope.onDrop = function($event, $data, array) {
            array.push($data);
        };

        /****************  Attributes **********************************************/

        function returnTreeChildren(treeNode) {
            var children = [];
            angular.forEach(treeNode, function(value, key) {
                var child = {};
                if ((value !== null && typeof value === 'object') || (value === 'object')) {
                    child['key'] = key;
                    child['type'] = 'object';

                    if (value === 'object') {
                        child['children'] = {};

                    } else {
                        child['children'] = returnTreeChildren(value, child['position']);

                    }

                    this.push(child);

                } else if (value !== null && typeof value === 'string') {
                    child['key'] = key;
                    child['type'] = 'string';
                    child['value'] = value;

                    this.push(child);
                }
            }, children);
            return children;
        }


        $scope.treeOptions = {
            nodeChildren: "children",
            dirSelectable: true,
            injectClasses: {
                ul: "a1",
                li: "a2",
                liSelected: "a7",
                iExpanded: "a3",
                iCollapsed: "a4",
                iLeaf: "a5",
                label: "a6",
                labelSelected: "a8"
            }
        };

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
        /***************** On Load ***********************************************/
        $scope.getNode();
        $scope.getRoles();
        $scope.getRecipes();
    });
