<?php

require_once dirname( __DIR__ ) . '/theme-export-jlg/theme-export-jlg.php';

/**
 * @group theme-tools
 */
class Test_Theme_Tools extends WP_UnitTestCase {

    public function test_create_child_theme_resumes_after_credentials_prompt() {
        $admin_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user( $admin_id );

        $child_name     = 'Integration Child ' . wp_generate_password( 6, false );
        $button_value   = 'Créer le Thème Enfant';
        $nonce_value    = wp_create_nonce( 'tejlg_create_child_action' );
        $expected_url   = admin_url( 'admin.php?page=theme-export-jlg&tab=export' );
        $original_post  = $_POST;
        $_POST['tejlg_create_child_nonce'] = $nonce_value;
        $_POST['child_theme_name']         = $child_name;
        $_POST['tejlg_create_child']       = $button_value;

        $attempts = 0;

        $credentials_filter = function ( $credentials, $form_post, $type, $error, $context, $extra_fields ) use ( &$attempts, $expected_url, $nonce_value, $child_name, $button_value ) {
            $attempts++;

            if ( 1 === $attempts ) {
                $this->assertSame( $expected_url, $form_post, 'Filesystem form should return to the export tools tab.' );
                $this->assertIsArray( $extra_fields );
                $this->assertArrayHasKey( 'tejlg_create_child_nonce', $extra_fields );
                $this->assertArrayHasKey( 'child_theme_name', $extra_fields );
                $this->assertArrayHasKey( 'tejlg_create_child', $extra_fields );
                $this->assertSame( $nonce_value, $extra_fields['tejlg_create_child_nonce'] );
                $this->assertSame( $child_name, $extra_fields['child_theme_name'] );
                $this->assertSame( $button_value, $extra_fields['tejlg_create_child'] );

                return false;
            }

            return '';
        };

        add_filter( 'request_filesystem_credentials', $credentials_filter, 10, 7 );

        $filesystem_method_filter = static function () {
            return 'direct';
        };

        add_filter( 'filesystem_method', $filesystem_method_filter, 10, 4 );

        $child_slug = sanitize_title( $child_name );
        $child_dir  = trailingslashit( get_theme_root() ) . $child_slug;

        if ( is_dir( $child_dir ) ) {
            $this->remove_directory( $child_dir );
        }

        global $wp_settings_errors;
        $previous_settings_errors = $wp_settings_errors ?? [];
        $wp_settings_errors = [];

        try {
            TEJLG_Theme_Tools::create_child_theme( $child_name );

            $this->assertSame( 1, $attempts, 'The filesystem credentials prompt should be requested once.' );
            $this->assertFalse( is_dir( $child_dir ), 'The child theme directory should not be created before credentials are provided.' );
            $this->assertSame( [], get_settings_errors( 'tejlg_admin_messages' ), 'No blocking error should be added when prompting for credentials.' );

            TEJLG_Theme_Tools::create_child_theme( $child_name );

            $this->assertSame( 2, $attempts, 'The filesystem prompt should be bypassed after credentials submission.' );
            $this->assertTrue( is_dir( $child_dir ), 'The child theme directory should be created after credentials are provided.' );
            $this->assertFileExists( $child_dir . '/style.css' );
            $this->assertFileExists( $child_dir . '/functions.php' );
        } finally {
            remove_filter( 'request_filesystem_credentials', $credentials_filter, 10 );
            remove_filter( 'filesystem_method', $filesystem_method_filter, 10 );
            wp_set_current_user( 0 );
            $_POST = $original_post;
            $wp_settings_errors = $previous_settings_errors;

            if ( is_dir( $child_dir ) ) {
                $this->remove_directory( $child_dir );
            }
        }
    }

    private function remove_directory( $directory ) {
        if ( ! is_dir( $directory ) ) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $item ) {
            if ( $item->isDir() ) {
                rmdir( $item->getPathname() );
            } else {
                unlink( $item->getPathname() );
            }
        }

        rmdir( $directory );
    }
}

