angular.module('NeuMatic')

    .controller('EditEnvironmentCtrl', function($scope, $stateParams, $log, $http, AuthedUserService, JiraService, AlertService, $location) {

        $scope.ajaxError = "";

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = $scope.authedUserService.authedUser;

        $scope.jiraService = JiraService;
        $scope.alertService = AlertService;

        $scope.modal = {
            title: '',
            message: ''
        };

        $scope.nav = {
            editEnvironment: true
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
        };

        $scope.showLoading = function() {
            $('#loading-spinner').show();
        };

        $scope.hideLoading = function() {
            $('#loading-spinner').hide();
        };
        /****************************************************************************/

        $scope.environmentName = $stateParams.id;

        $scope.rowClass = "col-md-6";
        $scope.labelClass = "col-lg-3";
        $scope.inputClass = "col-lg-9";

        $scope.businessServiceLoading = false;
        $scope.ownerGroupsLoading = false;
        $scope.subsystemLoading = false;
        $scope.locationLoading = false;
        $scope.timezoneLoading = false;

        $scope.timezones = ['EST5EDT', 'GMT', 'UTC', 'CST6CDT', 'MST7MDT', 'PST8PDT'];

        $scope.businessServiceSelected = "";

        $scope.ownerGroupsLoading = true;
        $http.get('/ldap/getUsergroupList').success(function(data) {

            $scope.userGroups = data.usergroups;
            $scope.ownerGroups = [];
            angular.forEach($scope.userGroups, function(value, key) {
                $scope.ownerGroups.push(value);
                //alert($scope.userGroup);
                if ($scope.userGroup === value) {
                    $scope.userGroupSelected = $scope.userGroups[key];

                }
            });
            $scope.ownerGroupsLoading = false;
        });

        $scope.getEnvironmentNodes = function() {

            $http.get('/chef/getEnvironmentNodesAndDetails/' + $scope.environmentName + '?chef_server=' + $scope.chefServer).success(function(envNodeData) {

                $scope.environmentNodes = envNodeData.nodes;
                $scope.hideLoading();
            }).error(function(envNodeData) {
                $scope.ajaxError(envNodeData);
            });
        };

        $scope.locationLoading = true;
        $scope.getLocations = function() {
            $http.get('/cmdb/getLocations').success(function(data) {

                $scope.locations = [];
                angular.forEach(data.locations, function(location) {
                    if (location.name.indexOf('Sterling-VA') >= 0 || location.name.indexOf('Charlotte-NC') >= 0 || location.name.indexOf('Denver-CO-NSR') >= 0)
                        $scope.locations.push(location);
                });
                $scope.locationSelected = "";
                angular.forEach($scope.locations, function(value, key) {

                    if ($scope.location === value.name) {

                        $scope.locationSelected = $scope.locations[key];
                        return false;
                    }
                });
                $scope.locationLoading = false;

            });
        };

        $scope.environmentTitleShow = true;
        $scope.environmentAttributesShow = true;
        $scope.environmentVersionsShow = true;
        $scope.environmentNodesShow = true;

        $scope.getEnvironment = function() {
            // get the environment
            $http.get('/chef/getEnvironment/' + $scope.environmentName + '?chef_server=' + $scope.chefServer).success(function(data) {
                // check return status
                if (typeof data.success !== "undefined" && !data.success && data.environment !== 'undefined') {
                    $scope.ajaxError(data);
                    return;
                }

                $scope.environment = data.environment;

                $scope.environmentNameInputDisabled = true;

                $scope.environmentDescription = data.environment.description;

                var treePosition;
                if (angular.isDefined(data.environment.default_attributes)) {
                    treePosition = 0;
                    $scope.defaultAttributesTree = returnTreeChildren(data.environment.default_attributes, treePosition);
                }

                if (angular.isDefined(data.environment.override_attributes)) {
                    treePosition = 0;
                    $scope.overrideAttributesTree = returnTreeChildren(data.environment.override_attributes, treePosition);
                }

                if (angular.isDefined(data.environment.default_attributes) && angular.isDefined(data.environment.default_attributes.neustar)) {

                    $scope.attributes = data.environment.default_attributes.neustar;

                    if (angular.isDefined($scope.attributes.ownerGroup)) {
                        $scope.ownerGroup = $scope.attributes.ownerGroup;
                        $scope.ownerGroupSelected = $scope.ownerGroup;

                    }
                    if (angular.isDefined($scope.attributes.business_service)) {
                        $scope.business_service = $scope.attributes.business_service;
                    }
                    if (angular.isDefined($scope.attributes.subsystem)) {
                        $scope.subsystem = $scope.attributes.subsystem;
                    }
                    if (angular.isDefined($scope.attributes.timezone)) {
                        $scope.timezone = $scope.attributes.timezone;
                    }
                    if (angular.isDefined($scope.attributes.location)) {
                        $scope.location = $scope.attributes.location;
                    }
                }

                if (angular.isDefined(data.environment.override_attributes) && angular.isDefined(data.environment.override_attributes.neustar)) {

                    $scope.attributes = data.environment.override_attributes.neustar;

                    if (angular.isDefined($scope.attributes.business_service)) {
                        $scope.business_service = $scope.attributes.business_service;
                    }
                    if (angular.isDefined($scope.attributes.subsystem)) {
                        $scope.subsystem = $scope.attributes.subsystem;
                    }
                    if (angular.isDefined($scope.attributes.timezone)) {
                        $scope.timezone = $scope.attributes.timezone;
                    }
                    if (angular.isDefined($scope.attributes.location)) {
                        $scope.location = $scope.attributes.location;
                    }
                }

                $scope.businessServiceLoading = true;


                $http.get('/cmdb/getBusinessServices').success(function(data) {

                    $scope.businessServices = data.businessServices;
                    angular.forEach($scope.businessServices, function(value, key) {
                        if ($scope.business_service === value.name) {
                            $scope.businessServiceSelected = $scope.businessServices[key];
                            return false;
                        }
                    });
                    $scope.businessServiceLoading = false;
                });


                if (angular.isDefined($scope.attributes) && angular.isDefined($scope.attributes.business_service) && angular.isDefined($scope.attributes.subsystem)) {
                    $scope.subsystemLoading = true;
                    if (typeof $scope.businessServiceSelected != 'undefined') {
                        $http.get('/cmdb/getSubsystemsByBusinessServiceName/' + $scope.business_service).success(function(data) {

                            $scope.subsystems = data.subsystems;

                            angular.forEach($scope.subsystems, function(value, key) {
                                if ($scope.subsystem === value.name) {
                                    $scope.subsystemSelected = $scope.subsystems[key];
                                    return false;
                                }
                            });
                            $scope.subsystemLoading = false;
                        });
                    }

                }
                $scope.getLocations();

                angular.forEach($scope.timezones, function(value) {
                    if ($scope.timezone === value) {
                        $scope.timezoneSelected = value;
                        return false;
                    }
                });

                $scope.environment_cookbook_versions = $scope.environment.cookbook_versions;

            }).error(function(data) {
                $scope.ajaxError(data);
            });

            $scope.getEnvironmentNodes();

        };

        if ($scope.environmentName != 'new') {
            $scope.newEnvironment = false;

            $scope.getEnvironment();

        } else {
            $scope.businessServiceLoading = true;
            $http.get('/cmdb/getBusinessServices').success(function(data) {

                $scope.businessServices = data.businessServices;
                angular.forEach($scope.businessServices, function(value, key) {
                    if ($scope.business_service === value.name) {
                        $scope.businessServiceSelected = $scope.businessServices[key];
                        return false;
                    }
                });
                $scope.businessServiceLoading = false;
            });
            $scope.environmentName = "";
            $scope.newEnvironment = true;
            $scope.environmentNameInputDisabled = false;
            $scope.environmentTitleShow = false;
            $scope.environmentVersionsShow = false;
            $scope.environmentAttributesShow = false;
            $scope.environmentNodesShow = false;
            $scope.getLocations();
        }


        $scope.$watch("businessServiceSelected", function(newValue, oldValue) {
            if (newValue === oldValue) {
                return;
            }
            if (typeof $scope.businessServiceSelected != 'undefined') {

                $scope.subsystemLoading = true;
                console.log("bsselected=" + $scope.businessServiceSelected.name);
                $http.get('/cmdb/getSubsystemsByBusinessServiceName/' + $scope.businessServiceSelected.name).success(function(data) {

                    $scope.subsystems = data.subsystems;

                    angular.forEach($scope.subsystems, function(value, key) {
                        if ($scope.subsystem === value.name) {
                            $scope.subsystemSelected = $scope.subsystems[key];
                            return false;
                        }
                    });
                    $scope.subsystemLoading = false;
                });
            }
        });

        $scope.environmentSaveButtonDisabled = true;

        $scope.$watch('authorizedEdit', function(newValue, oldValue) {
            if (newValue === oldValue) {
                return;
            }
            if ($scope.authorizedEdit == true && $scope.environmentName != "_default") {
                $scope.environmentSaveButtonDisabled = false;

            } else {
                $scope.environmentSaveButtonDisabled = true;

            }
        });

        $scope.deleteCookbookVersionConstraint = function(cookbookName) {

            if (confirm("Are you sure you want to delete the version constraint for cookbook " + cookbookName + "? This will probably break something!")) {

                $http({
                    method: 'POST',
                    url: '/chef/deleteEnvironmentCookbookVersion/' + $scope.environmentName + '/' + cookbookName + '?chef_server=' + $scope.chefServer,

                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    }
                }).success(function() {


                    $scope.getEnvironment();
                    //location = "/#/environments";

                });
            }
        };

        $scope.deleteNode = function(nodeName) {

            if (confirm("Are you sure you want to delete the node " + nodeName + " from the chef server " + $scope.chefServer + "?")) {


                $http({
                    method: 'POST',
                    url: '/chef/deleteNode/' + nodeName + '?chef_server=' + $scope.chefServer,

                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    }
                }).success(function() {

                    $scope.deleteClient(nodeName);
                });
            }
        };

        $scope.deleteClient = function(nodeName) {
            $http({
                method: 'POST',
                url: '/chef/deleteClient/' + nodeName + '?chef_server=' + $scope.chefServer,

                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                }
            }).success(function() {
                $scope.getEnvironmentNodes();
            });
        };

        $scope.saveEnvironment = function(redirect) {
            if (typeof redirect === "undefined") {
                redirect = true;
            }
            if (typeof $scope.environmentName == 'undefined' || $scope.environmentName == "") {
                alert("Environment Name is required");
                return;
            }
            if (typeof $scope.ownerGroupSelected == 'undefined' || $scope.ownerGroupSelected == "") {
                alert("Owner Group is required");
                return;
            }
            if (typeof $scope.businessServiceSelected == 'undefined' || $scope.businessServiceSelected == "") {
                alert("Business Service is required");
                return;
            }

            var data = $.param({
                environmentName: $scope.environmentName,
                environmentDescription: $scope.environmentDescription,
                environmentOwnerGroup: $scope.ownerGroupSelected,
                environmentBusinessService: $scope.businessServiceSelected,
                environmentSubsystem: $scope.subsystemSelected,
                environmentLocation: $scope.locationSelected,
                environmentTimezone: $scope.timezoneSelected,
                newEnvironment: $scope.newEnvironment,
                default_attributes: $scope.newDefaultAttributeTree,
                override_attributes: $scope.newOverrideAttributeTree
            });

            $http({
                method: 'POST',
                url: '/chef/saveEnvironment?chef_server=' + $scope.chefServer,
                data: data,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                }
            }).success(function() {
                if (redirect == true) {
                    //alert("Environment Saved");

                    window.location = "/#/environments";
                }
                //$scope.getEnvironment();
            });
        };


        $scope.saveEnvironmentCopy = function() {

            if (typeof $scope.copyName == 'undefined' || $scope.copyName == "") {
                alert("Environment Name for the new copy is required");
                return;
            }

            $scope.hideCopyEnvironmentOverlayBox();

            $scope.rebuiltDefaultAttributesTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);
            $scope.rebuiltOverrideAttributesTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);

            var data = $.param({
                environmentName: $scope.copyName,
                environmentDescription: $scope.environmentDescription,
                environmentOwnerGroup: $scope.ownerGroupSelected,
                environmentBusinessService: $scope.businessServiceSelected,
                environmentSubsystem: $scope.subsystemSelected,
                environmentLocation: $scope.locationSelected,
                environmentTimezone: $scope.timezoneSelected,
                newEnvironment: true,
                default_attributes: $scope.rebuiltDefaultAttributesTree,
                override_attributes: $scope.rebuiltOverrideAttributesTree,
                cookbook_versions: $scope.environment_cookbook_versions
            });

            $http({
                method: 'POST',
                url: '/chef/saveEnvironment?chef_server=' + $scope.copyEnvChefServer.name,
                data: data,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                }
            }).success(function() {

                location = "/#/environments";
            });

        };
        /*************** copy env ************************************/
        $scope.showCopyEnvironmentOverlay = false;

        $scope.showCopyEnvironmentOverlayBox = function() {
            $scope.showCopyEnvironmentOverlay = true;

        };

        $scope.hideCopyEnvironmentOverlayBox = function() {
            $scope.showCopyEnvironmentOverlay = false;

        };
        $chefServers = $scope.getChefServers();

        /**************** Default Attributes *************************/

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

        };

        $scope.hideAddDefaultAttributeBox = function(node) {
            $scope.showAddDefaultAttribute = false;
        };

        $scope.showEditDefaultAttribute = false;

        $scope.showEditDefaultAttributeBox = function(node) {
            $scope.hideAddDefaultAttributeBox();
            $scope.showEditDefaultAttribute = true;

            $scope.currentNode = node;

            $scope.newNode = angular.copy(node);

        };

        $scope.hideEditDefaultAttributeBox = function() {
            $scope.showEditDefaultAttribute = false;
        };

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
                    //$scope.newNode.value = 'object';
                    delete $scope.newNode.value;
                    $scope.newNode.children = [];
                }
                $scope.currentNode.push($scope.newNode);
            }

            $scope.saveDefaultAttributesTree();

            $scope.hideAddDefaultAttributeBox();
        };

        $scope.saveDefaultAttributesCurrentNode = function() {

            $scope.currentNode.key = $scope.newNode.key;
            $scope.currentNode.value = $scope.newNode.value;
            $scope.saveDefaultAttributesTree();
            $scope.hideEditDefaultAttributeBox();
        };

        $scope.saveDefaultAttributesTree = function() {
            $scope.newDefaultAttributeTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);

            $scope.saveEnvironment(false);
        };

        $scope.deleteDefaultAttributesNode = function(node) {

            if (confirm('Are you sure you wish to delete this attribute?')) {
                for (var member in node) {
                    delete node[member];
                }
                $scope.newDefaultAttributeTree = $scope.rebuildAttributeTree($scope.defaultAttributesTree);
                $scope.saveEnvironment(false);
            }

        };

        /**************** Override Attributes *************************/
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

        };

        $scope.hideAddOverrideAttributeBox = function(node) {
            $scope.showAddOverrideAttribute = false;
        };

        $scope.showEditOverrideAttribute = false;

        $scope.showEditOverrideAttributeBox = function(node) {
            $scope.hideAddOverrideAttributeBox();
            $scope.showEditOverrideAttribute = true;

            $scope.currentNode = node;

            $scope.newNode = angular.copy(node);

        };

        $scope.hideEditOverrideAttributeBox = function() {
            $scope.showEditOverrideAttribute = false;
        };

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
        };

        $scope.saveOverrideAttributesCurrentNode = function() {

            $scope.currentNode.key = $scope.newNode.key;
            $scope.currentNode.type = $scope.newNode.type;
            $scope.currentNode.value = $scope.newNode.value;
            $scope.saveOverrideAttributesTree();
            $scope.hideEditOverrideAttributeBox();
        };

        $scope.saveOverrideAttributesTree = function() {

            $scope.newOverrideAttributeTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);
            $scope.saveEnvironment(false);
        };

        $scope.deleteOverrideAttributesNode = function(node) {

            if (confirm('Are you sure you wish to delete this attribute?')) {
                for (var member in node) {
                    delete node[member];
                }
                $scope.newOverrideAttributeTree = $scope.rebuildAttributeTree($scope.overrideAttributesTree);
                $scope.saveEnvironment(false);
            }
        };

        /**************************************************************/

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

                } else if (value !== null && typeof value === 'number') {
                    child['key'] = key;
                    child['type'] = 'number';
                    child['value'] = value;
                    var positionIndex = children.length;
                    child['position'] = treePosition + '.' + positionIndex;
                    this.push(child);
                } else if (value !== null) {
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


        $scope.rebuildAttributeTree = function(treeNode) {
            var newTreeNode = {};

            angular.forEach(treeNode, function(node) {


                if (!isNaN(node.value)) {
                    node.type = 'number';
                }

                if (node.type == 'string') {
                    this[node.key] = node.value;
                } else if (node.type == 'number') {
                    this[node.key] = Number(node.value);

                } else if (node.type == 'object') {
                    if (typeof node.children !== "undefined" && node.children.length != 0) {
                        this[node.key] = $scope.rebuildAttributeTree(node.children);
                    } else {
                        this[node.key] = 'object';
                    }

                }
            }, newTreeNode);

            return newTreeNode;
        };

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

        $scope.chefServer = getCookie('chef_server');

        /**********************************************************************************/

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
        };

        $scope.back = function() {
            history.back();
        };

    });
