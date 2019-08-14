<?php

include_once 'Bag.php';
include_once 'Cart.php';
include_once 'Wishlist.php';
include_once 'Order.php';
include_once 'Messages.php';
include_once(__DIR__ . "../Logger/ICSLogger.php");

class Intuiko_ConnectedStore_Model_Observer extends Varien_Event_Observer {

	private static $ICSIsLoggedIn = 'ICSIsLoggedIn';

	const CART_KEY = "cart";
	const WISHLIST_KEY = "wishlist";
	const FORCED_SAVE_KEY = "forcedSave";

	/**
	 * Save a cart in ICS after a cart update.
	 * Called on magento event [checkout_cart_save_after].
	 */
	public function onSaveCart($observer) {
		if(!Mage::helper('connectedstore/ICSHelper')->isICSModuleEnabled()) {
			return;
		}

		ICSLogger::debug('*** Beginning SaveCart event (checkout_cart_save_after)');
		$this->performCartSave(Cart::getCartChecksumInSession(), Cart::createCart($observer->getEvent()->_data['cart']->getQuote()));
	}

	/**
	 * Save a wishlist in ICS after a wishlist update.
	 * Called on magento event [wishlist_items_renewed].
	 */
	public function onSaveWishlist() {
		$customer = Mage::getSingleton('customer/session')->getCustomer();
		$isLoggedIn = Mage::getSingleton('core/session')->getData(self::$ICSIsLoggedIn);
		if(!Mage::helper('connectedstore/ICSHelper')->isICSModuleEnabled() || empty($isLoggedIn) || !$customer->getId()){
			return;
		}

		ICSLogger::debug('*** Beginning SaveWishlist event (wishlist_items_renewed)');
		$this->performWishlistSave(Wishlist::getWishlistChecksumInSession(), Wishlist::createWishlist($customer, Mage::helper('wishlist')->getWishlist()));
	}

	/**
	 * Save an order in ICS after an order update.
	 * Called on magento event [checkout_submit_all_after].
	 */
	public function onSaveOrder($observer) {
		if(!Mage::helper('connectedstore/ICSHelper')->isICSModuleEnabled()) {
			return;
		}

		ICSLogger::debug('*** Beginning SaveOrder event (checkout_submit_all_after)');

		$bag = Order::createOrder($observer->getEvent()->_data['quote'], $observer->getEvent()->_data['order']);
		$response = Mage::helper('connectedstore/ICSHelper')->save($bag);

		if(empty($response)) {
			return;
		}

		Cart::resetIcsInfoInSession();
	}

	/**
	 * Manage login event, to get the customer cart and wishlist.
	 * At this point, the customer may have an anonymous bag (stored in the magento session) and/or a non-anonymous bag in the ICS database.
	 * Following the cases, this method get the non-anonymous bag, the anonymous one, or merge the two bags to get the final bag of the customer.
	 * This final bag is finally imported into the magento's quote and possibly saved again in ICS.
	 *
	 * Called on magento event [customer_login].
	 */
	public function onLogin() {
		$customer = Mage::getSingleton('customer/session')->getCustomer();
		if(!Mage::helper('connectedstore/ICSHelper')->isICSModuleEnabled() || !$customer->getId()) {
			return;
		}
		
		$this->sendIcsCustomer($customer);

		ICSLogger::debug('*** Beginning Login event (customer_login)');

        $twitterId = Mage::helper('connectedstore/ICSTwitterHelper')->getTwitterId($customer);
		$searchResponseCart = Mage::helper('connectedstore/ICSHelper')->searchBagsIds(Cart::ICS_TYPE, $customer->getId(), $customer->getEmail(), $twitterId);
		$searchResponseWishlist = Mage::helper('connectedstore/ICSHelper')->searchBagsIds(Wishlist::ICS_TYPE, $customer->getId(), $customer->getEmail(), $twitterId);

		ICSLogger::debug('+ Retrieving cart...');
		if(is_numeric($searchResponseCart) && $searchResponseCart !== -1) {
			return;
		}
		$array = $this->getCartForLogin($customer, $searchResponseCart);
		$bag = $array[self::CART_KEY];
		$forcedSave = $array[self::FORCED_SAVE_KEY];

		ICSLogger::debug("Importing cart into magento");
		$bag->importIntoMagento($customer);
		$finalBag = Cart::createCart(Mage::getModel('sales/quote')->loadByCustomer($customer));
		$this->performCartSave($bag->getCheckSum(), $finalBag, $forcedSave);

		ICSLogger::debug('+ Retrieving wishlist...');
		if(is_numeric($searchResponseWishlist) && $searchResponseWishlist !== -1) {
			return;
		}
		$array = $this->getWishlistForLogin($customer, $searchResponseWishlist);
		$bag = $array[self::WISHLIST_KEY];
		$forcedSave = $array[self::FORCED_SAVE_KEY];

		ICSLogger::debug("Importing wishlist into magento");
		$bag->importIntoMagento($customer);
		$finalBag = Wishlist::createWishlist($customer, Mage::helper('wishlist')->getWishlist());
		$this->performWishlistSave($bag->getCheckSum(), $finalBag, $forcedSave);

		Mage::getSingleton('core/session')->setData(self::$ICSIsLoggedIn, 1);
	}

	/**
	 * Reset cart and wishlist in session.
	 * Called on magento event [customer_logout].
	 */
	public function onLogout(){
		if(!Mage::helper('connectedstore/ICSHelper')->isICSModuleEnabled()) {
			return;
		}

		Cart::resetIcsInfoInSession();
		Wishlist::resetIcsInfoInSession();
		Customer::resetCustomerChecksumInSession();
		Mage::getSingleton('core/session')->setData(self::$ICSIsLoggedIn, 0);
	}

	/**
	 * Check synchronize flag and synchronize cart and wishlist if needed.
	 * Called on magento event [controller_action_predispatch].
	 */
	public function synchroBag() {
		$customer = Mage::getSingleton('customer/session')->getCustomer();
		$flag = Bag::getIcsFlagSynchroInSession();
		if(!Mage::helper('connectedstore/ICSHelper')->isICSModuleEnabled() || !empty($flag) || !$customer->getId()) {
			return;
		}

		ICSLogger::debug('*** Beginning SynchroBag event (controller_action_predispatch)');

		ICSLogger::debug('+ Synchronising cart...');
		$this->synchroCart($customer);
		ICSLogger::debug('+ Synchronising wishlist...');
		$this->synchroWishlist($customer);
		Bag::setIcsFlagSynchroInSession(true);
	}

	/**
	 * Function for enable the next synchronize of bags.
	 * Called on magento event [controller_action_layout_generate_blocks_after].
	 */
	public function resetFlagSynchro(){
		if(!Mage::helper('connectedstore/ICSHelper')->isICSModuleEnabled()) {
			return;
		}

		Bag::setIcsFlagSynchroInSession(false);
	}

	/**
	 * Synchronize cart from ICS.
	 *
	 * @param Mage_Customer_Model_Customer $customer The magento customer
	 */
	private function synchroCart($customer) {
        $twitterId = Mage::helper('connectedstore/ICSTwitterHelper')->getTwitterId($customer);
		$searchResponse = Mage::helper('connectedstore/ICSHelper')->searchBagsIds(Cart::ICS_TYPE, $customer->getId(), $customer->getEmail(), $twitterId);

		if(is_numeric($searchResponse)) {
			return;
		}

		if(empty($searchResponse)) {
			if(!Mage::helper('connectedstore/ICSHelper')->getIcsFlagError()){
				ICSLogger::debug('No cart found, quote will be flushed');
				Cart::flushCustomerQuote($customer);
			}else{
				ICSLogger::debug("No cart found, response error from ICS.");
			}
			
			Cart::resetIcsInfoInSession();
			return;
		}

		$icsBagId = $searchResponse[0];
		$bagId = Cart::getIcsCartIdInSession();

		if(!empty($bagId) && $bagId == $icsBagId) {
			$getResponse = Mage::helper('connectedstore/ICSHelper')->getBagById($bagId, Cart::getCartLastUpdateInSession());
			if(!empty($getResponse) && is_numeric($getResponse)) {
				ICSLogger::debug('Cart up to date, nothing is done');
				return;
			}

			ICSLogger::debug('Cart out of date, it will be updated');
			$bag = Cart::createCartFromJsonObject($getResponse);
		} else {
			ICSLogger::debug('New cart has been found, get request will be sent');
			$bag =  Cart::createCartFromJsonObject(Mage::helper('connectedstore/ICSHelper')->getBagById($icsBagId));
			Cart::setIcsCartIdInSession($icsBagId);
		}

		ICSLogger::debug("Importing cart into magento");
		$bag->importIntoMagento($customer);
		Cart::setCartLastUpdateInSession($bag->lastUpdate);
		$finalBag = Cart::createCart(Mage::getModel('sales/quote')->loadByCustomer($customer));
		$this->performCartSave($bag->getCheckSum(), $finalBag);
	}

	/**
	 * Synchronize wishlist from ICS.
	 *
	 * @param Mage_Customer_Model_Customer $customer The magento customer
	 */
	private function synchroWishlist($customer){
        $twitterId = Mage::helper('connectedstore/ICSTwitterHelper')->getTwitterId($customer);
		$searchResponse = Mage::helper('connectedstore/ICSHelper')->searchBagsIds(Wishlist::ICS_TYPE, $customer->getId(), $customer->getEmail(), $twitterId);

		if(is_numeric($searchResponse)) {
			return;
		}

		if(empty($searchResponse)) {
			ICSLogger::debug('No wishlist found, it will be flushed');
			Wishlist::flushWishlist();
			Wishlist::resetIcsInfoInSession();
			return;
		}

		$icsBagId = $searchResponse[0];
		$bagId = Wishlist::getIcsWishlistIdInSession();

		if(!empty($bagId) && $bagId == $icsBagId) {
			$getResponse = Mage::helper('connectedstore/ICSHelper')->getBagById($bagId, Wishlist::getWishlistLastUpdateInSession());
			if(!empty($getResponse) && is_numeric($getResponse)) {
				ICSLogger::debug('Wishlist up to date, nothing is done');
				return;
			}

			ICSLogger::debug('Wishlist out of date, it will be updated');
			$bag = Wishlist::createWishlistFromJsonObject($getResponse);
		} else {
			ICSLogger::debug('New wishlist has been found, get request will be sent');
			$bag =  Wishlist::createWishlistFromJsonObject(Mage::helper('connectedstore/ICSHelper')->getBagById($icsBagId));
			Wishlist::setIcsWishlistIdInSession($icsBagId);
		}

		ICSLogger::debug("Importing wishlist into magento");
		$bag->importIntoMagento($customer);
		$finalBag = Wishlist::createWishlist($customer, Mage::helper('wishlist')->getWishlist());
		Wishlist::setWishlistLastUpdateInSession($bag->lastUpdate);
		$this->performWishlistSave($bag->getCheckSum(), $finalBag);
	}

	/**
	 * Perform a call to the save method of the api if needed.
	 * The save is needed if the given bag's checksum is different than the given old checksum.
	 *
	 * @param String $oldChecksum The old checksum
	 * @param Cart $bag The bag to save
	 * @param Bool $forcedSave True for the bag to be saved even if the old checksum and the given bag's checksum are the same.
	 */
	private function performCartSave($oldChecksum, $bag, $forcedSave = false) {
		if(!is_null($oldChecksum) && strcmp($bag->getCheckSum(), $oldChecksum) == 0 && !$forcedSave) {
			ICSLogger::debug('Cart has not been modified, nothing is done');
			return;
		}

		ICSLogger::debug('Cart has been modified, save request will be sent');
		$response = Mage::helper('connectedstore/ICSHelper')->save($bag);

		if(empty($response)) {
			return;
		}

		$bag->bagId = $response['bagId'];
		Cart::setIcsInfoInSession($response['bagId'], $bag->getCheckSum(), $response['lastUpdate']);
	}

	/**
	 * Perform a call to the save method of the api if needed.
	 * The save is needed if the given bag's checksum is different than the old checksum.
	 *
	 * @param String $oldChecksum The old checksum
	 * @param Wishlist $bag The bag to save
	 * @param Bool $forcedSave True for the bag to be saved even if the old checksum and the given bag's checksum are the same.
	 */
	private function performWishlistSave($oldChecksum, $bag, $forcedSave = false) {
		if(!is_null($oldChecksum) && strcmp($bag->getCheckSum(), $oldChecksum) == 0 && !$forcedSave) {
			ICSLogger::debug('Wishlist has not been modified, nothing is done');
			return;
		}

		ICSLogger::debug('Wishlist has been modified, save request will be sent');
		$response = Mage::helper('connectedstore/ICSHelper')->save($bag);

		if(empty($response)) {
			return;
		}

		$bag->bagId = $response['bagId'];
		Wishlist::setIcsInfoInSession($response['bagId'], $bag->getCheckSum(), $response['lastUpdate']);
	}

	/**
	 * Get an array containing the current cart of the customer and if it must be saved.
	 * The cart is retrieved following the different possible cases.
	 *
	 * @param Mage_Customer_Model_Customer $customer The current customer
	 * @param Array $searchResponse The response of a search call
	 * @return Array An array containing, for the key "cart" the current cart of the customer,
	 * and for the key "forcedSave" a boolean that tells if that cart must be saved.
	 */
	private function getCartForLogin($customer, $searchResponse) {
		$bag = new Cart();
		$forcedSave = false;
		$currentBagId = Cart::getIcsCartIdInSession();

		if($searchResponse === -1 && empty($currentBagId)){
			ICSLogger::debug("Customer not known in ICS");
			$bag = Cart::createCart(Mage::getModel('sales/quote')->loadByCustomer($customer));
			$forcedSave = true;
		}
		else if($searchResponse === -1 && !empty($currentBagId)) {
			ICSLogger::debug('Customer not known in ICS with a cart in magento\'s session');
			$bag =  Cart::createCartFromJsonObject(Mage::helper('connectedstore/ICSHelper')->getBagById($currentBagId));
			$forcedSave = true;
		}
		else if(empty($searchResponse) && !empty($currentBagId)) {
			ICSLogger::debug('Customer has a cart in magento\'s session and not in ICS');
			$bag =  Cart::createCartFromJsonObject(Mage::helper('connectedstore/ICSHelper')->getBagById($currentBagId));
			$forcedSave = true;
		}
		else if(!empty($searchResponse) && empty($currentBagId)) {
			ICSLogger::debug('Customer has at least one cart in ICS and not in magento\'s session');
			$bag = Cart::createCartFromJsonObject(Mage::helper('connectedstore/ICSHelper')->getBagById($searchResponse[0]));
			Cart::setIcsInfoInSession($bag->bagId, $bag->getCheckSum(), $bag->lastUpdate);
		}
		else if(!empty($searchResponse) && !empty($currentBagId)) {
			ICSLogger::debug('Customer has a cart in magento\'s session and at least one cart in ICS');
			$bag = Cart::createCartFromJsonObject(Mage::helper('connectedstore/ICSHelper')->merge($searchResponse[0], $currentBagId));
			ICSLogger::debug('Merge done, delete request will be sent for the anonymous cart');
			Mage::helper('connectedstore/ICSHelper')->delete($currentBagId);
			Cart::setIcsInfoInSession($bag->bagId, $bag->getCheckSum(), $bag->lastUpdate);
		}
		else if(empty($searchResponse) && empty($currentBagId)) {
			ICSLogger::debug("Customer has no magento cart and no ICS cart");
			Cart::flushCustomerQuote($customer);
			Cart::resetIcsInfoInSession();
		}

		return array(self::CART_KEY => $bag, self::FORCED_SAVE_KEY => $forcedSave);
	}

	/**
	 * Get an array containing the current wishlist of the customer.
	 * The wishlist is retrieved following the different possible cases.
	 *
	 * @param Mage_Customer_Model_Customer $customer The current customer
	 * @param Array $searchResponse The response of a search call
	 * @return Array An array containing, for the key "wishlist" the current wishlist of the customer,
	 * and for the key "forcedSave" a boolean that tells if that wishlist must be saved.
	 */
	private function getWishlistForLogin($customer, $searchResponse) {
		$bag = new Wishlist();
		$forcedSave = false;

		if($searchResponse === -1){
			ICSLogger::debug("Customer not known in ICS");
			$bag = Wishlist::createWishlist($customer, Mage::helper('wishlist')->getWishlist());
			$forcedSave = true;
		}
		else if(!empty($searchResponse)) {
			ICSLogger::debug('Customer has at least one wishlist in ICS');
			$bag = Wishlist::createWishlistFromJsonObject(Mage::helper('connectedstore/ICSHelper')->getBagById($searchResponse[0]));
			Wishlist::setIcsInfoInSession($bag->bagId, $bag->getCheckSum(), $bag->lastUpdate);
		}
		else if(empty($searchResponse)) {
			ICSLogger::debug("Customer has no ICS wishlist");
			Wishlist::flushWishlist();
			Wishlist::resetIcsInfoInSession();
		}

		return array(self::WISHLIST_KEY => $bag, self::FORCED_SAVE_KEY => $forcedSave);
	}
	
	public function onCustomerSaveAfter() {
		if(!Mage::helper('connectedstore/ICSHelper')->isICSModuleEnabled()) {
			return;
		}
		ICSLogger::debug('*** Beginning SaveCustomer event (customer_save_after)');
		$this->sendIcsCustomer(Mage::getSingleton('customer/session')->getCustomer());
	}
	
	private function sendIcsCustomer($customerSession) {
		if($customerSession == null || $customerSession->getId() == null) {
			return;
		}
		$customerToSend = Customer::createIcsCustomer($customerSession);
		$oldChecksum = Customer::getCustomerChecksumInSession();
		$checksum = $customerToSend->getCheckSum(); 
		ICSLogger::debug("Customer checksum verification");
		if($oldChecksum == null || $oldChecksum != $checksum) {
			ICSLogger::debug("Checksums are not the same. We save.");
			$response = Mage::helper('connectedstore/ICSHelper')->saveCustomer($customerToSend);
			if(empty($response)) {
				return;
			}
			Customer::setCustomerChecksumInSession($checksum);
		}
	}
}