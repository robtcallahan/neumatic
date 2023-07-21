<?php
namespace Neumatic\Controller;

use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;

use Neumatic\Model;
use Zend\Log\Logger;
use \STS\AD;

class UsersController extends Base\BaseController {
		
    protected $ad;
	protected $baseDN;
    private $neumaticServer;

    /**
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch (MvcEvent $e)
    {
        $this->neumaticServer = $_SERVER['SERVER_NAME'];
        #$this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "neumaticServer: {$this->neumaticServer}"));
		return parent::onDispatch( $e );
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function indexAction() {
        $userTable = new Model\NMUserTable($this->_config);
        $logins = $userTable->getLogins();

        $serverTable  = new Model\NMServerTable($this->_config);
        $results = $serverTable->getServerCountByUsername();

        $hash = array();
        foreach ($results as $r) {
            $hash[$r['userCreated']] = $r['numServers'];
        }

        for ($i=0; $i<count($logins); $i++) {
            if (array_key_exists($logins[$i]['username'], $hash)) {
                $logins[$i]['numServers'] = intval($hash[$logins[$i]['username']]);
            } else {
                $logins[$i]['numServers'] = 0;
            }
        }

        return $this->renderView(array("success" => true, "users" => $logins));
	}

    /**
     * Get's the username from PHP, looks up in AD, updates or creates in user table, logs user in login table,
     * gets the motd, checks if the user should see the motd and then returns the user & login object and the motd.
     *
     * @return JsonModel
     */
    public function getAndLogUserAction()
    {
        $login = $this->logUser($this->_user->getUsername());

        $motd = file_exists($this->_config['motdFile']) ? file_get_contents($this->_config['motdFile']) : '';

        return $this->renderView(array(
                                     "success"     => true,
                                     "user"        => $this->_user->toObject(),
                                     "chefServers" => array(),
                                     "login"       => $login->toObject(),
                                     "motd"        => $motd,
                                     "version"     => $this->_version)
        );
    }

    public function getUsersChefServersAction() {
        $userId = $this->params()->fromRoute('id');

        $userTable = new Model\NMUserTable($this->_config);
        $user = $userTable->getById($userId);

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "user: {$user->getUserName()} ({$userId})"));

        // get allowed chef servers
        $json = $this->curlGetUrl("https://{$this->neumaticServer}/neumatic/getAllowedChefServers/" . $user->getUsername());
        $chefServers = $json->chefServers;
        return $this->renderView(array(
                                     "success"     => true,
                                     "logLevel"    => Logger::INFO,
                                     "logOutput"   => "Got Chef servers for user {$user->getUsername()}",
                                     "chefServers" => $chefServers
                                 )
        );
    }

    /**
     * @param $uid
     * @return Model\NMLogin
     */
    private function logUser($uid) {
        $adUser = new AD\ADUser();
        $adTable = new AD\ADUserTable($this->_config);
        try {
            $adUser = $adTable->getByUid($uid);
        } catch (\Exception $e) {
            // could not find user in Active Directory. Just return
            $this->renderView(array("success" => true));
        }

        $userTable = new Model\NMUserTable($this->_config);
        $user = $userTable->getByUserName($uid);

        if ($adUser && $adUser->getFirstName()) {
            $user->setEmpId($adUser->getEmpId())
                ->setFirstName($adUser->getFirstName())
                ->setLastName($adUser->getLastName())
                ->setTitle($adUser->getTitle())
                ->setDept($adUser->getDept())
                ->setEmail($adUser->getEmail())
                ->setOffice($adUser->getOffice())
                ->setOfficePhone($adUser->getOfficePhone())
                ->setMobilePhone($adUser->getMobilePhone());
        }
        $now = date('Y-m-d H:i:s');
        $user->setUserUpdated($this->_user->getUsername())
            ->setDateUpdated($now);
        if ($user->getId()) {
            $user = $userTable->update($user);
        } else {
            // user does not exist in the local user table. create an entry
            $user->setUserName($uid)
                ->setUserType('User')
                ->setMaxPoolServers(3)
                ->setUserCreated($this->_user->getUsername())
                ->setDateCreated($now);
            $user = $userTable->create($user);
        }

        $loginTable = new Model\NMLoginTable($this->_config);
        $login = $loginTable->record($user->getId());
        return $login;
    }

    /**
     * @return JsonModel
     */
    public function getAuthedUserAction()
    {
        return $this->renderView(array("success" => true, "user" => $this->_user->toObject()));
    }

    /**
     * @return JsonModel
     */
    public function getUserAction()
    {
        $userId = $this->params()->fromRoute('id');

        // get the user from db
        $userTable = new Model\NMUserTable($this->_config);
        $user = $userTable->getById($userId);

        // get login info
        $loginTable = new Model\NMLoginTable($this->_config);
        $login = $loginTable->getByUserId($user->getId());

        $userObj = $user->toObject();
        $userObj->numLogins = $login->getNumLogins();
        $userObj->lastLogin = $login->getLastLogin();
        $userObj->ipAddr = $login->getIpAddr();
        $userObj->userAgent = $login->getUserAgent();
        $userObj->chefServers = array();

        return $this->renderView(array("success" => true, "user" => $userObj));
    }

    public function getUsersAction() {
        $userTable = new Model\NMUserTable($this->_config);
        $results = $userTable->getAll();
        $users = array();
        foreach ($results as $user) {
            $users[] = $user->toObject();
        }
        return $this->renderView(array("success" => true, "users" => $users));
    }

    /**
     * @return \Zend\View\Model\JsonModel
     */
    public function incrementBuildCountAction() {
        $userId = $this->params()->fromRoute('id');
        $userTable = new Model\NMUserTable($this->_config);
        $user = $userTable->getById($userId);
        $user->setNumServerBuilds($user->getNumServerBuilds() + 1);
        $userTable->update($user);
        return $this->renderView(array("success" => true));
    }


    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

}
	
