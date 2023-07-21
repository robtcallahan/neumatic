<?php
namespace ChefServerApi;
/**
 * Chef Server Api Class
 * @uses openssl
 */
class ChefServer{
	
	private $con;
	private $header;
	private $signVersion = 'version=1.0';
	private $chefVersion = '11.0.8';
	private $userAgent = 'php chef client 0.0.1';
	/**
	 * Constructor Class.
	 * @param string $host
	 * @param int $port
	 * @param string $userid // Crowbar Client Id
	 * @param string $keyfile // Crowbar Access Key File
	 */
	public function __construct($host, $port, $userid, $keyfile = '/etc/chef/client.key'){
		$this->con = curl_init();
		$this->host = $host;
		$this->port = $port;
		$this->url = 'https://' . $host;
		$this->userid = $userid;
		$this->key = $this->getKey($keyfile);
        curl_setopt($this->con, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->con, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($this->con, CURLOPT_HEADER, 0);
		curl_setopt($this->con, CURLOPT_VERBOSE, 0);
		curl_setopt($this->con, CURLOPT_RETURNTRANSFER, 1);
	}
	
	private function getKey($file){
		return openssl_pkey_get_private("file://" . $file);
	}
	
	private function signCanonicalHeaders($method, $path, $content){
        #error_log("chef host=" . $this->host);
        #error_log("chef method=" . $method);
        #error_log("chef path=" . $path);
		$content_hash = $this->getContentHash($content);
		$path_hash = base64_encode(sha1($path, true));
		$time = gmdate("Y-m-d\TH:i:s\Z");
		$content = "Method:${method}\nHashed Path:${path_hash}\nX-Ops-Content-Hash:${content_hash}\nX-Ops-Timestamp:${time}\nX-Ops-UserId:" . $this->userid;
		openssl_private_encrypt($content, $crypted, $this->key);
		return $crypted;
	}
	
	private function getAuthorizationHeaders($signedContent){
		$sigs = explode("\n", chunk_split(base64_encode($signedContent), 60));
		for($i = 0; $i < count($sigs); $i++){
			$h[] = "X-Ops-Authorization-" . ($i + 1) . ": " . trim($sigs[$i]);
		}
		return $h;
	}
	
	private function getContentHash($content){
		return base64_encode(sha1($content, true));
	}
	
	private function getTime(){
		return $this->_time;
	}
	private function setTime(){
		$this->_time = gmdate("Y-m-d\TH:i:s\Z");
	}
	
	private function getUrl(){
		return $this->url;
	}
	
	private function getHeaders($method, $uri, $content = ''){
		$aHeaders = $this->getAuthorizationHeaders($this->signCanonicalHeaders($method, $uri, $content));
		$h[] = "X-Ops-UserId: " . $this->userid;
		$h[] = "X-Ops-Sign: " . $this->signVersion;
		$h[] = "X-Ops-Content-Hash: " . $this->getContentHash($content);
		$h[] = "X-Ops-Timestamp: " . $this->getTime();
		$h[] = "Host: ".$this->host.":".$this->port;
		$h[] = "Accept: application/json";
		$h[] = "X-Chef-Version: " . $this->chefVersion;
		$h[] = "User-Agent: " . $this->userAgent;
		$h[] = "Content-Type: application/json";
		//$h[] = "Connection: close";
		return array_merge($aHeaders, $h);
	}
	private function setDst($uri){
		curl_setopt($this->con, CURLOPT_URL, $this->url . $uri);
	}
	
	private function prepare($uri, $method = 'GET', $encodedData){
		$this->setDst($uri);
		$this->setTime();
        curl_setopt($this->con, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->con, CURLOPT_SSL_VERIFYHOST, false);
		if($method == 'POST'){
			curl_setopt($this->con, CURLOPT_POST, true);
			curl_setopt($this->con, CURLOPT_POSTFIELDS, $encodedData);
			curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, 'POST');
		}
		else if($method == 'PUT'){
			curl_setopt($this->con, CURLOPT_POST, true);
			curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($this->con, CURLOPT_POSTFIELDS, $encodedData);
		}
		else if($method == 'DELETE'){
			curl_setopt($this->con, CURLOPT_POST, false);
			curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, 'DELETE');
		}
		else{
			curl_setopt($this->con, CURLOPT_CUSTOMREQUEST, 'GET');
			curl_setopt($this->con, CURLOPT_POST, false);
			curl_setopt($this->con, CURLOPT_HTTPGET, true);
		}
	
	}
	
	private function execute($headers){
		curl_setopt($this->con, CURLOPT_HTTPHEADER, $headers);
        #curl_setopt($this->con, CURLOPT_VERBOSE, true);

		return curl_exec($this->con);
	}

    public function getHttpCode() {
        return curl_getinfo($this->con, CURLINFO_HTTP_CODE);
    }
    /**
     *
     * GET request method
     * @param string $uri
     * @param string $params
     * @return object
     */
	public function get($uri, $params = ''){
		if(!empty($params)){
		    if(is_array($params)){
		        $paramsOut = "";
		        foreach($params AS $k=>$v){
		            $paramsOut .= $k."=".urlencode($v)."&"; 
		        }
		    }else{
		      $paramsOut = urlencode($params);
                
		    }
			$this->prepare($uri . "?" . $paramsOut, "GET", '');
		}
		else{
			$this->prepare($uri, 'GET', '');
		}
        
		$headers = $this->getHeaders('GET', $uri);
        $json = $this->execute($headers);
        
		return (object) json_decode($json);
	}
	
	public function post($uri, $data, $json = false){
        if($json == true){
            $encodedData = $data;
        }else{
		if(!$this->isJson($data)){
			$encodedData = json_encode($data);
		}else{
			$encodedData = $data;
		}
        }
		$this->prepare($uri, 'POST', $encodedData);
		$headers = $this->getHeaders('POST', $uri, $encodedData);
		return (object) json_decode($this->execute($headers));
	}

    /**
     * This method allows us to add query params during a POST method
     * Reference: http://docs.opscode.com/api_chef_server.html#id42
     * Example query
     *   $results = $this->chef->postWithQueryParams(
     *    '/search/node',
     *    '?q=name: stopcdvvt3.va.neustar.com',
     *    '{"fqdn": ["fqdn"],
     *    "hostname": ["hostname"],
     *    "chefVersion": ["chef_packages", "chef", "version"],
     *    "ohaiTime": ["ohai_time"],
     *    "memory": ["memory","total"],
     *    "manufacturer": ["dmi", "system", "manufacturer"],
     *    "model": ["dmi", "system", "product_name"],
     *    "platform": ["platform"],
     *    "platformVersion": ["platform_version"]
     *   }', true);
     *
     * Note the query: ?q=name: stopcdvvt3.va.neustar.com
     * The POST JSON specify what config items to return
     * The true parameter indicates that we're passing JSON and not to convert the data
     *
     * @param string $uri
     * @param string $query
     * @param string $data
     * @param bool $json
     * @return object
     */
    public function postWithQueryParams($uri, $query, $data, $json = false) {
        if ($json == true) {
            $encodedData = $data;
        } else {
            if (!$this->isJson($data)) {
                $encodedData = json_encode($data);
            } else {
                $encodedData = $data;
            }
        }
        $this->prepare($uri, 'POST', $encodedData);
        $this->setDst($uri . $query);
        $headers = $this->getHeaders('POST', $uri, $encodedData);
        return (object)json_decode($this->execute($headers));
    }

    public function put($uri, $id, $data){
		//  	echo "put fonksiyonu : ".$uri."- id: $id - <br>";
		if(!$this->isJson($data)){
		    $encodedData = json_encode($data);
		}else{
		    $encodedData = $data;
		}
		$path = $uri . '/' . $id;

		$this->prepare($path, 'PUT', $encodedData);
		$headers = $this->getHeaders('PUT', $path, $encodedData);
		return (object) json_decode($this->execute($headers));
	}
	
	public function delete($uri, $id, $optional = null){
		$path = $uri . '/' . $id;
        if($optional != null){
            $path .= '/'.$optional;
        }
        
		$this->prepare($path, 'DELETE', "");
      
		$headers = $this->getHeaders('DELETE', $path, "");
		return (object) json_decode($this->execute($headers));
	}

	private function isJson($string) {
 	    if(!is_string($string)){
		return false;	
	    }
	    json_decode($string);
 	    return (json_last_error() == JSON_ERROR_NONE);
	}
}
//Example
//$api = new ChefServer("192.168.124.10",4000,'webserver');
//echo "<pre>";
//var_dump( $api->get('/clients') );
//var_dump( $api->get('/data/test') );
//var_dump( $api->post('/data', array('name'=>'test')) );
//var_dump( $api->post('/data/test', array('id'=>'bla','hedele'=>'test')) );
//var_dump( $api->put('/data/test', 'bla', array('id'=>'bla','hedele'=>'test', 'ops'=>'hede')) );
//var_dump( $api->delete('/data/test', 'bla') );
