<?php
/**
 * Site Export Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_siteexport_aggregate extends DokuWiki_Action_Plugin {


    /**
	* Register Plugin in DW
	**/
	function register(&$controller) {
		$controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, 'siteexport_aggregate');
		$controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'siteexport_aggregate_button', array ());
	}
	
	function siteexport_aggregate(&$event)
	{
		global $ID;

		//if (  $event->data != 'siteexport_aggregate' ) { return true; }
		if (  !isset($_REQUEST['siteexport_aggregate']) ) { return true; }
		
		$exportBase = cleanID($_REQUEST['baseID']);
        $functions=& plugin_load('helper', 'siteexport');
        $values = $functions->__getOrderedListOfPagesForID($ID, $exportBase);
        
        // Generate a TOC that can be exported
        $TOC = "<toc merge mergeheader>\n";
        foreach( $values as $value ) {
        	list($id, $title, $sort) = $value;
        	$TOC .= "  * [[{$title}]]\n";
        }
        
        $TOC .= "</toc>\n";
        $info = null;
        print p_render('xhtml', p_get_instructions($TOC),$info);
        
        $event->preventDefault();
        return false;
	}
	
	/**
	 * Inserts a toolbar button
	 */
	function siteexport_aggregate_button(& $event, $param) {
	    $event->data[] = array (
	        'type' => 'mediapopup',
	        'title' => $this->getLang('toolbarButton'),
	        'icon' => '../../plugins/siteexport/images/toolbar.png',
	        'url' => 'lib/plugins/siteexport/exe/siteexportmanager.php?ns=',
	        'options' => 'width=750,height=500,left=20,top=20,scrollbars=yes,resizable=yes',
	        'block' => false,
	    );
	}
}