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


    private $instructions = null;
    private $originalID = null;

    /**
	* Register Plugin in DW
	**/
	function register(&$controller) {
		$controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE',  $this, 'siteexport_aggregate_prepare');
		$controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, 'siteexport_aggregate');
		$controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'siteexport_aggregate_button', array ());
	}
	
	function siteexport_aggregate_prepare(&$event)
	{
	    global $ID, $INFO;
	    
		if ( !isset($_REQUEST['siteexport_aggregate']) ) { return true; }
		
		$exportBase = cleanID($_REQUEST['baseID']);
        $functions = plugin_load('helper', 'siteexport');
        $values = $functions->__getOrderedListOfPagesForID(getNs($exportBase), $exportBase);
    	
    	$this->originalID = (string) $ID;

        // Generate a TOC that can be exported
        $TOC = "~~NOCACHE~~\n<toc merge mergeheader>\n";
        $thema = array();
        foreach( $values as $value ) {
        	list($id, $title, $sort) = $value;

        	$thema[] = p_get_metadata($id, 'thema', METADATA_RENDER_USING_SIMPLE_CACHE);
        	$TOC .= "  * [[{$id}|{$title}]]\n";
        }
        
        $TOC .= "</toc>";
        
        // Only get first and last element
        $thema = array(reset($thema), end($thema));
        
        $meta = p_read_metadata($ID);
        $ID = (string) cleanID($ID . '-toc-' . implode('-', array_filter($thema)));
        
        $meta['current']['thema'] = implode(' - ', array_filter($thema));
        p_save_metadata($ID, $meta);

        $this->instructions = $TOC;        
	}
	
	function siteexport_aggregate(&$event)
	{
		global $ID, $INFO;

        if ( empty($this->instructions) ) { return true; }
        $event->preventDefault();
        
        $html = p_render('xhtml', p_get_instructions($this->instructions),$INFO);
        $html = html_secedit($html,false);
        if($INFO['prependTOC']) $html = tpl_toc(true).$html;

        @unlink(metaFN($ID, '.meta'));

        $ID = (string) $this->originalID;
        
        echo $html;
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
