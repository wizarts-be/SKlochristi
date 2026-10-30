<?php
/**
 * Rol "Klant": de club beheert de inhoud en de menu's, niet het thema.
 * Basis is Redacteur, zonder ongefilterde HTML, plus edit_theme_options voor
 * Weergave > Menu's. Al de rest dat aan edit_theme_options hangt (Site Editor,
 * Lettertypen, Customizer, Thema's, Widgets) en het menu Extra wordt verborgen
 * én geblokkeerd.
 */

defined( 'ABSPATH' ) || exit;

const SKL_ROLES_VERSION = 1;

function skl_is_klant() {
	$user = wp_get_current_user();
	return $user->exists() && in_array( 'klant', (array) $user->roles, true );
}

// De rol staat in de database: (opnieuw) aanmaken zodra de definitie hier wijzigt.
add_action( 'init', function () {
	if ( (int) get_option( 'skl_roles_version' ) === SKL_ROLES_VERSION && get_role( 'klant' ) ) {
		return;
	}
	$editor = get_role( 'editor' );
	if ( ! $editor ) {
		return;
	}
	$caps = $editor->capabilities;
	unset( $caps['unfiltered_html'] );
	$caps['edit_theme_options'] = true;

	remove_role( 'klant' );
	add_role( 'klant', 'Klant', $caps );
	update_option( 'skl_roles_version', SKL_ROLES_VERSION );
} );

// Ook voor de Klant geen ongefilterde HTML, wat de rol in de database ook zegt.
add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id ) {
	if ( 'unfiltered_html' === $cap || 'customize' === $cap ) {
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'klant', (array) $user->roles, true ) ) {
			return array( 'do_not_allow' );
		}
	}
	return $caps;
}, 10, 3 );

// Admin-pagina's die de Klant niet ziet. Weergave linkt dan vanzelf naar Menu's.
add_action( 'admin_menu', function () {
	if ( ! skl_is_klant() ) {
		return;
	}
	remove_menu_page( 'tools.php' );
	foreach ( array( 'themes.php', 'site-editor.php', 'widgets.php', 'theme-editor.php', 'font-library.php' ) as $page ) {
		remove_submenu_page( 'themes.php', $page );
	}
	global $submenu;
	foreach ( $submenu['themes.php'] ?? array() as $i => $item ) {
		if ( str_starts_with( $item[2], 'customize.php' ) || str_starts_with( $item[2], 'site-editor.php' ) ) {
			unset( $submenu['themes.php'][ $i ] );
		}
	}
}, 999 );

// En niet via de URL.
add_action( 'admin_init', function () {
	global $pagenow;
	$blocked = array( 'themes.php', 'theme-install.php', 'theme-editor.php', 'site-editor.php', 'font-library.php', 'customize.php', 'widgets.php', 'tools.php', 'import.php', 'export.php', 'export-personal-data.php', 'erase-personal-data.php', 'site-health.php' );
	if ( skl_is_klant() && in_array( $pagenow, $blocked, true ) && ! wp_doing_ajax() ) {
		wp_die( 'Deze pagina is niet beschikbaar voor je account.', 403, array( 'response' => 403, 'back_link' => true ) );
	}
} );

// Bovenbalk: geen "Website bewerken", Customizer, Thema's of Widgets.
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! skl_is_klant() ) {
		return;
	}
	foreach ( array( 'site-editor', 'customize', 'themes', 'widgets' ) as $node ) {
		$bar->remove_node( $node );
	}
}, 999 );

// Sjablonen, stijlen en widgets ook niet aanpassen via de REST API (bv. "Sjabloon bewerken" in de editor).
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( null !== $result || 'GET' === $request->get_method() || ! skl_is_klant() ) {
		return $result;
	}
	if ( preg_match( '#^/wp/v2/(templates|template-parts|global-styles|navigation|widgets|sidebars|themes|font-families|font-collections)(/|$)#', $request->get_route() ) ) {
		return new WP_Error( 'rest_forbidden', 'Dit is niet beschikbaar voor je account.', array( 'status' => 403 ) );
	}
	return $result;
}, 10, 3 );

add_filter( 'block_editor_settings_all', function ( $settings ) {
	if ( skl_is_klant() ) {
		$settings['supportsTemplateMode'] = false;
	}
	return $settings;
} );
