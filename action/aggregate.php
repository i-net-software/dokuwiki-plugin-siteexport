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
	public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, 'siteexport_aggregate');
		$controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'siteexport_aggregate_button', array ());
	}
	
	function siteexport_aggregate(&$event)
	{
	    global $ID, $INFO, $conf;

        // Aggregate only if
        // (1) this page really has an aggregator and we did submit a request to do so
        // (2) this page really has an aggregator and we export as PDF
		if ( !( (!empty($INFO['meta']['siteexport']) && $INFO['meta']['siteexport']['hasaggregator'] == true) && ( isset($_REQUEST['siteexport_aggregate']) || $conf['renderer_xhtml'] == 'siteexport_pdf' ) ) ) { return true; }
		
		$exportBase = cleanID($_REQUEST['baseID']);
		$namespace = empty($exportBase) ? $INFO['meta']['siteexport']['baseID'] : getNs($exportBase);
		
        $functions = plugin_load('helper', 'siteexport');
        $values = $functions->__getOrderedListOfPagesForID($namespace, $exportBase);
        
        // If no base given, take the first one from the ordered list.        
        if ( empty($exportBase) ) {
            // Reset to latest element
            list($exportBase) = reset( $values );
        }
        
        // If only the one file should be exported, strip it down.
        if ( !empty($_REQUEST['exportSelectedVersionOnly']) ) {
            // Strip down values
            $lookupNS = noNS($namespace) == $conf['start'] ? $namespace : $namespace . ':' . $conf['start'];
            
            if ( !empty( $_REQUEST['mergecompare_start'] ) && !empty( $_REQUEST['mergecompare_end'] ) ) {
                 $values = $functions->__getOrderedListOfPagesForStartEnd($lookupNS, $_REQUEST['mergecompare_start'], $_REQUEST['mergecompare_end']);
            } else {
                $values = $functions->__getOrderedListOfPagesForID($lookupNS, $exportBase);
                $values = array(end( $values )); // the list above has the $exportBase element at the very end
            }
        }
        
    	$this->originalID = (string) $ID;

        // Generate a TOC that can be exported
        $TOC = "<toc merge mergeheader>\n";
        $thema = array();
        foreach( $values as $value ) {
        	list($id, $title, $sort) = $value;

        	$thema[] = p_get_metadata($id, 'thema', METADATA_RENDER_USING_SIMPLE_CACHE);
        	$TOC .= "  * [[{$id}|{$title}]]\n";
        }
        
        $TOC .= "</toc>";
        
        // Only get first and last element
        $thema = array_unique(array(reset($thema), end($thema)));
        
        $meta = p_read_metadata($ID);
        $ID = (string) cleanID($ID . '-toc-' . implode('-', array_filter($thema)));
        
        $meta['current']['thema'] = implode(' - ', array_filter($thema));
        p_save_metadata($ID, $meta);

        if ( empty($TOC) ) { return true; }
        $event->preventDefault();
        
        $html = p_render('xhtml', p_get_instructions($TOC),$INFO);
        $html = html_secedit($html,false);
        if($INFO['prependTOC']) $html = tpl_toc(true).$html;

        @unlink(metaFN($ID, '.meta'));

        $ID = (string) $this->originalID;
        echo $html;
        return true;
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