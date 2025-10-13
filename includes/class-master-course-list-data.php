<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Master_Course_List_Data {

    /**
     * Cached course credit fields.
     *
     * @var array|null
     */
    private static $credit_fields = null;

    /**
     * Cached course metadata fields.
     *
     * @var array|null
     */
    private static $metadata_fields = null;

    /**
     * Cached FLMS course query table name.
     *
     * @var string|null
     */
    private static $course_query_table = null;

    /**
     * Determine if the core FLMS classes are available.
     */
    public static function flms_available() {
        return class_exists( 'FLMS_Course' );
    }

    /**
     * Retrieve course metadata table used for indexed lookups.
     */
    public static function get_course_query_table() {
        if ( null !== self::$course_query_table ) {
            return self::$course_query_table;
        }

        global $wpdb;

        if ( defined( 'FLMS_COURSE_QUERY_TABLE' ) ) {
            self::$course_query_table = FLMS_COURSE_QUERY_TABLE;
        } else {
            self::$course_query_table = $wpdb->base_prefix . 'flms_course_metadata';
        }

        return self::$course_query_table;
    }

    /**
     * Attempt to find a course ID by course number (global or credit-specific).
     *
     * @param string $course_number Course number value.
     * @param string $type          Number type, defaults to global.
     * @return int Course ID or 0 if not found.
     */
    public static function find_course_id_by_number( $course_number, $type = 'global' ) {
        $course_number = trim( (string) $course_number );
        if ( '' === $course_number ) {
            return 0;
        }

        global $wpdb;

        $meta_key = 'course_number_' . sanitize_key( $type );
        if ( 'course_number_global' !== $meta_key ) {
            // Keep compatibility for the main identifier.
            if ( 'global' === $type ) {
                $meta_key = 'course_number_global';
            }
        }

        $table = self::get_course_query_table();

        $sql       = $wpdb->prepare( "SELECT course_id FROM {$table} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $meta_key, $course_number );
        $course_id = (int) $wpdb->get_var( $sql );

        return $course_id;
    }

    /**
     * Retrieve active course credit fields keyed by slug with translated labels.
     *
     * @return array
     */
    public static function get_credit_fields() {
        if ( null !== self::$credit_fields ) {
            return self::$credit_fields;
        }

        self::$credit_fields = array();

        if ( ! self::flms_available() || ! class_exists( 'FLMS_Module_Course_Credits' ) ) {
            return self::$credit_fields;
        }

        $credits_module = new FLMS_Module_Course_Credits();
        $fields         = $credits_module->get_course_credit_fields();

        if ( empty( $fields ) ) {
            return self::$credit_fields;
        }

        foreach ( $fields as $slug ) {
            $label                              = $credits_module->get_credit_label( $slug );
            self::$credit_fields[ $slug ] = $label;
        }

        return self::$credit_fields;
    }

    /**
     * Retrieve active course metadata definitions keyed by slug.
     *
     * Each entry contains: label (name), description, and status data.
     *
     * @return array
     */
    public static function get_metadata_fields() {
        if ( null !== self::$metadata_fields ) {
            return self::$metadata_fields;
        }

        self::$metadata_fields = array();

        if ( ! self::flms_available() || ! class_exists( 'FLMS_Module_Course_Metadata' ) ) {
            return self::$metadata_fields;
        }

        $metadata_module = new FLMS_Module_Course_Metadata();
        $fields           = $metadata_module->get_course_metadata_settings_fields();

        if ( empty( $fields ) || ! is_array( $fields ) ) {
            return self::$metadata_fields;
        }

        foreach ( $fields as $slug => $field ) {
            if ( ! isset( $field['status'] ) || 'active' !== $field['status'] ) {
                continue;
            }

            $label       = isset( $field['name'] ) ? $field['name'] : $slug;
            $description = isset( $field['description'] ) ? $field['description'] : '';

            self::$metadata_fields[ $slug ] = array(
                'label'       => $label,
                'description' => $description,
            );
        }

        return self::$metadata_fields;
    }

    /**
     * Fetch paginated course data for the table view.
     *
     * @param array $args Arguments: paged, per_page, search, orderby, order.
     * @return array { items, total, total_pages }
     */
    public static function get_courses( $args = array() ) {
        $defaults = array(
            'paged'    => 1,
            'per_page' => 20,
            'search'   => '',
            'orderby'  => 'title',
            'order'    => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        $query_args = array(
            'post_type'      => 'flms-courses',
            'post_status'    => array( 'publish', 'draft', 'pending' ),
            'posts_per_page' => absint( $args['per_page'] ),
            'paged'          => max( 1, absint( $args['paged'] ) ),
            'orderby'        => sanitize_key( $args['orderby'] ),
            'order'          => in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'ASC',
        );

        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = $args['search'];
        }

        $query = new WP_Query( $query_args );

        $items = array();
        if ( ! empty( $query->posts ) ) {
            foreach ( $query->posts as $post ) {
                $items[] = self::format_course_row( $post );
            }
        }

        return array(
            'items'       => $items,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
        );
    }

    /**
     * Convert a WP_Post representing a course to a normalized array for UI use.
     *
     * @param WP_Post $post Course post.
     * @return array
     */
    public static function format_course_row( $post ) {
        $course_number        = '';
        $version_index        = '';
        $version_name         = '';
        $version_status       = '';
        $credits              = array();
        $metadata             = array();
        $per_credit_numbers   = array();

        if ( self::flms_available() ) {
            $course   = new FLMS_Course( $post->ID );
            $versions = $course->get_versions();

            if ( ! empty( $versions ) && is_array( $versions ) ) {
                $latest_version = $course->get_latest_course_version();

                if ( '' === $latest_version ) {
                    $keys           = array_keys( $versions );
                    $latest_version = reset( $keys );
                }

                if ( isset( $versions[ $latest_version ] ) ) {
                    $version_data = $versions[ $latest_version ];

                    $version_index  = $latest_version;
                    $version_name   = isset( $version_data['version_name'] ) ? $version_data['version_name'] : sprintf( __( 'Version %s', 'master-course-list' ), $latest_version );
                    $version_status = isset( $version_data['version_status'] ) ? $version_data['version_status'] : '';

                    if ( isset( $version_data['course_numbers']['global'] ) ) {
                        $course_number = $version_data['course_numbers']['global'];
                    }

                    if ( isset( $version_data['course_numbers'] ) && is_array( $version_data['course_numbers'] ) ) {
                        $per_credit_numbers = $version_data['course_numbers'];
                    }

                    $credit_fields = self::get_credit_fields();
                    foreach ( $credit_fields as $slug => $label ) {
                        $value             = isset( $version_data['course_credits'][ $slug ] ) ? $version_data['course_credits'][ $slug ] : '';
                        $credits[ $slug ] = $value;
                    }

                    $metadata_fields = self::get_metadata_fields();
                    foreach ( $metadata_fields as $slug => $definition ) {
                        $value              = isset( $version_data['course_metadata'][ $slug ] ) ? $version_data['course_metadata'][ $slug ] : '';
                        $metadata[ $slug ] = $value;
                    }
                }
            }
        }

        $product_id = get_post_meta( $post->ID, 'flms_woocommerce_product_id', true );

        return array(
            'ID'                 => $post->ID,
            'title'              => get_the_title( $post ),
            'status'             => get_post_status( $post ),
            'course_number'      => $course_number,
            'version_index'      => $version_index,
            'version_name'       => $version_name,
            'version_status'     => $version_status,
            'credits'            => $credits,
            'metadata'           => $metadata,
            'per_credit_numbers' => $per_credit_numbers,
            'product_id'         => $product_id,
            'modified'           => get_post_modified_time( 'U', true, $post ),
        );
    }
}



