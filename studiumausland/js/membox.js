/**
 * Membox 2.0a
 * @package Studium_Ausland
*/
/*
Data structure:

starred, unstarred: [ids]
entries: {
	id: {
		url: string,
		lbl: string,
		t: timestamp,
		$star_key: string, ...
	}, ...
}
stars: {
	id: { $star_key: 1, ... }
}

where $star_key is a string following the regex /^[a|c|f]-($key)$/
*/

(function($){
var	isVisible = 0,
	storagePersistent = {},
	storageSession = {store:'session'},
	animSpeed = 250,
	rAxis = {s:'Einzelzimmer',d:'Doppelzimmer',t:'Zweibettzimmer',m:'Mehrbettzimmer'},
	bAxis = {sc:'Ohne Verpflegung', br:'Fr&uuml;hst&uuml;ck', hb:'Halbpension', fb:'Vollpension'},
	rbMask = {s:16,d:32,t:64,m:128, sc:1,br:2,hb:4,fb:8};

MemBox = {

max: 15, // Maximum number of entries

init: function( container, current_school_or_id ) {
	this.entries = Util.retrieve( 'mb_all', storagePersistent ) || {};
	this.popped = {};

	this.container = ('string' == typeof container) ? $( '#' + container ) : container;

	if ( ! this.push( current_school_or_id ) )
		this.current = 0;

	this.container.on('click', '.close', function(){
		MemBox.pop( $(this).parent()[0].id.match(/^mb-(\d+)$/)[1] );
	});

	var self = this, toggleHover = function(){
		var e = $(this), all = $();
		if ( e.is('.course-cost td') )
			all = e.prev();
		else if ( e.is('.course-cost th') )
			all = e.next();
		else if ( e.is('.services tr') )
			all = e.children();
		e.add(all).toggleClass('hover');
	}, scrollTrigger = function(){$(window).scroll()}, winHeight = $(window).height(), winWidth = $(window).width();

	$(document).bind( 'school_loaded', function( event, school ) {
		self.push( school, true );
	}).bind( 'ajax_change_state', function( event, state ) {
		if ( 'school' == state.object ) {
			self.push( state.id );
		} else {
			self.current = 0;
		}
	}).one( 'util_init_done', function() {
		for ( var i in self.entries ) {
			self.show();
			break;
		}
	});
	RqApp.init();
},

/**
 * @param obj object|integer: A school object including an 'id' property; or an existing school's ID
 * @param noCurrent bool: Add to entries, but not at the top
 */
push: function( obj, noCurrent ) {
	var id = obj.id || obj, self=this;

	if ( ! obj || 'object' == typeof id )
		return false;
	if ( self.entries[id] ) {
		if ( 'object' == typeof obj ) {
			for ( i in obj )
				if ( ! ( i in self.entries[id] ) || self.entries[id][i] !== obj[i] )
					self.entries[id][i] = obj[i];
			for ( var i in self.entries[id] )
				if ( ! ( i in obj ) )
					delete self.entries[id][i];
		}
		return noCurrent || self.visit(id);
	}
	if ( self.popped[id] ) {
		obj = self.popped[id];
		delete self.popped[id];
	}

	if ( 'object' == typeof obj ) {
		delete obj.id;
		self.entries[id] = obj;
	}

	self.entries[id].t = new Date().getTime();
	noCurrent || (self.current = id);

	self.repaint();

	Util.store( 'mb_all', self.entries, storagePersistent );
	
	if ( ! isVisible )
		self.show();

	return true;
},

pop: function( id ) {
	if ( ! this.entries[id] )
		return false;

	$('#mb-'+id, this.container).remove();

	this.popped[id] = this.entries[id];
	delete this.entries[id];
	for ( i in this.entries ) {
		Util.store( 'mb_all', this.entries, storagePersistent );
		return true;
	}

	Util.store( 'mb_all', null, storagePersistent );
	isVisible = 0;
	this.container.slideUp(animSpeed);

	return true;
},

visit: function( id ) {
	if ( ! this.entries[id] )
		return false;

	this.current = id;

	this.entries[id].t = new Date().getTime();
	Util.store( 'mb_all', this.entries, storagePersistent );
	
	this.repaint();

	return true;
},

show: function() {
	isVisible = 1;
	this.repaint();
	this.container.show();
},

repaint: function() {
	var i, j, k, self = this, ids = self.getSortedIds(), outer, n;

	n = Math.min(ids.length,self.max);
	outer = self.container.children('#mb').hide().empty();
	for ( i = 0; i < n; i++ )
		self.addCrumb( ids[i], !!i );
	outer.show();

	if ( ids.length > self.max ) {
		for ( i = self.max; i < ids.length; i++ )
			delete self.entries[ids[i]];
		Util.store( 'mb_all', self.entries, storagePersistent );
	}
},

getSortedIds: function() {
	var i, ids = [], self = this;
	for ( i in self.entries )
		ids[ids.length] = i;
	ids.sort(function(a,b){
		// Sort by timestamp, descending, so as to build from the top down
		return self.entries[b].t - self.entries[a].t;
	});
	return ids;
},

addCrumb: function( id, append ) {
	if ( ! this.entries[id] )
		return;

	var	entry = this.entries[id],
		html = $('<li id="mb-' + id + '" class="c-'+entry.url.match(/^\/([^\/]*)/)[1]+'" />'),
		outer = $('#mb', this.container);

	html.append( '<a href="'+entry.url+'" data-title="'+entry.lbl+'"><h2>'+entry.lbl+'</h2></a>' );

	$('<div class="close" title="Aus der Liste l&ouml;schen" />').prependTo(html);

	if ( append )
		outer.append( html );
	else
		outer.prepend( html );

	return html;
},

getCurrent: function() {
	return this.get( this.current );
},

get: function( id ) {
	if ( id in this.entries )
		return this.entries[id];
	else if ( id in this.popped )
		return this.popped[id];
	else
		return false;
}
};




RqApp = {

/**
 * stage 0: No elements, no interactivity
 * stage 1: Link to request at screen/#content bottom
 * stage 2: Programme choice form
 * stage 3: Contact details form
 * stage 4: Data sent
**/
stage: 0,

elem: $('<form id="rq" />'),

init: function() {
	var self = this, winHeight = $(window).height(), winWidth = $(window).width(),
	reposition = function(){self.stage && self.position(false)};

	$(document).bind({
		ajax_switchto: reposition,
		foldout_done: reposition,
		ajax_change_state: function( event, state ) {
			if ( 'school' == state.object || self.u && /^quote(\?|$)/.test(state.id) && 'page' == state.object ) {
				self.resetData();
				self.setStage( self.u ? 10 : 1 );
			} else {
				self.setStage( 0 );
			}
		},
		foldout: function( event, id ) {
			if ( 2 == self.stage || 12 == self.stage ) {
				if ( /^a-/.test(id) )
					self.elem.find('[name="acc"]').val(id).change();
				else if ( /^c-/.test(id) )
					self.elem.find('[name="course"]').val(id).change();
			}
		},
		util_init_done: function() {
			var quote;
			if ( 'quote' == Ajax.state.id && 'page' == Ajax.state.object ) {
				quote = location.href.match( /([^\/]+)\?u=(\d+)&q=([a-z0-9]+).*/ );
				Ajax.replaceState(
					{ cat: 0, object: 'page', id: quote[0] },
					{ cat: 0, object: 'page', id: quote[1] }
				);
				Util.store( 'quote-user', quote, storageSession );
			} else {
				quote = Util.retrieve( 'quote-user', storageSession );
			}
			if ( quote ) {
				self.u = quote[2];
				self.q = quote[3];
				self.setStage( 10 );
				$('#request-link').attr('href','/'+quote[0]).html('Ihre Kostenvoranschl&auml;ge');
			}
		}
	});

	$(window).scroll(reposition).resize(function(){
		if ( self.stage ) {
			if ( navigator.userAgent && /MSIE/.test(navigator.userAgent) ) {
				var $w = $(window), newHeight = $w.height(), newWidth = $w.width();
				if ( newHeight == winHeight && newWidth == winWidth )
					return;
				winHeight = newHeight;
				winWidth = newWidth;
			}
			self.position( false );
		}
	});

	$(document.body).on( 'change', '#rq :input', function() {
		self.data || (self.data = {})
		if ( this.type == 'checkbox' )
			if ( /\[]/.test(this.name) )
				(self.data[this.name] || (self.data[this.name]={}))[this.value] = this.checked;
			else
				self.data[this.name] = this.checked;
		else
			self.data[this.name] = this.value;

		var constraints, dependent, i;
		if ( 'acc' == this.name ) {
			dependent = $(this).closest('form').find('select[name="room"],select[name="board"]');
			if ( this.value && ( constraints = parseInt( MemBox.get(self.data.school)[this.value].split('\t')[1], 16 ) ) ) {
				dependent.prop('disabled',false).children().each( function() {
					this.disabled = ! ( rbMask[this.value] & constraints );
				});
				dependent.each( function() {
					if ( ! ( rbMask[this.value] & constraints ) )
						$(this).val( $(this).children().not('[disabled]').first().val() ).change();
				});
			} else {
				dependent.prop('disabled',true)
			}
		} else if ( 'course' == this.name ) {
			dependent = $(this).closest('form').find('select[name="duration"]').empty();
			constraints = MemBox.get(self.data.school)[this.value].split( '\t' );
			for ( i = parseInt(constraints[2]); i <= constraints[3]; i++ )
				if ( ! constraints[4].match( ',' + i + ',' ) )
					dependent.append(
						'<option value="' + i +'">'
						+ (i+1) + ('s' == constraints[1] ? ' Semester' : ( ' Woche' + (i?'n':'') ) )
						+ '</option>'
					);
			self.data.duration = dependent.val( self.data.duration ).val();
		}
	}).on( 'click', '#rq.stage-1, #rq.stage-10', function() {
		var incr = 1;
		if ( 10 == self.stage && MemBox.current ) {
			self.data.school = MemBox.current;
			incr = 2;
		}
		self.setStage( self.stage + incr );
	}).on( 'click', '#rq-back', function() {
		if ( self.stage == 11 && ! ( 'quote' == Ajax.state.id && 'page' == Ajax.state.object || 'school' == Ajax.state.object ) )
			self.setStage( 0 );
		else
			self.setStage( self.stage - 1 );
	}).on( 'submit', '#rq', function() {
		var invalid, complete_cb = function( jqXHR, textStatus ) {
			if ( 'success' == textStatus ) {
				self.setStage( self.stage + 1, jqXHR.responseText );
			} else {
				self.elem.find(':input').prop('disabled',false).filter('#rq-next').val('Erneut senden')
				.end().filter('[name="acc"]').change();
				self.elem.children('nav').prepend('<div class="ybox">Beim Versenden des Formulars ist ein Fehler aufgetreten. Sollte sich dies wiederholen, bitte melden Sie uns den Vorfall.</div>');
				self.position(true);
			}
		};
		if ( 2 == self.stage || 11 == self.stage || 3 == self.stage && fbk.noAutoQ )
			self.setStage( self.stage + 1 );
		else if ( 3 == self.stage && ! fbk.noAutoQ || 4 == self.stage && fbk.noAutoQ || 12 == self.stage ) {
			self.elem.find(':input[required]').each( function() {
				if ( ! this.value || 'email' == this.type && ! /.+@.+\..+/.test(this.value) ) {
					$(this).addClass('invalid').focus();
					invalid = true;
					return false;
				}
			});
			if ( ! invalid ) {
				$.ajax({
					type: 'POST',
					data: 'action=' + ( 12 == self.stage ? 'fbk_add_quote' : 'fbk_request' ) + '&school=' + self.data.school
					 + ( self.u ? '&u=' + self.u + '&q=' + self.q : '' )
					 + '&' + self.elem.serialize(),
					complete: complete_cb
				});
				self.elem.find(':input').change().prop('disabled',true).filter('#rq-next').val('Wird gesendet...');
			}
		}
		return false;
	}).on( 'click', '#page', function( event ) {
		if ( ! $(event.target).closest('#rq').length && ! $(event.target).closest('.single-school #content').length ) {
			if ( self.stage > 1 && ( self.stage < 4 && ! fbk.noAutoQ || self.stage < 5 && fbk.noAutoQ ) )
				self.setStage( 1 );
			else if ( self.stage > 10 )
				self.setStage( 10 );
		}
	})

	// Push quote features
	.on( 'click', '.open-push-quote', function() {
		$('#push-quote').insertAfter( $(this).closest('.quote-pick').hide() ).show().find('input[name="birthdate"]').removeClass('hasDatepicker').datepicker();
	}).on( 'click', '#push-quote .cancel', function() {
		$('#push-quote').hide().siblings('.quote-pick').show();
	}).on( 'submit', '#push-quote', function() {
		var form = $(this);
		$.ajax({
			method: 'POST',
			data: 'action=fbk_push_quote&quote=' + form.closest('section.quote').attr('id').match(/quote-(.*)/)[1]
				+ '&u=' + self.u + '&q=' + self.q + '&' + form.serialize(),
			complete: function( jqXHR, textStatus ) {
				if ( 'success' == textStatus ) {
					form.hide().after( jqXHR.responseText ).find('[type="submit"]').val('Absenden »'); // UTF-8 &raquo;
				} else {
					form.prepend('<p class="ybox">Beim Versenden des Formulars ist ein Fehler aufgetreten. '
					+ 'Sollte sich dies wiederholen, bitte melden Sie uns den Vorfall.</p>')
					.find('[type="submit"]').val('Erneut senden');
				}
				form.find(':input').prop('disabled',false);
			}
		});
		form.find(':input').prop('disabled',true).filter('[type="submit"]').val('Wird gesendet...');
		return false;
	});

	$('#request-link').click( function( event ) {
		if ( self.u )
			return true;
		if ( 1 == self.stage || 10 == self.stage ) {
			var incr = 1;
			if ( 10 == self.stage && MemBox.current ) {
				self.data.school = MemBox.current;
				incr = 2;
			}
			self.setStage( self.stage + incr );
			event.stopPropagation();
			return false;
		}
	});
},

setStage: function( toStage, message ) {
	var i, j, k, table, entry, c, a, d, f, change = $(), self=RqApp;
	self.elem.detach().removeClass();
	switch ( toStage ) {
		default:
			toStage = 0;
			break;
		case 1:
		case 10:
			;
			self.elem.html(10 == toStage ? 'Einen weiteren Kostenvoranschlag hinzuf&uuml;gen &raquo;' : 'Unverbindlicher Kostenvoranschlag &raquo;');
			self.position();
			break;
		case 11:
			k = self.elem.empty().append('<div id="stage-11"><label for="stage-11">Schule: </label> <select name="school" id="stage-11" />'
			+ '<p class="ybox">Sehen Sie sich auf unserer Seite um, um weitere Schulen ausw&auml;hlen zu k&ouml;nnen!</p>'
			+'<nav><input type="button" id="rq-back" value="Abbrechen" tabindex="3"><input type="submit" id="rq-next" value="Weiter &raquo;" tabindex="2"></nav>'
			+ '</div>').find('select');
			j = MemBox.getSortedIds();
			for ( i = 0; i < j.length; i++ )
				k.append('<option value="'+j[i]+'">' + MemBox.get(j[i]).lbl + '</option>');
			if ( self.data && self.data.school )
				k.val(self.data.school)
			else
				change = k;
			break;
		case 12:
		case 2:
			if ( 1 == self.stage || 11 == self.stage ) {
				self.data || (self.data={});
				self.data.course = $('.slice-kurse').find('.foldout-outer').not('.collapsed').parent().attr('id');
				self.data.acc = $('.slice-unterkunft').find('.foldout-outer').not('.collapsed').parent().attr('id');
			}
			if ( 12 == toStage || ! self.elem.find('#stage-2').length ) {
				entry = MemBox.get(self.data.school);
				table = self.elem.empty().append('<div><table id="stage-2"><tbody /></table></div>').find('tbody');
				f = '';
				c = $('<select name="course" />');
				a = $('<select name="acc"><option value="">Keine Unterkunft</option></select>');
				d = $('<td style="white-space:nowrap;"><div>Beginn: <input name="start"></div><div>Dauer: <select name="duration"><option>1 Semester</option></select></div></td>');
				for ( i in entry ) {
					if ( /^c-/.test(i) )
						c.append('<option value="'+i+'">'+entry[i].split('\t')[0]);
					else if ( /^a-/.test(i) )
						a.append('<option value="'+i+'">'+entry[i].split('\t')[0]);
					else if ( /^f-/.test(i) ) {
						fee = entry[i].split('\t');
						f += '<li><input type="checkbox" name="fees[]" value="'+i+'" id="cb-'+i+'"> <label for="cb-'+i+'">'
						+fee[0]+(fee[1] ? ' ('+fee[1]+')' : '')+'</label></li>';
					}
				}
				a = $( '<td>'
+ '<select name="room"><option value="s">Einzelzimmer<option value="d">Doppelzimmer<option value="t">Zweibettzimmer</option><option value="m">Mehrbettzimmer</select>'
+ '<select name="board"><option value="sc">Ohne Verpflegung<option value="br">Fr&uuml;hst&uuml;ck<option value="hb">Halbpension<option value="fb">Vollpension</select>'
				).prepend(a);
				table.append('<tr><th>Kurs</th><th>Zeitraum</th><th>Unterkunft</th></tr>')
				.append( 
					$('<tr />')
					 .append($('<td />').append(c))
					 .append(d)
					 .append(a)
				);
				if ( f ) {
					a.children().not(':last-child').after('<br>');
					table.children()
					 .first().append('<th>Weitere Leistungen</th>')
					 .siblings().append('<td><ul>'+f+'</ul></td>');
				}
				change = a.children('select[name="acc"]').add(c);
				table.find(':input').attr('tabindex',1)
				.filter('[name="start"]').datepicker({
					minDate: '+1',
					beforeShowDay: function(date){ return [date.getDay() == 1, '', '']; },
					beforeShow: function(i){
						setTimeout( function() {
							var w = (i=$(i)).datepicker('widget');
							w.css('top',i.offset().top - w.innerHeight() - $(window).scrollTop() - 2 + 'px');
						}, 50 );
					}
				});
			}
			self.elem.children('h2,nav').remove();
			self.elem.prepend('<h2>Kostenvoranschlag &ndash; &Uuml;ber welches Programm wollen Sie mehr wissen?</h2>')
			.append('<nav><input type="button" id="rq-back" value="Abbrechen" tabindex="3"><input type="submit" id="rq-next" value="'
			+ ( 12 == toStage ? 'Hinzuf&uuml;gen' : 'Weiter' ) + ' &raquo;" tabindex="2"></nav>');
			self.elem.find('#stage-3 :input').prop('disabled',true);
			break;
		case 3:
			if ( ! self.elem.find('#stage-3').length ) {
				self.elem.append('<div><table id="stage-3"><tbody>'
				+ '<tr><th>Anrede</th><td><select name="salutation"><option>Frau<option>Herr</select></td>'
				+  '<th>Stra&szlig;e</th><td><input name="street"></td></tr>'
				+ '<tr><th>Vorname</th><td><input required name="first_name"></td>'
				+  '<th>PLZ</th><td><input name="postalcode"></td></tr>'
				+ '<tr><th>Nachname</th><td><input required name="last_name"></td>'
				+  '<th>Ort</th><td><input name="city"></td></tr>'
				+ '<tr><th>E-Mail</th><td><input required name="email" type="email"></td>'
				+  '<th>Bundesland</th><td><input name="state"></td></tr>'
				+ '<tr><th>Telefon</th><td><input required name="phone" type="tel"></td>'
				+  '<th>Land</th><td><select name="country"><option>Deutschland<option>&Ouml;sterreich<option>Schweiz</select></td></tr>'
				+ '<tr><th>Bevorzugte Kontaktmethode</th><td><select name="contact_method"><option value="phone">Telefon<option value="mail">E-Mail</select></td>'
				+  '<th>Nationalit&auml;t</th><td><input name="nationality"><br><i>Diese Angabe ist wegen der unterschiedlichen<br>Einreisebestimmungen wichtig.</i></td></tr>'
				+'</tbody><tfoot>'
				+ '<tr><td colspan="4">Bitte f&uuml;llen Sie alle mit <span class="required">*</span> markierten Felder aus.'
				+  '<br>Ihre Daten werden selbstverst&auml;ndlich vertraulich behandelt und unter keinen Umst&auml;nden weitergegeben.</td></tr>'
				+ '<tr><td colspan="4"><input type="checkbox" name="newsletter" checked tabindex="4" id="cb_newsletter">'
				+  '<label for="cb_newsletter">Ja, ich m&ouml;chte den Studium-Ausland-Newsletter per E-Mail erhalten.</label></td></tr>'
				+ ( fbk.noAutoQ ? '' : '<tr><td colspan="4">Bitte beachten Sie, dass diese Anfrage v&ouml;llig unverbindlich ist. Wenn Sie dieses Formular abschicken,'
				  + ' schicken wir Ihnen unverz&uuml;glich eine E-Mail mit Ihrem Kostenvoranschlag. Sie k&ouml;nnen dann in aller Ruhe abw&auml;gen'
				  + ' und melden sich wieder bei uns, sobald Sie sich entschieden haben.</td></tr>'
				  )
				+'</tfoot></table></div>'
				);

				table = self.elem.find('#stage-3 tbody');
				table.find('input[required]').parent().prev().prepend('<span class="required">* </span>');
				table.find('td:nth-child(2) :input').attr('tabindex', 2);
				table.find('td:nth-child(4) :input').attr('tabindex', 3);
			}
			self.elem.children('h2,nav').remove();
			self.elem.prepend('<h2>Kostenvoranschlag &ndash; Wie erreichen wir Sie?</h2>')
			.append('<nav><input type="button" id="rq-back" value="&laquo; Zur&uuml;ck" tabindex="6"><input type="submit" id="rq-next" value="'
			 + ( fbk.noAutoQ ? 'Weiter &raquo;' : 'Absenden' ) + '" tabindex="5"></nav>');
			self.elem.find('#stage-3 :input').prop('disabled',false);
			break;
		case 13:
			message = $(message);
			message.filter('section.quote').children('header').addClass('open').end().insertAfter('section.quote:last');
			try {
				var cache = Ajax.cache[0].page[ ('quote' in Ajax.cache[0].page) ? 'quote' : 'quote?u='+self.u+'&q='+self.q ];
				cache[1] = cache[1].add( message.filter('section.quote') );
			} catch(e){}
			message = message.filter( 'quote'==Ajax.state.id && 'page'==Ajax.state.object ? '#at-quote' : '#not-at-quote');
		case 4:
			if ( fbk.noAutoQ ) {
				if ( ! self.elem.find('#stage-4').length ) {
					self.elem.append('<div><table id="stage-4"><tbody>'
					+ '<tr><th>Geburtsdatum</th><td><input name="birthdate"></td></tr>'
					+ '<tr><th>Beruf</th><td><input name="job"></td></tr>'
					+ '<tr><th>Sprachkenntnisse</th><td><select name="lang_level">'
						+ '<option value="0">Keine Vorkenntnisse'
						+ '<option value="1">Grundkenntnisse'
						+ '<option value="2">Untere Mittelstufe'
						+ '<option value="3">Mittelstufe'
						+ '<option value="4">Obere Mittelstufe'
						+ '<option value="5">Fortgeschrittene'
					+ '</select></td></tr>'
					+ '<tr><th>Bisherige Lernerfahrungen</th><td><textarea name="experience"></textarea></td></tr>'
					+ '<tr><th>Sonstiges, Bemerkungen</th><td><textarea name="comments"></textarea></th></tr>'
					+'</tbody><tfoot>'
					+ '<tr><td colspan="4">Bitte beachten Sie, dass diese Anfrage v&ouml;llig unverbindlich ist. Wenn Sie dieses Formular abschicken,'
					+  ' schicken wir Ihnen unverz&uuml;glich eine E-Mail mit Ihrem Kostenvoranschlag. Sie k&ouml;nnen dann in aller Ruhe abw&auml;gen'
					+  ' und melden sich wieder bei uns, sobald Sie sich entschieden haben.</td></tr>'
					+'</tfoot></table></div>'
					);
				}
				self.elem.children('h2,nav').remove();
				self.elem.prepend('<h2>Mit den folgenden Angaben erleichtern Sie uns die Arbeit:</h2>')
				.append('<nav><input type="button" id="rq-back" value="&laquo; Zur&uuml;ck" tabindex="6"><input type="submit" id="rq-next" value="Absenden" tabindex="5"></nav>');
				self.elem.find('#stage-4 :input').prop('disabled',false);
				break;
			}
		case 5:
			self.elem.empty().append('<h2>Vielen Dank!</h2>' ).append( message );
			if ( ! self.elem.find('a').length )
				setTimeout( self.setStage, 4000, (4 == toStage || 5 == toStage) ? 0 : 10 );
			break;
	}

	self.stage = toStage;
	self.elem.addClass('stage-'+toStage);

	if ( table && self.data ) {
		for ( i in self.data ) {
			k = table.find('[name="'+i+'"]');
			if ( k.is('[type="checkbox"]') )
				if ( /\[]/.test(i) )
					for ( j in self.data[i] )
						k.filter('[value="'+j+'"]').prop('checked', self.data[i][j]);
				else
					k.prop('checked', self.data[i]);
			else
				k.val( self.data[i] );
		}
	}

	self.position( true );

	change.change();
},

position: function( reEvaluate ) {
	if ( ! this.stage )
		return this.elem.add('#rq-ph').detach();

	this.elem.removeClass('fixed static');
	if ( reEvaluate || ! this.height ) {
		this.elem.css({
			top: 0,
			left: 0,
			width: '',
			maxWidth: ((1==this.stage||10==this.stage) ? $('#content').width() : $('#page>.w1k').width() - 20 ) + 'px',
			position: 'fixed'
		}).appendTo(document.body);
		this.height = this.elem.innerHeight() + parseInt((this.elem.css('border-top-width').match(/\d+/)||'0')[0]);
		this.outerHeight = this.elem.outerHeight(true);
	}

	var $win = $(window), top = $win.height() - this.height, placeholder, ph_offset, css;

	if ( 1 == this.stage || 10 == this.stage ) {
		placeholder = $('#rq-ph');
		if ( ! placeholder.length )
			placeholder = $('<div id="rq-ph" />').appendTo('#content');
		placeholder.height(this.outerHeight);
		ph_offset = placeholder.offset();
		if ( top + $win.scrollTop() > ph_offset.top ) {
			return this.elem.addClass('static').css({position:'static'}).appendTo(placeholder);
		} else {
			css = {
				position: 'fixed',
				top: top + 'px'
			};
			this.elem.addClass('fixed');
		}
		css.left = ( ph_offset.left + placeholder.width() - this.elem.css('position','fixed').outerWidth() ) + 'px';
	} else {
		placeholder = $('#page>.w1k');
		css = {
			top: top + 'px',
			position: 'fixed',
			left: placeholder.offset().left + 10 + 'px',
			width: placeholder.width() - 20 + 'px'
		};
		this.elem.addClass('fixed');
	}

	for ( i in css )
		if ( this.elem.css(i) != css[i] )
			this.elem.css(i, css[i]);

	this.elem.appendTo(document.body);
},

resetData: function() {
	if ( this.data ) {
		delete this.data.course;
		delete this.data.acc;
		delete this.data.room;
		delete this.data.board;
		delete this.data['fees[]'];
	}
	(this.data || (this.data = {})).school = MemBox.current;
}
};


})(jQuery);