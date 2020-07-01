<?php
/**
 * Siteexport Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */
 
if (!defined('DOKU_INC')) define('DOKU_INC', /** @scrutinizer ignore-type */ realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_siteexport_toctools extends DokuWiki_Syntax_Plugin {

    protected $special_pattern = '<mergehint\b[^>\r\n]*?/>';
    protected $entry_pattern   = '<mergehint\b.*?>(?=.*?</mergehint>)';
    protected $exit_pattern    = '</mergehint>';

    function getType(){ return 'substition';}
    function getAllowedTypes() { return array('container', 'formatting', 'substition', 'protected', 'disabled', 'paragraphs'); }
    function getPType(){ return 'stack';}
    function getSort(){ return 999; }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern($this->special_pattern,$mode,'plugin_siteexport_toctools');
        $this->Lexer->addEntryPattern($this->entry_pattern,$mode,'plugin_siteexport_toctools');
    }

    function postConnect() {
        $this->Lexer->addExitPattern($this->exit_pattern, 'plugin_siteexport_toctools');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler){
        global $conf;
        switch ($state) {
            case DOKU_LEXER_ENTER:
            case DOKU_LEXER_SPECIAL:
                $data = trim(substr($match,strpos($match,' '),-1)," \t\n/");

                // print "<pre>"; print_r($handler); print "</pre>"; 
                
                if ($handler->status['section']) {
                    $handler->_addCall('section_close',array(),$pos);
                }

                return array('mergehint', 'start', $data, sectionid( $data ));

            case DOKU_LEXER_UNMATCHED:
                $handler->_addCall('cdata', array($match), $pos);
                break;

            case DOKU_LEXER_EXIT:
            
                $level = 1;
                foreach( array_reverse( $handler->calls ) as $call ) {
                    if ( $calls[0] == 'section_open' ) {
                        $level = $calls[1][0];
                        break;
                    }
                }
            
                // We need to add the current plugin first and then open the section again.
                $handler->_addCall('plugin',array('siteexport_toctools', array('mergehint', 'end', 'syntax'),DOKU_LEXER_EXIT),$pos);
                $handler->_addCall('section_open',array($level),$pos+strlen($match));
        }
        return false;
    }

    /**
     * Create output
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode == 'xhtml') {
            list( $type, $pos, $title, $id ) = $data;
            if ( $type == 'mergehint' ) {
                if ( $pos == 'start' ) {
                    $renderer->doc .= '<!-- MergeHint Start for "' . $title . '" -->';
                    $renderer->doc .= '<div id="' . $id . '" class="siteexport mergehintwrapper"><aside class="mergehint">' . $title . '</aside><div class="mergehintcontent">';
                } else {
                    $renderer->doc .= '</div></div>';
                    $renderer->doc .= '<!-- MergeHint End for "' . $title . '" -->';
                }
            } else {
                $renderer->doc .= "<br style=\"page-break-after:always;\" />";
            }
            return true;
        }
        return false;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :