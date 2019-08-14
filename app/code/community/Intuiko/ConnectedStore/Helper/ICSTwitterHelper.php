<?php
/**
 * Magento
 */


/**
 *
 * Helper class to uses with Twitter Connectors for ICS
 *
 */
class Intuiko_ConnectedStore_Helper_ICSTwitterHelper extends Mage_Core_Helper_Abstract {

	public function getTwitterId($customer) {
		if (empty($customer)) {
			return null;
		}

        // override this return statement to return the customer's twitterId
		return null;
	}
}