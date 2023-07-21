describe('NeuMatic', function() {


    var debug = true;
    var page = element(by.id("selfService-page"));

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
    });

    it('should load CMDB business services', function() {
        setTestingFlag(true).then(
            function() {
                if (debug) showVar('e2eTesting');
                element(by.model('server.businessService')).sendKeys('Automation & Tools');
                element(by.model('server.businessService')).sendKeys(protractor.Key.TAB);
                expect(element(by.model('server.businessService')).getAttribute('value')).toBe('Automation & Tools');
            }
        );
    });

    it('should load CMDB subsystems from business service', function() {
        pause(5);
        setTestGateOpen(true).then(function() {
            pause(1);
            var subsystems = $$('#subsystem option');
            expect(subsystems.count()).toBe(6);

            subsystems.get(2).click();
            expect(element(by.id('subsystem')).$('option:checked').getText()).toBe('AT - Web Tools');
        });
        pause(15);
    });
});
