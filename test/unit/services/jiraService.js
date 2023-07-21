describe('Unit: Testing Jira Service', function() {
    var scope,
        httpBackend,
        JiraService;

    // load our module
    beforeEach(module('NeuMatic'));

    it('should have a NeuMatic.JiraService defined', function() {
        expect(NeuMatic.JiraService).not.toBe(null);
    });

    beforeEach(inject(function($q, $rootScope, $controller, $httpBackend, _JiraService_) {
        scope = $rootScope.$new();
        httpBackend = $httpBackend;
        JiraService = _JiraService_;
    }));

    it('should be able to get JIRA issue types', function() {
        var html = '<div class="modal-header"><h3>NeuMatic Bug/Feedback Report</h3></div><div class="modal-body"><div class="row"><div class="form-group"><label for="fb-issueType" class="col-lg-3 control-label">Type</label><div class="col-lg-9"><select class="form-control" id="fb-issueType" ng-model="bugReport.issueType" ng-options="issueType.name for issueType in issueTypes"></select></div></div><div class="form-group"><label for="fb-summary" class="col-lg-3 control-label">Summary</label><div class="col-lg-9"><input class="form-control" id="fb-summary" ng-model="bugReport.summary" ng-required="true" /></div></div><div class="form-group"><label for="fb-description" class="col-lg-3 control-label">Description</label><div class="col-lg-9"><textarea rows="4" cols="60" class="form-control" id="fb-description" ng-model="bugReport.description" ng-required="true"></textarea></div></div></div></div><div class="modal-footer"><button class="btn btn-primary" ng-disabled="bugReport.issueType === \'\' || bugReport.summary === \'\' || bugReport.description === \'\'" ng-click="submit()">Submit</button><button class="btn btn-warning" ng-click="cancel()">Cancel</button></div>';

        httpBackend.when('GET', 'bugReportContent.html').respond(200, html);
        httpBackend.when('GET', '/jira/getIssueTypes').respond(200,
            {"success":true,
                "issueTypes":[
                    {"id":1,"name":"Bug","descr":"A problem which impairs or prevents the functions of the product."},
                    {"id":2,"name":"New Feature","descr":"A new feature of the product, which has yet to be developed."},
                    {"id":4,"name":"Improvement","descr":"An improvement or enhancement to an existing feature or task."},
                    {"id":3,"name":"Task","descr":"A task that needs to be done."},
                    {"id":14,"name":"WishList Item","descr":"A desired feature that is not critical."}
                ]
            }
        );

        JiraService.openBugReport();
        httpBackend.flush();
        expect(JiraService.getIssueTypes()).not.toBe(null);
        expect(JiraService.getIssueTypes().length).toBe(5);
        expect(JiraService.getIssueTypes()[3].name).toBe('Task');

        browser.get('http://localhost/#/');
        console.log(angular.element('#bug-report-form').html());
        //expect(element('#bug-report-form').html()).toContain('NeuMatic Bug/Feedback Report');
    });
 });
