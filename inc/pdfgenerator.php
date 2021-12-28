<?php

if (!defined('DOKU_PLUGIN')) die('meh');

if (!empty($_REQUEST['pdfExport']) && intval($_REQUEST['pdfExport']) == 1 && file_exists(DOKU_PLUGIN . 'dw2pdf/mpdf/mpdf.php')) {

    require_once(DOKU_PLUGIN . 'siteexport/inc/mpdf.php');
    class siteexport_pdfgenerator
    {
        private $functions;

        public function __construct($functions = null)
        {
            $this->functions = $functions;
        }

        public function createPDFFromFile($filename, &$NAME) {
            
            global $INPUT;

            if (!preg_match("/" . $this->functions->settings->fileType . "$/", $NAME)) {
                $this->functions->debug->message("Filetype " . $this->functions->settings->fileType . " did not match filename '$NAME'", null, 4);
                return false;
            }

            $mpdf = new siteexportPDF($this->functions->debug);

            if (!$mpdf) {
                $this->functions->debug->message("Could not instantiate MPDF", null, 4);
                return false;
            }

            $html = @file_get_contents($filename);

            if (!strstr($html, "<html")) {
                $this->functions->debug->message("Filecontent had no HTML starting tag", null, 4);
                return false;
            }

            // Save HTML too
            $this->functions->debug->message("Arranging HTML", null, 2);
            $this->arrangeHtml($html, 'bl,acronym');
            $this->functions->debug->message("Done arranging HTML:", $html, 1);
            
            $mpdf->debug = false;
            $mpdf->list_indent_first_level = 1; // Indents the first level of lists.

            $mpdf->usepre = false;
            $mpdf->margin_bottom_collapse = true;
            $mpdf->SetDisplayMode('fullpage');
            $mpdf->restoreBlockPageBreaks = true;

            $mpdf->dpi = $INPUT->int('dpi', 96, true);
            $mpdf->img_dpi = $INPUT->int('dpi', 96, true);

            $mpdf->setBasePath(empty($this->functions->settings->depth) ? './' : $this->functions->settings->depth);

            $mpdf->ignore_invalid_utf8 = true;
            $mpdf->mirrorMargins = $this->functions->getConf('useOddEven'); // don't mirror margins
            $mpdf->setAutoTopMargin = 'pad';
            $mpdf->setAutoBottomMargin = 'pad';

            $mpdf->WriteHTML($html);
            $mpdf->Output($filename, "F");

            return $html;
        }

        private function arrangeHtml(&$html, $norendertags = '')
        {
            global $conf;

            // add bookmark links
            $html = preg_replace_callback("/<h(\d)(.*?)>(.*?)<\/h\\1>/s", array($this, '__pdfHeaderCallback'), $html);
            $html = preg_replace_callback("/<\/div>\s*?<h({$conf['plugin']['siteexport']['PDFHeaderPagebreak']})(.*?)>/s", array($this, '__pdfHeaderCallbackPagebreak'), $html);
            $html = preg_replace("/(<img.*?mediacenter.*?\/>)/", "<table style=\"width:100%; border: 0px solid #000;\"><tr><td style=\"text-align: center\">$1</td></tr></table>", $html);

            // Remove p arround img and table
            $html = preg_replace("/<p[^>]*?>(\s*?<img[^>]*?\/?>\s*?)<\/p>/s", "$1", $html);
            $html = preg_replace("/<p[^>]*?>(\s*?<table.*?<\/table>\s*?)<\/p>/s", "$1", $html);
            $html = preg_replace_callback("/<pre(.*?)>(.*?)<\/pre>/s", array($this, '__pdfPreCodeCallback'), $html);
            $html = preg_replace_callback("/<a href=\"mailto:(.*?)\".*?>(.*?)<\/a>/s", array($this, '__pdfMailtoCallback'), $html);
            /**/

            $standardReplacer = array(
            // insert a pagebreak for support of WRAP and PAGEBREAK plugins
                                    '<br style="page-break-after:always;">' => '<pagebreak />',
                                    '<div class="wrap_pagebreak"></div>' => '<pagebreak />',
                                    '<sup>' => '<sup class="sup">',
                                    '<sub>' => '<sub class="sub">',
                                    '<code>' => '<code class="code">'
            );
            $html = str_replace(array_keys($standardReplacer), array_values($standardReplacer), $html);

            // thanks to Jared Ong
            // Customized to strip all span tags so that the wiki <code> SQL would display properly
            $norender = explode(',', $norendertags);
            $html = $this->strip_only($html, $norender); //array('span','acronym'));
            $html = $this->strip_htmlencodedchars($html);
            // Customized to strip all span tags so that the wiki <code> SQL would display properly
        }

        private function __pdfMailtoCallback($DATA) {
            if ($DATA[1] == $DATA[2]) {
                $DATA[2] = $this->deobfuscate($DATA[2]);
            }
            $DATA[1] = $this->deobfuscate($DATA[1]);
            return "<a href=\"mailto:{$DATA[1]}\">{$DATA[2]}</a>";
        }

        private function __pdfPreCodeCallback($DATA) {

            $code = nl2br($DATA[2]);
            $code = preg_replace_callback("/(^|<br \/>)(\s+)(\S)/s", array($this, '__pdfPreWhitespacesCallback'), $code);

            return "\n<pre" . $DATA[1] . ">\n" . $code . "\n</pre>\n";
        }

        private function __pdfPreWhitespacesCallback($DATA) {
            return $DATA[1] . "\n" . str_repeat("&nbsp;", strlen($DATA[2])-($DATA[2][0] == "\n" ? 1 : 0)) . $DATA[3];
        }

        private function __pdfHeaderCallback($DATA) {
            $contentText = htmlspecialchars_decode(preg_replace("/<\/?.*?>/s", '', $DATA[3]), ENT_NOQUOTES); // 2014-07-23 Do not encode again. or &auml; -> &amp;auml;
            return '<h' . $DATA[1] . $DATA[2] . '><tocentry content="' . $contentText . '" level="' . ($DATA[1]-1) . '" /><bookmark content="' . $contentText . '" level="' . ($DATA[1]-1) . '" />' . $DATA[3] . '</h' . $DATA[1] . '>';
        }

        private function __pdfHeaderCallbackPagebreak($DATA) {
            return '</div>' . "\r\n" . '<pagebreak />' . "\r\n\r\n" . '<h' . $DATA[1] . $DATA[2] . '>';
        }
        // thanks to Jared Ong
        // Custom function for help in stripping span tags
        private function strip_only($str, $tags) {
            if (!is_array($tags)) {
                $tags = (strpos($str, '>') !== false ? explode('>', str_replace('<', '', $tags)) : array($tags));
                if (end($tags) == '') array_pop($tags);
            }

            foreach ($tags as $tag) $str = preg_replace('#</?' . $tag . '[^>]*>#is', '', $str);
            return $str;
        }
        // Custom function for help in stripping span tags

        // Custom function for help in replacing &#039; &quot; &gt; &lt; &amp;
        private function strip_htmlencodedchars($str) {
            $str = str_replace('&#039;', '\'', $str);
            return $str;
        }
        // Custom function for help in replacing &#039; &quot; &gt; &lt; &amp;

        /**
         * return an de-obfuscated email address in line with $conf['mailguard'] setting
         */
        private function deobfuscate($email) {
            global $conf;

            switch ($conf['mailguard']) {
                case 'visible' :
                    return /** @scrutinizer ignore-call */ strtr($email, array(' [at] ' => '@', ' [dot] ' => '.', ' [dash] ' => '-'));

                case 'hex' :
                    $encode = '';
                    $len = strlen($email);
                    for ($x = 0; $x < $len; $x += 6) {
                        $encode .= chr((int)hexdec($email[$x+3] . $email[$x+4]));
                    }
                    return $encode;

                case 'none' :
                default :
                    return $email;
            }
        }
    }
}
