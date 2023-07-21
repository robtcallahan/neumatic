describe('NeuMatic', function() {

    it('main page should have a title', function() {
        browser.get('http://localhost/');
        expect(browser.getTitle()).toEqual('NeuMatic');
    });

    it('Get Started button should go to help page', function() {
        element(by.id('get-started')).click();
        expect(element(by.id('getting-started')).getText()).toBe('Getting Started');
    });

});
