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

        $search_term = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
        $total_items = $table->get_total_items_count();

        static $styles_printed = false;
        if ( ! $styles_printed ) {
            $styles_printed = true;
            echo '<style id="mcl-admin-styles">'
                . '.mcl-table-container{overflow-x:auto;margin-top:1em;border:1px solid #dcdcde;border-radius:4px;background:#fff;}'
                . '#mcl-course-table{scroll-margin-top:80px;}'
                . '.mcl-table-container table.wp-list-table{min-width:1200px;border:0;margin-bottom:0;}'
                . '.mcl-table-container table.wp-list-table thead tr th, .mcl-table-container table.wp-list-table tbody tr td{white-space:nowrap;}'
                . '.mcl-table-container table.wp-list-table th.column-title, .mcl-table-container table.wp-list-table td.column-title{position:sticky;left:0;background:#fff;z-index:3;box-shadow:2px 0 0 rgba(220,220,222,.75);max-width:220px;white-space:normal;}'
                . '.mcl-table-container table.wp-list-table th.column-course_number, .mcl-table-container table.wp-list-table td.column-course_number{position:sticky;left:220px;background:#fff;z-index:2;box-shadow:2px 0 0 rgba(220,220,222,.5);}'
                . '.mcl-search-summary{margin-top:20px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:4px;}'
                . '.mcl-search-summary h2{margin:0 0 8px;}'
                . '.mcl-search-summary p{margin:0 0 12px;color:#50575e;}'
                . '.mcl-search-results{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;}'
                . '.mcl-search-card{background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:12px;}'
                . '.mcl-search-card h3{margin:0 0 8px;font-size:14px;}'
                . '.mcl-search-card .mcl-meta{font-size:12px;color:#50575e;margin:4px 0;}'
                . '.mcl-search-card .mcl-card-actions{margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;}'
                . '.mcl-search-card .mcl-card-actions .button{margin:0;}'
                . '</style>';
        }

        if ( '' !== $search_term ) {
            $preview_limit   = 6;
            $displayed_items = array_slice( $table->items, 0, $preview_limit );

            echo '<div class="mcl-search-summary">';

            if ( $total_items > 0 ) {
                printf(
                    '<h2>%s</h2>',
                    sprintf(
                        esc_html__( 'Found %1$s course(s) matching "%2$s".', 'master-course-list' ),
                        number_format_i18n( $total_items ),
                        esc_html( $search_term )
                    )
                );

                if ( $total_items > count( $displayed_items ) ) {
                    printf(
                        '<p>%s</p>',
                        sprintf(
                            esc_html__( 'Showing the first %1$s results. Use the table below for the full list.', 'master-course-list' ),
                            number_format_i18n( count( $displayed_items ) )
                        )
                    );
                } else {
                    echo '<p>' . esc_html__( 'Use the quick actions below or scroll to the table for full context.', 'master-course-list' ) . '</p>';
                }

                if ( ! empty( $displayed_items ) ) {
                    $credit_labels = Master_Course_List_Data::get_credit_fields();
                    echo '<div class="mcl-search-results">';

                    foreach ( $displayed_items as $item ) {
                        $course_number = ! empty( $item['course_number'] ) ? $item['course_number'] : __( 'Not assigned', 'master-course-list' );
                        $title         = ! empty( $item['title'] ) ? $item['title'] : __( '(no title)', 'master-course-list' );
                        $heading       = sprintf(
                            /* translators: 1: Course number, 2: Course title */
                            __( 'Course %1$s - %2$s', 'master-course-list' ),
                            $course_number,
                            $title
                        );

                        $edit_link         = get_edit_post_link( $item['ID'] );
                        $view_link         = get_permalink( $item['ID'] );
                        $product_edit_link = ! empty( $item['product_id'] ) ? get_edit_post_link( $item['product_id'] ) : '';

                        echo '<div class="mcl-search-card">';
                        echo '<h3>' . esc_html( $heading ) . '</h3>';

                        if ( ! empty( $item['version_name'] ) ) {
                            $version_line = $item['version_name'];
                            if ( ! empty( $item['version_status'] ) ) {
                                $version_line .= sprintf( ' (%s)', ucfirst( $item['version_status'] ) );
                            }
                            echo '<div class="mcl-meta"><strong>' . esc_html__( 'Latest version', 'master-course-list' ) . ':</strong> ' . esc_html( $version_line ) . '</div>';
                        }

                        if ( ! empty( $item['credits'] ) && is_array( $item['credits'] ) ) {
                            $credit_summary = array();
                            foreach ( $item['credits'] as $slug => $value ) {
                                if ( '' === $value && '0' !== $value && 0 !== $value ) {
                                    continue;
                                }
                                $label            = isset( $credit_labels[ $slug ] ) ? $credit_labels[ $slug ] : $slug;
                                $credit_summary[] = sprintf( '%1$s: %2$s', $label, $value );
                            }
                            if ( ! empty( $credit_summary ) ) {
                                echo '<div class="mcl-meta"><strong>' . esc_html__( 'Credits', 'master-course-list' ) . ':</strong> ' . esc_html( implode( ', ', $credit_summary ) ) . '</div>';
                            }
                        }

                        if ( ! empty( $item['product_id'] ) ) {
                            $product_title = get_the_title( $item['product_id'] );
                            if ( '' === $product_title ) {
                                $product_title = $item['product_id'];
                            }
                            echo '<div class="mcl-meta"><strong>' . esc_html__( 'Product', 'master-course-list' ) . ':</strong> ' . esc_html( $product_title ) . '</div>';
                        }

                        if ( ! empty( $item['modified'] ) ) {
                            $timestamp    = absint( $item['modified'] );
                            $date_format  = sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) );
                            $updated_label = wp_date( $date_format, $timestamp );
                            echo '<div class="mcl-meta"><strong>' . esc_html__( 'Last updated', 'master-course-list' ) . ':</strong> ' . esc_html( $updated_label ) . '</div>';
                        }

                        echo '<div class="mcl-card-actions">';
                        if ( $edit_link ) {
                            echo '<a class="button button-primary" href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit course', 'master-course-list' ) . '</a>';
                        }
                        echo '<a class="button" href="#mcl-course-table">' . esc_html__( 'Show in table', 'master-course-list' ) . '</a>';
                        if ( $product_edit_link ) {
                            echo '<a class="button" href="' . esc_url( $product_edit_link ) . '">' . esc_html__( 'Edit product', 'master-course-list' ) . '</a>';
                        }
                        if ( $view_link ) {
                            echo '<a class="button" href="' . esc_url( $view_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View course', 'master-course-list' ) . '</a>';
                        }
                        echo '</div>';

                        echo '</div>';
                    }

                    echo '</div>';
                }
            } else {
                printf(
                    '<h2>%s</h2>',
                    sprintf(
                        esc_html__( 'No courses found matching "%s".', 'master-course-list' ),
                        esc_html( $search_term )
                    )
                );
                echo '<p>' . esc_html__( 'Double-check the course number formatting or run a fresh import to add missing courses.', 'master-course-list' ) . '</p>';
            }

            echo '</div>';
        }

        echo '<form method="get">';
        foreach ( array( 'page', 'orderby', 'order' ) as $hidden ) {
            if ( isset( $_REQUEST[ $hidden ] ) ) {
                echo '<input type="hidden" name="' . esc_attr( $hidden ) . '" value="' . esc_attr( wp_unslash( $_REQUEST[ $hidden ] ) ) . '" />';
            }
        }

        $table->search_box( __( 'Search courses', 'master-course-list' ), 'mcl-courses' );

        echo '<div id="mcl-course-table" class="mcl-table-container">';
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
