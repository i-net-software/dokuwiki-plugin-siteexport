(function($){
	
	var popupviewer = function() {
	};
	
	/* singleton */
	var instance = null;
	$.popupviewer = function() {
		return instance || (instance = new popupviewer());
	};
	
	// Static functions
	(function(_){

		var viewer = null;
		var content = null;
		var additionalContent = null;
		var BASE_URL = DOKU_BASE + 'lib/exe/ajax.php';
		var viewerIsFixed = false;
		var next = null;
		var previous = null;
		var internal = {};
		
		_.popupImageStack = null;
		
		internal.log = function(message) {
			// console.log(message);	
		};
		
		_.showViewer = function() {
			
			if ( viewer == null ) {
				
				viewer = $('<div id="popupviewer"/>').click(_.hideViewer).appendTo('body');
				content = $('<div class="content"/>').click(function(e){e.stopPropagation()});
				content.current = $();

				additionalContent = $('<div class="additionalContent dokuwiki"/>');
				viewerIsFixed = viewer.css('position');

				$('<div class="controls"/>').
					append(content).
					append(additionalContent).
					append(previous = $('<a class="previous"/>').click({'direction': -1}, _.skipImageInDirection)).
					append(next = $('<a class="next"/>').click({'direction': 1}, _.skipImageInDirection)).
					append($('<a class="close"/>').addClass('visible').click(_.hideViewer)).
					appendTo(viewer);
					
				$(document).keydown(internal.globalKeyHandler);
				
			}
			
			content.empty();
			additionalContent.empty();
			$('body').css('overflow', 'hidden');
			viewer.show();
			return _;
		};
		
		_.hideViewer = function(e, finalFunction) {
			if ( viewer != null ) {
				$('body').css('overflow', 'auto');

				additionalContent.animate({
					opacity: 0,
					height: 0
				});

				content.animate({
					width : 208,
					height : 13,
				}).parent('.controls').animate({
					top : '50%',
					left : '50%',
					'margin-left' : -104
				}).parent('#popupviewer').animate({
					opacity: finalFunction ? 1 : 0
				}, function(){
					viewer.hide();

					content.empty();
					additionalContent.empty();
					content.current = null;
					
					additionalContent.css({
						opacity: 1,
						height: ''
					});
					
					content.css({
						width : '',
						height : '',
					}).parent('.controls').css({
						top : '',
						left : '',
						'margin-left' : ''
					}).parent('#popupviewer').css({
						opacity : 1
					});
										
					if ( typeof finalFunction == 'function' ) {
						finalFunction(e);
					}
				});
			}
			
			return _;
		};
		
		internal.globalKeyHandler = function(e) {
			
			if ( !viewer.is(":visible") ) return;
			
			switch(e.keyCode) {
				case 39: // Right
					e.stopPropagation();
					next.click();
					break;
				case 37: // Left
					e.stopPropagation();
					previous.click();
					break;
				case 27: // Escape
					e.stopPropagation();
					_.hideViewer();
					break;
			}
		};
	
		_.presentViewerWithContent = function(e, popupData) {

			popupData = popupData || this.popupData || e.target.popupData; // Either as param or from object
			
			/*
				popupData = {
					isImage: boolean,
					call: ajax_handler,
					src: URL,
					id: alternate_wiki_page,
					width: width_of_window,
					height: height_of_window
				}
			*/
			
			if ( !popupData ) { return; }
			e && e.preventDefault();

			if ( content && !content.is(':empty') ) {
				e.target.popupData = popupData;
				_.hideViewer(e, _.presentViewerWithContent);
				return _;
			}

			_.showViewer();

			content.current = $(this);
			
			internal.log(popupData);
			
			if ( popupData.isImage ) {
				
				// Load image routine
				internal.log("loading an image");
				popupData.call = popupData.call || '_popup_load_image_meta';
				$(new Image()).attr('src', popupData.src || this.href).load(function(){

					var image = $(this);

					var wrapper = $('<div/>').load(BASE_URL, popupData, function() {
						
						// Force size for the moment
						content.css({
							width: content.width(),
							height: content.height(),
							overflow: 'hidden'
						})
	
						content.append(image);
						content.popupData = jQuery.extend(true, {}, popupData);
	
						additionalContent.html(wrapper.html());
                        _.registerCloseHandler();
						_.resizePopup(popupData.width, popupData.height, additionalContent.innerHeight(), image, false, popupData.hasNextPrevious);
					});
				});
				
			} else {
				
				popupData.call = popupData.call || '_popup_load_file';
				popupData.src = popupData.src || BASE_URL;
				var wrapper = $('<div/>').load(popupData.src, popupData, function(response, status, xhr) {

					var success = function(node)
					{
						node.find('div.dokuwiki,body').first().waitForImages({
							finished: function() {
						
							// Force size for the moment
							content.css({
								width: content.width(),
								height: content.height()
							})

							content.html(this);

							// Check for Javascript to execute
							var script = "";
							node.find('popupscript').
							each(function() {
								script += (this.innerHTML || this.innerText);
							})

							if ( script.length > 0 )
							{
								var randomID = Math.ceil(Math.random()*1000000);
								content.attr('id', randomID);

								var newContext = ""; //"jQuery.noConflict(); containerContext = this; ___ = function( selector, context ){return new jQuery.fn.init(selector,context||containerContext);}; ___.fn = ___.prototype = jQuery.fn;jQuery.extend( ___, jQuery );jQuery = ___;\n"
								
								try{
									$.globalEval("try{\n(function(){\n"+newContext+script+"\n}).call(jQuery('div#"+randomID+"').get(0));\n}catch(e){}\n//");
								} catch (e) {
									internal.log("Exception!");
									internal.log(e);
								}
							}

							if ( popupData.postPopupHook && typeof popupData.postPopupHook == 'function' ) {
							    // Post-Hook which as to be a javascript function and my modify the popupData
    							popupData.postPopupHook(this, popupData);
							}

                            _.registerCloseHandler();
                            // At the very end we will resize the popup to fit the content.
							_.resizePopup(popupData.width, popupData.height, null, content, true, popupData.hasNextPrevious);

						}, waitForAll: true});
					}
				
				
					if ( status == "error") {
						// Go for an iframe
						var finished = false;
						var iframe = null;
						
						var messageFunction = function(event) {
				
							finished = true;
							var data = event.data || event.originalEvent.data;
							// If this message does not come with what we want, discard it.
							if ((typeof data).toLowerCase() == "string" || !data.message
									|| data.message != 'frameContent') {
								alert("Could not load page via popupviewer. The page responded with a wrong message.");
								return;
							}
				
							iframe.remove();

							// Clear the window Event after we are done!
							$(window).unbind("message", messageFunction);

							success($(data.body));
						};
						
						var iframe = $('<iframe/>').load(function(){

							var frame = this;
							if ( frame.contentWindow.postMessage ) {
							
								// Register the Message Event for PostMessage receival
								$(window).bind("message", messageFunction);
								
								// Send a message
								var message = "getFrameContent";
								frame.contentWindow.postMessage(message, "*");
							}
							
						}).hide().attr('src', internal.getCurrentLocation() ).appendTo('body');
						
						window.setTimeout(function() {
							if (!finished) {
								iframe.remove();
								alert("Could not load page via popupviewer. The page is not available.");
							}
						}, 30000);
						
					} else {
						success(wrapper)
					}

				});
			}
		};
		
		/* has to be called via popupscript in page if needed. */
		_.propagateClickHandler = function(node) {
			node.find('a[href],form[action]').
			each(function(){
				// Replace all event handler
				
				var element = $(this);
				
				urlpart = element.attr('href') || element.attr('action') || "";
				if ( urlpart.match(new RegExp("^#.*?$")) ) {
					// Scroll to anchor
					element.click(function(){
						content.get(0).scrollTop( urlpart == '#' ? 0 : $(urlpart).offset().top);
					});
				}
				
				if ( this.getAttribute('popupviewerdata') ) {
					this.popupData = $.parseJSON(this.getAttribute('popupviewerdata'));
					this.removeAttribute('popupviewerdata');
				} else {
					this.popupData = jQuery.extend(true, {}, popupData);
					this.popupData.src = urlpart;
					delete(this.popupData.id); // or it will always load this file.
				}
				
				$(this).bind('click', function(e){
					e.stopPropagation(); e.preventDefault();
					_.hideViewer(e, _.presentViewerWithContent);
				});
			});
		};
		
		internal.getCurrentLocation = function() {
			return content.current.attr('href') || content.current.attr('src') || content.current.attr('action');
		};
		
		internal.optimalSize = function(offsetElement, isPageContent) {
/*			
			if ( !isPageContent ) {
				return {width: offsetElement.get(0).width, height: offsetElement.get(0).height};
			}
*/			
			var prevWidth = content.width();
			var prevHeight = content.height();
		
			offsetElement.css({width:'auto', height: 'auto'});
			
			width = offsetElement.width();
			height = offsetElement.height(); 

			// Reset to previous size so the whole thing will animate from the middle
			offsetElement.css({width:prevWidth, height: prevHeight});
			
			return {width: width, height: height};
		}
		
		_.resizePopup = function(width, height, additionalHeight, offsetElement, isPageContent, needsNextPrevious) {

			internal.log("Initial Size: " + width + " " + height);
			internal.log(offsetElement);
			
			if ( offsetElement && !width && !height) {
				var optimalSize = internal.optimalSize(offsetElement, isPageContent);
				width = optimalSize.width;
				height = optimalSize.height; 
			}
			
			internal.log("OffsetElement Size: " + width + " " + height);
			width = parseInt(width) || ($(window).width() * 0.7);
			height = parseInt(height) || ($(window).height() * 0.8);
			
			var ratio = width / height;
			var maxHeight = ( $(window).height() * 0.99 ) - 60;
			var maxWidth = ( $(window).width() * 0.99 ) - 40;
			
			additionalHeight = additionalHeight || 0;
			height += additionalHeight;
			
			internal.log("After Additional Content Size: " + width + " " + height);

			if ( height > maxHeight ) {
				height = maxHeight;
				if ( !isPageContent ) { // If this is an image we will have to fix the size
					width = (height - additionalHeight) * ratio;
				} else {
					width += 20; // For the scroller Bar that will apear;
				}
			}
			
			if ( width > maxWidth ) {
				width = maxWidth;
				if ( !isPageContent ) { // If this is an image we will have to fix the size
					height = width / ratio + additionalHeight;
				}
			}
			
			var xOffset = viewerIsFixed ? 0 : $(document).scrollLeft() || 0;
			var yOffset = viewerIsFixed ? 0 : $(document).scrollTop() || 0;
			
			yOffset = Math.max(($(window).height() - height) * 0.5 + yOffset, 5);
			xOffset += ($(window).width() - width) * 0.5;
			
			internal.log("Final Size: " + width + " " + height);
			internal.log("Final Offset: " + xOffset + " " + yOffset);
			
			if ( !isPageContent && offsetElement.is('img') ) {

				offsetElement.animate({
					width : width,
					height : height - additionalHeight
				});
				
				content.css({
					width : '',
					height : '',
					overflow: ''
				});

			} else {
				content.animate({
					width : width,
					height : isPageContent ? height : 'auto',
				});
			}
			
			content.parent().animate({
				top : yOffset,
				left : xOffset,
				'margin-left' : 0
			});
			
			if ( isPageContent ) {
				content.removeClass('isImage');
			} else {
				content.addClass('isImage');
			}
			
			_.handleNextAndPrevious(!isPageContent || needsNextPrevious);
			return _;
		};
		
		_.skipImageInDirection = function(e)
		{
			e.stopPropagation();
			
			if ( !$(this).is(':visible') ) { return; }
			
			var skipTo =  $.inArray(content.current.get(0), _.popupImageStack) + e.data.direction;
			skipTo = Math.min(_.popupImageStack.length-1, Math.max(skipTo, 0));
			
			internal.log("skipping " + (e.data.direction < 0 ? 'previous' : 'next') + ' ' + skipTo );
			return _.skipToImage(skipTo, e.data.direction);
		};

		_.skipToImage = function(skipTo, inDirection)
		{
			if ( !$(_.popupImageStack[skipTo]).is(content.current) ) {
				_.hideViewer(null, function() {
					// Deliver extra functionality to clicked item.
					var nextItem = _.popupImageStack[skipTo];
					(nextItem.popupData && nextItem.popupData.click && nextItem.popupData.click(skipTo, inDirection)) || $(nextItem).click();
				});
			}
			
			return _;
		}

		_.isFirst = function() {
			return _.popupImageStack.first().is(content.current);
		}

		_.isLast = function() {
			return _.popupImageStack.last().is(content.current);
		}
		
		_.handleNextAndPrevious = function(currentIsImage) {
		
			if ( currentIsImage && _.popupImageStack && _.popupImageStack.size() > 1) {
			
				if ( _.isFirst() ) {
					previous.addClass('inactive');
				} else {
					previous.removeClass('inactive');
				}

				if ( _.isLast() ) {
					next.addClass('inactive');
				} else {
					next.removeClass('inactive');
				}

				next.addClass('visible');
				previous.addClass('visible');
			} else {
				next.removeClass('visible');
				previous.removeClass('visible');
			}
			
			return _;
		};

        _.registerCloseHandler = function () {
            $('*[popupviewerclose]').each(function(){
                $(this).click(function(e){
                   e && e.preventDefault();
                   _.hideViewer(e);
                   return false;
                });
                if (this.removeAttribute) this.removeAttribute('popupviewerclose');
            });
        }

		_.init = function(popupImageStack) {

			_.popupImageStack = $(popupImageStack || '*[popupviewerdata]').each(function(){
				this.popupData = this.popupData || $.parseJSON(this.getAttribute('popupviewerdata'));
				if (this.removeAttribute) this.removeAttribute('popupviewerdata');
				$(this).unbind('click').click(_.presentViewerWithContent);
			}).filter(function(){
				// Only images allowed in Stack.
				return this.popupData.isImage || this.popupData.hasNextPrevious;
			});
			
			return _;
		};
	
	})(popupviewer.prototype);
	
    // Namespace all events.
    var eventNamespace = 'waitForImages';

    // CSS properties which contain references to images.
    $.waitForImages = {
        hasImageProperties: ['backgroundImage', 'listStyleImage', 'borderImage', 'borderCornerImage', 'cursor']
    };

    // Custom selector to find `img` elements that have a valid `src` attribute and have not already loaded.
    $.expr[':'].uncached = function (obj) {
        // Ensure we are dealing with an `img` element with a valid `src` attribute.
        if (!$(obj).is('img[src!=""]')) {
            return false;
        }

        // Firefox's `complete` property will always be `true` even if the image has not been downloaded.
        // Doing it this way works in Firefox.
        var img = new Image();
        img.src = obj.src;
        return !img.complete;
    };

    $.fn.waitForImages = function (finishedCallback, eachCallback, waitForAll) {

        var allImgsLength = 0;
        var allImgsLoaded = 0;

        // Handle options object.
        if ($.isPlainObject(arguments[0])) {
            waitForAll = arguments[0].waitForAll;
            eachCallback = arguments[0].each;
			// This must be last as arguments[0]
			// is aliased with finishedCallback.
            finishedCallback = arguments[0].finished;
        }

        // Handle missing callbacks.
        finishedCallback = finishedCallback || $.noop;
        eachCallback = eachCallback || $.noop;

        // Convert waitForAll to Boolean
        waitForAll = !! waitForAll;

        // Ensure callbacks are functions.
        if (!$.isFunction(finishedCallback) || !$.isFunction(eachCallback)) {
            throw new TypeError('An invalid callback was supplied.');
        }

        return this.each(function () {
            // Build a list of all imgs, dependent on what images will be considered.
            var obj = $(this);
            var allImgs = [];
            // CSS properties which may contain an image.
            var hasImgProperties = $.waitForImages.hasImageProperties || [];
            // To match `url()` references.
            // Spec: http://www.w3.org/TR/CSS2/syndata.html#value-def-uri
            var matchUrl = /url\(\s*(['"]?)(.*?)\1\s*\)/g;

            if (waitForAll) {

                // Get all elements (including the original), as any one of them could have a background image.
                obj.find('*').addBack().each(function () {
                    var element = $(this);

                    // If an `img` element, add it. But keep iterating in case it has a background image too.
                    if (element.is('img:uncached')) {
                        allImgs.push({
                            src: element.attr('src'),
                            element: element[0]
                        });
                    }

                    $.each(hasImgProperties, function (i, property) {
                        var propertyValue = element.css(property);
                        var match;

                        // If it doesn't contain this property, skip.
                        if (!propertyValue) {
                            return true;
                        }

                        // Get all url() of this element.
                        while (match = matchUrl.exec(propertyValue)) {
                            allImgs.push({
                                src: match[2],
                                element: element[0]
                            });
                        }
                    });
                });
            } else {
                // For images only, the task is simpler.
                obj.find('img:uncached')
                    .each(function () {
                    allImgs.push({
                        src: this.src,
                        element: this
                    });
                });
            }

            allImgsLength = allImgs.length;
            allImgsLoaded = 0;

            // If no images found, don't bother.
            if (allImgsLength === 0) {
                finishedCallback.call(obj[0]);
            }

            $.each(allImgs, function (i, img) {

                var image = new Image();

                // Handle the image loading and error with the same callback.
                $(image).on('load.' + eventNamespace + ' error.' + eventNamespace, function (event) {
                    allImgsLoaded++;

                    // If an error occurred with loading the image, set the third argument accordingly.
                    eachCallback.call(img.element, allImgsLoaded, allImgsLength, event.type == 'load');

                    if (allImgsLoaded == allImgsLength) {
                        finishedCallback.call(obj[0]);
                        return false;
                    }

                });

                image.src = img.src;
            });
        });
    };
    	
	$(function(){
		$.popupviewer().init();
	});
	
})(jQuery);


/* Loading the content for locally exported content */
(function($){
	$(window).bind("message", function(event){

		var data = event.data || event.originalEvent.data;
		var source = event.source || event.originalEvent.source;
		if (data != "getFrameContent") {
			return;
		}

		try {
			source.postMessage({
				message : "frameContent",
				body : jQuery('html').html()
			}, "*");
		} catch (e) {
			alert("Fatal Exception! Could not load page via popupviewer.\n" + e);
		}
	});
})(jQuery);
