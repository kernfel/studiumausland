/**
 * @package Studium_Ausland
 */

jQuery(document).ready(function($){
	recalc_coursestack = function( event ) {
		var allInputs = $(event.target).closest('.stack-details').find('input.stackval'), first = false, last, index, i, col = [];
		if ( $(event.target).is('input') ) {
			index = parseInt( $(event.target).attr('id').match(/(\d+)\]$/)[1] );
			if ( ! $(event.target).val() ) {
				// Search downwards for previous set value
				for ( i = index - 1; i >= 0; i-- ) {
					if ( allInputs.eq(i).val() ) {
						first = i;
						break;
					}
				}
				if ( first === false )
					// Search upwards for first set value
					allInputs.slice(index).each( function(i,e) {
						if ( $(this).attr('placeholder','').val() ) {
							first = index + i;
							return false;
						}
					});
			} else {
				first = index;
			}
			if ( first !== false )
				// Search upwards for next set value
				allInputs.slice( first + 1 ).each( function(i,e) {
					if ( $(this).val() ) {
						last = first + i + 1;
						return false;
					}
				});
		} else {
			allInputs.each( function(i,e) {
				if ( $(this).val() ) {
					first = i;
					return false;
				}
			});
		}
		if ( first === false )
			allInputs.attr('placeholder', '');
		else {
			if ( ! last )
				last = allInputs.length;
			allInputs.slice(first, last).each( function(i,e) {
				var v = $(this).val();
				if ( v )
					col[first+i] = parseInt(v);
			});
			col = Calc.getFullCol( col, {start:first, weeks:last}, $(event.target).closest('.stack-details').find('.stack-calc').val() );
			allInputs.slice(first, last).each( function(i,e) {
				$(this).attr( 'placeholder', col[first+i] ? col[first+i] : '' );
			});
		}
	};

	fbk_hidden_eds = {};

	// Make lines sortable within their container
	$('.fbk_cf_sortable').sortable({
		containment: '#post-body-content',
		handle: '.fbk_cf_sortable_handle',
		start: function(event, ui){
			ui.item.find('.fbk_cf_mceInitialised').each(function(i,e){
				fbk_hidden_eds[e.id] = tinyMCE.get(e.id).isHidden();
				tinyMCE.execCommand('mceRemoveControl',false,e.id);
			});
		},
		stop: function(event, ui){
			fbk_cf_update_line_ids( ui.item.parent() );
			ui.item.find('.fbk_cf_mceInitialised').each(function(i,e){
				tinyMCE.execCommand('mceAddControl',false,e.id);
				if(fbk_hidden_eds[e.id])
					tinyMCE.execCommand('mceToggleEditor',false,e.id);
			});
			fbk_hidden_eds = {};
		}
	});
	// Adds event listeners
	$('._fbk-cf_line').each(function(i,e){
		var kids=$('a.fbk_cf_js',e);
		kids.eq(1).click(fbk_cf_clone);
		kids.eq(2).click(fbk_cf_remove);
		kids.eq(3).click(function(){
			fbk_cf_toggleExpand( $(this).closest('._fbk-cf_line') );
		});
		kids.parent()
		.mouseenter(function(){$(this).attr('class','ui-state-hover')})
		.mouseleave(function(){$(this).attr('class','ui-state-default')});
	});
	// Fixes broken line numbers on F5
	$('.fbk_cf_sortable').each(function(i,e){
		fbk_cf_update_line_ids( $(e) );
	});
	//Disable <p>-removal in tinyMCE
	$('body').bind('beforePreWpautop',function(ev,o){
		o.data='';
	}).bind('afterPreWpautop',function(ev,o){
		o.data=o.unfiltered;
	});
	// Stacks: Bind controls
	$('#poststuff').on( 'click', '.open-stack', function( event ) {
		var item = $(event.target).closest('.stack-closed');
		item.removeClass('stack-closed').addClass('stack-open');
		if ( item.parent().is( '._fbk-cf_accstack' ) && ! item.find('.accstack-top').length )
			bindAccStack( item );
		return false;
	}).on( 'click', '.close-stack', function( event ) {
		$(event.target).closest('.stack-open').removeClass('stack-open').addClass('stack-closed');
		return false;
	}).on( 'click', '.kill-stack', function( event ) {
		if ( confirm( 'Diese Preisliste wirklich l\u00F6schen?' ) )
			$(event.target).closest('.stack-item').empty().hide();
		return false;
	}).on( 'click', '.dupe-stack', function( event ) {
		var proto = $(event.target).closest('.stack-item'),
		proto_inputs = $(event.target).parent().find(':input'),
		dupe = proto.clone().removeClass('proto-stack stack-closed').addClass('stack-open').html(
			proto.html().replace( /\[proto\]/g, '[' + proto.siblings('.stack-item').length + ']' )
		).insertBefore( proto );
		dupe.find('.dupe-stack').parent().find(':input').each( function(i,e){
			$(e).val( proto_inputs.eq(i).val() );
		});
		proto_inputs.val('');
		dupe.find('.hasDatepicker').removeClass('hasDatepicker').datepicker();
		if ( dupe.parent().is( '._fbk-cf_accstack' ) )
			bindAccStack( dupe );
		return false;
	})
	// Stacks: Make [Enter] trigger change only
	.on( 'keypress', 'input', function( event ) {
		if ( event.which == 13 ) {
			$(event.target).change();
			event.preventDefault();
		}
	})
	// Course stack: Extend
	.on( 'focus', '._fbk-cf_coursestack div.stackval:last-child input', function( event ) {
		var item = $(event.target).closest('div.stackval'), i = parseInt( item.find('label').text() );
		item.clone().html(
			item.html().replace( new RegExp( '\\[' + (i-1) + '\\](?!\\[)', 'g' ), '[' + i + ']' )
		).insertAfter(item).find('label').html(i+1).siblings('input').val('').change();
	})
	// Course stack: Disable calc='add' for type==mat
	.on( 'change', '._fbk-cf_coursestack .stack-type', function( event ) {
		var	isMat = $(event.target).val() == 'mat',
			sel = $(event.target).closest('.stack-item').find('.stack-calc option[value="add"]').prop( 'disabled', isMat ).parent();
		if ( sel.val() == 'add' && isMat )
			sel.val('');
	})
	// Course stack: Recalc
	.on( 'change', '._fbk-cf_coursestack .stack-calc, input.stackval', recalc_coursestack );
	$('._fbk-cf_coursestack .stack-calc').change();
	// Stacks: Sort
	$('._fbk-cf_coursestack,._fbk-cf_accstack').sortable({
		items: '.stack-item:not(.proto-stack)',
		cursor: 'N-resize',
		handle: '.sort-stack',
		containment: 'parent',
		stop: function( event, ui ) {
			ui.item.siblings('.stack-item').not('.proto-stack').add(ui.item).each( function(i,e) {
				$(e).find('.stack-menu_order').val(i);
			});
		}
	});
});

function fbk_cf_toggleExpand( t ){
	var toggle = ! t.is('.fbk_cf_expanded'), oh = t.height(), nh;
	if ( ! toggle )
		t.find('.collapse-hide').hide();
	else
		t.find('.collapse-hide').show();

	t.toggleClass('fbk_cf_expanded',toggle);
	nh=t.height();
	t.css('height',oh);
	if ( toggle )
		t.find('.collapse-hide').hide();
	t.animate({height:nh},500,function(){
		if ( toggle )
			jQuery('.collapse-hide',this).show();
		jQuery(this).css('height','').find('.fbk_cf_mce_mark').each(function(i,e){
			if ( typeof( tinyMCE ) == 'object' && typeof( tinyMCE.execCommand ) == 'function' ) {
				e=jQuery(e);
				if(!e.is('.fbk_cf_mceInitialised')){
					e.addClass('mceEditor').addClass('fbk_cf_mceInitialised');
					tinyMCE.execCommand('mceAddControl', false, e[0].id);
				} else {
					tinyMCE.execCommand('mceToggleEditor', false, e[0].id);
				}
			}
		});
	});
}

function fbk_cf_clone(event){
	var $=jQuery, linediv=$(event.target).closest('._fbk-cf_line'), clone, expanded, index = new Date().getTime().toString(36),
	 lid = linediv.attr('id').match(/-([a-z0-9]*)$/i)[1], search = new RegExp( '([^\\[])\\[' + lid + '\\]', 'g' ), replace = '$1[' + index + ']',
	 tags_search = 'fbk_cf_line_' + lid + '__', tags_replace = 'fbk_cf_line_' + index + '__';
	if(linediv.is('.fbk_cf_expanded')){
		fbk_cf_toggleExpand( linediv );
		expanded=true;
	}

	linediv.find('.fbk_cf_mceInitialised').each(function(i,e){
		fbk_hidden_eds[e.id] = tinyMCE.get(e.id).isHidden();
		tinyMCE.execCommand('mceRemoveControl',false,e.id);
	});

	clone = linediv.clone(true).attr('id', linediv.attr('id').replace( new RegExp( lid + '$' ), index ) ).stop();

	linediv.find('.fbk_cf_mceInitialised').each(function(i,e){
		tinyMCE.execCommand('mceAddControl', false, e.id );
		if ( fbk_hidden_eds[e.id] )
			tinyMCE.execCommand('mceToggleEditor',false,e.id);
	});
	fbk_hidden_eds = {};

	clone.find('[id]').andSelf().each(function(){
		$(this).attr('id',$(this).attr('id').replace(search,replace).replace(tags_search,tags_replace));
	});
	clone.find('[for]').each(function(){
		$(this).attr('for',$(this).attr('for').replace(search,replace).replace(tags_search,tags_replace));
	});
	clone.find('[name]').each(function(){
		$(this).attr('name',$(this).attr('name').replace(search,replace));
	});
	clone.find('.fbk_cf_mceInitialised').removeClass('fbk_cf_mceInitialised');

	linediv.after(clone);
	clone.find('.hasDatepicker').removeClass('hasDatepicker').datepicker();

	if ( event.shiftKey || event.ctrlKey )
		fbk_cf_remove( false, clone );

	fbk_cf_update_line_ids( linediv.parent() );

	var oh=clone.css('height'), oc=clone.css('background-color');
	clone.css({height:0,backgroundColor:'#aaa'});
	clone.animate({height:oh,backgroundColor:oc},400,function(){
		$(this).css('height','');
		if(expanded)
			fbk_cf_toggleExpand( $(this) );
	});
}

function fbk_cf_remove(event, manualTarget){
	if ( ! manualTarget ) {
		event.preventDefault();
		if ( ! confirm('Dieses Element wirklich ' + ( event.shiftKey || event.ctrlKey ? 'leeren' : 'l\u00F6schen' ) + '?' ) )
			return false;
		var linediv = jQuery(event.target).closest('._fbk-cf_line');
	} else {
		var linediv = manualTarget;
		event = {shiftKey:true};
	}
	if( event.shiftKey || event.ctrlKey || ! linediv.siblings('._fbk-cf_line').length ){
		linediv.find('.stack-item').not('.proto-stack').remove();
		linediv.find(':input').not('.mceEditor,.menu_order,[type="button"]').each(function(i,e){
			jQuery(e).val('');
		});
		linediv.find('.fbk_cf_mceInitialised').each(function(i,e){
			if(linediv.not('.fbk_cf_expanded').is('.fbk_cf_expandable')){
				tinyMCE.execCommand('mceToggleEditor', false, e.id);
				tinyMCE.execInstanceCommand(e.id,'mceSetContent',false,'');
				tinyMCE.execCommand('mceToggleEditor', false, e.id);
			}else{
				tinyMCE.execInstanceCommand(e.id,'mceSetContent',false,'');
			}
		});
		tagBox.quickClicks(linediv);
	} else {
		linediv.animate({height:0,backgroundColor:'#aaa'},200,function(){jQuery(this).remove();});
	}
}

function fbk_cf_update_line_ids( container ){
	container.find('input.menu_order').each(function(i,e){
		e.value = i;
	});
}

bindAccStack = function($){ return function( container ) {
	if ( ! container.length || container.find('.accstack-top').length )
		return false;

	var	inner = container.find('.stack-details'),
		sourceWeeks = [],
		i;

	function to( to_week, context ) {
		if ( to_week.target ) {
			context = $(to_week.target).closest('.stack-details');
			to_week.preventDefault();
			to_week = $.data(to_week.target, 'to');
		}
		to_week = parseInt(to_week);
		if ( isNaN(to_week) || to_week < 0 )
			to_week = 0;
		if ( to_week !== context.data('week') ) {
			context.data('week', to_week);
			context.find('table input:visible').not('.noval').each( input_replace );
			repaint( context );
		}
	};
	function input_replace( event ) {
		var 	el = $(event.target || this),
			context = el.closest('.stack-details'),
			check = '[name$="[' + context.data('week') + ']"],:not([name])',
			s;
		if ( el.is(check) )
			return;
		if ( ! (s=el.hide().siblings(check)).show().length )
			s = el.clone().val('').attr('name', el.attr('name').replace( /\[\d+\]$/, '[' + context.data('week') + ']' ) )
			.insertAfter(el).removeClass('sourceval').show();
		if ( /focus/.test(event.type) )
			s.focus();
	};
	function repaint( context ) {
		$('.accstack-top input', context).val(context.data('week')+1);
		var tmp = $('.accstack-top .sourceweeks', context).empty(),
			sourceWeeks = context.data('sourceWeeks') || [];
		for ( i = 0; i < sourceWeeks.length; i++ ) {
			if ( ! isNaN(i) && sourceWeeks[i] )
				$('<a>'+ (1+i) +'</a>').appendTo(tmp).data('to',i).click(to);
		}
	};

	// Building the form framework and adding all relevant data
	inner.prepend(
		$('<div class="accstack-top" />')
		.append($('<label>Woche </label>'))
		.append($('<button class="button">&laquo;</button>').click(function(event){
			var context = $(event.target).closest('.stack-details');
			to(context.data('week')-1, context);
			event.preventDefault();
			return false;
		}))
		.append($('<input type="text">').change(function(event){
			to(event.target.value-1, $(event.target).closest('.stack-details'));
		}))
		.append($('<button class="button">&raquo;</button>').click(function(event){
			var context = $(event.target).closest('.stack-details');
			to(context.data('week')+1, context);
			event.preventDefault();
			return false;
		}))
		.append('<div class="sourceweeks" />')
	).on('change', 'table input', function( event ) {
		var	el = $(event.target),
			context = el.closest('.stack-details'),
			hasval,
			checkval = function(){if ( $(this).val() ) { hasval = !0; return false; } },
			sourceWeeks = context.data('sourceWeeks') || [];
		if ( ! el.is('[name]') ) {
			return;
		} else if ( el.val() ) {
			el.addClass('sourceval').add(el.siblings()).removeClass('noval');
			sourceWeeks[ el.attr('name').match(/\[(\d+)\]$/)[1] ] = !0;
		} else {
			el.removeClass('sourceval');
			el.siblings().each(checkval);
			if ( ! hasval )
				el.add(el.siblings()).addClass('noval');
			hasval = !1;
			el.closest('table').find('input:visible').each(checkval);
			if ( ! hasval )
				sourceWeeks[ el.attr('name').match(/\[(\d+)\]$/)[1] ] = !1;
		}
		context.data( 'sourceWeeks', sourceWeeks );
		repaint( context );
	}).on('focus', 'table input', input_replace)
	.find('input[name]').each( function() {
		var n = this.name.match(/\[(\d+)\]$/)[1];
		if ( $(this).val() ) {
			$(this).addClass('sourceval').add($(this).siblings()).removeClass('noval');
			sourceWeeks[n] = !0;
		}
	});
	inner.data( 'sourceWeeks', sourceWeeks );

	if ( sourceWeeks.length ) {
		for ( i=0; i < sourceWeeks.length; i++ )
			if ( sourceWeeks[i] ) {
				to(i, inner);
				break;
			}
	} else {
		to(0, inner);
	}
}}(jQuery);