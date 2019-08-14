<?php

class Order extends Bag {

	/**
	 * Default constructor.
	 */
	private function __construct() {
		$this->checkFields();
	}

	/**
	 * A static method which instanciates an Order and feeds it with quote information.
	 *
	 * @param Mage_Sales_Model_Quote $quote The cart's quote
	 * @param Mage_Sales_Model_Order $order The order's information
	 * @return Cart A new order built from the cart and the checkout information
	 */
	public static function createOrder($quote, $order) {
		$bag = new Order();
		$bag->bagType = 'ORDER';
		$bag->constructCartOrOrderFromQuote($quote);
		$bag->setTotalsForOrder($order);
        $bag->paymentMethod = $quote->getPayment()->getMethodInstance()->getTitle();
        $bag->shipmentMethod = $order->getShippingDescription();
		// if guest, special customer method
		if($quote->getData('checkout_method') === Mage_Checkout_Model_Type_Onepage::METHOD_GUEST){
			$bag->customer = $bag->getGuestFromQuote($quote);
		}
		$bag->checkFields();
		return $bag;
	}

	/**
	 * Set the order's totals
	 *
	 * @param Mage_Sales_Model_Order $order The order
	 */
	private function setTotalsForOrder($order) {
		$totals = new Totals();

		$totals->subtotal = $order->getSubtotal();
		$totals->total = $order->getGrandTotal();
		$totals->discount = $order->getDiscountAmount();
		$totals->shipping = $order->getShippingAmount();
		$totals->vat = $order->getTaxAmount();
		// TODO: vatRate
		$totals->currency = Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);

		$this->totals = $totals;
	}
}