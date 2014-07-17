<?php

/**
 Honestly: this is evil code to come by a problem in the cssmgr.php class of mpdf that breaks generating PDFs with large CSS files
 It comes from: http://stackoverflow.com/questions/137006/redefine-class-methods-or-class
*/
class CSSMgrPatch {

    private $_code;
    
    public function __construct($include_file = null) {
        if ( $include_file ) {
            $this->includeCode($include_file);
        }
    }
    
    public function setCode($code) {
        $this->_code = $code;
    }
    
    public function includeCode($path) {
    
        $fp = fopen($path,'r');
        $contents = fread($fp, filesize($path));
        $contents = str_replace('<?php','',$contents);
        $contents = str_replace('?>','',$contents);
        fclose($fp);        
    
        $this->setCode($contents);
    }
    
    function redefineFunction($new_function) {
    
        preg_match('/function (.+)\(/', $new_function, $aryMatches);
        $func_name = trim($aryMatches[1]);
    
        if ( preg_match('/(function '.$func_name.'[\w\W\n]+?)(function)/s', $this->_code, $aryMatches) ) {
    
            $search_code = $aryMatches[1];
    
            $new_code = str_replace($search_code, $new_function."\n\n", $this->_code);
    
            $this->setCode($new_code);
    
            return true;
    
        } else {
    
            return false;
    
        }
    }
    
    function getCode() {
        return $this->_code;
    }
}
?>