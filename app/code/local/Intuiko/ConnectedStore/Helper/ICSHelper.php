<?php
/**
 * Magento
 */

/**
 *
 * Helper class to uses to request Api ICS
 * Example:
 * http://support.qualityunit.com/061754-How-to-make-REST-calls-in-PHP
 *
 */
class Intuiko_ConnectedStore_Helper_ICSHelper extends Mage_Core_Helper_Abstract {

	private static $ICS_Error = 'ICS_Error';
	private static $CURLE_OPERATION_TIMEDOUT = '28';
	
	private static $API_VERSION = "1.3" ;

	/** ICS connector status */
	private $status = NULL;

	/** ICS api url */
	private $uri = NULL;

	/** Application api key */
	private $apikey = NULL;

	/** Tenant id */
	private $tenantId = NULL;

	/** Brand Id */
	private $brandId = NULL;

	/** Bag Merge Method */
	private $mergeMethod = NULL;
	
	/** Time of timeout */
	private $timeout = NULL;

	/**
	 * Init param to call api ICS
	 */
	public function Intuiko_ConnectedStore_Helper_ICSHelper(){
		$this->status = Mage::getStoreConfig('connectedstore_section/connectedstore_group/status_field');
		$this->uri = Mage::getStoreConfig('connectedstore_section/connectedstore_group/urlservice_field');
		$this->apikey = Mage::getStoreConfig('connectedstore_section/connectedstore_group/apikey_field');
		$this->tenantId = Mage::getStoreConfig('connectedstore_section/connectedstore_group/tenantid_field');
		$this->brandId = Mage::getStoreConfig('connectedstore_section/connectedstore_group/brandid_field');
		$this->mergeMethod = Mage::getStoreConfig('connectedstore_section/connectedstore_group/mergemethod_field');
		$this->timeout = Mage::getStoreConfig('connectedstore_section/connectedstore_group/timeout_field');

		if($this->status && (empty($this->uri) || empty($this->apikey) || empty($this->tenantId) || empty($this->brandId))) {
			ICSLogger::error('A field is missing in the configuration, ICS connector will be deactivated.');
			$this->status = false;
		}
	}
	
	/**
	* Set the flag value of mode timeout in the session
	*
	* @param Boolean $value The boolean to set in the session
	*/
	public static function setIcsFlagError($value){
		$session = Mage::getSingleton('core/session');
		$session->setData(self::$ICS_Error, $value);
	}
	
	/**
	 * Get the flag value of mode timeout from the session
	 *
	 * @return Boolean The flag value
	 */
	public static function getIcsFlagError(){
		$session = Mage::getSingleton('core/session');
		return $session->getData(self::$ICS_Error);
	}
	
	
	/**
	 * Generate authentication key as followed:
	 *   'ICS ' . sha256($salt . $apiKey)
	 * where $salt is a timestamp.
	 *
	 * @param Integer $time The timestamp used as salt
	 * @return String The generated authentication key
	 */
	private function getKey($time){
		return "ICS " . hash('sha256', $time . $this->apikey);
	}

	/**
	 * Generate header to build a http request on Api ICS
	 *
	 * @return Array The array of headers used for ICS api request
	 */

	private function getHeader(){
		$date = new DateTime();
		$key = $this->getKey($date->getTimestamp());

		if($key == NULL){
			return NULL;
		}
		return array(
				'x-timestamp: '. $date->getTimestamp(),
				'Authorization: '. $key,
				'Content-type: application/json; version=' . self::$API_VERSION . '; charset=utf-8',
				'Accept: application/json; version=' . self::$API_VERSION . '; charset=utf-8'
		) ;
	}

	/**
	 * Check if the ICS module is in timeout mode.
	 *
	 * @return bool True if the module is enabled, false otherwise
	 */
	public function isICSModuleEnabled() {
		return $this->status && !$this->getIcsFlagError();
	}
	
	/**
	* Check if the ICS module is enabled
	*
	* @return bool True if the module is enabled, false otherwise
	*/
	public function isIcsModuleStatusEnabled() {
		return $this->status;
	}

	/**
	 * This function does a ping to initialize the communication
	 *
	 * @return String A pong class response which contains these attributes : tenantName, applicationProfile, active, apiVersion
	 */
	public function ping(){
		$header = $this->getHeader();

		if($header == NULL){
			return NULL;
		}

		$opts = array('http' => array('method'=>'GET', 'header'=> $header));
		$context = stream_context_create($opts);

		$time = microtime(true);
		
		$pong = json_decode(file_get_contents( $this->uri . 'tenants/' . $this->tenantId . '/brands/' . $this->brandId . '/ping', false, $context));

		$timeElapsed = microtime(true) - $time;
		
		if($pong == false){
			return NULL;
		}
		
		$pong->timeElapsed = round($timeElapsed * 1000);

		return $pong;
	}

	/**
	 * Call the save method of ICS api to save the given bag.
	 *
	 * @param Bag $bag The bag to be saved in ICS.
	 * @return Array|False The api response as an array, or false if an error occurred
	 */
	public function save($bag) {
		if(empty($bag)) {
			return false;
		}

		$path = 'tenants/' . $this->tenantId . '/brands/' . $this->brandId . '/bags';
		try {
			$response = $this->post($bag, $path);
		} catch(Intuiko_ConnectedStore_Exception $e) {
			return false;
		}

		return json_decode($response, true);
	}
	
	/**
	 * Call the save method of ICS api to save the given customer.
	 *
	 * @param Customer $customer The customer to be saved in ICS.
	 * @return True|False True if everything is OK, or false if an error occurred
	 */
	public function saveCustomer($customer) {
		if(empty($customer)) {
			return false;
		}
		
		$path = 'tenants/' . $this->tenantId . '/brands/' . $this->brandId . '/customers';
		try {
			$this->post($customer, $path);
		} catch(Intuiko_ConnectedStore_Exception $e) {
			return false;
		}
		
		return true;
	}

	/**
	 * Call the search method of ICS api to get ids of the bags matching the given bag type and belonging to the
	 * customer defined by the given appCustomerId or email.
	 *
	 * @param String $type The bag type
	 * @param Mixed $appCustomerId The customer business identifier
	 * @param String $email The customer email
	 * @param String $twitterId The customer twitter id
	 * @return Array|False|Int The api response as an array, or an error code/false if an error occurred
	 */
	public function searchBagsIds($type, $appCustomerId, $email, $twitterId) {
		if(empty($type) || (empty($appCustomerId) && empty($email) && empty($twitterId))) {
			return false;
		}

		$searchBagMessage = new SearchBagMessage($type, $appCustomerId, $email, $twitterId);
		$path = 'tenants/' . $this->tenantId . '/brands/' . $this->brandId . '/bags/_search';
		try {
			$response = $this->put($searchBagMessage, $path);
		} catch(Intuiko_ConnectedStore_Exception $e) {
			return !empty($e->codeError) ? $e->codeError : false;
		}

		return json_decode($response);
	}

	/**
	 * Call the get method of ICS api to get the bag matching the given id.
	 * If a timestamp is provided, the bag will be retrieved if and only if it is more recent than the given timestamp.
	 *
	 * @param String $bagId The id of the bag to retrieve from ICS
	 * @param Int $timestamp The timestamp (default value: NULL)
	 * @return Array|False|Int The api response as an array or a int (see get($path) method), or false if an error occurred.
	 */
	public function getBagById($bagId, $timestamp = NULL) {
		if(empty($bagId)) {
			return false;
		}

		$path = 'tenants/' . $this->tenantId . '/brands/' . $this->brandId . '/bags/' . $bagId;
		if(!empty($timestamp)) {
			$path = $path . '?timestamp=' . $timestamp;
		}

		try {
			$response = $this->get($path);
		} catch(Intuiko_ConnectedStore_Exception $e) {
			return false;
		}

		if(!empty($response) && is_numeric($response)) {
			return $response;
		}

		return json_decode($response, true);
	}

	/**
	 * Call the merge method of ICS api to merge two bags.
	 * All the modifications will be done on the bag matching the masterBagId.
	 * See the api documentation for more details on the different possible merge.
	 *
	 * @param String $masterBagId The id of the bag that will be considered as the master
	 * @param String $slaveBagId The id of the bag that will be considered as the slave
	 * @return Array|False The api response as an array, or false if an error occurred
	 */
	public function merge($masterBagId, $slaveBagId) {
		if(empty($this->mergeMethod) || empty($masterBagId) || empty($slaveBagId)) {
			return false;
		}

		$mergeMessage = new MergeMessage($masterBagId, $slaveBagId, $this->mergeMethod);
		$path = 'tenants/' . $this->tenantId . '/brands/' . $this->brandId . '/bags/_merge';
		try {
			$response = $this->post($mergeMessage, $path);
		} catch (Intuiko_ConnectedStore_Exception $e) {
			return false;
		}

		return json_decode($response, true);
	}

	/**
	 * Call the delete method of ICS api to delete the bag matching the given id.
	 *
	 * @param String $bagId The id of the bag to delete
	 */
	public function delete($bagId) {
		if(empty($bagId)) {
			return;
		}

		$deleteMessage = new DeleteMessage($bagId);
		$path = 'tenants/' . $this->tenantId . '/brands/' . $this->brandId . '/bags/_delete';
		try {
			$this->put($deleteMessage, $path);
		} catch(Intuiko_ConnectedStore_Exception $e) {}
	}

	
	/**
	 * Run Exception if Detect that curl is in timeOut
	 * 
	 * @param curl $ch
	 * @param time $time
	 * @throws Intuiko_ConnectedStore_Exception
	 */
	public function checkTimeOut($ch, $time){
		if(curl_errno($ch) == self::$CURLE_OPERATION_TIMEDOUT){
			curl_close($ch);
			$this->setIcsFlagError(true);
			throw new Intuiko_ConnectedStore_Exception('request ended in timeout error after ' . $time . 's');
		}
	}
	
	/**
	 * return true when status code is in error, otherwise false. 
	 * 
	 * @param unknown_type $statusCode
	 */
	public function checkStatusError($statusCode){
		if ($statusCode == 0 || $statusCode >= 300) {
			if ($statusCode == 0 || $statusCode >= 500){
				$this->setIcsFlagError(true);
			}
			return true;
		}
		return false;
	}
	
	/**
	 * Convert an object to JsonObject without its null attributes
	 *
	 * @param $entity The object to convert
	 */
	private function toJsonObjectWithoutNullAttributes($entity) {
		return json_encode(array_filter((array) $entity, 'filterCallback'));
	}
		
	/**
	 * Sent POST request with the provided path and entity
	 *
	 * @param Mixed $entity The entity to send in the request body. It will be encoded in JSON.
	 * @param String $path The path of the api method. It will be concatenated to the api url.
	 * @throws Intuiko_ConnectedStore_Exception If the http request ends with an error
	 * @return String The api response
	 */
	private function post($entity, $path){
		$header = $this->getHeader();

		if($header == NULL){
			throw new Intuiko_ConnectedStore_Exception('Header cannot be null.');
		}

		if($path == NULL){
			throw new Intuiko_ConnectedStore_Exception('Path cannot be null.');
		}

		$data = $this->toJsonObjectWithoutNullAttributes($entity);
		$url =  $this->uri . $path;
		
		ICSLogger::debug('Sent POST request with url=[' . $url . '] and data=[' . $data . ']');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);

		$result = curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
		
		//Controle erreur
		$this->checkTimeOut($ch, $time);
		curl_close($ch);
		
		if($this->checkStatusError($statusCode)){
			$error = json_decode($result, true);
			throw new Intuiko_ConnectedStore_Exception(
						'POST request ended with error=['. $error['httpCode'] .' - '. $error['message'] .'] - url=['. $url .'] and data=['. $data .']' . $time . 's');
		}
		
		ICSLogger::debug('POST request ended with statusCode=[' . $statusCode . '] and content=[' . $result . '] in ' . $time . 's');
		return $result;
	}
	
	/**
	 * Sent PUT request with the provided path and entity
	 *
	 * @param Mixed $entity The entity to send in the request body. It will be encoded in JSON.
	 * @param String $path The path of the api method. It will be concatenated to the api url.
	 * @throws Intuiko_ConnectedStore_Exception
	 * @return String The response content or null if an error occurred.
	 */
	public function put($entity, $path){
		$header = $this->getHeader();

		if($header == NULL){
			throw new Intuiko_ConnectedStore_Exception('Header cannot be null.');
		}

		if($path == NULL){
			throw new Intuiko_ConnectedStore_Exception('Path cannot be null.');
		}

		$data = $this->toJsonObjectWithoutNullAttributes($entity);
		$url =  $this->uri . $path;
		ICSLogger::debug('Sent PUT request with url=[' . $url . '] and data=[' . $data . ']');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);

		$result = curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

		
		//Controle erreur
		$this->checkTimeOut($ch, $time);
		curl_close($ch);
		
		if($this->checkStatusError($statusCode)){
			$error = json_decode($result, true);
			if($statusCode === 0 || $statusCode === 500) {
				$error['errorCode'] = -500;
			}
			throw new Intuiko_ConnectedStore_Exception(
				'PUT request ended with error=['. $error['httpCode'] .' - '. $error['message'] .'] - url=['. $url .'] and data=[' . $data . ']' . $time . 's', $error['errorCode']);
		}
		
		ICSLogger::debug('PUT request ended with statusCode=[' . $statusCode . '] and content=[' . $result . '] in ' . $time . 's');

		return $result;
	}

	/**
	 * Sent GET request with the provided path
	 *
	 * @param String $path The path of the api method. It will be concatenated to the api url.
	 * @throws Intuiko_ConnectedStore_Exception
	 * @return String The response content or null if an error occurred.
	 */
	public function get($path){

		$header = $this->getHeader();

		if($header == NULL){
			throw new Intuiko_ConnectedStore_Exception('Header cannot be null.');
		}

		if($path == NULL){
			throw new Intuiko_ConnectedStore_Exception('Path cannot be null.');
		}

		$url =  $this->uri . $path;
		ICSLogger::debug('Sent GET request with url=[' . $url . ']');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);

		$result = curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
		
		
		//Controle erreur
		$this->checkTimeOut($ch, $time);
		curl_close($ch);
		
		if (!$statusCode === 404 && $this->checkStatusError($statusCode)) {
			$error = json_decode($result, true);
			throw new Intuiko_ConnectedStore_Exception(
				'GET request ended with error=['. $error['httpCode'] .' - '. $error['message'] .'] - url=['. $url .']' . $time . 's');
		}
		
		ICSLogger::debug('GET request ended with statusCode=[' . $statusCode . '] and content=[' . $result . '] in ' . $time . 's');
		
		if($statusCode === 404 || $statusCode === 204) {
			return $statusCode;
		}

		return $result;
	}
}
