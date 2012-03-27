/**
 * @package Studium_Ausland
 */

jQuery(function($){
	var	table = $('<table style="display:none;"><tbody class="h"><tr><th /></tr><tr /></tbody></table>').appendTo('#connect'),
		T = $('<tbody />').appendTo(table),
		D = groupData,
		L = $('<ul />').appendTo('#connect-changes'),
		changes = {},
		shareChanges = {},
		merging,
		S = {},

	setGroup = function( groupId ) {
		var i, j, trH, tr, s, t, S_var = {};
		table.hide().find('tbody.h tr:not(:first-child)').empty();
		table.find('tbody.h th').attr('colspan', 2+D.groups[groupId].schools.length);
		table.find('tbody.h:first-child').find('tr:first-child th').text( D.groups[groupId].name ).end()
		.find('tr:nth-child(2)').append('<td>vvv <b>Verbindungen von</b> vvv</td><td><b>nach</b> &gt;&gt;&gt;</td>');
		table.find('tbody.h:not(:first-child) tr:nth-child(2)').append('<td colspan="2">&nbsp;</td>');
		trH = table.find('tbody.h tr:nth-child(2)');
		T.empty();

		for ( i = 0; i < D.groups[groupId].schools.length; i++ ) {
			s = D.schools[ D.groups[groupId].schools[i] ];

			trH.append('<th class="c-' + s.cat + '">' + D.groups[groupId].schools[i] + '</th>');
			tr = $(
			 '<tr><td class="c-' + s.cat + '"><a href="' + D.baseUrl + D.groups[groupId].schools[i] + '"><b>'
			 + s.name + '</b> (' + s.lang + ', ' + s.loc + ')'
			 + '</a></td><th class="c-' + s.cat + '">' + D.groups[groupId].schools[i] + '</th></tr>'
			);
			for ( j = 0; j < D.groups[groupId].schools.length; j++ ) {
				$( '<td class="' + getClass( D.groups[groupId].schools[i], D.groups[groupId].schools[j] ) + '" />' )
				.data({
					from: D.groups[groupId].schools[i],
					to: D.groups[groupId].schools[j]
				}).appendTo(tr);
			}
			tr.appendTo(T);

			for ( t in S ) {
				if ( ! ( t in S_var ) )
					S_var[t] = {};
				for ( j in s[t] ) {
					if ( ! ( j in S_var[t] ) )
						S_var[t][j] = {};
					S_var[t][j][ D.groups[groupId].schools[i] ] = 1;
				}
			}
		}

		for ( t in S ) {
			S[t].empty();
			for ( j in S_var[t] ) {
				tr = $( '<tr><td colspan="2">' + D.shared[t][j].name + '</td></tr>' );
				for ( i = 0; i < D.groups[groupId].schools.length; i++ ) {
					$( '<td class="' + getShareClass( t, D.groups[groupId].schools[i], j ) + '">&nbsp;</td>' )
					.data({
						obj: j,
						school: D.groups[groupId].schools[i]
					}).appendTo(tr);
				}
				tr.appendTo( S[t] );
			}
		}
		table.show();
	},

	getClass = function( from, to ) {
		if ( from == to )
			return 'mirrored';
		if ( to in D.schools[from].links ) {
			if ( (from in changes) && (to in changes[from]) )
				return 'unlinking';
			else
				return 'linked';
		} else {
			if ( (from in changes) && (to in changes[from]) )
				return 'linking';
			else
				return 'unlinked';
		}
	},

	getShareClass = function( t, school, obj ) {
		if ( obj in D.schools[school][t] ) {
			if ( (t in shareChanges) && (school in shareChanges[t]) && (obj in shareChanges[t][school]) )
				return 'unlinking';
			else
				return 'linked';
		} else {
			if ( (t in shareChanges) && (school in shareChanges[t]) && (obj in shareChanges[t][school]) )
				return 'linking';
			else
				return 'unlinked';
		}
	};

	for ( var t in D.shared ) {
		table.append('<tbody class="h"><tr><th>' + D.shared[t].label + '</th></tr><tr /></tbody>');
		S[t] = $('<tbody class="shared" />').data( 'type', t ).appendTo(table);
	}

	$('#groups').on( 'click', 'tbody tr', function(){
		setGroup( $(this).data('group') );
	}).on( 'click', 'td.merge', function(){
		var g = $(this).parent().data('group');
		if ( merging ) {
			$('td.merge').html('Verbinden mit...');
			if ( g !== merging ) {
				$(this).parent().add('#groups tr[data-group="' + merging + '"]').addClass('missing');
				D.groups[g].name = D.groups[merging].name += ' &amp; ' + D.groups[g].name;
				D.groups[g].schools = $.merge( D.groups[merging].schools, D.groups[g].schools );
			}
			merging = false;
			
		} else {
			$(this).html('Abbrechen');
			$('td.merge').not(this).html('... dieser Gruppe');
			merging = g;
		}
		return false;
	});

	table.on( 'click', 'td', function() {
		var d = $(this).data(), x, tbody = $(this).closest('tbody');
		if ( tbody.is('.h') ) {
			return;
		} else if ( tbody.is(T) ) { // Main table: school<->school links
			if ( ! ( d.from && d.to ) || d.from == d.to )
				return;
			if ( (d.from in changes) && (d.to in changes[d.from]) ) {
				delete changes[d.from][d.to];
				$(this).attr( 'class', getClass( d.from, d.to ) );
				L.children('#' + d.from + '-' + d.to).remove();
			} else {
				x = ! (d.to in D.schools[d.from].links);
				( changes[d.from] || (changes[d.from] = {}) )[d.to] = x;
				$(this).addClass( x ? 'linking' : 'unlinking' );
				L.append(
				 '<li id="' + d.from + '-' + d.to + '">'
				 + ( x ? 'Verbinde: ' : 'Entferne Verbindung: ' ) + d.from + ' -&gt; ' + d.to
				 + '<input type="hidden" name="changes[' + d.from + '][' + d.to + ']" value="' + (x ? 1 : 0) + '">'
				 + '</li>'
				);
			}
		} else { // share tables: school <-> courses/accs
			var t = tbody.data('type'),
			doConfirm = function(){
				var i, stillLinked = false;
				for ( i = 0; i < D.shared[t][d.obj].shared.length; i++ ) {
					if ( D.shared[t][d.obj].shared[i] == d.school )
						continue;
					if ( ! ( D.shared[t][d.obj].shared[i] in D.schools )
					 || ! ( D.shared[t][d.obj].shared[i] in shareChanges[t] )
					 || ! ( d.obj in shareChanges[t][ D.shared[t][d.obj].shared[i] ] )
					 || shareChanges[t][ D.shared[t][d.obj].shared[i] ][d.obj]
					) {
						stillLinked = true;
						break;
					}
				}
				for ( i in shareChanges[t] ) {
					if ( i == d.school )
						continue;
					if ( d.obj in shareChanges[t][i] && shareChanges[t][i][d.obj] ) {
						stillLinked = true;
						break;
					}
				}
				return stillLinked || confirm('Diese Beziehung wirklich l\u00f6schen? Das gew\u00e4hlte Objekt geht dabei unwiderruflich verloren.');
			};
			if ( ! t || ! d.obj || ! d.school )
				return;
			if ( (t in shareChanges) && (d.school in shareChanges[t]) && (d.obj in shareChanges[t][d.school]) ) {
				if ( shareChanges[t][d.school][d.obj] && ! doConfirm() )
					return;
				delete shareChanges[t][d.school][d.obj];
				$(this).attr( 'class', getShareClass( t, d.school, d.obj ) );
				L.children('#' + t + '-' + d.school + '-' + d.obj).remove();
			} else {
				x = ! (d.obj in D.schools[d.school][t]);
				if ( ! ( t in shareChanges ) )
					shareChanges[t] = {};
				if ( ! x && ! doConfirm() )
					return;
				( shareChanges[t][d.school] || (shareChanges[t][d.school] = {}) )[d.obj] = x;
				$(this).addClass( x ? 'linking' : 'unlinking' );
				L.append(
				 '<li id="' + t + '-' + d.school + '-' + d.obj + '"><i>'
				 + D.shared[t][d.obj].name + '</i>' + ( x ? ' verbinden mit ' : ' entfernen von ' ) + d.school
				 + '<input type="hidden" name="shareChanges[' + t + '][' + d.obj + '][' + d.school + ']" value="' + (x ? 1 : 0) + '">'
				 // YES, that's intentionally [t][obj][school] and not the other way around. Easier to process in the backend!
				);
			}
		}
	});
});