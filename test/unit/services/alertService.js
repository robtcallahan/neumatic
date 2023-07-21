describe('Unit: Testing Alert Service', function() {
    var $scope,
        $modal,
        $httpBackend,
        AlertService;

    // load our module
    beforeEach(function() {
        module('NeuMatic');
    });

    beforeEach(function() {
        //$modal = {};

        /*
        module('NeuMatic', function($provide) {
          $provide.value('$modal', $modal);
        });
        */

        inject(function($injector, _$modal_, _$httpBackend_, $q, _AlertService_) {
            $scope = $injector.get('$rootScope').$new();
            //$modal = $injector.get('$modal');
            $httpBackend = _$httpBackend_;
            AlertService = _AlertService_;
            $modal = _$modal_;

        });


        // Mock APIs
        //$modal.open = sinon.stub();
        spyOn($modal, "open").andCallFake(function() {
            var deferred = $q.defer();
            deferred.resolve('Remove call result');
            return deferred.promise;
        });
    });

    it('can do remote call', inject(function() {
        AlertService.showAlert()
            .then(function() {
                console.log("success");
            })
    }));



    /*
    it('calls $modal.open with the correct params', function(){
        var options = {
            title: 'Info',
            message: 'It worked'
        };
        var expected = {
            templateUrl: 'views/templates/alert_modal.html',
            resolve: {
                alert: sinon.match(function(value) {
                    return value() === options;
                }, 'boo!')
            },
            result: {
                then: sinon.match.any
            },
            controller: sinon.match.any
        };

        // Execution
        $scope.alertService = AlertService;
        $scope.alertService.showAlert(options);

        // Expectation
        expect($modal.open).to.have
            .been.calledOnce
            .and.calledWithMatch(expected);
    });
    */

    /*
    var modalInstance = {};
    beforeEach(function() {
        modalInstance = {                    // Create a mock object using spies
          close: function() {}, //sinon.spy(modalInstance, 'close'),
          dismiss: function() {}, //sinon.spy(modalInstance, 'dismiss'),
          result: {
            then: function() {} //sinon.spy(modalInstance, 'result.then')
          }
        };
        modalInstance.close = sinon.spy(modalInstance, 'close');
        modalInstance.dismiss = sinon.spy(modalInstance, 'dismiss');
        modalInstance.result.then = sinon.spy(modalInstance, 'result.then');
    });

    beforeEach(inject(function(_$rootScope_, _$httpBackend_, _AlertService_) {
        scope = _$rootScope_.$new();
        httpBackend = _$httpBackend_;
        AlertService = _AlertService_;
    }));
    */

    /*
    it('should have a AlertService defined', function() {
        expect(AlertService).to.be.a('object');
    });

    it('should have working getters and setters', function() {
        AlertService.setAlertTitle('Title');
        assert.equal(AlertService.getAlertTitle(), 'Title');

        AlertService.setAlertType('danger');
        assert.equal(AlertService.getAlertType(), 'danger');

        AlertService.setAlertMessage('Hello World');
        assert.equal(AlertService.getAlertMessage(), 'Hello World');

        AlertService.setAlertShow(true);
        assert.equal(AlertService.getAlertShow(), true);

        AlertService.setCallback(function() {});
        assert.typeOf(AlertService.getCallback(), 'function');
    });
    */
});
