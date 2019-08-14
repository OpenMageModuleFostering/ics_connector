<?php

include 'Entities.php';
include 'Resource/ICSResource.php';

class Bag extends Message {

	private static $ICS_FlagSynchro = 'ICS_FlagSynchro';
	const DOT_REPLACE = '\uff0e';
	
	public $bagId = null;
	public $bagType = null;
	public $bagName = null;
	public $bagItems = array();
	public $coupons = null;
    public $totals = null;
    public $paymentMethod = null;
    public $shipmentMethod = null;
	public $refBagOrigin = null;
	public $lastUpdate = null;
	
	/**
	 * Default constructor
	 */
	private function __construct() {}

	/**
	 * Set the flag value in the session
	 *
	 * @param Boolean $value The boolean to set in the session
	 */
	public static function setIcsFlagSynchroInSession($value){
		$session = Mage::getSingleton('core/session');
		$session->setData(self::$ICS_FlagSynchro, $value);
	}

	/**
	 * Get the flag value from the session
	 *
	 * @return Boolean The flag value
	 */
	public static function getIcsFlagSynchroInSession(){
		$session = Mage::getSingleton('core/session');
		return $session->getData(self::$ICS_FlagSynchro);
	}

	/**
	 * Generates a checksum from the current bag information
	 *
	 * @return String The checksum of the current bag
	 */
	public function getCheckSum() {
		$data = array();
		$data['bagId'] = $this->bagId;
		$data['bagType'] = $this->bagType;
		$data['bagName'] = $this->bagName;
		$data['coupons'] = $this->coupons;
        $data['totals'] = $this->totals;
        $data['paymentMethod'] = $this->paymentMethod;
        $data['shipmentMethod'] = $this->shipmentMethod;
		
		$bagItemsChecksum = 0;
		if($this->bagItems != null && is_array($this->bagItems)) {
			foreach($this->bagItems as $bagItem){
				$bagItemsChecksum += hexdec(md5(json_encode($bagItem)));
			}
		}
		
		$checksum = hexdec(md5(json_encode($data)));
		return $checksum + $bagItemsChecksum;
	}

	/**
	 * Allows to convert an array from a json stream which represents an ics bag
	 *
	 * @param Object $jsonBag The json object that represents a bag
	 */
	protected function createBagFromJsonObject($jsonBag){
		$this->bagId = $jsonBag['id'];
		$this->lastUpdate = $jsonBag['lastUpdate'];
		$this->bagName = $jsonBag['name'];
		$this->bagType = $jsonBag['bagType'];
		$this->customer = $this->createBagCustomer(Mage::getSingleton('customer/session')->getCustomer());

		$bagItems = array();
		if($jsonBag['bagItems'] != null){
			foreach($jsonBag['bagItems'] as $serial => $bagItem){
				$currentBagItem = new BagItem();
				if(isset($bagItem['comment'])){
					$currentBagItem->comment = $bagItem['comment'];
				}
				if(isset($bagItem['ean'])){
					$currentBagItem->ean = $bagItem['ean'];
				}
				$currentBagItem->qtyToBill = $bagItem['qtyToBill'];
				$currentBagItem->imgUrl = $bagItem['imgUrl'];
				$currentBagItem->label = $bagItem['label'];
				$currentBagItem->language = $bagItem['language'];
				$currentBagItem->orderedQty = $bagItem['orderedQty'];
				$currentBagItem->qtyUnit = $bagItem['qtyUnit'];
				$currentBagItem->qtyToShip = $bagItem['qtyToShip'];
				$currentBagItem->skuRef = $bagItem['skuRef'];
				$currentBagItem->type = $bagItem['type'];
				$currentBagItem->family = $bagItem['family'];

				$attributes = array();
				if($bagItem['attributes'] != null) {
					foreach($bagItem['attributes'] as $key=>$value){
						$currentAttribute = new Attribute();
						$currentAttribute->value = $value['value'];
						$attributes[$key] = $currentAttribute;
					}
				}

				$currentBagItem->attributes = $attributes;

				if($bagItem['price'] != null){
					$currentPrice = $bagItem['price'];
					$price = new Price();
					$price->base = (float) $currentPrice['base'];
					$price->currency = $currentPrice['currency'];
					$price->sale = $currentPrice['sale'] != null ? (float) $currentPrice['sale'] : null;
					$price->sell = (float) $currentPrice['sell'];
					$price->vat = (float) $currentPrice['vat'];
					$price->vatRate = (float) $currentPrice['vatRate'];
					$currentBagItem->price = $price;
				}

				if(isset($bagItem['rawData'])) {
					$currentRawData = $bagItem['rawData'];
					$rawData = new RawData();
					$rawData->buyRequest = $currentRawData['buyRequest'];
					$rawData->parentSku = $currentRawData['parentSku'];
					$currentBagItem->rawData = $rawData;
				}

				$bagItems[$serial] = $currentBagItem;
			}
		}

		$this->bagItems = $bagItems;
		$this->coupons = $jsonBag['coupons'];

		$totals = new Totals();

		if($jsonBag['totals'] != null){
			$bagTotals = $jsonBag['totals'];

			$totals->currency = $bagTotals['currency'];
			$totals->discount = $bagTotals['discount'];
			$totals->shipping= $bagTotals['shipping'];
			$totals->total = $bagTotals['total'];
			$totals->vat = $bagTotals['vat'];
			$totals->vatRate = $bagTotals['vatRate'];
			$totals->subtotal = $bagTotals['subtotal'];

			$this->totals = $totals;
		}

        $this->paymentMethod = $jsonBag['paymentMethod'];
        $this->shipmentMethod = $jsonBag['shipmentMethod'];
	}

	/**
	 * Get ICS geolocalisation from gps.
	 *
	 * @param String $gps The String containing the latitude and longitude information
	 * @return Geoloc The ICS geoloc built from latitude and longitude information
	 */
	private function getGeoloc($gps){
		$geoloc = new Geoloc();
		list($lat, $lng) = explode('::', $gps);
		$geoloc->lat = $lat;
		$geoloc->lng = $lng;

		return $geoloc;
	}

	/**
	 * Get ICS referer from server information.
	 *
	 * @return Referer The ICS referer built from server information
	 */
	private function getReferer(){
		$referer = new Referer();
		$referer->name = $_SERVER['HTTP_REFERER'];
		return $referer;
	}

	/**
	 * Get an ICS context from the magento core session.
	 *
	 * @param Mage_Core_Model_Session $session
	 * @return Context The ICS context built from the session
	 */
	protected function getContext($session){
		$context = new Context();
		$context->device = $session->getData("_session_validator_data/http_user_agent");
		$context->ipAddress = $_SERVER['REMOTE_ADDR'];
		$context->referer = $this->getReferer();

		if (isset($_COOKIE['gps'])) {
			$gps = $_COOKIE["gps"];
			if($gps != NULL){
				$context->geoloc = $this->getGeoloc($gps);
			}
		}
		return $context;
	}

	/**
	 * Get an ICS price from a magento's product.
	 *
	 * @param Mage_Catalog_Model_Product $product The magento's product
	 * @return Price The ICS price built from a product
	 */
	protected function getPriceIcs($product) {
		$price = new Price();
		if($product->getPrice() != null) {
			$price->base = (float) $product->getPrice();
		} else {
			$price->base = (float) $product->getFinalPrice();
		}

// 		$price->sale = 0; // FIXME
		$price->sell = (float) $product->getFinalPrice();
		$price->vat = (float) 2.1; // FIXME
		$price->vatRate = (float) 20; // FIXME
		$price->currency = Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);
		return $price;
	}

	/**
	 * Gets the selections id array from bundle options
	 *
	 * @param Array $bundleOptions The array of bundle options
	 * @return Array The array of selections id built from the bundle options
	 */
	private function getBundleSelectionIdsFromBundleOptions($bundleOptions) {
		$selectionIds = array();
		foreach($bundleOptions as $selectionId) {
			if(!is_array($selectionId)) {
				array_push($selectionIds, $selectionId);
			} else {
				foreach($selectionId as $selectId) {
					array_push($selectionIds, $selectId);
				}
			}
		}

		return $selectionIds;
	}

	/**
	 * Get ics attributes from super attributes from buyRequest
	 *
	 * @param Array $buyRequest The buy request of an item
	 * @return Array An array of ICS attributes built from super attributes
	 */
	protected function getIcsAttributesFromSuperAttributes($buyRequest) {
		$attributes = array();

		if(isset($buyRequest['super_attribute'])) {
			$superAttributes = $buyRequest['super_attribute'];
			foreach($superAttributes as $attributeId => $valueId) {
				$attribute = $this->getAttributeByIdOrCode($attributeId);

				$bagAttribute = new Attribute();
				$bagAttribute->value = $attribute->getSource()->getOptionText($valueId);
				$attributes[$attribute->getData('attribute_code')] = $bagAttribute;
			}
		}

		return $attributes;
	}

	/**
	 * Get ics bagItems from bundle options from buyRequest
	 *
	 * @param Array $buyRequest The buy request of an item
	 * @return BagItem The ICS item built from bundle options
	 */
	protected function getIcsItemsFromBundleOptions($buyRequest) {
		$storeId = Mage::app()->getStore()->getId();
		$bagItems = array();

		if(isset($buyRequest['bundle_option'])) {
			$selectionIds = $this->getBundleSelectionIdsFromBundleOptions($buyRequest['bundle_option']);

			foreach($selectionIds as $selectionId) {
				$selection = ICSResource::getSelectionById($selectionId);
				$productId = $selection['product_id'];
				$product = Mage::helper('catalog/product')->getProduct($productId, $storeId, 'productId');

				if(!is_null($product)) {
					$options = $product->getTypeInstance(true)->getOrderOptions($product);
					$bagItems[$product->getSku()] = $this->getIcsItemFromProduct($product, $options['info_buyRequest'], $selection['selection_qty']);
				}
			}
		}

		return $bagItems;
	}

	/**
	 * Get ics bagItems from super group from buyRequest
	 *
	 * @param Array $buyRequest The buy request of an item
	 * @return BagItem The ICS item built from super group
	 */
	protected function getIcsItemsFromSuperGroup($buyRequest) {
		$storeId = Mage::app()->getStore()->getId();
		$bagItems = array();

		if(isset($buyRequest['super_group'])) {
			$superGroup = $buyRequest['super_group'];

			foreach($superGroup as $productId => $qty) {
				$product = Mage::helper('catalog/product')->getProduct($productId, $storeId, 'productId');

				if($product != null) {
					$options = $product->getTypeInstance(true)->getOrderOptions($product);
					$bagItems[$product->getSku()] = $this->getIcsItemFromProduct($product, $options['info_buyRequest'], $qty);
				}
			}
		}

		return $bagItems;
	}

	/**
	 * Turn a Quote item into an ICS item
	 *
	 * @param Mage_Wishlist_Model_Item $item The magento's item
	 * @return BagItem The ICS item built from magento's one
	 */
	private function getIcsItemFromQuoteItem($item) {
		$product = $item->getProduct();
		$options = $product->getTypeInstance(true)->getOrderOptions($product);
		$buyRequest = $options['info_buyRequest'];

		$bagItem = $this->getIcsItemFromProduct($product, $buyRequest, $item->getData('qty'));

		if(isset($buyRequest['options']) || isset($buyRequest['bundle_option']) || isset($buyRequest['links'])) {
			$bagItem->rawData->buyRequest = $buyRequest;
			$bagItem->innerItems = $this->getIcsItemsFromBundleOptions($buyRequest);
		}

		return $bagItem;
	}

	/**
	 * Get an ICS item from a magento's product
	 *
	 * @param Mage_Catalog_Model_Product $product The magento's product
	 * @param $buyRequest Array The buy request of the item represented by the given product
	 * @param Integer $qty The quantity of the product (default: 1)
	 * @return BagItem The ICS item built from magento's product
	 */
	protected function getIcsItemFromProduct($product, $buyRequest, $qty = 1) {
		$bagItem = new BagItem();
		$qty = round($qty, 2);

		$bagItem = $this->setSkuInICSItemFromProduct($bagItem, $product);
		$bagItem->orderedQty = $qty;
		$bagItem->qtyUnit = "piece";
		$bagItem->label = $product->getName();
		$bagItem->language =  Mage::getBlockSingleton('page/html')->getLang();
		$bagItem->type = $product->getTypeId();
		$bagItem->family = Mage::getModel("eav/entity_attribute_set")->load($product->getAttributeSetId())->getAttributeSetName();
		$bagItem->imgUrl = Mage::getModel('catalog/product_media_config')->getMediaUrl($product->getSmallImage());
		$bagItem->attributes = $this->getIcsAttributesFromSuperAttributes($buyRequest);
		$bagItem->price = $this->getPriceIcs($product);

		return $bagItem;
	}

	/**
	 * Set the sku and the parent sku if needed in an ICS item from a magento product.
	 *
	 * @param BagItem $bagItem The ICS item to set sku and parent sku
	 * @param Mage_Catalog_Model_Product $product The magento product.
	 * @return BagItem The modified item
	 */
	protected function setSkuInICSItemFromProduct($bagItem, $product) {
		$parentSku = $product->getData('sku');
		$sku = $product->getSku();

		if($parentSku != $sku && $product->getTypeId() != Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
			$bagItem->skuRef = $sku;
			$bagItem->rawData->parentSku = $parentSku;
		} else {
			/* We took the parent sku to avoid dynamic sku (which are stored in the getSku() method of product model) */
			$bagItem->skuRef = $parentSku;
		}

		return $bagItem;
	}

	/**
	 * Check if all super attributes from buy request are set.
	 *
	 * @param Array $buyRequest the buyRequest of an item
	 * @return Boolean false if all super attributes have a value, true otherwise.
	 */
	protected function isAllAttributesSet($buyRequest) {
		if(isset($buyRequest['super_attribute'])) {
			foreach($buyRequest['super_attribute'] as $valueId) {
				if(!isset($valueId) || empty($valueId)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Create an light ICS customer from a Magento Customer
	 *
	 * @param Mage_Customer_Model_Customer $customer The customer from Magento
	 * @return Customer A light ICS customer built from the magento's one
	 */
	protected function createBagCustomer($customer){
		$res = new Customer();
		$res->appCustomerId = $customer->getId();
		$res->email = $customer->getEmail();
        $res->twitterId = Mage::helper('connectedstore/ICSTwitterHelper')->getTwitterId($customer);
		return $res;
	}

	/**
	 *
	 * Get guest customer information from quote.
	 *
	 * @param Mage_Sales_Model_Quote $quote The quote
	 * @return Customer The customer in guest mode
	 */
	protected function getGuestFromQuote($quote) {
		$res = new Customer();
		$res->appCustomerId = $quote->getData('customer_id');
		$res->firstName = $quote->getData('customer_firstname');
		$res->lastName = $quote->getData('customer_lastname');
		$res->email = $quote->getData('customer_email');

		$addresses = $quote->getAllAddresses();
		// We get the billing address
		$billingAddress = NULL;
		if($addresses != NULL && count($addresses) == 1) {
			$billingAddress = $addresses[0];
		} else if($addresses != NULL && count($addresses) > 0) {
			for($i=0; $i<count($addresses); $i++) {
				$address = $addresses[$i];
				if($address != null && $address->getData('address_type') != null && $address->getData('address_type') === Mage_Sales_Model_Quote_Address::TYPE_BILLING) {
					$billingAddress = $addresses[$i];
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
	 * Generate a key for a configurable product
	 * The key is generated as followed: sku-attribute1-attribute2-...
	 *
	 * @param BagItem $bagItem An ICS item
	 * @return String A generated key from the attributes of the item
	 */
	protected function generateBagItemKeyFromAttributes($bagItem) {
		$sku = $bagItem->skuRef;
		$attributes = $bagItem->attributes;

		$key = $sku;
		if(isset($attributes)) {
			foreach($attributes as $value) {
				$attributeValue = $value->value;
				if(isset($attributeValue) && !empty($attributeValue)) {
					$key .= '-' . $value->value;
				}
			}
		}
		return str_replace('.', self::DOT_REPLACE, $key);
	}

	/**
	 * Generates a key with a bagItem inner items.
	 *
	 * @param BagItem $bagItem The item which key needs to be generated
	 * @return String The generated key
	 */
	protected function generateBagItemKeyFromChildren($bagItem){
		$str = $bagItem->skuRef;
		if(!is_null($bagItem->innerItems)){
			foreach($bagItem->innerItems as $children){
				$qty = round($children->orderedQty, 2);
				$str .= '-' . $qty . '-' . $children->skuRef;
			}
		}
		return str_replace('.', self::DOT_REPLACE, $str);
	}

	/**
	 * Create a cart or an order from a quote
	 *
	 * @param Mage_Sales_Model_Quote $quote The quote
	 */
	protected function constructCartOrOrderFromQuote($quote) {
		$session = Mage::getSingleton('core/session');

		// We get the customer
		$customerSession = Mage::getSingleton('customer/session')->getCustomer();
		$customer = $this->createBagCustomer($customerSession);
		if($customer != null && $customer->email != null){
			$this->customer = $customer;
		}

		//set origin
		if(Mage::app()->getRequest()->getModuleName() == 'wishlist' && Mage::app()->getRequest()->getActionName() == 'cart'){
			$this->refBagOrigin = Wishlist::getIcsWishlistIdInSession();
		}

		$context = $this->getContext($session);
		$this->context = $context;

		// We get the bagId in session
		$cartId = $session->getData(Cart::$ICS_CartId);
		if($cartId != NULL) {
			$this->bagId = $cartId;
		}

		$this->bagItems = array();
		foreach ($quote->getAllVisibleItems() as $item) {
			$icsItem = $this->getIcsItemFromQuoteItem($item);
			if($icsItem->type === 'bundle') {
				$key = $this->generateBagItemKeyFromChildren($icsItem);
			} else {
				$key = str_replace('.', self::DOT_REPLACE, $item->getProduct()->getSku());
			}

			if(isset($this->bagItems[$key])) {
				$bagItem = $this->bagItems[$key];
				$bagItem->orderedQty += $icsItem->orderedQty;
				$bagItem->qtyToShip += $icsItem->qtyToShip;
				$bagItem->qtyToBill += $icsItem->qtyToBill;
				$this->bagItems[$key] = $bagItem;
			} else {
				$this->bagItems[$key] = $icsItem;
			}
		}

		if(strlen($quote->getData('coupon_code'))) {
			$this->coupons = array($quote->getData('coupon_code') => 1);
		} else {
			$this->coupons = null;
		}
	}

	/**
	 * Build an array of magento useful attributes to add a product into a cart/wishlist
	 *
	 * @param BagItem $bagItem The ICS item
	 * @return array An array containing all attributes to add a product into a cart/wishlist
	 */
	public function getProductDataFromBagItem($bagItem) {
		$productData = array();

		$rawData = $bagItem->rawData;
		if(isset($rawData) && isset($rawData->buyRequest)) {
			$productData = $rawData->buyRequest;
			$productData['product_id'] = $productData['product'];
		} else {
			$productModel = Mage::getModel('catalog/product');
			$productId = $productModel->getIdBySku($bagItem->skuRef);

			if(isset($bagItem->attributes) && count($bagItem->attributes) > 0) {
				$product = $productModel->load($productId);
				$superAttributes = array();
				foreach($bagItem->attributes as $key=>$value) {
					$superAttributes[$this->getAttributeIdByCode($key)] = $product->getData($key);
				}
				$productData['super_attribute'] = $superAttributes;
			}

			$productData['sku'] = isset($rawData->parentSku) ? $rawData->parentSku : $bagItem->skuRef;
		}

		$productData['qty'] = $bagItem->orderedQty;

		return $productData;
	}

	/**
	 * Get an attribute code by its id
	 *
	 * @param Integer $attributeId The attribute id
	 * @return String The attribute code matching the given id
	 */
	public function getAttributeCodeById($attributeId) {
		return $this->getAttributeByIdOrCode($attributeId)->getData('attribute_code');
	}

	/**
	 * Get an attribute id by its code
	 *
	 * @param String $attributeCode The attribute code
	 * @return Integer The attribute id matching the given code
	 */
	public function getAttributeIdByCode($attributeCode) {
		return $this->getAttributeByIdOrCode($attributeCode)->getData('attribute_id');
	}

	/**
	 * Get an attribute by its code or id.
	 * The entity type of the attribute is fixed to catalog_product
	 *
	 * @param Integer|String The code or the id of the attribute to retrieve
	 * @return Mage_Eav_Model_Entity_Attribute_Abstract The attribute matching the given id/code
	 */
	public function getAttributeByIdOrCode($identifier) {
		$model = Mage::getModel('eav/config');
		$entityType = $model->getEntityType(Mage_Catalog_Model_Product::ENTITY);
		return $model->getAttribute($entityType, $identifier);
	}

	/**
	 * Check some fields and gives them always the same value if they are null or empty.
	 * The goal is to avoid a field is empty at a moment and null at another moment.
	 *
	 * This method does NOT check mandatory fields.
	 */
	public function checkFields() {
		// bag items
		if(!is_null($this->bagItems)) {
			foreach($this->bagItems as $key=>$bagItem) {
				$bagItem->checkFields();
				$this->bagItems[$key] = splitNullValuesInObject($bagItem);
			}
		}
		
		// totals
		if($this->totals != null) {
			$this->totals = splitNullValuesInObject($this->totals);
		}
		
		// context
		if($this->context != null) {
			$this->context->checkFields();
			$this->context = splitNullValuesInObject($this->context);
		}
		
		// customer
		if($this->customer != null) {
			$this->customer->checkFields();
			$this->customer = splitNullValuesInObject($this->customer);
		}
	}
}

class Message {
	public $context = null;
	public $customer = null;
}