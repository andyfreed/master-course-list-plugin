<?php
/*
Plugin Name: Master Course List
Description: Admin tools for managing BHFE course metadata directly in WordPress.
Version: 0.1.0
Author: Beacon Hill Financial Educators
License: GPL2
Text Domain: master-course-list
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MCL_PLUGIN_FILE', __FILE__ );
define( 'MCL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MCL_PLUGIN_DIR . 'includes/class-master-course-list-plugin.php';

Master_Course_List_Plugin::instance();
