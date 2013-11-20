<?php

if(!defined('DOKU_PLUGIN')) die('meh');
require_once(DOKU_PLUGIN.'siteexport/inc/toc.php');

class siteexport_javahelp
{
    private $functions = null;
    private $translation = null;
    private $filewriter = null;
    
    private $tocName = 'toc.xml';
    private $mapName = 'map.xml';
    
    public function siteexport_javahelp($functions, $filewriter)
    {
        $this->functions = $functions;
        $this->filewriter = $filewriter;
        $this->translation = & plugin_load('helper', 'translation' );
    }

    public function createTOCFiles($data)
    {
        global $conf;
        
        // Split Tree for translation
        $translationHSFiles = array();

        for ($i=0; $i<count($data); $i++)
        {
            $lang = '';
            if ( $this->translation )
            {
                $this->translation->tns = $this->translation->setupTNS($data[$i]['id']);
                $lang = $this->translation->getLangPart($data[$i]['id']);
            }

            // get all the relative URLs
            $translationHSFiles[$lang][] = $data[$i];
        }
        
        $toc = new siteexport_toc($this->functions);
        if ( $this->translation )
        {
            $translationRoot = curNS($this->translation->tns);
        }
        
        $hsPrename = curNS(getNS($this->translation->tns)) . '_' . $translationRoot;
        
        $check = array();
        foreach( $translationHSFiles as $lang => $data )
        {
            if ( count($translationHSFiles) == 1 && $lang == $conf['lang'] )
            {
                // If there is only one language and it is the system language - there is no language
                $lang = '';
            }
            
            // Prepare Translations
            if ( !empty($lang) )
            {
                $toc->translation = &$this->translation;
                $rootNode = cleanID($this->translation->tns . $lang) . ':';
            } else {
                $toc->translation = null;
                $rootNode = '';
            }
            
            $tsRootPath = $this->translationRootPath($translationRoot);

            // Create toc and map for each lang
            list($tocData, $mapData, $startPageID) = $toc->__getJavaHelpTOCXML($data, $tsRootPath);
            $this->functions->debug->message("Generating JavaHelpDocZip for language '$lang'", null, 2);
            $this->filewriter->__moveDataToZip($tocData, $tsRootPath . $lang . '/' . $this->tocName);
            $this->filewriter->__moveDataToZip($mapData, $tsRootPath . $lang . '/' . $this->mapName);

            // Create HS File
            // array_shift($toc->getMapID($rootNode, &$check))
            $HS = $this->getHSXML( $startPageID, $this->functions->getSiteTitle($rootNode), $lang, $tsRootPath );
            $this->filewriter->__moveDataToZip($HS, $hsPrename . '_' . $lang . '.hs');
            
            // Default Lang
            if ( $lang == $conf['lang'] )
            {
                $this->filewriter->__moveDataToZip($HS, $hsPrename . '.hs');
            }
        }
    }
    
    private function translationRootPath($translationRoot = '')
    {
        if ( !empty($translationRoot) )
        {
            return $translationRoot . '/';
        }
        
        return $translationRoot;
    }
    
    private function getHSXML($rootID, $title, $lang='', $translationRoot='')
    {
        return <<<OUTPUT
<?xml version='1.0' encoding='ISO-8859-1' ?>
<helpset version="1.0">
 
	<title>{$title}</title>
    <maps>
        <homeID>{$rootID}</homeID>
        <mapref location="{$translationRoot}{$lang}/{$this->mapName}"/>
     </maps>

    <view>
        <name>TOC</name>
        <label>{$this->functions->getLang('toc')}</label>
        <type>javax.help.TOCView</type>
        <data>{$translationRoot}{$lang}/{$this->tocName}</data>
    </view>

    <view>
        <name>Search</name>
        <label>{$this->functions->getLang('search')}</label>
        <type>javax.help.SearchView</type>
        <data engine="com.sun.java.help.search.DefaultSearchEngine">
            {$translationRoot}{$lang}/JavaHelpSearch
        </data>
    </view>

    <impl>
        <helpsetregistry helpbrokerclass="javax.help.DefaultHelpBroker" />
        <viewerregistry viewertype="text/html" viewerclass="com.inet.html.InetHtmlEditorKit" />
    </impl>
</helpset>
OUTPUT;
    }
}