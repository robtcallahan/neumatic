describe('Unit: Testing AuthedUser Service', function() {
    var scope,
        httpBackend,
        AuthedUserService;

    // load our module
    beforeEach(module('NeuMatic'));

    it('should have a NeuMatic.AuthedUserService defined', function() {
        expect(NeuMatic.AuthedUserService).not.toBe(null);
    });

    beforeEach(inject(function($q, $rootScope, $controller, $httpBackend, _AuthedUserService_) {
        scope = $rootScope.$new();
        httpBackend = $httpBackend;
        AuthedUserService = _AuthedUserService_;
    }));


    it('should be able to get user information', function() {
        httpBackend.when('GET', '/users/getAndLogUser').respond(200,
            {
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
                "motd": "Hello World",
                "version": "NeuMatic Development Instance"
            }
        );

        var user = AuthedUserService.authedUser;
        httpBackend.flush();
        expect(user.firstName).toBe('Robert');
        expect(user.numLogins).toBe(1575);
        expect(user.showMotd).toBe(false);
        expect(user.motd).toBe("Hello World");
        expect(user.version).toBe("NeuMatic Development Instance");
    });
});
