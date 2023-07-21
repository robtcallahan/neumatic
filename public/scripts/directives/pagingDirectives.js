angular.module('NeuMatic')
    .directive('tablePager', function(filterFilter, pagingFilter, orderByFilter) {
        return {
            restrict: 'E',
            replace: true,
            scope: {
                pagerData: '=input',
                pagedData: '=output',
                predicate: '=',
                reverse:   '='
            },
            templateUrl: 'views/templates/pager.html',

            link: function(scope) {
                var debug = false;

                //scope.pagerData = [];
                scope.pagedData = [];

                scope.searchText = '';
                scope.linesPerPage = 30;
                scope.page = 1;
                scope.linesFrom = 1;
                scope.linesTo = scope.page;
                scope.linesTotal = scope.page;
                scope.prevEnabled = false;
                scope.nextEnabled = false;

                scope.$watch('pagerData', function(newValue, oldValue) {
                    if (newValue !== oldValue) {
                        if (debug) console.log("watch(pagerData) change detected");
                        scope.pagedData = [];
                        for (var i=0; i<scope.pagerData.length; i++) {
                            scope.pagedData.push(scope.pagerData[i]);
                        }
                        if (debug) console.log("watch(pagerData) pagedData length=" + scope.pagedData.length);
                        filterData();
               		}
               	});

                scope.$watch('searchText', function(newValue, oldValue) {
                    if (newValue !== oldValue) {
                        if (debug) console.log("watch(searchText)=" + newValue);
                        scope.page = 1;
                        filterData();
               		}
               	});

                scope.$watch('linesPerPage', function(newValue, oldValue) {
                    if (newValue !== oldValue) {
                        if (debug) console.log("watch(linesPerPage)=" + newValue);
                        filterData();
               		}
               	});

                scope.$watch('page', function(newValue, oldValue) {
                    if (newValue !== oldValue) {
                        if (debug) console.log("watch(page)=" + newValue);
                        filterData();
               		}
               	});

                scope.$watch('predicate', function(newValue, oldValue) {
                    if (newValue !== oldValue) {
                        if (debug) console.log("watch(predicate)=" + newValue);
                        filterData();
               		}
               	});

                scope.$watch('reverse', function(newValue, oldValue) {
                    if (newValue !== oldValue) {
                        if (debug) console.log("watch(reverse)=" + newValue);
                        filterData();
               		}
               	});

                function filterData() {
                    scope.filtered = filterFilter(scope.pagerData, scope.searchText);
                    var sortedData = orderByFilter(scope.filtered, scope.predicate, scope.reverse)
                    scope.pagedData = pagingFilter(sortedData, scope.linesPerPage, scope.page);
                    if (debug) console.log("filterData() filteredLength=" + scope.filtered.length + ", pagedDataLength=" + scope.pagedData.length);

                    scope.linesFrom = (scope.page - 1) * scope.linesPerPage + 1;
                    scope.linesTo = scope.linesPerPage * scope.page - (scope.linesPerPage - scope.pagedData.length);
                    scope.linesTotal = scope.filtered.length;

                    navButtons();
                }

                function navButtons() {
                    // check if prev button should be enabled
                    if (debug) console.log("navButtons() linesPerPage=" + scope.linesPerPage + ", page=" + scope.page + ", calc=" + (scope.page - 2) * scope.linesPerPage + 1);
                    if ((scope.page - 2) * scope.linesPerPage + 1 < 0) {
                        scope.prevEnabled = false;
                    } else {
                        scope.prevEnabled = true;
                    }
                    if (debug) console.log("navButtons() prevEnabled=" + scope.prevEnabled);

                    // check if next button should be enabled
                    if (debug) console.log("navButtons() linesPerPage=" + scope.linesPerPage + ", page=" + scope.page + ", calc=" + (scope.filtered.length - (scope.linesPerPage * scope.page)) + ", filteredLength=" + scope.filtered.length);
                    if (scope.filtered.length - (scope.linesPerPage * scope.page) > 0) {
                        scope.nextEnabled = true;
                    } else {
                        scope.nextEnabled = false;
                    }
                    if (debug) console.log("navButtons() nextEnabled=" + scope.nextEnabled);
                }

                scope.prevPage = function() {
                    navButtons();
                    if (scope.prevEnabled) {
                        scope.page -= 1;
                    }
                };

                scope.firstPage = function() {
                    scope.page = 1;
                    navButtons();
                };

                scope.nextPage = function() {
                    navButtons();
                    if (scope.nextEnabled) {
                        scope.page += 1;
                    }
                };

            }
        }
    });