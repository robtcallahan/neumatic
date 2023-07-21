angular.module('NeuMatic')
    .factory('AuthedUserService', function($resource, $q, $cookies, AlertService) {
            var user = {
                username: null,
                firstName: '',
                lastName: '',
                title: '',
                email: '',
                userType: '',
                adminOn: false,

                ldapUserGroups: [],

                chefServers: [],

                numLogins: 0,
                lastLogin: '',
                showMotd: false,
                version: '' // I know that this doesn't belong here, but didn't want to make another call to a controller
            };

            var callback = function() {};

            function ajaxError(json, apiUrl) {
                AlertService.ajaxAlert({
                    json: json,
                    apiUrl: apiUrl
                });
            }

            function httpGet(url, onFailure) {
                onFailure = onFailure || function() {};

                var resource = $resource(url, {}, {query: {method: 'GET', isArray: false}}),
                    deferred = $q.defer();
                resource.get(
                    function(json) {
                        if (typeof json.success !== "undefined" && !json.success) {
                            onFailure(json, url);
                            deferred.reject();
                        } else {
                            deferred.resolve(json);
                        }
                    },
                    function(json) {
                        onFailure(json, url);
                        deferred.reject();
                    });
                return deferred.promise;
            }


            if (user.username === null) {
                httpGet('/users/getAndLogUser').then(
                    function(json) {
                        // have to assign each property separately or we'll overwrite the user model
                        user.id = json.user.id;
                        user.username = json.user.username;
                        user.firstName = json.user.firstName;
                        user.lastName = json.user.lastName;
                        user.title = json.user.title;
                        user.email = json.user.email;
                        user.userType = json.user.userType;
                        user.dept = json.user.dept;
                        user.office = json.user.office;
                        user.mobilePhone = json.user.mobilePhone;
                        user.officePhone = json.user.officePhone;

                        user.ldapUserGroups = JSON.parse(JSON.stringify(json.user.ldapUserGroups));

                        user.numLogins = json.login.numLogins;
                        user.lastLogin = json.login.lastLogin;
                        user.userAgent = json.login.userAgent;
                        user.ipAddr = json.login.ipAddr;
                        user.showMotd = json.login.showMotd;

                        user.adminOn = false;
                        user.chefUser = false;

                        user.chefServers = json.chefServers;

                        user.motd = json.motd;

                        user.version = json.version;

                        if ($cookies.userAdminOn) {
                            user.adminOn = $cookies.userAdminOn === 'true';
                        }

                        callback();
                    }),
                    function() {
                        ajaxError(json, '/users/getAndLogUser');
                    };
            }

            return {
                authedUser: user,
                setCallback: function(_callback_) {
                    callback = _callback_;
                },
                setAdminOn: function() {
                    user.adminOn = true;
                    $cookies.userAdminOn = 'true';
                },
                setAdminOff: function() {
                    user.adminOn = false;
                    $cookies.userAdminOn = 'false';
                },
                setChefServers: function(chefServers) {
                    user.chefServers = chefServers;
                },
                clearCache: function() {
                    httpGet('/chef/clearCache').then(
                        function(json) {
                            
                        }),
                        function() {
                            ajaxError(json, '/chef/clearCache');
                        };
                }
            };
        })
