describe('Unit: Testing NeuMatic Service', function() {
    var scope,
        httpBackend,
        NeuMaticService;

    // load our module
    beforeEach(module('NeuMatic'));

    it('should have a NeuMatic.NeuMaticService defined', function() {
        expect(NeuMatic.NeuMaticService).not.toBe(null);
    });

    beforeEach(inject(function($q, $rootScope, $controller, $httpBackend, _NeuMaticService_) {
        scope = $rootScope.$new();
        httpBackend = $httpBackend;
        NeuMaticService = _NeuMaticService_;

        NeuMaticService.setScope(scope);
    }));

    it('should be able to get subsystem by business service id', function() {
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
    });


    it('should be able to get CMDB environments', function() {
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
    });

    it('should be able to get NeuMatic VM sizes', function() {
        httpBackend.when('GET', '/neumatic/getVMSizes').respond(200,
            {"success": true, "vmSizes": [
                {"name": "Small", "numCPUs": 1, "memoryGB": 2, "luns": [50], "displayValue": "Small: 1CPU, 2GB Mem, 50GB HD"},
                {"name": "Medium", "numCPUs": 2, "memoryGB": 8, "luns": [30, 120], "displayValue": "Medium: 2CPU, 8GB Mem, 30GB \u0026 120GB HDs"},
                {"name": "Large", "numCPUs": 4, "memoryGB": 16, "luns": [30, 220], "displayValue": "Large: 4CPU, 16GB Mem, 30GB \u0026 220GB HDs"}
            ], "logLevel": 6, "logOutput": "Standard VM sizes was returned"}
        );
        NeuMaticService.getVMSizes(
            function(vms) {
                expect(vms.length).toBe(3);
                expect(vms[1].luns.length).toBe(2);
                expect(vms[2].memoryGB).toBe(16);
            }
        );
        httpBackend.flush();
    });


    it('should be able to get Cobbler servers', function() {
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
    });

    it('should be able to get Chef servers', function() {
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
        NeuMaticService.getChefServers(
            function(servers) {
                expect(servers.length).toBe(10);
                expect(servers[3].name).toBe("stopvprchef02.va.neustar.com");
                expect(servers[5].env).toBe("OMS Dev");
            }
        );
        httpBackend.flush();
    });

    it('should be able to get VMware datacenters', function() {
        httpBackend.when('GET', '/vmware/getDataCenters').respond(200,
            {"success": true, "nodes": [
                {"vSphereSite": "lab", "vSphereServer": "stlabvcenter03.va.neustar.com", "uid": "datacenter-401", "name": "LAB", "dcName": "LAB", "dcUid": "datacenter-401", "hasChildren": true, "childrenType": "ClusterComputeResources", "index": 1},
                {"vSphereSite": "prod", "vSphereServer": "stopvcenter02.va.neustar.com", "uid": "datacenter-444", "name": "Charlotte", "dcName": "Charlotte", "dcUid": "datacenter-444", "hasChildren": true, "childrenType": "ClusterComputeResources", "index": 2},
                {"vSphereSite": "prod", "vSphereServer": "stopvcenter02.va.neustar.com", "uid": "datacenter-449", "name": "Sterling", "dcName": "Sterling", "dcUid": "datacenter-449", "hasChildren": true, "childrenType": "ClusterComputeResources", "index": 3}
            ], "logLevel": 6, "logOutput": "3 datacenters returned"}
        );
        NeuMaticService.getVmwareDataCenters(
            function(datacenters) {
                expect(datacenters.length).toBe(3);
                expect(datacenters[1].vSphereServer).toBe("stopvcenter02.va.neustar.com");
                expect(datacenters[2].uid).toBe("datacenter-449");
            }
        );
        httpBackend.flush();
    });

    it('should be able to get VMware cluster compute resources', function() {
        httpBackend.when('GET', '/vmware/getClusterComputeResources/datacenter-401?vSphereSite=lab').respond(200,
            {"success": true, "nodes": [
                {"vSphereSite": "lab", "vSphereServer": "stlabvcenter03.va.neustar.com", "dcUid": "datacenter-401", "dcName": "LAB", "uid": "domain-c406", "ccrUid": "domain-c406", "name": "LAB_Cluster", "ccrName": "LAB_Cluster", "rpUid": "resgroup-407", "hasChildren": true, "childrenType": "ComputeResourceVMs", "index": 1},
                {"vSphereSite": "lab", "vSphereServer": "stlabvcenter03.va.neustar.com", "dcUid": "datacenter-401", "dcName": "LAB", "uid": "domain-c4020", "ccrUid": "domain-c4020", "name": "NSX_Cluster", "ccrName": "NSX_Cluster", "rpUid": "resgroup-4401", "hasChildren": true, "childrenType": "ComputeResourceVMs", "index": 2}
            ], "logLevel": 6, "logOutput": "2 cluster compute resources returned"}
        );
        NeuMaticService.getVmwareComputeClusters('datacenter-401', 'lab',
            function(ccrs) {
                expect(ccrs.length).toBe(2);
                expect(ccrs[0].ccrUid).toBe("domain-c406");
                expect(ccrs[1].name).toBe("NSX_Cluster");
            }
        );
        httpBackend.flush();
    });

    it('should be able to get VMware cluster compute resource networks', function() {
        httpBackend.when('GET', '/vmware/getClusterComputeResourceNetworks/domain-c406?vSphereSite=lab').respond(200,
            {"success": true, "networks": [
                {"id": "dvportgroup-4432", "name": "VLAN4", "vlanId": "dvportgroup-4432", "vlanName": "VLAN4", "displayValue": "VLAN4"},
                {"id": "dvportgroup-4404", "name": "VLAN20", "vlanId": "dvportgroup-4404", "vlanName": "VLAN20", "displayValue": "VLAN20"},
                {"id": "dvportgroup-416", "name": "VLAN21", "vlanId": "dvportgroup-416", "vlanName": "VLAN21", "displayValue": "VLAN21"},
                {"id": "dvportgroup-417", "name": "VLAN32", "vlanId": "dvportgroup-417", "vlanName": "VLAN32", "displayValue": "VLAN32"}
            ], "logLevel": 6, "logOutput": "4 cluster compute resource networks returned"}
        );
        NeuMaticService.getVmwareClusterComputeResourceNetworks('domain-c406', 'lab',
            function(vlans) {
                expect(vlans.length).toBe(4);
                expect(vlans[0].id).toBe("dvportgroup-4432");
                expect(vlans[1].name).toBe("VLAN20");
            }
        );
        httpBackend.flush();
    });
});
