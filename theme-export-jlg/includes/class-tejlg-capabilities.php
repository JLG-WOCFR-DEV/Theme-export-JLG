<?php

class TEJLG_Capabilities {
    const MANAGE_PLUGIN  = 'tejlg_manage_plugin';
    const MANAGE_EXPORTS = 'tejlg_manage_exports';
    const MANAGE_IMPORTS = 'tejlg_manage_imports';
    const MANAGE_DEBUG   = 'tejlg_manage_debug';
    const MANAGE_SETTINGS = 'tejlg_manage_settings';

    /**
     * List of contexts mapped to the capability that should be checked.
     *
     * @var array<string,string>
     */
    private static $context_capabilities = [
        'menu'      => self::MANAGE_PLUGIN,
        'exports'   => self::MANAGE_EXPORTS,
        'imports'   => self::MANAGE_IMPORTS,
        'debug'     => self::MANAGE_DEBUG,
        'settings'  => self::MANAGE_SETTINGS,
        'ajax'      => self::MANAGE_EXPORTS,
        'reports'   => self::MANAGE_DEBUG,
    ];

    /**
     * Default fallback mapping used when custom capabilities are not explicitly granted.
     *
     * @var array<string,string>
     */
    private static $fallback_capabilities = [
        self::MANAGE_PLUGIN   => 'manage_options',
        self::MANAGE_EXPORTS  => 'manage_options',
        self::MANAGE_IMPORTS  => 'manage_options',
        self::MANAGE_DEBUG    => 'manage_options',
        self::MANAGE_SETTINGS => 'manage_options',
    ];

    public static function init() {
        add_filter('map_meta_cap', [ __CLASS__, 'map_meta_cap' ], 10, 4);
    }

    /**
     * Maps the plugin meta capabilities to primitive ones so administrators keep access by default.
     *
     * @param string[] $caps
     * @param string   $cap
     * @param int      $user_id
     * @param mixed[]  $args
     *
     * @return string[]
     */
    public static function map_meta_cap($caps, $cap, $user_id, $args) {
        $fallbacks = apply_filters('tejlg_capability_fallbacks', self::$fallback_capabilities);

        if (isset($fallbacks[$cap])) {
            $fallback = $fallbacks[$cap];

            if (!is_string($fallback) || '' === $fallback) {
                $fallback = 'manage_options';
            }

            return [ $fallback ];
        }

        return $caps;
    }

    /**
     * Returns the capability that should be required for the given context.
     *
     * @param string $context
     *
     * @return string
     */
    public static function get_capability($context) {
        $context = sanitize_key($context);
        $default = isset(self::$context_capabilities[$context])
            ? self::$context_capabilities[$context]
            : self::MANAGE_PLUGIN;

        /**
         * Filters the capability required for a given context inside Theme Export - JLG.
         *
         * @param string $capability Capability name that will be checked with current_user_can().
         * @param string $context    Context identifier (menu, exports, imports, debug, settings, ajax, reports...).
         */
        $capability = apply_filters('tejlg_required_capability_' . $context, $default, $context);
        $capability = apply_filters('tejlg_required_capability', $capability, $context);

        if (!is_string($capability) || '' === $capability) {
            $capability = $default;
        }

        return $capability;
    }

    /**
     * Wrapper around current_user_can() that automatically resolves the capability for a context.
     *
     * @param string $context
     *
     * @return bool
     */
    public static function current_user_can($context) {
        return current_user_can(self::get_capability($context));
    }
}
