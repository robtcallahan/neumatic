angular.module('NeuMatic')

    .controller('MainCtrl', function($scope, $log, $cookies, $http, AuthedUserService, JiraService, AlertService) {
        $scope.nav = {main: true};

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;

        $scope.jiraService = JiraService;

        $scope.rate = 5;
        $scope.comments = "";
        $scope.max = 5;
        $scope.isReadonly = false;
        $scope.userRatingIsCollapsed = true;

        $scope.hoveringOver = function(value) {
            $scope.overStar = value;
            $scope.percent = 100 * (value / $scope.max);
        };

        $scope.ratingStates = [
            {stateOn: 'glyphicon-star', stateOff: 'glyphicon-star-empty'}
        ];

        $scope.submitUserRating = function() {
            $scope.userRatingIsCollapsed = true;

            var data = $.param({
                userId: $scope.authedUser.id,
                rating: $scope.rating,
                comments: $scope.comments
            });

            // call a POST to the neumatic controller passing all the server values
            //noinspection JSValidateTypes
            $http({
                method: 'POST',
                url: '/neumatic/saveRating',
                data: data,
                // content type is required here so that the data is formatted correctly
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            })

                // on success, return to the servers page
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/saveRating'
                        });
                    } else {
                        getRatings();
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/saveRating'
                    });
                });
        };

        function getRatings() {
            //noinspection JSValidateTypes
            $http.get('/neumatic/getRatings')
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getRatings'
                        });
                    } else {
                        $scope.ratings = json.ratings;
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getRatings'
                    });
                });
        }

        function getStats() {
            //noinspection JSValidateTypes
            $http.get('/neumatic/getStats')
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getStats'
                        });
                    } else {
                        $scope.stats = json.stats;
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getStats'
                    });
                });
        }

        function getNeuCollectionVersions() {
            //noinspection JSValidateTypes
            $http.get('/neumatic/getNeuCollectionCookbookVersions')
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getNeuCollectionCookbookVersions'
                        });
                    } else {
                        $scope.versions = json.versions;
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getNeuCollectionCookbookVersions'
                    });
                });
        }

        //getRatings();
        //getStats();
        getNeuCollectionVersions();

        //var ws = new WebSocket('wss://statvdvweb01.va.neustar.com:8443/websocketd/');
        var ws = new WebSocket('wss://' + window.location.hostname + ':8443/websocketd/');
        ws.onmessage = function(event) {
            var json = JSON.parse(event.data);
            //console.log(json);
            var items = ['lastUpdate', 'qotd', 'server', 'monitors', 'stats', 'weather', 'stock'];

            if (typeof json.message !== "undefined") {
                console.log(json.message);
            }
            for (var i=0; i<items.length; i++) {
                if (typeof json[items[i]] !== "undefined") {
                    $scope[items[i]] = json[items[i]];
                }
            }
            $scope.$apply();
        }
    });

