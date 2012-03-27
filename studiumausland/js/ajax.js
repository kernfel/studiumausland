/**
 * @package Studium_Ausland
 * @version 2.0a
 *
 * @dependencies: JSON, jQuery, History
 */

(function($){
var body = $('body'), strSchool='school', strClass='class', idContent='#content',
	wp_objects = {school:1,offer:0,post:0};
Ajax = {

cache: {_sb:{}},
subs: [],
e503: "Die Seite ist wegen Unterhaltsarbeiten vor\u00FCbergehend nicht erreichbar. Bitte versuchen Sie es in zwei Minuten nochmals. Danke!",
e0: "Ein unbekannter Fehler ist aufgetreten. Bitte versuchen Sie es sp\u00E4ter nochmals.",

findState: function( body, title ) {
	var i, state = fbk.state;
	if ( state && this.rich ) {
		if ( state.cat )
			(this.cache[state.cat] || (this.cache[state.cat] = {})).menu = $('#navi', body).children();
		if ( ! this.fading )
			$(idContent, body).find('script').remove();
		if ( 'search' == state.object ) {
			((this.cache[state.cat] || (this.cache[state.cat] = {}))[state.object] || (this.cache[state.cat][state.object] = {}))._form
			 = $('#extended_search', body);
			this.cache[state.cat][state.object][state.id] = [title||document.title, $('#searchresults', body)];
		} else {
			((this.cache[state.cat] || (this.cache[state.cat] = {}))[state.object] || (this.cache[state.cat][state.object] = {}))[state.id]
			 = [title || document.title, $(idContent, body).children()];
		}
		for ( i in {left:1,right:1} ) {
			(this.cache._sb[i] || (this.cache._sb[i]={}))[ fbk.sb[state.object]||0 ]
			 = $('#dyn-'+i, body).children();
		}
	}
	return state;
},

init: function() {
	var state, hash = document.location.hash.substr(1),
	_doCat = function(lang, init) {
		if ( Ajax.state.lang == lang && !init )
			delete Ajax.state.lang;
		else
			Ajax.state.lang=lang;
		Ajax.doNav(Ajax.state, init);
	};

	this.rich = window.History && window.History.enabled ;// && (!navigator.userAgent || !/MSIE [1-8]\./.test(navigator.userAgent));
	state = this.findState( body );
	if ( this.rich ) {
		if ( state.object ) {
			History.replaceState( state, document.title, document.location.href.replace(/#.*$/, '') );
			History.Adapter.bind(window, 'statechange', function(){
				if ( Ajax.replacing ) return Ajax.replacing = 0;
				var state = History.getState().data, editlink, stateActually = Ajax.substitute(state);
				state = stateActually || state;
				Ajax.doNav( state );
				Ajax.fetch( state );
				if ( $('#wpadminbar').length ) {
					if ( ! (editlink = $('#wp-admin-bar-edit a')).length )
						editlink = $('<li id="wp-admin-bar-edit"><a href="'+fbk.ajaxurl+'" /></li>').insertAfter('#wp-admin-bar-dashboard').children('a');
					if ( state.object in wp_objects )
						editlink.html('Bearbeiten').show()
						.attr('href',editlink.attr('href').replace(/\/[^\/]*$/, '/post.php?action=edit&post='+state.id));
					else
						editlink.hide();
				}
				$('link[rel="next"],link[rel="prev"]').remove();
			});

			body.on('click', 'a', this.click);
			$('form[role="search"]').on('submit', function(event){
				var args = $(this).find(':input').not('[data-csv]').serialize();
				$(this).find(':input[data-csv]').each(function(){
					if ( $(this).val() )
						args += '&' + $(this).attr('data-csv') + '=' + $(this).val().join(',');
				});
				Ajax.to( '/?' + args, 'Suche', {object:'search',id:args} );
				event.preventDefault();
				return false;
			});
			$(document).keyup(function(event){
				if ( event.which == 27 && body.is('.loading') ) {
					Ajax.state = History.getState().data;
					History.back();
				}
			});
		}
	} else {
		$('.slicenav a').click(this.click);
		$('body.category #navi li.menu-item-level-0>a').click(function( event ){
			_doCat( $(this).attr('href').match(/#(.*)/)[1] );
			event.preventDefault();
			return false;
		});
	}

	$('#content').on(
		'click',
		'.foldout header',
		function(){
			var i = Ajax.state.object=='news'
			Ajax.foldout( this.parentNode.id, i, i );
		}
	);

	this.foldout( 0, state.object=='news', 1 );
	if ( strSchool == state.object ) {
		this.postLoad( state, hash );
	} else if ( 'page' == state.object )
		this.pageForm();

	this.state = state;
	
	if ( state.object in wp_objects )
		this.media();

	body.removeClass('alllangs');
	state.object=='cat' && _doCat(state.lang, 1);

	$(document).one( 'util_init_done', function(){
		strSchool == state.object && $(document).trigger( 'school_loaded', [fbk.current] );
		$(document).trigger( 'ajax_change_state', [state] );
	});
},

click: function( event ) {
	if ( event.which == 2 || event.metaKey || ! $(this).attr('href') || $(this).attr('target') )
		return true;

	var i, link = $(this), url = link.attr('href').replace(/^http:\/\//, ''), match, slice,
	menu = (link.parent().attr(strClass)||'').match(/menu-item-object-(\w+)/),
	state={};
	
	if ( url.indexOf(location.host||location.hostname) == 0 )
		url = url.substr((location.host||location.hostname).length);
	
	// Leave images (lightbox!) and back-end links well alone
	if ( /\.(jpg|gif|png|jpeg|bmp)$/.test(url) || /^\/wp-/.test(url) )
		return true;
	// Non-menu anchors or external links
	else if ( ! menu && url && ! /^\//.test(url) ) {
		if ( /^#/.test(url) && window.opera ) {
			location.href = location.href.split('#')[0] + url;
			return false;
		}
		return true;
	}

	if ( link.closest('.slicenav').length ) {
		Ajax.switchTo( link.parent().attr('class').match(/sni-(\w+)/)[1] );
		event.preventDefault();
		return false;
	} else if ( menu && 'page' != menu[1] ) {
		if ( 'lang' == menu[1] ) {
			state.object = 'cat';
			state.id = link.closest('nav > ul').attr(strClass).match(/menu-cat-(\d+)/)[1];
		} else {
			state.object = menu[1];
			match = new RegExp( 'menu-item-'+state.object+'-(\\d+)' );
		}
	} else if ( link.closest('ul').is('#mb') ) {
		state.object = strSchool;
		state.id = link.closest('li')[0].id.match(/mb-(\d+)/)[1];
	} else if ( link.attr(strClass) && (match = link.attr(strClass).match(/\b(\w+)-(\d+)\b/)) ) {
		state = {object: match[1], id: match[2]};
	} else if ( link.closest('.pagination').length || /^\/(\?|\?.*&)s=/.test(url) ) {
		state = { object: 'search', id: url.match( /\?(.*)/ )[1], cat: 0 };
	} else {
		if ( '' == url || '/' == url ) {
			Ajax.to( '/', '', {object:'index'} );
			event.preventDefault();
			return false;
		} else {
			if ( link.closest('#c4p').length && /\/\/[0-9-]*.*$/.test(url) )
				url = url.replace( '//', '/Welt/' );
			state.object = 'page';
			state.id = url.match( /\/([^#]+)/ )[1].replace(/\/$/,'');
			if ( state.id == 'feed' )
				return false;
		}
	}

	if ( state.object ) {
		if ( ! state.id ) {
			if ( ! (match = link.parent().attr(strClass).match(match)) )
				return true;
			state.id = match[1];
		}
		if ( state.object in {school:1,loc:1,cat:1} )
			state.cat = state.cat || (function(e){
					for ( var i in fbk.cats )
						if ( e == fbk.cats[i] )
							return i;
					return 0;
				})(url.match(/^\/([^\/#]*)/)[1]);
		else
			state.cat = state.cat || 0;
		if ( /#/.test(url) ) {
			url = url.split('#');
			if ( state.object == 'cat' )
				Ajax.state.lang==url[1] || (state.lang = url[1] );
			url = url[0];
		}
		Ajax.to(
			url,
			(((Ajax.cache[state.cat]||{})[state.object]||{})[state.id] // Make use of cached title
			 || [ link.attr('data-title') || link.attr('title') || link.text() ] ) [0],
			state
		);
		event.preventDefault();
	}
},

/**
 * url: base-relative URL to be displayed in the address bar
 * object: 'school' | taxonomy
 * cat: category id
 */
to: function( url, title, state ) {
	state.id||(state.id=0);
	state.cat||(state.cat=0);
	var prev = History.getState().data;
	
	state = this.substitute( state ) || state;
	if ( state.id == prev.id && state.object == prev.object && state.cat == prev.cat ) {
		if ( ! this.fading && state.lang != this.state.lang ) {
			this.doNav( state );
			this.state = state;
		}
		return;
	}
	
	title = this.title( title );

	this.scrollUp = 1;

	if ( this.fading && ! body.is('.loading') )
		this.qState = {state:state, title:title, url:url};
	else
		History.pushState( state, title, location.protocol + '//' + location.host + url );
},

title: function( title, replace ) {
	if ( ! title )
		title = fbk.siteTitle;
	if ( replace && (replace=History.getState()).title != title ) {
		this.replacing = 1;
		History.replaceState( replace.data, title, replace.url );
	}
	return title;
},

/**
 * input should be an object containing the following keys, where applicable:
 *
 * object, id, cat, hash
 */
fetch: function( state ) {
	$(document).trigger( 'before_ajax_change_state', [state] );
	if ( this.cache[state.cat] && this.cache[state.cat][state.object] && this.cache[state.cat][state.object][state.id] ) {
		this.insert( state, 1 );
	} else {
		var queueCheck, ajaxDone, ajaxErr, i, data = {
			action: 'fbk_ajaxnav',
			obj: state.object,
			id: state.id,
			rel: state.cat
		};
	
		if ( state.cat && ! (this.cache[state.cat]&&this.cache[state.cat].menu) )
			data.menu = 1;
		
		if ( 'search' == state.object && ! (this.cache[state.cat]&&this.cache[state.cat][state.object]) )
			data.form = 1;

		if ( ! this.cache._sb.left[ fbk.sb[state.object]||0 ] ) // Left or right don't matter, they're linked anyway.
			data.sb = 1;

		queueCheck = function() {
			var qState = Ajax.qState;
			body.addClass('loading');
			if ( qState ) {
				History.pushState( qState.state, qState.title, qState.url );
				Ajax.qState = 0;
			}
		}
		if ( 'search' == state.object && 'search' == this.state.object )
			i = '#searchresults';
		else
			i = idContent;
		$( i + ( ((fbk.sb[state.object]||0) != (fbk.sb[this.state.object]||0)) ? ',#dyn-left,#dyn-right' : '' ) ).fadeOut(250, queueCheck);
		this.fading = 1;
		this.waitingFor = state;
		ajaxDone = function( response, statusText, jqXHR ) {
			var self = Ajax, cache = self.cache, iState = state, i, title,
				stillNeeded = (self.waitingFor && self.waitingFor.object == iState.object && self.waitingFor.id == iState.id && self.waitingFor.cat == iState.cat);
			if ( stillNeeded && ! body.is('.loading') ) // Protect against fast server responses
				return setTimeout(function(){ajaxDone(response, statusText, jqXHR)}, 50);
			if ( /^</.test(response) ) {
				fbk.state = eval( response.match(/<script>(fbk\.state=[^;]*;?)/)[1] );
				iState = self.findState(
					jQuery(response.match(/<body[\s\S]*<\/body>/)[0].replace(/<body/,'<div').replace(/<\/body>/,'</div>')).eq(0),
					title = response.match(/<title>([^<]*?)</)[1]
				);

				if ( stillNeeded ) {
					if ( state.lang && iState.object == 'cat' )
						iState.lang = state.lang;
					self.doNav( iState );
				}

				self.replaceState( state, iState, self.title(title), History.getState().url, stillNeeded );

				if ( strSchool == iState.object )
					$(document).trigger( 'school_loaded', [fbk.current] );
			} else {
				try {
					response = JSON.parse( response );
				} catch(e) {
					ajaxErr({status:-1});
					return;
				}

				if ( 'search' == iState.object && response.id )
					iState.id = response.id;

				if ( ! response.title && /^<!--{/.test(response.html) ) { // Response granted thru cache, find it in html
					response.title = response.html.match( /^<!--{(.+?)}-->/ )[1];
				}

				response.html = $( response.html.replace(/^<!--{.+?}-->/, '') );
				(( cache[iState.cat] || (cache[iState.cat]={}) )[iState.object] || (cache[iState.cat][iState.object]={}))[iState.id]
				 = [response.title, response.html];

				if ( response.sb ) {
					for ( i in response.sb ) {
						cache._sb[i][ fbk.sb[iState.object]||0 ] = $(response.sb[i]).find('#dyn-'+i).children();
						if ( iState.cat && 'left' == i )
							cache[iState.cat].menu = $(response.sb[i]).find('#navi').children();
					}
				} else if ( response.menu ) {
					cache[iState.cat].menu = $(response.menu).children();
					if ( stillNeeded )
						self.doNav( iState );
				}
				
				if ( response.form ) {
					cache[iState.cat][iState.object]._form = $(response.form);
				}

				if ( strSchool == iState.object && response.html.filter('script#fbk_current').length ) {
					$(document).trigger( 'school_loaded', [JSON.parse(
						response.html.filter('script#fbk_current').remove().html().replace(/fbk\.current=/, '').replace(/;$/, '')
					)] );
				}
			}

			if ( stillNeeded ) {
				self.waitingFor = 0;
				self.insert( iState, 0 );
			}
			
			self.foldout( 0, iState.object=='news', 1, cache[iState.cat][iState.object][iState.id][1] );

			if ( iState.object in wp_objects ) {
				if ( ! stillNeeded ) {
					Ajax.cache[iState.cat][iState.object][iState.id][2] = !0; // mark to do media() on insert
				} else {
					self.media();
					self.postLoad( iState );
				}
			}
		};
		ajaxErr = function( jqXHR, statusText, errorThrown ) {
			if ( jqXHR.status == 503 ) {
				alert( self.e503 );
				self.state = state;
				History.back();
			} else {
				window.console && console.warn && console.warn('ajax.js error '+jqXHR.status);
				location.reload();
			}
		};
		if ( /\?/.test(data.id) && 'search' != state.object ) {
			var query = data.id.split('?'), vars = query[1].split('&');
			data.id = query[0];
			for ( i = 0; i < vars.length; i++ ) {
				query = vars[i].split('=');
				data[ query[0] ] = query[1];
			}
		}
		$.ajax({
			data: data,
			dataType: 'text',
			success: ajaxDone,
			error: ajaxErr
		});
	}
},

replaceState: function( oldState, newState, title, url, immediate ) {
	if ( ! this.substitute( oldState ) )
		this.subs[this.subs.length] = [oldState, newState];
	if ( immediate ) {
		this.replacing = 1;
		History.replaceState( newState, title, url );
	}
},

substitute: function( state ) {
	var i, subs=this.subs;
	for ( i = 0; i < this.subs.length; i++ )
		if ( subs[i][0].id == state.id && subs[i][0].object == state.object && subs[i][0].cat == state.cat )
			return subs[i][1];
	return false;
},

postLoad: function( state, slice ) {
	(strSchool == state.object ) && this.switchTo( slice || 
		( this.rich
			? this.cache[state.cat][state.object][state.id][1].filter('.slice')
			: $('.slice')
		).filter('.active').attr('id')
	);
},

insert: function( state, fade ) {
	fade = fade && ! this.fading;
	this.fading = fade ? 1 : -1;
	var fn = function() {
		var classes = [], self = Ajax, cache = self.cache, qState, search, i, j, appendix = $();
		if ( partial ) {
			$(partial,idContent).detach();
			selection = selection.not( partial );
		} else if ( 'search' == state.object ) {
			appendix = appendix.add( cache[state.cat][state.object]._form );
		}
		selection.children().detach();
		if ( qState = self.qState ) {
			self.qState = 0;
			body.addClass('loading');
			return History.pushState( qState.state, qState.title, qState.url );
		}
		appendix = appendix.add( cache[state.cat][state.object][state.id][1] );
		if ( partial ) {
			$(idContent).append( appendix.css('display','none') );
		} else {
			appendix = $(idContent).append( appendix );
		}
		appendix.fadeIn(250, function(){
			qState = self.qState;
			self.fading = self.qState = 0;
			qState && History.pushState( qState.state, qState.title, qState.url );
		}).find('script').remove();
		appendix.filter('script').remove();
		if ( sb ) {
			for ( i in sb )
				$('#dyn-'+i).append( cache._sb[i][ fbk.sb[state.object]||0 ] ).fadeIn(250);
			self.doNav( state, 1 );
		}

		self.scrollUp && $(window).scrollTop( self.scrollUp = 0 );
		self.fading = -1;
		self.waitingFor = 0;

		if ( strSchool == state.object ) {
			classes = ['single-school', 'postid-'+state.id];
		} else if ( 'loc' == state.object )
			classes = ['tax-loc', 'term-'+state.id, 'archive'];
		else if ( 'cat' == state.object ) {
			classes = [ 'category', 'archive' ];
		} else if ( 'page' == state.object ) {
			classes = ['page'];
			self.pageForm();
		} else if ( 'index' == state.object ) {
			classes = ['page', 'home'];
		} else if ( 'search' == state.object ) {
			classes = ['search'];
			if ( ! partial || /search_type=simple/.test(state.id) ) {
				search = {_array: state.id.split('&') };
				for ( i = 0; i < search._array.length; i++ ) {
					j = search._array[i].split('=');
					if ( j[1] )
						search[j[0]] = decodeURIComponent(j[1].replace('+',' '));
				}
				$('form[role="search"]').each(function(){this.reset();}).find(':input').each(function(){
					if ( 'undefined' != typeof search[i = this.name] )
						$(this).val(search[i]).change();
					else if ( 'undefined' != typeof search[i = $(this).attr('data-csv')] )
						$(this).val(search[i].split(',')).change();
				});
			}
		} else if ( 'post' == state.object ) {
			classes = ['single-post', 'postid-'+state.id];
		} else if ( 'news' == state.object ) {
			classes = ['news', 'paged-'+state.id];
		} else if ( 'offer' == state.object ) {
			classes = ['single-offer', 'postid-'+state.id];
		}

		if ( state.object in wp_objects && cache[state.cat][state.object][state.id][2] ) {
			cache[state.cat][state.object][state.id][2] = !1;
			self.media();
			self.postLoad(state);
		}

		if ( ! window.opera )
			$('input[type="date"], input[name^="FlightData"]', idContent).not('.hasDatepicker,[isdatepicker]').each(function(i,e){
				var tmp, opts = {};
				if ( tmp = $(e).attr('min') )
					opts.minDate = Util.parseDate(tmp);
				if ( tmp = $(e).attr('max') )
					opts.maxDate = Util.parseDate(tmp);
				$(e).datepicker(opts);
			});

		body.attr( strClass, classes.join(' ')+' c-'+(fbk.cats[state.cat]||0) );

		self.state = state;
		
		self.title( cache[state.cat][state.object][state.id][0], 1 );

		$(document).trigger('ajax_change_state', [state] );
	},
	sb = {}, selection = $(), partial;
	if ( (fbk.sb[state.object]||0) != (fbk.sb[this.state.object]||0) ) {
		sb = {left:1,right:1};
		selection = selection.add( '#dyn-left,#dyn-right' );
	}
	if ( ('search' == state.object && 'search' == this.state.object) )
		selection = selection.add( partial = '#searchresults' );
	else
		selection = selection.add( idContent );
	fade ? selection.fadeOut(250, fn) : fn();
},

switchTo: function( slice ) {
	if ( ! $('#'+slice).length )
		slice = $('.slice').eq(0).attr('id');
	if ( navigator && navigator.userAgent && /Safari/.test(navigator.userAgent) && ! /Chrome/.test(navigator.userAgent) && ! $('#'+slice).is('.active') ) {
		$('.slice.active iframe').not('.has_ph').each(function(){
			$('<div class="__iframe_ph" />').data('i',$(this)).insertAfter(this);
		}).end().detach();
		$('#'+slice).find('.__iframe_ph').each(function(){
			$(this).data('i').insertBefore(this);
		});
	}
	var both = $('#'+slice+', td.sni-'+slice);
	if ( both.is(':not(.active)') ) // double-dipping .active and show() for IE6 with IE7.js
		both.addClass('active').filter('.slice').show().end().siblings().removeClass('active').filter('.slice').hide();
	$(document).trigger('ajax_switchto', [slice]);
},

doNav: function( state, noanim ) {
	var nav = $('#navi'), speed = noanim ? 0 : 250, slideEnd = {display:'',height:'',padding:'',margin:'',overflow:''};
	
	if ( ! nav.length )
		return;
	
	if ( this.state.cat != state.cat ) {
		nav.slideUp(speed, function(){
			nav.children().detach();
			if ( !Ajax.cache[state.cat] || !Ajax.cache[state.cat].menu )
				return;
			if ( Ajax.cache[state.cat].menu.length )
				nav.append(Ajax.cache[state.cat].menu);

			nav.find('li.current-menu-ancestor').removeClass('current-menu-ancestor');
			nav.find('li.current-menu-item').removeClass('current-menu-item');

			var newCurrent = state.lang ? nav.find('a[href$="#'+state.lang+'"]').parent() : nav.find('li.menu-item-'+state.object+'-'+state.id);
			if ( newCurrent.length )
				newCurrent.addClass('current-menu-item').parents('li.menu-item').addClass('current-menu-ancestor');

			body.removeClass('c-'+fbk.cats[Ajax.state.cat]).addClass('c-'+fbk.cats[state.cat]);
			Ajax.cache[state.cat].menu.length && nav.slideDown(speed,function(){$(this).css(slideEnd)});
		});
	} else {
		var	oldVisibles = nav.find('ul.sub-menu:visible'),
			newCurrent = state.lang ? nav.find('a[href$="#'+state.lang+'"]').parent() : nav.find('li.menu-item-'+state.object+'-'+state.id),
			newAncestors = newCurrent.parents('li.menu-item'),
			newVisibles = newCurrent.children('ul').add(newCurrent.parents('ul.sub-menu')),
			toCollapse = oldVisibles.not(newVisibles),
			toExpand = newVisibles.not(oldVisibles),
			height = 0;

		if ( toExpand.length ) {
			toExpand.children()
			 .filter(newAncestors).addClass('current-menu-ancestor').end()
			 .filter(newCurrent).addClass('current-menu-item');
			toExpand = toExpand.first().css({overflow:'hidden',height:0,display:'block'});
			toExpand.children().each(function(){
				height += $(this).height();
			});
			toExpand.animate({height:height},speed,function(){$(this).css(slideEnd)}).parent()
			 .filter(newAncestors).addClass('current-menu-ancestor').end()
			 .filter(newCurrent).addClass('current-menu-item');
		}
		if ( toCollapse.length ) {
			toCollapse.first().slideUp(speed, function(){
				toCollapse.parent().removeClass('current-menu-ancestor current-menu-item');
				$(this).css('display','');
			});
		}
	}
},

foldout: function( id, allowSoloClose, noanim, context ) {
	var	h = 0,
		targetSelector = id ? '#'+id : '.foldout',
		target = $( targetSelector, context || body ).add( context ? context.filter(targetSelector) : ''),
		show = id ? target.children('.collapsed') : $(),
		hide = target.siblings('.foldout').andSelf().children('.foldout-outer').not('.collapsed'),
		forceOpen = $(),
		animSpeed = 400,
		ids = [];
	if ( ! allowSoloClose && ! show.length ) {
		if ( id && ! target.siblings('.foldout').length )
			forceOpen = forceOpen.add(target);
		else if ( ! id )
		// With no ID, we may be operating with multiple sets, so check each 
			target.each(function(){
				if ( ! $(this).siblings('.foldout').length )
					forceOpen = forceOpen.add($(this));
			});
		hide = hide.not( forceOpen.children('.foldout-outer') );
	}
	if ( id && target.is('.quote') && hide.length ) { // Allow multiple quotes to stay open
		hide = hide.filter(targetSelector + ' .foldout-outer');
	}
	if ( noanim ) {
		show.show();
		hide.hide();
	} else {
		if ( show.length ) {
			h = show.css({height:0,overflow:'hidden',display:'block'}).children().outerHeight(true);
			show.animate({height:h},animSpeed,function(){
				$(this).css('height','');
				id && $(document).trigger( 'foldout_done', [id, Ajax.state] );
			});
		}
		hide.slideUp(animSpeed, function(){
			id && $(document).trigger( 'foldout_done', [id, Ajax.state] );
		});
	}
	show.removeClass('collapsed').siblings('header').addClass('open');
	hide.addClass('collapsed').siblings('header').removeClass('open');
	forceOpen.children('header').addClass('open').siblings('.foldout-outer').removeClass('collapsed').show();

	id && $(document).trigger( 'foldout', [id, this.state] );
	id && noanim && $(document).trigger( 'foldout_done', [id, this.state] );
},

media: function() {
	if ( $.fn.colorbox )
		$('.gallery a').colorbox({rel:this.state.id});
},

pageForm: function() {
	var form = $('form',idContent), captcha = $('#captcha',form), submitting = 0;
	if ( ! form.length || form.is( '#c4p form' ) )
		return;
	if ( captcha.length ) {
		if ( /MSIE 6/i.test(navigator.userAgent) && ! window.Recaptcha )
			location.reload();
		Util.getCaptcha();
	}
	if ( ! form.is('.prepared') ) {
		form.submit(function(){
			event.preventDefault();
			var fail, fields = $(':input', form),
			warn = function(e){
				fail = 1;
				var c = $(e).css('backgroundColor');
				$(e).focus().animate({backgroundColor:'#e11'},500,function(){$(this).animate({backgroundColor:c},500)});
				return 0;
			};
			fields.filter('[required]').each(function(i,e){
				if ( ! $(e).val() )
					return warn($(e));
			});
			fields.filter('[type="email"]').each(function(i,e){
				if ( $(e).val() && ! /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test($(e).val()) )
					return warn($(e));
			});
			fields.filter('[type="tel"]').each(function(i,e){
				if ( $(e).val() && ! /^[0-9\s+\/-]+/.test($(e).val()) )
					return warn($(e));
			});
			if ( ! fail && ! submitting ) {
				$.ajax({
					type: 'POST',
					data: fields.serialize() + '&action=fbk_cform&page=' + Ajax.state.id,
					success: function(response){
						form.siblings('.error').remove();
						if ( /^__rcfail__$/.test(response) ) {
							Util.getCaptcha('error');
							form.before('<p class="error">Oops! L&ouml;sen Sie die Spamschutzaufgabe bitte nochmals.</p>')
							.find(':input').prop('disabled',false).filter('[type="submit"]').val('Erneut senden');
						} else {
							form.replaceWith(response);
							Ajax.pageForm();
						}
						submitting = 0;
					},
					error: function(jqXHR){
						if ( jqXHR.status == 503 )
							alert( Ajax.e503 );
						else
							alert( Ajax.e0 );
						form.find(':input').prop('disabled',false).filter('[type="submit"]').val('Erneut senden');
					}
				});
				fields.prop('disabled',true).filter('[type="submit"]').val('Wird gesendet...');
				submitting = 1;
			}
			return false;
		}).addClass('prepared');
	}
}

}})(jQuery);