// ==========================================================================
//
// Guestures
// Adds touch guestures, handles click and tap events
//
// ==========================================================================
;(function (window, document, $) {
	'use strict';

	var requestAFrame = (function() {
		return  window.requestAnimationFrame ||
				window.webkitRequestAnimationFrame ||
				window.mozRequestAnimationFrame ||
				function( callback ) {
					window.setTimeout(callback, 1000 / 60); };
				})();


	var pointers = function( e ) {
		var result = [];

		e = e.originalEvent || e || window.e;
		e = e.touches && e.touches.length ? e.touches : ( e.changedTouches && e.changedTouches.length ? e.changedTouches : [ e ] );

		for ( var key in e ) {

			if ( e[ key ].pageX ) {
				result.push( { x : e[ key ].pageX, y : e[ key ].pageY } );

			} else if ( e[ key ].clientX ) {
				result.push( { x : e[ key ].clientX, y : e[ key ].clientY } );
			}
		}

		return result;
	};

	var distance = function( point2, point1, what ) {

		if ( what === 'x' ) {
			return point2.x - point1.x;

		} else if ( what === 'y' ) {
			return point2.y - point1.y;
		}

		return Math.sqrt( Math.pow( point2.x - point1.x, 2 ) + Math.pow( point2.y - point1.y, 2 ) );

	};

	var isClickable = function( $el ) {

	 	return $el.is('a') || $el.is('button') || $el.is('input') || $el.is('textarea') || $.isFunction( $el.get(0).onclick );

	};

	var hasScrollbars = function( el ) {
		var overflowY = window.getComputedStyle( el )['overflow-y'];
		var overflowX = window.getComputedStyle( el )['overflow-x'];

		var vertical   = (overflowY === 'scroll' || overflowY === 'auto') && el.scrollHeight > el.clientHeight;
		var horizontal = (overflowX === 'scroll' || overflowX === 'auto') && el.scrollWidth > el.clientWidth;

		return vertical || horizontal;
	};

	var isScrollable = function ( $el ) {

		var rez = false;

		while ( true ) {
			rez	= hasScrollbars( $el.get(0) );

			if ( rez ) {
				break;
			}

			$el = $el.parent();

			if ( !$el.length || $el.hasClass('fancybox-slider') || $el.is('body') ) {
				break;
			}

		}

		return rez;

	};


	var Guestures = function ( instance ) {

		var self = this;

		self.instance = instance;

		self.$wrap       = instance.$refs.slider_wrap;
		self.$slider     = instance.$refs.slider;
		self.$container  = instance.$refs.container;

		self.destroy();

		self.$wrap.on('touchstart.fb mousedown.fb', $.proxy(self, "ontouchstart"));

	};

	Guestures.prototype.destroy = function() {

		this.$wrap.off('touchstart.fb mousedown.fb touchmove.fb mousemove.fb touchend.fb touchcancel.fb mouseup.fb mouseleave.fb');

	};


	Guestures.prototype.ontouchstart = function( e ) {

		var self = this;

		var $target  = $( e.target );
		var instance = self.instance;
		var current  = instance.current;
		var $content = current.$content || current.$placeholder;

		var frame = function() {

			if ( self.sliderNewPos ) {
				$.fancybox.setTranslate( self.$slider, self.sliderNewPos );

				self.sliderNewPos = null;
			}

			if ( self.contentNewPos ) {
				$.fancybox.setTranslate( self.$content, self.contentNewPos );

				self.contentNewPos = null;
			}

			if ( self.isSwiping || self.isPanning || self.isZooming ) {
				requestAFrame( frame );
			}

		};

		// Ignore taping on links, buttons and scrollable items
		if ( isClickable( $target ) || isClickable( $target.parent() ) || ( isScrollable( $target ) && !$target.hasClass('fancybox-slide') ) ) {
			return;
		}

		e.stopPropagation();
		e.preventDefault();

		if ( !current || self.instance.isAnimating || self.instance.isClosing ) {
			return;
		}

		self.startPoints = pointers( e );

		// Prevent zooming if already swiping
		if ( self.startPoints.length > 1 && !current.isMoved ) {
			return;
		}

		self.$wrap.off('touchmove.fb mousemove.fb',  $.proxy(self, "ontouchmove"));
		self.$wrap.off('touchend.fb touchcancel.fb mouseup.fb mouseleave.fb',  $.proxy(self, "ontouchend"));

		self.$wrap.on('touchmove.fb mousemove.fb',  $.proxy(self, "ontouchmove"));
		self.$wrap.on('touchend.fb touchcancel.fb mouseup.fb mouseleave.fb',  $.proxy(self, "ontouchend"));

		self.$content = $content;
		self.$target  = $target;
		self.$content = $content;

		self.startTime = new Date().getTime();
		self.distanceX = self.distanceY = self.distance = 0;

		self.canvasWidth  = Math.round( current.$slide.width() );
		self.canvasHeight = Math.round( current.$slide.height() );

		self.canTap    = current.isMoved;
		self.isPanning = false;
		self.isSwiping = false;
		self.isZooming = false;

		self.sliderStartPos = $.fancybox.getTranslate( self.$slider );
		self.sliderNewPos   = null;

		self.contentStartPos = $.fancybox.getTranslate( self.$content );
		self.contentNewPos   = null;

		if ( self.startPoints.length == 1 ) {

			if ( current.type === 'image' && ( self.contentStartPos.width > self.canvasWidth || self.contentStartPos.height > self.canvasHeight  ) ) {

				$.fancybox.stop( self.$content );

				self.isPanning = true;

			} else {

				$.fancybox.stop( self.$slider );

				self.isSwiping = true;
			}

			self.$container.addClass('fancybox-controls--isGrabbing');

		}

		if ( current.type === 'image' && self.startPoints.length == 2 && ( current.isLoaded || current.$ghost ) ) {

			self.isZooming = true;
			self.canTap    = false;

			$.fancybox.stop( self.$content );

			self.centerPointStartX = ((self.startPoints[0].x + self.startPoints[1].x) / 2) - $(window).scrollLeft();
			self.centerPointStartY = ((self.startPoints[0].y + self.startPoints[1].y) / 2) - $(window).scrollTop();

			self.percentageOfImageAtPinchPointX = ( self.centerPointStartX - self.contentStartPos.left ) / self.contentStartPos.width;
			self.percentageOfImageAtPinchPointY = ( self.centerPointStartY - self.contentStartPos.top  ) / self.contentStartPos.height;

			self.startDistanceBetweenFingers = distance( self.startPoints[0], self.startPoints[1] );

		}

		frame();
	};

	Guestures.prototype.ontouchmove = function( e ) {

		var self = this;

		e.preventDefault();

		if ( self.instance.isAnimating ) {
			return;
		}

		self.newPoints = pointers( e );

		self.distanceX = distance( self.newPoints[0], self.startPoints[0], 'x' );
		self.distanceY = distance( self.newPoints[0], self.startPoints[0], 'y' );

		self.distance = distance( self.newPoints[0], self.startPoints[0] );

		// Skip false ontouchmove events (Chrome)
		if ( self.distance > 0 ) {
			if ( self.isSwiping ) {
				self.onSwipe();

			} else if ( self.isPanning ) {
				self.onPan();

			} else if ( self.isZooming ) {
				self.onZoom();
			}
		}

	};

	Guestures.prototype.onSwipe = function() {

		var self = this;

		var swiping = self.isSwiping;
		var angle;

		if ( swiping === true ) {

			if ( Math.abs( self.distance ) > 10 )  {

				angle   = Math.abs( Math.atan2( self.distanceY, self.distanceX ) * 180 / Math.PI );
				swiping = ( angle > 45 && angle < 135 ) || self.instance.group.length <= 1 ? 'y' : 'x';

				if ( self.instance.opts.touch.vertical === false || ( self.instance.opts.touch.vertical === 'auto' && $( window ).width() > 800 ) ) {
					swiping = 'x';
				}

				self.isSwiping = swiping;
				self.canTap    = false;

				// Reset points to avoid jumping, because we dropped first swipes to calculate the angle
				self.startPoints = self.newPoints;
			}

		} else {

			self.sliderNewPos = {
				top  : swiping == 'x' ? 0 : self.sliderStartPos.top  + self.distanceY,
				left : swiping == 'y' ? self.sliderStartPos.left : self.sliderStartPos.left + self.distanceX
			};

		}

	};

	Guestures.prototype.onPan = function() {

		var self = this;

		var newOffsetX, newOffsetY, newPos;

		self.canTap = false;

		if ( self.contentStartPos.width > self.canvasWidth ) {
			newOffsetX = self.contentStartPos.left + self.distanceX;

		} else {
			newOffsetX = self.contentStartPos.left;

		}

		newOffsetY = self.contentStartPos.top + self.distanceY;

		newPos = self.limitMovement( newOffsetX, newOffsetY, self.contentStartPos.width, self.contentStartPos.height );

		newPos.scaleX = self.contentStartPos.scaleX;
		newPos.scaleY = self.contentStartPos.scaleY;

		self.contentNewPos  = newPos;
		self.contentLastPos = newPos;

	};

	// Make panning sticky to the edges
	Guestures.prototype.limitMovement = function( newOffsetX, newOffsetY, newWidth, newHeight ) {

		var self = this;

		var minTranslateX, minTranslateY, maxTranslateX, maxTranslateY;

		var canvasWidth  = self.canvasWidth;
		var canvasHeight = self.canvasHeight;

		var currentOffsetX = self.contentStartPos.left;
		var currentOffsetY = self.contentStartPos.top;

		var distanceX = self.distanceX;
		var distanceY = self.distanceY;

		// Slow down proportionally to traveled distance

		minTranslateX = Math.max(0, canvasWidth  * 0.5 - newWidth  * 0.5 );
		minTranslateY = Math.max(0, canvasHeight * 0.5 - newHeight * 0.5 );

		maxTranslateX = Math.min( canvasWidth  - newWidth,  canvasWidth  * 0.5 - newWidth  * 0.5 );
		maxTranslateY = Math.min( canvasHeight - newHeight, canvasHeight * 0.5 - newHeight * 0.5 );

		if ( newWidth > canvasWidth ) {

			//   ->
			if ( distanceX > 0 && newOffsetX > minTranslateX ) {
				newOffsetX = minTranslateX - 1 + Math.pow(-minTranslateX + currentOffsetX + distanceX, 0.8 ) || 0;
			}

			//    <-
			if ( distanceX  < 0 && newOffsetX < maxTranslateX ) {
				newOffsetX = maxTranslateX + 1 - Math.pow( maxTranslateX - currentOffsetX - distanceX, 0.8 ) || 0;
			}

		}

		if ( newHeight > canvasHeight ) {

			//   \/
			if ( distanceY > 0 && newOffsetY > minTranslateY ) {
				newOffsetY = minTranslateY - 1 + Math.pow(-minTranslateY + currentOffsetY + distanceY, 0.8 ) || 0;
			}

			//   /\
			if ( distanceY < 0 && newOffsetY < maxTranslateY ) {
				newOffsetY = maxTranslateY + 1 - Math.pow ( maxTranslateY - currentOffsetY - distanceY, 0.8 ) || 0;
			}

		}

		return {
			top  : newOffsetY,
			left : newOffsetX
		};

	};


	Guestures.prototype.limitPosition = function( newOffsetX, newOffsetY, newWidth, newHeight ) {

		var self = this;

		var canvasWidth  = self.canvasWidth;
		var canvasHeight = self.canvasHeight;

		if ( newWidth > canvasWidth ) {
			newOffsetX = newOffsetX > 0 ? 0 : newOffsetX;
			newOffsetX = newOffsetX < canvasWidth - newWidth ? canvasWidth - newWidth : newOffsetX;

		} else {

			// Center horizontally
			newOffsetX = Math.max( 0, canvasWidth / 2 - newWidth / 2 );

		}

		if ( newHeight > canvasHeight ) {
			newOffsetY = newOffsetY > 0 ? 0 : newOffsetY;
			newOffsetY = newOffsetY < canvasHeight - newHeight ? canvasHeight - newHeight : newOffsetY;

		} else {

			// Center vertically
			newOffsetY = Math.max( 0, canvasHeight / 2 - newHeight / 2 );

		}

		return {
			top  : newOffsetY,
			left : newOffsetX
		};

	};

	Guestures.prototype.onZoom = function() {

		var self = this;

		// Calculate current distance between points to get pinch ratio and new width and height

		var currentWidth  = self.contentStartPos.width;
		var currentHeight = self.contentStartPos.height;

		var currentOffsetX = self.contentStartPos.left;
		var currentOffsetY = self.contentStartPos.top;

		var endDistanceBetweenFingers = distance( self.newPoints[0], self.newPoints[1] );

		var pinchRatio = endDistanceBetweenFingers / self.startDistanceBetweenFingers;

		var newWidth  = Math.floor( currentWidth  * pinchRatio );
		var newHeight = Math.floor( currentHeight * pinchRatio );

		// This is the translation due to pinch-zooming
		var translateFromZoomingX = (currentWidth  - newWidth)  * self.percentageOfImageAtPinchPointX;
		var translateFromZoomingY = (currentHeight - newHeight) * self.percentageOfImageAtPinchPointY;

		//Point between the two touches

		var centerPointEndX = ((self.newPoints[0].x + self.newPoints[1].x) / 2) - $(window).scrollLeft();
		var centerPointEndY = ((self.newPoints[0].y + self.newPoints[1].y) / 2) - $(window).scrollTop();

		// And this is the translation due to translation of the centerpoint
		// between the two fingers

		var translateFromTranslatingX = centerPointEndX - self.centerPointStartX;
		var translateFromTranslatingY = centerPointEndY - self.centerPointStartY;

		// The new offset is the old/current one plus the total translation

		var newOffsetX = currentOffsetX + ( translateFromZoomingX + translateFromTranslatingX );
		var newOffsetY = currentOffsetY + ( translateFromZoomingY + translateFromTranslatingY );

		var newPos = {
			top    : newOffsetY,
			left   : newOffsetX,
			scaleX : self.contentStartPos.scaleX * pinchRatio,
			scaleY : self.contentStartPos.scaleY * pinchRatio
		};

		self.canTap = false;

		self.newWidth  = newWidth;
		self.newHeight = newHeight;

		self.contentNewPos  = newPos;
		self.contentLastPos = newPos;

	};

	Guestures.prototype.ontouchend = function( e ) {

		var self = this;

		var current = self.instance.current;

		var dMs = Math.max( (new Date().getTime() ) - self.startTime, 1);

		var swiping = self.isSwiping;
		var panning = self.isPanning;
		var zooming = self.isZooming;

		self.endPoints = pointers( e );

		if ( self.endPoints.length > 1 && ( self.isSwiping === 'x' || self.isSwiping === 'y' ) ) {
			return;
		}

		self.$container.removeClass('fancybox-controls--isGrabbing');

		self.$wrap.off('touchmove.fb mousemove.fb',  $.proxy(this, "ontouchmove"));
		self.$wrap.off('touchend.fb touchcancel.fb mouseup.fb mouseleave.fb',  $.proxy(this, "ontouchend"));

		self.isSwiping = false;
		self.isPanning = false;
		self.isZooming = false;

		if ( self.canTap )  {
			return self.ontap();
		}

		// Speed in px/ms
		self.velocityX = self.distanceX / dMs * 0.5;
		self.velocityY = self.distanceY / dMs * 0.5;

		self.speed = current.opts.speed;

		self.speedX = Math.max( self.speed - 120, Math.min( self.speed + 120, ( 1 / Math.abs( self.velocityX ) ) * self.speed ) );
		self.speedY = Math.max( self.speed - 120, Math.min( self.speed + 120, ( 1 / Math.abs( self.velocityY ) ) * self.speed ) );

		if ( swiping ) {
			self.endSwiping( swiping );

		} else if ( panning ) {
			self.endPanning();

		} else if ( zooming ) {
			self.endZooming();

		}


		return;
	};

	Guestures.prototype.endSwiping = function( swiping ) {

		var self = this;

		// Close if swiped vertically / navigate if horizontally

		if ( swiping == 'y' && Math.abs( self.distanceY ) > 50 ) {

			// Continue vertical movement

			$.fancybox.animate( self.$slider, null, {
				top     : self.sliderStartPos.top + self.distanceY + self.velocityY * 150,
				left    : self.sliderStartPos.left,
				opacity : 0
			}, self.speedY );

			self.instance.close( true );

		} else if ( swiping == 'x' && self.distanceX > 50 ) {
			self.instance.previous( self.speedX );

		} else if ( swiping == 'x' && self.distanceX < -50 ) {
			self.instance.next( self.speedX );

		} else {

			// Move back to center
			self.instance.update( false, false, true );

		}

	};

	Guestures.prototype.endPanning = function() {

		var self = this;

		var newOffsetX = self.contentLastPos.left + ( self.velocityX * self.speed * 2 );
		var newOffsetY = self.contentLastPos.top  + ( self.velocityY * self.speed * 2 );

		var newPos = self.limitPosition( newOffsetX, newOffsetY, self.contentStartPos.width, self.contentStartPos.height );

		 newPos.width  = self.contentStartPos.width;
		 newPos.height = self.contentStartPos.height;

		$.fancybox.animate( self.$content, null, newPos, self.speed, "easeOutSine" );

	};


	Guestures.prototype.endZooming = function() {

		var self = this;

		var current = self.instance.current;
		var newPos;

		var newOffsetX = self.contentLastPos.left;
		var newOffsetY = self.contentLastPos.top;

		var newWidth  = self.newWidth;
		var newHeight = self.newHeight;

		var reset = {
		   	top    : newOffsetY,
		   	left   : newOffsetX,
		   	width  : newWidth,
		   	height : newHeight,
			scaleX : 1,
			scaleY : 1
	   };

	   // Reset scalex/scaleY values; this helps for perfomance and does not break animation
	   $.fancybox.setTranslate( self.$content, reset );

		if ( newWidth < self.canvasWidth && newHeight < self.canvasHeight ) {
			self.instance.scaleToFit( 150 );

		} else if ( newWidth > current.width || newHeight > current.height ) {
			self.instance.scaleToActual( self.centerPointStartX, self.centerPointStartY, 150 );

		} else {

			newPos = self.limitPosition( newOffsetX, newOffsetY, newWidth, newHeight );

			$.fancybox.animate( self.$content, null, newPos, self.speed, "easeOutSine" );

		}

	};

	Guestures.prototype.ontap = function() {

		var self = this;

		var x = self.endPoints[0].x;
		var y = self.endPoints[0].y;

		x = x - self.$wrap.offset().left;
		y = y - self.$wrap.offset().top;

		if ( !$.fancybox.isTouch ) {

			if ( self.instance.opts.closeClickOutside && self.$target.is('.fancybox-slide') ) {
				self.instance.close();

				return;
			}

			if ( self.instance.current.type == 'image' && self.instance.current.isMoved ) {

				if ( self.instance.canPan() ) {
					self.instance.scaleToFit();

				} else if ( self.instance.isScaledDown() ) {
					self.instance.scaleToActual( x, y );

				} else if ( self.instance.group.length < 2 ) {
					self.instance.close();

				}

			}

			return;
		}


		// Double tap
		if ( self.tapped ) {

			self.tapped = false;

			clearTimeout( self.id );

			if (Math.abs( x - self.x ) > 50 || Math.abs( y - self.y ) > 50 || !self.instance.current.isMoved ) {
				return this;
			}

			if ( !( self.instance.current.isLoaded || self.instance.current.$ghost ) ) {
				return;
			}

			if ( self.instance.current.type == 'image' ) {

				if ( self.instance.canPan() ) {
					self.instance.scaleToFit();

				} else if ( self.instance.isScaledDown() ) {
					self.instance.scaleToActual( x, y );

				}

			}

		} else {

			// Single tap

			self.tapped = true;

			self.x = x;
			self.y = y;

			self.id = setTimeout(function() {
				self.tapped = false;

				self.instance.toggleControls( true );

			}, 300);
		}

		return this;
	};

	$(document).on('onActivate.fb', function (e, instance) {

		if ( instance.opts.touch && !instance.Guestures ) {
			instance.Guestures = new Guestures( instance );
		}

	});

	$(document).on('beforeClose.fb', function (e, instance) {

		if ( instance.Guestures ) {
			instance.Guestures.destroy();
		}

	});


}(window, document, window.jQuery));
