<?php

declare(strict_types=1);

namespace Apermo\Stash\Admin;

use Apermo\Stash\PostType\TagTaxonomy;

\defined( 'ABSPATH' ) || exit();

/**
 * Wires jQuery UI autocomplete to the comma-separated tags inputs in the
 * dashboard widget and the bookmark list-screen quick-add form.
 *
 * Backs onto WordPress's existing `ajax-tag-search` admin-ajax endpoint,
 * which natively understands any taxonomy registered with `show_ui` true
 * — so all we contribute is the script enqueue and a small adapter that
 * suggests against only the last comma-separated segment of the input.
 *
 * The bookmark edit screen relies on core's standard taxonomy meta box,
 * which already ships its own autocomplete; this class only targets the
 * standalone forms.
 */
class TagAutocomplete {

	/**
	 * Returns true when the current screen renders the dashboard quick-add form.
	 *
	 * @param string $hook Hook suffix passed to admin_enqueue_scripts.
	 *
	 * @return bool
	 */
	private static function is_target_screen( string $hook ): bool {
		return $hook === 'index.php';
	}

	/**
	 * Returns minimal CSS for the autocomplete dropdown.
	 *
	 * WordPress doesn't bundle the full jQuery UI base stylesheet, so the
	 * dropdown would otherwise render unstyled. This is enough to give it
	 * an admin-coloured panel with a hover state — no external assets.
	 *
	 * @return string
	 */
	private static function dropdown_css(): string {
		return '.ui-autocomplete{background:#fff;border:1px solid #c3c4c7;list-style:none;margin:0;padding:0;position:absolute;z-index:9999;box-shadow:0 2px 4px rgba(0,0,0,0.08);max-height:14rem;overflow-y:auto}.ui-autocomplete .ui-menu-item{padding:6px 10px;cursor:pointer}.ui-autocomplete .ui-menu-item.ui-state-active,.ui-autocomplete .ui-menu-item:hover{background:#2271b1;color:#fff}';
	}

	/**
	 * Returns the inline adapter script that wires jQuery UI autocomplete
	 * to inputs marked with `data-linkstash-tag-autocomplete="<taxonomy>"`.
	 *
	 * @return string
	 */
	private static function adapter_js(): string {
		$taxonomy = TagTaxonomy::TAXONOMY;

		return "( function ( \$ ) {\n"
			. "\t\$( function () {\n"
			. "\t\t\$( 'input[data-linkstash-tag-autocomplete]' ).each( function () {\n"
			. "\t\t\tvar \$input = \$( this );\n"
			. "\t\t\tvar taxonomy = \$input.data( 'linkstashTagAutocomplete' ) || '" . $taxonomy . "';\n"
			. "\t\t\t\$input.autocomplete( {\n"
			. "\t\t\t\tminLength: 1,\n"
			. "\t\t\t\tsource: function ( request, response ) {\n"
			. "\t\t\t\t\tvar term = request.term.split( /,\\s*/ ).pop();\n"
			. "\t\t\t\t\tif ( term.length < 1 ) {\n"
			. "\t\t\t\t\t\tresponse( [] );\n"
			. "\t\t\t\t\t\treturn;\n"
			. "\t\t\t\t\t}\n"
			. "\t\t\t\t\t\$.get( window.ajaxurl, {\n"
			. "\t\t\t\t\t\taction: 'ajax-tag-search',\n"
			. "\t\t\t\t\t\ttax: taxonomy,\n"
			. "\t\t\t\t\t\tq: term\n"
			. "\t\t\t\t\t}, function ( data ) {\n"
			. "\t\t\t\t\t\tresponse( ( data || '' ).split( '\\n' ).filter( Boolean ) );\n"
			. "\t\t\t\t\t} );\n"
			. "\t\t\t\t},\n"
			. "\t\t\t\tsearch: function () {\n"
			. "\t\t\t\t\tvar term = this.value.split( /,\\s*/ ).pop();\n"
			. "\t\t\t\t\tif ( term.length < 1 ) {\n"
			. "\t\t\t\t\t\treturn false;\n"
			. "\t\t\t\t\t}\n"
			. "\t\t\t\t},\n"
			. "\t\t\t\tfocus: function () { return false; },\n"
			. "\t\t\t\tselect: function ( event, ui ) {\n"
			. "\t\t\t\t\tvar terms = this.value.split( /,\\s*/ );\n"
			. "\t\t\t\t\tterms.pop();\n"
			. "\t\t\t\t\tterms.push( ui.item.value );\n"
			. "\t\t\t\t\tterms.push( '' );\n"
			. "\t\t\t\t\tthis.value = terms.join( ', ' );\n"
			. "\t\t\t\t\treturn false;\n"
			. "\t\t\t\t}\n"
			. "\t\t\t} );\n"
			. "\t\t} );\n"
			. "\t} );\n"
			. "} )( jQuery );\n";
	}

	/**
	 * Hooks the script enqueue.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue' ] );
	}

	/**
	 * Enqueues jQuery UI autocomplete + adapter on the screens that show
	 * a quick-add form (dashboard, bookmark list table).
	 *
	 * @param string $hook Current admin screen hook suffix.
	 *
	 * @return void
	 */
	public function maybe_enqueue( string $hook ): void {
		if ( ! self::is_target_screen( $hook ) ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_add_inline_script( 'jquery-ui-autocomplete', self::adapter_js() );
		wp_register_style( 'linkstash-tag-autocomplete', false, [], '0.1.0' );
		wp_enqueue_style( 'linkstash-tag-autocomplete' );
		wp_add_inline_style( 'linkstash-tag-autocomplete', self::dropdown_css() );
	}
}
