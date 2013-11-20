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

    require_once(DOKU_PLUGIN . 'dw2pdf/mpdf/mpdf.php');

    class siteexportPDF extends mPDF {
        
        var $debugObj = true;

        function siteexportPDF($encoding, $debug=false) {
            parent::mPDF($encoding);
            $this->debugObj = $debug;
            $this->debug = true;
            $this->shrink_tables_to_fit = 1; // Does not shrink tables by default, only in emergency
            $this->use_kwt = true; // avoids page-breaking in H1-H6 if a table follows directly
        }
        
        function message($msg, $vars=null, $lvl=1)
        {
            if ( $this->debugObj !== false ) {
                // $this->debugObj->message($msg, $vars, $lvl);
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
        
/*
        function _putannots($n) {
            $nb=$this->page;
            for($n=1;$n<=$nb;$n++)
            {
                $annotobjs = array();
                if(isset($this->PageLinks[$n]) || isset($this->PageAnnots[$n])) {
                    $wPt=$this->pageDim[$n]['w']*$this->k;
                    $hPt=$this->pageDim[$n]['h']*$this->k;

                    //Links
                    if(isset($this->PageLinks[$n])) {
                        foreach($this->PageLinks[$n] as $key => $pl) {
                            $this->_newobj();
                            $annot='';
                            $rect=sprintf('%.3f %.3f %.3f %.3f',$pl[0],$pl[1],$pl[0]+$pl[2],$pl[1]-$pl[3]);
                            $annot .= '<</Type /Annot /Subtype /Link /Rect ['.$rect.']';
                            $annot .= ' /Contents '.$this->_UTF16BEtextstring($pl[4]);
                            $annot .= ' /NM ('.sprintf('%04u-%04u', $n, $key).')';
                            $annot .= ' /M '.$this->_textstring('D:'.date('YmdHis'));
                            $annot .= ' /Border [0 0 0]';
                            // mPDF 4.2.018
                            if ($this->PDFA) { $annot .= ' /F 28'; }
                            if (strpos($pl[4],'@')===0) {
                                $p=substr($pl[4],1);
                                //	$h=isset($this->OrientationChanges[$p]) ? $wPt : $hPt;
                                $htarg=$this->pageDim[$p]['h']*$this->k;
                                $annot.=sprintf(' /Dest [%d 0 R /XYZ 0 %.3f null]>>',1+2*$p,$htarg);
                            }
                            else if(is_string($pl[4])) {
                                
                                if ( preg_match( "#^(https?:/|file:)/#", $pl[4] )) {
                                    $annot .= ' /A <</Type/Action/S/URI/URI'.$this->_textstring($pl[4]).'>> >>';
                                } else {
                                    $annot .= ' /A <</Type/Action/S/GoToR/F'.$this->_textstring($pl[4]).'>> >>';
                                }
                            }
                            else {
                                $l=$this->links[$pl[4]];
                                // mPDF 3.0
                                // may not be set if #link points to non-existent target
                                if (isset($this->pageDim[$l[0]]['h'])) { $htarg=$this->pageDim[$l[0]]['h']*$this->k; }
                                else { $htarg=$this->h*$this->k; } // doesn't really matter
                                $annot.=sprintf(' /Dest [%d 0 R /XYZ 0 %.3f null]>>',1+2*$l[0],$htarg-$l[1]*$this->k);
                            }
                            $this->_out($annot);
                            $this->_out('endobj');
                        }
                    }
*/

                    /*-- ANNOTATIONS --*/
/*                    if(isset($this->PageAnnots[$n])) {
                        foreach ($this->PageAnnots[$n] as $key => $pl) {
                            $this->_newobj();
                            $annot='';
                            $pl['opt'] = array_change_key_case($pl['opt'], CASE_LOWER);
                            $x = $pl['x'];
                            if ($this->annotMargin <> 0 || $x==0 || $x<0) {	// Odd page
                                $x = ($wPt/$this->k) - $this->annotMargin;
                            }
                            $w = $h = ($this->annotSize * $this->k);
                            $a = $x * $this->k;
                            // mPDF 3.0
                            $b = $hPt - ($pl['y']  * $this->k);
                            $rect = sprintf('%.3f %.3f %.3f %.3f', $a, $b-$h, $a+$w, $b);
                            $annot .= '<</Type /Annot /Subtype /Text /Rect ['.$rect.']';
                            $annot .= ' /Contents '.$this->_UTF16BEtextstring($pl['txt']);
                            $annot .= ' /NM ('.sprintf('%04u-%04u', $n, (2000 + $key)).')';
                            $annot .= ' /M '.$this->_textstring('D:'.date('YmdHis'));
                            $annot .= ' /CreationDate '.$this->_textstring('D:'.date('YmdHis'));
                            $annot .= ' /Border [0 0 0]';
                            // mPDF 4.2.018
                            if ($this->PDFA) {
                                $annot .= ' /F 28';
                                $annot .= ' /CA 1';
                            }
                            else if ($pl['opt']['ca']>0) { $annot .= ' /CA '.$pl['opt']['ca']; }

                            $annot .= ' /C [';
                            if (isset($pl['opt']['c']) AND (is_array($pl['opt']['c']))) {
                                foreach ($pl['opt']['c'] as $col) {
                                    $col = intval($col);
                                    $color = $col <= 0 ? 0 : ($col >= 255 ? 1 : $col / 255);
                                    $annot .= sprintf(" %.4f", $color);
                                }
                            }
                            else { $annot .= '1 1 0'; }	// mPDF 4.2.026
                            $annot .= ']';
                            // Usually Author
                            if (isset($pl['opt']['t']) AND is_string($pl['opt']['t'])) {
                                $annot .= ' /T '.$this->_UTF16BEtextstring($pl['opt']['t']);
                            }
                            if (isset($pl['opt']['subj'])) {
                                $annot .= ' /Subj '.$this->_UTF16BEtextstring($pl['opt']['subj']);
                            }
                            $iconsapp = array('Comment', 'Help', 'Insert', 'Key', 'NewParagraph', 'Note', 'Paragraph');
                            if (isset($pl['opt']['icon']) AND in_array($pl['opt']['icon'], $iconsapp)) {
                                $annot .= ' /Name /'.$pl['opt']['icon'];
                            }
                            else { $annot .= ' /Name /Note'; }
                            // mPDF 4.2.027
                            if (!empty($pl['opt']['popup'])) {
                                $annot .= ' /Open true';
                                $annot .= ' /Popup '.($this->n+1).' 0 R';				// mPDF 4.2.027
                            }
                            else { $annot .= ' /Open false'; }	// mPDF 4.2.027
                            $annot .= ' /P 3 0 R';							// mPDF 4.2.027
                            $annot .= '>>';
                            $this->_out($annot);
                            $this->_out('endobj');

                            // mPDF 4.2.027
                            if (!empty($pl['opt']['popup'])) {
                                $this->_newobj();
                                $annot='';
                                if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][0])) { $x = $pl['opt']['popup'][0] * $this->k; }
                                else { $x = $pl['x'] * $this->k; }
                                if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][1])) { $y = $hPt - ($pl['opt']['popup'][1] * $this->k); }
                                else { $y = $hPt - ($pl['y']  * $this->k); }
                                if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][2])) { $w = $pl['opt']['popup'][2] * $this->k; }
                                else { $w = 180; }
                                if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][3])) { $h = $pl['opt']['popup'][3] * $this->k; }
                                else { $h = 120; }
                                $rect = sprintf('%.3f %.3f %.3f %.3f', $x, $y-$h, $x+$w, $y);
                                $annot .= '<</Type /Annot /Subtype /Popup /Rect ['.$rect.']';
                                $annot .= ' /M '.$this->_textstring('D:'.date('YmdHis'));
                                if ($this->PDFA) { $annot .= ' /F 28'; }
                                $annot .= ' /P 3 0 R';
                                $annot .= ' /Parent '.($this->n-1).' 0 R';
                                $annot .= '>>';
                                $this->_out($annot);
                                $this->_out('endobj');
                            }
                        }
                    }
*/                    /*-- END ANNOTATIONS --*/
/*                }
            }
        }
*/
    }
}