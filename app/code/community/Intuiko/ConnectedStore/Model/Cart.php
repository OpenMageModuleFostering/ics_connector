<?php

class Cart extends Bag {

	protected static $ICS_CartId = 'ICS_CartId';
	protected static $ICS_CartChecksum = 'ICS_CartChecksum';
	protected static $ICS_CartLastUpdate = 'ICS_CartLastUpdate';
	/** Name of the ICS type for cart */
	const ICS_TYPE = 'CART';


	/**
	 * Default constructor.
	 */
	public function __construct() {
		$this->checkFields();
	}

	/**
	 * A static function which instanciates a Cart and feeds it with quote information.
	 *
	 * @param Mage_Sales_Model_Quote $quote The cart's quote
	 * @return Cart A new cart built from the quote
	 */
	public static function createCart($quote) {
		$cart = new Cart();
		$cart->bagType = self::ICS_TYPE;
		$cart->constructCartOrOrderFromQuote($quote);
		$cart->setTotalsForCart($quote);
		$cart->checkFields();
		return $cart;
	}

	/**
	 * Create a Cart from the given json object.
	 *
	 * @param Object $jsonObject The json stream that represents a cart
	 * @return Cart A cart built from the json stream
	 */
	public static function createCartFromJsonObject($jsonObject) {
		$cart = new Cart();

		if(empty($jsonObject)) {
			return $cart;
		}

		$cart->createBagFromJsonObject($jsonObject);
		$cart->checkFields();
		return $cart;
	}

	/**
	 * Set the cart's checksum given in parameter in session.
	 *
	 * @param String $checksum The cart checksum
	 */
	public static function setCartChecksumInSession($checksum){
		Mage::getSingleton('core/session')->setData(self::$ICS_CartChecksum, $checksum);
	}

	/**
	 * Get the current cart's checksum in session.
	 *
	 * @return String The cart checksum
	 */
	public static function getCartChecksumInSession(){
		return Mage::getSingleton('core/session')->getData(self::$ICS_CartChecksum);
	}

	/**
	 * Set the Cart id given in parameter in session.
	 *
	 * @param Integer $cartId The current cartId
	 */
	public static function setIcsCartIdInSession($cartId){
		Mage::getSingleton('core/session')->setData(self::$ICS_CartId, $cartId);
	}

	/**
	 * Get the current cart id from the session.
	 *
	 * @return Integer the current cart id
	 */
	public static function getIcsCartIdInSession(){
		return Mage::getSingleton('core/session')->getData(self::$ICS_CartId);
	}

	/**
	 * Set the Cart's date of last update given in parameter in session.
	 *
	 * @param String $date The date to set in the session
	 */
	public static function setCartLastUpdateInSession($date){
		Mage::getSingleton('core/session')->setData(self::$ICS_CartLastUpdate, $date);
	}

	/**
	 * Get the cart's date of last update from session.
	 *
	 * @return String Cart's date of last update in session
	 */
	public static function getCartLastUpdateInSession(){
		return Mage::getSingleton('core/session')->getData(self::$ICS_CartLastUpdate);
	}

	/**
	 * Set the several information in session:
	 *  -> The given cartId
	 *  -> The given checksum
	 *  -> The given lastUpdate
	 *
	 * @param Integer $cartId The current cartId
	 * @param String $checksum The cart checksum
	 * @param String $lastUpdate The cart last update
	 */
	public static function setIcsInfoInSession($cartId, $checksum, $lastUpdate) {
		self::setIcsCartIdInSession($cartId);
		self::setCartChecksumInSession($checksum);
		self::setCartLastUpdateInSession($lastUpdate);
	}

	/**
	 * Flush all cart information in session
	 */
	public static function resetIcsInfoInSession() {
		self::setIcsCartIdInSession(null);
		self::setCartChecksumInSession(null);
		self::setCartLastUpdateInSession(null);
		Mage::helper('connectedstore/ICSHelper')->setIcsFlagError(false);
	}

	/**
	 * Flush the quote of the given customer.
	 *
	 * @param Mage_Customer_Model_Customer $customer The customer
	 */
	public static function flushCustomerQuote($customer){
		if(empty($customer)) {
			return;
		}

		$quote = Mage::getModel('sales/quote')->loadByCustomer($customer);
		$quote = self::removeAllItems($quote);
		$quote->collectTotals()->save();
	}

	/**
	 * Import the current cart in the quote of the given customer.
	 *
	 * @param Mage_Customer_Model_Customer $customer The current customer
	 */
	public function importIntoMagento($customer) {
		$storeId = Mage::app()->getStore()->getId();
		if(empty($this->bagId) || empty($storeId)) {
			return;
		}

		self::flushCustomerQuote($customer);
		$quoteId = Mage::getModel('sales/quote')->loadByCustomer($customer)->getId();

		if(!empty($this->bagItems)) {
			$api = new Mage_Checkout_Model_Cart_Product_Api();
			$productsData = array();
			foreach($this->bagItems as $bagItem) {
				$productData = $this->getProductDataFromBagItem($bagItem);
				$productId = empty($productData['product_id'])
					? Mage::getModel('catalog/product')->getIdBySku($productData['sku'])
					: $productData['product_id'];
				$productToTest = Mage::getModel('catalog/product')->loadByAttribute('entity_id', $productId);
				$productData['product_id'] = $productId;
				if(!empty($productToTest)) {
					array_push($productsData, $productData);
				}
			}
			
			if(!(empty($productsData))) {
				try {
					$api->add($quoteId, $productsData, $storeId);
				} catch (Exception $e) {
					ICSLogger::error('At least one product does not exist in database for bag : ' . $this->bagId);
				}
			}
		}

		if(!empty($this->coupons) && is_array($this->coupons)){
			$apiCoupon = new Mage_Checkout_Model_Cart_Coupon_Api();
			$keys = array_keys($this->coupons);
			$apiCoupon->add($quoteId, $keys[0], $storeId);
		}
	}

	/**
	 * Set the cart's totals
	 *
	 * @param Mage_Sales_Model_Quote $quote The cart's quote
	 */
	private function setTotalsForCart($quote) {
		$totals = new Totals();

		$cartTotals = $quote->getTotals();

		$totals->subtotal = $cartTotals["subtotal"]->getValue();
		$totals->total = $cartTotals["grand_total"]->getValue();

		if(isset($cartTotals['discount']) && $cartTotals['discount']->getValue()) {
			$totals->discount = $cartTotals['discount']->getValue();
		}

		if(isset($cartTotals['tax']) && $cartTotals['tax']->getValue()) {
			$totals->vat = $cartTotals['tax']->getValue();
		}

		$totals->currency = Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);

		$this->totals = $totals;
	}
	
	/**
	 * Removes all the items of a quote given in parameter
	 * 
	 * @param Quote $quote A quote to flush
	 */
	private static function removeAllItems($quote) {
		foreach ($quote->getItemsCollection() as $itemId => $item) {
			if (is_null($item->getId())) {
				$quote->getItemsCollection()->removeItemByKey($itemId);
			} else {
				$item->isDeleted(true);
			}
		}
		return $quote;
	}
}