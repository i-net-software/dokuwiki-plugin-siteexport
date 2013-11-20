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
    
    /**
     * Constructor.
     */
    function __construct($debug){
        global $conf;

        // call parent constructor
        $this->debugClass = $debug;
        parent::__construct();
        
        $this->timeout = 60; //max. 25 sec
        $this->headers['If-Modified-Since'] = substr(gmdate('r', 0), 0, -5).'GMT';
        $this->status = -1;
        $this->debug = true;
	}

	
	 /**
	 * print debug info to file if exists
	 */
	public function _debug($info,$var=null){
		
		if ( !$this->debugClass ) {
			return;
		}

		$this->debugClass->message($info, $var, 1);
	}
}

//Setup VIM: ex: et ts=4 enc=utf-8 :