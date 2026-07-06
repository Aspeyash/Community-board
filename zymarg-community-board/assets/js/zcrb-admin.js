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
            if ( page === 'zcrb-hub-requests' ) {
                return 'requests';
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
    // All Requests view — live keyword filter, sortable columns,
    // select-all, bulk-action guard, per-page change.
    //
    // Status filter is now driven server-side by ?zcrb_status={tab} so
    // pagination and counts stay accurate — this JS only handles the
    // in-page keyword search + sort + selection interactions.
    // ------------------------------------------------------------------
    function initRequestsFilter() {
        var $view = $( '.zcrb-view[data-view="requests"]' );
        if ( ! $view.length ) {
            return;
        }

        var $search   = $view.find( '[data-zcrb-requests-search]' );
        var $tbody    = $view.find( '[data-zcrb-tbody]' );
        var $cards    = $view.find( '[data-zcrb-cards]' );
        var $emptyMsg = $view.find( '[data-zcrb-empty-message]' );
        var $emptyMsgCards = $view.find( '[data-zcrb-empty-message-cards]' );

        // Combine table rows + mobile card rows so keyword + sort keep the
        // two views in lock-step. Each element carries the same data-* attrs.
        function allRowGroups() {
            return [
                { $container: $tbody, $rows: $tbody.children( 'tr' ) },
                { $container: $cards, $rows: $cards.children( '.zcrb-request' ) }
            ];
        }

        // -------- Live keyword search (client-side) ----------------
        function applyKeyword() {
            var q = ( $search.val() || '' ).toLowerCase().trim();
            var visibleTable = 0;
            var visibleCards = 0;

            allRowGroups().forEach( function ( group ) {
                group.$rows.each( function () {
                    var $row  = $( this );
                    var hay   = ( $row.attr( 'data-search' ) || '' );
                    var match = ! q || hay.indexOf( q ) !== -1;
                    $row.toggleClass( 'is-hidden', ! match );
                    if ( match ) {
                        if ( group.$container.is( 'tbody' ) ) {
                            visibleTable++;
                        } else {
                            visibleCards++;
                        }
                    }
                } );
            } );

            // Show / hide the "no matching requests" empty state per view.
            if ( $emptyMsg.length ) {
                $emptyMsg.toggle( q !== '' && visibleTable === 0 );
            }
            if ( $emptyMsgCards.length ) {
                $emptyMsgCards.toggle( q !== '' && visibleCards === 0 );
            }
        }

        if ( $search.length ) {
            $search.on( 'input', applyKeyword );
        }

        // -------- Sortable table columns (click header) ------------
        var currentSort = { key: null, dir: 'asc' };

        function sortRows( key, dir ) {
            allRowGroups().forEach( function ( group ) {
                var rows = group.$rows.get();
                rows.sort( function ( a, b ) {
                    var av = $( a ).attr( 'data-sort-' + key ) || '';
                    var bv = $( b ).attr( 'data-sort-' + key ) || '';

                    // Numeric compare when both look numeric.
                    var an = parseFloat( av );
                    var bn = parseFloat( bv );
                    var isNum = ! isNaN( an ) && ! isNaN( bn ) && av !== '' && bv !== '';

                    var cmp;
                    if ( isNum ) {
                        cmp = an - bn;
                    } else {
                        cmp = av.localeCompare( bv );
                    }
                    return dir === 'asc' ? cmp : -cmp;
                } );
                // Re-append rows in sorted order.
                rows.forEach( function ( r ) { group.$container.append( r ); } );
            } );
        }

        $view.on( 'click', '.zcrb-sortable .zcrb-sort-btn', function ( e ) {
            e.preventDefault();
            var $th  = $( this ).closest( '.zcrb-sortable' );
            var key  = $th.data( 'sort-key' );
            if ( ! key ) {
                return;
            }

            // Toggle direction if same column, otherwise start ascending.
            var dir = ( currentSort.key === key && currentSort.dir === 'asc' ) ? 'desc' : 'asc';
            currentSort = { key: key, dir: dir };

            // Update visual indicator on all headers.
            $view.find( '.zcrb-sortable' )
                .removeClass( 'is-sorted-asc is-sorted-desc' );
            $th.addClass( dir === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc' );

            sortRows( key, dir );
        } );

        // -------- Select-all master checkbox -----------------------
        $view.on( 'change', '[data-zcrb-check-all]', function () {
            var checked = this.checked;
            var scope   = $( this ).data( 'zcrb-check-all' );

            if ( 'table' === scope ) {
                $tbody.find( '.zcrb-row-check' ).each( function () {
                    if ( ! $( this ).closest( 'tr' ).hasClass( 'is-hidden' ) ) {
                        this.checked = checked;
                    }
                } );
            } else {
                $view.find( '.zcrb-row-check' ).each( function () {
                    if ( ! $( this ).closest( '.zcrb-row, .zcrb-request' ).hasClass( 'is-hidden' ) ) {
                        this.checked = checked;
                    }
                } );
            }

            // Keep table + card checkboxes in sync (same post_id).
            syncCheckboxesByPostId();
        } );

        // Individual row checkbox → sync its twin in the other view.
        $view.on( 'change', '.zcrb-row-check', function () {
            var postId  = $( this ).data( 'post-id' );
            var checked = this.checked;
            if ( typeof postId === 'undefined' ) {
                return;
            }
            $view.find( '.zcrb-row-check[data-post-id="' + postId + '"]' ).each( function () {
                if ( this !== null ) {
                    this.checked = checked;
                }
            } );
        } );

        function syncCheckboxesByPostId() {
            var checkedIds = {};
            $view.find( '.zcrb-row-check' ).each( function () {
                if ( this.checked ) {
                    checkedIds[ $( this ).data( 'post-id' ) ] = true;
                }
            } );
            $view.find( '.zcrb-row-check' ).each( function () {
                this.checked = !! checkedIds[ $( this ).data( 'post-id' ) ];
            } );
        }

        // -------- Bulk action form guard --------------------------
        $view.on( 'submit', '[data-zcrb-bulk-form]', function ( e ) {
            var $form  = $( this );
            var action = $form.find( 'select[name="zcrb_bulk_action"]' ).val();
            var checked = $form.find( '.zcrb-row-check:checked' );

            if ( ! action ) {
                e.preventDefault();
                window.alert( ( window.wp && window.wp.i18n && window.wp.i18n.__ )
                    ? window.wp.i18n.__( 'Please choose a bulk action.', 'zymarg-community-board' )
                    : 'Please choose a bulk action.' );
                return false;
            }
            if ( 0 === checked.length ) {
                e.preventDefault();
                window.alert( ( window.wp && window.wp.i18n && window.wp.i18n.__ )
                    ? window.wp.i18n.__( 'Please select at least one request.', 'zymarg-community-board' )
                    : 'Please select at least one request.' );
                return false;
            }
            if ( 'delete' === action ) {
                if ( ! window.confirm( 'Permanently delete the selected request(s)? This cannot be undone.' ) ) {
                    e.preventDefault();
                    return false;
                }
            }
        } );

        // -------- Per-page selector ------------------------------
        $view.on( 'change', '[data-zcrb-per-page]', function () {
            var $sel = $( this );
            var val  = $sel.val();
            var base = $sel.data( 'base-url' ) || '';
            if ( ! base ) {
                return;
            }
            var sep = base.indexOf( '?' ) === -1 ? '?' : '&';
            // Strip any existing per_page= from the base to avoid duplicates.
            var cleaned = base.replace( /([?&])per_page=[^&]*(&|$)/, function ( m, p1, p2 ) {
                return p2 === '&' ? p1 : ( p1 === '?' ? '' : '' );
            } );
            // Ensure the separator character is correct after strip.
            var finalSep = cleaned.indexOf( '?' ) === -1 ? '?' : '&';
            window.location.href = cleaned + finalSep + 'per_page=' + encodeURIComponent( val );
        } );

        // Run once on init so the empty-message toggles start in sync.
        applyKeyword();
    }

    // ------------------------------------------------------------------
    // Sidebar intercept — when already on a hub page, intercept WP
    // sidebar submenu clicks and do SPA view switching instead of nav.
    // ------------------------------------------------------------------
    function initSidebarIntercept() {
        var $app = $( '#zcrb-app' );
        if ( ! $app.length ) {
            return;
        }

        var sidebarLinks = document.querySelectorAll( '#adminmenu .toplevel_page_zcrb-hub .wp-submenu a' );
        sidebarLinks.forEach( function ( link ) {
            link.addEventListener( 'click', function ( e ) {
                var href = link.getAttribute( 'href' ) || '';
                var view = null;

                if ( href.indexOf( 'page=zcrb-hub-requests' ) !== -1 ) {
                    view = 'requests';
                } else if ( href.indexOf( 'page=zcrb-settings' ) !== -1 ) {
                    view = 'settings';
                } else if ( href.indexOf( 'page=zcrb-hub' ) !== -1 && href.indexOf( 'page=zcrb-hub-requests' ) === -1 ) {
                    view = 'dashboard';
                }

                if ( view !== null ) {
                    e.preventDefault();

                    // Trigger the existing SPA view switch logic.
                    var $navItems = $app.find( '.zcrb-nav-item' );
                    var $views    = $app.find( '.zcrb-view' );
                    var $title    = $( '#zcrb-view-title' );

                    $navItems
                        .removeClass( 'is-active' )
                        .attr( 'aria-selected', 'false' )
                        .filter( '[data-view="' + view + '"]' )
                        .addClass( 'is-active' )
                        .attr( 'aria-selected', 'true' );

                    $views.removeClass( 'is-active' ).filter( '[data-view="' + view + '"]' ).addClass( 'is-active' );

                    var lbl = $navItems.filter( '.is-active' ).find( '.zcrb-nav-label' ).text();
                    if ( $title.length && lbl ) {
                        $title.text( lbl );
                    }

                    var url = ( ZCRBHub && ZCRBHub.hubUrl ) || 'admin.php?page=zcrb-hub';
                    history.pushState( { zcrbView: view }, '', url + '&section=' + view );

                    // Update sidebar active states.
                    $( '#adminmenu .toplevel_page_zcrb-hub .wp-submenu li' ).removeClass( 'current' );
                    $( link ).parent( 'li' ).addClass( 'current' );

                    $( document ).trigger( 'zcrb-hub:view-shown', [ view ] );
                }
            } );
        } );
    }

    // ------------------------------------------------------------------
    // Boot.
    // ------------------------------------------------------------------
    $( function () {
        initSpaNav();
        initSettingsForm();
        initRequestsFilter();
        initSidebarIntercept();
    } );

} )( jQuery );
