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
    
    private $checkArray = array();

    public function getType(){ return 'substition';}
    public function getAllowedTypes() { return array('container', 'formatting', 'substition', 'protected', 'disabled', 'paragraphs'); }
    public function getPType(){ return 'stack';}
    public function getSort(){ return 999; }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern($this->special_pattern,$mode,'plugin_siteexport_toctools');
        $this->Lexer->addEntryPattern($this->entry_pattern,$mode,'plugin_siteexport_toctools');
    }

    public function postConnect() {
        $this->Lexer->addExitPattern($this->exit_pattern, 'plugin_siteexport_toctools');
    }
    
    private function findPreviousSectionOpen( Doku_Handler $handler ) {
        foreach( array_reverse( $handler->calls ) as $calls ) {
            if ( $calls[0] == 'section_open' ) {
                return $calls[1][0];
            }
        }
        return 1;
    }
    
    private function addInstructionstoHandler( $match, $state, $pos, Doku_Handler $handler, $instructions ) {

        // Switch for DW Hogfather+
        if ( ( method_exists( $handler, 'getStatus') && $handler->getStatus('section') ) || $handler->status['section'] ) {
            $handler->_addCall('section_close', array(), $pos);
        }
    
        // We need to add the current plugin first and then open the section again.
        $level = $this->findPreviousSectionOpen( $handler );
        $handler->_addCall('plugin', array('siteexport_toctools', $instructions, $state), $pos);
        $handler->_addCall('section_open', array($level), $pos+strlen($match) );
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        global $conf;
        switch ($state) {
            case DOKU_LEXER_ENTER:
            case DOKU_LEXER_SPECIAL:
                $data = trim(substr($match,strpos($match,' '),-1)," \t\n/");
                $this->addInstructionstoHandler( $match, $state, $pos, $handler, array('mergehint', 'start', $data, sectionID( $data, $this->checkArray ) ) );
                break;
            case DOKU_LEXER_UNMATCHED:
                $handler->_addCall('cdata', array($match), $pos);
                break;
            case DOKU_LEXER_EXIT:
                $this->addInstructionstoHandler( $match, $state, $pos, $handler, array('mergehint', 'end', 'syntax' ) );
                break;
        }
        return false;
    }

    /**
     * Create output
     */
    public function render($mode, Doku_Renderer $renderer, $data) {

        list( $type, $pos, $title, $id ) = $data;
        if ($mode == 'xhtml') {
            if ( $type == 'mergehint' ) {
                
                $startHint = '<!-- MergeHint Start for "';
                $lastDiv = '<div class="mergehintcontent">';

                if ( $pos == 'start' ) {
                    $renderer->doc .= $startHint . $title . '" -->';
                    $renderer->doc .= '<div id="' . $id . '" class="siteexport mergehintwrapper"><aside class="mergehint">' . $title . '</aside>' . $lastDiv;
                } else {
                    
                    // check if anything was inserted. We have to strip all tags,
                    // we're just looking for real content here
                    $lastPos = strrpos($renderer->doc, $lastDiv);
                    if ( $lastPos !== false ) {
                        
                        $remaining = substr( $renderer->doc, $lastPos + strlen($lastDiv) );
                        $remaining = strip_tags( $remaining );
                        $remaining = trim($remaining);
                        if ( strlen( $remaining ) == 0 ) {
                            // empty
                            $lastPos = strrpos($renderer->doc, $startHint);
                            $renderer->doc = substr($renderer->doc, 0, $lastPos);
                            return true;
                        }
                    }

                    $renderer->doc .= '</div></div>';
                    $renderer->doc .= '<!-- MergeHint End for "' . $title . '" -->';
                }
            } else {
                $renderer->doc .= "<br style=\"page-break-after:always;\" />";
            }
            return true;
        } else if ( $mode = 'markdown' && $type == 'mergehint' && $pos == 'start' ) {
            $renderer->doc .= DOKU_LF . '##### ' . $title . DOKU_LF;
            return true;
        }
        return false;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :