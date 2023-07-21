describe('Unit: Testing Main Controller', function() {
    var scope,
        $httpBackend,
        AuthedUserService;

    // Our tests will go here
    beforeEach(angular.mock.module('NeuMatic'));

    it('should have a MainCtrl controller', function() {
        expect(NeuMatic.MainCtrl).not.toBe(null);
    });


    beforeEach(inject(function($rootScope, $controller, _$httpBackend_, _AuthedUserService_) {
        scope = $rootScope.$new();
        $httpBackend = _$httpBackend_;
        AuthedUserService = _AuthedUserService_;

        $controller('MainCtrl', {
            $scope: scope
        });

        // define the return of the getRatings() function
        $httpBackend.when('GET', '/neumatic/getRatings').respond(200, {
            success: true,
            ratings: [
                {
                    id: 1,
                    userId: 1,
                    rating: 5.0,
                    firstName: 'Billy',
                    lastName: 'Joel'
                }
            ]
        });

        // define the return of the getStats() function
        $httpBackend.when('GET', '/neumatic/getStats').respond(200, {
            success: true,
            stats: {
                    numServers: 200,
                    numLabServers: 50,
                    numUsers: 100,
                    rating: 5.0,
                    numLoginsThisMonth: 25,
                    numBuilds: 1000
                }
        });

        $httpBackend.when('GET', '/users/getAndLogUser').respond(200, {
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
    }));

    if('authedUser should be defined', function() {
        expect(scope.authedUser).not.toBe(null);
        expect(scope.authedUser.username).toBe('rcallaha');
        expect(scope.authedUser.userType).toBe('Admin');
    });

    it('rating should have a value of 5', function() {
        expect(scope.rating).toBe(5);
        expect(scope.max).toBe(5);
        expect(scope.comments).toBe('');
        expect(scope.isReadonly).toBe(false);
        expect(scope.userRatingIsCollapsed).toBe(true);
        expect(typeof scope.ratingStates[0].stateOn).not.toBe('undefined');
        expect(scope.ratingStates[0].stateOn).toBe('glyphicon-star');
        expect(typeof scope.ratingStates[0].stateOff).not.toBe('undefined');
        expect(scope.ratingStates[0].stateOff).toBe('glyphicon-star-empty');
    });

    it('hoveringOver should set percent correctly', function() {
        scope.hoveringOver(2);
        expect(scope.overStar).toBe(2);
        expect(scope.percent).toBe(40);
    });

    it('getRatings should assign ratings variable', function() {
        scope.ratings = null;
        scope.getRatings();
        $httpBackend.flush();
        expect(scope.ratings).not.toBe(null);
        expect(typeof scope.ratings).toBe('object');
        expect(typeof scope.ratings[0].firstName).not.toBe('undefined');
        expect(scope.ratings[0].firstName).toBe('Billy');
    });

    it('getStats should assign stats variable', function() {
        scope.stats = null;
        scope.getStats();
        $httpBackend.flush();
        expect(scope.stats).not.toBe(null);
        expect(typeof scope.stats).toBe('object');
        expect(typeof scope.stats.numServers).not.toBe('undefined');
        expect(scope.stats.numServers).toBe(200);
    });
});
