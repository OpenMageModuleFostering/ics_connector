<?php

class Intuiko_ConnectedStore_PingController extends Mage_Core_Controller_Front_Action {

	public function indexAction() {
		if(!Mage::helper('connectedstore/ICSHelper')->isIcsModuleStatusEnabled()) {
			$response = 'ICS connector is disabled.';
		} else {
			$pong = $this->pingAction();
			if($pong != NULL) {
				$response = "Success in " . $pong->timeElapsed . " ms! Tenant: " . $pong->tenantName . ", Profile: " . $pong->applicationProfile . ", Version: " . $pong->apiVersion;
			} else {
				$response = "Fail to connect to ICS, please check your configuration.";
			}	
		}
		
		$this->getResponse()->setBody($response);
		$this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);
	}

	public function pingAction() {
		return Mage::helper('connectedstore/ICSHelper')->ping();
	}
}
