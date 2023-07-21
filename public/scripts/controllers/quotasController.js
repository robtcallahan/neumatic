angular.module('NeuMatic')

    .controller('QuotasCtrl', function($scope, $log, $http, AuthedUserService, NeuMaticService, JiraService, AlertService) {

        $scope.nav = {admin: true};
        $scope.$log = $log;

        $scope.authedUserService = AuthedUserService;
        $scope.authedUser = AuthedUserService.authedUser;
        $scope.jiraService = JiraService;

        $scope.title = "VMware Quotas for Business Services";

        $scope.dataCenters = [];
        $scope.clusters = [];
        $scope.businessServices = [];

        $scope.showQuote = true;
        $scope.cpuEdit = true;


        var bsCache = {};
        var firstLoad = true;

        function getDataCenters() {
            // make the combo show 'Loading...'
            $scope.dataCenters = [
                {
                    name: 'Loading...',
                    uid: ''
                }
            ];
            $scope.dataCenter = $scope.dataCenters[0];

            // get the list of VMware data centers
            NeuMaticService.apiGet('/vmware/getDataCenters',
                function(json) {
                    $scope.dataCenters = json.dataCenters;
                    if (firstLoad) {
                        $scope.dataCenters.forEach(function(item) {
                            // set the default data center
                            if (item.name === 'Sterling') {
                                $scope.dataCenter = item;
                                getClusters();
                            }
                        });
                    }
                });
        }

        $scope.getClusters = function() {
            getClusters();
        };

        function getClusters() {
            // make the combo show 'Loading...'
            $scope.clusters = [
                {
                    name: 'Loading...',
                    uid: ''
                }
            ];
            $scope.cluster = $scope.clusters[0];

            // get the list of VMware clusters by data center uid
            NeuMaticService.apiGet('/vmware/getClusterComputeResources/' + $scope.dataCenter.uid + '?vSphereSite=' + $scope.dataCenter.vSphereSite,
                function(json) {
                    $scope.clusters = json.clusters;
                    if (firstLoad) {
                        $scope.clusters.forEach(function(item) {
                            // set the default cluster
                            if (item.name === 'ST_IHN') {
                                $scope.cluster = item;
                                getBusinessServices();
                            }
                        });
                    }
                });
        }

        $scope.getBusinessServices = function() {
            getBusinessServices();
        };

        function getBusinessServices() {
            if (!firstLoad) showLoading();
            firstLoad = false;

            NeuMaticService.apiGet('/neumatic/getBusinessServicesByVMCluster/' + $scope.dataCenter.uid + '/' + $scope.cluster.uid,
                function(json) {
                    hideLoading();
                    // Loop over each and add the edit flags for cpu, memory and storage; set them to false
                    json.businessServices.forEach(function(item, i, ar) {
                        json.businessServices[i].edit = {
                            cpus: false,
                            memory: false,
                            storage: false
                        };
                    });
                    $scope.businessServices = json.businessServices;
                });
        }

        /**
         * Enables editing of the cell
         * @param bs        business service data object
         * @param index     table row index (0-based)
         * @param item      the item to be edited: 'cpus', 'memory', 'storage'
         */
        $scope.cellEdit = function(bs, index, item) {
            // cache the current values in case we cancel the edit
            bsCache = JSON.parse(JSON.stringify(bs));

            // enable the cell editor by setting the flag
            $scope.businessServices[index].edit[item] = true;

            // set the input focus to the cell, but delay it by 1/2 a sec to insure the input element has been rendered
            setTimeout(function(id) {
                $('#' + item + '-input-' + index).focus();
            }, 500);
        };

        $scope.cancelEdit = function(bs, index, item) {
            $scope.businessServices[index] = JSON.parse(JSON.stringify(bsCache));
            $scope.businessServices[index].edit[item] = false;
        };

        /**
         * Save any changes that were made to the cpu, memory or storage quota values
         * @param bs        business service data object
         * @param index     table row index (0-based)
         * @param item      the item to be edited: 'cpus', 'memory', 'storage'
         */
        $scope.save = function(bs, index, item) {
            $scope.businessServices[index].edit[item] = false;

            var data = $.param({
                quotaId: bs.quotaId,
                dcUid: $scope.dataCenter.dcUid,
                ccrUid: $scope.cluster.ccrUid,
                bsSysId: bs.sysId,
                bsName: bs.name,
                cpusQuota: bs.cpusQuota,
                memoryQuota: bs.memoryQuota,
                storageQuota: bs.storageQuota
            });

            $http({
                method: 'POST',
                url: '/neumatic/saveQuota',
                data: data,
                // content type is required here so that the data is formatted correctly
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}
            })

                // on success, return to the servers page
                .success(function(json) {
                    if (typeof json.success !== "undefined" && !json.success) {
                        AlertService.ajaxAlert({
                            json: json,
                            apiUrl: '/neumatic/saveQuota'
                        });
                    }
                })
                .error(function(json) {
                    AlertService.ajaxAlert({
                        json: json,
                        apiUrl: '/neumatic/saveQuota'
                    });
                });
        }

        // ----------------------------------------------------------------------------------------------------
        // On Page Load
        // ----------------------------------------------------------------------------------------------------
        NeuMaticService.apiGet('/neumatic/getQuoteOfTheDay',
            function(json) {
                $scope.quote = json.quote;
                $scope.author = json.author;
            });

        getDataCenters();


        // ----------------------------------------------------------------------------------------------------
        // General purpose functions
        // ----------------------------------------------------------------------------------------------------

        function showLoading() {
            $scope.showQuote = true;
            $scope.businessServices = []
            NeuMaticService.apiGet('/neumatic/getQuoteOfTheDay',
                function(json) {
                    $scope.quote = json.quote;
                    $scope.author = json.author;
                    $scope.showQuote = true;
                });
        }

        function hideLoading() {
            $scope.showQuote = false;
        }
    });

