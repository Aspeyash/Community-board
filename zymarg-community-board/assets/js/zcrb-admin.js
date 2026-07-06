/* global ZCRBHub, jQuery */
/**
 * ZYMARG Community Board — admin SPA behaviour.
 *
 * Two responsibilities:
 *
 * 1) Client-side view switching. All three views (Dashboard, All Requests,
 *    Settings) are rendered server-side in one page load. Clicking a nav
 *    button just toggles `.is-active` on the matching `.zcrb-view` — NO
 *    AJAX fetch, NO page reload. URL is updated with pushState so refresh
 *    / bookmark / back-forward all work.
 *
 * 2) AJAX save for the Settings form (unchanged nonce + endpoint from
 *    v2.1.x; kept as-is so existing installs upgrade cleanly).
 *
 * Also filters the All Requests list live (keyword + status), which needs
 * no server round-trip because the whole page is already in the DOM.
 */
( function ( $ ) {
    'use strict';

    // ------------------------------------------------------------------
    // Utility: parse URL, resolve section, push state.
    // ------------------------------------------------------------------
    var VALID_VIEWS = [ 'dashboard', 'requests', 'settings' ];

    function currentSection() {
        try {
            var params = new URLSearchParams( window.location.search );
            var s = params.get( 'section' );
            if ( s && VALID_VIEWS.indexOf( s ) !== -1 ) {
                return s;
            }
            // Settings slug maps to "settings" view even when no ?section=.
            var page = params.get( 'page' );
            if ( page === 'zcrb-settings' ) {
                return 'settings';
            }
        } catch ( e ) {
            /* URLSearchParams unavailable — fall through. */
        }
        return 'dashboard';
    }

    function buildUrl( view ) {
        var base = ( ZCRBHub && ZCRBHub.hubUrl ) ? ZCRBHub.hubUrl : ( window.location.pathname + '?page=zcrb-hub' );
        var sep  = base.indexOf( '?' ) === -1 ? '?' : '&';
        return base + sep + 'section=' + encodeURIComponent( view );
    }

    // ------------------------------------------------------------------
    // View switcher — the core SPA behaviour.
    // ------------------------------------------------------------------
    function initSpaNav() {
        var $app = $( '#zcrb-app' );
        if ( ! $app.length ) {
            return;
        }

        var $navItems = $app.find( '.zcrb-nav-item' );
        var $views    = $app.find( '.zcrb-view' );
        var $title    = $( '#zcrb-view-title' );

        function activate( view, pushState ) {
            if ( VALID_VIEWS.indexOf( view ) === -1 ) {
                view = 'dashboard';
            }
            var $target = $views.filter( '[data-view="' + view + '"]' );
            if ( ! $target.length ) {
                // Requested view isn't rendered (e.g. Settings hidden for non-admins).
                return;
            }

            $navItems
                .removeClass( 'is-active' )
                .attr( 'aria-selected', 'false' )
                .filter( '[data-view="' + view + '"]' )
                .addClass( 'is-active' )
                .attr( 'aria-selected', 'true' );

            $views.removeClass( 'is-active' );
            $target.addClass( 'is-active' );

            // Update the topbar title to match the nav label.
            var $activeBtn = $navItems.filter( '.is-active' );
            if ( $title.length && $activeBtn.length ) {
                var lbl = $activeBtn.find( '.zcrb-nav-label' ).text();
                if ( lbl ) {
                    $title.text( lbl );
                }
            }

            // Update the URL so refresh / bookmark / share all preserve the view.
            if ( pushState !== false && window.history && window.history.pushState ) {
                try {
                    window.history.pushState( { zcrbView: view }, '', buildUrl( view ) );
                } catch ( e ) {
                    /* Ignore SecurityError on file:// or sandboxed contexts. */
                }
            }

            // Broadcast so per-view listeners can lazily rebind if they wish.
            $( document ).trigger( 'zcrb-hub:view-shown', [ view ] );
        }

        // Wire up nav clicks.
        $navItems.on( 'click', function ( e ) {
            e.preventDefault();
            activate( $( this ).data( 'view' ), true );
        } );

        // Browser back / forward.
        window.addEventListener( 'popstate', function ( e ) {
            var view = ( e.state && e.state.zcrbView ) || currentSection();
            activate( view, false );
        } );

        // If the URL says one thing but the server marked another view active
        // (e.g. old bookmark with ?section=requests hitting a cached template),
        // sync to the URL. Otherwise trust the server's initial render.
        var wantView   = currentSection();
        var serverView = $app.attr( 'data-initial-view' ) || 'dashboard';
        if ( wantView && wantView !== serverView ) {
            activate( wantView, false );
        }
    }

    // ------------------------------------------------------------------
    // Settings form AJAX save.
    // ------------------------------------------------------------------
    function initSettingsForm() {
        var $form = $( '.zcrb-settings form[action="options.php"]' );
        if ( ! $form.length || $form.data( 'zcrb-init' ) ) {
            return;
        }
        $form.data( 'zcrb-init', true );

        var $submit = $form.find( 'input[type="submit"], button[type="submit"]' );
        var originalLabel = $submit.val() || $submit.text();
        var $toast = $form.find( '[data-zcrb-toast]' );
        if ( ! $toast.length ) {
            $toast = $( '<span class="zcrb-settings-toast" data-zcrb-toast></span>' );
            $submit.after( $toast );
        }

        function showToast( message, kind ) {
            $toast.removeClass( 'is-success is-error is-visible' );
            $toast.text( message );
            $toast.addClass( 'is-visible ' + ( kind === 'success' ? 'is-success' : 'is-error' ) );
            setTimeout( function () {
                $toast.removeClass( 'is-visible' );
            }, 3000 );
        }

        $form.on( 'submit', function ( e ) {
            e.preventDefault();

            if ( ! ZCRBHub || ! ZCRBHub.ajaxUrl || ! ZCRBHub.settingsNonce ) {
                // No config — fall back to classic options.php submit.
                $form.get( 0 ).submit();
                return;
            }

            var formData = $form.serialize();

            $submit.prop( 'disabled', true );
            if ( $submit.is( 'input' ) ) {
                $submit.val( ZCRBHub.i18n.saving );
            } else {
                $submit.text( ZCRBHub.i18n.saving );
            }

            $.ajax( {
                url:      ZCRBHub.ajaxUrl,
                method:   'POST',
                dataType: 'json',
                data: {
                    action:   'zcrb_save_settings',
                    nonce:    ZCRBHub.settingsNonce,
                    settings: formData
                }
            } ).done( function ( response ) {
                if ( response && response.success ) {
                    showToast( ( response.data && response.data.message ) || ZCRBHub.i18n.saved, 'success' );
                } else {
                    var msg = ( response && response.data && response.data.message ) || ZCRBHub.i18n.saveError;
                    showToast( msg, 'error' );
                }
            } ).fail( function () {
                showToast( ZCRBHub.i18n.saveError, 'error' );
            } ).always( function () {
                $submit.prop( 'disabled', false );
                if ( $submit.is( 'input' ) ) {
                    $submit.val( originalLabel );
                } else {
                    $submit.text( originalLabel );
                }
            } );
        } );
    }

    // ------------------------------------------------------------------
    // All Requests view — live keyword + status filter (no server hit).
    // ------------------------------------------------------------------
    function initRequestsFilter() {
        var $view = $( '.zcrb-view[data-view="requests"]' );
        if ( ! $view.length ) {
            return;
        }
        var $search  = $view.find( '[data-zcrb-requests-search]' );
        var $status  = $view.find( '[data-zcrb-requests-status]' );
        var $rows    = $view.find( '.zcrb-request' );

        function apply() {
            var q = ( $search.val() || '' ).toLowerCase().trim();
            var s = $status.val() || '';
            $rows.each( function () {
                var $row = $( this );
                var matchQ = ! q || ( $row.attr( 'data-search' ) || '' ).indexOf( q ) !== -1;
                var matchS = ! s || ( $row.attr( 'data-status' ) === s );
                $row.toggleClass( 'is-hidden', ! ( matchQ && matchS ) );
            } );
        }

        $search.on( 'input', apply );
        $status.on( 'change', apply );
    }

    // ------------------------------------------------------------------
    // Boot.
    // ------------------------------------------------------------------
    $( function () {
        initSpaNav();
        initSettingsForm();
        initRequestsFilter();
    } );

} )( jQuery );
