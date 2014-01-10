<?php
/**
 * Search with Scopes
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_siteexport_toc extends DokuWiki_Syntax_Plugin {

	var $insideToc = false;
	var $savedToc = array();

	var $mergedPages = array();
	var $includedPages = array();

	function getType() { return 'protected'; }
	function getPType() { return 'block'; }
	function getAllowedTypes() { return array('container'); }
	function getSort() { return 100; }

	/**
	 * for backward compatability
	 * @see inc/DokuWiki_Plugin#getInfo()
	 */
    function getInfo(){
        if ( method_exists(parent, 'getInfo')) {
            $info = parent::getInfo();
        }
        return is_array($info) ? $info : confToHash(dirname(__FILE__).'/../plugin.info.txt');
    }
	
	/**
	 * Connect pattern to lexer
	 */
	function connectTo($mode) {
		$this->Lexer->addEntryPattern('<toc>(?=.*?</toc>)',$mode,'plugin_siteexport_toc');
		$this->Lexer->addEntryPattern('<toc .+?>(?=.*?</toc>)',$mode,'plugin_siteexport_toc');
		$this->Lexer->addSpecialPattern("\[\[.+?\]\]",$mode,'plugin_siteexport_toc');
	}

	function postConnect() {
		$this->Lexer->addExitPattern('</toc.*?>', 'plugin_siteexport_toc');
	}

	function handle($match, $state, $pos, &$handler) {
		global $ID, $INFO;

		switch ($state) {
			case DOKU_LEXER_ENTER:

				$this->insideToc = true;

				$options = explode(' ', substr($match, 5, -1));
				return array('start' => true, 'pos' => $pos, 'options' => $options);
				break;

			case DOKU_LEXER_SPECIAL:

				if ( $this->insideToc ) {

					$link = preg_replace(array('/^\[\[/','/\]\]$/u'),'',$match);
					// Split title from URL
					$link = explode('|',$link,2);
					if ( !isset($link[1]) ) {
						$link[1] = NULL;
					} else if ( preg_match('/^\{\{[^\}]+\}\}$/',$link[1]) ) {
						// If the title is an image, convert it to an array containing the image details
						$link[1] = Doku_Handler_Parse_Media($link[1]);
					}
					$link[0] = trim($link[0]);

					if ( ! (preg_match('/^[a-zA-Z0-9\.]+>{1}.*$/u',$link[0]) ||
					preg_match('/^\\\\\\\\[\w.:?\-;,]+?\\\\/u',$link[0]) ||
					preg_match('#^([a-z0-9\-\.+]+?)://#i',$link[0]) ||
					preg_match('<'.PREG_PATTERN_VALID_EMAIL.'>',$link[0]) ||
					preg_match('!^#.+!',$link[0]) )
					) {

						// Get current depth from call stack
						$depth = 1;
						if ( $handler->CallWriter instanceof Doku_Handler_List ) {

							$calls = array_reverse($handler->CallWriter->calls);
							$call = $calls[0];
							foreach ( $calls as $item ) {
								if ( in_array( $item[0], array( 'list_item', 'list_open') ) ) { $call = $item; break;}
							}

							$depth = $handler->CallWriter->interpretSyntax($call[1][0], $listType);

						}

						if ( empty( $link[0] ) ) { break; } // No empty elements. This would lead to problems
						return array($link[0], $link[1], $depth);
						break;
					} else {
						// use parser! - but with another p
						$handler->internallink($match, $state, $pos);
					}
				} else {
					// use parser!
					$handler->internallink($match, $state, $pos);
				}

				return false;
			case DOKU_LEXER_UNMATCHED:

				$handler->_addCall('cdata',array($match), $pos);
				return false;
				break;
			case DOKU_LEXER_EXIT:

				$this->insideToc = false;
				return 'save__meta';
				break;
		}
		return false;
	}

	function render($mode, &$renderer, $data) {
		global $ID, $lang, $INFO;

		list( $SID, $NAME, $DEPTH ) = $data;
		
		resolve_pageid(getNS($ID),$SID,$exists);
//		$SID = cleanID($SID); // hier kein cleanID, da sonst mšglicherweise der anker verloren geht

        //    Render XHTML and ODT
		if ($mode == 'xhtml' || $mode == 'odt') {

		    // TOC Title
			if ( isset($data['start']) ) {
			    
			    if ( is_Array($data['options']) ) {
                    foreach( $data['options'] as $opt ) {
    					switch( $opt ) {
    						case 'description' : $renderer->meta['sitetoc']['showDescription'] = true; break;
    						case 'notoc' : $renderer->meta['sitetoc']['noTOC'] = true; break;
    						case 'merge' : $renderer->meta['sitetoc']['mergeDoc'] = true; break;
    						case 'nohead' : $renderer->meta['sitetoc']['noTocHeader'] = true; break;
    					}
    				}
			    }
				
				$renderer->section_open("1 sitetoc");
				if ( $renderer->meta['sitetoc']['noTocHeader'] === false ) {
					$renderer->header($lang['toc'], 1, $data['pos']);
				}

				return true;
			}

			// All Output has been done
			if ( !is_array($data) && $data == 'save__meta' ) {

				// Close TOC
				$renderer->section_close();
				
				if ( $renderer->meta['sitetoc']['noTOC'] === true ) {
					$renderer->doc = preg_replace("/<div.*?sitetoc.*?$/si", "", $renderer->doc);
				}

				// If this is not set, we may have it as Metadata
				if ( !$this->mergedPages && $renderer->meta['sitetoc']['mergeDoc'] ) {
					$toc = $renderer->meta['sitetoc']['siteexportTOC'];
					if ( is_array($toc)) {
						foreach ($toc as $tocItem ) {
							$this->mergedPages[] = $tocItem['id'];
						}
					}
				}

				// If there is some data to be merged
				if ( count($this->mergedPages) > 0) {
				
					$renderer->section_open("1 mergedsite");

					// Prepare lookup Array
					foreach ( $this->mergedPages as $tocItem ) {
						$this->includedPages[] = array_shift(explode('#', $tocItem));
					}

					// Print merged pages
					foreach ( $this->mergedPages as $tocItem ) {
						$this->_render_output($renderer,$tocItem, $mode);
					}

					$renderer->section_close();
				}
				return true;
			}

			// Save the current ID
			$LNID = $SID;

			// Add ID to flags['mergeDoc']
			if ( $renderer->meta['sitetoc']['mergeDoc'] === true ) { // || (count($renderer->meta['sitetoc']['siteexportTOC']) > 0 && $renderer->meta['sitetoc']['siteexportMergeDoc'] === true) ) {
				$this->mergedPages[] = $SID;
				$default = $renderer->_simpleTitle($SID); $isImage = false;
				resolve_pageid(getNS($ID),$SID,$exists);

				$NAME = empty($NAME) ? p_get_first_heading($SID,true) : $NAME;
				$LNID = "$ID#" . sectionID($SID, $check);
			}

			// Print normal internal link (XHTML odt)
			$renderer->internallink($LNID, $NAME, null);
			
			// Display Description underneath
			if ( $renderer->meta['sitetoc']['showDescription'] === true ) {
				// $renderer->p_open();
				$renderer->cdata(p_get_metadata($SID, 'description abstract', true));
				// $renderer->p_close();
			}
			
			// Render Metadata
		} else if ($mode == 'metadata') {
			if ( !is_array($data) && $data == 'save__meta' ) {
				$renderer->meta['sitetoc']['siteexportTOC'] = $this->savedToc;
				
                foreach ($this->savedToc as $page) {
                    $renderer->meta['relation']['references'][$page['id']] = $page['exists'];
                }
				
				$this->savedToc = array();
			} else if ( !isset($data['start']) && !isset($data['pos']) ) {
				$this->savedToc[] = $this->__addTocItem($SID, $NAME, $DEPTH, $renderer);
			}
		} else {
			return false;
		}

		return true;
	}

	/*
	 * pull apart the ID and create an Entry for the TOC
	 */
	function __addTocItem($id, $name, $depth, $renderer) {
		global $conf;
		global $ID;

		// Render Title
		$default = $renderer->_simpleTitle($id);
		$exists = false; $isImage = false; $linktype = null;
		resolve_pageid(getNS($ID),$id,$exists);
		$name = $renderer->_getLinkTitle($name, $default, $isImage, $id, $linktype);

		//keep hash anchor
		list($id,$hash) = explode('#',$id,2);
		if(!empty($hash)) $hash = $renderer->_headerToLink($hash);

		// Build Sitetoc Item
		$item = array();
		$item['id'] = $id;
		$item['name'] = $name;
		$item['anchor'] = $hash;
		$item['depth'] = $depth;
		$item['exists'] = $exists;
		if(!$conf['skipacl'] && auth_quickaclcheck($item['id']) < AUTH_READ){
			return false;
		}

		return $item;
	}

	/*
	 * Render the output of one page
	 */
	function _render_output($renderer, $addID, $mode) {
		global $ID;

		//get data(in instructions format) from $file (dont use cache: false)
		$file    = wikiFN($addID);
		$instr   = p_cached_instructions($file, false);

		//page was empty
		if (empty($instr)) {
			return;
		}

		// Convert Link instructions
		$instr   = $this->_convertInstructions($instr, $addID, $renderer);


		// Section IDs
		$check = null;
		$addID = sectionID($addID, $check);	//not possible to use a:b:c for id

		if ( $mode == 'xhtml' ) {
			//--------RENDER
			//renderer information(TOC build / Cache used)
			$info = array();
			$content = p_render($mode, $instr, $info);

			//Remove TOC`s, section edit buttons and tags
			$content = $this->_cleanXHTML($content);


			// embed the included page
			$renderer->doc .= '<div class="include">';
			//add an anchor to find start of a inserted page
			$renderer->doc .= "<a name='$addID' id='$addID'>";
			$renderer->doc .= $content;
			$renderer->doc .= '</div>';
		} else if ( $mode == 'odt') {

			$renderer->doc .= '<text:bookmark text:name="'.$addID.'"/>';

			// Loop through the instructions
			foreach ( $instr as $instruction ) {
				// Execute the callback against the Renderer
				call_user_func_array(array($renderer, $instruction[0]),$instruction[1]);
			}
		}
	}


	/*
	 * Corrects relative internal links and media and
	 * converts headers of included pages to subheaders of the current page
	 */
	function _convertInstructions($instr, $id, &$renderer) {
		global $ID;
		global $conf;

		$n = count($instr);

		for ($i = 0; $i < $n; $i++){
			//internal links(links inside this wiki) an relative links
			if((substr($instr[$i][0], 0, 12) == 'internallink')){
				$this->_convert_link($renderer,$instr[$i],$id);
			}
			else if((substr($instr[$i][0], 0, 13) == 'internalmedia')){
				$this->_convert_media($renderer,$instr[$i],$id);
			}
		}

		//if its the document start, cut off the first element(document information)
		if ($instr[0][0] == 'document_start')
		return array_slice($instr, 1, -1);
		else
		return $instr;
	}


	/*
	 * Convert link of given instruction
	 */
	function _convert_link(&$renderer,&$instr,$id) {
		global $ID;

		$exists = false;

		resolve_pageid(getNS($id),$instr[1][0],$exists);
		list( $pageID, $pageReference ) = explode("#", $instr[1][0], 2);

		if ( in_array($pageID, $this->includedPages) ) {
			// Crate new internal Links
			$check = null;

			// Either get existing reference or create from first heading. If still not there take the alternate ID
			$pageNameLink = empty( $pageReference ) ? sectionID($pageID,$check) : $pageReference;

			$instr[1][0] = $ID . "#" . $pageNameLink;

		} else {
			// Convert external Links to plain Text

			$instr = array(
						"cdata",
			array($instr[1][1]),
			$instr[2]
			);
		}
	}

	/*
	 * Convert internalmedia of given instruction
	 */
	function _convert_media(&$renderer,&$instr,$id) {
		global $ID;

		// Resolvemedia returns the absolute path to media by reference
		$exists = false;
		resolve_mediaid(getNS($id),$instr[1][0],$exists);
	}

	/**
	 * Remove TOC, section edit buttons and tags
	 */
	function _cleanXHTML($xhtml){
		$replace  = array(
			'!<div class="toc">.*?(</div>\n</div>)!s' => '', // remove TOCs
			'#<!-- SECTION \[(\d*-\d*)\] -->#e'       => '', // remove section edit buttons
			'!<div id="tags">.*?(</div>)!s'           => ''  // remove category tags
		);
		$xhtml  = preg_replace(array_keys($replace), array_values($replace), $xhtml);
		return $xhtml;
	}


	/**
	 * Allow the plugin to prevent DokuWiki creating a second instance of itself
	 *
	 * @return bool   true if the plugin can not be instantiated more than once
	 */
	function isSingleton() {
		return true;
	}
}
// vim:ts=4:sw=4:et:enc=utf-8:
