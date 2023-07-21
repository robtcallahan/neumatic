module.exports = function() {
    return {

        // base path used to resolve all patterns (e.g. files, exclude)
        basePath: '../',

        // frameworks to use
        frameworks: ['mocha', 'sinon-chai'],

        // list of files / patterns to load in the browser
        files: [
            //3rd Party Code
            'public/bower_components/jquery/jquery.js',

            'public/bower_components/angular/angular.js',

            'public/bower_components/sass-bootstrap/js/modal.js',

            'public/bower_components/angular-bootstrap/ui-bootstrap.js',
            'public/bower_components/angular-bootstrap/ui-bootstrap-tpls.js',

            'public/bower_components/angular-resource/angular-resource.js',
            'public/bower_components/angular-cookies/angular-cookies.js',
            'public/bower_components/angular-sanitize/angular-sanitize.js',
            'public/bower_components/angular-route/angular-route.js',
            'public/bower_components/angular-ui-router/release/angular-ui-router.min.js',
            'public/bower_components/angular-animate/angular-animate.min.js',
            'public/bower_components/angular-dragdrop/angular-dragdrop.js',
            'public/bower_components/angular-tree-control/angular-tree-control.js',

            'public/bower_components/angularjs-scope.safeapply/src/Scope.SafeApply.js',

            //App-specific Code
            'public/scripts/directives/**/*.js',
            'public/scripts/services/**/*.js',
            'public/scripts/controllers/**/*.js',
            'public/scripts/filters/**/*.js',
            'public/scripts/app.js'

            //Test-Specific Code
        ],

        // list of files to exclude
        exclude: [],

        // test results reporter to use
        reporters: ['progress'],

        // web server port
        port: 9876,

        // enable / disable colors in the output (reporters and logs)
        colors: true,

        // level of logging
        logLevel: config.LOG_INFO,

        // enable / disable watching file and executing tests on file changes
        autoWatch: true,

        // start these browsers
        browsers: ['PhantomJS'],

        // Continuous Integration mode
        // if true, Karma captures browsers, runs the tests and exits
        singleRun: false
    }
};
