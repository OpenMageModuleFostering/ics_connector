<?php

class ICSLogger {
	
	const ERROR = 1;
	const WARN  = 2;
    const INFO  = 3;
	const DEBUG = 4;
	
	const LOG_FILE = 'ics.log';
	
	const LOG_LEVEL = self::DEBUG;
	
	
	public static function error($message) {
		self::log($message, self::ERROR, Zend_Log::ERR);
	}
	
	
	public static function warn($message) {
		self::log($message, self::WARN, Zend_Log::WARN);
	}
	
	
	public static function debug($message) {
		self::log($message, self::DEBUG, Zend_Log::DEBUG);
	}
	
	
	public static function info($message) {
		self::log($message, self::INFO, Zend_Log::INFO);
	}
	
	private static function log($message, $level, $zendLevel) {
		if($level <= self::LOG_LEVEL) {
			Mage::log($message, $zendLevel, self::LOG_FILE);
		}
	}
}