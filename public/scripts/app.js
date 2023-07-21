angular.module('NeuMatic',
        [
            'ngResource',
            'ngSanitize',
            'ngCookies',
            'ui.router',
            'ui.bootstrap',
            'ngTagsInput',
            'ngDragDrop',
            'treeControl',
            'Scope.safeApply'
        ]
    )

    .config(function($provide, $httpProvider) {
        // Intercept http calls.
        $provide.factory('MyHttpInterceptor', function($q, Browser) {
            return {
                // On request success
                request: function(config) {
                    if (config.method === 'GET' && Browser() === 'ie' && config.url.search(/html/) === -1) {
                        var separator = config.url.indexOf('?') === -1 ? '?' : '&';
                        config.url = config.url+separator+'noCache=' + new Date().getTime();
                    }
                    // Return the config or wrap it in a promise if blank.
                    return config || $q.when(config);
                },

                // On request failure
                requestError: function(rejection) {
                    // console.log(rejection); // Contains the data about the error on the request.

                    // Return the promise rejection.
                    return $q.reject(rejection);
                },

                // On response success
                response: function(response) {
                    $("#ajaxError").html('');

                    // Return the response or promise.
                    return response || $q.when(response);
                },

                // On response failture
                responseError: function(rejection) {
                    console.log(rejection); // Contains the data about the error.
                    $("#statusDiv").html(rejection.data);
                    // Return the promise rejection.
                    return $q.reject(rejection);
                }
            };
        });

        // Add the interceptor to the $httpProvider.
        $httpProvider.interceptors.push('MyHttpInterceptor');
    })

    .config(function($logProvider) {
        $logProvider.debugEnabled(true);
    })

    // configuring our UI routes for the Self-Service provisioning
    // =============================================================================
    .config(function($stateProvider, $urlRouterProvider) {

        $stateProvider

            .state('main', {
                url: '/',
                templateUrl: 'views/main.html',
                controller: 'MainCtrl'
            })

            .state('audit', {
                url: '/audit/:id',
                templateUrl: 'views/auditLog.html',
                controller: 'AuditCtrl'
            })

            .state('bootstrap', {
                url: '/util/bootstrap',
                templateUrl: 'views/util/bootstrap.html',
                controller: 'BootstrapCtrl'
            })

            .state('cookbooks', {
                url: '/cookbooks',
                templateUrl: 'views/cookbooks.html',
                controller: 'CookbooksCtrl'
            })
            .state('cookbooksId', {
                url: '/cookbooks/:id',
                templateUrl: 'views/editCookbook.html',
                controller: 'EditCookbookCtrl'
            })
            .state('cookbooksAdd', {
                url: '/addCookbook',
                templateUrl: 'views/addCookbook.html',
                controller: 'AddCookbookCtrl'
            })
            .state('cookbooksAddGroupId', {
                url: '/addCookbook/:group/:id',
                templateUrl: 'views/addCookbookForm.html',
                controller: 'AddCookbookFormCtrl'
            })

            .state('deleteNode', {
                url: '/deleteNode',
                templateUrl: 'views/deleteNode.html',
                controller: 'DeleteNodeCtrl'
            })

            .state('editServer', {
                url: '/server/:id',
                templateUrl: 'views/editServer.html',
                controller: 'EditServerCtrl'
            })

            .state('environments', {
                url: '/environments',

                templateUrl: 'views/environments.html',
                controller: 'EnvironmentsCtrl'
            })
            .state('environmentsId', {
                url: '/environments/:id',
                templateUrl: 'views/editEnvironment.html',
                controller: 'EditEnvironmentCtrl'
            })
            .state('environmentsEditCookbook', {
                url: '/environments/editCookbookVersion/:env/:cb',
                templateUrl: 'views/editEnvironmentCookbookVersion.html',
                controller: 'EditEnvironmentCookbookVersionCtrl'
            })

            .state('help', {
                url: '/help',
                templateUrl: 'views/help.html',
                controller: 'HelpCtrl'
            })

            .state('leases', {
                url: '/leases',
                templateUrl: 'views/leases.html',
                controller: 'LeasesCtrl'
            })
            .state('leasesId', {
                url: '/leases/:id',
                templateUrl: 'views/editLease.html',
                controller: 'LeasesCtrl'
            })

            .state('nodes', {
                url: '/nodes',
                templateUrl: 'views/nodes.html',
                controller: 'NodesCtrl'
            })
            .state('nodesId', {
                url: '/nodes/:id',
                templateUrl: 'views/editNode.html',
                controller: 'EditNodeCtrl'
            })

            .state('quotas', {
                url: '/quotas',
                templateUrl: 'views/quotas.html',
                controller: 'QuotasCtrl'
            })

            .state('reports_builds', {
                url: '/reports/builds',
                templateUrl: 'views/reports/builds.html',
                controller: 'BuildsReportCtrl'
            })
            .state('reports_nodes', {
                url: '/reports/nodes',
                templateUrl: 'views/reports/nodes.html',
                controller: 'NodesReportCtrl'
            })
            .state('reports_users', {
                url: '/reports/users',
                templateUrl: 'views/reports/users.html',
                controller: 'UsersReportCtrl'
            })

            .state('roles', {
                url: '/roles',
                templateUrl: 'views/roles.html',
                controller: 'RolesCtrl'
            })
            .state('rolesId', {
                url: '/roles/:id',
                templateUrl: 'views/editRole.html',
                controller: 'EditRoleCtrl'
            })

            // route to show our basic form (/selfService)
            .state('selfService', {
                url: '/selfService',
                templateUrl: 'views/selfService/selfService.html',
                controller: 'SelfServiceCtrl'
            })
            // nested states
            // each of these sections will have their own view
            // url will be /selfService/interests
            .state('selfService.cmdbInfo', {
                url: '/selfService/cmdbInfo',
                templateUrl: 'views/selfService/selfService-cmdbInfo.html'
            })
            /*
            .state('selfService.serverIdent', {
                url: '/selfService/serverIdent',
                templateUrl: 'views/selfService/selfService-serverIdent.html'
            })
            */
            .state('selfService.serverGroups', {
                url: '/selfService/serverGroups',
                templateUrl: 'views/selfService/selfService-groups.html'
            })
            .state('selfService.serverSize', {
                url: '/selfService/serverSize',
                templateUrl: 'views/selfService/selfService-serverSize.html'
            })
            .state('selfService.chefInfo', {
                url: '/selfService/chefInfo',
                templateUrl: 'views/selfService/selfService-chefInfo.html'
            })
            .state('selfService.build', {
                url: '/selfService/build',
                templateUrl: 'views/selfService/selfService-build.html'
            })

            .state('servers', {
                url: '/servers/:listType/:ldapUserGroup',
                templateUrl: 'views/servers.html',
                controller: 'ServersCtrl'
            })

            .state('teams', {
                url: '/teams',
                templateUrl: 'views/teams.html',
                controller: 'TeamsCtrl'
            })
            .state('teamsId', {
                url: '/teams/:id',
                templateUrl: 'views/editTeam.html',
                controller: 'TeamsCtrl'
            })

            .state('usersId', {
                url: '/users/:id',
                templateUrl: 'views/user.html',
                controller: 'UsersCtrl',
                // TODO: This is not working like it is suppose to. Need a way to obtain authedUser before this page loads
                resolve: {
                    'AuthedUserLoaderPromise': function(AuthedUserService) {
                        return AuthedUserService;
                    }
                }
            })

            .state('vlans', {
                url: '/vlans',
                templateUrl: 'views/vlans.html',
                controller: 'VLANsCtrl'
            })
            .state('vmware', {
                url: '/vmware',
                templateUrl: 'views/vmware.html',
                controller: 'VMWareCtrl'
            })
        ;
        // catch all route
        // send users to the form page
        $urlRouterProvider.otherwise('/');
    });

