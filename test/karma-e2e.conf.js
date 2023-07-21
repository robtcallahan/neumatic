var sharedConfig = require('./karma-shared.conf');

module.exports = function(config) {
  var conf = sharedConfig();

  conf.files = conf.files.concat([
    '../public/bower_components/angular-scenario/angular-scenario.js',
    './test/e2e/**/*.js'
  ]);

  conf.proxies = {
    '/': 'http://localhost:9999/'
  };

  conf.urlRoot = '/__karma__/';

  conf.frameworks = ['ng-scenario'];

  config.set(conf);
};
