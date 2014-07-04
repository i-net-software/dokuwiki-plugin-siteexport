<?php

/**
 * @group plugin_siteexport
 * @group plugins
 */
class SiteexportJavaScriptEvaluation extends DokuWikiTest {

    protected $pluginsEnabled = array('siteexport');

    public function test_javascript_evaluates() {
        
        $dir = dirname(__FILE__) . '/phantomjs/';
        $compressed = $dir . 'compressed.source.js';
        $uncompressed = $dir . 'uncompressed.source.js';
        
        @unlink($compressed);
        @unlink($uncompressed);
        
        file_put_contents($uncompressed, $this->setUpJavascript(0));
        $this->assertFileExists($uncompressed, "The uncompressed javascript version does not exist.");

        file_put_contents($compressed, $this->setUpJavascript(1));
        $this->assertFileExists($compressed, "The compressed javascript version does not exist.");
        
    }
    
    private function setUpJavascript($compress=1) {
        
        global $conf;
        
        $_SERVER['SERVER_PORT'] = rand();
        $conf['compress'] = $compress;
        
        ob_start();
        js_out();
        $js = ob_get_contents();
        ob_end_clean();
        
        return $js;
    }
}