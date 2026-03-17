<?php

if (! defined('ABSPATH')) {
    exit;
}

final class WP_PQ_Plugin
{
    private static ?WP_PQ_Plugin $instance = null;

    public static function instance(): WP_PQ_Plugin
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        WP_PQ_DB::create_tables();
        WP_PQ_DB::migrate_legacy_statuses();
        WP_PQ_DB::migrate_task_context_fields();
        WP_PQ_DB::migrate_named_default_buckets();
        WP_PQ_DB::ensure_default_billing_buckets();
        WP_PQ_Housekeeping::init();
        WP_PQ_Admin::init();
        WP_PQ_API::init();
        WP_PQ_Portal::init();

        add_filter('upload_size_limit', [self::class, 'upload_size_limit']);
    }

    public static function upload_size_limit($size)
    {
        $mb = (int) get_option('wp_pq_max_upload_mb', 1024);
        $bytes = $mb * 1024 * 1024;

        return max((int) $size, $bytes);
    }
}
