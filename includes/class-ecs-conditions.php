<?php
/**
 * ECS Conditions - Conditional Snippet Execution System
 *
 * @package ECS
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ECS;

/**
 * Conditions class for evaluating snippet execution rules
 *
 * Determines whether a snippet should run based on various conditions:
 * - Page/Post Type
 * - User Role
 * - Logged-in Status
 * - Device Type
 * - Date/Time
 * - URL Patterns
 */
class Conditions
{
    /**
     * Check if a snippet should run based on its conditions
     *
     * @param array $snippet Snippet data with conditions
     * @return bool True if snippet should run, false otherwise
     */
    public static function should_run(array $snippet): bool
    {
        // If snippet is not active, don't run
        if (empty($snippet['active']) || !$snippet['active']) {
            return false;
        }

        // If no conditions are set, run everywhere
        if (empty($snippet['conditions'])) {
            return true;
        }

        // Parse conditions
        $conditions = self::parse_conditions($snippet['conditions']);

        // If parsing failed or empty, run everywhere
        if (empty($conditions)) {
            return true;
        }

        // Check each condition type
        $checks = [
            'page_type'    => self::check_page_type($conditions),
            'post_type'    => self::check_post_type($conditions),
            'user_role'    => self::check_user_role($conditions),
            'login_status' => self::check_login_status($conditions),
            'device_type'  => self::check_device_type($conditions),
            'url_pattern'  => self::check_url_pattern($conditions),
            'date_range'   => self::check_date_range($conditions),
        ];

        // Log condition checks in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ECS Conditions] Snippet: ' . $snippet['title']);
            error_log('[ECS Conditions] Checks: ' . print_r($checks, true));
        }

        // All enabled conditions must pass (AND logic)
        foreach ($checks as $check_name => $result) {
            if ($result === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[ECS Conditions] Failed check: {$check_name}");
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Parse conditions from JSON or array
     *
     * @param mixed $conditions Conditions data
     * @return array Parsed conditions
     */
    private static function parse_conditions($conditions): array
    {
        if (is_string($conditions)) {
            $decoded = json_decode($conditions, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($conditions) ? $conditions : [];
    }

    /**
     * Check page type conditions (home, front_page, archive, single, etc.)
     *
     * @param array $conditions Conditions array
     * @return bool|null True if passes, false if fails, null if not set
     */
    private static function check_page_type(array $conditions): ?bool
    {
        if (empty($conditions['page_type']) || !is_array($conditions['page_type'])) {
            return null; // Not set, skip check
        }

        $page_types = $conditions['page_type'];

        foreach ($page_types as $type) {
            switch ($type) {
                case 'home':
                    if (is_home()) {
                        return true;
                    }
                    break;

                case 'front_page':
                    if (is_front_page()) {
                        return true;
                    }
                    break;

                case 'single':
                    if (is_single()) {
                        return true;
                    }
                    break;

                case 'page':
                    if (is_page()) {
                        return true;
                    }
                    break;

                case 'archive':
                    if (is_archive()) {
                        return true;
                    }
                    break;

                case 'search':
                    if (is_search()) {
                        return true;
                    }
                    break;

                case '404':
                    if (is_404()) {
                        return true;
                    }
                    break;

                case 'admin':
                    if (is_admin()) {
                        return true;
                    }
                    break;
            }
        }

        return false; // None matched
    }

    /**
     * Check post type conditions
     *
     * @param array $conditions Conditions array
     * @return bool|null True if passes, false if fails, null if not set
     */
    private static function check_post_type(array $conditions): ?bool
    {
        if (empty($conditions['post_type']) || !is_array($conditions['post_type'])) {
            return null; // Not set, skip check
        }

        $current_post_type = get_post_type();

        if (!$current_post_type) {
            return false;
        }

        return in_array($current_post_type, $conditions['post_type'], true);
    }

    /**
     * Check user role conditions
     *
     * @param array $conditions Conditions array
     * @return bool|null True if passes, false if fails, null if not set
     */
    private static function check_user_role(array $conditions): ?bool
    {
        if (empty($conditions['user_role']) || !is_array($conditions['user_role'])) {
            return null; // Not set, skip check
        }

        $user = wp_get_current_user();

        if (!$user || !$user->exists()) {
            // Check if 'guest' is in allowed roles
            return in_array('guest', $conditions['user_role'], true);
        }

        $user_roles = (array) $user->roles;

        // Check if any user role matches allowed roles
        foreach ($user_roles as $role) {
            if (in_array($role, $conditions['user_role'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check login status conditions
     *
     * @param array $conditions Conditions array
     * @return bool|null True if passes, false if fails, null if not set
     */
    private static function check_login_status(array $conditions): ?bool
    {
        if (!isset($conditions['login_status'])) {
            return null; // Not set, skip check
        }

        $is_logged_in = is_user_logged_in();

        if ($conditions['login_status'] === 'logged_in') {
            return $is_logged_in;
        } elseif ($conditions['login_status'] === 'logged_out') {
            return !$is_logged_in;
        }

        return null; // Invalid value
    }

    /**
     * Check device type conditions (mobile, tablet, desktop)
     *
     * @param array $conditions Conditions array
     * @return bool|null True if passes, false if fails, null if not set
     */
    private static function check_device_type(array $conditions): ?bool
    {
        if (empty($conditions['device_type']) || !is_array($conditions['device_type'])) {
            return null; // Not set, skip check
        }

        $device_types = $conditions['device_type'];

        // Check for mobile
        if (in_array('mobile', $device_types, true) && wp_is_mobile()) {
            return true;
        }

        // Check for desktop (not mobile)
        if (in_array('desktop', $device_types, true) && !wp_is_mobile()) {
            return true;
        }

        return false;
    }

    /**
     * Check URL pattern conditions
     *
     * @param array $conditions Conditions array
     * @return bool|null True if passes, false if fails, null if not set
     */
    private static function check_url_pattern(array $conditions): ?bool
    {
        if (empty($conditions['url_pattern'])) {
            return null; // Not set, skip check
        }

        $current_url = self::get_current_url();
        $patterns = is_array($conditions['url_pattern']) 
            ? $conditions['url_pattern'] 
            : [$conditions['url_pattern']];

        foreach ($patterns as $pattern) {
            // Convert wildcard to regex
            $regex = str_replace(['*', '/'], ['.*', '\/'], $pattern);
            $regex = '/^' . $regex . '$/i';

            if (preg_match($regex, $current_url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check date range conditions
     *
     * @param array $conditions Conditions array
     * @return bool|null True if passes, false if fails, null if not set
     */
    private static function check_date_range(array $conditions): ?bool
    {
        if (empty($conditions['date_from']) && empty($conditions['date_to'])) {
            return null; // Not set, skip check
        }

        $now = current_time('timestamp');

        // Check start date
        if (!empty($conditions['date_from'])) {
            $date_from = strtotime($conditions['date_from']);
            if ($now < $date_from) {
                return false;
            }
        }

        // Check end date
        if (!empty($conditions['date_to'])) {
            $date_to = strtotime($conditions['date_to']);
            if ($now > $date_to) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get current URL
     *
     * @return string Current URL
     */
    private static function get_current_url(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return $protocol . '://' . $host . $uri;
    }

    /**
     * Get available page types
     *
     * @return array Available page types with labels
     */
    public static function get_page_types(): array
    {
        return [
            'home'       => __('Blog Home', 'wp-smart-code'),
            'front_page' => __('Front Page', 'wp-smart-code'),
            'single'     => __('Single Post', 'wp-smart-code'),
            'page'       => __('Page', 'wp-smart-code'),
            'archive'    => __('Archive', 'wp-smart-code'),
            'search'     => __('Search Results', 'wp-smart-code'),
            '404'        => __('404 Error', 'wp-smart-code'),
            'admin'      => __('Admin Area', 'wp-smart-code'),
        ];
    }

    /**
     * Get available user roles
     *
     * @return array Available user roles with labels
     */
    public static function get_user_roles(): array
    {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }

        $roles = ['guest' => __('Guest (Not Logged In)', 'wp-smart-code')];

        foreach ($wp_roles->roles as $role_key => $role) {
            $roles[$role_key] = $role['name'];
        }

        return $roles;
    }

    /**
     * Get available device types
     *
     * @return array Available device types with labels
     */
    public static function get_device_types(): array
    {
        return [
            'mobile'  => __('Mobile', 'wp-smart-code'),
            'desktop' => __('Desktop', 'wp-smart-code'),
        ];
    }

    /**
     * Get available login statuses
     *
     * @return array Available login statuses with labels
     */
    public static function get_login_statuses(): array
    {
        return [
            'logged_in'  => __('Logged In', 'wp-smart-code'),
            'logged_out' => __('Logged Out', 'wp-smart-code'),
        ];
    }

    /**
     * Validate conditions array
     *
     * @param array $conditions Conditions to validate
     * @return array Validated conditions
     */
    public static function validate_conditions(array $conditions): array
    {
        $validated = [];

        // Validate page_type
        if (isset($conditions['page_type']) && is_array($conditions['page_type'])) {
            $valid_page_types = array_keys(self::get_page_types());
            $validated['page_type'] = array_intersect($conditions['page_type'], $valid_page_types);
        }

        // Validate post_type
        if (isset($conditions['post_type']) && is_array($conditions['post_type'])) {
            $post_types = get_post_types(['public' => true], 'names');
            $validated['post_type'] = array_intersect($conditions['post_type'], $post_types);
        }

        // Validate user_role
        if (isset($conditions['user_role']) && is_array($conditions['user_role'])) {
            $valid_roles = array_keys(self::get_user_roles());
            $validated['user_role'] = array_intersect($conditions['user_role'], $valid_roles);
        }

        // Validate login_status
        if (isset($conditions['login_status'])) {
            $valid_statuses = array_keys(self::get_login_statuses());
            if (in_array($conditions['login_status'], $valid_statuses, true)) {
                $validated['login_status'] = $conditions['login_status'];
            }
        }

        // Validate device_type
        if (isset($conditions['device_type']) && is_array($conditions['device_type'])) {
            $valid_devices = array_keys(self::get_device_types());
            $validated['device_type'] = array_intersect($conditions['device_type'], $valid_devices);
        }

        // Validate url_pattern
        if (isset($conditions['url_pattern'])) {
            if (is_array($conditions['url_pattern'])) {
                $validated['url_pattern'] = array_map('sanitize_text_field', $conditions['url_pattern']);
            } else {
                $validated['url_pattern'] = sanitize_text_field($conditions['url_pattern']);
            }
        }

        // Validate date_from and date_to
        if (isset($conditions['date_from'])) {
            $validated['date_from'] = sanitize_text_field($conditions['date_from']);
        }

        if (isset($conditions['date_to'])) {
            $validated['date_to'] = sanitize_text_field($conditions['date_to']);
        }

        return $validated;
    }
}

