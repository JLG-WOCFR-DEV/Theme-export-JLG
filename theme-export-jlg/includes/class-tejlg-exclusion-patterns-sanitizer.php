<?php

class TEJLG_Exclusion_Patterns_Sanitizer {
    const DEFAULT_MAX_PATTERNS = 200;
    const DEFAULT_MAX_PATTERN_LENGTH = 255;

    /**
     * @param string|array $raw_patterns
     * @param int|null $max_patterns
     * @param int|null $max_pattern_length
     * @return array<int, string>
     */
    public static function sanitize_list($raw_patterns, $max_patterns = null, $max_pattern_length = null) {
        $max_patterns       = self::normalize_positive_int($max_patterns, self::DEFAULT_MAX_PATTERNS);
        $max_pattern_length = self::normalize_positive_int($max_pattern_length, self::DEFAULT_MAX_PATTERN_LENGTH);

        if (is_string($raw_patterns)) {
            $candidates = preg_split('/[,\r\n]+/', $raw_patterns);
        } elseif (is_array($raw_patterns)) {
            $candidates = $raw_patterns;
        } else {
            $candidates = [];
        }

        if (!is_array($candidates)) {
            $candidates = [];
        }

        $sanitized = [];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) || is_object($candidate)) {
                continue;
            }

            $pattern = (string) $candidate;

            if (function_exists('wp_check_invalid_utf8')) {
                $pattern = wp_check_invalid_utf8($pattern, true);
            }

            if (function_exists('wp_strip_all_tags')) {
                $pattern = wp_strip_all_tags($pattern);
            } else {
                $pattern = strip_tags($pattern);
            }
            $pattern = preg_replace('/[\x00-\x1F\x7F]/', '', $pattern);

            if (!is_string($pattern)) {
                continue;
            }

            $pattern = trim($pattern);
            $pattern = preg_replace('#^[\\/]+#', '', $pattern);

            if (!is_string($pattern)) {
                continue;
            }

            $normalized_for_segments = str_replace('\\', '/', $pattern);

            if (preg_match('#(?:^|/)\.\.(?:/|$)#', $normalized_for_segments)) {
                continue;
            }

            if ('' === $pattern) {
                continue;
            }

            if (function_exists('mb_substr')) {
                $pattern = mb_substr($pattern, 0, $max_pattern_length);
            } else {
                $pattern = substr($pattern, 0, $max_pattern_length);
            }

            if (!is_string($pattern)) {
                continue;
            }

            if ('' === $pattern) {
                continue;
            }

            if (in_array($pattern, $sanitized, true)) {
                continue;
            }

            $sanitized[] = $pattern;

            if (count($sanitized) >= $max_patterns) {
                break;
            }
        }

        return $sanitized;
    }

    /**
     * @param string|array $raw_patterns
     * @param int|null     $max_patterns
     * @param int|null     $max_pattern_length
     *
     * @return string
     */
    public static function sanitize_string($raw_patterns, $max_patterns = null, $max_pattern_length = null) {
        $patterns = self::sanitize_list($raw_patterns, $max_patterns, $max_pattern_length);

        if (empty($patterns)) {
            return '';
        }

        return implode("\n", $patterns);
    }

    private static function normalize_positive_int($value, $default) {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_numeric($value)) {
            $value = (int) $value;

            if ($value > 0) {
                return $value;
            }
        }

        return $default;
    }
}
