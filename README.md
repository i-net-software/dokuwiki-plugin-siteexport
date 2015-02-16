#Dokuwiki Site Export

Site Export is an admin plugin that offers a convenient way to download your dokuwiki site as a standalone version. It cycles through your namespaces - a starting point can be given at run-time - and packs the result into a zip file.
The result can be unpacked at any place and viewed without having an internet connection. That makes the plugin perfect for providing static documentation to customers on a CD or DVD.

[![Build Status](https://travis-ci.org/i-net-software/dokuwiki-plugin-siteexport.svg?branch=master)](https://travis-ci.org/i-net-software/dokuwiki-plugin-siteexport)

##Requirements

 * [DokuWiki](http://dokuwiki.org) version **Weatherwax**, **Binky** and newer
 * You need to log in as administrator to have access to the siteexport plugin
 * You have to have the [zip compression library of your php](http://de.php.net/manual/en/book.zip.php) installation activated.
 * [dw2pdf](https://www.dokuwiki.org/plugin:dw2pdf) for PDF export options
 * a writable <code>/inc/preload.php</code> file for template switching

##Configuration

This is about the Admin --> Configuration Manager page.

 * **Default Export Depth:**<br>
 How far in should the export go. This option will be used when selecting "_specific depth_" as _Export Type_.
 * **Try to export non-public pages:**<br>
 SiteExport only allows to export public pages. This option will allow to export non-public pages that the currently logged in user has access too as well. **(no yet implemented)**
 * **Wiki Path and name for exported ZIP file:**<br>
 DokuWiki namespace and file name that will be used to create temporary files.
 * **Pattern to exclude resources:**<br>
 A regular expression to define paths that should not be exported
 * **Maximum script execution time:**<br>
 Defines an execution time in seconds for how long the script may run while exporting a site via URL Request or wget/curl request. Due to PHP settings this may be very limited and if you export a very large site or namespace the script will time out. This option will take care of redirecting the request as many times as needed until the export is finished for all pages (the time should be lonf enough to have at least one page exported).
 * **Debug Level:**<br>
 Level of Debug during export job. This may be important to find errors for the support.
 * **Debug File:**<br>
 Where will the debug log be written to? It has to be a writable destination.
 * **Cache time for export:**<br>
 The siteexport uses its own cache timer to determine when an export should be discarded.

##How to export pages

SiteExport is only available from the Admin menu at the _Additional Plugins_ section. When starting of from the page you want to export, simply go to the export menu, and hit _start_.

###Enter your starting Namespace

Basic export options

####Set Namespace
The namespace/page you actually want to export. This will be predefined with the page you currently visited.
 
####Parent Namespace to export
By default this is the same namespace/page that you are going to export. That will result in a flat structure, with the pages at the top level.

You can define a higher namespace which will result in the structure below being exported with potentially empty folders but habing the lib (plugins, template) directories beeing at top level.

This is usefull for exporting translated namespaces starting with the root of the translation.

####Export Type
How many pages should be exported?

  * **This page only:**<br>
  Attemps to only export this one page.
  * **All sub namespaces:**<br>
  Exports everything below the defined namespace
  * **Specific depth:**<br>
  Exports everything below the defined namespace but only for the defined depth. The depth means how many namespaces it will go down.

#####Depth
Number of namespaces to go down into.

#####Export Linked Pages
Will export linked pages outside or even deeper of the defined namespace as well

###Select your Options

####Export Absolute Paths

#### Export Body only
Adds the option for renderes to only export the inner body other than exporting the whole page.

####Export all parameters (e.g. "do")
Adds all parameters to the links in exported pages - which may make sense when using JavaScript that relies on the links

####Render Engine
By default the engine of the DokuWiki. This allows exporting the pages with other renderers, e.g. the siteexport_pdf (derived from dw2pdf) to have pages in PDF file format.

####Export Template
**Only available if <code>inc/preload.php</code> is writable.**<br>
Allows to export the pages with a different template than the default one.

####PDF Export
**Only available if the dw2pdf plugin is installed.**<br>
Exports the pages into PDF files, one per page. There are options ([TOC](#Table Of Contents definition)) to export multiple pages into one large PDF.

####Numbered Headings
**Only available if the dw2pdf plugin is installed.**<br>
Adds a number to each heading. Usefull for a Table Of Contents inside the PDF

###Select one of the Help Creation Options (optional)
This is totaly optional.

####Create Eclipse Help:
Allows the creation of <code>context.xml</code> and <code>map.xml</code> files that can be used by Eclipse and its Plugins.

####Create Java Help:
Allows the creation of <code>tox.xml</code> and <code>map.xml</code> files that can be used by Java and the Java Help implementation.

####Use TOC file in Namespace
If you do not want the export to be structured like your DokuWiki is, you can create a file called <code>toc</code> in the namespace and create a custom structure that will be used instead.

This is great for having all the chapters of a documentation in their own file and exporting them into PDF as a single file.

See [Table Of Contents definition](#table-of-contents-definition)

###Disable (JS/CSS) Plugins while export
The checkboxes stand for each plugin. By checking it the plugin will be disabled temporarily and therefore not generate any CSS or JS output.

This is great for a static export that does not need any other or only some plugins. Be adviced that disabling plugins might improve the speed of PDF export.

###Custom Options
Here you can add additional variables that will be given to exported page. This can help to create content dynamically when using other plugins or PHP execution.

Simply hit _add Option_ for a new _name_ / _value_ field, enter the variables name and value. Done.

###Start Process
The three links are convenience links. They will be regenerated by every change of any option. They reflect static URLs that can be copied and used e.g. for _ant_ jobs.

Now: Hit start and your pages will be exported.

###Status
Reflects what is currently going on and will display errors that occur during exporting or changing options.

###Save as Cron Job
If your configuration directory is writable - which it should after setup, you can save your current setup here.

You can show what has been saved, view them, delete them and re-run them.

If you have CLI access (terminal or whatever) and cron access to your server, you can add the <code>cron.php</code> file to schedule runs of your cron jobs.


##Table Of Contents definition
If you do not want the export to be structured like your DokuWiki is, you can create a file called <code>toc</code> in the namespace and create a custom structure that will be used instead.

This is great for having all the chapters of a documentation in their own file and exporting them into PDF as a single file.

The structure is basically a list of links:

<pre>
&lt;toc&gt;
  * [[.:index|Index of the page]]
    * [[.:sub:index|Index of the sub namespace]]
      * [[.:sub:sub:index|Index of the sub/sub namespace]]
    * [[.:sub:page|Page in the sub namespace]]
  * [[.:another-page|Another page]]
    * [[.:another-sub:index|Index of another sub namespace]]
&lt;/toc&gt;
</pre>

The &lt;toc&gt; tag support several options:

Option | Behavior
---- | ----
notoc | hide the user defined TOC in the document
description | display the description abstract below of the linked page below the link (usefull together with:`~~META:description abstract=This is my abstract.~~`
merge | this will merge all the defined documents from the TOC into the current document.
mergeheader | this will, as addition to merge, merge all headers starting with the first document (new headers of later documents will be appended at the end, the will not be sorted alphabetically)
pagebreak | inserts a pagebreak after each page defined by the namespace

You have to define the options like this: <code>&lt;toc notoc merge&gt;</code>

##Siteexport Aggregator
There is the additional syntax: aggregator. This allows an in-page selection of an ordered list of pages in the current namespace and sub-namespaces. Once selected and submitted, that page will be generated with the options provided - and merged back up the list (it actually starts merging top down). (What?!)

The Syntax is (and can be used multiple times per document):

<pre>
{{siteexportAGGREGATOR [options]}}
</pre>

 * This will actually create a `<toc>` internally, using the options `merge` and `mergeheader`
 * Without options it will generate a dropdown-list of all pages in the namespace (except the current) one
 * The list will be ordered by a meta key `mergecompare` which has to be added via the META plugin.
 * You can create an element with predefined options using the editor button.
 * There is one additional option: `exportSelectedVersionOnly` - if set it will only export this one selected entry. It will then export this one page with the metadata of the page that has the aggregator.
