<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('TEJLG_PATH')) {
    define('TEJLG_PATH', dirname(__DIR__) . '/theme-export-jlg/');
}

require_once TEJLG_PATH . 'includes/class-tejlg-export-connectors.php';

class ExportConnectorsTest extends TestCase {
    public function test_custom_endpoint_injects_bucket_into_host_when_missing() {
        $method = new ReflectionMethod('TEJLG_Export_Connectors', 'build_s3_request_target');
        $method->setAccessible(true);

        $target = $method->invoke(
            null,
            'https://s3.example.test',
            'eu-west-1',
            'my-bucket',
            'exports/theme.zip',
            false
        );

        $this->assertIsArray($target);
        $this->assertSame('my-bucket.s3.example.test', $target['host']);
        $this->assertSame('/exports/theme.zip', $target['uri']);
        $this->assertSame('https://my-bucket.s3.example.test/exports/theme.zip', $target['url']);
    }

    public function test_path_style_custom_endpoint_keeps_bucket_in_path() {
        $method = new ReflectionMethod('TEJLG_Export_Connectors', 'build_s3_request_target');
        $method->setAccessible(true);

        $target = $method->invoke(
            null,
            'https://s3.example.test',
            'eu-west-1',
            'my-bucket',
            'exports/theme.zip',
            true
        );

        $this->assertIsArray($target);
        $this->assertSame('s3.example.test', $target['host']);
        $this->assertStringContainsString('my-bucket', $target['uri']);
        $this->assertStringContainsString('exports/theme.zip', $target['uri']);
    }

    public function test_handle_event_records_skip_for_invalid_payload() {
        $skipped = [];

        add_action(
            'tejlg_export_remote_connector_skipped',
            static function ($payload) use (&$skipped) {
                $skipped[] = $payload;
            }
        );

        TEJLG_Export_Connectors::handle_event('not-an-array', null, [], [], [], false);

        $this->assertNotEmpty($skipped);
        $this->assertSame('invalid_event_payload', $skipped[0]['reason']);
    }
}
