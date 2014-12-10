<?php

if(!defined('DOKU_PLUGIN')) die('meh');
require_once(DOKU_PLUGIN.'siteexport/inc/settings.php');
require_once(DOKU_PLUGIN.'siteexport/inc/debug.php');

class siteexport_functions extends DokuWiki_Plugin
{
    public $debug = null;
    public $settings = null;

    public function siteexport_functions($init=true, $isAJAX=false)
    {
        if ( $init )
        {
            $this->debug = new siteexport_debug();
            $this->debug->isAJAX = $isAJAX;

            $this->settings = new settings_plugin_siteexport_settings($this);
            $this->debug->message("Settings completed: zipFile", $this->settings->zipFile, 1);
        }
    }

    public function getPluginName()
    {
        return 'siteexport';
    }
    
    public function downloadURL()
    {
        $params = array('cache' => 'nocache', 'siteexport' => $this->settings->pattern);

        if ( $this->debug->debugLevel() < 5 ) {
            // If debug, then debug!
            $params['debug'] = $this->debug->debugLevel();
        }
        
        return ml($this->settings->origZipFile, $params, true, '&');
    }

    public function checkIfCacheFileExistsForFileWithPattern($file, $pattern)
    {
        if ( !@file_exists($file) )
        {
            // If the cache File does not exist, move the newly created one over ...
            $this->debug->message("'{$file}' does not exist. Checking original ZipFile", null, 3 );
            $newCacheFile = mediaFN($this->getSpecialExportFileName($this->settings->origZipFile, $pattern));
            
            if ( !@file_exists($newCacheFile) )
            {
                $this->debug->message("The export must have gone wrong. The cached file does not exist.", array("pattern" => $pattern, "original File" => $this->settings->origZipFile, "expected cached file" => $newCacheFile), 3);
            }
            
            $status = io_rename($newCacheFile, $file);
            $this->debug->message("had to move another original file over. Did it work? " . ($status ? 'Yes, it did.' : 'No, it did not.'), null, 2 );
        } else {
            $this->debug->message("The file does exist!", $file, 2 );
        }
    }


    /**
     * Returns an utf8 encoded Namespace for a Page and input Namespace
     * @param $NS
     * @param $PAGE
     */
    function getNamespaceFromID($NS, &$PAGE) {
        global $conf;
        // Check current page - if its an NS add the startpage
        $clean = true;
        resolve_pageid(getNS($NS), $NS, $clean);
        $NSa = explode(':', $NS);
        if ( ! page_exists($NS) && array_pop($NSa) != strtolower($conf['start'] )) { // Compare to lowercase since clean lowers it.
            $NS .= ':' . $conf['start'];
            resolve_pageid(getNS($NS), $NS, $clean);
        }

        $PAGE = noNS($NS);
        $NS = getNS($NS);

        return utf8_encodeFN(str_replace(':', '/', $NS));
    }

    /**
     * create a file name for the page
     **/
    public function getSiteName($ID, $overrideRewrite=false) {
        global $conf;

        if ( empty($ID) ) return false;

        // Remove extensions
        if ( $overrideRewrite ) {
            $ID = preg_replace("#\.(php|html)$#", '', $ID);
        }

        $url = $this->wl($this->cleanID($ID), null, true, null, null, $overrideRewrite); // this must be done with rewriting set to override
        //$url = $this->wl($this->cleanID($ID), null, true); // this must be done with rewriting set to override
        $uri = @parse_url($url);
        if ( $uri['path'][0] == '/' ) {
            $uri['path'] = substr($uri['path'], 1);
        }

        return $this->shortenName($uri['path'] . '.' . $this->settings->fileType);
    }

    /**
     * get the Title for the page
     **/
    public function getSiteTitle($ID) {
        if (useHeading('content') && $ID) {
            $heading = p_get_first_heading($ID,true);
            if ($heading) {
                return $this->xmlEntities($heading);
            }
        }
        $elements = explode(':', $ID);
        return ucwords($this->xmlEntities(array_pop($elements)));
    }

    /**
     * Encoding ()taken from DW - but without needing the renderer
     **/
    public function xmlEntities($string) {
        return htmlspecialchars($string,ENT_QUOTES,'UTF-8');
    }

    /**
     * Create name for the file inside the zip and the replacements
     **/
    function shortenName($NAME)
    {
        $NS = $this->settings->exportNamespace;
        $NAME = preg_replace("%^" . preg_quote(DOKU_BASE, '%') . "%", "", $NAME);
        $NAME = preg_replace("%^((_media|_detail)/)?(" . preg_quote($NS, '%') . "/)?%", "", $NAME);
        
        if ( strstr($NAME, '%') ) { $NAME = rawurldecode($NAME); }

        $this->debug->message("Shortening file to '$NAME'", null, 1);
        return $NAME;
    }

    /**
     * Remove unwanted chars from ID
     *
     * Cleans a given ID to only use allowed characters. Accented characters are
     * converted to unaccented ones
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @param  string  $raw_id    The pageid to clean
     * @param  boolean $ascii     Force ASCII
     * @param  boolean $media     Allow leading or trailing _ for media files
     */
    function cleanID($raw_id,$ascii=false,$media=false){
        global $conf;
        global $lang;
        static $sepcharpat = null;

        global $cache_cleanid;
        $cache = & $cache_cleanid;

        // check if it's already in the memory cache
        if (isset($cache[(string)$raw_id])) {
            return $cache[(string)$raw_id];
        }

        $sepchar = $conf['sepchar'];
        if($sepcharpat == null) // build string only once to save clock cycles
        $sepcharpat = '#\\'.$sepchar.'+#';

        $id = trim((string)$raw_id);
        //        $id = utf8_strtolower($id); // NO LowerCase for us!

        //alternative namespace seperator
        $id = strtr($id,';',':');
        if($conf['useslash']){
            $id = strtr($id,'/',':');
        }else{
            $id = strtr($id,'/',$sepchar);
        }

        if($conf['deaccent'] == 2 || $ascii) $id = utf8_romanize($id);
        if($conf['deaccent'] || $ascii) $id = utf8_deaccent($id,-1);

        // We want spaces to be preserved when they are in the link.
        global $UTF8_SPECIAL_CHARS2;
        $UTF8_SPECIAL_CHARS2_SAVE = (string)$UTF8_SPECIAL_CHARS2;
        $UTF8_SPECIAL_CHARS2 = str_replace(' ', '', $UTF8_SPECIAL_CHARS2);

        //remove specials
        $id = utf8_stripspecials($id,$sepchar,'\*');
        $UTF8_SPECIAL_CHARS2 = $UTF8_SPECIAL_CHARS2_SAVE;

        if($ascii) $id = utf8_strip($id);

        //clean up
        $id = preg_replace($sepcharpat,$sepchar,$id);
        $id = preg_replace('#:+#',':',$id);
        $id = ($media ? trim($id,':.-') : trim($id,':._-'));
        $id = preg_replace('#:[:\._\-]+#',':',$id);

        $cache[(string)$raw_id] = $id;
        return($id);
    }


    /**
     * This builds a link to a wikipage - changed for internal use here
     *
     * It handles URL rewriting and adds additional parameter if
     * given in $more
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */

    function wl($id='',$more='',$abs=false,$sep='&amp;', $IDexists=true, $overrideRewrite=false, $hadBase=false){
        global $conf;

        $this->debug->message("Starting to build WL-URL for '$id'", $more, 1);

        if(is_array($more)){
        
        	$intermediateMore = '';
        	foreach( $more as $key => $value) {
        	
        		if ( strlen($intermediateMore) > 0 ) {
	        		$intermediateMore .= $sep;
        		}
        	
	        	if ( !is_array($value) ) {
		        	$intermediateMore .= rawurlencode($key) . '=';
		        	$intermediateMore .= rawurlencode($value);
		        	continue;
	        	}
	        	
	        	foreach( $value as $val ) {
	        		if ( strlen($intermediateMore) > 0 ) {
		        		$intermediateMore .= $sep;
	        		}
	        	
		        	$intermediateMore .= rawurlencode($key) . '[]=';
		        	$intermediateMore .= rawurlencode($val);
	        	}
        	}
        
            $more = $intermediateMore;
        }else{
            $more = str_replace(',',$sep,$more);
        }

        $id = idfilter($id);

        if($abs){
            $xlink = DOKU_URL;
            if ( !$IDexists && !$hadBase ) { // If the file does not exist, we have to remove the base. This link my be one to an parallel BASE.
                $xlink = preg_replace('#' . DOKU_BASE . '$#', '', $xlink);
            }
        }else if ($IDexists || $hadBase) { // if the ID does exist, we may add the base.
            $xlink = DOKU_BASE;
        } else{
            $xlink = "";
        }

        // $this->debug->message("internal WL function Before Replacing: '$xlink'", array(DOKU_REL, DOKU_URL, DOKU_BASE, $xlink), 2);
        $xlink = preg_replace('#(?<!http:|https:)//+#','/', ($abs ? '' : '/') . "$xlink/"); // ensure slashes at beginning and ending, but strip doubles
        $this->debug->message("'$xlink'", array(DOKU_REL, DOKU_URL, DOKU_BASE, $xlink), 2);

        if ( $overrideRewrite ) {
            $this->debug->message("Override enabled.", null, 1);
            $id = strtr($id,':','/');

            $xlink .= $id;
            if($more) $xlink .= '?'.$more;
        } else {
            if($conf['userewrite'] == 2 ){
                $xlink .= DOKU_SCRIPT.'/'.$id;
                if($more) $xlink .= '?'.$more;
            }elseif($conf['userewrite'] ){
                $xlink .= $id;
                if($more) $xlink .= '?'.$more;
            }elseif($id){
                $xlink .= DOKU_SCRIPT.'?id='.$id;
                if($more) $xlink .= $sep.$more;
            }else{
                $xlink .= DOKU_SCRIPT;
                if($more) $xlink .= '?'.$more;
            }
        }

        $this->debug->message("internal WL function result: '$xlink'", null, 2);

        return $xlink;
    }

    /**
     * Create the export file name - this is the file where everything is being stored
     * @param $FILE
     * @param $PATTERN - additional pattern for re-using old files
     */
    public function getSpecialExportFileName($FILE, $PATTERN=null) {

        if ( empty($FILE) )
        {
            $FILE = $this->settings->origZipFile;
        }

        if ( empty($PATTERN) && empty($this->settings->pattern) ){
            $this->debug->message("Generating an internal md5 pattern. This will go wrong - and won't cache properly.", null, 3);
            $PATTERN = md5(microtime(false));
        }

        // Set Pattern Global for other stuff
        if ( empty($this->settings->pattern) ) {
            $this->settings['pattern'] = $PATTERN;
        } else {
            $PATTERN = $this->settings->pattern;
        }

        $FA = explode('.', $FILE);
        $EXT = array_pop($FA);
        array_push($FA, 'auto');
        array_push($FA, $PATTERN);
        array_push($FA, $EXT);

        $fileName = implode('.', $FA);
        $this->debug->message("Export Filename for '$FILE' will be: '$fileName'", null, 2);
        return $fileName;
    }

    public function getCacheFileNameForPattern($PATTERN = null)
    {
        if ( $PATTERN == null ) {
            $PATTERN = $this->settings->pattern;
        }

        return getCacheName($this->getSpecialExportFileName($this->settings->origZipFile, $PATTERN), '.' . basename(mediaFN($this->settings->origZipFile)) );
    }

    function startRedirctProcess($counter) {
        global $ID;

        $URL = wl($ID);

        $additionalParameters = $_REQUEST;
        $additionalParameters['startcounter'] = $counter;
        $additionalParameters['pattern'] = $this->settings->pattern;

        unset($additionalParameters['id']);
        unset($additionalParameters['u']);
        unset($additionalParameters['p']);
        unset($additionalParameters['r']);
        unset($additionalParameters['http_credentials']);

        $this->addAdditionalParametersToURL($URL, $additionalParameters);
        $this->debug->message("Redirecting to '$URL'", null, 2);

        send_redirect($URL);
        exit(0); // Should not be reached, but anyways
    }

    /**
     * Builds additional Parameters into the URL given
     * @param $URL
     * @param $newAdditionalParameters
     */
    function addAdditionalParametersToURL(&$URL, $newAdditionalParameters) {
         
        // Add additionalParameters
        if ( !empty($newAdditionalParameters) ) {
            foreach($newAdditionalParameters as $key => $value ) {
                if ( empty($key) || empty($value) ) { continue; }

                $append = '';

                if ( is_array($value) ) {
                    foreach( array_values($value) as $aValue ) { // Array Handling
                        $URL .= (strstr($URL, '?') ? '&' : '?') . $key . "[]=$aValue";
                    }
                } else {
                    $append = "$key=$value";
                    $URL .= empty($append) || strstr($URL, $append)  ? '' : (strstr($URL, '?') ? '&' : '?') . $append;
                }

            }
        }
    }

    /**
     * Cleans the wiki variables and returns a rebuild URL that has the new variables at hand
     * @param $data
     */
    function prepare_POSTData($data)
    {
        $NS = !empty($data['ns']) ? $data['ns'] : $data['id'];

        $this->removeWikiVariables($data);
        $data['do'] = 'siteexport';
        $additionalKeys = '';

        ksort($data);

        $this->debug->message("Prepared POST data:", $data, 1);

        foreach( $data as $key => $value ) {

            if ( !is_array($value) ) { continue; }
            $this->debug->message("Found inner Array:", $value, 1);

            asort($value);
            foreach ( $value as $innerKey => $aValue )
            {
                if ( is_numeric($innerKey))
                {
                    $innerKey = '';
                }

                $additionalKeys .= "&$key" . "[$innerKey]=$aValue";
            }

            unset($data[$key]);
        }

        return wl($NS, $data, true, '&') . $additionalKeys;
    }

    /**
     * Parses a String into a $_REQUEST Like variant. You have to tell if a decode of the values is needed
     * @param $inputArray
     * @param $decode
     */
    public function parseStringToRequestArray($inputArray, $decode=false)
    {
        global $plugin_controller;

        $outputArray = $inputArray;
        if ( !is_array($inputArray) )
        {
            $intermediate = str_replace("&amp;", "&", $inputArray);

            $outputArray = array();
            foreach( explode("&", $intermediate) as $param ) {
                list($key, $value) = explode("=", $param, 2);

                // This is needed if we do want to calculate $_REQUEST for a non HTTP-Request
                if ( $decode)
                {
                    $value = urldecode($value);
                }

                if ( empty($key) ) { continue; } // Don't check on Value, because there may be only the key that should be preserved

                if ( substr($key, -2) == '[]' ) {
	                $key = substr($key, 0, -2);
	                if ( !is_array($outputArray[$key]) ) {
		                $outputArray[$key] = array();
	                }
	                
                    array_push($outputArray[$key], $value); // Array Handling
                } else {
                    $outputArray[$key] = $value;
                }
            }
        }

        if ( !empty($outputArray['diPlu']) ) {

            $allPlugins = array();
            foreach($plugin_controller->getList(null,true) as $plugin ) {
                // check for CSS or JS
                if ( !file_exists(DOKU_PLUGIN."$plugin/script.js") && !file_exists(DOKU_PLUGIN."$p/style.css") ) { continue; }
                $allPlugins[] = $plugin;
            }

            if ( count($outputArray['diPlu']) > (count($allPlugins) / 2) ) {
                $outputArray['diInv'] = 1;
                $outputArray['diPlu'] = array_diff($allPlugins, $outputArray['diPlu']);
            }
        }

        return $outputArray;
    }

    /**
     * Remove certain fields from the list.
     * @param $removeArray
     * @param $advanced
     * @param $isString
     */
    function removeWikiVariables(&$removeArray, $advanced=false, $isString=false) {

        $removeArray = $this->parseStringToRequestArray($removeArray);

        // 2010-08-23 - If there is still the media set, retain the id for e.g. detail.php
        if ( !isset($removeArray['media']) ) {
            unset($removeArray['id']);
        }

        unset($removeArray['do']);
        unset($removeArray['ns']);
        unset($removeArray['call']);
        unset($removeArray['sectok']);
        unset($removeArray['rndval']);
        unset($removeArray['tseed']);
        unset($removeArray['http_credentials']);
        unset($removeArray['u']);
        unset($removeArray['p']);
        unset($removeArray['r']);
        unset($removeArray['base']);
        unset($removeArray['siteexport']);
        unset($removeArray['DokuWiki']);
        unset($removeArray['cronOverwriteExisting']);

        if ( $removeArray['renderer'] == 'xhtml' ) {
            $removeArray['do'] = 'export_' . $removeArray['renderer'];
            unset($removeArray['renderer']);
        }
        
        // Keep custom options
        if ( is_array($removeArray['customoptionname']) && is_array($removeArray['customoptionvalue']) && count($removeArray['customoptionname']) == count($removeArray['customoptionvalue']) )
        {
            for( $index=0; $index<count($removeArray['customoptionname']); $index++)
            {
                $removeArray[$removeArray['customoptionname'][$index]] = $removeArray['customoptionvalue'][$index];
            }
	        unset($removeArray['customoptionname']);
	        unset($removeArray['customoptionvalue']);
        }

        if ( $advanced ) {
            if ( $removeArray['renderer'] != 'xhtml' && !empty($removeArray['renderer']) ) {
                $removeArray['do'] = 'export_' . $removeArray['renderer'];
            }

            // 2010-08-25 - Need fakeMedia for some _detail cases with rewrite = 2
            if ( isset($removeArray['fakeMedia'])  ) {
                unset($removeArray['media']);
                unset($removeArray['fakeMedia']);
            }

            /* remove internal params */
            unset($removeArray['ens']);
            unset($removeArray['renderer']);
            unset($removeArray['site']);
            unset($removeArray['namespace']);
            unset($removeArray['exportbody']);
            unset($removeArray['addParams']);
            unset($removeArray['template']);
            unset($removeArray['eclipseDocZip']);
            unset($removeArray['useTocFile']);
            unset($removeArray['JavaHelpDocZip']);
            unset($removeArray['depth']);
            unset($removeArray['depthType']);
            unset($removeArray['startcounter']);
            unset($removeArray['pattern']);
            unset($removeArray['TOCMapWithoutTranslation']);
			// unset($removeArray['disableCache']);
			unset($removeArray['debug']);
        }

        if ( $isString && is_array($removeArray) ) {
            $intermediate = $removeArray;
            $removeArray = array();

            foreach ( $intermediate as $key => $value ) {
                if ( is_array($value) ) {
                    foreach( array_values($value) as $aValue ) { // Array Handling
                        $removeArray[] = $key . "[]=$aValue";
                    }
                } else {
                    $value = trim($value);

                    $removeArray[] = "$key" . ( ((empty($value) && intval($value) !== 0)) || $value == '' ? '' : "=$value" ); // If the Value is empty, the Key must be preserved
                }
            }

            //$removeArray = implode( ($this->settings->fileType == 'pdf' ? "&" : "&amp;"), $removeArray);
            $removeArray = implode( "&", $removeArray); // The &amp; made problems with the HTTPClient / Apache. It should not be a problem to have &
        }
    }

    /**
     * returns a hashed name for the parameters
     * @param $parameters
     */
    public function cronJobNameForParameters($parameters)
    {
        return md5($parameters);
    }

    /**
     * Takes an URL and transforms it into the path+query part
     * Used several times, e.g. for genering the hash for the cache file
     * @param $url
     */
    public function urlToPathAndParams($url)
    {
        $query = parse_url($url, PHP_URL_QUERY);
        $path = preg_replace(":^".DOKU_REL.":", "", parse_url($url, PHP_URL_PATH));
        return "{$path}?{$query}";
    }

    /**
     * Transforms an $_REQUEST into a Hash that can be used for cron and cache file
     * @param $request
     */
    public function requestParametersToCacheHash($request)
    {
        $params = $this->urlToPathAndParams($this->prepare_POSTData($request));
        $this->debug->message("Calculated the following Cache Hash URL: ", $params, 2);
        return $this->cronJobNameForParameters($params);
    }

    /**
     * Check a replaceURL against a baseURL - and make the replaceURL relative against it
     * @param replaceURL - URL which will be made relative if needed
     * @param baseURL - URL which is the reference to be made relative against
     */
    public function getRelativeURL($replaceURL, $baseURL)
    {
        // Base is always absolute without anything at the beginning
        if ( preg_match("#^(\.\./)+#", $baseURL) ) {
            $this->debug->message("The baseURL was not absolute.", $baseURL, 1);
            return $replaceURL;
        }

        $origReplaceURL = $replaceURL;
        $replaceURL = preg_replace("#^(\.\./)+#", '', $replaceURL);

        // Remove ../ at beginning to get the absolute path
        if ( $replaceURL == $origReplaceURL ) {
            $this->debug->message("The replaceURL was already absolute.", $replaceURL, 1);
            return $replaceURL;
        }

        $replaceParts = explode('/', $replaceURL);
        $fileName = array_pop($replaceParts); // Get file

        $baseParts = explode('/', $baseURL);
        array_pop($baseParts); // Remove file. We only need the path to this location.
        
        $this->debug->message("State before kicking.", array($replaceParts, $baseParts), 1);

        // Kick all ../
        while( count($replaceParts) > 0 && count($baseParts) > 0 ) {
        
            if ( $baseParts[0] == $replaceParts[0] ) {
                // Beginning is OK, so remove it.
                array_shift($replaceParts);
                array_shift($baseParts);
            } else {
                break;
            }
        
        }
        
        $this->debug->message("Found URL '{$replaceURL}' that is relative to current page '{$baseURL}'.", array($replaceParts, $baseParts), 1);
        
        // Remove everything that is identical
        $replaceParts[] = $fileName;
        
        return str_repeat('../', count($baseParts)) . implode('/', $replaceParts);
    }

    public function hasAuthentication() {
        $user = $this->getConf('defaultAuthenticationUser');
        $password = $this->getConf('defaultAuthenticationPassword');
        return empty($user) ? false : array(
            'user' => $user,
            'password' => $password
        );
    }
    
    public function authenticate() {
        if( !isset($_SERVER['HTTP_AUTHORIZATION']) && $this->hasAuthentication() ) {
            $authentication = $this->hasAuthentication();
            $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( $authentication['user'] . ':' . $authentication['password'] );
            $this->debug->message("Re-authenticating with default user from configuration", $authentication['user'], 3);
            return auth_setup();
        }
        
        return false;
    }
}

?>