<?php
/**
 * Site Export Plugin - mPDF Extension
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

if ( file_exists(DOKU_PLUGIN . 'dw2pdf/DokuPDF.class.php') ) {

	global $conf;
	// if(!defined('_MPDF_TEMP_PATH')) define('_MPDF_TEMP_PATH', $conf['tmpdir'].'/dwpdf/'.rand(1,1000).'/');

    require_once(DOKU_PLUGIN . 'dw2pdf/DokuPDF.class.php');

    class siteexportPDF extends DokuPDF {
        
        var $debugObj = true;

		function __construct($encoding, $debug=false) {
			
            parent::__construct($encoding);
            $this->debugObj = $debug;
            $this->debug = true;
            $this->shrink_tables_to_fit = 1; // Does not shrink tables by default, only in emergency
            $this->use_kwt = true; // avoids page-breaking in H1-H6 if a table follows directly
        }
        
        function message($msg, $vars=null, $lvl=1)
        {
            if ( $this->debugObj !== false ) {
                $this->debugObj->message($msg, $vars, $lvl);
            }
        }

        function Error($msg)
        {
            if ( $this->debug !== false && $lvl == null && method_exists($this->debug, 'runtimeException') ) {
                $this->debug->runtimeException($msg);
            } else {
                parent::Error($msg);
            }
        }
        
        function GetFullPath(&$path,$basepath='') {
        
        	// Full Path might return a doubled path like /~gamma/documentation/lib//~gamma/documentation/lib/tpl/clearreports/./_print-images/background-bottom.jpg
        	
			$path = str_replace("\\","/",$path); //If on Windows
			$path = preg_replace('/^\/\//','http://',$path);	// mPDF 5.6.27
			$regexp = '|^./|';	// Inadvertently corrects "./path/etc" and "//www.domain.com/etc"
			$path = preg_replace($regexp,'',$path);
		
        	if ( preg_match("/^.+\/\.\.\//", $path) ) {
        		// ../ not at the beginning
	        	$newpath = array();
	        	$oldpath = explode('/', $path);
	        	
	        	foreach( $oldpath as $slice ) {
		        	if ( $slice == ".." && count($newpath) > 0 ) {
			        	array_pop($newpath);
			        	continue;
		        	}
		        	
		        	$newpath[] = $slice;
	        	}
	        	
	        	$path = implode('/', $newpath);
        	}
        	
        	parent::GetFullPath($path, $basepath);
        	
        	$regex = "/(". preg_quote(DOKU_BASE, '/') .".+)\\1/";
        	if ( preg_match($regex, $path, $matches) ) {
        		$path = preg_replace($regex, "\\1", $path);
        	}
        }
        
        function OpenTag($tag, $attr) {
            switch($tag) {
                case 'BOOKMARK':
                case 'TOCENTRY':
                    if ( $attr['CONTENT'] ) {
                        // resolve double encoding
                        $attr['CONTENT'] = htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES);
                    }
                    break;
            }
            return parent::OpenTag($tag, $attr); 
        }
    }
}