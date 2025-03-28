<?php
class DSB_Settings {
    private static $option_name = 'dsb_global_settings';

    public static function get_settings() {
        return wp_parse_args(
            get_option(self::$option_name, []),
            [
                'cancelation_time_hours' => 24,
                'daily_limit' => 2,
                'class_cost' => 1,
            ]
        );
    }

    public static function get($key) {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : null;
    }

    public static function update($key, $value) {
        $settings = self::get_settings();
        $settings[$key] = $value;
        update_option(self::$option_name, $settings);
    }
}
