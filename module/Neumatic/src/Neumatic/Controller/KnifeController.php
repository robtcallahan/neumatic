<?php

namespace Neumatic\Controller;

use Zend\Json\Server\Exception\ErrorException;
use Zend\Log\Logger;
use Zend\View\Model\JsonModel;
use Zend\Mvc\MvcEvent;

use STS\Util\SSH2;

class KnifeController extends Base\BaseController {

    /**
     * apache chef workstation dir
     * @var $chefWsDir
     */
    private $chefWsDir = "/var/chef";

    private $neumaticServer;

    /**
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e) {
        $this->neumaticServer = $_SERVER['SERVER_NAME'];

        return parent::onDispatch($e);
    }

    /**
     * This action should never be called
     *
     * @return JsonModel
     */
    public function indexAction() {

        return $this->renderview(array("error"=>"This controller has no output from index. Eventually I would like to display the documentation here."));
    }


    /**
     *
     * knife bootstrap FQDN
     *      --ssh-user rcallaha --ssh-password password
     *      --sudo --use-sudo-password
     *      --run-list 'role[neu_base]' --environment ST_CORE_LAB
     *      --format json
     *      --yes
     *
     * @throws \ErrorException
     * @return JsonModel
     */
    public function bootstrapAction() {
        
       
        $chefServer = $this->params()->fromPost('chefServer');
        $chefRole = $this->params()->fromPost('chefRole');
        $chefEnv = $this->params()->fromPost('chefEnv');

        $username = $this->params()->fromPost('username');
        $password = $this->params()->fromPost('password');

        $hostName = $this->params()->fromPost('hostName');
        
        
        $noDelete = $this->params()->fromPost('noDelete');
        
        $this->writeLog(array(
            "logLevel" => Logger::DEBUG,
            "logOutput" => "chefServer={$chefServer}, chefRole={$chefRole}, chefEnv={$chefEnv}, hostName={$hostName}"
        ));

        $output = array();
        $output[] = "Removing host from Chef...";

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "Instantiating SSH2"));
        try {
            $ssh = new SSH2($hostName);
        } catch (\Exception $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "SSH connection to {$hostName} failed. Check network connectivity",
                "logLevel" => Logger::ERR,
                "logOutput" => "SSH connection to {$hostName} failed"
            ));
        }

        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "Logging into " . $hostName));
        try {
            $ssh->loginWithPassword($this->_config['stsappsUser'], $this->_config['stsappsPassword']);
        } catch (\ErrorException $e) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Login to {$hostName} failed. Check credentials",
                "logLevel" => Logger::ERR,
                "logOutput" => "Login to {$hostName} failed."
            ));
        }
        $stream = $ssh->getShell(false, 'vt102', Array(), 4096);
        $prompt = ']$ ';

        // stop the chef-client service
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "command=sudo service chef-client stop"));
        $buffer = '';
        $prompt = 'assword:';
        $ssh->writePrompt("/usr/bin/sudo /sbin/service chef-client stop");
        $ssh->waitPrompt($prompt, $buffer, 2);
       
        $output[] = $buffer;
        $prompt = ']$ ';

        $buffer = '';
        $setHostnameCommand = "sudo hostname ".$hostName;

        $ssh->writePrompt($setHostnameCommand);
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output[] = $buffer;
         
        //delete any existing client.pem
        $buffer = '';
        $deleteClientPemCommand = "sudo rm -f /etc/chef/client.pem";
        $ssh->writePrompt($deleteClientPemCommand);
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output[] = $buffer; 
        
         //delete chef-initial-run.log
        $buffer = '';
        $deleteInitialRunLogCommand = "sudo rm -f /var/log/chef-initial-run.log";
        $ssh->writePrompt($deleteInitialRunLogCommand);
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output[] = $buffer; 
         
        // get the current Chef server if exists
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "getting Chef server"));
        $buffer = '';
        $ssh->writePrompt("sudo grep chef_server /etc/chef/client.rb 2> /dev/null | awk -F'/' '{print $3}' | awk -F'\"' '{print $1}'");
        $ssh->waitPrompt($prompt, $buffer, 2);

        $currentChefServer = "";
        if (preg_match("/\n(.*\.com)/", $buffer, $m)) {
            $currentChefServer = $m[1];
        }
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "currentChefServer={$currentChefServer}"));

        if (!$currentChefServer) {
            $currentChefServer = $chefServer;
        }
        // remove the client & node from the chef server
        if (!$this->curlGetUrl("https://{$this->neumaticServer}/chef/deleteNode/{$hostName}?chef_server={$currentChefServer}")) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Could not remove node {$hostName} from Chef server {$currentChefServer}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Could not remove node {$hostName} from Chef server {$currentChefServer}"
            ));
        }
        if (!$this->curlGetUrl("https://{$this->neumaticServer}/chef/deleteClient/{$hostName}?chef_server={$currentChefServer}")) {
            return $this->renderView(array(
                "success" => false,
                "error" => "Could not remove client {$hostName} from Chef server {$currentChefServer}",
                "logLevel" => Logger::ERR,
                "logOutput" => "Could not remove client {$hostName} from Chef server {$currentChefServer}"
            ));
        }
        
        // create trusted_certs dir
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "command=sudo mkdir /etc/chef/trusted_certs"));
        $buffer = '';
        $ssh->writePrompt("sudo mkdir -p /etc/chef/trusted_certs");
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output[] = $buffer;
        
        if(!isset($noDelete) OR $noDelete == false){
            // remove the chef RPM
            $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "command=sudo yum erase chef"));
            $buffer = '';
            $ssh->writePrompt("/usr/bin/sudo /usr/bin/yum erase -y chef");
            $ssh->waitPrompt($prompt, $buffer, 2);
            $output[] = $buffer;
    
            // remove everything under the /etc/chef directory
            $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "command=sudo rm -rf /etc/chef"));
            $buffer = '';
            $ssh->writePrompt("sudo rm -rf /etc/chef");
            $ssh->waitPrompt($prompt, $buffer, 2);
            $output[] = $buffer;
    
            // install Chef client
            $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "command=wget -O - http://repo.va.neustar.com/opscode/install.sh | sudo bash -s - -v 11.12.8-2"));
            $buffer = '';
            $ssh->writePrompt("wget -O - --progress=dot http://repo.va.neustar.com/opscode/install.sh | sudo bash -s - -v 11.12.8-2");
            $ssh->waitPrompt($prompt, $buffer, 2);
            $output[] = $buffer;
    
           
        }
      

        // get the server cert
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "command=wget server.crt"));
        $buffer = '';
        // need to use snake case for the fqdn as per https://jira.nexgen.neustar.biz/browse/HBA-325
        $serverFqdnSnakeCase = preg_replace("/\./", '_', $chefServer);
        $serverFqdnSnakeCase = preg_replace("/\//", '_', $serverFqdnSnakeCase);
        $wgetCommand = "sudo wget --user={$username} --password={$password} --timeout=10 --no-check-certificate -nv " .
                    "-O /etc/chef/trusted_certs/{$serverFqdnSnakeCase}.crt " .
                    "https://{$this->neumaticServer}/clientconfig/{$chefServer}/server.crt";
        $ssh->writePrompt($wgetCommand);
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output[] = $buffer;

        // get the validation.pem
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "command=wget validation.pem"));
        $buffer = '';
        $wgetCommand = "sudo wget --user={$username} --password={$password} --timeout=10 --no-check-certificate -nv " .
                    "-O /etc/chef/validation.pem " .
                    "https://{$this->neumaticServer}/clientconfig/{$chefServer}/validation.pem";
        $ssh->writePrompt($wgetCommand);
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output[] = $buffer;

        // get the client.rb
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "command=wget client.rb"));
        $buffer = '';
        $wgetCommand = "sudo wget --user={$username} --password={$password} --timeout=10 --no-check-certificate -nv " .
                    "-O /etc/chef/client.rb " .
                    "https://{$this->neumaticServer}/clientconfig/{$chefServer}/client.rb";

        $ssh->writePrompt($wgetCommand);
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output[] = $buffer;
        

        // boostrap
        $this->writeLog(array("logLevel" => Logger::DEBUG, "logOutput" => "bootstrapping"));
        $output[] = "Bootstrapping {$hostName}...";
        $execOutput = array();
        $cdCmd = "cd {$this->chefWsDir}/{$chefServer}";
        
        
        $knifeCmd = "knife bootstrap {$hostName} --ssh-user stsapps --ssh-password '{$this->_config['stsappsPassword']}' --sudo --use-sudo-password " .
            "--run-list 'role[{$chefRole}]' --environment {$chefEnv} " .
            "--format summary --yes -V";
            
        exec("{$cdCmd}; {$knifeCmd}", $execOutput, $returnVar);

        $output[] = "";
        // TODO: remove the knife command from first array element
         // Create log file
        $prompt = ']$ ';
        $buffer = '';
        $touchLogCommand = 'sudo touch /var/log/chef-initial-run.log';
        $ssh->writePrompt($touchLogCommand);
        $ssh->waitPrompt($prompt, $buffer, 2);
        $output[] = $buffer;
        
        // Write log to host
        $buffer = '';
        
        $ssh->writePrompt('sudo echo "test" > /var/log/chef-initial-run.log');
        $ssh->waitPrompt($prompt, $buffer, 5);
        $output[] = $buffer;
        
        $ssh->closeStream();
        
                
        $output = array_merge($output, $execOutput);
        $output = preg_replace("/\[0m/", "\n", $output);
        $output = preg_replace("/\[37m/", "", $output);
        
        exec("rm -f /opt/neumatic/watcher_log/bootstrap.log.$hostName");        
        file_put_contents("/opt/neumatic/watcher_log/bootstrap.log.$hostName", $output);
        
       
        return $this->renderView(array(
            "success" => true,
            "logLevel" => Logger::NOTICE,
            "logOutput" => "{$hostName} was bootstrapped to {$chefServer}",
            "output" => implode("\n", $output)
        ));
    }

    // *****************************************************************************************************************
    // Private methods
    // *****************************************************************************************************************

}
