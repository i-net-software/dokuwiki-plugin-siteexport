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
 
    /**
     * What kind of syntax are we?
     */
    public function getType() {
        return 'substition';
    }
 
    /**
     * What kind of syntax are we?
     */
    public function getPType() {
        return 'block';
    }
 
    /**
     * Where to sort in?
     */ 
    public function getSort() {
        return 999;
    }
 
 
    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode) {
        // not really a syntax plugin
    }
 
    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        // not really a syntax plugin
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