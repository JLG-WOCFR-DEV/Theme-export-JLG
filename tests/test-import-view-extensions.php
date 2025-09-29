<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-import.php';

/**
 * @group import-view
 */
class Test_Import_View_Extensions extends WP_UnitTestCase {

    public function test_import_tab_lists_configured_extensions(): void {
        $config = TEJLG_Import::get_import_file_types();

        $admin  = new TEJLG_Admin();
        $method = new ReflectionMethod(TEJLG_Admin::class, 'render_import_tab');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($admin);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertNotSame('', $output);

        preg_match_all('/\(\.[A-Za-z0-9.,\s]+\)/', $output, $matches);
        $found_mentions = isset($matches[0]) ? array_values(array_unique($matches[0])) : [];

        $expected_mentions = [];

        foreach ($config as $config_entry) {
            if (!isset($config_entry['extensions']) || !is_array($config_entry['extensions'])) {
                continue;
            }

            $extensions = [];

            foreach ($config_entry['extensions'] as $extension) {
                $extension = '.' . ltrim(strtolower((string) $extension), '.');

                if ('.' === $extension) {
                    continue;
                }

                $extensions[] = $extension;
            }

            if (empty($extensions)) {
                continue;
            }

            $extensions         = array_values(array_unique($extensions));
            $expected_mentions[] = '(' . implode(', ', $extensions) . ')';
        }

        $expected_mentions = array_values(array_unique($expected_mentions));

        sort($expected_mentions);
        sort($found_mentions);

        $this->assertSame($expected_mentions, $found_mentions, 'The import tab should only mention configured extensions.');

        $inputs_to_types = [
            'theme_zip'          => 'theme',
            'patterns_json'      => 'patterns',
            'global_styles_json' => 'global_styles',
        ];

        foreach ($inputs_to_types as $input_id => $type) {
            $accept_value = TEJLG_Import::get_accept_attribute_value($type);

            $this->assertStringContainsString(
                sprintf('id="%s"', $input_id),
                $output,
                sprintf('Missing expected input with id %s in the import tab.', $input_id)
            );

            $this->assertStringContainsString(
                sprintf('accept="%s"', esc_attr($accept_value)),
                $output,
                sprintf('Input %s should use the configured accept attribute.', $input_id)
            );
        }
    }
}

