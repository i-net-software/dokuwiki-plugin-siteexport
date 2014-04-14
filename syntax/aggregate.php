<?php
/**
 * i-net Download Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_siteexport_aggregate extends DokuWiki_Syntax_Plugin {

    function getType(){ return 'substition';}
    function getPType(){ return 'block';}
    function getSort(){ return 300; }

    function connectTo($mode){
        $this->Lexer->addSpecialPattern('\{\{(?=siteexport|siteexportAGGREGATOR).*?\}\}',$mode,'plugin_siteexport_aggregate');
    }

    function handle($match, $state, $pos, &$handler){
    
    	$options = explode(' ', trim(substr($match, 2, -2)));
        return $options;
    }
    
    function render($mode, &$renderer, $data){
        global $ID, $conf;

        if ($mode == 'xhtml'){

            $renderer->info['toc'] = false;
            $renderer->nocache();
            
            $formParams = array( 'id' => sectionID('siteexport_site_aggregator', $renderer->headers), 'action' => wl($ID), 'class' => 'siteexport aggregator' );
            $form = new Doku_Form($formParams);
            $functions=& plugin_load('helper', 'siteexport');

        	$form->addHidden('ns', $ID);
        	$form->addHidden('site', $ID);

			if ( array_shift($data) == 'siteexportAGGREGATOR' ) {
	        	$form->addHidden('siteexport_aggregate', '1');
			}

			$namespace = $ID;
            foreach( $data as $option ) {
	            
	            list($key, $value) = explode('=', $option);
	            if ($key == "namespace") {
		            $namespace = $value . ':';
		            continue;
	            }
	            
	        	$form->addHidden($key, $value);  
            }
            
            $values = $functions->__getOrderedListOfPagesForID($namespace);
            if ( empty($values) ) {
	            $renderer->doc .= '<span style="color: #a00">'.$this->getLang('NoEntriesFoundHint').'</span>';
            } else {
	            $form->addElement(form_makeMenuField('baseID', $values, isset($_REQUEST['baseID']) ? $_REQUEST['baseID'] : $values[0], $this->getLang('AggragateExportPages')/*, $id='', $class='', $attrs=array() */ ));
	            $form->addElement(form_makeButton('submit', 'siteexport', $this->getLang('AggregateSubmitLabel'), array('class' => 'button download' /*, 'onclick' => 'return (new inet_pdfc_request_license(this)).run();'*/)));
	
		        ob_start();
		        $form->printForm();
		        $renderer->doc .= ob_get_contents();
		        ob_end_clean();
            }
             
            return true;
        }

        return false;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :