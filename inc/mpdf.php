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

if ( file_exists(DOKU_PLUGIN . 'dw2pdf/mpdf/mpdf.php') ) {

    global $conf;
    if(!defined('_MPDF_TEMP_PATH')) define('_MPDF_TEMP_PATH', $conf['tmpdir'].'/dwpdf/'.rand(1,1000).'/');
    if(!defined('_MPDF_TTFONTDATAPATH')) define('_MPDF_TTFONTDATAPATH',$conf['cachedir'].'/mpdf_ttf/');

    require_once(DOKU_PLUGIN . 'dw2pdf/mpdf/mpdf.php');

    class siteexportPDF extends mpdf {
    
        private $debugObj = false;

		function __construct($debug) {
            global $INPUT;
            global $conf;
		
            $dw2pdf = plugin_load('action', 'dw2pdf');
		
		    // decide on the paper setup from param or config
            $pagesize    = $INPUT->str('pagesize', $dw2pdf->getConf('pagesize'), true);
            $orientation = $INPUT->str('orientation', $dw2pdf->getConf('orientation'), true);
    
            io_mkdir_p(_MPDF_TTFONTDATAPATH);
            io_mkdir_p(_MPDF_TEMP_PATH);
    
            $format = $pagesize;
            if($orientation == 'landscape') $format .= '-L';
    
            switch($conf['lang']) {
                case 'zh':
                case 'zh-tw':
                case 'ja':
                case 'ko':
                    $mode = '+aCJK';
                    break;
                default:
                    $mode = 'UTF-8-s';
    
            }
    
            // we're always UTF-8
            parent::__construct($mode, $format);
            $this->SetAutoFont(AUTOFONT_ALL);
            $this->ignore_invalid_utf8 = true;
            $this->tabSpaces = 4;
            $this->debugObj = $debug;
            $this->debug = $debug !== false;
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
            if ( $this->debugObj !== false && method_exists($this->debugObj, 'runtimeException') ) {
                $this->debugObj->runtimeException($msg);
            } else {
                parent::Error($msg);
            }
        }

//*
        // Nothing
/*/        
        var $previousPage = '';
        var $currentPage = '';
        var $skipAddPage = false;
        
        function Footer(){
        
            $currentPage = $this->pages[count($this->pages)];
            $this->skipAddPage = $this->previousPage && $this->pages[count($this->pages)] == $this->currentPage;
            
            if ( $this->skipAddPage ) {
                $this->message("HAS TO REMOVE PAGE:", count($this->pages));
            }
            
            $this->currentPage = $currentPage;
            parent::Footer();
        }
        function AddPage($orientation='',$condition='', $resetpagenum='', $pagenumstyle='', $suppress='',$mgl='',$mgr='',$mgt='',$mgb='',$mgh='',$mgf='',$ohname='',$ehname='',$ofname='',$efname='',$ohvalue=0,$ehvalue=0,$ofvalue=0,$efvalue=0,$pagesel='',$newformat='')
        {
        
            if ( $skipAddPage ) { return; }

            $count = count($this->pages);
            $stack = array();
            $trace = debug_backtrace();
            foreach( $trace as $entry ) {
                $vars = substr(implode(',', $entry['args']), 0, 20);
                $stack[] = "{$entry['function']}({$vars}) | {$entry['file']} | {$entry['line']}";
            }

            array_unshift($stack, "(({$this->y}+{$this->divheight}>{$this->PageBreakTrigger}) || ({$this->y}+h>{$this->PageBreakTrigger}) || 
		({$this->y}+(h*2)+{$this->blk[$this->blklvl]['padding_bottom']}+{$this->blk[$this->blklvl]['margin_bottom']}>{$this->PageBreakTrigger} && {$this->blk[$this->blklvl]['page_break_after_avoid']})) and !{$this->InFooter} and AcceptPageBreak())");

            $this->message("Is Adding Page $count: $orientation,$condition, $resetpagenum, $pagenumstyle,$suppress,$mgl,$mgr,$mgt,$mgb,$mgh,$mgf,$ohname,$ehname,$ofname,$efname,$ohvalue,$ehvalue,$ofvalue,$efvalue,$pagesel,$newformat", $stack, 1);
        
            parent::AddPage($orientation,$condition, $resetpagenum, $pagenumstyle,$suppress,$mgl,$mgr,$mgt,$mgb,$mgh,$mgf,$ohname,$ehname,$ofname,$efname,$ohvalue,$ehvalue,$ofvalue,$efvalue,$pagesel,$newformat);
        }
//*/        
        
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

        	$regex = "/^(". preg_quote(DOKU_BASE, '/') .".+)\\1/";
        	if ( preg_match($regex, $path, $matches) ) {
        		$path = preg_replace($regex, "\\1", $path);
        	}

        }
        
        /*
          Only when the toc is being generated  
        */
        function MovePages($target_page, $start_page, $end_page=-1) {
            parent::MovePages($target_page, $start_page, $end_page);
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
    
    if ( file_exists(DOKU_PLUGIN . 'dw2pdf/mpdf/classes/cssmgr.php') && !class_exists('cssmgr', false)) {
//*        
        require_once(DOKU_PLUGIN . 'siteexport/inc/patchCSSmgr.php');
        $objPatch = new CSSMgrPatch(DOKU_PLUGIN . 'dw2pdf/mpdf/classes/cssmgr.php');
        if ( $objPatch->redefineFunction(file_get_contents(DOKU_PLUGIN . 'siteexport/inc/readCSS.patch')) ) {
            eval($objPatch->getCode());
        }
/*/
//*/
    }

}
