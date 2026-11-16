<?php
/**
 * Disinstallazione.
 *
 * Se l'agenzia non ha scelto "Cancella tutti i dati", non viene toccato nulla:
 * i dati restano nel database. Se lo ha scelto, vengono rimossi tabelle,
 * opzioni, contenuti del plugin e meta utente.
 *
 * @package AcademyElearning
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ael_settings = get_option( 'ael_settings', array() );
if ( ! is_array( $ael_settings ) || empty( $ael_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// 1. Tabelle: quelle del core più quelle registrate dai moduli.
$ael_tables = get_option( 'ael_tables', array() );
$ael_tables = is_array( $ael_tables ) ? $ael_tables : array();
$ael_tables = array_unique( array_merge( array( 'ael_grants', 'ael_progress', 'ael_watch_time' ), $ael_tables ) );
foreach ( $ael_tables as $ael_table ) {
	if ( is_string( $ael_table ) && preg_match( '/^ael_[a-z0-9_]+$/', $ael_table ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . $ael_table . '`' );
	}
}

// 2. Contenuti del plugin (corsi, moduli, lezioni, docenti, raccolte) e i loro meta.
$ael_like = $wpdb->esc_like( 'ael_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$ael_post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type LIKE %s", $ael_like ) );
foreach ( $ael_post_ids as $ael_post_id ) {
	wp_delete_post( (int) $ael_post_id, true );
}

// 3. Meta utente del plugin (per esempio il collegamento con Stripe) e capacità dell'agenzia.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $ael_like ) );
foreach ( get_users( array( 'fields' => 'ID' ) ) as $ael_user_id ) {
	$ael_user = get_userdata( (int) $ael_user_id );
	if ( $ael_user && $ael_user->has_cap( 'ael_manage_modules' ) ) {
		$ael_user->remove_cap( 'ael_manage_modules' );
	}
}

// 3b. Ruolo Docente e permesso del titolare.
remove_role( 'ael_teacher' );
$ael_admin_role = get_role( 'administrator' );
if ( $ael_admin_role ) {
	$ael_admin_role->remove_cap( 'ael_manage_academy' );
}
foreach ( get_users( array( 'fields' => 'ID' ) ) as $ael_user_id ) {
	$ael_user = get_userdata( (int) $ael_user_id );
	if ( $ael_user && $ael_user->has_cap( 'ael_manage_academy' ) ) {
		$ael_user->remove_cap( 'ael_manage_academy' );
	}
}

// 4. Opzioni.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$ael_options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $ael_like ) );
foreach ( $ael_options as $ael_option ) {
	delete_option( $ael_option );
}
