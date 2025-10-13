<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Centralized schema helper for mapping spreadsheet columns to plugin metadata.
 */
class Master_Course_List_Schema {

    const OPTION_METADATA_FIELDS = 'mcl_metadata_fields';

    /**
     * Return persisted custom metadata field definitions keyed by slug.
     *
     * @return array
     */
    public static function get_registered_metadata_fields() {
        $fields = get_option( self::OPTION_METADATA_FIELDS, array() );

        if ( ! is_array( $fields ) ) {
            return array();
        }

        return array_map( array( __CLASS__, 'normalize_metadata_definition' ), $fields );
    }

    /**
     * Persist a metadata definition for the provided column header.
     *
     * @param string $header Original CSV header label.
     * @return string Slug used for storage.
     */
    public static function ensure_metadata_field( $header ) {
        $label = trim( (string) $header );
        if ( '' === $label ) {
            return '';
        }

        $slug = sanitize_title( $label );
        if ( '' === $slug ) {
            $slug = 'mcl_field_' . md5( $label );
        }

        $fields = get_option( self::OPTION_METADATA_FIELDS, array() );
        if ( ! is_array( $fields ) ) {
            $fields = array();
        }

        if ( isset( $fields[ $slug ] ) ) {
            // Keep label updated in case spreadsheet header casing changes.
            if ( isset( $fields[ $slug ]['label'] ) && $fields[ $slug ]['label'] !== $label ) {
                $fields[ $slug ]['label'] = $label;
            }
            if ( isset( $fields[ $slug ]['sources'] ) && is_array( $fields[ $slug ]['sources'] ) ) {
                if ( ! in_array( $label, $fields[ $slug ]['sources'], true ) ) {
                    $fields[ $slug ]['sources'][] = $label;
                }
            } else {
                $fields[ $slug ]['sources'] = array( $label );
            }
        } else {
            $fields[ $slug ] = array(
                'label'       => $label,
                'description' => '',
                'status'      => 'active',
                'sources'     => array( $label ),
            );
        }

        update_option( self::OPTION_METADATA_FIELDS, $fields, false );

        if ( class_exists( 'Master_Course_List_Data' ) ) {
            Master_Course_List_Data::flush_metadata_cache();
        }

        return $slug;
    }

    /**
     * Provide helper lookup for metadata field information by slug.
     *
     * @param string $slug Metadata slug.
     * @return array|null
     */
    public static function get_metadata_field( $slug ) {
        $fields = self::get_registered_metadata_fields();
        if ( isset( $fields[ $slug ] ) ) {
            return $fields[ $slug ];
        }

        return null;
    }

    /**
     * Normalize stored metadata definitions.
     *
     * @param array $definition Raw definition from the option value.
     * @return array
     */
    private static function normalize_metadata_definition( $definition ) {
        $definition = is_array( $definition ) ? $definition : array();

        return array(
            'label'       => isset( $definition['label'] ) ? (string) $definition['label'] : '',
            'description' => isset( $definition['description'] ) ? (string) $definition['description'] : '',
            'status'      => isset( $definition['status'] ) ? (string) $definition['status'] : 'active',
            'sources'     => isset( $definition['sources'] ) && is_array( $definition['sources'] ) ? array_values( array_unique( $definition['sources'] ) ) : array(),
        );
    }
}
