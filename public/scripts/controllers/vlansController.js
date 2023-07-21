angular.module('NeuMatic')

    .controller('VLANsCtrl', function($scope, $log, $http, AuthedUserService, JiraService, AlertService) {

        $scope.nav = {admin: true};
        $scope.$log = $log;

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        $scope.title = "VLANs";

        $scope.distSwitches = [];
        $scope.vlans = [];

        var defaultVlan = {
            id: 4,
            model: 'stdn7010a',
            name: 'Sterling General Purpose'
        };

        $scope.businessServiceSelected = function(vlan, bs) {
            console.log("bs.name=" + bs.name);
        };

        function getDistSwitches() {
            showLoading();
            //noinspection JSValidateTypes
            $http.get('/neumatic/getDistSwitches')
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getDistSwitches'
                        });
                        return;
                    }
                    $scope.distSwitches = json.distSwitches;
                    $scope.distSwitches.forEach(function(item) {
                        item.displayValue = item.name + ' (' + item.model + ')';
                        if (item.id === defaultVlan.id) {
                            $scope.distSwitch = item;
                        }
                    });
                    hideLoading();
                    $scope.getVlans(defaultVlan.id);
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getDistSwitches'
                    });
                });
        }

        $scope.getVlans = function(distSwitchId) {
            showLoading();
            //noinspection JSValidateTypes
            $http.get('/neumatic/getVlansByDistSwitchId/' + distSwitchId)
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/getVlansByDistSwitchId'
                        });
                        return;
                    }
                    var vlans = json.vlans;
                    vlans.forEach(function(item) {
                        item.expanded = false;
                    });
                    $scope.vlans = vlans;
                    hideLoading();
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/getVlansByDistSwitchId'
                    });
                });
        };

        $scope.saveVlan = function(vlan) {
            showLoading();
            $scope.editMode = false;

            // create a data structure with all the values of the server
            var data = $.param({
                id: vlan.id,
                distSwitchId: vlan.distSwitchId,
                vlanId: vlan.vlanId,
                name: vlan.name,
                network: vlan.network,
                netmask: vlan.netmask,
                gateway: vlan.gateway,
                enabled: vlan.enabled,
                businessServices: angular.toJson(vlan.businessServices)
            });

            // call a POST to the neumatic controller passing all the server values
            //noinspection JSValidateTypes
            $http({
                method: 'POST',
                url: '/neumatic/saveVlan',
                data: data,
                // content type is required here so that the data is formatted correctly
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            })

                // on success, return to the servers page
                .success(function(json) {
                    hideLoading();
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/saveVlan'
                        });
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/saveVlan'
                    });
                });
        };

        $scope.addBusinessService = function(vlan) {
            vlan.businessServices[vlan.businessServices.length] = {
                id: 0,
                vlanId: vlan.vlanId,
                name: 'BS Name',
                sysId: 'null',
                environment: 'Environment'
            };
        };

        $scope.deleteBusinessService = function(index, vlan) {
            vlan.businessServices.splice(index, 1);
            $scope.saveVlan(vlan);
        };

        // ----------------------------------------------------------------------------------------------------
        // On Page Load
        // ----------------------------------------------------------------------------------------------------
        getDistSwitches();

        // ----------------------------------------------------------------------------------------------------
        // General purpose functions
        // ----------------------------------------------------------------------------------------------------

        function showLoading() {
            $('#loading-spinner').show();
        }

        function hideLoading() {
            $('#loading-spinner').hide();
        }
    });

