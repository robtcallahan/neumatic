angular.module('NeuMatic')
/**
 * nodeService keeps the selected VMWare node information
 * It is set by vmwareControler and read by editServiceController
 */
    .factory('nodeService', function() {
        var node = {
            defined: false,
            vSphereSite: '',
            vSphereServer: '',
            dcUid: '',
            dcName: '',
            ccrUid: '',
            ccrName: '',
            rpUid: ''
        };
        return {
            getNode: function() {
                return node;
            },
            setNode: function(nodeObj) {
                for (var prop in nodeObj) {
                    if (nodeObj.hasOwnProperty(prop)) {
                        node[prop] = nodeObj[prop];
                    }
                }
                node.defined = true;
            },
            setDefined: function(value) {
                node.defined = value;
            },
            isDefined: function() {
                return node.defined;
            }
        };
    })

