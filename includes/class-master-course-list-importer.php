<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Master_Course_List_Importer {

    /**
     * Result of the most recent upload attempt.
     *
     * @var array|null
     */
    private $last_result = null;

    /**
     * Whether the current run is a dry run.
     *
     * @var bool
     */
    private $dry_run = true;

    /**
     * Handle current request (if any) and capture result for rendering.
     */
    public function handle_request() {
        if ( empty( $_POST['mcl_import_nonce'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->last_result = array(
                'success' => false,
                'messages' => array( __( 'You do not have permission to run imports.', 'master-course-list' ) ),
            );
            return;
        }

        if ( ! wp_verify_nonce( wp_unslash( $_POST['mcl_import_nonce'] ), 'master-course-list-import' ) ) {
            $this->last_result = array(
                'success' => false,
                'messages' => array( __( 'Security check failed. Please try again.', 'master-course-list' ) ),
            );
            return;
        }

        if ( empty( $_FILES['mcl_import_file'] ) || ! isset( $_FILES['mcl_import_file']['tmp_name'] ) ) {
            $this->last_result = array(
                'success'  => false,
                'messages' => array( __( 'Please select a CSV file to upload.', 'master-course-list' ) ),
            );
            return;
        }

        $this->dry_run     = isset( $_POST['mcl_dry_run'] );
        $this->last_result = $this->process_upload( $_FILES['mcl_import_file'], $this->dry_run );
    }

    /**
     * Render the importer page.
     */
    public function render_page() {
        $this->handle_request();

        echo '<div class="wrap master-course-list-import">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Import Master Course List', 'master-course-list' ) . '</h1>';

        echo '<p>' . esc_html__( 'Upload a CSV export of the master course spreadsheet to preview how fields map into the LMS. Run a dry preview first, then uncheck "Dry run" to apply updates.', 'master-course-list' ) . '</p>';

        $this->render_upload_form();

        if ( null !== $this->last_result ) {
            $this->render_result( $this->last_result );
        }

        echo '</div>';
    }

    /**
     * Output upload form.
     */
    private function render_upload_form() {
        echo '<form method="post" enctype="multipart/form-data" class="mcl-import-form">';
        wp_nonce_field( 'master-course-list-import', 'mcl_import_nonce' );

        echo '<table class="form-table" role="presentation">';
        echo '<tr>'; // File row.
            echo '<th scope="row"><label for="mcl_import_file">' . esc_html__( 'CSV file', 'master-course-list' ) . '</label></th>';
            echo '<td><input type="file" name="mcl_import_file" id="mcl_import_file" accept=".csv,text/csv" required /></td>';
        echo '</tr>';
        echo '<tr>';
            echo '<th scope="row">' . esc_html__( 'Dry run', 'master-course-list' ) . '</th>';
            $checked = $this->dry_run ? ' checked' : '';
            echo '<td><label><input type="checkbox" name="mcl_dry_run" value="1"' . $checked . ' /> ' . esc_html__( 'Preview only (no data will be saved).', 'master-course-list' ) . '</label>';
            echo '<p class="description">' . esc_html__( 'Uncheck to apply changes directly to matching courses. Only do this after verifying the preview results.', 'master-course-list' ) . '</p>';
            echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button( __( 'Preview import', 'master-course-list' ) );

        echo '</form>';
    }

    /**
     * Render upload result summary.
     *
     * @param array $result Result data from process_upload().
     */
    private function render_result( $result ) {
        $success  = ! empty( $result['success'] );
        $dry_run  = isset( $result['dry_run'] ) ? (bool) $result['dry_run'] : true;
        $messages = isset( $result['messages'] ) ? (array) $result['messages'] : array();

        $notice_class = $success ? 'notice-success' : 'notice-error';

        if ( empty( $messages ) ) {
            $messages[] = $success ? __( 'File processed successfully.', 'master-course-list' ) : __( 'Import failed.', 'master-course-list' );
        }

        echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible">';
        foreach ( $messages as $message ) {
            echo '<p>' . esc_html( $message ) . '</p>';
        }
        echo '</div>';

        if ( ! $success ) {
            return;
        }

        if ( ! empty( $result['summary'] ) && is_array( $result['summary'] ) ) {
            $this->render_summary( $result['summary'], $dry_run );
        }

        if ( ! empty( $result['warnings'] ) ) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html__( 'Warnings', 'master-course-list' ) . '</strong></p>';
            echo '<ul>';
            foreach ( (array) $result['warnings'] as $warning ) {
                echo '<li>' . esc_html( $warning ) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if ( ! empty( $result['mapping'] ) ) {
            echo '<h2>' . esc_html__( 'Detected columns', 'master-course-list' ) . '</h2>';
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
                echo '<th>' . esc_html__( 'Column header', 'master-course-list' ) . '</th>';
                echo '<th>' . esc_html__( 'Mapped to', 'master-course-list' ) . '</th>';
                echo '<th>' . esc_html__( 'Notes', 'master-course-list' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ( $result['mapping'] as $map ) {
                $label = isset( $map['label'] ) ? $map['label'] : __( 'Unknown', 'master-course-list' );
                $notes = isset( $map['type'] ) ? $this->describe_mapping_type( $map['type'] ) : '';
                if ( ! empty( $map['warning'] ) ) {
                    $notes .= ' ' . $map['warning'];
                }
                echo '<tr>';
                    echo '<td>' . esc_html( $map['original'] ) . '</td>';
                    echo '<td>' . esc_html( $label ) . '</td>';
                    echo '<td>' . esc_html( trim( $notes ) ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }

        if ( ! empty( $result['preview'] ) ) {
            echo '<h2>' . esc_html__( 'Preview', 'master-course-list' ) . '</h2>';
            echo '<p>' . esc_html__( 'First five rows are shown below with recognized values.', 'master-course-list' ) . '</p>';

            echo '<table class="widefat striped">';
            echo '<thead><tr>';
                echo '<th>' . esc_html__( 'Row', 'master-course-list' ) . '</th>';
                echo '<th>' . esc_html__( 'Course #', 'master-course-list' ) . '</th>';
                echo '<th>' . esc_html__( 'Title', 'master-course-list' ) . '</th>';
                echo '<th>' . esc_html__( 'Credits', 'master-course-list' ) . '</th>';
                echo '<th>' . esc_html__( 'Metadata', 'master-course-list' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ( $result['preview'] as $row ) {
                echo '<tr>';
                    echo '<td>' . intval( $row['index'] ) . '</td>';
                    echo '<td>' . esc_html( $row['course_number'] ) . '</td>';
                    echo '<td>' . esc_html( $row['title'] ) . '</td>';
                    echo '<td>' . esc_html( $this->format_credit_preview( $row ) ) . '</td>';
                    echo '<td>' . esc_html( $this->format_metadata_preview( $row ) ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }

        if ( $dry_run ) {
            echo '<p class="description">' . esc_html__( 'Dry run complete: no changes were saved. Review the summary above, then uncheck "Dry run" to commit updates.', 'master-course-list' ) . '</p>';
        } else {
            echo '<p class="description">' . esc_html__( 'Import complete. Matching courses have been updated with the values from the uploaded file.', 'master-course-list' ) . '</p>';
        }
    }

    /**
     * Describe mapping type.
     */
    private function describe_mapping_type( $type ) {
        switch ( $type ) {
            case 'credit':
                return __( 'Credit value', 'master-course-list' );
            case 'credit_number':
                return __( 'Credit-specific course number', 'master-course-list' );
            case 'metadata':
                return __( 'Course metadata', 'master-course-list' );
            case 'course_number':
                return __( 'Primary course number', 'master-course-list' );
            case 'title':
                return __( 'Course title', 'master-course-list' );
            case 'notes':
                return __( 'Course notes', 'master-course-list' );
            case 'word_count':
                return __( 'Word count', 'master-course-list' );
            case 'price':
                return __( 'Pricing data', 'master-course-list' );
            default:
                return __( 'Not mapped', 'master-course-list' );
        }
    }

    /**
     * Normalize course numbers for duplicate detection and lookups.
     */
    private function normalize_course_number_value( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }

        $value = strtoupper( $value );
        $value = ltrim( $value, '#' );
        $value = preg_replace( '/[^A-Z0-9\-]/', '', $value );

        return $value;
    }

    /**
     * Render headline summary list.
     */
    private function render_summary( array $summary, $dry_run ) {
        echo '<h2>' . esc_html__( 'Summary', 'master-course-list' ) . '</h2>';

        $rows_total      = isset( $summary['total_rows'] ) ? (int) $summary['total_rows'] : 0;
        $with_numbers    = isset( $summary['rows_with_course_number'] ) ? (int) $summary['rows_with_course_number'] : 0;
        $missing_numbers = isset( $summary['rows_missing_course_number'] ) ? (int) $summary['rows_missing_course_number'] : 0;
        $matched         = isset( $summary['matched_courses'] ) ? (int) $summary['matched_courses'] : 0;
        $not_found       = isset( $summary['courses_not_found'] ) ? (int) $summary['courses_not_found'] : 0;
        $updates         = isset( $summary['updates_applied'] ) ? (int) $summary['updates_applied'] : 0;

        echo '<ul class="mcl-import-summary">';
            echo '<li>' . esc_html( sprintf( __( 'Rows processed: %d', 'master-course-list' ), $rows_total ) ) . '</li>';
            echo '<li>' . esc_html( sprintf( __( 'Rows with course numbers: %d', 'master-course-list' ), $with_numbers ) ) . '</li>';
            if ( $missing_numbers > 0 ) {
                echo '<li>' . esc_html( sprintf( __( 'Rows missing course numbers: %d', 'master-course-list' ), $missing_numbers ) ) . '</li>';
            }
            echo '<li>' . esc_html( sprintf( __( 'Matching courses found: %d', 'master-course-list' ), $matched ) ) . '</li>';
            if ( $not_found > 0 ) {
                echo '<li>' . esc_html( sprintf( __( 'Rows without a matching course: %d', 'master-course-list' ), $not_found ) ) . '</li>';
            }
            if ( ! $dry_run ) {
                echo '<li>' . esc_html( sprintf( __( 'Courses updated: %d', 'master-course-list' ), $updates ) ) . '</li>';
            }
        echo '</ul>';

        if ( ! empty( $summary['duplicate_numbers'] ) ) {
            echo '<p><strong>' . esc_html__( 'Duplicate course numbers detected:', 'master-course-list' ) . '</strong></p>';
            echo '<ul class="mcl-import-duplicates">';
            foreach ( $summary['duplicate_numbers'] as $number => $rows ) {
                $rows_list = implode( ', ', array_map( 'intval', (array) $rows ) );
                echo '<li>' . esc_html( sprintf( __( '%1$s (rows %2$s)', 'master-course-list' ), $number, $rows_list ) ) . '</li>';
            }
            echo '</ul>';
        }
    }

    /**
     * Format credit summary for preview output.
     */
    private function format_credit_preview( array $row ) {
        $credits        = isset( $row['credits'] ) ? (array) $row['credits'] : array();
        $credit_numbers = isset( $row['credit_numbers'] ) ? (array) $row['credit_numbers'] : array();

        if ( empty( $credits ) && empty( $credit_numbers ) ) {
            return 'N/A';
        }

        $labels = Master_Course_List_Data::get_credit_fields();
        $parts  = array();

        foreach ( $credits as $slug => $value ) {
            $label = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
            $entry = sprintf( '%s: %s', $label, $value );
            if ( isset( $credit_numbers[ $slug ] ) && '' !== $credit_numbers[ $slug ] ) {
                $entry .= sprintf( ' (%s)', $credit_numbers[ $slug ] );
            }
            $parts[] = $entry;
            unset( $credit_numbers[ $slug ] );
        }

        if ( ! empty( $credit_numbers ) ) {
            foreach ( $credit_numbers as $slug => $value ) {
                if ( '' === $value ) {
                    continue;
                }
                $label   = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
                $parts[] = sprintf( '%s (%s)', $label, $value );
            }
        }

        return empty( $parts ) ? 'N/A' : implode( '; ', $parts );
    }

    /**
     * Format metadata/extras for preview output.
     */
    private function format_metadata_preview( array $row ) {
        $metadata = isset( $row['metadata'] ) ? (array) $row['metadata'] : array();
        $extras   = isset( $row['extras'] ) ? (array) $row['extras'] : array();

        $fields = Master_Course_List_Data::get_metadata_fields();
        $parts  = array();

        foreach ( $metadata as $slug => $value ) {
            if ( '' === $value ) {
                continue;
            }
            $label   = isset( $fields[ $slug ]['label'] ) ? $fields[ $slug ]['label'] : $slug;
            $parts[] = sprintf( '%s: %s', $label, $value );
        }

        if ( ! empty( $extras['notes'] ) ) {
            $parts[] = sprintf( '%s: %s', __( 'Notes', 'master-course-list' ), $extras['notes'] );
        }

        if ( ! empty( $extras['word_count'] ) ) {
            $parts[] = sprintf( '%s: %s', __( 'Word count', 'master-course-list' ), $extras['word_count'] );
        }

        if ( isset( $extras['prices'] ) && is_array( $extras['prices'] ) ) {
            foreach ( $extras['prices'] as $key => $value ) {
                if ( '' === $value ) {
                    continue;
                }
                $parts[] = sprintf( '%s: %s', $this->format_price_label( $key ), $value );
            }
        }

        return empty( $parts ) ? 'N/A' : implode( '; ', $parts );
    }

    /**
     * Translate price key into a human friendly label.
     */
    private function format_price_label( $key ) {
        switch ( $key ) {
            case 'price_print':
                return __( 'Print price', 'master-course-list' );
            case 'price_pdf':
                return __( 'PDF price', 'master-course-list' );
            default:
                return __( 'Price', 'master-course-list' );
        }
    }

    /**
     * Process uploaded CSV file and build preview details.
     *
     * @param array $file Uploaded file array from $_FILES.
     * @return array
     */
    private function process_upload( $file, $dry_run ) {
        $upload = wp_handle_upload(
            $file,
            array(
                'test_form' => false,
                'mimes'     => array( 'csv' => 'text/csv', 'txt' => 'text/plain' ),
            )
        );

        if ( isset( $upload['error'] ) ) {
            return array(
                'success'  => false,
                'messages' => array( $upload['error'] ),
            );
        }

        $path = $upload['file'];

        $handle = fopen( $path, 'r' );
        if ( ! $handle ) {
            wp_delete_file( $path );
            return array(
                'success'  => false,
                'messages' => array( __( 'Could not read the uploaded file.', 'master-course-list' ) ),
            );
        }

        $headers = fgetcsv( $handle );
        if ( empty( $headers ) ) {
            fclose( $handle );
            wp_delete_file( $path );
            return array(
                'success'  => false,
                'messages' => array( __( 'The uploaded CSV does not contain headers.', 'master-course-list' ) ),
            );
        }

        $headers = array_map( array( $this, 'normalize_header' ), $headers );
        $mapping = $this->generate_mapping( $headers );

        $preview_rows  = array();
        $preview_limit = 5;
        $row_index     = 1; // header row already consumed.

        $summary = array(
            'dry_run'                   => $dry_run,
            'total_rows'                => 0,
            'rows_with_course_number'   => 0,
            'rows_missing_course_number'=> 0,
            'matched_courses'           => 0,
            'courses_not_found'         => 0,
            'duplicate_numbers'         => array(),
            'updates_applied'           => 0,
        );

        $warnings     = array();
        $seen_numbers = array();
        $sync         = new Master_Course_List_Sync();

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_index++;
            $summary['total_rows']++;

            $parsed_row = $this->parse_row_data( $row_index, $headers, $row, $mapping );

            if ( count( $preview_rows ) < $preview_limit ) {
                $preview_rows[] = $parsed_row;
            }

            if ( '' === $parsed_row['course_number'] ) {
                $summary['rows_missing_course_number']++;
                $warnings[] = sprintf( __( 'Row %d: missing course number.', 'master-course-list' ), $row_index );
                continue;
            }

            $summary['rows_with_course_number']++;

            $normalized_number = $this->normalize_course_number_value( $parsed_row['course_number'] );
            $number_key        = $normalized_number ? $normalized_number : $parsed_row['course_number'];

            if ( ! isset( $seen_numbers[ $number_key ] ) ) {
                $seen_numbers[ $number_key ] = array(
                    'value' => $parsed_row['course_number'],
                    'key'   => $number_key,
                    'rows'  => array( $row_index ),
                );
            } else {
                $seen_numbers[ $number_key ]['rows'][] = $row_index;
                if ( '' === $seen_numbers[ $number_key ]['value'] ) {
                    $seen_numbers[ $number_key ]['value'] = $parsed_row['course_number'];
                }
            }

            $course_id = Master_Course_List_Data::find_course_id_by_number( $parsed_row['course_number'] );
            if ( $course_id ) {
                $summary['matched_courses']++;
                if ( ! $dry_run ) {
                    $apply_result = $sync->apply_row( $course_id, $parsed_row );
                    if ( ! empty( $apply_result['updated'] ) ) {
                        $summary['updates_applied']++;
                    }
                    if ( ! empty( $apply_result['messages'] ) ) {
                        $warnings = array_merge( $warnings, $apply_result['messages'] );
                    }
                }
            } else {
                $summary['courses_not_found']++;
                $warnings[] = sprintf( __( 'Row %1$d: course number %2$s not found.', 'master-course-list' ), $row_index, $parsed_row['course_number'] );
            }
        }

        fclose( $handle );
        wp_delete_file( $path );

        foreach ( $seen_numbers as $entry ) {
            if ( count( $entry['rows'] ) > 1 ) {
                $display_number = $entry['value'];
                if ( '' === $display_number && ! empty( $entry['key'] ) ) {
                    $display_number = $entry['key'];
                }
                $summary['duplicate_numbers'][ $display_number ] = $entry['rows'];
            }
        }

        $warnings = array_slice( array_unique( $warnings ), 0, 20 );

        $message = $dry_run
            ? __( 'Preview generated. Review detected columns and summary before running a full import.', 'master-course-list' )
            : __( 'Import completed. Review the summary below for applied updates.', 'master-course-list' );

        return array(
            'success'  => true,
            'messages' => array( $message ),
            'mapping'  => $mapping,
            'preview'  => $preview_rows,
            'summary'  => $summary,
            'warnings' => $warnings,
            'dry_run'  => $dry_run,
        );
    }

    /**
     * Normalize a CSV header for consistent mapping.
     */
    private function normalize_header( $header ) {
        $header = trim( (string) $header );
        $header = preg_replace( '/\s+/', ' ', $header );
        return $header;
    }

    /**
     * Build mapping for normalized headers.
     */
    private function generate_mapping( array $headers ) {
        $mapping = array();

        foreach ( $headers as $header ) {
            $mapping[] = $this->map_header_to_field( $header );
        }

        return $mapping;
    }

    /**
     * Map a single header to a known field definition.
     */
    private function map_header_to_field( $header ) {
        $normalized = strtolower( $header );
        $normalized = preg_replace( '/[^a-z0-9\s#\-\/]/', '', $normalized );
        $slug       = sanitize_title( $normalized );

        $base_map = $this->get_base_column_map();

        if ( isset( $base_map[ $slug ] ) ) {
            return array(
                'original' => $header,
                'label'    => $base_map[ $slug ]['label'],
                'type'     => $base_map[ $slug ]['type'],
                'key'      => $base_map[ $slug ]['key'],
            );
        }

        // Credit fields.
        foreach ( Master_Course_List_Data::get_credit_fields() as $credit_slug => $credit_label ) {
            $candidate_slugs = array(
                sanitize_title( $credit_label ),
                sanitize_title( $credit_label . ' credits' ),
                sanitize_title( $credit_slug ),
                sanitize_title( $credit_slug . ' credits' ),
            );

            if ( in_array( $slug, $candidate_slugs, true ) ) {
                return array(
                    'original' => $header,
                    'label'    => sprintf( __( '%s credits', 'master-course-list' ), $credit_label ),
                    'type'     => 'credit',
                    'key'      => $credit_slug,
                );
            }

            $candidate_number_slugs = array(
                sanitize_title( $credit_label . ' course number' ),
                sanitize_title( $credit_slug . ' course number' ),
            );

            if ( in_array( $slug, $candidate_number_slugs, true ) ) {
                return array(
                    'original' => $header,
                    'label'    => sprintf( __( '%s course number', 'master-course-list' ), $credit_label ),
                    'type'     => 'credit_number',
                    'key'      => $credit_slug,
                );
            }
        }

        // Metadata fields.
        foreach ( Master_Course_List_Data::get_metadata_fields() as $meta_slug => $meta ) {
            $candidate_slugs = array(
                sanitize_title( $meta['label'] ),
                sanitize_title( $meta_slug ),
            );

            if ( in_array( $slug, $candidate_slugs, true ) ) {
                return array(
                    'original' => $header,
                    'label'    => $meta['label'],
                    'type'     => 'metadata',
                    'key'      => $meta_slug,
                );
            }
        }

        $metadata_slug = Master_Course_List_Schema::ensure_metadata_field( $header );

        if ( '' !== $metadata_slug ) {
            return array(
                'original' => $header,
                'label'    => $header,
                'type'     => 'metadata',
                'key'      => $metadata_slug,
            );
        }

        return array(
            'original' => $header,
            'label'    => __( 'Unmapped column', 'master-course-list' ),
            'type'     => 'unknown',
            'key'      => null,
            'warning'  => __( 'No target field detected.', 'master-course-list' ),
        );
    }

    /**
     * Base column map for spreadsheet-specific headers.
     */
    private function get_base_column_map() {
        return array(
            'four-digit'      => array(
                'label' => __( 'Primary course number', 'master-course-list' ),
                'type'  => 'course_number',
                'key'   => 'course_number',
            ),
            'four-digit-id'   => array(
                'label' => __( 'Primary course number', 'master-course-list' ),
                'type'  => 'course_number',
                'key'   => 'course_number',
            ),
            'course'          => array(
                'label' => __( 'Course title', 'master-course-list' ),
                'type'  => 'title',
                'key'   => 'post_title',
            ),
            'course-title'    => array(
                'label' => __( 'Course title', 'master-course-list' ),
                'type'  => 'title',
                'key'   => 'post_title',
            ),
            'notes'           => array(
                'label' => __( 'Internal notes', 'master-course-list' ),
                'type'  => 'notes',
                'key'   => 'notes',
            ),
            'word-count'      => array(
                'label' => __( 'Word count', 'master-course-list' ),
                'type'  => 'word_count',
                'key'   => 'word_count',
            ),
            'words'           => array(
                'label' => __( 'Word count', 'master-course-list' ),
                'type'  => 'word_count',
                'key'   => 'word_count',
            ),
            'price'           => array(
                'label' => __( 'Price', 'master-course-list' ),
                'type'  => 'price',
                'key'   => 'price',
            ),
            'print-price'     -> array(
                'label' => __( 'Print price', 'master-course-list' ),
                'type'  => 'price',
                'key'   => 'price_print',
            ),
            'pdf-price'       => array(
                'label' => __( 'PDF price', 'master-course-list' ),
                'type'  => 'price',
                'key'   => 'price_pdf',
            ),
        );
    }

    /**
     * Build preview row with recognized values.
     */
    private function parse_row_data( $row_index, $headers, $row, $mapping ) {
        $data = array(
            'index'          => $row_index,
            'course_number'  => '',
            'title'          => '',
            'credits'        => array(),
            'credit_numbers' => array(),
            'metadata'       => array(),
            'extras'         => array(
                'notes'      => '',
                'word_count' => '',
                'prices'     => array(),
            ),
        );

        foreach ( $mapping as $index => $map ) {
            if ( ! isset( $row[ $index ] ) ) {
                continue;
            }

            $value = trim( (string) $row[ $index ] );
            if ( '' === $value ) {
                continue;
            }

            switch ( $map['type'] ) {
                case 'course_number':
                    $data['course_number'] = $value;
                    break;
                case 'title':
                    $data['title'] = $value;
                    break;
                case 'credit':
                    if ( isset( $map['key'] ) ) {
                        $data['credits'][ $map['key'] ] = $value;
                    }
                    break;
                case 'credit_number':
                    if ( isset( $map['key'] ) ) {
                        $data['credit_numbers'][ $map['key'] ] = $value;
                    }
                    break;
                case 'metadata':
                    if ( isset( $map['key'] ) ) {
                        $data['metadata'][ $map['key'] ] = $value;
                    }
                    break;
                case 'notes':
                    $data['extras']['notes'] = $value;
                    break;
                case 'word_count':
                    $data['extras']['word_count'] = $value;
                    break;
                case 'price':
                    if ( isset( $map['key'] ) ) {
                        $data['extras']['prices'][ $map['key'] ] = $value;
                    }
                    break;
            }
        }

        return $data;
    }
}
