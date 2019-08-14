<?php

include 'Customer.php';

function filterCallback($var) {
	if(is_object($var)) {
		$tmp = get_object_vars($var);
		return count($tmp) > 0;
	}
	return $var === 0 || !empty($var);
}

/**
* Delete all null values in an object
*
* @param $object
*/
function splitNullValuesInObject($object) {
	return (object) array_filter((array) $object, 'filterCallback');
}

class Geoloc {
	public $lat = NULL;
	public $lng = NULL;
}

class Referer {
	public $type = NULL;
	public $name = NULL;
	public $campaign = NULL;
}

class Context {
	public $device = NULL;
	public $geoloc = NULL;
	public $refSalesAssociate = NULL;
	public $refShop = NULL;
	public $referer = NULL;
	public $ipAddress = NULL;
	public $checkIn = NULL; // Boolean
	
	/**
	* Check some fields and gives them always the same value if they are null or empty.
	* The goal is to avoid a field is empty at a moment and null at another moment.
	*
	* This method does NOT check mandatory fields.
	*/
	public function checkFields() {
		// referer
		if($this->referer != null) {
			$this->referer = splitNullValuesInObject($this->referer);
		}
		
		// geoloc
		if($this->geoloc != null) {
			$this->geoloc = splitNullValuesInObject($this->geoloc);
		}
	}
}

class LoyaltyCard {
	public $refBrand = NULL;
	public $number = NULL;
	public $rawData = NULL;
	
	/**
	* Check some fields and gives them always the same value if they are null or empty.
	* The goal is to avoid a field is empty at a moment and null at another moment.
	*
	* This method does NOT check mandatory fields.
	*/
	public function checkFields() {
		// rawData
		if($this->rawData != null) {
			$this->rawData = splitNullValuesInObject($this->rawData);
		}
	}
}

class Attribute {
	public $value = NULL;
}

class Price {
	public $base = NULL;
	public $sale = NULL;
	public $sell = NULL;
	public $vat = NULL;
	public $vatRate = NULL;
	public $currency = NULL;
}

class BagItem {

	public $ean = NULL;
	public $skuRef = NULL;
	public $orderedQty = NULL;
	public $qtyToBill = NULL;
	public $qtyToShip = NULL;
	public $qtyUnit = NULL;
	public $label = NULL;
	public $language = NULL;
	public $type = NULL;
	public $family = NULL;
	public $imgUrl = NULL;
	public $comment = NULL;
	public $attributes = array();
	public $price = NULL;
	public $rawData = NULL;
	public $innerItems = array();

	public function __construct() {
		$this->rawData = new RawData();
	}
	
	/**
	* Check some fields and gives them always the same value if they are null or empty.
	* The goal is to avoid a field is empty at a moment and null at another moment.
	*
	* This method does NOT check mandatory fields.
	*/
	public function checkFields() {
		// price
		if($this->price != null) {
			$this->price = splitNullValuesInObject($this->price);
		}
		
		// rawData
		if($this->rawData != null) {
			$this->rawData = splitNullValuesInObject($this->rawData);
		}
	}
}

class RawData {
	public $buyRequest = NULL;
	public $parentSku = NULL;
}

class Totals {
	public $subtotal = NULL;
	public $discount = NULL;
	public $shipping = NULL;
	public $total = NULL;
	public $vat = NULL;
	public $vatRate = NULL;
	public $currency = NULL;
}
