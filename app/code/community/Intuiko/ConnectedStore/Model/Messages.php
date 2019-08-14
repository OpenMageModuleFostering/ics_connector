<?php

class SearchBagMessage {
	public $type = NULL;
	public $appCustomerId = NULL;
	public $email = NULL;
    public $twitterId = NULL;

	public function __construct($type, $appCustomerId, $email, $twitterId) {
		$this->type = $type;
		$this->appCustomerId = $appCustomerId;
		$this->email = $email;
        $this->twitterId = $twitterId;
	}
}

class MergeMessage {
	public $masterBagId = NULL;
	public $slaveBagId = NULL;
	public $method = NULL;

	public function __construct($masterBagId, $slaveBagId, $method) {
		$this->masterBagId = $masterBagId;
		$this->slaveBagId = $slaveBagId;
		$this->method = $method;
	}
}

class DeleteMessage {
	public $bagId = NULL;

	public function __construct($bagId) {
		$this->bagId = $bagId;
	}
}