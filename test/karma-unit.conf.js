var sharedConfig = require('./karma-shared.conf.js');

module.exports = function(config) {
    var conf = sharedConfig();

    conf.files = conf.files.concat([
        //extra testing code
        'public/bower_components/angular-mocks/angular-mocks.js',

        //'public/bower_components/angular-scenario/angular-scenario.js',

        //test files
        //'./test/unit/controllers/mainController.js',
        //'./test/unit/controllers/editServerController.js',

        //'./test/unit/services/neumaticService.js',
        //'./test/unit/services/authedUserService.js',
        './test/unit/services/alertService.js',
        //'./test/unit/services/jiraService.js'
    ]);

    config.set(conf);
};
