angular.module('NeuMatic')

    .controller('EditRoleCtrl', function($scope, $stateParams, $log, $http, $location, AuthedUserService, JiraService, AlertService) {

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

        $scope.getChefServers = function() {
            $http.get('/chef/getServers').success(function(data) {
                $scope.chefServers = [];

                if ($scope.chefServer == "") {
                    $scope.chefServer = data.servers[0].name;
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
            $scope.getRoles();
        };

        $scope.showLoading = function() {
            $('#loading-spinner').show();
        };

        $scope.hideLoading = function() {
            $('#loading-spinner').hide();
        };

        /**********************************************************************************/

        $scope.roleName = $stateParams.id;

        $scope.getRole = function() {
            $http.get('/chef/getRole/' + $scope.roleName + '?chef_server=' + $scope.chefServer).success(function(data) {

                $scope.role = data.role;

                $scope.roleDescription = data.role.description;
                $scope.ownerGroup = data.role.default_attributes.ownerGroup;
                $scope.nodes = data.role.nodeList;
                $scope.nodeCount = data.role.nodeCount;
                $scope.runlist = [];
                $scope.roleNameInputDisabled = true;
                angular.forEach(data.role.run_list, function(value, key) {
                    value = value.replace(']', '');
                    var runItem = new Object();
                    var runItemSplit = value.split("[");
                    runItem.type = runItemSplit[0];
                    runItem.name = runItemSplit[1];
                    $scope.runlist[key] = runItem
                });

                if (angular.isDefined(data.role.default_attributes)) {
                    var treePosition = 0;
                    $scope.defaultAttributesTree = returnTreeChildren(data.role.default_attributes, treePosition);
                }

                if (angular.isDefined(data.role.override_attributes)) {
                    var treePosition = 0;
                    $scope.overrideAttributesTree = returnTreeChildren(data.role.override_attributes, treePosition);
                }
                $scope.getGroups();
                $scope.hideLoading();
            });
        }

        $scope.saveRole = function(redirect) {
            if (typeof redirect === "undefined") {
                redirect = true;
            }
            $scope.newDefaultAttributeTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);
            $scope.newOverrideAttributeTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);

            $scope.saverunlist = [];
            angular.forEach($scope.runlist, function(value, key) {
                var runitem = value.type + "[" + value.name + "]";
                $scope.saverunlist.push(runitem);
            });


            var data = $.param({
                roleName: $scope.roleName,
                roleDescription: $scope.roleDescription,
                ownerGroup: $scope.ownerGroupSelected,
                newRole: $scope.newRole,
                default_attributes: $scope.newDefaultAttributeTree,
                override_attributes: $scope.newOverrideAttributeTree,
                run_list: $scope.saverunlist
            });

            $http({
                method: 'POST',
                url: '/chef/saveRole?chef_server=' + $scope.chefServer,
                data: data,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                }
            }).success(function() {
                if (redirect == true) {
                    //alert("Role Saved");

                    location = "/#/roles";
                } else {
                    $scope.defaultAttributesTreeHidden = false;
                    $scope.overrideAttributesTreeHidden = false;
                    setTimeout(function() {
                        $scope.getRole();
                    }, 500);

                }

            });

        };

        /**************** Copy Role *******************************************************/

        $scope.showCopyRoleOverlay = false;

        $scope.showCopyRoleOverlayBox = function() {
            $scope.showCopyRoleOverlay = true;

        }

        $scope.hideCopyRoleOverlayBox = function() {
            $scope.showCopyRoleOverlay = false;

        }
        $chefServers = $scope.getChefServers();

        $scope.saveRoleCopy = function(redirect) {
            if (typeof redirect === "undefined") {
                redirect = true;
            }
            $scope.newDefaultAttributeTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);
            $scope.newOverrideAttributeTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);

            $scope.saverunlist = [];
            angular.forEach($scope.runlist, function(value, key) {
                var runitem = value.type + "[" + value.name + "]";
                $scope.saverunlist.push(runitem);
            });

            var data = $.param({
                roleName: $scope.copyName,
                roleDescription: $scope.roleDescription,
                newRole: true,
                default_attributes: $scope.newDefaultAttributeTree,
                override_attributes: $scope.newOverrideAttributeTree,
                run_list: $scope.saverunlist
            });

            $http({
                method: 'POST',
                url: '/chef/saveRole?chef_server=' + $scope.copyRoleChefServer.name,
                data: data,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                }
            }).success(function() {

                location = "/#/roles";
            });

        };

        /**************** Default Attributes **********************************************/

        $scope.defaultAttributeType = "string";

        $scope.showAddDefaultAttribute = false;

        $scope.showAddDefaultAttributeBox = function(node) {
            $scope.hideEditDefaultAttributeBox();
            $scope.showAddDefaultAttribute = true;

            if (node === 'root') {
                $scope.currentNode = $scope.defaultAttributesTree;
                $scope.isRootNode = true;
            } else {
                $scope.currentNode = node;
                $scope.isRootNode = false;
                $scope.parentNode = angular.copy(node);
            }

            $scope.newNode = {
                'key': '',
                'value': '',
                'type': 'string'
            };

        }
        $scope.hideAddDefaultAttributeBox = function(node) {
            $scope.showAddDefaultAttribute = false;
        }

        $scope.showEditDefaultAttribute = false;

        $scope.showEditDefaultAttributeBox = function(node) {
            $scope.hideAddDefaultAttributeBox();
            $scope.showEditDefaultAttribute = true;

            $scope.currentNode = node;

            $scope.newNode = angular.copy(node);

        }
        $scope.hideEditDefaultAttributeBox = function() {
            $scope.showEditDefaultAttribute = false;
        }

        $scope.saveNewDefaultAttribute = function() {
            if ($scope.isRootNode == false) {
                if (typeof $scope.parentNode.children === "undefined") {

                    $scope.parentNode.children = [];
                }
                if ($scope.newNode.type == 'object') {
                    delete $scope.newNode.value;
                }

                $scope.parentNode['children'][$scope.parentNode.children.length] = $scope.newNode;

                $scope.currentNode.children = $scope.parentNode.children;
            } else {
                if ($scope.newNode.type == 'object') {
                    $scope.newNode.value = 'object';
                    $scope.newNode.children = []
                }
                $scope.currentNode.push($scope.newNode);
            }

            $scope.saveDefaultAttributesTree();

            $scope.hideAddDefaultAttributeBox();
        }

        $scope.saveDefaultAttributesCurrentNode = function() {

            $scope.currentNode.key = $scope.newNode.key;
            $scope.currentNode.value = $scope.newNode.value;
            $scope.saveDefaultAttributesTree();
            $scope.hideEditDefaultAttributeBox();
        }

        $scope.saveDefaultAttributesTree = function() {
            //$scope.newDefaultAttributeTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);
            //$scope.newOverrideAttributeTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);

            $scope.saveRole(false);
        }

        $scope.deleteDefaultAttributesNode = function(node) {

            if (confirm('Are you sure you wish to delete this attribute?')) {
                for (var member in node) {
                    delete node[member];
                }
                //$scope.newDefaultAttributeTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);
                //$scope.newOverrideAttributeTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);
                $scope.saveRole(false);
            }

        }
        /**************** Override Attributes ***********************************************/

        $scope.overrideAttributeType = "string";

        $scope.showAddOverrideAttribute = false;

        $scope.showAddOverrideAttributeBox = function(node) {
            $scope.hideEditOverrideAttributeBox();
            $scope.showAddOverrideAttribute = true;

            if (node === 'root') {
                $scope.currentNode = $scope.overrideAttributesTree;
                $scope.isRootNode = true;
            } else {
                $scope.currentNode = node;
                $scope.isRootNode = false;
                $scope.parentNode = angular.copy(node);
            }

            $scope.newNode = {
                'key': '',
                'value': '',
                'type': 'string'
            };

        }
        $scope.hideAddOverrideAttributeBox = function(node) {
            $scope.showAddOverrideAttribute = false;
        }

        $scope.showEditOverrideAttribute = false;

        $scope.showEditOverrideAttributeBox = function(node) {
            $scope.hideAddOverrideAttributeBox();
            $scope.showEditOverrideAttribute = true;

            $scope.currentNode = node;

            $scope.newNode = angular.copy(node);

        }
        $scope.hideEditOverrideAttributeBox = function() {
            $scope.showEditOverrideAttribute = false;
        }

        $scope.saveNewOverrideAttribute = function() {
            if ($scope.isRootNode == false) {
                if (typeof $scope.parentNode.children === "undefined") {

                    $scope.parentNode.children = [];
                }
                if ($scope.newNode.type == 'object') {
                    delete $scope.newNode.value;
                }

                $scope.parentNode['children'][$scope.parentNode.children.length] = $scope.newNode;

                $scope.currentNode.children = $scope.parentNode.children;
            } else {
                if ($scope.newNode.type == 'object') {
                    $scope.newNode.value = 'object';
                    $scope.newNode.children = []
                }
                $scope.currentNode.push($scope.newNode);
            }

            $scope.saveOverrideAttributesTree();

            $scope.hideAddOverrideAttributeBox();
        }

        $scope.saveOverrideAttributesCurrentNode = function() {

            $scope.currentNode.key = $scope.newNode.key;
            $scope.currentNode.value = $scope.newNode.value;
            $scope.saveOverrideAttributesTree();
            $scope.hideEditOverrideAttributeBox();
        }

        $scope.saveOverrideAttributesTree = function() {

            //$scope.newOverrideAttributeTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);
            //$scope.newDefaultAttributeTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);
            $scope.saveRole(false);
        }

        $scope.deleteOverrideAttributesNode = function(node) {

            if (confirm('Are you sure you wish to delete this attribute?')) {
                for (var member in node) {
                    delete node[member];
                }
                //$scope.newOverrideAttributeTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);
                //$scope.newDefaultAttributeTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);
                $scope.saveRole(false);
            }
        }
        /**************** Tree ***************************************************************/

        $scope.rebuildAttributeTree = function(treeNode) {
            var newTreeNode = {};

            angular.forEach(treeNode, function(node) {

                if (node.type == 'string') {
                    this[node.key] = node.value;

                } else if (node.type == 'object') {
                    if (typeof node.children !== "undefined" && node.children.length != 0) {
                        this[node.key] = $scope.rebuildAttributeTree(node.children);
                    } else {
                        this[node.key] = 'object';
                    }

                }
            }, newTreeNode);

            return newTreeNode;
        }
        function returnTreeChildren(treeNode, treePosition) {
            var children = [];
            angular.forEach(treeNode, function(value, key) {
                var child = {};
                if ((value !== null && typeof value === 'object') || (value === 'object')) {
                    child['key'] = key;
                    child['type'] = 'object';

                    //var positionIndex = children.length;

                    //child['position'] = treePosition + '.' + positionIndex;

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
                    var positionIndex = children.length;
                    child['position'] = treePosition + '.' + positionIndex;
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

        /************ Runlist ****************************************************************/

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

        $scope.getGroups = function() {
            $scope.ownerGroupsLoading = true;
            $http.get('/ldap/getUsergroupList').success(function(data) {

                $scope.userGroups = data.usergroups;
                $scope.ownerGroups = [];

                angular.forEach($scope.userGroups, function(value, key) {

                    $scope.ownerGroups.push(value);

                    if ($scope.ownerGroup === value) {

                        $scope.ownerGroupSelected = $scope.userGroups[key];

                    }
                });
                $scope.ownerGroupsLoading = false;
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
        /*************************************************************************************/
        $scope.dropSuccessHandler = function($event, index, array) {
            array.splice(index, 1);
        };

        $scope.onDrop = function($event, $data, array) {
            array.push($data);
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

        $scope.go = function(path) {
            $location.path(path);
        };

        $scope.back = function() {
            history.back();
        };

        /**************** On Load *************************************************************/
        $scope.roleNameInputDisabled = true;
        if ($scope.roleName == 'new') {
            $scope.roleName = "";
            $scope.roleDescription = "";
            $scope.runlist = [];
            $scope.defaultAttributesTreeHidden = true;
            $scope.overrideAttributesTreeHidden = true;
            $scope.defaultAttributeTree = [];
            $scope.overrideAttributeTree = [];
            $scope.newRole = true;
            $scope.roleNameInputDisabled = false;

        } else {

            $scope.getRole();

        }

        $scope.getRoles();

        $scope.getRecipes();
        $scope.getGroups();
    });

