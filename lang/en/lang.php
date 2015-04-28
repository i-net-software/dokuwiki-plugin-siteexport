<?php
/**
 * english language file
 */
 
// settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';
 
// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'Site Export'; 
 
// custom language strings for the plugin
$lang['absolutePath'] = 'Export Absolute Paths'; 
$lang['pdfExport'] = 'PDF Export'; 
$lang['usenumberedheading'] = 'Numbered Headings'; 
$lang['template'] = 'Export Template' ; 
$lang['exportBody'] = 'Export Body only'; 
$lang['addParams'] = 'Export all parameters (e.g. "do")';
$lang['disableCache'] = 'Disable cache for current request';

$lang['startingNamespace'] = 'Enter your starting Namespace'; 
$lang['selectYourOptions'] = 'Select your Options'; 
$lang['helpCreationOptions'] = 'Select one of the Help Creation Options (optional)'; 
$lang['eclipseDocZip'] = 'Create Eclipse Help'; 
$lang['JavaHelpDocZip'] = 'Create Java Help';
$lang['TOCMapWithoutTranslation'] = 'Remove Translation Root';

$lang['useTocFile'] = 'Use TOC file in Namespace'; 
$lang['emptyTocElem'] = 'Empty Namespaces in TOC'; 
$lang['startProcess'] = 'Start Process'; 
$lang['directDownloadLink'] = 'Direct Download Link'; 
$lang['wgetURLLink'] = 'wget Download URL';
$lang['curlURLLink'] = 'curl Download URL';
$lang['start'] = 'start'; 
$lang['status'] = 'Status'; 
$lang['ns'] = 'Set Namespace';
$lang['ens'] = 'Parent Namespace to export';
$lang['defaultLang'] = 'Default Language for multi-language namespaces';

$lang['disablePluginsOption'] = 'Disable (JS/CSS) Plugins while export'; 

$lang['depthType'] = 'Export Type';
$lang['depth.pageOnly'] = 'this page only';
$lang['depth.allSubNameSpaces'] = 'all sub namespaces';
$lang['depth.specifiedDepth'] = 'specified depth';

$lang['depth'] = 'Depth';
$lang['renderer'] = 'Render Engine';

$lang['exportLinkedPages'] = 'Export Linked Pages';

$lang['canOverwriteExisting'] = 'Overwrite existing Cron Job:';
$lang['cronSaveProcess'] = 'Save as Cron Job';
$lang['cronDescription'] = 'This allows to create cron-based jobs. They will be executed according to the server setting (you need command line access to the cron tool).';
$lang['cronSaveAction'] = 'Save as Cron Job';
$lang['cronDeleteAction'] = 'Delete Cron Job';

$lang['customOptions'] = 'Custom Options';
$lang['customOptionsDescription'] = 'You can add further custom options that will be considered while exporting';
$lang['addCustomOption'] = 'add Option';

$lang['search'] = 'Search';
$lang['toc'] = 'Table of Contents';


$lang['AggregateSubmitLabel'] = 'Download';
$lang['AggragateExportPages'] = 'Starting page for merger:';
$lang['toolbarButton'] = 'Insert Siteexporter';
$lang['useOptionsInEditor'] = 'Use these options and insert {{siteAggregator}} element';
$lang['NoEntriesFoundHint'] = 'No pages have been found for aggregation.';

$lang['js']['loadingpage'] = 'Loading';
$lang['js']['startdownload'] = 'Starting Download';
$lang['js']['downloadfinished'] = 'Finished Download';
$lang['js']['finishedbutdownloadfailed'] = 'Finished but download failed.';
//Setup VIM: ex: et ts=4 enc=utf-8 :
