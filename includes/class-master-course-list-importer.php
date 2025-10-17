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

            $credit_fields   = Master_Course_List_Data::get_credit_fields();
            $metadata_fields = Master_Course_List_Data::get_metadata_fields();
            $extra_columns   = array(
                'notes'      => __( 'Notes', 'master-course-list' ),
                'word_count' => __( 'Word count', 'master-course-list' ),
            );
            $price_keys = array();

            foreach ( $result['preview'] as $row ) {
                if ( isset( $row['extras']['prices'] ) && is_array( $row['extras']['prices'] ) ) {
                    foreach ( $row['extras']['prices'] as $key => $value ) {
                        if ( '' === $value ) {
                            continue;
                        }
                        $price_keys[ $key ] = true;
                    }
                }
            }

            echo '<table class="widefat striped">';
            echo '<thead><tr>';
                echo '<th>' . esc_html__( 'Row', 'master-course-list' ) . '</th>';
                echo '<th>' . esc_html__( 'Course #', 'master-course-list' ) . '</th>';
                echo '<th>' . esc_html__( 'Title', 'master-course-list' ) . '</th>';

                foreach ( $credit_fields as $slug => $label ) {
                    echo '<th>' . esc_html( sprintf( __( '%s Credits', 'master-course-list' ), $label ) ) . '</th>';
                }

                foreach ( $metadata_fields as $slug => $definition ) {
                    $meta_label = isset( $definition['label'] ) ? $definition['label'] : $slug;
                    echo '<th>' . esc_html( $meta_label ) . '</th>';
                }

                foreach ( $extra_columns as $key => $label ) {
                    echo '<th>' . esc_html( $label ) . '</th>';
                }

                foreach ( array_keys( $price_keys ) as $key ) {
                    echo '<th>' . esc_html( $this->format_price_label( $key ) ) . '</th>';
                }

            echo '</tr></thead>';
            echo '<tbody>';
            foreach ( $result['preview'] as $row ) {
                echo '<tr>';
                    echo '<td>' . intval( $row['index'] ) . '</td>';
                    echo '<td>' . esc_html( $row['course_number'] ) . '</td>';
                    echo '<td>' . esc_html( $row['title'] ) . '</td>';

                    foreach ( $credit_fields as $slug => $label ) {
                        $value  = isset( $row['credits'][ $slug ] ) ? $row['credits'][ $slug ] : '';
                        $number = isset( $row['credit_numbers'][ $slug ] ) ? $row['credit_numbers'][ $slug ] : '';

                        $display = '';
                        if ( '' !== $value ) {
                            $display = $value;
                        }
                        if ( '' !== $number ) {
                            $display = ( '' !== $display )
                                ? sprintf( '%s (%s)', $display, $number )
                                : $number;
                        }

                        echo '<td>' . ( '' === $display ? '&mdash;' : esc_html( $display ) ) . '</td>';
                    }

                    foreach ( $metadata_fields as $slug => $definition ) {
                        $value = isset( $row['metadata'][ $slug ] ) ? $row['metadata'][ $slug ] : '';
                        echo '<td>' . ( '' === $value ? '&mdash;' : esc_html( $value ) ) . '</td>';
                    }

                    foreach ( $extra_columns as $extra_key => $label ) {
                        switch ( $extra_key ) {
                            case 'notes':
                                $extra_value = isset( $row['extras']['notes'] ) ? $row['extras']['notes'] : '';
                                break;
                            case 'word_count':
                                $extra_value = isset( $row['extras']['word_count'] ) ? $row['extras']['word_count'] : '';
                                break;
                            default:
                                $extra_value = '';
                                break;
                        }

                        echo '<td>' . ( '' === $extra_value ? '&mdash;' : esc_html( $extra_value ) ) . '</td>';
                    }

                    foreach ( array_keys( $price_keys ) as $key ) {
                        $price_value = isset( $row['extras']['prices'][ $key ] ) ? $row['extras']['prices'][ $key ] : '';
                        echo '<td>' . ( '' === $price_value ? '&mdash;' : esc_html( $price_value ) ) . '</td>';
                    }

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
                $warningsaid