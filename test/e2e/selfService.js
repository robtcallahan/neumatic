describe('NeuMatic', function() {
    var statusButtons;

    var debug = true;
    var page;

    function setTestingFlag(boolValue) {
        var trueFalse = boolValue ? "true" : "false";
        if (debug) console.log("Setting e2eTest to " + trueFalse);
        return browser.executeScript("angular.element(arguments[0]).scope().e2eTesting = " + trueFalse + ";" +
                                     "angular.element(document.body).injector().get('$rootScope').$apply();", page);
    };

    function setTestGateOpen(boolValue) {
        var trueFalse = boolValue ? "true" : "false";
        if (debug) console.log("Setting testGateOpen to " + trueFalse);
        return browser.executeScript("angular.element(arguments[0]).scope().testGateOpen = " + trueFalse + ";" +
                                     "angular.element(document.body).injector().get('$rootScope').$apply();", page);
    };

    function showVar(varName) {
        browser.executeScript("return angular.element(arguments[0]).scope()." + varName + ";", page).then(
            function(value) {
                console.log(varName + "=[" + JSON.stringify(value) + "]");
        });
    };

    function pause(seconds) {
        browser.sleep(seconds * 1000);
    }

    it('Neu System button should go to the self service page', function() {
        browser.get('http://localhost/');

        element(by.id('neu-system')).click();
        expect(browser.getLocationAbsUrl()).toMatch('/selfService/selfService/cmdbInfo');

        page = element(by.id("selfService-page"));
        statusButtons = $$('#status-buttons a');

        setTestingFlag(true).then(
            function() {
                if (debug) showVar('e2eTesting');
            }
        )
    });

    it('self service page should have form page indicators', function() {
        expect(statusButtons.count()).toBe(4);
        expect(statusButtons.get(0).getAttribute('class')).toBe("active");
    });

    it('should load CMDB business services', function() {
        element(by.model('server.businessService')).sendKeys('Automation & Tools');
        element(by.model('server.businessService')).sendKeys(protractor.Key.TAB);
        expect(element(by.model('server.businessService')).getAttribute('value')).toBe('Automation & Tools');
    });

    it('should load CMDB subsystems from business service', function() {
        var subsystems = $$('#subsystem option');
        expect(subsystems.count()).toBe(6);

        subsystems.get(2).click();
        expect(element(by.id('subsystem')).$('option:checked').getText()).toBe('AT - Web Tools');
    });

    it('should load the CMDB environments', function() {
        element(by.model('server.subsystem')).sendKeys(protractor.Key.TAB);
        var envs = $$('#cmdbEnvironment option');
        expect(envs.count()).toBe(8);

        envs.get(4).click();
        expect(element(by.id('cmdbEnvironment')).$('option:checked').getText()).toBe('Customer Test');

        envs.get(1).click();
        expect(element(by.id('cmdbEnvironment')).$('option:checked').getText()).toBe('Lab');
    });

    it('should go to VM sizes self service page', function() {
        element(by.id('cmdb-next-button')).click().then(function() {
            browser.sleep(2000).then(function() {
                var statusButtons = $$('#status-buttons a');
                expect(statusButtons.get(1).getAttribute('class')).toBe("active");
            });
        });
    });

    it('should be able to select VM sizes', function() {
        expect(element(by.id('button-small')).getAttribute('class')).toMatch(/server-btn-pressed/);
        expect(element(by.id('button-medium')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-large')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-custom')).getAttribute('class')).not.toMatch(/server-btn-pressed/);

        element(by.id('button-medium')).click();
        expect(element(by.id('button-small')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-medium')).getAttribute('class')).toMatch(/server-btn-pressed/);
        expect(element(by.id('button-large')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-custom')).getAttribute('class')).not.toMatch(/server-btn-pressed/);

        element(by.id('button-large')).click();
        expect(element(by.id('button-small')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-medium')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-large')).getAttribute('class')).toMatch(/server-btn-pressed/);
        expect(element(by.id('button-custom')).getAttribute('class')).not.toMatch(/server-btn-pressed/);

        element(by.id('button-custom')).click();
        expect(element(by.id('button-small')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-medium')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-large')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-custom')).getAttribute('class')).toMatch(/server-btn-pressed/);

        element(by.id('button-small')).click();
    });

    it('should go to chef info self service page', function() {
        element(by.id('vmsizes-next-button')).click().then(function() {
            browser.sleep(2000).then(function() {
                var statusButtons = $$('#status-buttons a');
                expect(statusButtons.get(2).getAttribute('class')).toBe("active");
            });
        });
    });

    it('should load the Cobbler distros', function() {
        var distros = $$('#cobblerDistro option');
        expect(distros.count()).toBe(46);

        distros.get(0).click();
        expect(element(by.id('cobblerDistro')).$('option:checked').getText()).toBe('CentOS-5.10-x86_64');

        distros.get(5).click();
        expect(element(by.id('cobblerDistro')).$('option:checked').getText()).toBe('CentOS-6.3-x86_64');
    });

    it('should load the Chef roles', function() {
        var roles = $$('#chefRole option');
        expect(roles.count()).toBe(43);

        roles.get(0).click();
        expect(element(by.id('chefRole')).$('option:checked').getText()).toBe('autotools-app');

        roles.get(24).click();
        expect(element(by.id('chefRole')).$('option:checked').getText()).toBe('neu_collection');
    });

    it('should load the Chef environments', function() {
        var envs = $$('#chefEnv option');
        expect(envs.count()).toBe(19);

        //envs.get(0).click();
        //expect(element(by.id('chefEnv')).$('option:checked').getText()).toBe('AUTOTOOLS-CHEF-SERVER-DEV');

        envs.get(15).click();
        expect(element(by.id('chefEnv')).$('option:checked').getText()).toBe('ST_CORE_LAB');
    });

    it('should have CMDB values still present', function() {
        // click the button for step one
        var statusButtons = $$('#status-buttons a');
        statusButtons.get(0).click();

        expect(element(by.model('server.businessService')).getAttribute('value')).toBe('Automation & Tools');
        expect(element(by.id('subsystem')).$('option:checked').getText()).toBe('AT - Web Tools');
    });

    it('should have selected VM size still present', function() {
        var statusButtons = $$('#status-buttons a');
        statusButtons.get(1).click();

        expect(element(by.id('button-small')).getAttribute('class')).toMatch(/server-btn-pressed/);
        expect(element(by.id('button-medium')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-large')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
        expect(element(by.id('button-custom')).getAttribute('class')).not.toMatch(/server-btn-pressed/);
    });

    it('should have the Chef selections still present', function() {
        var statusButtons = $$('#status-buttons a');
        statusButtons.get(2).click();

        expect(element(by.id('cobblerDistro')).$('option:checked').getText()).toBe('CentOS-6.3-x86_64');
        expect(element(by.id('chefRole')).$('option:checked').getText()).toBe('neu_collection');
        expect(element(by.id('chefEnv')).$('option:checked').getText()).toBe('ST_CORE_LAB');
    });

    it('should go to build self service page and start the build process', function() {
        element(by.id('chef-next-button')).click().then(function() {
            browser.sleep(2000).then(function() {
                var statusButtons = $$('#status-buttons a');
                expect(statusButtons.get(3).getAttribute('class')).toBe("active");
            });
        });
    });

    it('should get the next pool server', function() {
        var status = element(by.binding('buildStatus.getPoolServer.text'));
        expect(status.getText()).toBe('Getting server from pool...');

        expect(element(by.id('get-pool-server-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('get-pool-server-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('get-pool-server-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                browser.waitForAngular().then(
                    function() {
                        expect(element(by.id('get-pool-server-img1')).getAttribute('class')).toMatch(/ng-hide/);
                        expect(element(by.id('get-pool-server-img2')).getAttribute('class')).toBe('');
                        expect(element(by.id('get-pool-server-img3')).getAttribute('class')).toBe('ng-hide');
                    });
            });
    });

    it('should save this server', function() {
        var status = element(by.binding('buildStatus.saving.text'));
        expect(status.getText()).toBe('Saving config...');

        expect(element(by.id('save-server-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('save-server-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('save-server-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                browser.waitForAngular().then(
                    function() {
                        expect(element(by.id('save-server-img1')).getAttribute('class')).toMatch(/ng-hide/);
                        expect(element(by.id('save-server-img2')).getAttribute('class')).toBe('');
                        expect(element(by.id('save-server-img3')).getAttribute('class')).toBe('ng-hide');
                    });
            });
    });

    it('should create the VM', function() {
        var status = element(by.binding('buildStatus.createVM.text'));
        expect(status.getText()).toBe('Creating VMware VM...');

        expect(element(by.id('create-vm-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('create-vm-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('create-vm-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                browser.waitForAngular().then(
                    function() {
                        expect(element(by.id('create-vm-img1')).getAttribute('class')).toMatch(/ng-hide/);
                        expect(element(by.id('create-vm-img2')).getAttribute('class')).toBe('');
                        expect(element(by.id('create-vm-img3')).getAttribute('class')).toBe('ng-hide');
                    });
            });
    });

    it('should create the CMDB CI', function() {
        var status = element(by.binding('buildStatus.createCmdbCi.text'));
        expect(status.getText()).toBe('Creating CMDB CI...');

        expect(element(by.id('create-cmdb-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('create-cmdb-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('create-cmdb-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                browser.waitForAngular().then(
                    function() {
                        expect(element(by.id('create-cmdb-img1')).getAttribute('class')).toMatch(/ng-hide/);
                        expect(element(by.id('create-cmdb-img2')).getAttribute('class')).toBe('');
                        expect(element(by.id('create-cmdb-img3')).getAttribute('class')).toBe('ng-hide');
                    });
            });
    });

    it('should make coffee', function() {
        var status = element(by.binding('buildStatus.makingCoffee.text'));
        expect(status.getText()).toBe('Making coffee...');

        expect(element(by.id('making-coffee-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('making-coffee-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('making-coffee-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                pause(1.5);
                expect(element(by.id('making-coffee-img1')).getAttribute('class')).toMatch(/ng-hide/);
                expect(element(by.id('making-coffee-img2')).getAttribute('class')).toBe('');
                expect(element(by.id('making-coffee-img3')).getAttribute('class')).toBe('ng-hide');
            });
    });

    it('should add host to LDAP', function() {
        var status = element(by.binding('buildStatus.ldapUpdate.text'));
        expect(status.getText()).toBe('Adding host to LDAP...');

        expect(element(by.id('update-ldap-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('update-ldap-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('update-ldap-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                browser.waitForAngular().then(
                    function() {
                        expect(element(by.id('update-ldap-img1')).getAttribute('class')).toMatch(/ng-hide/);
                        expect(element(by.id('update-ldap-img2')).getAttribute('class')).toBe('');
                        expect(element(by.id('update-ldap-img3')).getAttribute('class')).toBe('ng-hide');
                    });
            });
    });

    it('should add host to Cobbler', function() {
        var status = element(by.binding('buildStatus.cobblerUpdate.text'));
        expect(status.getText()).toBe('Creating Cobbler profile...');

        expect(element(by.id('update-cobbler-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('update-cobbler-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('update-cobbler-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                browser.waitForAngular().then(
                    function() {
                        expect(element(by.id('update-cobbler-img1')).getAttribute('class')).toMatch(/ng-hide/);
                        expect(element(by.id('update-cobbler-img2')).getAttribute('class')).toBe('');
                        expect(element(by.id('update-cobbler-img3')).getAttribute('class')).toBe('ng-hide');
                    });
            });
    });

    it('should order pizza', function() {
        var status = element(by.binding('buildStatus.orderPizza.text'));
        expect(status.getText()).toBe('Ordering pizza...');

        expect(element(by.id('ordering-pizza-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('ordering-pizza-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('ordering-pizza-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                pause(1.5);
                expect(element(by.id('ordering-pizza-img1')).getAttribute('class')).toMatch(/ng-hide/);
                expect(element(by.id('ordering-pizza-img2')).getAttribute('class')).toBe('');
                expect(element(by.id('ordering-pizza-img3')).getAttribute('class')).toBe('ng-hide');
            });
    });

    it('should power on the VM', function() {
        var status = element(by.binding('buildStatus.powerOnVm.text'));
        expect(status.getText()).toBe('Powering on VM...');

        expect(element(by.id('poweron-vm-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('poweron-vm-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('poweron-vm-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                browser.waitForAngular().then(
                    function() {
                        expect(element(by.id('poweron-vm-img1')).getAttribute('class')).toMatch(/ng-hide/);
                        expect(element(by.id('poweron-vm-img2')).getAttribute('class')).toBe('');
                        expect(element(by.id('poweron-vm-img3')).getAttribute('class')).toBe('ng-hide');
                    });
            });
    });

    it('should start the Cobbler watcher', function() {
        var status = element(by.binding('buildStatus.startWatcher.text'));
        expect(status.getText()).toBe('Starting Cobbler watcher...');

        expect(element(by.id('cobbler-watcher-img1')).getAttribute('class')).toBe('mask-spinner');
        expect(element(by.id('cobbler-watcher-img2')).getAttribute('class')).toBe('ng-hide');
        expect(element(by.id('cobbler-watcher-img3')).getAttribute('class')).toBe('ng-hide');

        setTestGateOpen(true).then(
            function() {
                browser.waitForAngular().then(
                    function() {
                        expect(element(by.id('cobbler-watcher-img1')).getAttribute('class')).toMatch(/ng-hide/);
                        expect(element(by.id('cobbler-watcher-img2')).getAttribute('class')).toBe('');
                        expect(element(by.id('cobbler-watcher-img3')).getAttribute('class')).toBe('ng-hide');
                    });
            });
    });
});
