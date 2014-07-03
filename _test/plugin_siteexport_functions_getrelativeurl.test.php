<?php

@require_once(DOKU_PLUGIN . 'siteexport/inc/functions.php');

/**
 * @group plugin_siteexport
 * @group plugins
 */
class SiteexportFunctionsGetRelativeURLTest extends DokuWikiTest {

    protected $pluginsEnabled = array('siteexport');

    public function test_functionsExist() {
        $this->assertFileExists(DOKU_PLUGIN . 'siteexport/inc/functions.php', 'The functions.php file could not be found.');
        $this->assertTrue( class_exists('siteexport_functions'), 'The class for the functions could not be found.' );
    }

    /**
     * @depends test_functionsExist
     */    
    public function test_getRelativeURL() {
    
        $functions = new siteexport_functions();
        // $functions->debug->setDebugLevel(1);
        // $functions->debug->setDebugFile('/tmp/siteexport.log');
        
        $testMatrix = array(
        
            // Same directory
            array(
                'base'      => "test/test.html",
                'relative'  => "../test/test2.html",
                'expected'  => "test2.html",
            ),
        
            // Same directory at base
            array(
                'base'      => "test.html",
                'relative'  => "test2.html",
                'expected'  => "test2.html",
            ),
        
            // Different directory
            array(
                'base'      => "test.html",
                'relative'  => "../test/test2.html",
                'expected'  => "test/test2.html",
            ),

            array(
                'base'      => "test/test.html",
                'relative'  => "../test2.html",
                'expected'  => "../test2.html",
            ),

            array(
                'base'      => "test/test.html",
                'relative'  => "../test2/test2.html",
                'expected'  => "../test2/test2.html",
            ),
        );

        foreach($testMatrix as $test) {
            $result = $functions->getRelativeURL($test['relative'], $test['base']);
            $this->assertTrue($test['expected'] == $result, "Result '{$result}' did not match expected result '{$test['expected']}' (base: '{$test['base']}', relative: '{$test['relative']}')");
        }
   }

}
