<?php

class Wishlist extends Bag {

	protected static $ICS_WishlistId = 'ICS_WishlistId';
	protected static $ICS_WishlistChecksum = 'ICS_WishlistChecksum';
	protected static $ICS_WishlistLastUpdate = 'ICS_WishlistLastUpdate';
	/** Name of the ICS type for cart */
	const ICS_TYPE = 'WISHLIST';


	/**
	 * Default constructor
	 */
	public function __construct() {
		$this->checkFields();
	}

	/**
	 * A static function which instanciates a Wishlist and feeds it current Magento wishlist information
	 *
	 * @param Mage_Customer_Model_Customer $customer The magento's customer
	 * @param Mage_Wishlist_Model_Wishlist $wishlist The magento's wishlist
	 * @return Wishlist The ICS wishlist built from the magento's one
	 */
	public static function createWishlist($customer, $wishlist) {
		$bag = new Wishlist();
		$bag->bagType = 'WISHLIST';
		$bag->bagName = 'default';
		$bag->constructWishlist($customer, $wishlist);
		$bag->setTotalsForWishlist($wishlist);
		$bag->checkFields();
		return $bag;
	}

	/**
	 * Create a Wishlist from the given json object.
	 *
	 * @param Object $jsonObject The json stream that represents a wishlist
	 * @return Wishlist A wishlist built from the json stream
	 */
	public static function createWishlistFromJsonObject($jsonObject) {
		$wishlist = new Wishlist();

		if(empty($jsonObject)) {
			return $wishlist;
		}

		$wishlist->createBagFromJsonObject($jsonObject);
		$wishlist->checkFields();
		return $wishlist;
	}

	/**
	 * Set the wishlist's checksum given in parameter in session
	 *
	 * @param String $checksum The checksum to set in the session
	 */
	public static function setWishlistChecksumInSession($checksum){
		Mage::getSingleton('core/session')->setData(self::$ICS_WishlistChecksum, $checksum);
	}

	/**
	 * Get the current wishlist's checksum in session
	 *
	 * @return String The current wishlist checksum
	 */
	public static function getWishlistChecksumInSession(){
		return Mage::getSingleton('core/session')->getData(self::$ICS_WishlistChecksum);
	}

	/**
	 * Set the wishlist id given in parameter in session
	 *
	 * @param Integer $wishlistId The id to set in the session
	 */
	public static function setIcsWishlistIdInSession($wishlistId){
		Mage::getSingleton('core/session')->setData(self::$ICS_WishlistId, $wishlistId);
	}

	/**
	 * Get the wishlist id from the session
	 *
	 * @return Integer the current wishlist id
	 */
	public static function getIcsWishlistIdInSession(){
		return Mage::getSingleton('core/session')->getData(self::$ICS_WishlistId);
	}

	/**
	 * Set the wishlist's date of last update given in parameter in session
	 *
	 * @param String $date The date to set in the session
	 */
	public static function setWishlistLastUpdateInSession($date){
		Mage::getSingleton('core/session')->setData(self::$ICS_WishlistLastUpdate, $date);
	}

	/**
	 * Get the Wishlist's date of last update in session
	 *
	 * @return String The wishlist's date of last update in session
	 */
	public static function getWishlistLastUpdateInSession(){
		return Mage::getSingleton('core/session')->getData(self::$ICS_WishlistLastUpdate);
	}

	/**
	 * Set the several information in session:
	 *  -> The given wishlistId
	 *  -> The given checksum
	 *  -> The given lastUpdate
	 *
	 * @param Integer $wishlistId The current wishlistId
	 * @param String $checksum The wishlist checksum
	 * @param String $lastUpdate The wishlist last update
	 */
	public static function setIcsInfoInSession($wishlistId, $checksum, $lastUpdate) {
		self::setIcsWishlistIdInSession($wishlistId);
		self::setWishlistChecksumInSession($checksum);
		self::setWishlistLastUpdateInSession($lastUpdate);
	}

	/**
	 * Flush all wishlist information in session
	 */
	public static function resetIcsInfoInSession() {
		self::setIcsWishlistIdInSession(null);
		self::setWishlistChecksumInSession(null);
		self::setWishlistLastUpdateInSession(null);
	}

	/**
	 * Flush the wishlist of the current customer.
	 */
	public static function flushWishlist(){
		$wishlist = Mage::helper('wishlist')->getWishlist();
		if(is_null($wishlist) || is_null($wishlist->getId())) {
			return;
		}

		$items = Mage::helper('wishlist')->getWishlistItemCollection();
		foreach($items as $item) {
			Mage::getModel('wishlist/item')->load($item->getId())->delete();
		}

		$wishlist->save();
		$wishlist = Mage::getModel('wishlist/wishlist')->load($wishlist->getId());
		Mage::unregister('wishlist');
		Mage::register('wishlist', $wishlist);
	}

	/**
	 * Import the current wishlist in the magento's one.
	 *
	 * @param Mage_Customer_Model_Customer $customer The current customer
	 */
	public function importIntoMagento($customer){
		self::flushWishlist();

		$wishlist = Mage::helper('wishlist')->getWishlist();
		if(is_null($wishlist) || is_null($wishlist->getId())) {
			$wishlist = Mage::getSingleton('wishlist/wishlist')->loadByCustomer($customer, true);
		}
		$wishlist = Mage::getModel('wishlist/wishlist')->load($wishlist->getId());

		if(!empty($this->bagItems)) {
			foreach($this->bagItems as $bagItem) {
				$productData = $this->getProductDataFromBagItem($bagItem);

				// Getting product id
				$sku = empty($bagItem->rawData->parentSku) ? $bagItem->skuRef : $bagItem->rawData->parentSku;
				$product = Mage::getModel('catalog/product')->load(Mage::getModel('catalog/product')->getIdBySku($sku));

				// Wishlist creation
				try {
					$item = $wishlist->addNewItem($product, $productData);
					$item->description = $bagItem->comment;
				} catch (Exception $e) {
					ICSLogger::error('At least one product does not exist in database when loading wishlist : ' . $this->bagId);
				}

				$wishlist->save();
			}
		}

		$wishlist->save();
		$wishlist = Mage::getModel('wishlist/wishlist')->load($wishlist->getId());
		Mage::unregister('wishlist');
		Mage::register('wishlist', $wishlist);
		Mage::unregister('_helper/wishlist');
	}

	/**
	 * Construct a bag message for the current customer wishlist
	 *
	 * @param Mage_Customer_Model_Customer $customer
	 * @param Mage_Wishlist_Model_Wishlist $wishlist
	 */
	private function constructWishlist($customer, $wishlist) {
		$session = Mage::getSingleton('core/session');
		// We add the customer
		$this->customer = $this->createBagCustomer($customer);
		// We get the context
		$this->context = $this->getContext($session);

		//set origin
		if(Mage::app()->getRequest()->getModuleName() == 'wishlist' && Mage::app()->getRequest()->getActionName() == 'fromcart'){
			$this->refBagOrigin = Cart::getIcsCartIdInSession();
		}

		// We get the bagId in session
		$bagId = $session->getData(self::$ICS_WishlistId);
		if($bagId != NULL) {
			$this->bagId = $bagId;
		}

		$this->bagItems = array();
		foreach($wishlist->getItemCollection() as $item) {
			$product = $item->getProduct();
			$icsItem = $this->getBagItemIcsFromWishlistItem($item);

			switch ($product->getTypeId()) {
				case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE :
					$key = $this->generateBagItemKeyFromChildren($icsItem);
					break;
				case Mage_Catalog_Model_Product_Type::TYPE_GROUPED :
					$key = $this->generateBagItemKeyFromChildren($icsItem);
					break;
				case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE :
					$key = $this->generateBagItemKeyFromAttributes($icsItem);
					break;
				default:
					$key = str_replace('.', self::DOT_REPLACE, $product->getData('sku'));
			}

			$this->bagItems[$key] = $icsItem;
		}
	}

	/**
	 * Generate a bagItem from a wishlist item
	 *
	 * @param Mage_Wishlist_Model_Item $item The magento's item
	 * @return BagItem The ICS item built from the magento's one
	 */
	private function getBagItemIcsFromWishlistItem($item) {
		$product = $item->getProduct();
		$buyRequest = $item->getBuyRequest()->getData();

		$bagItem = $this->getIcsItemFromProduct($product, $buyRequest, $item->getData('qty'));
		$bagItem->comment = $item->getDescription();

		$fullyConfiguredProduct = $this->isAllAttributesSet($buyRequest);
		if(isset($buyRequest['options']) || isset($buyRequest['super_group']) || isset($buyRequest['bundle_option']) || isset($buyRequest['links'])
			|| !$fullyConfiguredProduct) {
			$bagItem->rawData->buyRequest = $buyRequest;

			if(isset($buyRequest['bundle_option'])) {
				$bagItem->innerItems = $this->getIcsItemsFromBundleOptions($buyRequest);
			} else if(isset($buyRequest['super_group'])) {
				$bagItem->innerItems = $this->getIcsItemsFromSuperGroup($buyRequest);
			}
		}

		if($fullyConfiguredProduct && $product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
			$bagItem->rawData->parentSku = $bagItem->skuRef;
			$bagItem->skuRef = $this->getSkuFromConfigurableProduct($product, $buyRequest);
		}

		return $bagItem;
	}

	/**
	 * Get the child sku from a fully configured product.
	 *
	 * @param Mage_Catalog_Model_Product $parentProduct The parent product
	 * @param Array $buyRequest The item's buy request
	 * @return String The child sku if found, the parent one otherwise
	 */
	private function getSkuFromConfigurableProduct($parentProduct, $buyRequest) {
		$products = array();

		if(isset($buyRequest['super_attribute'])) {
			$products = Mage::getModel('catalog/product')->getCollection()->addStoreFilter();

			foreach($buyRequest['super_attribute'] as $attributeId => $valueId) {
				$code = $this->getAttributeCodeById($attributeId);
				$products = $products->addAttributeToFilter($code, $valueId);
			}
		}

		$sku = $parentProduct->getSku();
		$parentId = $parentProduct->getId();
		foreach($products as $product) {
			$found = false;
			$parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getData('entity_id'));
			foreach($parentIds as $id) {
				if($parentId == $id) {
					$found = true;
					break;
				}
			}
			if($found) {
				$sku = $product->getSku();
				break;
			}
		}

		return $sku;
	}

	/**
	 * Set the wishlist's totals
	 *
	 * @param Mage_Wishlist_Model_Wishlist $wishlist The magento's wishlist
	 */
	private function setTotalsForWishlist($wishlist) {
		$totals = new Totals();

		$totals->total = 0;
		foreach ($wishlist->getItemCollection() as $item) {
			$totals->total = $totals->total + $item->getProduct()->getFinalPrice() * $item->getData('qty');
		}

		$totals->subtotal = $totals->total;
		$totals->currency = Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);

		$this->totals = $totals;
	}
}