<?php

class Customer {
	protected static $ICS_CustomerChecksum = 'ICS_CustomerChecksum';
	
	public $appCustomerId = NULL;
	public $email = NULL;
	public $firstName = NULL;
	public $lastName = NULL;
	public $address = NULL;
	public $zipCode = NULL;
	public $city = NULL;
	public $country = NULL;
	public $loyaltyCards = array();
	public $rawData = NULL;
	public $twitterId = NULL;

	/**
	 * Check some fields and gives them always the same value if they are null or empty.
	 * The goal is to avoid a field is empty at a moment and null at another moment.
	 *
	 * This method does NOT check mandatory fields.
	 */
	public function checkFields() {
		// loyalty cards
		if(!is_null($this->loyaltyCards)) {
			foreach($this->loyaltyCards as $key=>$loyaltyCard) {
				$loyaltyCard->checkFields();
				$this->loyaltyCards[$key] = splitNullValuesInObject($loyaltyCard);
			}
		}

		// rawData
		if($this->rawData != null) {
			$this->rawData = splitNullValuesInObject($this->rawData);
		}
	}

	/**
	 * Create an ICS customer from a Magento Customer
	 *
	 * @param Mage_Customer_Model_Customer $customer The customer from Magento
	 * @return Customer An ICS customer built from the magento's one
	 */
	public static function createIcsCustomer($customer){
		$addresses = $customer->getAddressesCollection()->getItems();

		$res = new Customer();
		$res->appCustomerId = $customer->getId();
		$res->firstName = $customer->getFirstname();
		$res->lastName = $customer->getLastname();
		$res->email = $customer->getEmail();
		$res->twitterId = Mage::helper('connectedstore/ICSTwitterHelper')->getTwitterId($customer);

		// Array indexes are the addresses' ids
		$keys = array_keys($addresses);

		$billingAddress = NULL;
		if($addresses != NULL && count($addresses) == 1){
			$billingAddress = $addresses[$keys[0]];
		}else if($addresses != NULL && count($addresses) > 0){
			for($i=0; $i<count($addresses); $i++) {
				$address = $addresses[$keys[$i]];
				if($address->getId() != NULL && $customer->getDefaultBilling() === $address->getId()) {
					$billingAddress = $addresses[$keys[$i]];
					break;
				}
			}
		}

		if($billingAddress != NULL) {
			$res->city = $billingAddress->getCity();
			$res->country = $billingAddress->getCountryId();
			$res->zipCode = $billingAddress->getPostcode();
			$res->address =  $billingAddress->getData("street");
		}

		return $res;
	}

	/**
	 * Generate a checksum for this
	 */
	public function getCheckSum() {
		$data = array();

		$data['appCustomerId'] = $this->appCustomerId;
		$data['email'] = $this->email;
		$data['firstName'] = $this->firstName;
		$data['lastName'] = $this->lastName;
		$data['address'] = $this->address;
		$data['zipCode'] = $this->zipCode;
		$data['city'] = $this->city;
		$data['country'] = $this->country;
		// 	TODO : gérer les cartes de fid
		//$data['loyaltyCards'] = $this->bagType;
		$data['rawData'] = $this->rawData;
		$data['twitterId'] = $this->twitterId;

		$checksum = hexdec(md5(json_encode($data)));
		return $checksum;
	}
	
	
	/**
	* Set the checksum of the customer given in parameter in session.
	*
	* @param String $checksum The cart checksum
	*/
	public static function setCustomerChecksumInSession($checksum){
		Mage::getSingleton('core/session')->setData(self::$ICS_CustomerChecksum, $checksum);
	}
	
	/**
	* Get the current checksum of the customer in session.
	*
	* @return String The cart checksum
	*/
	public static function getCustomerChecksumInSession(){
		return Mage::getSingleton('core/session')->getData(self::$ICS_CustomerChecksum);
	}
	
	/**
	 * Reset the checksum of the customer in session
	 */
	public static function resetCustomerChecksumInSession() {
		self::setCustomerChecksumInSession(null);
	}
}