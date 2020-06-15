/* DOKUWIKI:include jquery.filedownload.js */

/** global: DOKU_BASE */
/** global: LANG */
/** global: NS */
/** global: JSINFO */
/** global: opener */
// Siteexport Admin Plugin Script
(function($){
    $(function(){
            
        if ( !$('form#siteexport, form#siteexport_site_aggregator, form#siteexport_siteexporter').length ) {
            return;
        }
        
        if (!window.location.origin) {
            window.location.origin = window.location.protocol + "//" + window.location.hostname + (window.location.port ? ':' + window.location.port: '');
        }
        
        var SiteexportAdmin = function() {
        };

        (function(_){
            
            _.url = DOKU_BASE + 'lib/exe/ajax.php';
            _.aggregateForm = $('form#siteexport_site_aggregator, form#siteexport_siteexporter');
            _.suspendGenerate = _.aggregateForm.length > 0;
            _.allElements = 'form#siteexport :input:not([readonly]):not([disabled]):not([type=submit]):not(button):not(.dummy), form#siteexport_site_aggregator :input:not([type=submit]):not(button), form#siteexport_siteexporter :input:not([type=submit]):not(button)';
            _.isManager = $('div#siteexport__manager').length > 0;
            _.forbidden_options = [ 'call', 'sectok' ];

            _.generate = function() {
                
                if ( _.suspendGenerate || _.isManager ) { return; }
                
                this.resetDataForNewRequest();

                _.throbber(true);
                $.post( _.url, _.settings('__siteexport_generateurl'), function(data) {
                    data = data.split("\n");
                    $('#copyurl').val(data[0]);
                    $('#wgeturl').val(data[1]);
                    $('#curlurl').val(data[2]);
                }).fail(function(jqXHR){
                    _.errorLog(jqXHR.responseText);
                }).always(function(){
                    _.throbber(false);
                });
            };
            
            _.run = function() {
            
                this.resetDataForNewRequest();
            
                if ( _.isManager && !(typeof opener === "undefined") ) {
                
                    var settings = $.param(_.cleanSettings()).split('&').join(' ');
                    if ( settings.length > 0 ) { settings = ' ' + settings; }
                    
                    var edid = String.prototype.match.call(document.location, new RegExp("&edid=([^&]+)"));
                    opener.insertTags(edid ? edid[1] : 'wiki__text', '{{siteexportAGGREGATOR' + settings + '}}','','');

                    window.close();
                    opener.focus();
                    return;
                }

                _.throbber(true);
                $.post( _.url, _.settings('__siteexport_getsitelist'), function(data) {
                    data = data.split("\n");

                    _.pattern = data.shift();
                    _.zipURL = data.shift();

                    _.pageCount = data.length - 1; // starting at 0
                    _.currentCount = 0;

                    _.allPages = data;
                    _.status(_.pages());
                    _.nextPage();
                }).fail(function(jqXHR){
                    _.errorLog(jqXHR.responseText);
                }).always(function(){
                    _.throbber(false);
                });
            };
            

            _.aggregatorStatus = null;
            _.runAggregator = function() {
                
                this.resetDataForNewRequest();
                
                if ( _.aggregatorStatus == null ) {
                    _.aggregatorStatus = $('<span id="siteexport__out"/>').appendTo("form#siteexport_site_aggregator, form#siteexport_siteexporter");
                }

                _.status(LANG.plugins.siteexport.loadingpage);
                _.aggregatorStatus.removeClass('error').show();
                _.aggregateForm.addClass('loading');
                var settings = _.settings('__siteexport_aggregate');
                var throbber = $('form#siteexport_site_aggregator :input[name=baseID], form#siteexport_site_aggregator :input[type=submit], form#siteexport_siteexporter :input[type=submit]').prop('disabled', true);
                $.post( _.url, settings, function(data) {

                    if ( data.match( new RegExp( 'mpdf error', 'i' ) ) ) {
                        _.aggregatorStatus.addClass('error');
                        _.status(data);
                    } else {
                        _.downloadFile({
                                id : 'siteexport_site_aggregator_downloader',
                                src: window.location.origin + data,
                                root: 'div.siteexporter',
                                timeout: function(){
                                    _.aggregatorStatus.hide();
                                }
                        });
                    }

                }).fail(function(jqXHR){
                    _.aggregatorStatus.addClass('error');
                    _.status(jqXHR.responseText.replace("\n", "<br/>"));
                }).always(function(){
                    throbber.prop('disabled', false);
                    _.aggregateForm.removeClass('loading');
                });
            };
            
            _.downloadFile = function(iframeProps) {

                    _.status(LANG.plugins.siteexport.startdownload);
                    _.aggregateForm.addClass('download');
                    if ( $.fileDownload ) {
                        
                        $.fileDownload(iframeProps.src).done(function(){
                            _.status(LANG.plugins.siteexport.downloadfinished);

                            if ( typeof iframeProps.timeout == 'function' ) {
                                window.setTimeout(iframeProps.timeout, 2000);
                            }
                            
                        }).fail(function(){
                            _.error(LANG.plugins.siteexport.finishedbutdownloadfailed);
                        }).always(function(){
                            _.aggregateForm.removeClass('download');
                        });
                        
                        return;
                    }

                    var frameQuery = "iframe#" + iframeProps.id;
                    var frame = $(frameQuery);
                    if ( frame.length == 0 ) {
                        frame = $('<iframe/>')
                        .hide()
                        .appendTo(iframeProps.root)
                        .prop({
                            type : 'application/octet-stream',
                            id : iframeProps.id
                        });
                    }
                    
                    // Downloads do not generate a load event
                    frame.load(function(){
                        _.status(LANG.plugins.siteexport.downloadfinished);
                        
                        // This must only happen when not downloading, meaning we have a PDF file.
                        // ENSURE THIS IS THE ONLY CASE!
                        // frame.remove();
                        
                        if ( $.popupviewer ) {

                            var clone = frame.clone().css({
                                border: 'none'
                            }).show();
                            
                            frame.remove();
                            _.aggregateForm.removeClass('download');

                            var viewer = new $.popupviewer();
                            viewer.showViewer();
                            
                            $("div#popupviewer div.content").html(clone);
                            viewer.resizePopup($(window).width(), $(window).height(), null, clone, false, false);
                        } else {
                            // No Popup? Open right here
                            document.location.href = iframeProps.src;
                        }
                    });
                    
                    frame.attr('src', iframeProps.src);
                    if ( typeof iframeProps.timeout == 'function' ) {
                        window.setTimeout(iframeProps.timeout, 2000);
                    }

                       $(window).unload(function(){
                           // last resort or the frame might reload
                           frame.remove();
                       });
            };
                    
            _.addSite = function(site) {
        
                var settings = _.settings('__siteexport_addsite');
                settings.push({
                    name: 'site',
                    value: site
                },{
                    name: 'pattern',
                    value: this.pattern
                },{
                    name: 'base',
                    value: DOKU_BASE
                });

                _.throbber(true);
                $.post( _.url, settings, function(data) {
                    _.zipURL = data.split("\n").pop();
                    _.nextPage();
                }).fail(function(jqXHR){
                    _.errorLog(jqXHR.responseText);
                    _.errorCount++;
                }).always(function(){
                    _.throbber(false);
                });
            };
            
            _.nextPage = function() {
                if (!this.allPages) {
                    return;
                }
        
                var page = this.allPages.shift();
        
                if (!page) {
                    if (_.zipURL != "" && _.zipURL != 'undefined' && typeof _.zipURL != 'undefined' ) {

                        _.downloadFile({
                                id : 'siteexport_downloader',
                                src: window.location.origin + _.zipURL,
                                root: '#siteexport'
                        });

                    } else {
                        _.status(LANG.plugins.siteexport.finishedbutdownloadfailed);
                        _.errorLog(_.zipURL);
                    }
                    return;
                }
        
                if (!page) {
                    this.nextPage();
                }
        
                _.status('Adding "' + page + '" ' + this.pages(this.currentCount++));
                _.addSite(page);
            };
            
            _.pages = function() {
                return '( '
                        + _.currentCount
                        + ' / '
                        + _.pageCount
                        + (_.errorCount && _.errorCount != 0 ? ' / <span style="color: #a00">' + _.errorCount + '</span>'
                                : '') + ' )';
            };
            
            _.status = function(text) {
                $('#siteexport__out').html(text).removeClass('error');
            };

            _.error = function(text) {
                $('#siteexport__out').html(text).addClass('error');
            };
            
            _.settings = function(call) {
                var settings = $(_.allElements).serializeArray();

                /* Because serializeArray() ignores unset checkboxes and radio buttons: */
                settings = settings.concat(
                    $('form#siteexport label.sendIfNotSet input[type=checkbox]:not(:checked)').map(function() {
                        return { "name": this.name, "value": '0' /* false */ }
                    }).get()
                );

                if (call) { settings.push({ name: 'call', value: call}); }
                if ( $('input#pdfExport:checked').length > 0 ) { settings.push({ name: 'renderer', value: 'siteexport_pdf'}); } // is disabled and would not get pushed
                return settings;
            };
            
            _.cleanSettings = function(call) {
                
                return _.settings(call).filter(function(element){
                    
                    if ( element.value == NS || element.value == JSINFO.id || element.value == JSINFO.namespace ) { element.name = null; }
                    if ( !isNaN(element.value) ) { element.value = parseInt(element.value); }
                    return element.name && _.forbidden_options.indexOf(element.name) < 0 && (element.value.length > 0 || (!isNaN(element.value) && element.value > 0));
                });
            };
            
            /**
             * Display the loading gif
             */
            _.throbberCount = 0;
            _.throbber = function(on) {
                
                _.throbberCount += (on?1:-1);
                $('#siteexport__throbber').css('visibility', _.throbberCount>0 ? 'visible' : 'hidden');
            };

            _.resetDataForNewRequest = function() {
                
                this.pageCount = 0;
                this.currentCount = 0;
                this.errorCount = 0;
                this.allPages = [];
                this.zipURL = '';
                this.pattern = '';

                this.status('');
                this.resetErrorLog();
            };

            /**
             * Log errors into container
             */
            _.errorLog = function(text) {
        
                if (!text) {
                    return;
                }
        
                if (!$('#siteexport__errorlog').length) {
                    $('#siteexport__out').parent().append($('<div id="siteexport__errorlog"></div>'));
                }
        
                var msg = text.split("\n");
                for ( var int = 0; int < msg.length; int++) {
        
                    var txtMsg = msg[int];
                    txtMsg = txtMsg.replace(new RegExp("^runtime error:", "i"), "");
        
                    if (txtMsg.length == 0) {
                        continue;
                    }

                    $('#siteexport__errorlog').append($('<p></p>').text(txtMsg.replace(new RegExp(
                            "</?.*?>", "ig"), "")));
                }
            };
        
            _.resetErrorLog = function() {
                $('#siteexport__errorlog').remove();
            };
            
            _.toggleDisableAllPlugins = function(input) {
                $('#siteexport :input[name=disableplugin\\[\\]]:not([disabled=disabled])').prop('checked', input.checked);
            };

    
            _.addCustomOption = function(nameVal, valueVal) {
                
                if ( typeof nameVal == 'undefined' )
                {
                    nameVal = 'name';
                }
                
                if ( typeof valueVal == 'undefined' )
                {
                    valueVal = 'value';
                }
        
                
                var regenerate = function(event) {
                    event.stopPropagation();
                    _.generate();
                };

                var customOption = $('<input type="hidden"/>').attr({ name: nameVal, value: valueVal});
                var name = $('<input/>').addClass('edit dummy').attr({ value: nameVal}).change(function(event)
                {
                    customOption.attr({ name: this.value }); regenerate(event);
                }).click(function(){this.select();});
                var value = $('<input/>').addClass('edit dummy').attr({ value: valueVal}).change(function(event)
                {
                    customOption.attr({ value: this.value }); regenerate(event);
                }).click(function(){this.select();});
                
                
                $('<li/>').append(name).append(value).append(customOption).appendTo('#siteexport__customActions');
            };
            
            _.updateValue = function( elem, value ) {
                if ( !elem.length ) {
                    return;
                }
                switch(elem[0].type) {
                    case 'checkbox': elem.prop('checked', elem.val() == value); break;
                    case 'select-one': 
                    case 'select': {
                    
                        var selected = false;
                        elem.find('option').each(function(){
                            if ( $(this).val() == value ) {
                                $(this).prop('selected', true);
                                selected = true;
                                return false;
                            }
                            return true;
                        });
                        
                        if ( !selected && !isNaN(value) ) {
                            elem.find('option:eq('+value+')').prop('selected', true);
                        }
                        
                         break;
                    }
                    default: elem.val(value);
                }
            };
            
            _.setValues = function(values) {
                
                $(_.allElements + ':not(:checkbox)').val(null);
                $(_.allElements + ':checkbox').prop('checked', false);
                $('#siteexport__customActions').html("");
                
                _.suspendGenerate = true;
                for ( var node in values ) {
                    
                    if ( !values.hasOwnProperty(node)) {
                        continue; // Skip keys from the prototype.
                    }

                    var value = values[node];
                    if ( typeof value == 'object' ) {
                        for ( var val in value ) {
                            if ( !value.hasOwnProperty(val)) {
                                continue; // Skip keys from the prototype.
                            }
                            _.updateValue($('#siteexport #'+node+'_'+value[val]+':input[name='+node+'\\[\\]]'), value[val]);
                        }
                    } else {
                        _.updateValue($('#siteexport :input[name='+node+']'), value);
                    }
                }
                
                // Custom Options
                for ( var index in values['customoptionname'] ) {
                    
                    if ( !values['customoptionname'].hasOwnProperty(index)) {
                        continue; // Skip keys from the prototype.
                    }

                    try {
                        _.addCustomOption(values['customoptionname'][index], values['customoptionvalue'][index]);
                    } catch (e) {
                        _.errorLog(e);
                    }
                }
                                
                _.suspendGenerate = false;
            };
            
        }(SiteexportAdmin.prototype));
        
        var __siteexport = null;
        $.siteexport = function() {
            if ( __siteexport == null ) {
                __siteexport = new SiteexportAdmin();
            }
            
            return __siteexport;
        };
        
        $.siteexport().generate();
        $('#siteexport :input').each(function(){
            $(this).change(function(event){
                event.stopPropagation();
                $.siteexport().generate();
            });
        });
        
        $('form#siteexport :input[type=submit][name~=do\\[siteexport\\]]').click(function(event){
            event.stopPropagation();
            $.siteexport().run();
            return false;
        });
        
        $('form#siteexport_site_aggregator :input[type=submit][name~=do\\[siteexport\\]], form#siteexport_siteexporter :input[type=submit][name~=do\\[siteexport\\]]').click(function(event){
            event.stopPropagation();
            $.siteexport().runAggregator();
            return false;
        });
        
        $('form#siteexport #depthType:input').change(function(event){
            event.stopPropagation();
            if ( parseInt($(this).val()) == 2 ) {
                $('div#depthContainer').show();
            } else {
                $('div#depthContainer').hide();
            }
        });
        

        $('form#siteexport #pdfExport:input').change(function(event){
            event.stopPropagation();
            $('form#siteexport #renderer').find('option[value=siteexport_pdf]').prop('selected', this.checked);
            $('form#siteexport #renderer').prop('disabled', this.checked);
        });
        
        $('form#siteexport select#renderer').change(function(event){
            event.stopPropagation();
            $('form#siteexport #pdfExport:input, form#siteexport select#template').prop('disabled', $(this).prop('value') == 'dw2pdf');
        });

        
        $('form#siteexport #disableall:input').change(function(event){
            event.stopPropagation();
            $.siteexport().toggleDisableAllPlugins(this);
        });
        
        $('form#siteexport :input[name=disableplugin\\[\\]]').change(function(event){
            event.stopPropagation();
            
            if ( !this.checked && $('form#siteexport #disableall:input').prop('checked') ) {
                $('form#siteexport #disableall:input').prop('checked', false);
            }
        });
        
        $('form#siteexport :input[type=submit][name=do\\[addoption\\]]').click(function(event){
            event.stopPropagation();
            $.siteexport().addCustomOption();
            return false;
        });
    });
})(jQuery);

var copyMapIDToClipBoard = function() {
    var $this = jQuery(this);
    var $mapID = $this.find('span.mapID');
    var range, selection, ok;

    if (window.getSelection && document.createRange) {
        selection = window.getSelection();
        range = document.createRange();
        range.selectNodeContents($mapID.get(0));
        selection.removeAllRanges();
        selection.addRange(range);
    } else if (document.selection && document.body.createTextRange) {
        range = document.body.createTextRange();
        range.moveToElementText($mapID.get(0));
        range.select();
    }
    // Use try & catch for unsupported browser
    try {
        // The important part (copy selected text)
        ok = document.execCommand('copy');
    } catch (err) {
        // Ignore if it does not work.
    }
    
    if (ok) {
        $mapID.addClass('done');
        setTimeout(function(){
            $mapID.removeClass('done');
        }, 1000);
    }
};