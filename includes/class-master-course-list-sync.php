<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Master_Course_List_Sync {

    /**
     * Update a course with parsed import data.
     *
     * @param int   $course_id Course post ID.
     * @param array $row       Parsed row data.
     * @return array Result details (updated bool, messages array).
     */
    public function apply_row( $course_id, array $row ) {
        $result = array(
            'updated'  => false,
            'messages' => array(),
        );

        if ( ! Master_Course_List_Data::flms_available() ) {
            $result['messages'][] = __( 'FLMS is not available; skipping update.', 'master-course-list' );
            return $result;
        }

        $course_id = absint( $course_id );
        if ( $course_id <= 0 ) {
            $result['messages'][] = __( 'Invalid course ID supplied.', 'master-course-list' );
            return $result;
        }

        $versions = get_post_meta( $course_id, 'flms_version_content', true );
        if ( empty( $versions ) || ! is_array( $versions ) ) {
            $result['messages'][] = __( 'Course has no version data; skipping.', 'master-course-list' );
            return $result;
        }

        $course = new FLMS_Course( $course_id );
        $latest = $course->get_latest_course_version();

        if ( '' === $latest ) {
            $keys   = array_keys( $versions );
            $latest = reset( $keys );
        }

        if ( ! isset( $versions[ $latest ] ) || ! is_array( $versions[ $latest ] ) ) {
            $result['messages'][] = __( 'Unable to determine active course version.', 'master-course-list' );
            return $result;
        }

        $version = $versions[ $latest ];

        if ( ! isset( $version['course_numbers'] ) || ! is_array( $version['course_numbers'] ) ) {
            $version['course_numbers'] = array();
        }

        if ( ! empty( $row['course_number'] ) ) {
            $version['course_numbers']['global'] = sanitize_text_field( $row['course_number'] );
        }

        if ( ! empty( $row['credit_numbers'] ) && is_array( $row['credit_numbers'] ) ) {
            foreach ( $row['credit_numbers'] as $slug => $value ) {
                $version['course_numbers'][ $slug ] = sanitize_text_field( $value );
            }
        }

        if ( ! isset( $version['course_credits'] ) || ! is_array( $version['course_credits'] ) ) {
            $version['course_credits'] = array();
        }

        if ( ! empty( $row['credits'] ) && is_array( $row['credits'] ) ) {
            foreach ( $row['credits'] as $slug => $value ) {
                if ( '' === $value ) {
                    continue;
                }
                $version['course_credits'][ $slug ] = is_numeric( $value ) ? floatval( $value ) : sanitize_text_field( $value );
            }
        }

        if ( ! isset( $version['course_metadata'] ) || ! is_array( $version['course_metadata'] ) ) {
            $version['course_metadata'] = array();
        }

        if ( ! empty( $row['metadata'] ) && is_array( $row['metadata'] ) ) {
            foreach ( $row['metadata'] as $slug => $value ) {
                $version['course_metadata'][ $slug ] = sanitize_text_field( $value );
            }
        }

        $versions[ $latest ] = $version;

        update_post_meta( $course_id, 'flms_version_content', $versions );

        // Persist plugin-specific extras such as notes/word counts.
        if ( ! empty( $row['extras'] ) && is_array( $row['extras'] ) ) {
            if ( isset( $row['extras']['notes'] ) ) {
                update_post_meta( $course_id, 'mcl_notes', sanitize_textarea_field( $row['extras']['notes'] ) );
            }
            if ( isset( $row['extras']['word_count'] ) ) {
                update_post_meta( $course_id, 'mcl_word_count', sanitize_text_field( $row['extras']['word_count'] ) );
            }
            if ( isset( $row['extras']['prices'] ) && is_array( $row['extras']['prices'] ) ) {
                $prices = array();
                foreach ( $row['extras']['prices'] as $key => $price_value ) {
                    $price_value = trim( (string) $price_value );
                    if ( '' === $price_value ) {
                        continue;
                    }
                    $prices[ $key ] = sanitize_text_field( $price_value );
                }
                if ( ! empty( $prices ) ) {
                    update_post_meta( $course_id, 'mcl_prices', $prices );
                } else {
                    delete_post_meta( $course_id, 'mcl_prices' );
                }
            }
        }

        // Refresh course metadata indexes for search/reporting.
        if ( class_exists( 'FLMS_Course_Manager' ) ) {
            $manager = new FLMS_Course_Manager();
            $manager->update_course_query_metadata( $course_id );
        }

        $result['updated'] = true;

        return $result;
    }
}



