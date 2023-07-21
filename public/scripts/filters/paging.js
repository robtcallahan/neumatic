angular.module('NeuMatic')

    .filter('paging', function() {
        return function(input, numLines, page) {
            input = input || '';
            numLines = numLines || 20;
            page = page || 1;

            var out = [];

            for (var i = 0 + ((page - 1) * numLines); i < numLines * (page); i++) {
                if (i >= input.length) {
                    return out;
                }
                out.push(input[i]);
            }
            return out;
        };
    });