<?php

$meta['allowallusers'] = array('onoff');
$meta['depth']  = array('numeric');
$meta['skipacl'] = array('onoff');
$meta['zipfilename'] = array('string');
$meta['exclude'] = array('string');
$meta['max_execution_time'] = array('numeric');
$meta['zipfilename'] = array('string');
$meta['ignoreNon200'] = array('onoff');
$meta['ignoreAJAXError'] = array('onoff');


$meta['debugLevel']  = array('multichoice','_choices' => array('5','4','3','2','1'));
$meta['debugFile'] = array('string');

$meta['cachetime'] = array('numeric');

$meta['PDFHeaderPagebreak'] = array('numeric');

$meta['useOddEven'] = array('onoff');

$meta['defaultAuthenticationUser'] = array('string');
$meta['defaultAuthenticationPassword'] = array('password');

//Setup VIM: ex: et ts=2 enc=utf-8 :
