<?php

if(!defined('DOKU_PLUGIN')) die('meh');
require_once(DOKU_PLUGIN.'siteexport/inc/pdfgenerator.php');

class siteexport_zipfilewriter
{
    /**
     * further classes
     */
    private $pdfGenerator = false;
    private $functions = null;

    public function siteexport_zipfilewriter( $functions = null )
    {
        $this->functions = $functions;
        if ( class_exists( 'siteexport_pdfgenerator' ) )
        {
            $this->pdfGenerator = new siteexport_pdfgenerator($functions);
        }
    }

    public function canDoPDF()
    {
        return $this->pdfGenerator !== false;
    }


    /**
     * Wrapper for fetching the Context or the TOC for Eclipse Documentation
     * This also puts the file into the zip package
     **/
    public function __moveDataToZip($DATA, $FILENAME='toc.xml', $ZIP=null, $JUSTWRITE=false) {

        if ( empty($DATA) ) { return false; }

        $tmpFile = tempnam($this->functions->settings->tmpDir , 'siteexport__');

        @file_put_contents($tmpFile, $DATA);

        // Add to zip
        if ( $JUSTWRITE ) {
            $status = $this->__writeFileToZip($tmpFile, $FILENAME, $ZIP);
        } else {
            $status = $this->__addFileToZip($tmpFile, $FILENAME, $ZIP);
        }
        @unlink($tmpFile);

        return $status;
    }

    /**
     * Adds a file to the zip file
     * @param $FILE file-name of the zip
     * @param $NAME name of the file that is being added
     * @param $ZIP name of the zip file to which we add
     */
    function __addFileToZip($FILE, $NAME, $ZIP=null) {

        if ( $NAME[0] === "/" ) {
            $this->functions->debug->message("Weird, the NAME for the ZIP started with a '/'. This may result in wrong links!", null, 3);
            $NAME = substr($NAME, 1);
        }

        // check for mpdf
        if ( $this->canDoPDF() ) {
            $this->functions->debug->message("Trying to create PDF from File '$FILE' with name '$NAME' for ZIP '$ZIP'", null, 2);

            $succeeded = $this->pdfGenerator->createPDFFromFile($FILE, $NAME);

            if ( $this->functions->debug->debugLevel() <= 1 ) { // 2011-01-12 Write HTML to ZIP for Debug purpose
                $this->__moveDataToZip($succeeded, "_debug/$NAME.html", $ZIP, true);
            }

            if ( $succeeded===false ) {
                $this->functions->debug->runtimeException("Create PDF from File '$FILE' with name '$NAME' went wrong and is not being added!");
                return false;
            }
        }

        return $this->__writeFileToZip($FILE, $NAME, $ZIP);
    }

    /**
     * This really writes a file to a zip-file
     * @param $FILE file-name of the zip
     * @param $NAME name of the file that is being added
     * @param $ZIP name of the zip file to which we add
     */
    private function __writeFileToZip($FILE, $NAME, $ZIPFILE) {
        if ( empty( $ZIPFILE ) ) $ZIPFILE = $this->functions->settings->zipFile;

        if ( !class_exists('ZipArchive') ) {
            $this->functions->debug->runtimeException("PHP class 'ZipArchive' does not exist. Please make sure that you have the ziplib extension for PHP installed.");
            return false;
        }

        $zip = new ZipArchive();
        if ( !$zip ) {
            $this->functions->debug->runtimeException("Can't create new instance of 'ZipArchive'. Please make sure that you have the ziplib extension for PHP installed.");
            return false;
        }

        $code = $zip->open($ZIPFILE, ZipArchive::CREATE);
        if ($code === TRUE) {

            $this->functions->debug->message("Adding file '$NAME' to ZIP $ZIP", null, 2);

            $zip->addFile($FILE, $NAME);
            $zip->close();

            // If this has worked out, we may put this version into the cache ... ?

            // ALibi Touching - 2011-09-13 wird nicht gebraucht nach Umstellung
            // io_saveFile(mediaFN($this->origZipFile), "alibi file");

            return true;
        }

        $this->functions->debug->runtimeException("Zip Error #{$code} for file {$NAME}");
        return false;
    }

    /**
     * check if a file exists allready
     * @param $NAME name of the file in the zip
     */
    function fileExistsInZip($NAME)
    {
        $zip = new ZipArchive();
        $code = $zip->open($this->functions->settings->zipFile, ZipArchive::CREATE);
        if ($code === TRUE) {
            $exists = !($zip->statName($NAME) === FALSE);
            $zip->close();
            return $exists;
        }

        return false;
    }

    /**
     * Checks if a valid cache file exists for the given request parameters
     * @param $requestData
     */
    function hasValidCacheFile($requestData, $depends=array())
    {
        $pattern = $this->functions->requestParametersToCacheHash($requestData);
        return $this->hasValidCacheFileForPattern($pattern, $depends);
    }
    
    private function hasValidCacheFileForPattern($pattern, $depends=array())
    {
        $this->functions->debug->message("HASH-Pattern for CacheFile: ", $pattern, 2);
        $this->functions->settings->hasValidCacheFile = false; // reset the cache settings
        $cacheFile = $this->functions->getCacheFileNameForPattern($pattern);
        
        $mtime = @filemtime($cacheFile); // 0 if not exists

        // Check if the file is expired - if so, just create a new one.
        if ( $mtime == 0 || $mtime < time()-$this->functions->settings->cachetime )
        {
            @unlink($cacheFile);
            @unlink($this->functions->settings->zipFile);
            $this->functions->debug->message("New CacheFile because the file was over the cachetime: ", $cacheFile, 2);
            return false;
        }

        // Check for dependencies
        if ( !empty($depends) )
        {
            $this->functions->debug->message("Checking dependencies: ", $depends, 1);
            foreach ($depends as $site) {
                
                if ( !page_exists($site['id']) )
                {
                    $this->functions->debug->message("File does not exist: ", $site['id'], 2);
                    continue;
                }
                
                if ($mtime < @filemtime(wikiFN($site['id']))) {
                    @unlink($cacheFile);
                    @unlink($this->functions->settings->zipFile);
                    $this->functions->debug->message("New CacheFile, because a page changed: ", $cacheFile, 2);
                    return false;         // cache older than files it depends on?
                }
            }
        }

        $this->functions->debug->message("CacheFile exists: ", $cacheFile, 2);
        return $this->functions->settings->hasValidCacheFile = true;
    }
    
    public function getOnlyFileInZip(&$data = null) {

        if ( is_null($data['file']) ) $data['file'] = $this->functions->settings->zipFile;
        
        $zip = new ZipArchive();
        $code = $zip->open($data['file']);
        if ( $code !== TRUE ) {
            $this->functions->debug->message("Can't open the zip-file.", $data['file'], 2);
            return false;
        }
        
        if ( $zip->numFiles != 1 ) {
            $zip->close();
            $this->functions->debug->message("More than one ({$zip->numFiles}) file in zip.", $data['file'], 2);
            return false;
        }
        
        $stat = $zip->statIndex( 0 );
        $this->functions->debug->message("Stat.", $stat, 3);
        if ( substr($stat['name'], -3) != 'pdf' ) {
            $zip->close();
            $this->functions->debug->message("The file was not a PDF ({$stat['name']}).", $stat['name'], 2);
            return false;
        }
        
        $data['mime'] = 'application/pdf';
        
        // Extract single file.
        $folder = dirname($data['file']);
        
        $data['orig'] = utf8_basename($stat['name']);
        $zip->extractTo($folder, $stat['name']);
        $zip->close();
        
        sleep(1);
        $data['file'] .= '.' . cleanID($data['orig']); // Wee need the other file for cache reasons.
        @rename($folder.'/'.$data['orig'], $data['file']);
	    return true;
    }
}

?>
