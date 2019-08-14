<?php

class Intuiko_ConnectedStore_Exception extends Zend_Exception {
	public $codeError;

	public function __construct($msg = '', $codeError = 0) {
		ICSLogger::error($msg);
		$this->codeError = $codeError;
		parent::__construct($msg, 0, null);
	}
}
