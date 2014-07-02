<?php

/**
 * @group plugin_siteexport
 * @group plugin_siteexport_functions
 */
class SiteexportFunctionsGetRelativeURLTest extends DokuWikiTest {

    protected $pluginsEnabled = array('siteexport');

    function testAbsolute() {

        $functions = new siteexport_functions();
        
        $testMatrix = array(
        
            array(
                'base'      => "test.html",
                'relative'  => "test2.html",
                'expected'  => "test2.html",
            ),
        
            array(
                'base'      => "test/test.html",
                'relative'  => "test/test2.html",
                'expected'  => "test2.html",
            ),
        
        );

        foreach($testMatrix as $test) {
            $result = $functions->getRelativeURL($test['relative'], $test['base']);
            $this->assertTrue($test['expected'] == $result, "Result '{$result}' did not match expected result '{$test['expected']}' (base: '{$test['base']}', relative: '{$test['relative']}')");
        }
    }
}
