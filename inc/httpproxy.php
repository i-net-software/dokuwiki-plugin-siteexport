<?php

/**
 * i-net software provides programming examples for illustration only,
 * without warranty either expressed or implied, including, but not
 * limited to, the implied warranties of merchantability and/or fitness
 * for a particular purpose. This programming example assumes that you
 * are familiar with the programming language being demonstrated and the
 * tools used to create and debug procedures. i-net software support
 * professionals can help explain the functionality of a particular
 * procedure, but they will not modify these examples to provide added
 * functionality or construct procedures to meet your specific needs.  
 * Copyright � i-net software 1998-2010
 */

/** ********************************************************************
 * THIS FILE SHOULD NOT BE MODIFIED
 ******************************************************************** */

if(!defined('DOKU_INC')) die('meh');
require_once( DOKU_INC . 'inc/HTTPClient.php');

class HTTPProxy extends DokuHTTPClient {
	
    var $debugClass = null;
    var $setttings = null;
    
    /**
     * Constructor.
     */
    function __construct($debug, $settings){
        global $conf;

        // call parent constructor
        $this->debugClass = $debug;
        $this->settings = $settings;
        parent::__construct();
        
        $this->timeout = 60; //max. 25 sec
        $this->headers['If-Modified-Since'] = substr(gmdate('r', 0), 0, -5).'GMT';
        $this->status = -1;
        $this->debug = true;

		if ( $this->settings->cookie == null ) {
			$this->_debug("Has to re-authenticate request.");
			if ( !$this->authenticate() ) {
				$this->_debug("Trying other Authentication (auth.php):", auth_setup() && $this->authenticate(true) ? 'authenticated' : 'not authenticated'); // Try again.
			}

			$this->_debug("Using Authentication:", array('user' => $this->user, 'password' => '*****'));
			
		} else {
			$this->cookies = $this->settings->cookie;
		}

		$this->headers['X-Real-Ip'] = clientIP(true);
		$this->headers['Accept-Encoding'] = $_SERVER['HTTP_ACCEPT_ENCODING'];
		$this->headers['Accept-Charset'] = $_SERVER['HTTP_ACCEPT_CHARSET'];
		$this->agent = $_SERVER['HTTP_USER_AGENT'];
	}
	
	/**
	 * Authenticate using currently logged in user
	 */
	private function authenticate($secondAttempt=false) {
		
		global $auth, $INPUT;
		
		// Ok, this is evil. We read the login information of the current user and forward it to the HTTPClient
		list($this->user, $sticky, $this->pass) = auth_getCookie();

		// Logged in in second attempt is now in Session.	
		if ( $secondAttempt && !isset($this->user) && $INPUT->str('u') && $INPUT->str('p') ) {

			// We hacked directly into the login mechanism which provides the login information without encryption via $INPUT
			$this->user = $INPUT->str('u');
			$this->pass = $INPUT->str('p');
			$sticky = $INPUT->str('r');
		} else {
			$secret = auth_cookiesalt(!$sticky, true); //bind non-sticky to session
			$this->pass = auth_decrypt($this->pass, $secret);
		}
		
		return isset($this->user);
	}

	/**
	 * Remeber HTTPClient Cookie after successfull authentication
	 */	
	function sendRequest($url,$data='',$method='GET') {
		
		$returnCode = parent::sendRequest($url,$data,$method);
		if ( $this->settings->cookie == null ) {
			$this->settings->cookie = $this->cookies;
		}
		
		return $returnCode;
	}
	
	 /**
	 * print debug info to file if exists
	 */
	public function _debug($info,$var=null){
		
		if ( !$this->debugClass ) {
			return;
		}

		$this->debugClass->message("[HTTPClient] " . $info, $var, 1);
	}
}

//Setup VIM: ex: et ts=4 enc=utf-8 :