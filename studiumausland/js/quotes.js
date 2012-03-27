/**
 * @package Studium_Ausland
 */

(function($){

$('<div class="quote-pick"><input type="button" value="Zur Anmeldung &raquo;" class="floatright"></div>').appendTo('section.quote');
$('#content').on('click', '.quote-pick input[type="button"]', function() {
	$(this).closest('.quote-pick').html( '<form><table><tbody>'
		+ '<tr><th colspan="2" scope="rowgroup">Mit den folgenden Angaben erleichtern Sie uns die Arbeit:</th></tr>'
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
		+ '</tbody><tfoot><tr><td colspan="2">'
		+ 'Beachten Sie, dass dies eine unverbindliche Anfrage ist. Sobald wir Ihre Anfrage erhalten haben, werden wir uns bei Ihnen'
		+ ' melden, um allf&auml;llige Fragen zu kl&auml;ren; anschlie&szlig;end schicken wir Ihnen das verbindliche Formular'
		+ ' zu, mit dem Sie Ihre Anmeldung best&auml;tigen.</td></tr>'
		+ '<tr><td colspan="2"><input type="submit" value="Absenden" class="floatright"></td></tr></tfoot></table></form>'
	).find('form').submit( function() {
		var form = $(this);
		$.ajax({
			method: 'POST',
			data: 'action=fbk_quote_push&quote=' + form.closest('section.quote').attr('id').match(/quote-(.*)/)[1]
				+ window.location.search.replace(/\?/, '&') + '&' + form.serialize(),
			complete: function( jqXHR, textStatus ) {
				if ( 'success' == textStatus ) {
					form.parent().html( jqXHR.responseText );
				} else {
					form.prepend('<p class="ybox">Beim Versenden des Formulars ist ein Fehler aufgetreten. '
					+ 'Sollte sich dies wiederholen, bitte melden Sie uns den Vorfall.</p>')
					.find(':input').prop('disabled',false).filter('[type="submit"]').val('Erneut senden');
				}
			}
		});
		form.find(':input').prop('disabled',true).filter('[type="submit"]').val('Wird gesendet...');
		return false;
	}).find('input[name="birthdate"]').datepicker();
});

})(jQuery);