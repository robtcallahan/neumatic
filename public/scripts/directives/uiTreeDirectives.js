angular.module('NeuMatic')
    .directive('uiTree', function() {
        return {
            template: '<ul class="uiTree"><ui-tree-node ng-repeat="node in tree"></ui-tree-node></ul>',
            replace: true,
            transclude: true,
            restrict: 'E',
            scope: {
                tree: '=ngModel',
                attrNodeId: "@",
                attrChildrenType: "@",
                attrSite: "@",
                lineNum: "=",
                loadFn: '=',
                expandTo: '=',
                selectedId: '='
            },
            controller: function($scope, $element, $attrs) {
                $scope.loadFnName = $attrs.loadFn;
                // this seems like an egregious hack, but it is necessary for recursively-generated
                // trees to have access to the loader function
                if ($scope.$parent.loadFn) {
                    /** @namespace $scope.$parent.loadFn */
                    $scope.loadFn = $scope.$parent.loadFn;
                }

                // TODO expandTo shouldn't be two-way, currently we're copying it
                if ($scope.expandTo && $scope.expandTo.length) {
                    $scope.expansionNodes = angular.copy($scope.expandTo);
                    var arrExpandTo = $scope.expansionNodes.split(",");
                    $scope.nextExpandTo = arrExpandTo.shift();
                    $scope.expansionNodes = arrExpandTo.join(",");
                }
            }
        };
    })

    .directive('uiTreeNode', function($compile, $timeout) {
        return {
            restrict: 'E',
            replace: true,
            template: '<li>' +
                '<table class="node" data-node-id="{{ nodeId() }}">' +
                '<tr ng-show="!node.hasChildren && node.index == 1">' +
                '<th class="vmName">VM Name</th><th class="vmCpu">CPU</th><th class="vmMem">Mem (GB)</th>' +
                '<th class="vmDisk">Disk 1 (GB)</th><th class="vmDisk">Disk 2 (GB)</th>' +
                '<th class="vmDisk">Disk 3 (GB)</th><th class="vmDisk">Disk 4 (GB)</th>' +
                '<th class="vmDisk">Disk 5 (GB)</th><th class="vmDisk">Disk 6 (GB)</th>' +
                '</tr>' +
                '<tr>' +
                '<td class="vmName">' +
                '<a ng-show="node.hasChildren" class="icon" ng-click="toggleNode(nodeId(),childrenType(),site())"></a>' +
                '<a ng-hide="selectedId" ng-href="#/vmware/get{{ childrenType() }}{{ nodeId() }}">{{ node.displayValue ? node.displayValue : node.name }}</a>' +
                '<span ng-show="selectedId" ng-class="css()" ng-click="setSelected(node)">' +
                '<!--suppress ALL --><a ng-show="node.id" href="/#/server/{{ node.id }}">{{ node.displayValue ? node.displayValue : node.name }}</a>' +
                '<span ng-hide="node.id">{{ node.displayValue ? node.displayValue : node.name }}</span>' +
                '</span>' +
                '</td>' +
                '<td ng-show="node.id" align="right" class="vmCpu">{{ node.numCPU }}</td>' +
                '<td ng-show="node.childrenType === \'ComputeResourceVMs\'">' +
                '<span>' +
                '<button class="btn btn-success" ' +
                'tooltip-placement="bottom" ' +
                'tooltip="Create a new VM on this ESX cluster" ' +
                'ng-click="newVm(node)">New VM' +
                '</button>' +
                '</span>' +
                '<td align="right" class="vmMem">{{ node.memoryGB }}</td>' +
                '<td align="right" class="vmDisk" ng-repeat="disk in node.disks">{{ disk.capacityGB }}</td>' +
                '</tr>' +
                '</table>' +
                '</li>',
            link: function(scope, elm) {
                scope.site = function(node) {
                    var localNode = node || scope.node;
                    return localNode[scope.attrSite];
                };

                scope.childrenType = function(node) {
                    var localNode = node || scope.node;
                    return localNode[scope.attrChildrenType];
                };

                scope.nodeId = function(node) {
                    var localNode = node || scope.node;
                    return localNode[scope.attrNodeId];
                };

                // emit a "newVm" event so that the vmwareController can receive and change to editServerController with node params
                scope.newVm = function(node) {
                    scope.$emit("newVm", node);
                };

                scope.toggleNode = function(nodeId, childrenType, site) {
                    var isVisible = elm.children(".uiTree:visible").length > 0;
                    var childrenTree = elm.children(".uiTree");
                    if (isVisible) {
                        scope.$emit('nodeCollapsed', nodeId);
                    }
                    else if (nodeId) {
                        scope.$emit('nodeExpanded', nodeId);
                    }
                    if (!isVisible && scope.loadFn && childrenTree.length === 0) {
                        // load the children asynchronously
                        var callback = function(resp) {
                            var success = resp.data.success,
                                nodes = resp.data.nodes;

                            if (!success) {
                                alert("There was an error in obtaining VMWare info");
                                return;
                            }

                            // we want to insure that all nodes have necessary info to create a VM.
                            // this loop passes the parent nodes info to the children that were just retrieved
                            for (var i = 0; i < nodes.length; i++) {
                                nodes[i].vSphereSite = scope.node.vSphereSite;
                                nodes[i].vSphereServer = scope.node.vSphereServer;
                                nodes[i].dcName = scope.node.dcName;
                                nodes[i].dcUid = scope.node.dcUid;
                                if (scope.node.ccrName) {
                                    nodes[i].ccrName = scope.node.ccrName;
                                }
                                if (scope.node.ccrUid) {
                                    nodes[i].ccrUid = scope.node.ccrUid;
                                }
                                if (scope.node.rpUid) {
                                    nodes[i].rpUid = scope.node.rpUid;
                                }
                            }
                            scope.node.children = nodes;
                            scope.appendChildren();
                            elm.find("a.icon div").show();
                            elm.find("a.icon img").remove();
                            scope.toggleNode(nodeId, childrenType, site); // show it
                        };

                        var promiseOrNodes = scope.loadFn(nodeId, childrenType, site, callback);

                        if (promiseOrNodes && promiseOrNodes.then) {
                            promiseOrNodes.then(callback, function(resp) {
                                alert("Error: " + resp.status)
                            });
                        }
                        else {
                            $timeout(function() {
                                callback(promiseOrNodes);
                            }, 100);
                        }
                        elm.find("a.icon div").hide();
                        var imgUrl = "/images/ajax-loader.gif";
                        elm.find("a.icon").append('<img src="' + imgUrl + '" width="18" height="18">');
                    }
                    else {
                        childrenTree.toggle(!isVisible);
                        elm.find("a.icon div").toggleClass("collapsed");
                        elm.find("a.icon div").toggleClass("expanded");
                    }
                };

                scope.appendChildren = function() {
                    // Add children by $compiling and doing a new ui-tree directive
                    // We need the load-fn attribute in there if it has been provided
                    var childrenHtml = '<ui-tree ng-model="node.children" attr-node-id="' +
                        scope.attrNodeId + '" attr-children-type="' +
                        scope.attrChildrenType + '" attr-site="' +
                        scope.attrSite + '"';
                    if (scope.loadFn) {
                        childrenHtml += ' load-fn="' + scope.loadFnName + '"';
                    }

                    // pass along all the variables
                    if (scope.expansionNodes) {
                        childrenHtml += ' expand-to="expansionNodes"';
                    }

                    if (scope.selectedId) {
                        childrenHtml += ' selected-id="selectedId"';
                    }

                    childrenHtml += ' style="display: none"></ui-tree>';
                    return elm.append($compile(childrenHtml)(scope));
                };

                scope.css = function() {
                    return {
                        nodeLabel: true,
                        selected: scope.selectedId && scope.nodeId() === scope.selectedId
                    };
                };

                // emit an event up the scope.  Then, from the scope above this tree, a "selectNode"
                // event is expected to be broadcasted downwards to each node in the tree.
                // TODO this needs to be re-thought such that the controller doesn't need to manually
                // broadcast "selectNode" from outside of the directive scope.
                scope.setSelected = function(node) {
                    scope.$emit("nodeSelected", node);
                };

                scope.$on("selectNode", function(event, node) {
                    scope.selectedId = scope.nodeId(node);
                });

                if (scope.node.hasChildren) {
                    elm.find("a.icon").append('<div class="collapsed"></div>');
                }

                //if(scope.nextExpandTo && scope.nodeId() == parseInt(scope.nextExpandTo, 10))
                //{
                //    scope.toggleNode(scope.nodeId());
                //}
            }
        };
    })
