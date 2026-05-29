/**
 * WC Fraud Blocker — Admin JS
 *
 * Handles add / remove AJAX requests.
 * All DOM manipulation is done here; no page reloads needed.
 */
/* global wcfb, jQuery */

( function ( $ ) {
    'use strict';

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    function showNotice( message, type ) {
        var $notice = $( '#wcfb-notice' );
        $notice
            .removeClass( 'wcfb-notice--success wcfb-notice--error' )
            .addClass( 'wcfb-notice--' + type )
            .text( ( type === 'success' ? '✓ ' : '✗ ' ) + message )
            .removeAttr( 'hidden' );

        clearTimeout( $notice.data( 'timer' ) );
        $notice.data( 'timer', setTimeout( function () {
            $notice.attr( 'hidden', true );
        }, 4000 ) );
    }

    function updateBadge( type, delta ) {
        var $badge = $( '#wcfb-count-' + type );
        var count  = parseInt( $badge.text(), 10 ) + delta;
        $badge.text( count );
        $badge.toggleClass( 'wcfb-badge--zero', count === 0 );
    }

    function buildListItem( value, type ) {
        return $( '<li>' )
            .addClass( 'wcfb-list__item wcfb-list__item--new' )
            .attr( { 'data-value': value, 'data-type': type } )
            .append(
                $( '<span>' ).addClass( 'wcfb-list__value' ).text( value )
            )
            .append(
                $( '<button>' )
                    .addClass( 'wcfb-btn wcfb-btn--remove' )
                    .attr( {
                        'data-value': value,
                        'data-type':  type,
                        title:        wcfb.i18n.confirm_remove,
                    } )
                    .text( '✕' )
            );
    }

    // ------------------------------------------------------------------ //
    //  Add
    // ------------------------------------------------------------------ //

    $( document ).on( 'click', '.wcfb-btn--add', function () {
        var $btn  = $( this );
        var type  = $btn.data( 'type' );
        var $input = $( '#wcfb-input-' + type );
        var value  = $input.val().trim();

        if ( ! value ) {
            showNotice( wcfb.i18n.empty_field, 'error' );
            $input.focus();
            return;
        }

        $btn.prop( 'disabled', true ).text( wcfb.i18n.adding );

        $.post( wcfb.ajax_url, {
            action: 'wcfb_add',
            nonce:  wcfb.nonce,
            type:   type,
            value:  value,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                // Remove "empty" placeholder if present
                $( '#wcfb-empty-' + type ).remove();

                var $list = $( '#wcfb-list-' + type );
                $list.prepend( buildListItem( res.data.value, type ) );
                updateBadge( type, 1 );
                $input.val( '' );
                showNotice( res.data.message, 'success' );
            } else {
                showNotice( res.data.message, 'error' );
            }
        } )
        .fail( function () {
            showNotice( 'Request failed. Please try again.', 'error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false ).text( type === 'email' ? '+ Block Email' : '+ Block Address' );
        } );
    } );

    // Allow Enter key to submit
    $( document ).on( 'keydown', '.wcfb-input', function ( e ) {
        if ( e.key === 'Enter' ) {
            $( this ).closest( '.wcfb-card' ).find( '.wcfb-btn--add' ).trigger( 'click' );
        }
    } );

    // ------------------------------------------------------------------ //
    //  Remove
    // ------------------------------------------------------------------ //

    $( document ).on( 'click', '.wcfb-btn--remove', function () {
        var $btn  = $( this );
        var type  = $btn.data( 'type' );
        var value = $btn.data( 'value' );

        if ( ! window.confirm( wcfb.i18n.confirm_remove ) ) {
            return;
        }

        $btn.prop( 'disabled', true ).text( '…' );

        $.post( wcfb.ajax_url, {
            action: 'wcfb_remove',
            nonce:  wcfb.nonce,
            type:   type,
            value:  value,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                var $item = $btn.closest( '.wcfb-list__item' );
                $item.addClass( 'wcfb-list__item--removing' );

                setTimeout( function () {
                    $item.remove();
                    updateBadge( type, -1 );

                    // Show empty state if list is now empty
                    var $list = $( '#wcfb-list-' + type );
                    if ( $list.children( '.wcfb-list__item' ).length === 0 ) {
                        $list.append(
                            $( '<li>' )
                                .addClass( 'wcfb-list__empty' )
                                .attr( 'id', 'wcfb-empty-' + type )
                                .text( type === 'email' ? 'No blocked emails yet.' : 'No blocked addresses yet.' )
                        );
                    }
                }, 220 );

                showNotice( res.data.message, 'success' );
            } else {
                showNotice( res.data.message, 'error' );
                $btn.prop( 'disabled', false ).text( '✕' );
            }
        } )
        .fail( function () {
            showNotice( 'Request failed. Please try again.', 'error' );
            $btn.prop( 'disabled', false ).text( '✕' );
        } );
    } );

}( jQuery ) );
