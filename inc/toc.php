<?php 

if (!defined('DOKU_PLUGIN')) die('meh');

class siteexport_toc
{
    private $emptyNSToc = true;
    private $functions = null;
    private $NS = null;
    public $translation = null;
    
    public function __construct($functions, $NS)
    {
        $this->doDebug = !empty($_REQUEST['tocDebug']);
        $this->emptyNSToc = !empty($_REQUEST['emptyTocElem']);
        $this->functions = $functions;
        $this->NS = $NS;
    }
    
    private function isNotEmpty( $val ) {
        return !empty($val);
    }
    
    private function shortenByTranslation(&$inputURL, $deepSearch = false)
    {
        // Mandatory: we allways want '/' insteadf of ':' here
        $inputURL = str_replace(':', '/', $inputURL);

        $checkArray = $this->translation ? $this->translation->translations : array(noNS($this->NS));
        
        $url = explode('/', $inputURL);

        $URLcount = count($url);
        for ($i = 0; $i < $URLcount ; $i++)
        {
            if (in_array($url[$i], $checkArray))
            {
                // Rauswerfen und weg
                $url[$i] = '';
                break;
            }
            
            if (!$deepSearch)
            {
                break;
            }

            // Ok, remove anyway
            $url[$i] = '';
        }
        
        $inputURL = implode('/', $url);
        $inputURL = preg_replace("$\/+$", "/", $inputURL);
        
        if (strlen($inputURL) > 0 && substr($inputURL, 0, 1) == '/')
        {
            $inputURL = substr($inputURL, 1);
        }
        
        return $inputURL;
    }
    
    /**
     * Build the Java Documentation TOC XML
     **/
    public function __getJavaHelpTOCXML($DATA) {

        if (count($DATA) == 0) {
            return false;
        }
        
        $this->debug("#### STARTING ####");
        $TOCXML = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<toc>";
        $MAPXML = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<map version=\"1.0\">";

        // Go through the pages
        $CHECKDATA = array();
        $nData = $DATA;
        $DATA = array();
        $check = array();
        $startPageID = null;

        foreach ( $nData as $elem )
        {
            // Check if available
            $anchor = ( !empty($elem['anchor']) ? '#' . $elem['anchor'] : '' );
            $elem['url'] = $this->functions->getSiteName($elem['id'], true); // Override - we need a clean name
            $elem['mapURL'] = $elem['url'];
            $this->shortenByTranslation($elem['url']);
            
            // only add an url once
            if ( in_array($elem['url'], $CHECKDATA) ) { continue; }

            if ( !isset($elem['exists']) ) {
                resolve_pageid(getNS($elem['id']),$elem['id'],$elem['exists']);
                $this->functions->debug->message("EXISTS previously not set.", $elem, 1);
            }

            // if not there, no map ids will be generated
            $elem['mapID'] = intval($elem['exists']) == 1 ? $this->functions->getMapID($elem['id'], $elem['anchor'], $check) : array();
            $elem['tags'] = explode(' ', p_get_metadata($elem['id'], 'context tags', true)); // thats from the tag plugin
            $elem['tags'] = array_filter($elem['tags'], array($this, 'isNotEmpty'));
            $elem['tags'] = array_map(array($this->functions, 'cleanId'), $elem['tags']);

            if ( empty($elem['depth']) ) {
                $elem['depth'] = count(explode('/', $elem['url']));
            }
            $CHECKDATA[] = $elem['url'];
            
            if ( $startPageID == null )
            {
                $startPageID = $elem['mapID'][0];
            }
            
            if ( empty( $elem['name'] ) || $elem['name'] == noNs($elem['id']) ) {
                $elem['name'] = $this->functions->getSiteTitle($elem['id']);
                
                if ( is_array($elem['mapID']) && empty( $elem['mapID'] ) ) {
                    array_push($elem['mapID'], noNs($elem['id']));
                }
                
                $this->debug("no name, get site title " . $elem['name']);
                $this->debug($elem);
            }

            // Go on building mapXML
            $this->shortenByTranslation($elem['mapURL'], true); // true to already remove all language stuff - false if not
            foreach ( $elem['mapID'] as $VIEWID ) {
                $MAPXML .= "\n\t<mapID target=\"" . $VIEWID . "\" url=\"" . $elem['mapURL'] . $anchor . "\"/>";
            }

            $elem['tocNS'] = getNS(cleanID($elem['url']));
            $elem['tocNS'] = $this->shortenByTranslation($elem['tocNS'], true);
            $elem['tocNS'] = strlen($elem['tocNS']) > 0 ? explode('/', $elem['tocNS']) : array();
            $this->functions->debug->message("This will be the TOC elements data:", $elem, 1);

            $this->__buildTOCTree($DATA, $elem['tocNS'], $elem);
        }

        $this->debug("#### Writing TOC Tree ####");
        $TOCXML .= $this->__writeTOCTree($DATA) . "\n</toc>";
        $this->debug("#### DONE: Writing TOC Tree ####");
        $MAPXML .= "\n</map>";

        $this->debug($DATA);
        $this->debug($TOCXML);
        $this->debug($MAPXML);

        return array($TOCXML, $MAPXML, $startPageID);
    }
    
    /**
     * Prepare the TOC Tree
     **/
    private function __buildTOCTree(&$DATA, $currentNSArray, $elemToAdd)
    {
        global $conf;
    
        // Actual level
        if (empty($currentNSArray)) {
            $elemToAdd['isStartPage'] = noNS($elemToAdd['id']) == $conf['start'];
            // $key = empty($elemToAdd['name']) || 1==1 ? noNS($elemToAdd['id']) : $elemToAdd['name'];
            $key = noNS($elemToAdd['id']);
            $DATA[$key] = $elemToAdd;
            return;
        }
        
        $currentLevel = array_shift($currentNSArray);
        $nextLevel = &$DATA[$currentLevel];
        if (empty($nextLevel)) {
            $nextLevel = array('pages' => array());
        } else {
            $nextLevel = &$DATA[$currentLevel]['pages'];
        }
        
        $this->__buildTOCTree($nextLevel, $currentNSArray, $elemToAdd);
    }
    
    /**
     * Create a single TOC Item
     **/
    private function __TOCItem($item, $depth, $selfClosed = true)
    {
        $this->debug("creating toc item");
        $this->debug($item);
        $targetID = $item['mapID'][0];
        if (empty($targetID)) {
            $targetID = $this->functions->cleanID($item['name']);
            $this->debug("no map ID, using: " . $targetID);
        }
        return "\n" . str_repeat("\t", max($depth, 0)+1) . "<tocitem target=\"" . $targetID . "\"" . (intval($item['exists']) == 1 ? " text=\"" . $item['name'] . "\"" : "") . ( array_key_exists('tags', $item) && !empty($item['tags']) ? " tags=\"" . implode(' ', $item['tags']) . "\"": "")  . ($selfClosed ? '/' : '') . ">";
    }
    
    /**
     * Create a single TOC Item
     **/
    private function __TOCItemClose($depth)
    {
        return "\n" . str_repeat("\t", max($depth, 0)+1) . "</tocitem>";
    }

    /**
     * Write the whole TOC TREE
     **/
    private function __writeTOCTree($CURRENTNODE, $CURRENTNODENAME = null, $DEPTH = 0) {
        global $conf;
    
        $XML = '';
        $didOpenItem = false;
        if (!is_array($CURRENTNODE) || empty($CURRENTNODE))
        {
            // errr … no.
            return $XML;
        }

        // This is an element!        
        if (!empty($CURRENTNODE['id']) && empty($CURRENTNODE['pages']))
        {
            // This has to be an item - only -!
            return $this->__TOCItem($CURRENTNODE, $DEPTH);
        }

        // Look for start page
        if (!empty($CURRENTNODE[$conf['start']]))
        {
            // YAY! StartPage found.
            $didOpenItem = !(count(empty($CURRENTNODE['pages']) ? $CURRENTNODE : $CURRENTNODE['pages']) == 0);
            $XML .= $this->__TOCItem($CURRENTNODE[$conf['start']], $DEPTH, !$didOpenItem);
            unset($CURRENTNODE[$conf['start']]);
        } else if (!empty($CURRENTNODE['element'])) {
            $didOpenItem = !(count($CURRENTNODE['pages']) == 0);
            $XML .= $this->__TOCItem($CURRENTNODE['element'], $DEPTH, !$didOpenItem);
            unset($CURRENTNODE['element']);
        } else if ($CURRENTNODENAME != null) {
            // We have a parent node for what is comming … lets honor that
            $didOpenItem = !(count($CURRENTNODE) == 0);
            $XML .= $this->__TOCItem(array('name' => $CURRENTNODENAME), $DEPTH, !$didOpenItem);
        } else {
            // Woohoo … empty node? do not count up!
            $DEPTH--;
        }
        
        $this->debug("-- This is the current node --");
        $this->debug($CURRENTNODE);
        
        // Circle through the entries
        foreach (empty($CURRENTNODE['pages']) ? $CURRENTNODE : $CURRENTNODE['pages'] as $NODENAME => $ELEM)
        {
            // a node should have more than only one entry … otherwise we will not tell our name!
            $XML .= $this->__writeTOCTree($ELEM, count($ELEM) >= 1 ? ( !empty($ELEM['name']) ? $ELEM['name'] : $NODENAME ) : null, $DEPTH+1);
        }
        
        // Close and return
        return $XML . ($didOpenItem ? $this->__TOCItemClose($DEPTH) : '');
    }

    /**
     * Build the Eclipse Documentation TOC XML
     **/
    public function __getTOCXML($DATA, $XML = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<?NLS TYPE=\"org.eclipse.help.toc\"?>\n") {

        $pagesArray = array();

        // Go through the pages
        foreach ($DATA as $elem) {

            $site = $elem['id'];
            $elems = explode('/', $this->functions->getSiteName($site));

            // Strip Site
            array_pop($elems);
             
            // build the topic Tree
            $this->__buildTopicTree($pagesArray, $elems, $site);
        }

        $XML .= $this->__addXMLTopic($pagesArray, 'toc');

        return $XML;

    }

    /**
     * Load the topic Tree for the TOC - recursive
     **/
    private function __buildTopicTree(&$PAGES, $DATA, $SITE, $INSERTDATA = null) {

        if (empty($DATA) || !is_array($DATA)) {
            
            if ($INSERTDATA == null)
            {
                $INSERTDATA = $SITE;
            }
        
            // This is already a namespace
            if (is_array($PAGES[noNS($SITE)])) {
                // The root already exists!
                if (!empty($PAGES[noNS($SITE)][noNS($SITE)])) {
                    if (strstr($PAGES[noNS($SITE)][noNS($SITE)], $SITE)) {
                        // The SITE is in the parent Namespace, and the current Namespace has an index with same name
                        $PAGES['__' . noNS($SITE)] = $INSERTDATA;
                    } else {
                        $PAGES['__' . noNS($SITE)] = $PAGES[noNS($SITE)][noNS($SITE)];
                        $PAGES[noNS($SITE)][noNS($SITE)] = $INSERTDATA;
                    }
                } else {
                    $PAGES[noNS($SITE)][noNS($SITE)] = $INSERTDATA;
                }
            } else {
                // just a Page
                $PAGES[noNS($SITE)] = $INSERTDATA;
            }
            return;
        }

        $NS = array_shift($DATA);
        if (!is_array($PAGES[$NS])) $PAGES[$NS] = empty($PAGES[$NS]) ? array() : array($PAGES[$NS]);
        $this->__buildTopicTree($PAGES[$NS], $DATA, $SITE, $INSERTDATA);

        return;
    }

    /**
     * Build the Topic Tree for TOC.xml
     **/
    private function __addXMLTopic($DATA, $ITEM = 'topic', $LEVEL = 0, $NODENAME = '') {
        global $conf;

        $DEPTH = str_repeat("\t", $LEVEL);

        if (!is_array($DATA)) {
            return $DEPTH . '<' . $ITEM . ' label="' . $this->functions->getSiteTitle($DATA) . '" ' . ($ITEM != 'topic' ? 'topic' : 'href') . '="' . $this->functions->getSiteName($DATA) . "\" />\n";
        }
        // Is array from this point on
        list($indexTitle, $indexFile) = $this->__getIndexItem($DATA, $NODENAME);

        if (empty($indexTitle)) $indexTitle = $this->functions->getSiteTitle($conf['start']);
        if (!empty($indexFile)) $indexFile = ($ITEM != 'topic' ? 'topic' : 'href') . "=\"$indexFile\"";

        $isEmptyNode = count($DATA) == 1 && empty($indexFile);

        if (!$isEmptyNode && ($this->emptyNSToc || count($DATA) > 0)) {
            $XML = "$DEPTH<$ITEM label=\"$indexTitle\" $indexFile>";
        } else {
            $XML = "";
        }

        if (!$isEmptyNode && count($DATA) > 0) $XML .= "\n";

        foreach ($DATA as $NODENAME => $NS) {
            $XML .= $this->__addXMLTopic($NS, (!($this->emptyNSToc || count($DATA) > 1) && $ITEM != 'topic' ? $ITEM : 'topic'), $LEVEL+(!$isEmptyNode ? 1 : 0), $NODENAME);
        }

        if (!$isEmptyNode && count($DATA) > 0) $XML .= "$DEPTH";
        if (!$isEmptyNode && ($this->emptyNSToc || count($DATA) > 0)) {
            $XML .= "</$ITEM>\n";
        }

        return $XML;
    }


    /**
     * Get the context XML
     **/
    public function __getContextXML($DATA) {

        $XML = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<?NLS TYPE=\"org.eclipse.help.context\"?>\n<contexts>\n";

        $check = array();
        foreach ($DATA as $elem)
        {
            $ID = $elem['id'];
            $meta = p_get_metadata($ID, 'context', true);
            if (empty($meta['id'])) { continue; }

            $TITLE = empty($meta['title']) ? $this->functions->getSiteTitle($ID) : $meta['title'];

            // support more than one view IDs ... for more than one reference
            $VIEWIDs = $this->functions->getMapID($elem['id'], $elem['anchor'], $check);

            $DESCRIPTION = $this->functions->xmlEntities(p_get_metadata($ID, 'description abstract'));

            // Build topic Links
            $url = $this->functions->getSiteName($ID);
            $this->shortenByTranslation($url);

            $TOPICS = array($url => $TITLE . " (Details)");
            $REFS = p_get_metadata($ID, 'relation references', true);
            if (is_array($REFS))
            foreach ($REFS as $REL => $EXISTS) {
                if (!$EXISTS) { continue; }
                $TOPICS[$this->functions->getSiteName($REL)] = $this->functions->getSiteTitle($REL);
            }
            
            // build XML - include multi view IDs
            foreach ($VIEWIDs as $VIEWID) {
                $XML .= "\t<context id=\"$VIEWID\" title=\"$TITLE\">\n";
                $XML .= "\t\t<description>$DESCRIPTION</description>\n";

                foreach ($TOPICS as $URL => $LABEL) {
                    $XML .= "\t\t<topic label=\"$LABEL\" href=\"$URL\" />\n";
                }

                $XML .= "\t</context>\n";
            }
        }

        $XML .= "</contexts>";
        return $XML;

    }

    /**
     * Determine if this is an index - and if so, find its Title
     **/
    private function __getIndexItem(&$DATA, $NODENAME = '') {
        global $conf;

        if (!is_array($DATA)) { return; }

        $indexTitle = '';
        $indexFile = '';
        foreach ($DATA as $NODE => $indexSearch) {
            // Skip next Namespaces
            if (is_array($indexSearch)) { continue; }

            // Skip if this is not a start
            if ($NODE != $conf['start']) { continue; }

            $indexTitle = $this->functions->getSiteTitle($indexSearch);
            $indexFile = $indexSearch;
            unset($DATA[$NODE]);
            break;
        }

        if (empty($indexFile) && !empty($DATA[$NODENAME])) {
            $indexTitle = $this->functions->getSiteTitle($DATA[$NODENAME]);
            $indexFile = $DATA[$NODENAME];
            unset($DATA[$NODENAME]);
        }

        return array($indexTitle, $this->functions->getSiteName($indexFile));
    }

    private $doDebug = false;
    private static $didDebug = false;
    public function debug($data, $final = false) {
        if ( ! $this->doDebug ) { return; }
        
        if ( !$this->didDebug ) {
            print "<html><pre>";
            $this->didDebug = true;
        }
        
        if ( is_array($data) ) {
            print_r($data);
        } else {
            print str_replace("<", "&lt;", str_replace(">", "&gt;", $data));;
        }
        
        print "\n\n";

        if ( $final ) {
            print "</pre></html>";
            exit;
        }
    }
}
