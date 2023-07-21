angular.module('NeuMatic')
    .directive('neumaticVersion', function() {
        return {
            restrict: 'E',
            template: '<span class="version">{{authedUser.version}}</span>'
        }
    })

    .directive('footer', function() {
        return {
            restrict: 'E',
            template: '<div class="footer-left"><neumatic-version></neumatic-version></div>' +
                '<div class="footer-right">' +
                '&copy; 2015 Neustar, Inc. -- ' +
                'Developed by ' +
                '<a href="mailto:AutomationToolsTeam@neustar.biz" tooltip-placement="top" tooltip="Email us!">The Automation &amp; Tools Team</a>' +
                ' -- Built on ' +
                '<a href="http://angularjs.org/" tooltip-placement="top" tooltip="Show me the Angular way">AngularJS</a> &amp; ' +
                '<a href="http://framework.zend.com/" tooltip-placement="top" tooltip="I want to be Zendified">PHP/Zend2</a>' +
                '</div>'
        }
    })

    .directive('promptModal', function() {
        return {
            restrict: 'E',
            scope: {
                model: '='
            },
            templateUrl: 'views/templates/prompt_modal.html',
            link: function(scope, element) {

            }
        }
    })

    .directive('userGroupAssignDialog', function($http, NeuMaticService) {
        return {
            restrict: 'E',
            scope: {
                username: '=',
                server: '='
            },
            templateUrl: 'views/templates/user_group_assign_dialog.html',
            link: function(scope) {
                scope.submitCallback = function() {
                    NeuMaticService.updateServer(scope.server.id, 'ldapUserGroup', scope.ldapUserGroup).then(
                        function() {
                            scope.server.ldapUserGroup = scope.ldapUserGroup;
                        });
                };

                scope.cancelCallback = function() {
                    // NO OP
                };

                scope.getLdapUserGroupsForTypeAhead = function(val) {
                    var results = [];
                    console.log("getLdapUserGroupsForTypeAhead()");
                    return $http.get('/ldap/getUsergroupList', {
                    }).then(function(res) {
                        /** @namespace res.data.usergroups */
                        res.data.usergroups.forEach(function(item) {
                            var re = new RegExp(val, "i");
                            if (item.search(re) !== -1) {
                                results.push(item);
                            }
                        });
                        return results;
                    });
                };


            }
        }
    })

    .directive('ownerAssignDialog', function($http, NeuMaticService) {
        return {
            restrict: 'E',
            scope: {
                server: '='
            },
            templateUrl: 'views/templates/owner_assign_dialog.html',
            link: function(scope) {
                scope.submitCallback = function() {
                    NeuMaticService.updateServer(scope.server.id, 'ownerId', scope.owner.id).then(
                        function() {
                            scope.server.owner = scope.owner.username;
                        });
                };

                scope.cancelCallback = function() {
                    // NO OP
                };

                scope.getUsersForTypeAhead = function(val) {
                    var results = [];

                    return $http.get('/users/getUsers', {
                    }).then(function(res) {
                        res.data.users.forEach(function(item) {
                            var re = new RegExp(val, "i");
                            if (item.username.search(re) !== -1) {
                                results.push(item);
                            }
                        });
                        return results;
                    });
                };
            }
        }
    })


