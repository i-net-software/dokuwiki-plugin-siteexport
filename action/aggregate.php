<?php
/**
 * Site Export Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) define('DOKU_INC', /** @scrutinizer ignore-type */ realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');

class action_plugin_siteexport_aggregate extends DokuWiki_Action_Plugin {

    /**
     * Register Plugin in DW
     **/
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, 'siteexport_aggregate');
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'siteexport_aggregate_button', array ());
    }
    
    public function siteexport_aggregate(Doku_Event &$event)
    {
        global $ID, $INFO, $conf, $INPUT;

        // Aggregate only if
        // (1) this page really has an aggregator and we did submit a request to do so
        // (2) this page really has an aggregator and we export as PDF
        if ( !( (!empty($INFO['meta']['siteexport']) && $INFO['meta']['siteexport']['hasaggregator'] == true) && ( $INPUT->has( 'siteexport_aggregate' ) || $conf['renderer_xhtml'] == 'siteexport_pdf' ) ) ) { return true; }
        
        $exportBase = cleanID( $INPUT->str('baseID') );
        $namespace = empty($exportBase) ? $INFO['meta']['siteexport']['baseID'] : getNs($exportBase);
        
        $functions = plugin_load('helper', 'siteexport');
        $values = $functions->__getOrderedListOfPagesForID($namespace, $exportBase);
        
        // If no base given, take the first one from the ordered list.        
        if ( empty($exportBase) ) {
            // Reset to latest element
            list($exportBase) = reset( $values );
        }
        
        // If only the one file should be exported, strip it down.
        if ( $INPUT->bool('exportSelectedVersionOnly' ) ) {
            // Strip down values
            $lookupNS = noNS($namespace) == $conf['start'] ? $namespace : $namespace . ':' . $conf['start'];
            
            if ( $INPUT->has( 'mergecompare_start' ) && $INPUT->has( 'mergecompare_end' ) ) {
                    $values = $functions->__getOrderedListOfPagesForStartEnd($lookupNS, $INPUT->int( 'mergecompare_start' ), $INPUT->int( 'mergecompare_end' ) );
            } else {
                $values = $functions->__getOrderedListOfPagesForID($lookupNS, $exportBase);
                $values = array(end( $values )); // the list above has the $exportBase element at the very end
            }
        }
        
        if( $INPUT->bool('includeSelectedVersion', true, true ) && count( $values ) > 1 ) {
            array_pop( $values ); // Remove last entry which is the selected version, but only if more than one entry exists
        }

        $originalID = (string) $ID;

        // Generate a TOC that can be exported
        $TOC = "<toc merge mergeheader";

        // add a mergehint, or better remove it if not required
        if( $INPUT->bool('mergehint', true, true ) ) {
            $TOC .= " mergehint";
        }

        $TOC .= ">\n";
        $thema = array();
        foreach( $values as $value ) {
            list($id, $title) = $value;

            $thema[] = p_get_metadata($id, 'thema', METADATA_RENDER_USING_SIMPLE_CACHE);
            $TOC .= "  * [[{$id}|{$title}]]\n";
        }

        $TOC .= "</toc>";

        // Only get first and last element
        $thema = array_reverse(array_unique(array(reset($thema), end($thema))));
        
        $meta = p_read_metadata($originalID);
        // Temporary ID for rendering a document.
        $ID = (string) cleanID($originalID . '-toc-' . implode('-', array_filter($thema)));

        $meta['current']['thema'] = implode(' - ', array_filter($thema));
        p_save_metadata($originalID, $meta);
        p_save_metadata($ID, $meta);

        if (empty($TOC)) { return true; }
        $event->preventDefault();

        $html = p_render('xhtml', p_get_instructions($TOC), $INFO);
        if ($INFO['prependTOC']) $html = tpl_toc(true) . $html;

        if (@unlink(metaFN($ID, '.meta')) === false) {
            dbglog("Could not delete old meta file: " . metaFN($ID, '.meta') );
        }

        $ID = (string) $originalID;
        echo $html;
        return true;
    }

    /**
     * Inserts a toolbar button
     */
    public function siteexport_aggregate_button(& $event, $param) {
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
