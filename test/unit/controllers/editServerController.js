describe('Unit: Testing Edit Server Controller', function() {
    var scope,
        httpBackend,
        AuthedUserService,
        NeuMaticService;

    // Our tests will go here
    beforeEach(module('NeuMatic'));

    it('should have a EditServerCtrl controller', function() {
        expect(NeuMatic.EditServerCtrl).not.toBe(null);
    });

    /*
        httpBackend.when('GET', '/neumatic/getServer/NaN').respond(200,
            {"success": true, "server": {"id": 1428, "name": "stlabvnode03.va.neustar.com", "serverType": "vmware", "sysId": null, "businessServiceName": "Automation \u0026 Tools", "businessServiceId": "28676deba91d19001091c6685e0bdbcd", "subsystemName": "AT - Web Tools", "subsystemId": "b59a3163a95d19001091c6685e0bdbfc", "cmdbEnvironment": "Lab", "location": "Lab", "network": "172.30.32.0", "subnetMask": "255.255.255.0", "gateway": "172.30.32.1", "macAddress": "00:50:56:bb:c2:4a", "ipAddress": "172.30.32.152", "cobblerServer": "stlabvmcblr01.va.neustar.com", "cobblerDistro": "CentOS-6.3-x86_64", "cobblerKickstart": "\/var\/lib\/cobbler\/kickstarts\/baseline_6.ks", "chefServer": "stopcdvvcm01.va.neustar.com", "chefRole": "neu_collection", "chefEnv": "AUTOTOOLS-WEB-DEV", "dateCreated": "2014-08-19 10:59:34", "userCreated": "rcallaha", "dateUpdated": "2014-08-19 01:05:46", "userUpdated": "rcallaha", "okToBuild": 1, "status": "Failed", "statusText": "Burnt", "timeBuildStart": "2014-08-19 01:05:53", "timeBuildEnd": "2014-08-19 01:09:43", "dateBuilt": "2014-08-19 01:09:43", "userBuilt": null, "dateFirstCheckin": null, "archived": 0, "changes": [], "vmwareId": 1302, "serverId": 1428, "vSphereSite": "lab", "vSphereServer": "stlabvcenter02.cis.neustar.com", "dcName": "LAB", "dcUid": "datacenter-401", "ccrName": "LAB_Cluster", "ccrUid": "domain-c406", "rpUid": "resgroup-407", "hsName": "stopcdvesx02.va.neustar.com", "instanceUuid": "503b8302-fa0d-a301-263e-ec7f27750000", "vmSize": "Small", "numCPUs": 1, "memoryGB": 2, "vlanName": "VLAN32", "vlanId": "dvportgroup-417", "dateCreatedShort": "2014-08-19", "luns": [
                {"id": 2035, "serverId": 1428, "lunSizeGb": 50, "changes": []}
            ],
                "serverPoolId": 7, "isLeased": 1, "leaseStartDate": "2014-08-19", "daysToLeaseEnd": 29, "leaseAlertClass": "lease-alert-bg-normal", "leaseDuration": 30, "extensionInDays": 7, "numExtensionsAllowed": 2, "numTimesExtended": 0, "numExtensionsRemaining": 2}, "owner": {"id": 1, "firstName": "Robert", "lastName": "Callahan", "username": "rcallaha", "empId": "002386", "title": "Principal Systems Engr", "dept": "066002 - Tools \u0026 Automation", "office": "B10 Office\/Cube 2089", "email": "Robert.Callahan@neustar.biz", "officePhone": "(571) 434-5165", "mobilePhone": "(703) 851-5412", "userType": "Admin", "numServerBuilds": 112, "dateCreated": "2014-02-04 12:27:47", "userCreated": "rcallaha", "dateUpdated": "2014-08-19 01:15:49", "userUpdated": "rcallaha", "changes": []}, "logLevel": 6, "logOutput": "Returning server stlabvnode03.va.neustar.com"}
        );
        */

    beforeEach(inject(function($q, $rootScope, $controller, $httpBackend, _NeuMaticService_, _AuthedUserService_) {
        scope = $rootScope.$new();
        httpBackend = $httpBackend;
        NeuMaticService = _NeuMaticService_;
        AuthedUserService = _AuthedUserService_;

        /*
         mockNeuMaticService = NeuMaticService;
         mockNeuMaticService.query =
         function() {
         queryDeferred = $q.defer();
         return {$promise: queryDeferred.promise};
         };

         spyOn(mockNeuMaticService, 'query').andCallThrough();
         */
        /*
        $controller('EditServerCtrl', {
            $scope: scope,
            NeuMaticService: NeuMaticService,
            AuthedUserService: AuthedUserService
        });
        */

        httpBackend.when('GET', '/users/getAndLogUser').respond(200, {
                "success": true,
                "user": {
                    "id": 1,
                    "firstName": "Robert",
                    "lastName": "Callahan",
                    "username": "rcallaha",
                    "empId": "002386",
                    "title": "Principal Systems Engr",
                    "dept": "066002 - Tools \u0026 Automation",
                    "office": "B10 Office\/Cube 2089",
                    "email": "Robert.Callahan@neustar.biz",
                    "officePhone": "(571) 434-5165",
                    "mobilePhone": "(703) 851-5412",
                    "userType": "Admin",
                    "numServerBuilds": 112,
                    "dateCreated": "2014-02-04 12:27:47",
                    "userCreated": "rcallaha",
                    "dateUpdated": "2014-08-19 01:15:49",
                    "userUpdated": "rcallaha",
                    "changes": []
                },
                "chefServers": [],
                "login": {
                    "id": 1,
                    "userId": 1,
                    "numLogins": 1575,
                    "lastLogin": "2014-08-19 12:57:25",
                    "ipAddr": "10.33.204.166",
                    "userAgent": "Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.8; rv:31.0) Gecko\/20100101 Firefox\/31.0 FirePHP\/0.7.4",
                    "showMotd": false,
                    "changes": {
                        "lastLogin": {
                            "originalValue": "2014-08-19 13:15:49",
                            "modifiedValue": "2014-08-19 12:57:25"
                        }
                    }
                },
                "motd": "",
                "version": "NeuMatic Development Instance\n"
            }
        );

        httpBackend.when('GET', '/neumatic/getServer/NaN').respond(200,
            {"success": true, "server": {"id": 1428, "name": "stlabvnode03.va.neustar.com", "serverType": "vmware", "sysId": null, "businessServiceName": "Automation \u0026 Tools", "businessServiceId": "28676deba91d19001091c6685e0bdbcd", "subsystemName": "AT - Web Tools", "subsystemId": "b59a3163a95d19001091c6685e0bdbfc", "cmdbEnvironment": "Lab", "location": "Lab", "network": "172.30.32.0", "subnetMask": "255.255.255.0", "gateway": "172.30.32.1", "macAddress": "00:50:56:bb:c2:4a", "ipAddress": "172.30.32.152", "cobblerServer": "stlabvmcblr01.va.neustar.com", "cobblerDistro": "CentOS-6.3-x86_64", "cobblerKickstart": "\/var\/lib\/cobbler\/kickstarts\/baseline_6.ks", "chefServer": "stopcdvvcm01.va.neustar.com", "chefRole": "neu_collection", "chefEnv": "AUTOTOOLS-WEB-DEV", "dateCreated": "2014-08-19 10:59:34", "userCreated": "rcallaha", "dateUpdated": "2014-08-19 01:05:46", "userUpdated": "rcallaha", "okToBuild": 1, "status": "Failed", "statusText": "Burnt", "timeBuildStart": "2014-08-19 01:05:53", "timeBuildEnd": "2014-08-19 01:09:43", "dateBuilt": "2014-08-19 01:09:43", "userBuilt": null, "dateFirstCheckin": null, "archived": 0, "changes": [], "vmwareId": 1302, "serverId": 1428, "vSphereSite": "lab", "vSphereServer": "stlabvcenter02.cis.neustar.com", "dcName": "LAB", "dcUid": "datacenter-401", "ccrName": "LAB_Cluster", "ccrUid": "domain-c406", "rpUid": "resgroup-407", "hsName": "stopcdvesx02.va.neustar.com", "instanceUuid": "503b8302-fa0d-a301-263e-ec7f27750000", "vmSize": "Small", "numCPUs": 1, "memoryGB": 2, "vlanName": "VLAN32", "vlanId": "dvportgroup-417", "dateCreatedShort": "2014-08-19", "luns": [
                {"id": 2035, "serverId": 1428, "lunSizeGb": 50, "changes": []}
            ],
                "serverPoolId": 7, "isLeased": 1, "leaseStartDate": "2014-08-19", "daysToLeaseEnd": 29, "leaseAlertClass": "lease-alert-bg-normal", "leaseDuration": 30, "extensionInDays": 7, "numExtensionsAllowed": 2, "numTimesExtended": 0, "numExtensionsRemaining": 2}, "owner": {"id": 1, "firstName": "Robert", "lastName": "Callahan", "username": "rcallaha", "empId": "002386", "title": "Principal Systems Engr", "dept": "066002 - Tools \u0026 Automation", "office": "B10 Office\/Cube 2089", "email": "Robert.Callahan@neustar.biz", "officePhone": "(571) 434-5165", "mobilePhone": "(703) 851-5412", "userType": "Admin", "numServerBuilds": 112, "dateCreated": "2014-02-04 12:27:47", "userCreated": "rcallaha", "dateUpdated": "2014-08-19 01:15:49", "userUpdated": "rcallaha", "changes": []}, "logLevel": 6, "logOutput": "Returning server stlabvnode03.va.neustar.com"}
        );

    }));

    /*
    it('should have variable defaults defined', function() {
        expect(typeof scope.nav.editServer).not.toBe('undefined');
        expect(scope.nav.editServer).toBe(true);

        expect(scope.statusText).toBe('');
        expect(scope.buildRunning).toBe(false);
    });
    */

    it('should query the service', function() {
        //scope.authedUser.adminOn = true;

        NeuMaticService.setScope(scope);

        httpBackend.when('GET', '/cmdb/getBusinessServiceSubsystems/28676deba91d19001091c6685e0bdbcd').respond(200,
            {success: true,
                subsystems: [
            {"sysId": "e54ee45a213b1100ad50b9767365a5fc", "name": "Applications.APP.AT"},
            {"sysId": "b59a3163a95d19001091c6685e0bdbfc", "name": "AT - Web Tools"}
        ]});

        NeuMaticService.getSubsystemsByBSId('28676deba91d19001091c6685e0bdbcd',
            function(subsystems) {
                expect(subsystems.length).toBe(2);
                expect(subsystems[1].name).toBe("AT - Web Tools");
            }
        );
        httpBackend.flush();


        httpBackend.when('GET', '/cmdb/getEnvironments').respond(200,
            {success: true,
                environments: [
                {"id": 13, "name": "No Environment"},
                {"id": 12, "name": "Lab"},
                {"id": 3, "name": "Development"},
                {"id": 6, "name": "QE"},
                {"id": 10, "name": "Customer Test"},
                {"id": 14, "name": "Performance"},
                {"id": 4, "name": "Pre-Production"},
                {"id": 5, "name": "Production"}
            ]
            });
        NeuMaticService.getCmdbEnvironments(
            function(envs) {
                expect(envs.length).toBe(8);
                expect(envs[3]).toBe("QE");
            }
        );
        httpBackend.flush();


        httpBackend.when('GET', '/cobbler/getServers').respond(200,
            {"success": true, "servers": [
                {"name": "stlabvmcblr01.va.neustar.com", "env": "Lab", "displayValue": "[Lab] stlabvmcblr01.va.neustar.com"},
                {"name": "sonic.va.neustar.com", "env": "Sterling", "displayValue": "[Sterling] sonic.va.neustar.com"},
                {"name": "chopvprcblr01.nc.neustar.com", "env": "Charlotte", "displayValue": "[Charlotte] chopvprcblr01.nc.neustar.com"}
            ]}
        );
        NeuMaticService.getCobblerServers(
            function(servers) {
                expect(servers.length).toBe(3);
                expect(servers[1].name).toBe("sonic.va.neustar.com");
            }
        );
        httpBackend.flush();


        httpBackend.when('GET', '/chef/getServers').respond(200,
            {"success": 1, "servers": [
                {"name": "stopcdvvcm01.va.neustar.com", "env": "Lab", "displayValue": "[Lab] stopcdvvcm01.va.neustar.com"},
                {"name": "chefserverlab1.va.neustar.com", "env": "Lab (neu_collection development)", "displayValue": "[Lab (neu_collection development)] chefserverlab1.va.neustar.com"},
                {"name": "stopvprchef01.va.neustar.com", "env": "Prod", "displayValue": "[Prod] stopvprchef01.va.neustar.com"},
                {"name": "stopvprchef02.va.neustar.com", "env": "NPAC", "displayValue": "[NPAC] stopvprchef02.va.neustar.com"},
                {"name": "stnpvdvchef01.va.neustar.com", "env": "NPAC Dev", "displayValue": "[NPAC Dev] stnpvdvchef01.va.neustar.com"},
                {"name": "stomvdvchef01.va.neustar.com", "env": "OMS Dev", "displayValue": "[OMS Dev] stomvdvchef01.va.neustar.com"},
                {"name": "stomvprchef01.va.neustar.com", "env": "OMS Prod", "displayValue": "[OMS Prod] stomvprchef01.va.neustar.com"},
                {"name": "stenvdvchef01.va.neustar.com", "env": "ENUM Dev", "displayValue": "[ENUM Dev] stenvdvchef01.va.neustar.com"},
                {"name": "stenvprchef01.va.neustar.com", "env": "ENUM Prod", "displayValue": "[ENUM Prod] stenvprchef01.va.neustar.com"},
                {"name": "roc-chef01.ticprod.com", "env": "NIS Prod", "displayValue": "[NIS Prod] roc-chef01.ticprod.com"}
            ], "logOutput": "Retrieved the list of chef servers", "logLevel": 7}
        );

        httpBackend.when('GET', '/vmware/getDataCenters').respond(200,
            {"success": true, "nodes": [
                {"vSphereSite": "lab", "vSphereServer": "stlabvcenter03.va.neustar.com", "uid": "datacenter-401", "name": "LAB", "dcName": "LAB", "dcUid": "datacenter-401", "hasChildren": true, "childrenType": "ClusterComputeResources", "index": 1},
                {"vSphereSite": "prod", "vSphereServer": "stopvcenter02.va.neustar.com", "uid": "datacenter-444", "name": "Charlotte", "dcName": "Charlotte", "dcUid": "datacenter-444", "hasChildren": true, "childrenType": "ClusterComputeResources", "index": 2},
                {"vSphereSite": "prod", "vSphereServer": "stopvcenter02.va.neustar.com", "uid": "datacenter-449", "name": "Sterling", "dcName": "Sterling", "dcUid": "datacenter-449", "hasChildren": true, "childrenType": "ClusterComputeResources", "index": 3}
            ], "logLevel": 6, "logOutput": "3 datacenters returned"}
        );

        httpBackend.when('GET', '/neumatic/getVMSizes').respond(200,
            {"success": true, "vmSizes": [
                {"name": "Small", "numCPUs": 1, "memoryGB": 2, "luns": [50], "displayValue": "Small: 1CPU, 2GB Mem, 50GB HD"},
                {"name": "Medium", "numCPUs": 2, "memoryGB": 8, "luns": [30, 120], "displayValue": "Medium: 2CPU, 8GB Mem, 30GB \u0026 120GB HDs"},
                {"name": "Large", "numCPUs": 4, "memoryGB": 16, "luns": [30, 220], "displayValue": "Large: 4CPU, 16GB Mem, 30GB \u0026 220GB HDs"}
            ], "logLevel": 6, "logOutput": "Standard VM sizes was returned"}
        );

        httpBackend.when('GET', '/vmware/getClusterComputeResources/datacenter-401?vSphereSite=lab').respond(200,
            {"success": true, "nodes": [
                {"vSphereSite": "lab", "vSphereServer": "stlabvcenter03.va.neustar.com", "dcUid": "datacenter-401", "dcName": "LAB", "uid": "domain-c406", "ccrUid": "domain-c406", "name": "LAB_Cluster", "ccrName": "LAB_Cluster", "rpUid": "resgroup-407", "hasChildren": true, "childrenType": "ComputeResourceVMs", "index": 1},
                {"vSphereSite": "lab", "vSphereServer": "stlabvcenter03.va.neustar.com", "dcUid": "datacenter-401", "dcName": "LAB", "uid": "domain-c4020", "ccrUid": "domain-c4020", "name": "NSX_Cluster", "ccrName": "NSX_Cluster", "rpUid": "resgroup-4401", "hasChildren": true, "childrenType": "ComputeResourceVMs", "index": 2}
            ], "logLevel": 6, "logOutput": "2 cluster compute resources returned"}
        );

        httpBackend.when('GET', '/vmware/getClusterComputeResourceNetworks/domain-c406?vSphereSite=lab').respond(200,
            {"success": true, "networks": [
                {"id": "dvportgroup-4432", "name": "VLAN4", "vlanId": "dvportgroup-4432", "vlanName": "VLAN4", "displayValue": "VLAN4"},
                {"id": "dvportgroup-4404", "name": "VLAN20", "vlanId": "dvportgroup-4404", "vlanName": "VLAN20", "displayValue": "VLAN20"},
                {"id": "dvportgroup-416", "name": "VLAN21", "vlanId": "dvportgroup-416", "vlanName": "VLAN21", "displayValue": "VLAN21"},
                {"id": "dvportgroup-417", "name": "VLAN32", "vlanId": "dvportgroup-417", "vlanName": "VLAN32", "displayValue": "VLAN32"}
            ], "logLevel": 6, "logOutput": "4 cluster compute resource networks returned"}
        );



        /*
         mockNeuMaticResponse = [
         {"sysId": "e54ee45a213b1100ad50b9767365a5fc", "name": "Applications.APP.AT"},
         {"sysId": "b59a3163a95d19001091c6685e0bdbfc", "name": "AT - Web Tools"}
         ];
         queryDeferred.resolve(mockNeuMaticResponse);
         $rootScope.$apply();

         expect(mockNeuMaticService.query).toHaveBeenCalled();
         */
    });

    /*
     it('should have a working NeuMaticService', inject(function(NeuMaticService) {
     expect(NeuMaticService).not.to.equal(null);
     NeuMaticService.setScope(scope);

     httpBackend.expectGET('/cmdb/getBusinessServiceSubsystems/28676deba91d19001091c6685e0bdbcd').respond(200, [
     {"sysId": "e54ee45a213b1100ad50b9767365a5fc", "name": "Applications.APP.AT"},
     {"sysId": "b59a3163a95d19001091c6685e0bdbfc", "name": "AT - Web Tools"}
     ]);

     NeuMaticService.getSubsystemsByBSId('28676deba91d19001091c6685e0bdbcd',
     function(subsystems) {
     expect(subsystems.length).to.equal(2);
     expect(subsystems[1]).to.equal("AT - Web Tools");
     });
     httpBackend.flush();
     }
     ));
     */

});
