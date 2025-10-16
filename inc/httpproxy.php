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
 * Copyright © i-net software 1998-2010
 */

/** ********************************************************************
 * THIS FILE SHOULD NOT BE MODIFIED
 ******************************************************************** */

if (!defined('DOKU_INC')) die('meh');
if ( file_exists(DOKU_INC . 'inc/HTTPClient.php') ) {
    require_once(DOKU_INC . 'inc/HTTPClient.php');
    class _HTTPProxy extends DokuHTTPClient {}
} else if ( class_exists( "\dokuwiki\HTTP\DokuHTTPClient", true ) ) {
    class _HTTPProxy extends \dokuwiki\HTTP\DokuHTTPClient {}
}

class HTTPProxy extends _HTTPProxy {

    public $debugClass = null;
    public $settings = null;

    /**
     * Constructor.
     * @param siteexport_functions $functions
     */
    public function __construct($functions) {
        global $conf, $INPUT;

        // The proxy should only be used if configured.
        // Usually the proxy will allow connections away from the current server.
        // This is what we do not want in most cases.        
        if ($functions->getConf('useProxy')) {
            unset($conf['proxy']);
        }

        // call parent constructor
        $this->debugClass = $functions->debug;
        $this->settings = $functions->settings;
        parent::__construct();

        $this->timeout = 60; //max. 25 sec
        $this->headers['If-Modified-Since'] = gmdate('r', 0);
        $this->status = -1;
        $this->debug = true;

        if ($this->settings->cookie == null) {
            $this->_debug("Has to re-authenticate request.");
            if (!$this->authenticate()) {

                $this->_debug("Trying other Authentication (auth.php):"); // Try again.
                if (!(auth_setup() && $this->authenticate(true))) {
                    $this->_debug("Trying other Authentication (config):", $functions->authenticate() && $this->authenticate(true) ? 'authenticated' : 'not authenticated'); // Try again.
                } else {
                    $this->_debug("Ok, using default auth.php"); // Try again.
                }
            }

            $this->_debug("Using Authentication:", array('user' => $this->user, 'password' => '*****'));

        } else {
            $this->cookies = $this->settings->cookie;
        }

        $this->headers['X-Real-Ip'] = clientIP(true);
        $this->headers['X-Site-Exporter'] = $functions->getSecurityToken();
        $this->headers['Accept-Encoding'] = $INPUT->server->str('HTTP_ACCEPT_ENCODING');
        $this->headers['Accept-Charset'] = $INPUT->server->str('HTTP_ACCEPT_CHARSET');
        $this->agent = $INPUT->server->str('HTTP_USER_AGENT') . ' DokuWiki/SiteExport';
    }

    /**
     * Authenticate using currently logged in user
     */
    private function authenticate($secondAttempt = false) {

        global $auth, $INPUT;

        // Ok, this is evil. We read the login information of the current user and forward it to the HTTPClient
        list($this->user, $sticky, $this->pass) = auth_getCookie();

        // Logged in in second attempt is now in Session.    
        if ($secondAttempt && !isset($this->user) && $INPUT->str('u') && $INPUT->str('p')) {

            // We hacked directly into the login mechanism which provides the login information without encryption via $INPUT
            $this->user = $INPUT->str('u');
            $this->pass = $INPUT->str('p');
        } else {
            $secret = auth_cookiesalt(!$sticky, true); //bind non-sticky to session
            $this->pass = !empty($this->pass) ? $this->auth_decrypt($this->pass, $secret) : '';
        }

        return isset($this->user);
    }

    /**
     * Auth Decryption has changed from Weatherwax to Binky
     */    
    private function auth_decrypt($pass, $secret) {

        if (function_exists('auth_decrypt')) {
            // Binky
            return auth_decrypt($pass, $secret);
        } else if (function_exists('PMA_blowfish_decrypt')) {
            // Weatherwax
            return PMA_blowfish_decrypt($pass, $secret);
        } else {
            $this->debugClass->runtimeException("No decryption method found");
        }
    }

    /**
     * Remeber HTTPClient Cookie after successfull authentication
     */    
    public function sendRequest($url, $data = '', $method = 'GET') {

        $returnCode = parent::sendRequest($url, $data, $method);
        if ($this->settings->cookie == null) {
            $this->settings->cookie = $this->cookies;
        }

        return $returnCode;
    }

    /**
     * print debug info to file if exists
     * @param string $info
     * @param mixed  $var
     */
    public function _debug($info, $var = null) {

        if (!$this->debugClass) {
            return;
        }

        $this->debugClass->message("[HTTPClient] " . $info, $var, 1);
    }

    /**
     * print debug info to file if exists
     * @param string $info
     * @param mixed  $var
     */
    protected function debug($info, $var = null) {
        $this->_debug( $info, $var );
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
