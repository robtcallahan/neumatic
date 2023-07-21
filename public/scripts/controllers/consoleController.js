angular.module('NeuMatic')

    .controller('ConsoleCtrl', function($scope, $log, $http, $interval, $routeParams, consoleService, AuthedUserService, NeuCommon) {

        $scope.nav = {console: true};
        $scope.$log = $log;

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.neuCommon = NeuCommon;

        $scope.$routeParams = $routeParams;
        $scope.serverId = $routeParams.serverId;

        $scope.consoleService = consoleService;
        $scope.consoles = $scope.consoleService.getConsoles();
        $scope.console = false;

        $scope.scrollTopLast = 0; // comparing current scrollTop with this will tell us if the user scrolled up.

        /*
         * Insures that multiple calls to read the console from the server will not stack up. Only one outstanding call at a time
         */
        $scope.readConsoleBlock = false;

        $scope.buildCompleteRE = new RegExp(/ogin:/);

        $scope.getServer = function(serverId) {
            // get the server data from the database
            $http.get('/neumatic/getServer/' + serverId)
                .success(function(json, status, headers, config) {
                    $scope.server = json.server;
                    $scope.main();
                })
                .error(function(data, status, headers, config) {
                    throw new Error('Something went wrong...');
                });
        };

        $scope.main = function() {
            $scope.console = consoleService.getCon($scope.server);

            // does the server think the console is running?
            if ($scope.console.isRunning()) {
                $scope.consoleStop();
            }

            if ($scope.server.status === 'Building') {
                $scope.server.consoleLogPtr = 0;
                $scope.consoleStart();
            } else {
                $scope.consoleLog = "Loading...";
                $scope.readConsole();
            }
        };

        $scope.consoleStart = function() {
            $scope.consoleData = "Console starting...";
            $scope.consoleWatcher = "Console watcher starting...";
            $scope.readConsoleBlock = false;

            // set the application level flag
            $scope.console.setRunning(true);
            $scope.console.setTimer($interval($scope.readConsole, 10000));
        };

        $scope.consoleStop = function() {
            $scope.readConsoleBlock = false;
            $scope.console.stop();

        };

        $scope.markComplete = function() {
            $scope.consoleService.deleteConsole($scope.server);
            $http.get('/neumatic/serverBuilt/' + $scope.server.id)
                .success(function(data, status, headers, config) {
                })
                .error(function(data, status, headers, config) {
                    // TODO: do something here
                });
        };

        $scope.readConsole = function() {
            if ($scope.readConsoleBlock === true) {
                return;
            }

            $scope.readConsoleBlock = true;
            $http.get('/neumatic/consoleRead/' + $scope.server.id)
                .success(function(data, status, headers, config) {
                    var consoleDataEl = document.getElementById('consoleData'),
                        consoleLogEl = document.getElementById('consoleLog');

                    $scope.consoleData = data.consoleData + '<br><br><br><br><br><br>';
                    $scope.consoleWatcher = data.consoleWatcher + "\n\n\n\n\n\n";

                    if (consoleDataEl) {
                        consoleDataEl.scrollTop = consoleDataEl.scrollHeight;
                    }
                    if (consoleLogEl) {
                        consoleLogEl.scrollTop = consoleLogEl.scrollHeight;
                    }

                    $scope.readConsoleBlock = false;

                    if ($scope.server.status === "Built") {
                        $scope.consoleStop();
                    }
                })
                .error(function(data, status, headers, config) {
                    // TODO: do something here
                    $log.info("/chassis/consoleRead returned error");
                    $scope.readConsoleBlock = false;
                });
        };


        if (typeof $scope.serverId === "undefined") {
            // server was not passed; the console menu was pressed instead
            $log.info("serverId undefined");
            // look for an existing, defined console
            if ($scope.consoles.length > 0) {
                // console already running
                $log.info("found console");
                $scope.console = $scope.consoles[0];
                $scope.server = $scope.console.getServer();
                $scope.main();
            } else {
                $log.info("console not found");
            }
        } else {
            $scope.getServer($scope.serverId);
        }

    });

