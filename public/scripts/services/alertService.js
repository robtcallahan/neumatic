angular.module('NeuMatic')
    .factory('AlertService', function($modal) {
        var ModalInstanceCtrl = function($scope, $modalInstance, alert) {
            $scope.alert = alert;

            $scope.ok = function() {
                $modalInstance.dismiss($scope);
                if (alert.callback) {
                    alert.callback();
                }
            };
        };

        return {
            showAlert: function(options) {
                var type = options.type || 'success',
                    title = options.title || 'Info',
                    message = options.message || '',
                    trace = options.trace || '',
                    callback = options.callback || '';

                if (message === '') return;

                var modalInstance = $modal.open({
                    templateUrl: 'views/templates/alert_modal.html',
                    controller: ModalInstanceCtrl,
                    resolve: {
                        alert: function() {
                            return {
                                type: type,
                                title: title,
                                message: message,
                                trace: trace,
                                callback: callback
                            };
                        }
                    }
                });
                modalInstance.result.then(function(alert) {
                });
            },

            ajaxAlert: function(options) {
                var message,
                    json = options.json || {},
                    apiUrl = options.apiUrl || '',
                    callback = options.callback || '',
                    result = options.result || '';


                if (typeof json.error !== "undefined") {
                    message = json.error;
                } else if (typeof json.message !== "undefined") {
                    message = json.message;
                } else {
                    message = 'An unknown error has occurred during a call to the NeuMatic API.' + '<br>';
                    if (apiUrl) {
                        message += 'API method: ' + apiUrl + '<br>';
                    }
                    if (result && result.status) {
                        message += 'Status: ' + result.status + ' ' + result.statusText;
                    }
                }

                this.showAlert({
                    type: 'danger',
                    title: 'Error',
                    message: message,
                    trace: typeof json.trace !== "undefined" ? json.trace : '',
                    callback: callback
                });
            },

            // Old methods that will be deleted once all controllers have been updated
            setAlertTitle: function(title) {
                alert.title = title;
            },

            getAlertTitle: function() {
                return alert.title;
            },

            setAlertType: function(type) {
                alert.type = type;
            },

            getAlertType: function() {
                return alert.type;
            },

            setAlertMessage: function(message) {
                alert.message = message;
            },

            getAlertMessage: function() {
                return alert.message;
            },

            setAlertShow: function(show) {
                alert.show = show;
                if (!show && alert.callback !== null) {
                    alert.callback();
                }
            },

            getAlertShow: function() {
                return alert.show;
            },

            setCallback: function(callbackFunction) {
                alert.callback = callbackFunction;
            },

            getCallback: function() {
                return alert.callback;
            }
        }
    })

