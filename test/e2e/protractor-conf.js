exports.config = {
    seleniumAddress: 'http://localhost:4444/wd/hub',
    specs: [
        'main.js',
        'selfService.js'
    ],

    suites: {
        test: 'test.js',
        main: 'main.js',
        selfService: 'selfService.js'
    },

    capabilities: {
        browserName: 'firefox'
    },

    params: {
      login: {
        user: 'rcallaha'
      }
    },

    framework: 'jasmine',

    jasmineNodeOpts: {
      // If true, display spec names.
      isVerbose: false,
      // If true, print colors to the terminal.
      showColors: true,
      // If true, include stack traces in failures.
      includeStackTrace: true,
      // Default time to wait in ms before a test fails.
      defaultTimeoutInterval: 30000
    }
}
