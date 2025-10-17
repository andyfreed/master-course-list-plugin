<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Master_Course_List_Admin {

    /**
     * Importer instance.
     *
     * @var Master_Course_List_Importer
     */
    private $importer;

    /**
     * Constructor.
     *
     * @param Master_Course_List_Importer $importer Importer service.
     */
    public function __construct( Master_Course_List_Importer $importer ) {
        $this->importer = $importer;
    }

    /**
     * Register hooks.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
    }

    /**
     * Register the Course List menu.
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'Master Course List', 'master-course-list' ),
            __( 'Course List', 'master-course-list' ),
            'manage_options',
            Master_Course_List_Plugin::SLUG,
            array( $this, 'render_admin_page' ),
            'dashicons-welcome-learn-more',
            58
        );

        add_submenu_page(
            Master_Course_List_Plugin::SLUG,
            __( 'Import Master Course List', 'master-course-list' ),
            __( 'Import', 'master-course-list' ),
            'manage_options',
            Master_Course_List_Plugin::SLUG . '-import',
            array( $this, 'render_import_page' )
        );
    }

    /**
     * Render the admin page content.
     */
    public function render_admin_page() {
        echo '<div class="wrap master-course-list-admin">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Master Course List', 'master-course-list' ) . '</h1>';
        echo '<p class="description">' . esc_html__( 'Use this view to audit course numbers, credit totals, and metadata synced from the master course list. Scroll horizontally to view all columns.', 'master-course-list' ) . '</p>';

        if ( ! Master_Course_List_Data::flms_available() ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Fragments LMS must be active to display course data.', 'master-course-list' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $table = new Master_Course_List_Table();
        $table->prepare_items();

        static $styles_printed = false;
        if ( ! $styles_printed ) {
            $styles_printed = true;
            echo '<style id="mcl-admin-styles">'
                . '.mcl-table-container{overflow-x:auto;margin-top:1em;border:1px solid #dcdcde;border-radius:4px;background:#fff;}'
                . '.mcl-table-container table.wp-list-table{min-width:1200px;border:0;margin-bottom:0;}'
                . '.mcl-table-container table.wp-list-table thead tr th, .mcl-table-container table.wp-list-table tbody tr td{white-space:nowrap;}'
                . '.mcl-table-container table.wp-list-table th.column-title, .mcl-table-container table.wp-list-table td.column-title{position:sticky;left:0;background:#fff;z-index:3;box-shadow:2px 0 0 rgba(220,220,222,.75);max-width:220px;white-space:normal;}'
                . '.mcl-table-container table.wp-list-table th.column-course_number, .mcl-table-container table.wp-list-table td.column-course_number{position:sticky;left:220px;background:#fff;z-index:2;box-shadow:2px 0 0 rgba(220,220,222,.5);}'
                . '</style>';
        }

        echo '<form method="get">';
        // Preserve existing query args when submitting search.
        foreach ( array( 'page', 'orderby', 'order' ) as $hidden ) {
            if ( isset( $_REQUEST[ $hidden ] ) ) {
                echo '<input type="hidden" name="' . esc_attr( $hidden ) . '" value="' . esc_attr( wp_unslash( $_REQUEST[ $hidden ] ) ) . '" />';
            }
        }

        $table->search_box( __( 'Search courses', 'master-course-list' ), 'mcl-courses' );

        echo '<div class="mcl-table-container">';
        $table->display();
        echo '</div>';

        echo '</form>';

        echo '</div>';
    }

    /**
     * Render importer page.
     */
    public function render_import_page() {
        if ( ! Master_Course_List_Data::flms_available() ) {
            echo '<div class="wrap">';
            echo '<h1 class="wp-heading-inline">' . esc_html__( 'Import Master Course List', 'master-course-list' ) . '</h1>';
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Fragments LMS must be active before running the importer.', 'master-course-list' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $this->importer->render_page();
    }
}
