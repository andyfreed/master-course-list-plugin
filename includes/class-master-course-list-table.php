<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Master_Course_List_Table extends WP_List_Table {

    /**
     * Cached credit fields.
     *
     * @var array
     */
    protected $credit_fields = array();

    /**
     * Cached metadata fields.
     *
     * @var array
     */
    protected $metadata_fields = array();

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'course',
                'plural'   => 'courses',
                'ajax'     => false,
            )
        );

        $this->credit_fields   = Master_Course_List_Data::get_credit_fields();
        $this->metadata_fields = Master_Course_List_Data::get_metadata_fields();
    }

    /**
     * Retrieve table columns.
     */
    public function get_columns() {
        $columns = array(
            'title'         => __( 'Course', 'master-course-list' ),
            'course_number' => __( 'Course #', 'master-course-list' ),
            'version'       => __( 'Latest Version', 'master-course-list' ),
            'status'        => __( 'Status', 'master-course-list' ),
            'product'       => __( 'Product', 'master-course-list' ),
            'updated'       => __( 'Last Updated', 'master-course-list' ),
        );

        foreach ( $this->credit_fields as $slug => $label ) {
            $columns[ 'credit_' . $slug ] = sprintf( __( '%s Credits', 'master-course-list' ), $label );
        }

        foreach ( $this->metadata_fields as $slug => $definition ) {
            $columns[ 'meta_' . $slug ] = $definition['label'];
        }

        return $columns;
    }

    /**
     * Sortable columns configuration.
     */
    protected function get_sortable_columns() {
        return array(
            'title'   => array( 'title', true ),
            'updated' => array( 'modified', true ),
        );
    }

    /**
     * Primary column output.
     */
    protected function column_title( $item ) {
        $edit_link = get_edit_post_link( $item['ID'] );
        $view_link = get_permalink( $item['ID'] );

        $title = '<strong>' . esc_html( $item['title'] ) . '</strong>';

        if ( $edit_link ) {
            $title = '<strong><a href="' . esc_url( $edit_link ) . '">' . esc_html( $item['title'] ) . '</a></strong>';
        }

        $actions = array();
        if ( $edit_link ) {
            $actions['edit'] = '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit', 'master-course-list' ) . '</a>';
        }
        if ( $view_link ) {
            $actions['view'] = '<a href="' . esc_url( $view_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'master-course-list' ) . '</a>';
        }

        return $title . $this->row_actions( $actions );
    }

    /**
     * Column output: course number.
     */
    protected function column_course_number( $item ) {
        if ( empty( $item['course_number'] ) ) {
            return '&mdash;';
        }
        return esc_html( $item['course_number'] );
    }

    /**
     * Column output: version summary.
     */
    protected function column_version( $item ) {
        if ( empty( $item['version_name'] ) ) {
            return '&mdash;';
        }

        $status = $item['version_status'];
        if ( ! empty( $status ) ) {
            $status = sprintf( ' <span class="mcl-version-status">(%s)</span>', esc_html( ucfirst( $status ) ) );
        }

        return esc_html( $item['version_name'] ) . $status;
    }

    /**
     * Column output: status.
     */
    protected function column_status( $item ) {
        return esc_html( ucfirst( $item['status'] ) );
    }

    /**
     * Column output: WooCommerce product reference.
     */
    protected function column_product( $item ) {
        $product_id = $item['product_id'];

        if ( empty( $product_id ) ) {
            return '&mdash;';
        }

        $product = get_post( $product_id );
        if ( ! $product ) {
            return esc_html( $product_id );
        }

        $edit_link = get_edit_post_link( $product_id );
        $title     = get_the_title( $product_id );

        if ( $edit_link ) {
            return '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
        }

        return esc_html( $title );
    }

    /**
     * Column output: last updated timestamp.
     */
    protected function column_updated( $item ) {
        if ( empty( $item['modified'] ) ) {
            return '&mdash;';
        }

        $timestamp = absint( $item['modified'] );
        $format = sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) );

        return esc_html( wp_date( $format, $timestamp ) );
    }

    /**
     * Default column handler.
     */
    protected function column_default( $item, $column_name ) {
        if ( 0 === strpos( $column_name, 'credit_' ) ) {
            $slug = str_replace( 'credit_', '', $column_name );
            $value = isset( $item['credits'][ $slug ] ) ? $item['credits'][ $slug ] : '';
            if ( '' === $value && '0' !== $value && 0 !== $value ) {
                return '&mdash;';
            }
            return esc_html( $value );
        }

        if ( 0 === strpos( $column_name, 'meta_' ) ) {
            $slug  = str_replace( 'meta_', '', $column_name );
            $value = isset( $item['metadata'][ $slug ] ) ? $item['metadata'][ $slug ] : '';
            if ( '' === $value && '0' !== $value && 0 !== $value ) {
                return '&mdash;';
            }
            return esc_html( $value );
        }

        if ( isset( $item[ $column_name ] ) ) {
            $value = $item[ $column_name ];
            if ( is_scalar( $value ) && '' !== $value ) {
                return esc_html( $value );
            }
        }

        return '&mdash;';
    }

    /**
     * Prepare table items.
     */
    public function prepare_items() {
        $per_page     = $this->get_items_per_page( 'mcl_courses_per_page', 20 );
        $current_page = $this->get_pagenum();

        $orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'title';
        $order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'ASC';
        $search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

        $results = Master_Course_List_Data::get_courses(
            array(
                'paged'    => $current_page,
                'per_page' => $per_page,
                'search'   => $search,
                'orderby'  => $orderby,
                'order'    => $order,
            )
        );

        $this->items = $results['items'];

        $this->set_pagination_args(
            array(
                'total_items' => $results['total'],
                'per_page'     => $per_page,
                'total_pages'  => $results['total_pages'],
            )
        );
    }

    /**
     * Message displayed when no items found.
     */
    public function no_items() {
        esc_html_e( 'No courses found matching your criteria.', 'master-course-list' );
    }
}



