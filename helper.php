<?php
/**
 * Translation Plugin: Simple multilanguage plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_siteexport extends DokuWiki_Plugin {

	/**
	 * for backward compatability
	 * @see inc/DokuWiki_Plugin#getInfo()
	 */
    function getInfo(){
        if ( method_exists(parent, 'getInfo')) {
            $info = parent::getInfo();
        }
        return is_array($info) ? $info : confToHash(dirname(__FILE__).'/plugin.info.txt');
    }
	
    /*
     * return all the templates that this wiki has
     */
	function __getTemplates() {

		// populate $this->_choices with a list of directories
		$list = array();

		$_dir = DOKU_INC . 'lib/tpl/';
		$_pattern = '/^[\w-]+$/';
		if ($dh = @opendir($_dir)) {
			while (false !== ($entry = readdir($dh))) {
				if ($entry == '.' || $entry == '..') continue;
				if ($entry == '.' || $entry == '..') continue;
				if ($_pattern && !preg_match($_pattern,$entry)) continue;

				$file = (is_link($_dir.$entry)) ? readlink($_dir.$entry) : $entry;
				if (is_dir($_dir.$file)) $list[] = $entry;
			}
			closedir($dh);
		}


		sort($list);
		return $list;
	}
	
	/*
	 * Return array list of plugins that exist
	 */
	function __getPluginList() {
	    global $plugin_controller;
	    
	    $allPlugins = array();
	    foreach($plugin_controller->getList(null,true) as $plugin ) {
	    	// check for CSS or JS
	    	if ( !file_exists(DOKU_PLUGIN."$plugin/script.js") && !file_exists(DOKU_PLUGIN."$p/style.css") ) { continue; }
	    	$allPlugins[] = $plugin;
	    }
	    
    	return array($allPlugins, $plugin_controller->getList());
	}
}
