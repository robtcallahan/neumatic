angular.module('NeuMatic')

.controller('AddCookbookCtrl', function($scope, $stateParams, $log, $http, $location, AuthedUserService, JiraService, AlertService) {

	$scope.ajaxError = "";

	$scope.authedUserService = AuthedUserService; 
	$scope.authedUser = $scope.authedUserService.authedUser;
	
	$scope.jiraService = JiraService;
	$scope.alertService = AlertService;

	$scope.authorizedEdit = false;
	if ($scope.authedUser.adminOn == true) {
		$scope.authorizedEdit = true;
	}

	$scope.alert = {
		show : false,
		title : 'Error!',
		type : 'danger',
		message : ''
	};

	$scope.modal = {
		title : '',
		message : ''
	};

	$scope.nav = {
		editCookbooks : true
	};

	$scope.back = function() {
		history.back();
	};

	function setCookie(cname, cvalue, exdays) {
		var d = new Date();
		d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
		var expires = "expires=" + d.toGMTString();
		document.cookie = cname + "=" + cvalue + "; " + expires;
	}

	function getCookie(cname) {
		var name = cname + "=";
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i].trim();
			if (c.indexOf(name) == 0)
				return c.substring(name.length, c.length);
		}
		return "";
	}



	$scope.chefServer = getCookie('chef_server');
    
    $scope.getChefServers = function() {
        $http.get('/chef/getServers').success(function(data) {
       		$scope.chefServers = [];

			if ($scope.chefServer == "") {
				$scope.chefServer = data.servers[0].name;
			}

			for (var i = 0; i < data.servers.length; i++) {
				if (data.servers[i].name === $scope.chefServer) {
					$scope.chefServerSelected = data.servers[i];
				}
				if (data.servers[i].name != 'targetVersion' && data.servers[i].allowChef != false) {
					$scope.chefServers[i] = data.servers[i];
				}
			}
			$scope.chefServers = $scope.chefServers.filter(function(n){ return n != undefined }); 

		});
	}

	$scope.chefServerChange = function() {
		$scope.chefServer = $scope.chefServerSelected.name;
		setCookie('chef_server', $scope.chefServer, 1);
        	$scope.getCookbooks();
	};

	$scope.showLoading = function() {
		$('#loading-spinner').show();
	};

	$scope.hideLoading = function() {
		$('#loading-spinner').hide();
	};


	$scope.rowClass = "col-md-6";
	$scope.labelClass = "col-lg-3";
	$scope.inputClass = "col-lg-9";

	$scope.cookbooksHash = [];

	/**************************************************************************/

	$scope.showLoading();

	$http.get('/chef/getCookbooks?chef_server=' + $scope.chefServer).success(function(data) {
		// check return status
		if ( typeof data.success !== "undefined" && !data.success) {
			$scope.ajaxError(data);
			return;
		}
		$scope.cookbooks = data.cookbooks;

		for (var i = 0; i < $scope.cookbooks.length; i++) {

			$scope.cookbooksHash[$scope.cookbooks[i].name] = $scope.cookbooks[i];

		}
		//console.log($scope.cookbooksHash);
	}).error(function(data) {
		$scope.ajaxError(data);
	});

	$http.get('/git/getProjects?chef_server=' + $scope.chefServer).success(function(data) {
		// check return status
		if ( typeof data.success !== "undefined" && !data.success && data.projects !== 'undefined') {
			$scope.ajaxError(data);
			return;
		}
		
		$scope.gitProjects = data.projects;

		var projectsLength = $scope.gitProjects.length, element = null;

		$scope.projects = [];
		for (var i = 0; i < projectsLength; i++) {
			project = $scope.gitProjects[i];
			
			if (!(project.name in $scope.cookbooksHash)) {
				$scope.projects.push(project);
				$scope.hideLoading();
			}

		}
		//console.log($scope.projects);

	}).error(function(data) {
		$scope.ajaxError(data);
	});

	/**************************************************************************/

	$scope.go = function(path) {
		$location.path(path);
	};
	
	$scope.ajaxError = function(data) {

		$scope.alert.show = true;
		// $scope.alert.message = "Something went terribly wrong";

		$scope.alert.message = data;
		if ( typeof data.message !== "undefined") {
			$scope.alert.message = data.message.replace('\n\n', '<br>');
		}
		if ( typeof data.trace !== "undefined") {
			$scope.alert.message += '<br><br>Trace:<br>' + data.trace.replace('\n\n', '<br>');
		}
	}
	/************ On Load *****************************************************/
	$scope.chefServer = getCookie('chef_server');
	$scope.getChefServers();
});
