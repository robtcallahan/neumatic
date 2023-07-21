<?php namespace ChefServer12API;

class ChefServer12
{

    protected $server;
    protected $key;
    protected $client;
    protected $version;
    protected $enterprise_org;

    // the number of seconds to wait while trying to connect
    protected $timeout = 10;

    /**
     * Create a new Chef instance.
     *
     * @param  string $server
     * @param  string $client
     * @param  string $key
     * @param  string $version
     * @param bool $enterprise
     * @return \ChefServer12API\ChefServer12
     */
    function __construct($server, $client, $key, $version = '0.11.x', $enterprise = false) {
        $this->server = $server;

        $this->client  = $client;
        $this->key     = $key;
        $this->version = $version;

        // get private key content
        if (file_exists($key)) {
            $this->key = file_get_contents($key);
        }

        if ($enterprise) {
            $trim = trim($server, '/');
            $explode = explode('/', $trim);
            $this->enterprise_org = end($explode);
        } else {
            $this->enterprise_org = false;
        }
    }

    /**
     * API GET request
     *
     * @param  string $endpoint
     * @return mixed
     */
    function get($endpoint) {
        return $this->api($endpoint);
    }

    /**
     * API POST request
     *
     * @param  string $endpoint
     * @param  mixed $data
     * @return mixed
     */
    function post($endpoint, $data = null) {
        if(!$this->isJson($data)){
            $data     = json_encode($data);
        }    
        return $this->api($endpoint, 'POST', $data);
    }

    /**
     * API POST with query params request
     *
     * @param  string $endpoint
     * @param null $query
     * @param  mixed $data
     * @return mixed
     */
    function postWithQueryParams($endpoint, $query = null, $data = null) {
        if(!$this->isJson($data)){
            $data     = json_encode($data);
        }    
        $endpoint = $endpoint . $query;
        return $this->api($endpoint, 'POST', $data);
    }

    /**
     * API PUT request
     *
     * @param  string $endpoint
     * @param $id
     * @param  mixed $data
     * @return mixed
     */
    function put($endpoint, $id, $data = null) {
        if(!$this->isJson($data)){
            $data     = json_encode($data);
        }
        $endpoint = $endpoint . "/" . $id;
        return $this->api($endpoint, 'PUT', $data);
    }

    /**
     * API DELETE request
     *
     * @param  string $endpoint
     * @param $id
     * @return mixed
     */
    function delete($endpoint, $id) {
        $endpoint = $endpoint . "/" . $id;
        return $this->api($endpoint, 'DELETE');
    }

    /**
     * API calls.
     *
     * @param  string $endpoint
     * @param  string $method
     * @param  mixed $data
     * @throws \Exception
     * @return mixed
     */
    function api($endpoint, $method = 'GET', $data = FALSE) {


        // basic header
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Chef-Version: ' . $this->version

        );

        //$header['Hashed Path'] = base64_encode(sha1(""));

        // print_r($endpoint);
        // method always uppercase
        $method = strtoupper($method);

        // check if endpoint is full url

        $parts = parse_url($endpoint);
        if (isset($parts['host'])) {
            // split server and endpoint
            $endpoint = $parts['path'];
            $url      = $this->server . $endpoint;
        } else {
            // prepend own server to endpoint
            // RCallahan 01/07/2025 - changed $endpoint for $parts['path'] in ltrim() below
            $endpoint = '/' . ltrim($parts['path'], '/');
            $url      = $this->server . $endpoint . (array_key_exists('query', $parts) ? '?' . $parts['query'] : '');
        }

        // insure that we have 'https://' at the beginning of the url, otherwise you'll get an 'Invalid JSON' error
        if (!preg_match("/https:\/\//", $url)) {
            $url = 'https://' . $url;
        }

        // append data to url if GET request
        if ($method == 'GET' && is_array($data)) {
            $url .= '?' . http_build_query($data);
            $data = FALSE;
        }

        // json encode data
        
        if (!$this->isJson($data)) {
            $data = json_encode($data, JSON_FORCE_OBJECT);
        }

        // sign the request
        $this->sign($endpoint, $method, $data, $header);

        // initiate curl
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // most people are using self-signed certs for chef, so its easiest to just
        // disable ssl verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // add data to post en put requests
        if ($method == 'POST' || $method == 'PUT') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        // set the request header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        // want to be able to see the header that gets sent up
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        // execute
        $raw_response = curl_exec($ch);

        $status       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        #$curlInfo     = curl_getinfo($ch);
        curl_close($ch);

        // we got a response
        if ($raw_response !== FALSE) {
            // decode json
            $response = json_decode($raw_response);

            // throw exception if there was an error
            if ($status != 200 && isset($response->error) && !stristr($response->error[0], "not found") && !stristr($response->error[0], "Cannot load client")) {
                $message = reset($response->error);
                return $message;
                //throw new \Exception($message, $status);
            } elseif ($response === null) {
                return $raw_response;
                //throw new \Exception($raw_response, $status);
            }
            return $response;
        }
        return $raw_response;
    }

    /**
     * Encrypt a value with a key
     *
     * @param  mixed $data
     * @param  string $key
     * @return object
     */
    function encrypt($data, $key) {
        // encryption method
        $method = 'aes-256-cbc';

        // generate initialization vector
        $size = openssl_cipher_iv_length($method);
        $iv   = mcrypt_create_iv($size, MCRYPT_RAND);

        // check if file name was given
        if (file_exists($key)) {
            $key = file_get_contents($key);
        }

        // create wrapper object
        $wrapper               = new \stdClass;
        $wrapper->json_wrapper = $data;
        $json                  = json_encode($wrapper);

        $object                 = new \stdClass;
        $object->iv             = base64_encode($iv);
        $object->cipher         = 'aes-256-cbc';
        $object->version        = 1;
        $object->encrypted_data = openssl_encrypt($json, $method, pack('H*', hash('sha256', $key)), false, $iv);

        return $object;
    }

    /**
     * Decrypt a value with a key
     *
     * @param  object $data
     * @param  string $key
     * @return mixed
     */
    function decrypt($data, $key) {
        // can only decrypt a valid object
        if (!is_object($data) || !isset($data->encrypted_data)) {
            return false;
        }

        // check if file name was given
        if (file_exists($key)) {
            $key = file_get_contents($key);
        }

        // decrypt data
        $json = openssl_decrypt($data->encrypted_data, $data->cipher, pack('H*', hash('sha256', $key)), false, base64_decode($data->iv));

        // return content
        return json_decode($json)->json_wrapper;
    }

    /**
     * Sign API calls with private key.
     *
     * @param  string $endpoint
     * @param  string $method
     * @param  string $data //json format
     * @param $header
     * @internal param array $headers
     * @return void
     */
    private function sign($endpoint, $method, $data, &$header) {
        // generate timestamp
        $timestamp = gmdate("Y-m-d\TH:i:s\Z");

        // add X-Ops headers
        $header[] = 'X-Ops-Sign: algorithm=sha1;version=1.0';
        $header[] = 'X-Ops-UserId: ' . $this->client;
        $header[] = 'X-Ops-Timestamp: ' . $timestamp;
        $header[] = 'X-Ops-Content-Hash: ' . base64_encode(sha1($data, true));

        //rewrite the endpoint for enterprise organizations
        if ($this->enterprise_org !== false) {
            $endpoint = "/organizations/" . $this->enterprise_org . $endpoint;
        }

        // create signature
        $signature =
            "Method:" . $method . "\n" .
            "Hashed Path:" . base64_encode(sha1($endpoint, true)) . "\n" .
            "X-Ops-Content-Hash:" . base64_encode(sha1($data, true)) . "\n" .
            "X-Ops-Timestamp:" . $timestamp . "\n" .
            "X-Ops-UserId:" . $this->client;

        // encrypt signature with private key
        openssl_private_encrypt($signature, $crypted, $this->key);
        $encoded = base64_encode($crypted);

        // add signature to header
        $shrapnel = explode("\n", chunk_split($encoded, 60));
        for ($i = 0; $i < count($shrapnel); $i++) {
            if (strlen(trim($shrapnel[$i])) > 0) {
                $header[] = "X-Ops-Authorization-" . ($i + 1) . ": " . trim($shrapnel[$i]);
            }
        }
    }

    private function object_to_array($obj) {
        $arr    = array();
        $arrObj = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($arrObj as $key => $val) {
            $val       = (is_array($val) || is_object($val)) ? $this->object_to_array($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }
    private function isJson($string) {
        if(is_array($string)){
            return false;
        }
        if(is_object($string)){
            return false;
        }    
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}