/* global ysoData, wp */
( function ( $ ) {
	'use strict';

	// ── Enable/disable override toggle ──────────────────────────────────────

	var $enableToggle  = $( '#yso_override_enabled' );
	var $overridePanel = $( '.yso-override-panel' );

	$enableToggle.on( 'change', function () {
		if ( $( this ).is( ':checked' ) ) {
			$overridePanel.slideDown( 200 );
		} else {
			$overridePanel.slideUp( 200 );
		}
	} );

	// ── Simple / Advanced mode radio switch ─────────────────────────────────

	$( 'input[name="yso_mode"]' ).on( 'change', function () {
		var mode = $( this ).val();
		if ( 'simple' === mode ) {
			$( '.yso-mode-simple' ).slideDown( 200 );
			$( '.yso-mode-advanced' ).slideUp( 200 );
		} else {
			$( '.yso-mode-advanced' ).slideDown( 200 );
			$( '.yso-mode-simple' ).slideUp( 200 );
		}
	} );

	// ── Media picker ────────────────────────────────────────────────────────

	var mediaFrame = null;

	$( document ).on( 'click', '.yso-media-picker', function ( e ) {
		e.preventDefault();

		var $button  = $( this );
		var targetId = $button.data( 'target' );
		var $input   = $( '#' + targetId );
		var $preview = $button.siblings( '.yso-image-preview' );

		// If the media frame already exists, reopen it.
		if ( mediaFrame ) {
			mediaFrame.open();
			return;
		}

		mediaFrame = wp.media( {
			title:    ysoData.mediaTitle,
			button:   { text: ysoData.mediaButton },
			library:  { type: 'image' },
			multiple: false,
		} );

		mediaFrame.on( 'select', function () {
			var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
			var url        = attachment.url;

			$input.val( url );

			if ( $preview.length ) {
				$preview.attr( 'src', url ).show();
			}
		} );

		// Reset frame reference when closed so a new one can be opened for a
		// different picker button.
		mediaFrame.on( 'close', function () {
			mediaFrame = null;
		} );

		mediaFrame.open();
	} );

	// ── Live JSON validation feedback ───────────────────────────────────────

	var $jsonTextarea = $( '#yso_advanced_json' );

	if ( $jsonTextarea.length ) {
		// Ensure the error notice element exists.
		if ( ! $jsonTextarea.next( '.yso-json-error-notice' ).length ) {
			$jsonTextarea.after(
				'<p class="yso-json-error-notice">' +
					'Invalid JSON — please check your syntax before saving.' +
				'</p>'
			);
		}

		$jsonTextarea.on( 'blur', function () {
			var val     = $.trim( $( this ).val() );
			var $notice = $( this ).next( '.yso-json-error-notice' );

			if ( val === '' ) {
				$notice.hide();
				return;
			}

			try {
				JSON.parse( val );
				$notice.hide();
				$jsonTextarea.css( 'border-color', '' );
			} catch ( err ) {
				$notice.show();
				$jsonTextarea.css( 'border-color', '#d63638' );
			}
		} );
	}

} )( jQuery );
