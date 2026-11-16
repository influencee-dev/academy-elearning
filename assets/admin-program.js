/**
 * Editor "Programma" del corso e materiali della lezione (solo amministrazione).
 */
( function ( $ ) {
	'use strict';

	var cfg = window.aelProgram || { i18n: {} };
	var t = cfg.i18n;

	/* ---------------------------------------------------------------- */
	/* Programma                                                         */
	/* ---------------------------------------------------------------- */

	function initProgram( $root ) {
		var courseId = $root.data( 'course' );
		var $status = $root.find( '.ael-status' );

		function say( message, isError ) {
			$status.text( message ).toggleClass( 'ael-error', !! isError );
		}

		function request( action, data ) {
			return $.post(
				cfg.ajaxUrl,
				$.extend( { action: action, nonce: cfg.nonce, course: courseId }, data )
			);
		}

		function failure( xhr ) {
			var msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
			say( msg || t.error, true );
		}

		function refreshEmpty() {
			$root.find( '.ael-lessons' ).each( function () {
				$( this ).toggleClass( 'is-empty', 0 === $( this ).children( '.ael-lesson' ).length )
					.attr( 'data-empty', t.noLessons );
			} );
		}

		function currentOrder() {
			var order = [];
			$root.find( '.ael-modules > .ael-module' ).each( function () {
				var lessons = [];
				$( this ).find( '.ael-lessons > .ael-lesson' ).each( function () {
					lessons.push( $( this ).data( 'id' ) );
				} );
				order.push( { id: $( this ).data( 'id' ), lessons: lessons } );
			} );
			return order;
		}

		function saveOrder() {
			refreshEmpty();
			say( t.saving, false );
			request( 'ael_program_order', { order: JSON.stringify( currentOrder() ) } )
				.done( function () {
					say( t.saved, false );
				} )
				.fail( failure );
		}

		function initLessonList( $list ) {
			$list.sortable( {
				items: '> .ael-lesson',
				handle: '.ael-handle',
				connectWith: '.ael-lessons',
				placeholder: 'ael-placeholder',
				tolerance: 'pointer',
				update: function ( event, ui ) {
					// "update" scatta una volta per lista toccata: salva solo da quella che contiene l'elemento.
					if ( ui.item.parent()[ 0 ] === this ) {
						saveOrder();
					}
				}
			} );
		}

		$root.find( '.ael-modules' ).sortable( {
			items: '> .ael-module',
			handle: '.ael-module-handle',
			placeholder: 'ael-placeholder',
			tolerance: 'pointer',
			update: saveOrder
		} );
		$root.find( '.ael-lessons' ).each( function () {
			initLessonList( $( this ) );
		} );
		refreshEmpty();

		function add( $box, kind, parent ) {
			var $input = $box.find( 'input[type="text"]' );
			var title = $.trim( $input.val() );
			if ( '' === title ) {
				say( t.needTitle, true );
				$input.trigger( 'focus' );
				return;
			}
			$box.find( 'button' ).prop( 'disabled', true );
			say( t.saving, false );
			request( 'ael_program_add', { kind: kind, title: title, parent: parent } )
				.done( function ( response ) {
					var $item = $( response.data.html );
					if ( 'module' === kind ) {
						$root.find( '.ael-modules' ).append( $item );
						initLessonList( $item.find( '.ael-lessons' ) );
						$root.find( '.ael-modules' ).sortable( 'refresh' );
					} else {
						$box.closest( '.ael-module' ).find( '.ael-lessons' ).append( $item );
					}
					$input.val( '' ).trigger( 'focus' );
					refreshEmpty();
					say( '', false );
				} )
				.fail( failure )
				.always( function () {
					$box.find( 'button' ).prop( 'disabled', false );
				} );
		}

		// Aggiunta con il pulsante o con Invio (Invio non deve salvare l'intero corso).
		$root.on( 'click', '.ael-add button', function () {
			var $box = $( this ).closest( '.ael-add' );
			var kind = $( this ).data( 'kind' );
			add( $box, kind, 'lesson' === kind ? $box.closest( '.ael-module' ).data( 'id' ) : 0 );
		} );
		$root.on( 'keydown', '.ael-add input', function ( event ) {
			if ( 13 === event.which ) {
				event.preventDefault();
				$( this ).closest( '.ael-add' ).find( 'button' ).trigger( 'click' );
			}
		} );

		$root.on( 'click', '.ael-delete', function () {
			var $item = $( this ).closest( 'li' );
			var isModule = $item.hasClass( 'ael-module' );
			if ( ! window.confirm( isModule ? t.confirmModule : t.confirmLesson ) ) {
				return;
			}
			say( t.saving, false );
			request( 'ael_program_delete', { id: $item.data( 'id' ) } )
				.done( function () {
					$item.remove();
					refreshEmpty();
					say( '', false );
				} )
				.fail( failure );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Materiali della lezione                                           */
	/* ---------------------------------------------------------------- */

	function initMaterials( $root ) {
		var $field = $root.find( 'input[name="ael_material_ids"]' );
		var $list = $root.find( '.ael-material-list' );
		var frame = null;

		function sync() {
			var ids = [];
			$list.children( 'li' ).each( function () {
				ids.push( $( this ).data( 'id' ) );
			} );
			$field.val( ids.join( ',' ) );
		}

		function addItem( id, name ) {
			if ( $list.children( 'li[data-id="' + id + '"]' ).length ) {
				return;
			}
			var $li = $( '<li>' ).attr( 'data-id', id ).data( 'id', id );
			$li.append( '<span class="dashicons dashicons-media-default"></span>' );
			$li.append( $( '<span class="ael-material-name">' ).text( name ) );
			$li.append( $( '<button type="button" class="button-link ael-material-remove">' ).text( t.remove ) );
			$list.append( $li );
		}

		$root.on( 'click', '.ael-material-remove', function () {
			$( this ).closest( 'li' ).remove();
			sync();
		} );

		$root.on( 'click', '.ael-material-add', function () {
			if ( ! frame ) {
				frame = wp.media( {
					title: t.pickTitle,
					button: { text: t.pickButton },
					multiple: true
				} );
				frame.on( 'select', function () {
					frame.state().get( 'selection' ).each( function ( attachment ) {
						var data = attachment.toJSON();
						addItem( data.id, data.filename || data.title );
					} );
					sync();
				} );
			}
			frame.open();
		} );
	}

	$( function () {
		$( '.ael-program' ).each( function () {
			initProgram( $( this ) );
		} );
		$( '.ael-materials' ).each( function () {
			initMaterials( $( this ) );
		} );
	} );
}( jQuery ) );
