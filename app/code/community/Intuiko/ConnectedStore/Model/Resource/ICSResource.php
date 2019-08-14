<?php

class ICSResource {
	
	/**
	 * Get bundle selection from id
	 */
	public static function getSelectionById($selectionId) {
		$resource = Mage::getModel('bundle/option')->getResource();
		$adapter = $resource->getReadConnection();
		$select = $adapter->select()
			->from('catalog_product_bundle_selection')
			->where('selection_id = ?', $selectionId);
		return $adapter->fetchRow($select);
	}
}