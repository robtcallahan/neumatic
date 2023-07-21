angular.module('NeuMatic')

    .directive('editableGridInput', function($timeout) {
        return {
            restrict: 'E',
            replace: true,
            scope: {
                model: '=',
                align: '=?',
                buttons: '=?',
                defaultValue: '=?',
                save: '&'     // save function found in the controller; view passes the record
            },
            template: '<div class="editable-grid-cell" ' +
                      '     style="z-index:2000; width:100%; float:{{align || \'left\'}}" ' +
                      '     ng-click="editItem()"> ' +
                      '  <span ng-hide="editMode" ' +
                      '        style="color:#296496;text-align:{{align || \'left\'}}">{{model || defaultValue}} ' +
                      '  </span> ' +
                      '  <input ng-show="editMode" ' +
                      '         style="height:18px; width:100%; padding:0; margin:0; text-align:{{align || \'left\'}}" ' +
                      '         ng-keypress="keypress($event)" ' +
                      '         ng-blur="saveEdit()" ' +
                      '         ng-model="model" ' +
                      '         type="text" ' +
                      '  />' +
                      '  <button ng-click="cancelEdit()" ng-show="buttons && editMode">cancel</button>' +
                      '  <button ng-click="saveEdit()" ng-show="buttons && editMode">save</button>' +
                      '</div>',


            link: function(scope, element){
                var origValue = "",
                    preventOnBlur = false;

                // insure we set defaults for align and buttons attributes
                scope.align = angular.isDefined(scope.align) ? scope.align : 'left';
                scope.buttons = angular.isDefined(scope.buttons) ? scope.buttons : false;
                scope.defaultValue = angular.isDefined(scope.defaultValue) ? scope.defaultValue : " ";

                /**
                 * fires on any keypress event in the input field. We're only checking for an "Enter" though.
                 * This will end our edit session and we'll save the data
                 *
                 * @param $event
                 */
                scope.keypress = function($event) {
                    if ($event.key === "Enter") {
                        scope.saveEdit();
                    } else if ($event.key === "Esc") {
                        //console.log("keypress(ESC)");
                        preventOnBlur = true;
                        scope.cancelEdit();
                    }
                };

                /**
                 * Called when the user clicks on the table cell, if editable. Turns on edit mode
                 */
                scope.editItem = function() {
                    console.log("editItem()");

                    preventOnBlur = false;

                    // save the current value so that if the user hits Escape, we can return to it
                    origValue = scope.model;

                    // turn on edit mode which shows the input field
                    scope.editMode = true;

                    // set focus to the input field
                    $timeout(function() {
                        var el = element.find('input')[0];
                        el.focus();
                        el.select();
                    }, 0, false);
                };

                scope.cancelEdit = function() {
                    console.log("cancelEdit()");

                    // not sure it's necessary to assign both the model and the element text
                    // will need to research this at some later point
                    // if we just set the model, the text on the screen doesn't change. have to
                    // use element.text() method.
                    scope.model = origValue;

                    var el = element.find('span')[0];
                    el.innerHTML = origValue;

                    scope.editMode = false;
                };

                scope.saveEdit = function() {

                    console.log("saveEdit()");
                    if (!preventOnBlur) {
                        // turn off edit mode which hides the input field
                        scope.editMode = false;
                        // call the controller function. note that view is passing the record (bs) so we don't pass it here
                        scope.save();
                    }
                    preventOnBlur = false;
                };

            }
        }
    })

    .directive('editableGridBusinessService', function($http, $timeout) {
        return {
            restrict: 'E',
            replace: true,
            scope: {
                vlan: '=',
                businessService: '=',
                index: '=',
                save: '&'     // save function found in the controller; view passes the record (vlan)
            },
            template: '<div>' +
                      '  <span ng-hide="editMode" ng-click="editItem()" style="cursor:pointer;color:#296496">{{businessService.name || \'null\'}}</span>' +
                      '  <input ng-show="editMode" ' +
                      '         type="text" ' +
                      '         ng-model="selectedBS" ' +
                      '         placeholder="Enter substring" ' +
                      '         typeahead="searchResult as searchResult.name for searchResult in getBusServicesForTypeAhead($viewValue)" ' +
                      '         typeahead-editable="false" ' +
                      '         typeahead-min-length="3" ' +
                      '         typeahead-wait-ms="750" ' +
                      '         typeahead-loading="loadingBusServices" ' +
                      '         typeahead-on-select="saveEdit()" ' +
                      '         typeahead-template-url="typeaheadTemplate.html">' +
                      '  <button ng-click="cancelEdit()" ng-show="editMode">cancel</button>' +
                      '  <button ng-click="saveEdit()" ng-show="editMode">save</button>' +
                      '</div>',

            link: function(scope, element) {
                var origValue = "";

                scope.selectedBS = {
                    name: scope.businessService.name
                };

                /**
                 * Used in conjunction with the bootstrap typeahead function
                 *
                 * @param val the substring value typed into the field
                 * @returns {*}
                 */
                scope.getBusServicesForTypeAhead = function (val) {
                    return $http.get('/cmdb/getBusinessServicesBySubstring/' + val, {
                    }).then(function (res) {
                        return res.data.businessServices;
                    });
                };

                /**
                 * fires on any keypress event in the input field. We're only checking for an "Enter" though.
                 * This will end our edit session and we'll save the data
                 *
                 * @param $event
                 */
                scope.keypress = function($event) {
                    if ($event.key === "Esc") {
                        scope.cancelEdit();
                    }
                };

                /**
                 * Called when the user clicks on the table cell, if editable. Turns on edit mode
                 */
                scope.editItem = function() {
                    // save the current value so that if the user hits Escape, we can return to it
                    origValue = scope.model;

                    // turn on edit mode which shows the input field
                    scope.editMode = true;

                    $timeout(function() {
                        var el = element.find('input')[0];
                        el.focus();
                        el.select();
                    }, 0, false);
                };

                scope.cancelEdit = function() {
                    scope.editMode = false;
                    scope.model = origValue;
                };

                scope.saveEdit = function() {
                    // turn off edit mode which hides the input field
                    scope.editMode = false;

                    // set the vlan businessService to our selected value using the businessService ng-repeat
                    // index passed into this directive
                    scope.vlan.businessServices[scope.index].name = scope.selectedBS.name;
                    scope.vlan.businessServices[scope.index].sysId = scope.selectedBS.sysId;

                    // call the controller function. note that view is passing the record (vlan) so we don't pass it here
                    scope.save();
                };

            }
        }
    })

    .directive('editableGridEnvironment', function($http, $timeout, NeuMaticService) {
        return {
            restrict: 'E',
            replace: true,
            scope: {
                vlan: '=',
                businessService: '=',
                index: '=',
                save: '&'     // save function found in the controller; view passes the record (vlan)
            },
            template: '<div>' +
                      '  <span ng-hide="editMode" ng-click="editItem()" style="cursor:pointer;color:#296496">{{businessService.environment || \'null\'}}</span>' +
                      '  <select ng-show="editMode" ' +
                      '          style="font-size: 14px; height: 25px; width: 200px; padding: 0; margin: 0;" ' +
                      '          class="form-control" ' +
                      '          ng-model="selectedEnvironment" ' +
                      '          ng-change="saveEdit()" ' +
                      '          ng-options="cmdbEnvironment for cmdbEnvironment in cmdbEnvironments"> ' +
                      '  </select> ' +
                      '</div>',

            link: function(scope /*, element*/) {
                var origValue = "";
                scope.cmdbEnvironments = [];

                scope.selectedEnvironment = scope.businessService.environment;

                if (scope.cmdbEnvironments.length === 0) {
                    NeuMaticService.getCmdbEnvironments(
                        function(environments) {
                            scope.cmdbEnvironments = environments;
                        }
                    );
                }

                /**
                 * Called when the user clicks on the table cell, if editable. Turns on edit mode
                 */
                scope.editItem = function() {
                    // save the current value so that if the user hits Escape, we can return to it
                    origValue = scope.model;

                    // turn on edit mode which shows the input field
                    scope.editMode = true;
                };

                scope.saveEdit = function() {
                    // turn off edit mode which hides the input field
                    scope.editMode = false;

                    // set the vlan businessService to our selected value using the businessService ng-repeat
                    // index passed into this directive
                    scope.vlan.businessServices[scope.index].environment = scope.selectedEnvironment;

                    // call the controller function. note that view is passing the record (vlan) so we don't pass it here
                    scope.save();
                };

            }
        }
    });
