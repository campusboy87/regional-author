<?php
/**
 * Plugin Name: Regional Author
 * Description: Добавляет новую роль "Региональный автор".
 * Version: 1.0
 * Author: campusboy
 * Author https://wp-plus.ru/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

include_once dirname( __FILE__ ) . '/class-regional-author.php';

register_activation_hook( __FILE__, array( 'Regional_Author', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'Regional_Author', 'deactivation' ) );

add_action( 'plugins_loaded', array( 'Regional_Author', 'init' ) );
