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
     * Cached lookup for course numbers keyed by normalized value.
     *
     * @var array|null
     */
    private static $course_number_index = null;

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
     * Reset cached metadata definitions.
     */
    public static function flush_metadata_cache() {
        self::$metadata_fields = null;
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
        $normalized    = self::normalize_course_number( $course_number );
        $course_number = trim( (string) $course_number );

        if ( '' === $course_number && '' === $normalized ) {
            return 0;
        }

        $index = self::get_course_number_index( $type );

        if ( '' !== $normalized && isset( $index[ $normalized ] ) ) {
            return (int) $index[ $normalized ];
        }

        $candidates = array_unique(
            array_filter(
                array(
                    $course_number,
                    $normalized,
                    ltrim( $course_number, '#' ),
                    '#' . ltrim( $course_number, '#' ),
                ),
                'strlen'
            )
        );

        $meta_key = 'course_number_' . sanitize_key( $type );
        if ( 'course_number_global' !== $meta_key ) {
            if ( 'global' === $type ) {
                $meta_key = 'course_number_global';
            }
        }

        global $wpdb;
        $table = self::get_course_query_table();

        foreach ( $candidates as $candidate ) {
            $sql       = $wpdb->prepare( "SELECT course_id FROM {$table} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $meta_key, $candidate );
            $course_id = (int) $wpdb->get_var( $sql );

            if ( $course_id > 0 ) {
                self::add_course_number_to_index( $normalized ?: self::normalize_course_number( $candidate ), $course_id, $type );
                return $course_id;
            }
        }

        return 0;
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

        // Prime with any definitions saved by the schema helper.
        foreach ( Master_Course_List_Schema::get_registered_metadata_fields() as $slug => $definition ) {
            if ( empty( $definition['status'] ) || 'active' !== $definition['status'] ) {
                continue;
            }

            self::$metadata_fields[ $slug ] = array(
                'label'       => $definition['label'],
                'description' => isset( $definition['description'] ) ? $definition['description'] : '',
            );
        }

        if ( self::flms_available() && class_exists( 'FLMS_Module_Course_Metadata' ) ) {
            $metadata_module = new FLMS_Module_Course_Metadata();
            $fields           = $metadata_module->get_course_metadata_settings_fields();

            if ( ! empty( $fields ) && is_array( $fields ) ) {
                foreach ( $fields as $slug => $field ) {
                    if ( ! isset( $field['status'] ) || 'active' !== $field['status'] ) {
                        continue;
                    }

                    $label       = isset( $field['name'] ) ? $field['name'] : $slug;
                    $description = isset( $field['description'] ) ? $field['description'] : '';

                    if ( ! isset( self::$metadata_fields[ $slug ] ) ) {
                        self::$metadata_fields[ $slug ] = array(
                            'label'       => $label,
                            'description' => $description,
                        );
                    }
                }
            }
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

    /**
     * Normalize course number string for comparisons.
     *
     * @param string $number Raw course number.
     * @return string
     */
    private static function normalize_course_number( $number ) {
        $number = trim( (string) $number );
        if ( '' === $number ) {
            return '';
        }

        $number = strtoupper( $number );
        $number = ltrim( $number, '#' );
        $number = preg_replace( '/[^A-Z0-9\-]/', '', $number );

        return $number;
    }

    /**
     * Build or retrieve the cached course number index.
     *
     * @param string $type Course number type key.
     * @return array
     */
    private static function get_course_number_index( $type = 'global' ) {
        if ( null === self::$course_number_index ) {
            self::$course_number_index = array();
        }

        if ( isset( self::$course_number_index[ $type ] ) ) {
            return self::$course_number_index[ $type ];
        }

        self::$course_number_index[ $type ] = array();

        if ( ! self::flms_available() ) {
            return self::$course_number_index[ $type ];
        }

        $course_ids = get_posts(
            array(
                'post_type'      => 'flms-courses',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
            )
        );

        foreach ( $course_ids as $course_id ) {
            $versions = get_post_meta( $course_id, 'flms_version_content', true );
            if ( empty( $versions ) || ! is_array( $versions ) ) {
                continue;
            }

            foreach ( $versions as $version ) {
                if ( ! isset( $version['course_numbers'] ) || ! is_array( $version['course_numbers'] ) ) {
                    continue;
                }

                foreach ( $version['course_numbers'] as $number_type => $value ) {
                    if ( 'global' !== $type && $number_type !== $type ) {
                        continue;
                    }

                    if ( 'global' === $type && 'global' !== $number_type ) {
                        continue;
                    }

                    $normalized = self::normalize_course_number( $value );
                    if ( '' === $normalized ) {
                        continue;
                    }

                    if ( ! isset( self::$course_number_index[ $type ][ $normalized ] ) ) {
                        self::$course_number_index[ $type ][ $normalized ] = (int) $course_id;
                    }
                }
            }
        }

        return self::$course_number_index[ $type ];
    }

    /**
     * Add a course number to the runtime index for faster subsequent lookups.
     *
     * @param string $normalized Normalized course number.
     * @param int    $course_id  Course ID.
     * @param string $type       Course number type.
     */
    private static function add_course_number_to_index( $normalized, $course_id, $type = 'global' ) {
        $normalized = self::normalize_course_number( $normalized );
        $course_id  = (int) $course_id;

        if ( '' === $normalized || $course_id <= 0 ) {
            return;
        }

        if ( null === self::$course_number_index ) {
            self::$course_number_index = array();
        }

        if ( ! isset( self::$course_number_index[ $type ] ) || ! is_array( self::$course_number_index[ $type ] ) ) {
            self::$course_number_index[ $type ] = array();
        }

        if ( ! isset( self::$course_number_index[ $type ][ $normalized ] ) ) {
            self::$course_number_index[ $type ][ $normalized ] = $course_id;
        }
    }
}
