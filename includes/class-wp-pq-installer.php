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
        WP_PQ_Migrations::ensure_default_billing_buckets();
        self::set_default_options();
        self::deploy_relay();
        WP_PQ_Housekeeping::schedule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        WP_PQ_Housekeeping::unschedule();
        flush_rewrite_rules();
    }

    /**
     * Check whether the plugin needs initial setup (setup wizard).
     */
    public static function needs_setup(): bool
    {
        $relay_url = trim((string) get_option('wp_pq_relay_url', ''));
        $client_id = trim((string) get_option('wp_pq_google_client_id', ''));
        $encryption_key = trim((string) get_option('wp_pq_relay_encryption_key', ''));

        return $relay_url === '' || $client_id === '' || $encryption_key === '';
    }

    /**
     * Copy the bundled relay files to ABSPATH/relay/ and auto-configure
     * the relay URL and encryption key if not already set.
     */
    public static function deploy_relay(): void
    {
        $source = WP_PQ_PLUGIN_DIR . 'relay/';
        $target = ABSPATH . 'relay/';

        // Only deploy if the source exists in the plugin bundle.
        if (! is_dir($source)) {
            return;
        }

        // Create target directory if needed.
        if (! is_dir($target)) {
            if (! wp_mkdir_p($target)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('PQ Installer: could not create relay directory at ' . $target);
                }
                return;
            }
        }

        // Copy relay files (overwrite on update).
        $files = ['config.php', 'initiate.php', 'callback.php', 'refresh.php', '.htaccess'];
        foreach ($files as $file) {
            if (file_exists($source . $file)) {
                @copy($source . $file, $target . $file);
            }
        }

        // Copy .env.example if no .env exists yet.
        if (! file_exists($target . '.env') && file_exists($source . '.env.example')) {
            @copy($source . '.env.example', $target . '.env.example');
        }

        // Auto-configure relay URL if not set.
        $relay_url = trim((string) get_option('wp_pq_relay_url', ''));
        if ($relay_url === '') {
            $relay_url = home_url('/relay');
            update_option('wp_pq_relay_url', $relay_url);
        }

        // Auto-generate encryption key if not set.
        $encryption_key = trim((string) get_option('wp_pq_relay_encryption_key', ''));
        if ($encryption_key === '') {
            $encryption_key = bin2hex(random_bytes(32));
            update_option('wp_pq_relay_encryption_key', $encryption_key);
        }

        // Write relay .env if it doesn't exist and we have credentials.
        if (! file_exists($target . '.env')) {
            $client_id = trim((string) get_option('wp_pq_google_client_id', ''));
            $client_secret = trim((string) get_option('wp_pq_google_client_secret', ''));

            $env_content = "RELAY_GOOGLE_CLIENT_ID={$client_id}\n"
                . "RELAY_GOOGLE_CLIENT_SECRET={$client_secret}\n"
                . "RELAY_ENCRYPTION_KEY={$encryption_key}\n"
                . "RELAY_BASE_URL={$relay_url}\n";

            @file_put_contents($target . '.env', $env_content);
        }
    }

    /**
     * Save setup wizard settings, write the relay .env, and mark setup complete.
     */
    public static function save_setup(string $google_client_id, string $google_client_secret, string $openai_key = ''): array
    {
        $errors = [];

        if ($google_client_id === '') {
            $errors[] = 'Google Client ID is required.';
        }
        if ($google_client_secret === '') {
            $errors[] = 'Google Client Secret is required.';
        }
        if (! empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }

        // Save WP options.
        update_option('wp_pq_google_client_id', $google_client_id);
        update_option('wp_pq_google_client_secret', $google_client_secret);

        if ($openai_key !== '') {
            update_option('wp_pq_openai_api_key', $openai_key);
        }

        // Ensure relay is deployed and configured.
        self::deploy_relay();

        // Rewrite the relay .env with the new credentials.
        $relay_url = trim((string) get_option('wp_pq_relay_url', home_url('/relay')));
        $encryption_key = trim((string) get_option('wp_pq_relay_encryption_key', ''));
        $target = ABSPATH . 'relay/.env';

        $env_content = "RELAY_GOOGLE_CLIENT_ID={$google_client_id}\n"
            . "RELAY_GOOGLE_CLIENT_SECRET={$google_client_secret}\n"
            . "RELAY_ENCRYPTION_KEY={$encryption_key}\n"
            . "RELAY_BASE_URL={$relay_url}\n";

        if (! @file_put_contents($target, $env_content)) {
            $errors[] = 'Could not write relay .env file. Check write permissions on ' . ABSPATH . 'relay/';
        }

        // Assign manager role to current user if they don't have it.
        $user = wp_get_current_user();
        if ($user->ID > 0 && ! user_can($user, WP_PQ_Roles::CAP_APPROVE)) {
            $user->add_role('pq_manager');
        }

        $callback_url = rtrim($relay_url, '/') . '/callback.php';

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'callback_url' => $callback_url,
        ];
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
        add_option('wp_pq_google_scopes', 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/gmail.send');
    }
}
