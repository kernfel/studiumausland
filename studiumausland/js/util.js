/**
 * @package Studium_Ausland
 * @version 1.0
 *
 * @dependencies: JSON, jQuery
 */

(function(d){d.each(["backgroundColor","borderBottomColor","borderLeftColor","borderRightColor","borderTopColor","color","outlineColor"],function(f,e){d.fx.step[e]=function(g){if(!g.colorInit){g.start=c(g.elem,e);g.end=b(g.end);g.colorInit=true}g.elem.style[e]="rgb("+[Math.max(Math.min(parseInt((g.pos*(g.end[0]-g.start[0]))+g.start[0]),255),0),Math.max(Math.min(parseInt((g.pos*(g.end[1]-g.start[1]))+g.start[1]),255),0),Math.max(Math.min(parseInt((g.pos*(g.end[2]-g.start[2]))+g.start[2]),255),0)].join(",")+")"}});function b(f){var e;if(f&&f.constructor==Array&&f.length==3){return f}if(e=/rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)/.exec(f)){return[parseInt(e[1]),parseInt(e[2]),parseInt(e[3])]}if(e=/rgb\(\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*,\s*([0-9]+(?:\.[0-9]+)?)\%\s*\)/.exec(f)){return[parseFloat(e[1])*2.55,parseFloat(e[2])*2.55,parseFloat(e[3])*2.55]}if(e=/#([a-fA-F0-9]{2})([a-fA-F0-9]{2})([a-fA-F0-9]{2})/.exec(f)){return[parseInt(e[1],16),parseInt(e[2],16),parseInt(e[3],16)]}if(e=/#([a-fA-F0-9])([a-fA-F0-9])([a-fA-F0-9])/.exec(f)){return[parseInt(e[1]+e[1],16),parseInt(e[2]+e[2],16),parseInt(e[3]+e[3],16)]}if(e=/rgba\(0, 0, 0, 0\)/.exec(f)){return a.transparent}return a[d.trim(f).toLowerCase()]}function c(g,e){var f;do{f=d.curCSS(g,e);if(f!=""&&f!="transparent"||d.nodeName(g,"body")){break}e="backgroundColor"}while(g=g.parentNode);return b(f)}var a={aqua:[0,255,255],azure:[240,255,255],beige:[245,245,220],black:[0,0,0],blue:[0,0,255],brown:[165,42,42],cyan:[0,255,255],darkblue:[0,0,139],darkcyan:[0,139,139],darkgrey:[169,169,169],darkgreen:[0,100,0],darkkhaki:[189,183,107],darkmagenta:[139,0,139],darkolivegreen:[85,107,47],darkorange:[255,140,0],darkorchid:[153,50,204],darkred:[139,0,0],darksalmon:[233,150,122],darkviolet:[148,0,211],fuchsia:[255,0,255],gold:[255,215,0],green:[0,128,0],indigo:[75,0,130],khaki:[240,230,140],lightblue:[173,216,230],lightcyan:[224,255,255],lightgreen:[144,238,144],lightgrey:[211,211,211],lightpink:[255,182,193],lightyellow:[255,255,224],lime:[0,255,0],magenta:[255,0,255],maroon:[128,0,0],navy:[0,0,128],olive:[128,128,0],orange:[255,165,0],pink:[255,192,203],purple:[128,0,128],violet:[128,0,128],red:[255,0,0],silver:[192,192,192],white:[255,255,255],yellow:[255,255,0],transparent:[255,255,255]}})(jQuery);

Util = {
	df: '-'
};

/**
 * Storage access functions. Uses localStorage with a fallback to cookies.
 * All data that evaluates to false serves to remove the entry.
 */
Util.store = function( storage_id, data, args ) {
	data = data || null;
	args = args || {};
	var s;
	if ( ! args.store || args.store == 'local' ) {
		try {
			s = localStorage;
		} catch(e) {
			s = 0;
		}
	} else if ( args.store == 'session' ) {
		try {
			s = sessionStorage;
		} catch(e) {
			s = 0;
			args.exp = 1;
		}
	}

	if ( data && ! args.nojson )
		data = JSON.stringify(data);
	
	if ( s ) {
		data ? s.setItem( storage_id, data ) : s.removeItem( storage_id );
	} else if ( ! args.fallback || args.fallback == 'cookie' ) {
		var exp=new Date();
		exp.setTime( exp.getTime() + ( data ? ((args.exp||180)*24*60*60*1000) : (-1) ) );
		document.cookie = storage_id + '=' + encodeURIComponent( data ) + '; expires=' + exp.toUTCString() + '; path=/';
	} else if ( args.fallback == 'dom' ) {
		this.cache = this.cache || {};
		if ( data )
			this.cache[storage_id] = data;
		else
			delete this.cache[storage_id];
	}
};
Util.retrieve = function( storage_id, args ) {
	var c, s, data;
	args = args || {};
	if ( ! args.store || args.store == 'local' ) {
		try {
			s = localStorage;
		} catch(e) {
			s = 0;
		}
	} else if ( args.store == 'session' ) {
		try {
			s = sessionStorage;
		} catch(e) {
			s = 0;
		}
	}

	if ( s ) {
		data = s.getItem( storage_id );
	} else if ( ! args.fallback || args.fallback == 'cookie' ) {
		c = document.cookie.match( new RegExp(storage_id+'=[^;]*') );
		if ( c && ( c = c[0].replace(/^[^=]*=/,'') ) )
			data = decodeURIComponent( c );
	} else if ( args.fallback == 'dom' ) {
		try {
			data = this.cache[storage_id] || null;
		} catch(e) {
			data = null;
		}
	}

	return args.nojson ? data : JSON.parse( data||'""' );
};

/**
 * Clone of PHP func array_keys
 */
Util.keys = function( object ) {
	var i, keys = [];
	for ( i in object )
		if ( object.hasOwnProperty( i ) )
			keys[ keys.length ] = i;
	return keys;
}

Util.setCookie = function(name,value,path,ttl) {
	if(!path)	path='/';
	if(!ttl)	ttl=14;
	var exp=new Date();
	exp.setTime(exp.getTime()+(ttl*86400000));
	document.cookie=name.replace(/[^a-z0-9_]/gi,'')+'='+encodeURIComponent(value)+'; expires='+exp.toUTCString()+'; path='+path;
}

Util.getCookie = function(name) {
	name=name.replace(/[^a-z0-9_]/gi,'');
	var c=document.cookie.match(new RegExp(name+'=([^;]*)'));
	if(c) return decodeURIComponent(c[1]);
	else return '';
}

Util.setCookiePart = function(name,partname,value) {
	var o = this.getCookie(name).split("~"), replaced = 0, search = new RegExp("^"+partname+"!");
	if(!o[0]&&value)
		return this.setCookie(name,partname+"!"+value);
	for(var i in o) {
		if(search.test(o[i])){
			if(value)
				o[i]=partname+"!"+value;
			else
				o.splice(i,1);
			replaced=1;
			break;
		}
	}
	if(!replaced&&value)
		o[o.length] = partname+"!"+value;
	this.setCookie(name,o.join("~"));
}

Util.getCookiePart = function(name,partname) {
	var o = this.getCookie(name).split("~"), search = new RegExp("^"+partname+"!(.*)");
	for ( var i in o )
		if ( search.test(o[i]) )
			return o[i].match(search)[1];
	return '';
}

Util.datestr = function(date) {
	if ( ! date.getDate )
		return '';
	return ( ( this.df && '-' == this.df )
		? date.getFullYear() + '-' + (date.getMonth()<9?'0':'') + (1+date.getMonth()) + '-' + (date.getDate()<10?'0':'') + date.getDate()
		: (date.getDate()<10?'0':'') + date.getDate() + '.' + (date.getMonth()<9?'0':'') + (1+date.getMonth()) + '.' + date.getFullYear()
	);
}

Util.parseDate = function(str) {
	str = str||'';
	var date,
	ymd = str.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/),
	dmy = str.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/),
	dm = str.match(/^(\d{1,2})\.(\d{1,2})\.?$/);
	if ( ymd ) {
		this.df = '-';
		date = [ ymd[1], ymd[2], ymd[3] ];
	} else if ( dmy ) {
		this.df = '.';
		date = [ dmy[3], dmy[2], dmy[1] ];
	} else if ( dm ) {
		this.df = '.';
		date = [ (new Date).getFullYear(), dm[2], dm[1] ];
	}
	return date ? new Date( date[0], date[1]-1, date[2] ) : new Date();
}

Util.getCaptcha = function( addClass ) {
	if ( window.Recaptcha ) {
		Recaptcha.create( fbk.rc_pk, 'captcha', RecaptchaOptions );
		jQuery('#captcha').addClass(addClass);
	} else {
		jQuery.getScript( fbk.rc_url, function(){
			Recaptcha.create( fbk.rc_pk, 'captcha', RecaptchaOptions );
			jQuery('#captcha').addClass(addClass);
		});
	}
}


Calc = new Object;
	/**
	 * Gets the accommodation price at the given coordinates in the requested output calc.
	 * Original calc can be overridden using calc_override.
	 *
	 * data: object, singular acc price data
	 * coords: object {x,y,weeks[,start]}
	 * calc_override: str | empty, input format. Specify to override data.calc. Mandatory for data.calc="cp".
	 * total: boolean. False to return in data.calc, true to return the full price
	 * partial: boolean. If true, will deduct a price even if coords.weeks is less than the minimum dictated by data
	 */
Calc.getAccPrice = function( data, coords, calc_override, total, partial ) {
	if ( data.values[coords.x] && data.values[coords.x][coords.y] && data.values[coords.x][coords.y].length )
		return Calc.getPrice(
			{ values: data.values[coords.x][coords.y], calc: calc_override || data.calc },
			coords, total, partial
		);
	else
		return 0;
}
	
/**
 * Calculate the price for a sparse one-dimensional array of values
 *
 * data: object {calc, values}
 * coords: object {weeks[, start]} Warning - coords.weeks counts from offset 0, not from offset coords.start!
 * total: boolean. False to return in data.calc, true to return the full price
 * partial: boolean. If true, will deduct a price even if coords.weeks is less than the minimum dictated by data
 */
Calc.getPrice = function( data, coords, total, partial ) {
	var result = 0, i, j, set = 0;

	// Use zeroed lines as halting condition, or populate i with the highest field available.
	for ( i = coords.weeks-1; i >= 0; i-- ) {
		if ( 0 == data.values[i] )
			return 0;
		else if ( data.values[i] )
			break;
	}
	if ( ! data.values[i] && partial ) {
		// Oh gee, ain't it great to have sparse arrays? -.-
		var tmp = [];
		for ( j in data.values )
			if ( ! isNaN(j) )
				tmp[j] = data.values[j];
		if ( tmp.length < coords.weeks )
			return null;
		for ( i = coords.weeks; i < tmp.length; i++ )
			if ( data.values[i] )
				break;
	} else if ( ! data.values[i] )
		return null;
	
	if ( !total )	// Just return the value in the given calc
		if ( 'tot' == data.calc || 'stot' == data.calc )
			return data.values[i] * (coords.weeks) / (i+1);
		else
			return data.values[i];

	if ( 'nth' == data.calc ) {
		if ( partial && i >= coords.weeks )
			result = data.values[i] * coords.weeks / (i+1);
		else {
			result = (coords.weeks - i) * data.values[i];
			for ( j = 0; j < i; j++ ) {
				if ( data.values[j] )
					set = data.values[j];
				result += set;
			}
		}
	} else if ( 'pw' == data.calc || 'add' == data.calc || 'ps' == data.calc ) {
		result = data.values[i] * coords.weeks;
	} else if ( 'tot' == data.calc || 'stot' == data.calc ) {
		result = data.values[i] * coords.weeks / (i+1);
	} else if ( 'un' == data.calc ) {
		result = data.values[i];
// if 'cumulative' == data.calc :
//		for ( ; i >= 0; i-- )
//			if ( data.values[i] )
//				result += data.values[i];
	}
	
	if ( coords.start ) {
		result -= Calc.getPrice( data, {weeks:coords.start}, total, partial );
	}
	
	return result;
}

/**
 * Return an array of prices
 * col: Sparse array of given prices
 * coords: {start, weeks} Warning - coords.weeks counts from offset 0, not from offset coords.start!
 * calc: col's calc type
 */
Calc.getFullCol = function( col, coords, calc ) {
	var result = [], i, j, set;
	if ( ! col.length )
		return result;

	coords.start || (coords.start = 0);
	
	if ( null == col[coords.start] ) {
		for ( i = coords.start-1; i >= 0; i-- )
			if ( null != col[i] ) {
				set = i;
				break;
			}
		if ( 'undefined' == typeof set )
			for ( i = coords.start; i < coords.start + coords.weeks; i++ )
				if ( null != col[i] ) {
					coords.start = set = i;
					break;
				}
		if ( 'undefined' == typeof set )
			return result;
	} else {
		set = coords.start;
	}
	for ( i = coords.start; i < coords.weeks; i++ ) {
		if ( null != col[i] ) {
			set = i;
			result[i] = col[i];
		} else {
			switch ( calc ) {
				case 'pw':
				case 'add':
				case 'nth':
				case 'ps':
					result[i] = col[set];
					break;
				case 'tot':
				case 'stot':
					result[i] = col[set] * (i+1) / (set+1);
					break;
			}
		}
	}
	return result;
}

/**
 * Initialisation
 */
jQuery(document).ready(function($){

	if ( localStorage && Util.retrieve( 'schools_version' ) )
		localStorage.clear();

	if ( window.fbk ) {

		jQuery.ajaxSetup({ url: fbk.ajaxurl, cache: true });
		Ajax.init();

		MemBox.init('visits', fbk.current||0);

		Util.ready=1;
		$(document).trigger('util_init_done');
	}

	$('input.fbk_date').datepicker();
	if ( !window.opera )
		$('input[type="date"]').each(function(i,e){
			var tmp, opts = {};
			if ( tmp = $(e).attr('min') )
				opts.minDate = Util.parseDate(tmp);
			if ( tmp = $(e).attr('max') )
				opts.maxDate = Util.parseDate(tmp);
			$(e).datepicker(opts);
		});
	
	$('form').live('reset',function(){
		return $(this).find(':input').filter('[type="text"],[type="search"]').each(function(){
			this.defaultValue = '';
		}).end().filter('select').each(function(){
			$(this).children().removeAttr('selected');
		});
	});
});