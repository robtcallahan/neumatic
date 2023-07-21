angular.module('NeuMatic')

    .directive('ngEnter', function () {
        return function (scope, element, attrs) {
            element.bind("keydown keypress", function (event) {
                if(event.which === 13) {
                    scope.$apply(function (){
                        /** @namespace attrs.ngEnter */
                        scope.$eval(attrs.ngEnter);
                    });

                    event.preventDefault();
                }
            });
        };
    })

    .directive('ngBlur', function () {
        return function (scope, element, attrs) {
            element.bind("blur", function (event) {
                scope.$apply(function (){
                    /** @namespace attrs.ngEnter */
                    scope.$eval(attrs.ngEnter);
                });

                event.preventDefault();
            });
        };
    })


    /**
     * A replacement for the ng-bind-html-unsafe directive which no longer exists
     * Reference: http://stackoverflow.com/questions/18926306/angularjs-ng-bind-html-unsafe-replacement
     */
    .directive('bindHtmlUnsafe', function($compile) {
        return function($scope, $element, $attrs) {

            var compile = function(newHTML) { // Create re-useable compile function
                newHTML = $compile(newHTML)($scope); // Compile html
                $element.html('').append(newHTML); // Clear and append it
            };

            /** @namespace $attrs.bindHtmlUnsafe */
            var htmlName = $attrs.bindHtmlUnsafe; // Get the name of the variable
            // Where the HTML is stored

            $scope.$watch(htmlName, function(newHTML) { // Watch for changes to
                // the HTML
                if (!newHTML) {
                    return;
                }
                compile(newHTML);   // Compile it
            });

        };
    });