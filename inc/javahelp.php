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
        global $conf, $ID;

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
            $hsPrename = curNS(getNS($this->translation->tns));
        } else {
            $translationRoot = '';
            $hsPrename = '';
        }
                
        $this->functions->debug->message("HelpSetPre-Name: {$hsPrename}", null, 3);
        $this->functions->debug->message("Translation-Root: {$translationRoot}", null, 3);
        $this->functions->debug->message("HSFiles:", $translationHSFiles, 1);
        
        
        $check = array();
        $last_key = end(array_keys($translationHSFiles));
        
        foreach( $translationHSFiles as $lang => $data )
        {
            // Prepare Translations
            if ( !empty($lang) && !$this->functions->settings->TOCMapWithoutTranslation )
            {
                $toc->translation = &$this->translation;
                $rootNode = cleanID($this->translation->tns . $lang) . ':';
            } else {
                $toc->translation = null;
                $rootNode = '';
            }
            
            $tsRootPath = $hsPrename . '/' . $this->translationRootPath($translationRoot);
            $this->functions->debug->message("Generating JavaHelpDocZip for language '$lang'", $tsRootPath, 3);
            
            // Create toc and map for each lang
            list($tocData, $mapData, $startPageID) = $toc->__getJavaHelpTOCXML($data, $tsRootPath);
            $this->filewriter->__moveDataToZip($tocData, $tsRootPath . $lang . '/' . $this->tocName);
            $this->filewriter->__moveDataToZip($mapData, $tsRootPath . $lang . '/' . $this->mapName);

            // Create HS File
            // array_shift($toc->getMapID($rootNode, &$check))
            $HS = $this->getHSXML( $startPageID, $this->functions->getSiteTitle($rootNode), $lang, $tsRootPath );
            $this->filewriter->__moveDataToZip($HS, $translationRoot . ( empty($lang) ? '' : '_' . $lang ) . '.hs');
            
            // Default Lang
            if ( $lang == $this->functions->settings->defaultLang || $lang == $last_key )
            {
                $this->functions->debug->message("Writing Default HS File for Language:", $lang, 3);
                $this->filewriter->__moveDataToZip($HS, $translationRoot . '.hs');
                $last_key = null;
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
        if ( empty($lang) && substr($translationRoot, -1) == '/') {
            $translationRoot = substr($translationRoot, 0, -1);
        } else if ( !empty($lang) && substr($translationRoot, -1) != '/' ) {
            $lang .= '/';
        }
    
        return <<<OUTPUT
<?xml version='1.0' encoding='ISO-8859-1' ?>
<helpset version="1.0">
 
	<title>{$title}</title>
    <maps>
        <homeID>{$rootID}</homeID>
        <mapref location="{$translationRoot}{$lang}{$this->mapName}"/>
     </maps>

    <view>
        <name>TOC</name>
        <label>{$this->functions->getLang('toc')}</label>
        <type>javax.help.TOCView</type>
        <data>{$translationRoot}{$lang}{$this->tocName}</data>
    </view>

    <view>
        <name>Search</name>
        <label>{$this->functions->getLang('search')}</label>
        <type>javax.help.SearchView</type>
        <data engine="com.sun.java.help.search.DefaultSearchEngine">
            {$translationRoot}{$lang}JavaHelpSearch
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