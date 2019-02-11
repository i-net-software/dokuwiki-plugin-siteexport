<?php
/**
 * Render Plugin for XHTML  without details link for internal images.
 *
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once DOKU_INC . 'inc/parser/xhtml.php';

/**
 * The Renderer
 */
class renderer_plugin_siteexport_pdf extends Doku_Renderer_xhtml {

    public $acronymsExchanged = null;

    private $hasSeenHeader = false;

    private $currentLevel = 0;

    public $levels = array( '======'=>1,
                            '====='=>2,
                            '===='=>3,
                            '==='=>4,
                            '=='=>5
    );

    public $info = array(
                            'cache'      => true, // may the rendered result cached?
                            'toc'        => true, // render the TOC?
                            'forceTOC'   => false, // shall I force the TOC?
                            'scriptmode' => false, // In scriptmode, some tags will not be encoded => '<%', '%>'
    );

    public $headingCount = array(   1=>0,
                                    2=>0,
                                    3=>0,
                                    4=>0,
                                    5=>0
    );

    public function document_start() {
        global $TOC, $ID, $INFO;

        parent::document_start();

        // Cheating in again
        $newMeta = p_get_metadata($ID, 'description tableofcontents', false); // 2010-10-23 This should be save to use
        if (!empty($newMeta) && count($newMeta) > 1) {
            // $TOC = $this->toc = $newMeta; // 2010-08-23 doubled the TOC
            $TOC = $newMeta;
        }
    }

    public function document_end() {

        parent::document_end();

        // Prepare the TOC
        global $TOC, $ID;
        $meta = array();

        // NOTOC, and no forceTOC
        if ($this->info['toc'] === false && !($this->info['forceTOC'] || $this->meta['forceTOC'])) {
            $TOC = $this->toc = array();
            $meta['internal']['toc'] = false;
            $meta['description']['tableofcontents'] = array();
            $meta['forceTOC'] = false;

        } else if ($this->info['forceTOC'] || $this->meta['forceTOC'] || (utf8_strlen(strip_tags($this->doc)) >= $this->getConf('documentlengthfortoc') && count($this->toc) > 1)) {
            $TOC = $this->toc;
            // This is a little bit like cheating ... but this will force the TOC into the metadata
            $meta = array();
            $meta['internal']['toc'] = true;
            $meta['forceTOC'] = $this->info['forceTOC'] || $this->meta['forceTOC'];
            $meta['description']['tableofcontents'] = $TOC;
        }

        // allways write new metadata
        p_set_metadata($ID, $meta);
        $this->doc = preg_replace('#<p( class=".*?")?>\s*</p>#', '', $this->doc);
    }

    public function header($text, $level, $pos) {
        global $conf;
        global $ID;
        global $INFO;

        if ($text)
        {
            $hid = $this->_headerToLink($text, true);

            //only add items within configured levels
            $this->toc_additem($hid, $text, $level);

            // adjust $node to reflect hierarchy of levels
            $this->node[$level-1]++;
            if ($level < $this->lastlevel) {
                for ($i = 0; $i < $this->lastlevel-$level; $i++) {
                    $this->node[$this->lastlevel-$i-1] = 0;
                }
            }
            $this->lastlevel = $level;

            /* There should be no class for "sectioneditX" if there is no edit perm */
            if ($INFO['perm'] > AUTH_READ &&
                $level <= $conf['maxseclevel'] &&
                count($this->sectionedits) > 0 &&
                $this->sectionedits[count($this->sectionedits)-1][2] === 'section') {
                $this->finishSectionEdit($pos-1);
            }

            $headingNumber = '';
            $useNumbered = p_get_metadata($ID, 'usenumberedheading', true); // 2011-02-07 This should be save to use
            if ($this->getConf('usenumberedheading') || !empty($useNumbered) || !empty($INFO['meta']['usenumberedheading']) || isset($_REQUEST['usenumberedheading'])) {

                // increment the number of the heading
                $this->headingCount[$level]++;

                // build the actual number
                for ($i = 1; $i <= 5; $i++) {

                    // reset the number of the subheadings
                    if ($i > $level) {
                        $this->headingCount[$i] = 0;
                    }

                    // build the number of the heading
                    $headingNumber .= $this->headingCount[$i] . '.';
                }

                $headingNumber = preg_replace("/(\.0)+\.?$/", '', $headingNumber) . ' ';
            }

            // write the header
            $this->doc .= DOKU_LF.'<h'.$level;
            $class = array();
            if ($INFO['perm'] > AUTH_READ &&
                $level <= $conf['maxseclevel']) {
                $class[] = $this->startSectionEdit($pos, array( 'target' => 'section', 'name' => $text ) );
            }

            if ( !empty($headingNumber) ) {
                $class[] = 'level' . trim($headingNumber);
                if ( intval($headingNumber) > 1 ) {
                    $class[] = 'notfirst';
                } else {
                    $class[] = 'first';
                }
            }

            if ( !empty($class) ) {
                $this->doc .= ' class="' . implode(' ', $class) . '"';
            }

            $this->doc .= '><a name="'.$hid.'" id="'.$hid.'">';
            $this->doc .= $this->_xmlEntities($headingNumber . $text);
            $this->doc .= "</a></h$level>".DOKU_LF;

        } else if ( $INFO['perm'] > AUTH_READ ) {

            if ( $this->hasSeenHeader ) {
                $this->finishSectionEdit($pos);
            }

            // write the header
            $name = rand() . $level;
            $sectionEdit = $this->startSectionEdit($pos, array( 'target' => 'section_empty', 'name' => $name));
            $this->doc .= DOKU_LF.'<a name="'. $sectionEdit .'" class="' . $sectionEdit . '" ></a>'.DOKU_LF;
        }

        $this->hasSeenHeader = true;
    }

    public function section_open($level) {
        $this->currentLevel = $level;
        parent::section_open($level);
    }

    public function p_open() {
        $this->doc .= DOKU_LF . '<p class="level' . $this->currentLevel . '">' . DOKU_LF;
    }

    public function listu_open($classes = null) {
        $this->doc .= '<ul class="level' . $this->currentLevel . '">' . DOKU_LF;
    }

    public function listo_open($classes = null) {
        $this->doc .= '<ol class="level' . $this->currentLevel . '">' . DOKU_LF;
    }

    public function finishSectionEdit($end = null, $hid = null) {
        return '';
    }

    /**
     * @param string $type
     */
    public function startSectionEdit($start, $data) {
        return '';
    }

    /**
     * Wrap centered media in a div to center it
     */
    public function _media ($src, $title=NULL, $align=NULL, $width=NULL,
                        $height=NULL, $cache=NULL, $render = true) {

        $out = '';
        if($align == 'center'){
            $out .= '<div align="center" style="text-align: center">';
        }

        $out .= parent::_media ($src, $title, $align, $width, $height, $cache, $render);

        if($align == 'center'){
            $out .= '</div>';
        }

        return $out;
    }

    public function internalmedia($src, $title = NULL, $align = NULL, $width = NULL, $height = NULL, $cache = NULL, $linking = NULL, $return = false) {
        global $ID;
        list($src,$hash) = explode('#',$src,2);
        resolve_mediaid(getNS($ID),$src, $exists);

        $noLink = false;
        $render = ($linking == 'linkonly') ? false : true;
        $link = $this->_getMediaLinkConf($src, $title, $align, $width, $height, $cache, $render);

        list($ext,$mime,$dl) = mimetype($src);
        if(substr($mime,0,5) == 'image' && $render){
            $link['url'] = ml($src,array('id'=>$ID,'cache'=>$cache),($linking=='direct'));
            if ( substr($mime,0,5) == 'image' && $linking='details' ) { $noLink = true;}
        } elseif($mime == 'application/x-shockwave-flash' && $render){
            // don't link flash movies
            $noLink = true;
        } else{
            // add file icons
            $class = preg_replace('/[^_\-a-z0-9]+/i','_',$ext);
            $link['class'] .= ' mediafile mf_'.$class;
            $link['url'] = ml($src,array('id'=>$ID,'cache'=>$cache),true);
        }

        if($hash) {
            $link['url'] .= '#'.$hash;
        }

        //markup non existing files
        if (!$exists) {
                $link['class'] .= ' wikilink2';
        }

        //output formatted
        if ($linking == 'nolink' || $noLink) {
            $this->doc .= $link['name'];
        } else {
            $this->doc .= $this->_formatLink($link);
        }
    }

    /**
     * Render an internal Wiki Link
     *
     * $search,$returnonly & $linktype are not for the renderer but are used
     * elsewhere - no need to implement them in other renderers
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    public function internallink($id, $name = NULL, $search = NULL, $returnonly = false, $linktype = 'content') {
        global $conf;
        global $ID;
        // default name is based on $id as given
        $default = $this->_simpleTitle($id);

        // now first resolve and clean up the $id
        resolve_pageid(getNS($ID), $id, $exists);
        $name = $this->_getLinkTitle($name, $default, $isImage, $id, $linktype);
        if (!$isImage) {
            if ($exists) {
                $class = 'wikilink1';
            } else {
                $class = 'wikilink2';
                $link['rel'] = 'nofollow';
            }
        } else {
            $class = 'media';
        }

        //keep hash anchor
        list($id, $hash) = explode('#', $id, 2);
        if (!empty($hash)) $hash = $this->_headerToLink($hash);

        //prepare for formating
        $link['target'] = $conf['target']['wiki'];
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        // highlight link to current page
        if ($id == $ID) {
            $link['pre']    = '<span class="curid">';
            $link['suf']    = '</span>';
        }
        $link['more']   = '';
        $link['class']  = $class;
        $link['url']    = wl($id);
        $link['name']   = $name;
        $link['title']  = $this->_getLinkTitle(null, $default, $isImage, $id, $linktype);

        //add search string
        if ($search) {
            ($conf['userewrite']) ? $link['url'] .= '?' : $link['url'] .= '&amp;';
            if (is_array($search)) {
                $search = array_map('rawurlencode', $search);
                $link['url'] .= 's[]=' . join('&amp;s[]=', $search);
            } else {
                $link['url'] .= 's=' . rawurlencode($search);
            }
        }

        //keep hash
        if ($hash) $link['url'] .= '#' . $hash;

        //output formatted
        if ($returnonly) {
            return $this->_formatLink($link);
        } else {
            $this->doc .= $this->_formatLink($link);
        }
    }

    public function acronym($acronym) {

        if (empty($this->acronymsExchanged)) {
            $this->acronymsExchanged = $this->acronyms;
            $this->acronyms = array();

            foreach ($this->acronymsExchanged as $key => $value) {
                $this->acronyms[str_replace('_', ' ', $key)] = $value;
            }
        }

        parent::acronym($acronym);
    }

    /**
     * @param string $string
     */
    public function _xmlEntities($string) {

        $string = parent::_xmlEntities($string);
        $string = htmlentities($string, 8, 'UTF-8');
        $string = $this->superentities($string);

        if ($this->info['scriptmode']) {
            $string = str_replace(array("&lt;%", "%&gt;", "&lt;?", "?&gt;"),
            array("<%", "%>", "<?", "?>"),
            $string);
        }

        return $string;
    }

    // Unicode-proof htmlentities. 
    // Returns 'normal' chars as chars and weirdos as numeric html entites.

    /**
     * @param string $str
     */
    public function superentities( $str ){
        // get rid of existing entities else double-escape
        $str2 = '';
        $str = html_entity_decode(stripslashes($str),ENT_QUOTES,'UTF-8'); 
        $ar = preg_split('/(?<!^)(?!$)(?!\n)/u', $str );  // return array of every multi-byte character
        foreach ($ar as $c){
            $o = ord($c);
            if ( // (strlen($c) > 1) || /* multi-byte [unicode] */
                ($o > 127) // || /* <- control / latin weirdos -> */
                // ($o <32 || $o > 126) || /* <- control / latin weirdos -> */
                // ($o >33 && $o < 40) ||/* quotes + ambersand */
                // ($o >59 && $o < 63) /* html */

            ) {
                // convert to numeric entity
                $c = mb_encode_numericentity($c, array(0x0, 0xffff, 0, 0xffff), 'UTF-8');
            }
            $str2 .= $c;
        }
        return $str2;
    }

    public function preformatted($text) {
        $this->doc .= '<div class="pre">';
        parent::preformatted($text);
        $this->doc .= '</div>';
    }

    public function _highlight($type, $text, $language = NULL, $filename = NULL, $options = NULL) {
        $this->doc .= '<div class="pre">';
        parent::_highlight($type, $text, $language, $filename, $options);
        $this->doc .= '</div>';
    }

    /**
     * API of the imagereference plugin
     * https://github.com/i-net-software/dokuwiki-plugin-imagereference
     *
     * Allows to specify special imagecaption tags that the renderer (mpdf) can use
     */
    public function imageCaptionTags(&$imagereferenceplugin)
    {
        if ( !$imagereferenceplugin->accepts('table') ) {
            return array( '<figure id="%s" class="imgcaption%s">', // $captionStart
                            '</figure>',                             // $captionEnd
                            '<figcaption class="undercaption">',     // $underCaptionStart
                            '</figcaption>'                          // $underCaptionEnd
                    );
        }

        return null;
    }

    /**
     * Render a page local link
     *
     * @param string $hash       hash link identifier
     * @param string $name       name for the link
     * @param bool   $returnonly whether to return html or write to doc attribute
     * @return void|string writes to doc attribute or returns html depends on $returnonly
     */
    public function locallink($hash, $name = null, $returnonly = false) {
        global $ID;
        $name  = $this->_getLinkTitle($name, $hash, $isImage);
        $hash  = $this->_headerToLink($hash);
        $title = $name;

        $doc = '<a href="#'.$hash.'" title="'.$title.'" class="wikilink1">';
        $doc .= $name;
        $doc .= '</a>';

        if($returnonly) {
          return $doc;
        } else {
          $this->doc .= $doc;
        }
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
