<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Installer
{
    public static function activate(): void
    {
        WP_PQ_Roles::register_roles_and_caps();
        WP_PQ_DB::create_tables();
        WP_PQ_DB::ensure_default_billing_buckets();
        self::set_default_options();
        WP_PQ_Housekeeping::schedule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        WP_PQ_Housekeeping::unschedule();
        flush_rewrite_rules();
    }

    private static function set_default_options(): void
    {
        add_option('wp_pq_max_upload_mb', 1024);
        add_option('wp_pq_retention_days', 365);
        add_option('wp_pq_retention_reminder_day', 300);
        add_option('wp_pq_file_version_limit', 3);
        add_option('wp_pq_google_client_id', '');
        add_option('wp_pq_google_client_secret', '');
        add_option('wp_pq_google_redirect_uri', home_url('/wp-json/pq/v1/google/oauth/callback'));
        add_option('wp_pq_google_scopes', 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly');
    }
}
