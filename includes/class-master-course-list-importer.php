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

            return;}