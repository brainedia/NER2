<?php



# Projekt-Konfigurationsdatei verwenden
$path = explode( '/', $_SERVER['SCRIPT_NAME'] );
if( $path[ count($path)-2 ] == 'brainstage' ) {
	define( 'IN_BRAINSTAGE_DIR', true );
	include_once( '../config.php' );
}
else {
	define( 'IN_BRAINSTAGE_DIR', false );
	include_once( 'config.php' );
}
unset( $path );
