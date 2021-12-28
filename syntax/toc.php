<?php
/**
 * Search with Scopes
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_siteexport_toc extends DokuWiki_Syntax_Plugin {

    private $insideToc = false;
    private $savedToc = array();
    private $options = array();

    private $mergedPages = array();
    private $includedPages = array();
    private $merghintIds = array();
    private $mergeHints = array();

    public function getType() { return 'protected'; }
    public function getPType() { return 'block'; }
    public function getAllowedTypes() { return array('container'); }
    public function getSort() { return 100; }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('<toc>(?=.*?</toc>)', $mode, 'plugin_siteexport_toc');
        $this->Lexer->addEntryPattern('<toc .+?>(?=.*?</toc>)', $mode, 'plugin_siteexport_toc');
        $this->Lexer->addSpecialPattern("\[\[.+?\]\]", $mode, 'plugin_siteexport_toc');
    }

    public function postConnect() {
        $this->Lexer->addExitPattern('</toc.*?>', 'plugin_siteexport_toc');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler) {
        global $ID, $INFO;

        switch ($state) {
            case DOKU_LEXER_ENTER:

                $this->insideToc = true;
                $this->options = explode(' ', substr($match, 5, -1)?:"");
                return array('start' => true, 'pos' => $pos, 'options' => $this->options);

            case DOKU_LEXER_SPECIAL:

                if ($this->insideToc) {

                    $link = preg_replace(array('/^\[\[/', '/\]\]$/u'), '', $match);
                    // Split title from URL
                    $link = explode('|', $link, 2);
                    if (!isset($link[1])) {
                        $link[1] = NULL;
                    } else if (preg_match('/^\{\{[^\}]+\}\}$/', $link[1])) {
                        // If the title is an image, convert it to an array containing the image details
                        $link[1] = Doku_Handler_Parse_Media($link[1]);
                    }
                    $link[0] = trim($link[0]);

                    if (!(preg_match('/^[a-zA-Z0-9\.]+>{1}.*$/u', $link[0]) ||
                    preg_match('/^\\\\\\\\[\w.:?\-;,]+?\\\\/u', $link[0]) ||
                    preg_match('#^([a-z0-9\-\.+]+?)://#i', $link[0]) ||
                    preg_match('<' . PREG_PATTERN_VALID_EMAIL . '>', $link[0]) ||
                    preg_match('!^#.+!', $link[0]))
                    ) {

                        // Get current depth from call stack
                        $depth = 1;
                        if ($handler->CallWriter instanceof Doku_Handler_List) {

                            $calls = array_reverse($handler->CallWriter->calls);
                            $call = $calls[0];
                            foreach ($calls as $item) {
                                if (in_array($item[0], array('list_item', 'list_open'))) { $call = $item; break; }
                            }

                            $listType = null;
                            $depth = $handler->CallWriter->interpretSyntax($call[1][0], $listType)-1; // Minus one because of plus one inside the interpret function
                        }

                        if (empty($link[0])) { break; } // No empty elements. This would lead to problems
                        return array($link[0], $link[1], $depth);
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

                $handler->_addCall('cdata', array($match), $pos);

                return false;
            case DOKU_LEXER_EXIT:

                $this->insideToc = false;
                return 'save__meta';
        }
        return false;
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
        global $ID, $lang, $INFO;

        list($SID, $NAME, $DEPTH) = $data;

        $exists = null;
        resolve_pageid(getNS($ID), $SID, $exists);
//        $SID = cleanID($SID); // hier kein cleanID, da sonst moeglicherweise der anker verloren geht

        //    Render XHTML and ODT
        if ($mode != 'metadata' ) {

            // TOC Title
            if (is_array($data) && $data['start'] == true) {

                if (is_Array($data['options'])) {
                    foreach ($data['options'] as $opt) {
                        switch ($opt) {
                            case 'description' : $renderer->meta['sitetoc']['showDescription'] = true; break;
                            case 'notoc' : $renderer->meta['sitetoc']['noTOC'] = true; break;
                            case 'merge' : $renderer->meta['sitetoc']['mergeDoc'] = true; break;
                            case 'nohead' : $renderer->meta['sitetoc']['noTocHeader'] = true; break;
                            case 'mergeheader' : $renderer->meta['sitetoc']['mergeHeader'] = true; break;
                            case 'pagebreak' : $renderer->meta['sitetoc']['pagebreak'] = true; break;
                            case 'mergehint' : $renderer->meta['sitetoc']['mergehint'] = true; break;
                        }
                    }
                }

                $renderer->section_open("1 sitetoc");
                if ($renderer->meta['sitetoc']['noTocHeader'] === false) {
                    $renderer->header($lang['toc'], 1, $data['pos']);
                }

                return true;
            } else

            // All Output has been done
            if (!is_array($data) && $data == 'save__meta') {

                // Close TOC
                $renderer->section_close();

                if ($renderer->meta['sitetoc']['noTOC'] === true) {
                    $renderer->doc = preg_replace("/<div.*?sitetoc.*?$/si", "", $renderer->doc);
                }

                // If this is not set, we may have it as Metadata
                if (empty($this->mergedPages) && $renderer->meta['sitetoc']['mergeDoc']) {
                    $toc = $renderer->meta['sitetoc']['siteexportTOC'];

                    if (is_array($toc)) {
                        foreach ($toc as $tocItem) {
                            $this->mergedPages[] = array($tocItem['id'], $tocItem['depth']);
                        }
                    }

                }

                // If there is some data to be merged
                if (count($this->mergedPages) > 0) {

                    $renderer->doc = ''; // Start fresh!

                    $renderer->section_open("1 mergedsite" . ($renderer->meta['sitetoc']['mergehint'] && count($this->mergedPages) > 1 ? ' mergehint' : ''));

                    // Prepare lookup Array
                    foreach ($this->mergedPages as $tocItem) {
                        list($this->includedPages[]) = explode('#', $tocItem[0]);
                    }

                    // Load the instructions
                    $instr = array();
                    foreach ($this->mergedPages as $tocElement) {

                        list($tocItem, $depth) = $tocElement;
                        $file = wikiFN($tocItem);

                        if (@file_exists($file)) {
                            $instructions = p_cached_instructions($file, false, $tocItem); 
                        } else {
                            $instructions = p_get_instructions(io_readWikiPage($file, $tocItem)); 
                        }

                        // Convert Link and header instructions
                        $instructions = $this->_convertInstructions($instructions, $addID = null, $renderer, $depth);

                        if ($renderer->meta['sitetoc']['mergeHeader'] && count($this->mergedPages) > 1) {
                            // get a hint for merged pages
                            if ($renderer->meta['sitetoc']['mergehint']) {
                                // only if the first section is already there
                                $mergeHint = p_get_metadata($tocItem, 'mergehint', METADATA_RENDER_USING_SIMPLE_CACHE);
                                if (empty($mergeHint)) { $mergeHint = p_get_metadata($tocItem, 'thema', METADATA_RENDER_USING_SIMPLE_CACHE); }
                                if (empty($mergeHint)) { $mergeHint = tpl_pagetitle($tocItem, true); }
                                $instructions = $this->_mergeWithHeaders($this->_initialHeaderStructure($instructions), $instructions, 1, $mergeHint);
                            }
                            // Merge
                            $instr = $this->_mergeWithHeaders($instr, $instructions, 1);

                        } else
                        if ($renderer->meta['sitetoc']['pagebreak']) {
                            $sitepagebreak = array(array(
                                'plugin',
                                array(
                                    'siteexport_toctools',
                                    array(
                                        'pagebreak',
                                        null,
                                        null
                                    )
                                )
                            ));
                            $instr = array_merge($instr, $instructions, $sitepagebreak);
                        } else {
                            // Concat
                            $instr = array_merge($instr, $instructions);
                        }
                    }

                    if (!empty($instr)) {
                        if ( $this->_cleanAllInstructions($instr, true) ) {
                            // There are no toc elements, remove the mergesite mergehint
                            $renderer->doc = preg_replace( '/(class=".*?\s)mergedsite/', '\1', $renderer->doc );
                            $renderer->doc = preg_replace( '/(class=".*?\s)mergehint/', '\1', $renderer->doc );
                        }

                        // print "<pre>"; print_r($instr); print "</pre>";
                        $this->_render_output($renderer, $mode, $instr);
                    }

                    $renderer->section_close();
                }
                return true;
            }

            // Save the current ID
            $LNID = $SID;

            // Add ID to flags['mergeDoc']
            if ($renderer->meta['sitetoc']['mergeDoc'] === true) { // || (count($renderer->meta['sitetoc']['siteexportTOC']) > 0 && $renderer->meta['sitetoc']['siteexportMergeDoc'] === true) ) {
                $this->mergedPages[] = array($SID, $DEPTH);
                resolve_pageid(getNS($ID), $SID, $exists);
            } else {
                // // print normal internal link (XHTML odt)
                $renderer->internallink($LNID, $NAME, null);

                // Display Description underneath
                if ($renderer->meta['sitetoc']['showDescription'] === true) {
                    $renderer->cdata(p_get_metadata($SID, 'description abstract', true));
                }
            }

            // Render Metadata
        } else if ($mode == 'metadata') {
            if (!is_array($data) && $data == 'save__meta') {
                $renderer->meta['sitetoc']['siteexportTOC'] = $this->savedToc;

                foreach ($this->savedToc as $page) {
                    $renderer->meta['relation']['references'][$page['id']] = $page['exists'];
                }

                $this->savedToc = array();
            } else if (!isset($data['start']) && !isset($data['pos'])) {
                $this->savedToc[] = $this->__addTocItem($SID, $NAME, $DEPTH, $renderer);
            }
        }

        return true;
    }

    /*
     * pull apart the ID and create an Entry for the TOC
     */
    private function __addTocItem($id, $name, $depth, $renderer) {
        global $conf;
        global $ID;

        // Render Title
        $default = $renderer->_simpleTitle($id);
        $exists = false; $isImage = false; $linktype = null;
        resolve_pageid(getNS($ID), $id, $exists);
        $name = $renderer->_getLinkTitle($name, $default, $isImage, $id, $linktype);

        //keep hash anchor
        list($id, $hash) = explode('#', $id, 2);
        if (!empty($hash)) $hash = $renderer->_headerToLink($hash);

        // Build Sitetoc Item
        $item = array();
        $item['id'] = $id;
        $item['name'] = $name;
        $item['anchor'] = $hash;
        $item['depth'] = $depth;
        $item['exists'] = $exists;
        if (!$conf['skipacl'] && auth_quickaclcheck($item['id']) < AUTH_READ) {
            return false;
        }

        return $item;
    }

    /*
     * Render the output of one page
     */
    private function _render_output($renderer, $mode, $instr) {
        global $ID;

        // Section IDs
        // $addID = sectionID($addID, $check);    //not possible to use a:b:c for id

        if ($mode == 'xhtml') {

            //--------RENDER
            //renderer information(TOC build / Cache used)
            $info = array();
            $content = p_render($mode, $instr, $info);

            //Remove TOC`s, section edit buttons and tags
            $content = $this->_cleanXHTML($content);

            // embed the included page
            // $renderer->doc .= '<div class="include">';
            //add an anchor to find start of a inserted page
            // $renderer->doc .= "<a name='$addID' id='$addID'>";
            $renderer->doc .= $content;
            // $renderer->doc .= '</div>';
        } else {

            // Loop through the instructions
            foreach ($instr as $instruction) {
                // Execute the callback against the Renderer
                call_user_func_array(array($renderer, $instruction[0]), $instruction[1]);
            }
        }
    }

    /*
     * Corrects relative internal links and media and
     * converts headers of included pages to subheaders of the current page
     */
    private function _convertInstructions($instr, $id, &$renderer, $depth = 1) {
        global $ID;
        global $conf;

        $n = count($instr);

        for ($i = 0; $i < $n; $i++) {
            //internal links(links inside this wiki) an relative links
            if ((substr($instr[$i][0], 0, 12) == 'internallink')) {
                $this->_convert_link($renderer, $instr[$i], $id);
            }
            else if ((substr($instr[$i][0], 0, 13) == 'internalmedia')) {
                $this->_convert_media($renderer, $instr[$i], $id);
            }
            else if ((substr($instr[$i][0], 0, 6) == 'header')) {
                $this->_convert_header($renderer, $instr[$i], $depth-1); // -1 because the depth starts at 1
            }
            else if ((substr($instr[$i][0], 0, 12) == 'section_open')) {
                $this->_convert_section($renderer, $instr[$i], $depth-1); // -1 because the depth starts at 1
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
    private function _convert_link(&$renderer, &$instr, $id) {
        global $ID;

        $exists = false;

        resolve_pageid(getNS($id), $instr[1][0], $exists);
        list($pageID, $pageReference) = explode("#", $instr[1][0], 2);

        if (in_array($pageID, $this->includedPages)) {
            // Crate new internal Links
            $check = null;

            // Either get existing reference or create from first heading. If still not there take the alternate ID
            $pageNameLink = empty($pageReference) ? sectionID($pageID, $check) : $pageReference;

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
    private function _convert_media(&$renderer, &$instr, $id) {
        global $ID;

        // Resolvemedia returns the absolute path to media by reference
        $exists = false;
        resolve_mediaid(getNS($id), $instr[1][0], $exists);
    }

    /**
     * @param integer $depth
     */
    private function _convert_header(&$renderer, &$instr, $depth) {
        // More Depth!
        $instr[1][1] += $depth;
    }

    /**
     * @param integer $depth
     */
    private function _convert_section(&$renderer, &$instr, $depth) {
        // More Depth!
        $instr[1][0] += $depth;
    }

    private function _mergeWithHeaders($existing, $newInstructions, $level = 1, $mergeHint = array()) {

        $returnInstructions = array();
        $preparedInstructions = array();
        $existingStart = $existingEnd = 0;
        $firstRun = true;

        while ($this->_findNextHeaderSection($existing, $level, $existingStart, $existingEnd)) {

            if ($firstRun) {
                $returnInstructions = array_merge($returnInstructions, array_slice($existing, 0, $existingStart));
                $firstRun = false;
            }

            $currentSlice = array_slice($existing, $existingStart, $existingEnd-$existingStart);

            // Find matching part with headername
            $newStart = $newEnd = 0;
            if ($this->_findNextHeaderSection($newInstructions, $level, $newStart, $newEnd, $currentSlice[0][1][0])) {

                $newSlice = array_slice($newInstructions, $newStart, $newEnd-$newStart);
                if ($newSlice[0][0] == 'header')
                    array_shift($newSlice); // Remove Heading

                // merge found parts on next level.
                $returnedInstructions = $this->_mergeWithHeaders($currentSlice, $newSlice, $level+1, $mergeHint);

                // Put them at the end!
                $preparedInstructions = array_merge($preparedInstructions, $returnedInstructions);

                // Remove from input
                array_splice($newInstructions, $newStart, $newEnd-$newStart);
            } else {
                // Nothing else found
                $preparedInstructions = array_merge($preparedInstructions, $currentSlice);
            }

            $existingStart = $existingEnd;
        }

        // Append the rest
        $returnInstructions = array_merge($returnInstructions, array_slice($existing, $existingStart));

        // Check for section close inconsistencies and put one at the very end ...
        $section_postpend = array();
        if ( 
            ( 
                ($tmp1 = array_slice($newInstructions, -1))
                && ($tmp1[0][0] == 'section_close')
            )
            && 
            (
                ($tmp2 = array_slice($newInstructions, -2))
                && ($tmp2[0][0] == 'section_close')
            )
        ) {
            $section_postpend = array_splice($newInstructions, -1);
        }        
        if (
            ( 
                ($tmp3 = array_slice($returnInstructions, -1))
                && ($tmp3[0][0] == 'section_close')
            )
            && 
            (
                ($tmp4 = array_slice($returnInstructions, -2))
                && ($tmp4[0][0] == 'section_close')
            )
        ) {
            $section_postpend = array_merge($section_postpend, array_splice($returnInstructions, -1));
        }

        // What if there are headings left inside the $newInstructions?????
        // Find matching part with headername
        $newStart = $newEnd = 0;
        $section_prepend = array();
        if ($this->_findNextHeaderSection($newInstructions, $level, $newStart, $newEnd)) {
            // If there are header in here, build a prepend and have the rest at the end
            $section_prepend = array_splice($newInstructions, 0, $newStart);
        } else {
            // If not, prepend all of it.
            $section_prepend = $newInstructions;
            $newInstructions = array();
        }

        $this->_insertMergeHint($section_prepend, $mergeHint);

        $returnInstructions = array_merge($returnInstructions, $section_prepend, $preparedInstructions, $newInstructions, $section_postpend);

        return $returnInstructions;
    }

    /**
     * @param integer $level
     */
    private function _findNextHeaderSection($section, $level, &$start, &$end, $headerName = null) {

        $inCount = count($section);
        $currentSlice = -1;

        // Find Level 1 Header that matches.
        for ($i = $start; $i < $inCount; $i++) {

            $instruction = $section[$i];
            $end = $i; // Or it will be lost and a section close will be missing.

            // First Level Header
            if ($instruction[0] == 'header' && $instruction[1][1] == $level) {

                if ($currentSlice > 0) {
                    return true;
                }

                if ($headerName == null || ($headerName == $instruction[1][0])) {
                    // Begin of new slice ...
                    $start = $currentSlice = $i;
                }
            }
        }

        // Nothing found
        $end = $i; // Or it will be lost and a section close will be missing.
        return $currentSlice > 0;
    }

    private function _cleanAllInstructions(&$instr, $advanced=false) {
        $this->_cleanInstructions($instr, '/p_(close|open)/');
        $this->_cleanInstructions($instr, '/section_(close|open)/');
        $this->_cleanInstructions($instr, '/listu_(close|open)/');
        $this->_cleanInstructions($instr, '/listo_(close|open)/');
        
        if ( !$advanced ) {
            return false;
        }

        $currentMergeHint = null;
        $listOfMergeHintNames= [];

        for( $i=0; $i<count($instr); $i++ ) {
            
            $hasMoreEntries = count($instr)-1 > $i;

            if ( $instr[$i][0] == 'header' ) {
                // reset after header
                $currentMergeHint = null;
            }
            
            if ( $instr[$i][1][0] == 'siteexport_toctools' && $instr[$i][1][0][0] != 'pagebreak' ) {
                if ( $currentMergeHint != null && $instr[$i][1][1][2] == $currentMergeHint[1][1][2] ) {
                    
                    if ( $instr[$i][1][1][1] == 'end' ) {
                        // look ahead, if the next hint is also the same ID, if so: remove this ending hint.
                        $shouldSpliceAway = false;
                        for( $ii=$i+1; $ii<count($instr); $ii++ ) {
                            if ( $instr[$ii][0] == 'header' ) {
                                // Jumping over a section now ... we have to leave the last entry
                                break;
                            } else if ( $instr[$ii][1][0] == 'siteexport_toctools' && $instr[$ii][1][0][0] != 'pagebreak' ) {
                                if ( $instr[$ii][1][1][2] == $currentMergeHint[1][1][2] && $instr[$ii][1][1][1] == 'start' ) {
                                    // Found another one, that is identicall - so this will be removed.
                                    // also remove the current ending element
                                    $shouldSpliceAway = true;
                                }
                                
                                // Okay, this was a toctools whatever ... but maybe not a start of the same type.
                                // we're done.
                                break;
                            }
                        }
                        
                        if ( !$shouldSpliceAway ) {
                            // print "<pre>NOT Splicing away ". print_r($instr[$i], true) . "</pre>";
                            continue;
                        }
                        // print "<pre>Splicing away ". print_r($instr[$i], true) . "</pre>";
                    }
                    
                    // print "<p>Removing 'mergehint' in between  </p>";
                    array_splice($instr, $i--, 1);
                } else {
                    // print "<p>Resetting Mergehint '" . $instr[$i][1][1][2] . "' == '" . $currentMergeHint[1][1][2] . "'</p>";
                    $currentMergeHint = $instr[$i];
                    $listOfMergeHintNames[] = $instr[$i][1][1][2];
                }
            }
        }

/*
        print "<pre>" . print_r($instr, 1) . "</pre>";

//*/

        // There is only ONE distinct mergehint -> remove all
        $listOfMergeHintNames = array_unique($listOfMergeHintNames);
        if ( count($listOfMergeHintNames) == 1 ) {
            for( $i=0; $i<count($instr); $i++ ) {
                if ( $instr[$i][1][0] == 'siteexport_toctools' && $instr[$i][1][0][0] != 'pagebreak' ) {
                    array_splice($instr, $i--, 1);
                }
            }
        }

        return count($listOfMergeHintNames) == 1;
    }

    /**
     * @param string $tag
     */
    private function _cleanInstructions(&$instructions, $tag) {


/*
        print "<pre>";
        print "$tag ->\n";
        print_r($instructions);
        print "</pre>";
//*/
        $inCount = count($instructions);
        for ($i = 0; $i < $inCount; $i++) {

            // Last instruction
            if ($i == $inCount-1) {
                break;
            }

            if (preg_match($tag, $instructions[$i][0]) && preg_match($tag, $instructions[$i+1][0]) && $instructions[$i][0] != $instructions[$i+1][0]) {
/*
        print "<pre>";
        print "Removed ->\n";
        print_r($instructions[$i-1]);
        print "---\n";
        print_r($instructions[$i]);
        print_r($instructions[$i+1]);
        print "---\n";
        print_r($instructions[$i+2]);
        print "</pre>";
//*/

                // found different tags, but both match the expression and follow each other - so they can be elliminated
                array_splice($instructions, $i, 2);
                $inCount -= 2;
                $i--;
            }
        }
/*
        print "<pre>";
        print "$tag ->\n";
        print_r($instructions);
        print "</pre>";
//*/
    }
    
    /**
     * Strip everything except for the headers
     */
    private function _initialHeaderStructure($instructions) {
        $inCount = count($instructions);
        for ($i = 0; $i < $inCount; $i++) {

            // Last instruction
            if ($i == $inCount-1) {
                break;
            }

            if (!in_array($instructions[$i][0], array('header', 'section_open', 'section_close', 'p_open', 'p_close'))) {
                // found non-matching
                array_splice($instructions, $i, 1);
                $inCount--;
                $i--;
            }
        }
        return $instructions;
    }

    private function _insertMergeHint(&$instructions, $mergeHint) {

        // Surround new slice with a mergehint
        if (empty($mergeHint)) { return; }

        // No emtpy insruction sets.
        $this->_cleanAllInstructions($instructions);

        if (empty($instructions)) { return; }

        $mergeHintPrepend = $this->_toctoolPrepends( $instructions );

        // only section content should be surrounded.
        if ($instructions[0][0] != 'section_open') { return; }

        // save for later use
        $mergeHintId = sectionid($mergeHint, $this->mergeHints);
        $this->merghintIds[$mergeHintId] = $mergeHint;

        // Insert section information
        array_push( $mergeHintPrepend, array(
            'plugin',
            array(
                'siteexport_toctools',
                array(
                    'mergehint',
                    'start',
                    $mergeHint,
                    $mergeHintId
                )
            )
        ) );

        $mergeHintPostpend = array(array(
            'plugin',
            array(
                'siteexport_toctools',
                array(
                    'mergehint',
                    'end',
                    $mergeHint
                )
            )
        ));

        $instructions = array_merge($mergeHintPrepend, $instructions, $mergeHintPostpend);
/*
        print "<pre>"; print_r($instructions); print "</pre>"; 
//*/
    }
    
    private function _toctoolPrepends( &$instructions ) {

        $mergeHintPrependPrepend = array();
        
        // 2021-01-14 This did no good - if a merged page had two mergehints, the first was stripped.
/*
        if ( $instructions[0][0] == 'plugin' && $instructions[0][1][0] == 'siteexport_toctools' && $instructions[0][1][1][1] == 'start' ) {

            // This is already section merge hint ... but it will have a section at its end ... hopefully
            do {
                $_instructions = array_shift( $instructions );
                array_push( $mergeHintPrependPrepend, $_instructions);
            } while( !($_instructions[0] == 'plugin' && $_instructions[1][0] == 'siteexport_toctools' && $_instructions[1][1][1] == 'end' ) ) ;
            array_splice($mergeHintPrepend, 0, 0, $mergeHintPrependPrepend);
        }
//*/
/*
        print "<pre>"; print_r($instructions); print "</pre>"; 
//*/
        return $mergeHintPrependPrepend;
    }

    /**
     * Remove TOC, section edit buttons and tags
     */
    private function _cleanXHTML($xhtml) {
        $replace = array(
            '!<div class="toc">.*?(</div>\n</div>)!s' => '', // remove TOCs
            '#<!-- SECTION \[(\d*-\d*)\] -->#s'       => '', // remove section edit buttons
            '!<div id="tags">.*?(</div>)!s'           => ''  // remove category tags
        );
        $xhtml = preg_replace(array_keys($replace), array_values($replace), $xhtml);
        return $xhtml;
    }

    /**
     * Allow the plugin to prevent DokuWiki creating a second instance of itself
     *
     * @return bool   true if the plugin can not be instantiated more than once
     */
    public function isSingleton() {
        return true;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
