<?php 

if(!defined('DOKU_PLUGIN')) die('meh');

class siteexport_toc
{
    private $emptyNSToc = true;
    private $functions = null;
    public $translation = null;
    
    public function siteexport_toc($functions)
    {
        $this->emptyNSToc = !empty($_REQUEST['emptyTocElem']);
        $this->functions = $functions;
    }
    
    private function shortenByTranslation(&$inputURL, $deepSearch = false)
    {
        // Mandatory: we allways want '/' insteadf of ':' here
        $inputURL = str_replace(':', '/', $inputURL);
        if ( $this->translation )
        {
            $url = explode('/', $inputURL);
            
            for( $i=0; $i<count($url); $i++ )
            {
                if ( in_array($url[$i], $this->translation->trans ) )
                {
                    // Rauswerfen und weg
                    $url[$i] = '';
                    break;
                }
                
                if ( !$deepSearch )
                {
                    break;
                }

                // Ok, remove anyway
                $url[$i] = '';
            }
            
            $inputURL = implode('/', $url);
            $inputURL = preg_replace("$\/+$", "/", $inputURL);
        }
        
        if ( strlen($inputURL) > 0 && substr($inputURL, 0, 1) == '/' )
        {
            $inputURL = substr($inputURL, 1);
        }
        
        return $inputURL;
    }
    
    /**
     * Build the Java Documentation TOC XML
     **/
    public function __getJavaHelpTOCXML($DATA) {

        if ( count ( $DATA) == 0 ) {
            return false;
        }
        
        $TOCXML = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<toc>";
        $MAPXML = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<map version=\"1.0\">";
        // usort($DATA, array($this, 'sortFunction'));

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
            $elem['mapID'] = intval($elem['exists']) == 1 ? $this->getMapID($elem, $check) : array();

            if ( empty($elem['depth']) ) $elem['depth'] = count(explode('/', $elem['url']));            
            $CHECKDATA[] = $elem['url'];
            
            if ( $startPageID == null )
            {
                $startPageID = $elem['mapID'][0];
            }
            
            if ( empty( $elem['name'] ) || $elem['name'] == noNs($elem['id']) ) {
	            $elem['name'] = $this->functions->getSiteTitle($elem['id']);
            }

            // Go on building mapXML
            $this->shortenByTranslation($elem['mapURL'], true); // true to already remove all language stuff - false if not
            foreach ( $elem['mapID'] as $VIEWID ) {
                $MAPXML .= "\n\t<mapID target=\"$VIEWID\" url=\"" . $elem['mapURL'] . $anchor . "\"/>";
            }

            $elem['tocNS'] = getNS(cleanID($elem['url']));
            $elem['tocNS'] = explode('/', $this->shortenByTranslation($elem['tocNS'], true));
            $this->functions->debug->message("This will be the TOC elements data:", $elem, 1);

            $this->__buildTOCTree($DATA, $elem['tocNS'], $elem);
        }

        $TOCXML .= $this->__writeTOCTree($DATA) . "\n</toc>";
        $MAPXML .= "\n</map>";
/*
//*
        // http://documentation:81/documentation/clear-reports/remote-interface-help/configuration/configuration/index?JavaHelpDocZip=1&depthType=1&diInv=1&do=siteexport&ens=documentation%3Aclear-reports%3Aremote-interface-help%3Aconfiguration&renderer=&template=clearreports-setup&useTocFile=1
        print "<html><pre>";
        print_r($DATA);
        $TOCXML = str_replace("<", "&lt;", str_replace(">", "&gt;", $TOCXML));
        print "$TOCXML";

        $MAPXML = str_replace("<", "&lt;", str_replace(">", "&gt;", $MAPXML));
        print "$MAPXML";

        print "</pre></html>";
        exit;
/*/
        return array($TOCXML, $MAPXML, $startPageID);
//*/
    }
    
    /**
     * Prepare the TOC Tree
     **/
    private function __buildTOCTree(&$DATA, $currentNSArray, $elemToAdd)
    {
        global $conf;
    
        if ( empty($currentNSArray) )
        {
            // In Depth, let go!
            $DATA[noNS($elemToAdd['id'])] = $elemToAdd;
            return;
        } else if (count($currentNSArray) == 1 && $currentNSArray[0] == '' && noNS($elemToAdd['id']) == $conf['start'] )
        {
            // Wird gebraucht um die erste Ebene sauber zu bauen … kann aber irgendwelche Nebeneffekte haben
            $DATA[noNS($elemToAdd['id'])] = $elemToAdd;
            return;
        }
        
        $currentLevel = array_shift($currentNSArray);
        if ( empty($DATA[$currentLevel]) ) {
            $DATA[$currentLevel] = array();
        }
        
        $this->__buildTOCTree($DATA[$currentLevel], $currentNSArray, $elemToAdd);
    }
    
    /**
     * Create a single TOC Item
     **/
    private function __TOCItem($item, $depth, $selfClosed=true)
    {
        $targetID = $item['mapID'][0];
        if ( empty($targetID) ) {
            $targetID = strtolower($item['name']);
        }
        return "\n" . str_repeat("\t", max($depth, 0)+1) . "<tocitem target=\"$targetID\"" . (intval($item['exists']) == 1 ? " text=\"" . $item['name'] . "\"" : "") . ($selfClosed?'/':'') . ">";
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
    private function __writeTOCTree($CURRENTNODE, $CURRENTNODENAME = null, $DEPTH=0) {
        global $conf;
    
        $XML = '';
        $didOpenItem = false;
        if ( !is_array($CURRENTNODE) || empty($CURRENTNODE) )
        {
            // errr … no.
            return $XML;
        }

        // This is an element!        
        if ( !empty($CURRENTNODE['id']) )
        {
            // This has to be an item!
            return $this->__TOCItem($CURRENTNODE, $DEPTH);
        }
        
        // Look for start page
        if ( !empty($CURRENTNODE[$conf['start']]) )
        {
            // YAY! StartPage found.
            $didOpenItem = !(count($CURRENTNODE) == 1);
            $XML .= $this->__TOCItem($CURRENTNODE[$conf['start']], $DEPTH, !$didOpenItem );
            unset($CURRENTNODE[$conf['start']]);
        } else if ($CURRENTNODENAME != null) {
            // We have a parent node for what is comming … lets honor that
            $didOpenItem = !(count($CURRENTNODE) == 0);
            $XML .= $this->__TOCItem(array('name' => ucwords($CURRENTNODENAME)), $DEPTH, !$didOpenItem );
        } else {
            // Woohoo … empty node? do not count up!
            $DEPTH --;
        }
        
        // Circle through the entries
        foreach ( $CURRENTNODE as $NODENAME => $ELEM )
        {
            // a node should have more than only one entry … otherwise we will not tell our name!
            $XML .= $this->__writeTOCTree($ELEM, count($ELEM) >= 1 ? $NODENAME : null, $DEPTH+1);
        }
        
        // Close and return
        return $XML . ($didOpenItem?$this->__TOCItemClose($DEPTH):'');
    }
    
    
    function post(&$value, $key, array $additional){
        $inner_glue = $additional[0];
        $prefix = isset($additional[1])? $additional[1] : false;
        if($prefix === false) $prefix = $key;
    
        $value = $value.$inner_glue.$prefix;
    }
    
    function mapIDWithAnchor(&$n, $key, $postfix)
    {
        if ( empty($postfix) ) return;
        $n .= '-' . $postfix;
    }
    
    function getMapID($elem, &$check)
    {
        $meta = p_get_metadata($elem['id'], 'context', true);

        if ( empty($meta['id']) ) {
            $title = empty( $meta['title'] ) ? $this->functions->getSiteTitle($elem['id']) : $meta['title'];
            $meta['id'] = sectionID($this->functions->cleanId(strtolower($title)), $check);
        }

        $mapID = explode('|', $meta['id']);
        array_walk($mapID, array($this, 'mapIDWithAnchor'), $elem['anchor']);
            
        return $mapID;
    }
    
    /**
     * internal Sort function
     * @param unknown_type $a
     * @param unknown_type $b
     */
    private function sortFunction($a, $b)
    {
        $idA = $a['id'];
        $idB = $b['id'];
        
        $depthA = explode(':', getNS($idA));
        $depthB = explode(':', getNS($idB));
        
        for ( $i=0; $i < min(count($depthA), count($depthB)); $i++ )
        {
            $NSCMP = strcmp($depthA[$i], $depthB[$i]);
            if ( $NSCMP != 0 )
            {
                // Something is different!
                return $NSCMP;
            }
        }
        
        // There is mor in B, than in A!
        if ( count($depthA) < count($depthB) )
        {
            return -1;
        } else if ( count($depthA) > count($depthB) )
        {
            // there is more in A than in B
            return 1;
        }

        if ( $NSCMP == 0 )
        {
            // Something is different!
            return strcmp(noNS($idA), noNS($idB));
        }
        
        return 0;
    }

    /**
     * Build the Eclipse Documentation TOC XML
     **/
    public function __getTOCXML($DATA, $XML="<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<?NLS TYPE=\"org.eclipse.help.toc\"?>\n") {

        $pagesArray = array();

        // Go through the pages
        foreach ( $DATA as $elem ) {

            $site = $elem['id'];
            $elems = explode('/', $this->functions->getSiteName($site));

            // Strip Site
            array_pop( $elems );
             
            // build the topic Tree
            $this->__buildTopicTree($pagesArray, $elems, $site);
        }

        $XML .= $this->__addXMLTopic($pagesArray, 'toc');

        return $XML;

    }

    /**
     * Load the topic Tree for the TOC - recursive
     **/
    private function __buildTopicTree( &$PAGES, $DATA, $SITE, $INSERTDATA = null ) {

        if ( empty( $DATA ) || !is_array($DATA) ) {
            
            if ( $INSERTDATA == null )
            {
                $INSERTDATA = $SITE;
            }
        
            // This is already a namespace
            if ( is_array($PAGES[noNS($SITE)]) ) {
                // The root already exists!
                if ( !empty($PAGES[noNS($SITE)][noNS($SITE)]) ) {
                    if ( strstr($PAGES[noNS($SITE)][noNS($SITE)], $SITE) ) {
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
        if ( !is_array( $PAGES[$NS] ) ) $PAGES[$NS] = empty($PAGES[$NS]) ? array() : array($PAGES[$NS]);
        $this->__buildTopicTree( $PAGES[$NS], $DATA, $SITE, $INSERTDATA );

        return;
    }

    /**
     * Build the Topic Tree for TOC.xml
     **/
    private function __addXMLTopic($DATA, $ITEM='topic', $LEVEL=0, $NODENAME='') {
        global $conf;

        $DEPTH = str_repeat("\t", $LEVEL);

        if ( !is_array($DATA) ) {
            return $DEPTH . '<' . $ITEM . ' label="' . $this->functions->getSiteTitle($DATA) . '" ' . ($ITEM != 'topic' ? 'topic' : 'href' ) . '="' . $this->functions->getSiteName($DATA) . "\" />\n";
        }
        // Is array from this point on
        list($indexTitle, $indexFile) = $this->__getIndexItem($DATA, $NODENAME);

        if ( empty( $indexTitle) ) $indexTitle = $this->functions->getSiteTitle( $conf['start'] );
        if ( !empty( $indexFile) ) $indexFile = ($ITEM != 'topic' ? 'topic' : 'href' ) . "=\"$indexFile\"";

        $isEmptyNode = count($DATA) == 1 && empty($indexFile);

        if ( !$isEmptyNode && ($this->emptyNSToc || count($DATA) > 0) )
        $XML = "$DEPTH<$ITEM label=\"$indexTitle\" $indexFile>";

        if ( !$isEmptyNode && count ($DATA) > 0 ) $XML .= "\n";

        foreach( $DATA as $NODENAME => $NS ) {

            $XML .= $this->__addXMLTopic($NS, ( !($this->emptyNSToc || count($DATA) > 1) && $ITEM != 'topic' ? $ITEM : 'topic' ), $LEVEL+(!$isEmptyNode ? 1 : 0), $NODENAME);

        }

        if ( !$isEmptyNode && count ($DATA) > 0 ) $XML .= "$DEPTH";
        if ( !$isEmptyNode && ($this->emptyNSToc || count($DATA) > 0) ) {
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
        foreach ( $DATA as $elem )
        {
            $ID = $elem['id'];
            $meta = p_get_metadata($ID, 'context', true);
            if ( empty( $meta['id'] ) ) { continue; }

            $TITLE = empty( $meta['title'] ) ? $this->functions->getSiteTitle($ID) : $meta['title'];

            // support more than one view IDs ... for more than one reference
            $VIEWIDs = $this->getMapID($elem, $check);

            $DESCRIPTION = $this->functions->xmlEntities(p_get_metadata($ID, 'description abstract'));

            // Build topic Links
            $url = $this->functions->getSiteName($ID);
            $this->shortenByTranslation($url);

            $TOPICS = array( $url => $TITLE . " (Details)" );
            $REFS = p_get_metadata($ID, 'relation references', true);
            if ( is_array($REFS) )
            foreach ( $REFS as $REL => $EXISTS ) {
                if ( !$EXISTS ) { continue; }
                $TOPICS[$this->functions->getSiteName($REL)] = $this->functions->getSiteTitle($REL);
            }
            
            // build XML - include multi view IDs
            foreach ( $VIEWIDs as $VIEWID ) {
                $XML .= "\t<context id=\"$VIEWID\" title=\"$TITLE\">\n";
                $XML .= "\t\t<description>$DESCRIPTION</description>\n";

                foreach ( $TOPICS as $URL => $LABEL ) {
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
    private function __getIndexItem(&$DATA, $NODENAME='') {
        global $conf;

        if ( !is_array($DATA) ) { return; }

        $indexTitle = '';
        $indexFile = '';
        foreach ( $DATA as $NODE => $indexSearch) {
            // Skip next Namespaces
            if ( is_array($indexSearch) ) { continue; }

            // Skip if this is not a start
            if ( $NODE != $conf['start'] ) { continue; }

            $indexTitle = $this->functions->getSiteTitle( $indexSearch );
            $indexFile = $indexSearch;
            unset($DATA[$NODE]);
            break;
        }

        if ( empty($indexFile) && !empty($DATA[$NODENAME]) ) {
            $indexTitle = $this->functions->getSiteTitle( $DATA[$NODENAME] );
            $indexFile = $DATA[$NODENAME];
            unset($DATA[$NODENAME]);
        }

        return array($indexTitle, $this->functions->getSiteName($indexFile));
    }
}

?>