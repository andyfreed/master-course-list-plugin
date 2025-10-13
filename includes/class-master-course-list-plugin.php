<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Master_Course_List_Plugin {

    /**
     * Singleton instance.
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Plugin slug for hooks and menu registration.
     *
     * @var string
     */
    const SLUG = 'master-course-list';

    /**
     * Retrieve singleton instance.
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Master_Course_List_Plugin constructor.
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
    }

    /**
     * Bootstrap plugin hooks.
     */
    public function bootstrap() {
        $this->load_dependencies();

        load_plugin_textdomain( 'master-course-list', false, dirname( plugin_basename( MCL_PLUGIN_FILE ) ) . '/languages' );

        if ( is_admin() ) {
            $importer = new Master_Course_List_Importer();
            $admin    = new Master_Course_List_Admin( $importer );
            $admin->init();
        }
    }

    /**
     * Load required plugin files.
     */
    private function load_dependencies() {
        require_once MCL_PLUGIN_DIR . 'includes/class-master-course-list-schema.php';
        require_once MCL_PLUGIN_DIR . 'includes/class-master-course-list-data.php';
        require_once MCL_PLUGIN_DIR . 'includes/class-master-course-list-table.php';
        require_once MCL_PLUGIN_DIR . 'includes/class-master-course-list-importer.php';
        require_once MCL_PLUGIN_DIR . 'includes/class-master-course-list-sync.php';
        require_once MCL_PLUGIN_DIR . 'includes/class-master-course-list-admin.php';
    }
}
