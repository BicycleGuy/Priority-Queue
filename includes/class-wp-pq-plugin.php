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
        WP_PQ_DB::migrate_workflow_status_model();
        WP_PQ_DB::migrate_task_context_fields();
        WP_PQ_DB::migrate_client_accounts();
        WP_PQ_DB::migrate_named_default_buckets();
        WP_PQ_DB::migrate_invoice_draft_models();
        WP_PQ_DB::migrate_work_statement_snapshots();
        WP_PQ_DB::ensure_default_billing_buckets();
        WP_PQ_DB::migrate_workflow_ledger_model();
        WP_PQ_DB::migrate_portal_manager_model();
        WP_PQ_DB::migrate_ledger_closure_model();
        WP_PQ_DB::migrate_notification_event_keys();
        WP_PQ_DB::migrate_rejected_event_key();
        WP_PQ_DB::migrate_clear_false_archived_at();
        WP_PQ_DB::migrate_drive_storage_model();
        WP_PQ_DB::migrate_files_link();
        WP_PQ_DB::migrate_per_user_google_tokens();
        WP_PQ_DB::migrate_google_tokens_to_user();
        WP_PQ_DB::migrate_invite_tracking_columns();
        WP_PQ_Housekeeping::init();
        WP_PQ_Admin::init();
        WP_PQ_API::init();
        WP_PQ_Manager_API::init();
        WP_PQ_Portal::init();

        // File exchange removed — upload limits and media scoping no longer needed.

        // Branded login page.
        add_action('login_enqueue_scripts', [self::class, 'login_styles']);
        add_filter('login_headerurl', [self::class, 'login_header_url']);
        add_filter('login_headertext', [self::class, 'login_header_text']);
        add_filter('login_title', [self::class, 'login_title']);

        // Redirect non-admin users to /portal after login.
        add_filter('login_redirect', [self::class, 'login_redirect'], 10, 3);
    }

    public static function upload_size_limit($size)
    {
        $mb = (int) get_option('wp_pq_max_upload_mb', 1024);
        $bytes = $mb * 1024 * 1024;

        return max((int) $size, $bytes);
    }

    public static function restrict_media_library(array $query): array
    {
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            $query['author'] = get_current_user_id();
        }

        return $query;
    }

    public static function restrict_media_list_view(\WP_Query $query): void
    {
        global $pagenow;
        if ($pagenow !== 'upload.php' || ! $query->is_main_query()) {
            return;
        }
        if (! current_user_can(WP_PQ_Roles::CAP_APPROVE)) {
            $query->set('author', get_current_user_id());
        }
    }

    public static function allow_creative_mimes(array $mimes): array
    {
        $mimes['indd'] = 'application/x-indesign';
        $mimes['ai'] = 'application/postscript';
        $mimes['psd'] = 'image/vnd.adobe.photoshop';
        $mimes['eps'] = 'application/postscript';
        $mimes['sketch'] = 'application/zip';
        $mimes['fig'] = 'application/octet-stream';
        $mimes['md'] = 'text/markdown';
        $mimes['csv'] = 'text/csv';
        $mimes['webp'] = 'image/webp';

        return $mimes;
    }

    // ── Branded Login Page ──────────────────────────────────────

    public static function login_styles(): void
    {
        ?>
        <style>
            body.login {
                background: #f3f4f6;
                font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
            }
            .login h1 a {
                background-image: none !important;
                font-size: 26px !important;
                font-weight: 700 !important;
                color: #1e293b !important;
                text-indent: 0 !important;
                overflow: visible !important;
                width: auto !important;
                height: auto !important;
                letter-spacing: .3px;
                text-decoration: none !important;
                padding: 0 !important;
                margin-bottom: 20px !important;
                line-height: 1.3 !important;
            }
            .login h1 a:hover,
            .login h1 a:focus {
                color: #2563eb;
            }
            .login form {
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,.08);
            }
            .login #backtoblog,
            .login #nav {
                text-align: center;
            }
            .login #backtoblog a,
            .login #nav a {
                color: #64748b;
            }
            .login #backtoblog a:hover,
            .login #nav a:hover {
                color: #2563eb;
            }
            .wp-core-ui .button-primary {
                background: #2563eb;
                border-color: #1d4ed8;
                border-radius: 6px;
                font-weight: 600;
            }
            .wp-core-ui .button-primary:hover,
            .wp-core-ui .button-primary:focus {
                background: #1d4ed8;
                border-color: #1e40af;
            }
            .login .message,
            .login .success {
                border-left-color: #2563eb;
                border-radius: 4px;
            }
        </style>
        <?php
    }

    public static function login_header_url(): string
    {
        return home_url('/');
    }

    public static function login_header_text(): string
    {
        return 'Switchboard';
    }

    public static function login_title(): string
    {
        return 'Sign In \u2014 Switchboard';
    }

    /**
     * After login, send pq_client and pq_worker users to the portal
     * instead of wp-admin (which they can't access anyway).
     */
    public static function login_redirect(string $redirect_to, string $requested_redirect_to, $user): string
    {
        if (! $user instanceof WP_User) {
            return $redirect_to;
        }

        // If they were headed somewhere specific (e.g. password reset redirect), honour that.
        if ($requested_redirect_to !== '' && $requested_redirect_to !== admin_url()) {
            return $redirect_to;
        }

        // Managers and admins go to wp-admin as usual.
        if ($user->has_cap(WP_PQ_Roles::CAP_APPROVE)) {
            return $redirect_to;
        }

        // Everyone else goes to the portal.
        return WP_PQ_Portal::portal_url();
    }
}
