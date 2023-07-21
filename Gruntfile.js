module.exports = function(grunt) {

    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-open');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-connect');
    grunt.loadNpmTasks('grunt-karma');
    //grunt.loadNpmTasks('grunt-start-webdriver');

    grunt.initConfig({
        shell: {
            options: {
                stdout: true
            },
            npm_install: {
                command: 'npm install'
            },
            bower_install: {
                command: './node_modules/.bin/bower install'
            },
            font_awesome_fonts: {
                command: 'cp -R bower_components/components-font-awesome/font app/font'
            },
            protractor: {
                command: 'protractor test/e2e/protractor-conf.js --suite=selfService'
            },
            protractor_test: {
                command: 'protractor test/e2e/protractor-conf.js --suite=test'
            }
        },

        webdriver: {
            options: {
                debug: false,
                startCommand: 'webdriver-manager start'
            }
        },

        connect: {
            options: {
                base: 'public/'
            },
            webserver: {
                options: {
                    port: 8888,
                    keepalive: true
                }
            },
            devserver: {
                options: {
                    port: 8888
                }
            },
            testserver: {
                options: {
                    port: 9999
                }
            },
            coverage: {
                options: {
                    base: 'coverage/',
                    port: 5555,
                    keepalive: true
                }
            }
        },

        open: {
            devserver: {
                path: 'http://localhost:9876'
            },
            coverage: {
                path: 'http://localhost:5555'
            }
        },

        karma: {
            unit: {
                configFile: './test/karma-unit.conf.js',
                autoWatch: true,
                singleRun: false
            },
            unit_auto: {
                configFile: './test/karma-unit.conf.js'
            }
            /*
             midway: {
             configFile: './test/karma-midway.conf.js',
             autoWatch: false,
             singleRun: true
             },
             midway_auto: {
             configFile: './test/karma-midway.conf.js'
             },
             e2e: {
             configFile: './test/karma-e2e.conf.js',
             autoWatch: false,
             singleRun: true
             },
             e2e_auto: {
             configFile: './test/karma-e2e.conf.js'
             }
             */
        },

        watch: {
            neumatic: {
                files: ['public/styles/**/*.css', 'public/scripts/**/*.js'],
                tasks: ['concat']
            }
        },

        concat: {
            styles: {
                dest: './app/assets/app.css',
                src: [
                    'app/styles/reset.css',
                    'bower_components/components-font-awesome/css/font-awesome.css',
                    'bower_components/bootstrap.css/css/bootstrap.css',
                    'app/styles/app.css'
                ]
            },
            scripts: {
                options: {
                    separator: ';'
                },
                dest: './app/assets/app.js',
                src: [
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
                    'public/bower_components/angular-mocks/angular-mocks.js',

                    '/Users/rcallaha/node_modules/angularjs-scope.safeapply/src/Scope.SafeApply.js',

                    //App-specific Code
                    'public/scripts/directives/**/*.js',
                    'public/scripts/services/**/*.js',
                    'public/scripts/controllers/**/*.js',
                    'public/scripts/app.js'

                    //Test-Specific Code
                ]
            }
        }
    });

    //grunt.registerTask('test', ['connect:testserver','karma:unit', 'karma:midway', 'karma:e2e']);
    //grunt.registerTask('test', ['connect:testserver','karma:unit']);

    grunt.registerTask('test', ['shell:protractor_test']);

    grunt.registerTask('test:unit', ['karma:unit']);
    grunt.registerTask('test:midway', ['connect:testserver', 'karma:midway']);

    grunt.registerTask('test:e2e', ['shell:protractor']);

    //keeping these around for legacy use
    grunt.registerTask('autotest', ['autotest:unit']);
    grunt.registerTask('autotest:unit', ['connect:testserver', 'karma:unit_auto']);
    grunt.registerTask('autotest:midway', ['connect:testserver', 'karma:midway_auto']);
    grunt.registerTask('autotest:e2e', ['connect:testserver', 'karma:e2e_auto']);

    //installation-related
    grunt.registerTask('install', ['shell:npm_install', 'shell:bower_install', 'shell:font_awesome_fonts']);

    //defaults
    grunt.registerTask('default', ['test:e2e']);

    //development
    grunt.registerTask('dev', ['install', 'concat', 'connect:devserver', 'open:devserver', 'watch:neumatic']);

    //server daemon
    grunt.registerTask('serve', ['connect:webserver']);
};
