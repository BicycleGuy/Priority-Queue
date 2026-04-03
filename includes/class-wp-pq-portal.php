<?php

if (! defined('ABSPATH')) {
    exit;
}

class WP_PQ_Portal
{
    private static ?string $portal_url_cache = null;

    public static function init(): void
    {
        add_shortcode('pq_client_portal', [self::class, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_action('template_redirect', [self::class, 'redirect_old_portal_slug']);

        // Phase F — standalone /portal route.
        add_action('init', [self::class, 'register_portal_rewrites']);
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_action('template_redirect', [self::class, 'handle_portal_route'], 5);

        // Redirect login failures back to /portal/login when that's the referrer.
        add_action('wp_login_failed', [self::class, 'portal_login_failed_redirect']);
        add_filter('login_redirect', [self::class, 'portal_login_success_redirect'], 20, 3);
    }

    /**
     * Redirect retired slugs to /portal.
     */
    public static function redirect_old_portal_slug(): void
    {
        if (is_404()) {
            $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');
            if ($path === 'priority-portal' || strpos($path, 'priority-portal') === 0) {
                $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
                wp_redirect(home_url('/portal') . $qs, 301);
                exit;
            }
        }
    }

    // ── Phase F: Standalone /portal route ──────────────────────

    public static function register_portal_rewrites(): void
    {
        add_rewrite_rule('^portal/invite/([a-f0-9]{64})/?$', 'index.php?pq_portal_route=invite&pq_invite_token=$matches[1]', 'top');
        add_rewrite_rule('^portal/login/?$', 'index.php?pq_portal_route=login', 'top');
        add_rewrite_rule('^portal/?$', 'index.php?pq_portal_route=dashboard', 'top');
    }

    public static function register_query_vars(array $vars): array
    {
        $vars[] = 'pq_portal_route';
        $vars[] = 'pq_invite_token';
        return $vars;
    }

    /**
     * Intercept /portal and /portal/login — render standalone pages
     * outside the WordPress theme layer.
     */
    public static function handle_portal_route(): void
    {
        $route = get_query_var('pq_portal_route', '');
        if ($route === '') {
            return;
        }

        if ($route === 'invite') {
            $token = sanitize_text_field(get_query_var('pq_invite_token', ''));
            if (strlen($token) !== 64) {
                wp_safe_redirect(home_url('/portal/login'));
                exit;
            }

            global $wpdb;
            $invite = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pq_invites WHERE token = %s AND status = 'pending' AND expires_at > NOW()",
                $token
            ), ARRAY_A);

            if (! $invite) {
                $accepted = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}pq_invites WHERE token = %s",
                    $token
                ));
                if ($accepted === 'accepted') {
                    wp_safe_redirect(home_url('/portal/login?invite=accepted'));
                } else {
                    wp_safe_redirect(home_url('/portal/login?invite=expired'));
                }
                exit;
            }

            $existing_user = get_user_by('email', $invite['email']) ?: null;
            $logged_in_user = is_user_logged_in() ? wp_get_current_user() : null;

            // Case 1: Logged in as the matching user — auto-accept
            if ($logged_in_user && strtolower($logged_in_user->user_email) === strtolower($invite['email'])) {
                $logged_in_user->add_role($invite['role']);
                if ((int) ($invite['client_id'] ?? 0) > 0) {
                    WP_PQ_DB::ensure_client_member((int) $invite['client_id'], (int) $logged_in_user->ID, $invite['client_role'] ?: 'client_contributor');
                }
                $wpdb->update($wpdb->prefix . 'pq_invites', [
                    'status' => 'accepted',
                    'accepted_at' => current_time('mysql', true),
                    'accepted_user_id' => (int) $logged_in_user->ID,
                ], ['id' => (int) $invite['id']]);

                // Notify the inviter
                $invited_by = (int) ($invite['invited_by'] ?? 0);
                if ($invited_by > 0 && $invited_by !== (int) $logged_in_user->ID) {
                    $accepted_name = $logged_in_user->display_name ?: $invite['email'];
                    $client_name = '';
                    if ((int) ($invite['client_id'] ?? 0) > 0) {
                        $client_name = (string) $wpdb->get_var($wpdb->prepare(
                            "SELECT name FROM {$wpdb->prefix}pq_clients WHERE id = %d",
                            (int) $invite['client_id']
                        ));
                    }
                    $context = $client_name !== '' ? " and joined {$client_name}" : '';
                    $wpdb->insert($wpdb->prefix . 'pq_notifications', [
                        'user_id' => $invited_by,
                        'task_id' => null,
                        'event_key' => 'invite_accepted',
                        'title' => 'Invitation accepted',
                        'body' => sanitize_text_field("{$accepted_name} accepted your invitation{$context}."),
                        'payload' => wp_json_encode(['invite_id' => (int) $invite['id'], 'accepted_user_id' => (int) $logged_in_user->ID]),
                        'is_read' => 0,
                        'created_at' => current_time('mysql', true),
                        'read_at' => null,
                    ]);
                }

                wp_safe_redirect(home_url('/portal'));
                exit;
            }

            // Case 2: POST — process the acceptance form
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! empty($_POST['pq_accept_nonce'])) {
                if (! wp_verify_nonce($_POST['pq_accept_nonce'], 'pq_accept_invite_' . $token)) {
                    wp_safe_redirect(home_url('/portal/login?invite=error'));
                    exit;
                }

                // Re-validate invite (guard against race conditions)
                $invite = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}pq_invites WHERE token = %s AND status = 'pending' AND expires_at > NOW()",
                    $token
                ), ARRAY_A);
                if (! $invite) {
                    wp_safe_redirect(home_url('/portal/login?invite=expired'));
                    exit;
                }

                $existing_user = get_user_by('email', $invite['email']) ?: null;

                if ($existing_user) {
                    // Existing user: authenticate with provided password
                    $password = (string) ($_POST['pwd'] ?? '');
                    $auth = wp_authenticate($existing_user->user_login, $password);
                    if (is_wp_error($auth)) {
                        self::render_invite_page($invite, $token, $existing_user, 'Incorrect password. Please try again.');
                        exit;
                    }
                    $user = $auth;
                    $user->add_role($invite['role']);
                } else {
                    // New user: create account with chosen password
                    $password = (string) ($_POST['pwd'] ?? '');
                    $confirm = (string) ($_POST['pwd_confirm'] ?? '');
                    if (strlen($password) < 8) {
                        self::render_invite_page($invite, $token, null, 'Password must be at least 8 characters.');
                        exit;
                    }
                    if ($password !== $confirm) {
                        self::render_invite_page($invite, $token, null, 'Passwords do not match.');
                        exit;
                    }

                    $first = trim((string) ($invite['first_name'] ?? ''));
                    $last = trim((string) ($invite['last_name'] ?? ''));
                    $display = trim("{$first} {$last}") ?: $invite['email'];

                    $user_id = WP_PQ_DB::create_wp_user($invite['email'], [
                        'display_name'      => $display,
                        'first_name'        => $first,
                        'last_name'         => $last,
                        'role'              => $invite['role'],
                        'password'          => $password,
                        'username_fallback' => 'user',
                    ]);

                    if (is_wp_error($user_id)) {
                        self::render_invite_page($invite, $token, null, 'Account creation failed. Please try again.');
                        exit;
                    }
                    $user = get_userdata($user_id);
                }

                // Bind to client
                if ((int) ($invite['client_id'] ?? 0) > 0) {
                    WP_PQ_DB::ensure_client_member((int) $invite['client_id'], (int) $user->ID, $invite['client_role'] ?: 'client_contributor');
                }

                // Mark accepted
                $wpdb->update($wpdb->prefix . 'pq_invites', [
                    'status' => 'accepted',
                    'accepted_at' => current_time('mysql', true),
                    'accepted_user_id' => (int) $user->ID,
                ], ['id' => (int) $invite['id']]);

                // Notify the inviter
                $invited_by = (int) ($invite['invited_by'] ?? 0);
                if ($invited_by > 0) {
                    $accepted_name = trim(($invite['first_name'] ?? '') . ' ' . ($invite['last_name'] ?? '')) ?: $invite['email'];
                    $client_name = '';
                    if ((int) ($invite['client_id'] ?? 0) > 0) {
                        $client_name = (string) $wpdb->get_var($wpdb->prepare(
                            "SELECT name FROM {$wpdb->prefix}pq_clients WHERE id = %d",
                            (int) $invite['client_id']
                        ));
                    }
                    $context = $client_name !== '' ? " and joined {$client_name}" : '';
                    $wpdb->insert($wpdb->prefix . 'pq_notifications', [
                        'user_id' => $invited_by,
                        'task_id' => null,
                        'event_key' => 'invite_accepted',
                        'title' => 'Invitation accepted',
                        'body' => sanitize_text_field("{$accepted_name} accepted your invitation{$context}."),
                        'payload' => wp_json_encode(['invite_id' => (int) $invite['id'], 'accepted_user_id' => (int) $user->ID]),
                        'is_read' => 0,
                        'created_at' => current_time('mysql', true),
                        'read_at' => null,
                    ]);
                }

                // Log in and redirect
                wp_set_auth_cookie($user->ID, true, is_ssl());
                do_action('wp_login', $user->user_login, $user);
                wp_safe_redirect(home_url('/portal'));
                exit;
            }

            // Case 3: GET — show the acceptance form
            // If logged in as a different user, log them out first
            if ($logged_in_user && strtolower($logged_in_user->user_email) !== strtolower($invite['email'])) {
                wp_logout();
            }

            self::render_invite_page($invite, $token, $existing_user);
            exit;
        }

        if ($route === 'login') {
            self::render_portal_login();
            exit;
        }

        // /portal — require authentication.
        if (! is_user_logged_in()) {
            wp_redirect(home_url('/portal/login'));
            exit;
        }

        self::render_portal_standalone();
        exit;
    }

    /**
     * When login fails and the user came from /portal/login, redirect back there.
     */
    public static function portal_login_failed_redirect(string $username): void
    {
        $referer = wp_get_referer();
        if ($referer && strpos($referer, '/portal/login') !== false) {
            wp_redirect(home_url('/portal/login?login=failed'));
            exit;
        }
    }

    /**
     * After successful login via /portal/login form, ensure redirect goes to /portal.
     */
    public static function portal_login_success_redirect(string $redirect_to, string $requested, $user): string
    {
        if (strpos($redirect_to, '/portal') !== false) {
            return home_url('/portal');
        }
        return $redirect_to;
    }

    /**
     * Render the branded standalone login page at /portal/login.
     * Form POSTs to wp-login.php (battle-tested cookie handling)
     * with redirect_to=/portal so the user lands back here after auth.
     */
    private static function render_portal_login(): void
    {
        // Already logged in? Go to portal.
        if (is_user_logged_in()) {
            wp_redirect(home_url('/portal'));
            exit;
        }

        // Check for login error passed back from wp-login.php.
        $error = '';
        $info = '';
        if (! empty($_GET['login']) && $_GET['login'] === 'failed') {
            $error = 'Invalid username or password.';
        }
        if (! empty($_GET['invite'])) {
            switch ($_GET['invite']) {
                case 'accepted':
                    $info = 'You\'ve already accepted this invitation. Log in below to access your workspace.';
                    break;
                case 'expired':
                    $error = 'This invitation has expired or been revoked. Contact your administrator for a new one.';
                    break;
                case 'error':
                    $error = 'Something went wrong while processing your invitation. Contact your administrator.';
                    break;
            }
        }

        $login_action_url = site_url('wp-login.php', 'login');
        $redirect_to = home_url('/portal');

        // Render login page.
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In — Switchboard</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        background: #f3f4f6;
        font-family: -apple-system, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
        color: #334155;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 24px;
    }
    .pq-login-shell {
        width: 100%;
        max-width: 380px;
    }
    .pq-login-brand {
        text-align: center;
        margin-bottom: 28px;
    }
    .pq-login-brand h1 {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: .3px;
    }
    .pq-login-brand p {
        margin-top: 6px;
        font-size: 14px;
        color: #64748b;
    }
    .pq-login-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
        padding: 28px 24px 24px;
    }
    .pq-login-card label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: #475569;
        margin-bottom: 16px;
    }
    .pq-login-card input[type="text"],
    .pq-login-card input[type="password"] {
        display: block;
        width: 100%;
        margin-top: 4px;
        padding: 9px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 15px;
        color: #1e293b;
        background: #fff;
        transition: border-color .15s;
    }
    .pq-login-card input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,.12);
    }
    .pq-login-remember {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        color: #64748b;
        margin-bottom: 20px;
    }
    .pq-login-remember input { margin: 0; }
    .pq-login-btn {
        display: block;
        width: 100%;
        padding: 10px;
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
    }
    .pq-login-btn:hover { background: #1d4ed8; }
    .pq-login-btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    .pq-login-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 14px;
        margin-bottom: 16px;
    }
    .pq-login-footer {
        text-align: center;
        margin-top: 20px;
    }
    .pq-login-footer a {
        font-size: 13px;
        color: #64748b;
        text-decoration: none;
    }
    .pq-login-footer a:hover { color: #2563eb; }
</style>
</head>
<body>
<div class="pq-login-shell">
    <div class="pq-login-brand">
        <h1>Switchboard</h1>
        <p>Sign in to your workspace</p>
    </div>
    <div class="pq-login-card">
        <?php if ($error): ?>
            <div class="pq-login-error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="pq-login-info" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:6px;padding:10px 14px;font-size:14px;margin-bottom:16px;"><?php echo esc_html($info); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url($login_action_url); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
            <label>
                Username or Email
                <input type="text" name="log" autocomplete="username" required autofocus>
            </label>
            <label>
                Password
                <input type="password" name="pwd" autocomplete="current-password" required>
            </label>
            <label class="pq-login-remember">
                <input type="checkbox" name="rememberme" value="forever">
                Remember me
            </label>
            <button type="submit" class="pq-login-btn">Sign In</button>
        </form>
    </div>
    <div class="pq-login-footer">
        <a href="<?php echo esc_url(wp_lostpassword_url(home_url('/portal/login'))); ?>">Forgot password?</a>
    </div>
</div>
</body>
</html>
        <?php
    }

    /**
     * Render the invite acceptance page.
     */
    private static function render_invite_page(array $invite, string $token, ?\WP_User $existing_user = null, string $error = ''): void
    {
        $first = esc_html(trim((string) ($invite['first_name'] ?? '')));
        $last = esc_html(trim((string) ($invite['last_name'] ?? '')));
        $full_name = trim("{$first} {$last}") ?: esc_html($invite['email']);
        $email = esc_html($invite['email']);
        $is_new = ! $existing_user;
        $heading = $is_new ? 'Welcome to Switchboard' : 'Accept your invitation';
        $subheading = $is_new
            ? "Set a password to create your account, {$first}."
            : "Log in to accept your invitation, {$first}.";
        $btn_label = $is_new ? 'Create Account' : 'Log In &amp; Accept';
        $nonce = wp_create_nonce('pq_accept_invite_' . $token);
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $heading; ?> — Switchboard</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        background: #f3f4f6;
        font-family: -apple-system, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
        color: #334155;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 24px;
    }
    .pq-invite-shell { width: 100%; max-width: 420px; }
    .pq-invite-brand { text-align: center; margin-bottom: 28px; }
    .pq-invite-brand h1 { font-size: 28px; font-weight: 700; color: #1e293b; letter-spacing: .3px; }
    .pq-invite-brand p { margin-top: 6px; font-size: 14px; color: #64748b; }
    .pq-invite-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
        padding: 28px 24px 24px;
    }
    .pq-invite-card label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: #475569;
        margin-bottom: 16px;
    }
    .pq-invite-card input[type="text"],
    .pq-invite-card input[type="email"],
    .pq-invite-card input[type="password"] {
        display: block;
        width: 100%;
        margin-top: 4px;
        padding: 9px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 15px;
        color: #1e293b;
        background: #fff;
        transition: border-color .15s;
    }
    .pq-invite-card input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,.12);
    }
    .pq-invite-card input[readonly] {
        background: #f8fafc;
        color: #64748b;
        cursor: default;
    }
    .pq-invite-btn {
        display: block;
        width: 100%;
        margin-top: 8px;
        padding: 10px;
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
    }
    .pq-invite-btn:hover { background: #1d4ed8; }
    .pq-invite-btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    .pq-invite-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 14px;
        margin-bottom: 16px;
    }
    .pq-invite-detail {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 16px;
        padding: 10px 14px;
        background: #f8fafc;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }
    .pq-invite-detail strong { color: #334155; }
</style>
</head>
<body>
<div class="pq-invite-shell">
    <div class="pq-invite-brand">
        <h1>Switchboard</h1>
        <p><?php echo esc_html($heading); ?></p>
    </div>
    <div class="pq-invite-card">
        <?php if ($error): ?>
            <div class="pq-invite-error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <div class="pq-invite-detail">
            <?php echo esc_html($subheading); ?>
        </div>

        <form method="post" action="">
            <input type="hidden" name="pq_accept_nonce" value="<?php echo esc_attr($nonce); ?>">

            <label>
                Name
                <input type="text" value="<?php echo esc_attr($full_name); ?>" readonly>
            </label>
            <label>
                Email
                <input type="email" value="<?php echo esc_attr($invite['email']); ?>" readonly>
            </label>

            <label>
                Password<?php echo $is_new ? '' : ''; ?>
                <input type="password" name="pwd" autocomplete="<?php echo $is_new ? 'new-password' : 'current-password'; ?>" required autofocus minlength="<?php echo $is_new ? '8' : '1'; ?>">
            </label>

            <?php if ($is_new): ?>
            <label>
                Confirm Password
                <input type="password" name="pwd_confirm" autocomplete="new-password" required minlength="8">
            </label>
            <?php endif; ?>

            <button type="submit" class="pq-invite-btn"><?php echo $btn_label; ?></button>
        </form>
    </div>
</div>
</body>
</html>
        <?php
    }

    /**
     * Render the full portal app at /portal — standalone HTML, no theme.
     * Reuses render_shortcode() for the body — it handles asset enqueue,
     * config localisation, and all portal HTML.
     */
    private static function render_portal_standalone(): void
    {
        // Register assets so the shortcode renderer can enqueue them.
        self::register_assets();

        // render_shortcode() checks is_user_logged_in() — we've already
        // verified that in handle_portal_route(), so this will produce the
        // full authenticated portal HTML.
        $body = self::render_shortcode();

        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Switchboard</title>
<?php wp_head(); ?>
<style>
    /* Reset for standalone — no theme chrome. */
    html, body { margin: 0; padding: 0; height: 100%; }
    body { font-family: -apple-system, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif; }
    .wp-pq-wrap.wp-pq-portal { min-height: 100vh; }
</style>
</head>
<body class="pq-standalone">
<?php echo $body; ?>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }

    public static function register_assets(): void
    {
        wp_register_style('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/css/admin-queue.css', [], WP_PQ_VERSION);
        wp_register_style('wp-pq-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.css', [], '6.1.19');
        // Uppy removed — file exchange handled externally via link field.
        wp_register_script('sortable-js', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js', [], '1.15.6', true);
        wp_register_script('wp-pq-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js', [], '6.1.19', true);
        wp_register_script('wp-pq-admin', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue.js', ['sortable-js', 'wp-pq-fullcalendar'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-modals', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue-modals.js', ['wp-pq-admin'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-alerts', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue-alerts.js', ['wp-pq-admin'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-client-invites', WP_PQ_PLUGIN_URL . 'assets/js/admin-queue-client-invites.js', ['wp-pq-admin'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-mgr-core', WP_PQ_PLUGIN_URL . 'assets/js/admin-manager-core.js', ['wp-pq-admin', 'wp-pq-modals', 'wp-pq-alerts'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-mgr-clients', WP_PQ_PLUGIN_URL . 'assets/js/admin-manager-clients.js', ['wp-pq-mgr-core'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-mgr-reports', WP_PQ_PLUGIN_URL . 'assets/js/admin-manager-reports.js', ['wp-pq-mgr-core'], WP_PQ_VERSION, true);
        wp_register_script('wp-pq-portal-manager', WP_PQ_PLUGIN_URL . 'assets/js/admin-manager-tools.js', ['wp-pq-mgr-core', 'wp-pq-mgr-clients', 'wp-pq-mgr-reports'], WP_PQ_VERSION, true);
    }

    public static function portal_url(string $section = 'queue'): string
    {
        $url = home_url('/portal');
        $section = sanitize_key($section);
        if ($section !== '' && $section !== 'queue') {
            $url = add_query_arg('section', $section, $url);
        }

        return $url;
    }

    public static function render_shortcode(): string
    {
        wp_enqueue_style('wp-pq-admin');
        wp_enqueue_style('wp-pq-fullcalendar');

        if (! is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink());

            return '<div class="wp-pq-wrap wp-pq-guest">'
                . '<h2>Switchboard</h2>'
                . '<p>Requests, approvals, files, and scheduling in one place.</p>'
                . '<div class="wp-pq-guest-card">'
                . '<h3>Sign in required</h3>'
                . '<p>Please sign in to access your workspace.</p>'
                . '<a class="button button-primary" href="' . esc_url($login_url) . '">Log In</a>'
                . '</div>'
                . '</div>';
        }

        wp_enqueue_script('wp-pq-admin');
        wp_enqueue_script('wp-pq-modals');
        wp_enqueue_script('wp-pq-alerts');
        wp_enqueue_script('wp-pq-client-invites');

        $is_manager = current_user_can(WP_PQ_Roles::CAP_APPROVE);
        if ($is_manager) {
            wp_enqueue_script('wp-pq-portal-manager');
            // Uppy removed.
        }

        $portal_config = [
            'root' => esc_url_raw(rest_url('pq/v1/')),
            'coreRoot' => esc_url_raw(rest_url('wp/v2/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'portalUrl' => esc_url_raw(self::portal_url()),
            'canApprove' => current_user_can(WP_PQ_Roles::CAP_APPROVE),
            'canWork' => current_user_can(WP_PQ_Roles::CAP_WORK),
            'canAssign' => current_user_can(WP_PQ_Roles::CAP_ASSIGN),
            'canBatch' => current_user_can(WP_PQ_Roles::CAP_APPROVE),
            'canViewAll' => current_user_can(WP_PQ_Roles::CAP_VIEW_ALL),
            'currentUserId' => get_current_user_id(),
            'isManager' => $is_manager,
            'googleConnected' => ! empty(get_user_meta(get_current_user_id(), 'wp_pq_google_tokens', true)),
            'isClientAdmin' => false,
            'clientAdminClients' => [],
        ];

        // Detect client_admin memberships and expose to portal config
        $user_memberships = WP_PQ_DB::get_user_client_memberships(get_current_user_id());
        $admin_clients = [];
        if (! empty($user_memberships)) {
            global $wpdb;
            $clients_table = $wpdb->prefix . 'pq_clients';
            foreach ($user_memberships as $m) {
                if ($m['role'] === 'client_admin') {
                    $client_name = $wpdb->get_var($wpdb->prepare(
                        "SELECT name FROM {$clients_table} WHERE id = %d",
                        (int) $m['client_id']
                    ));
                    $admin_clients[] = ['id' => (int) $m['client_id'], 'name' => $client_name ?: ''];
                }
            }
        }
        if (! empty($admin_clients)) {
            $portal_config['isClientAdmin'] = true;
            $portal_config['clientAdminClients'] = $admin_clients;
        }

        wp_localize_script('wp-pq-admin', 'wpPqConfig', $portal_config);
        if ($is_manager) {
            wp_localize_script('wp-pq-mgr-core', 'wpPqManagerConfig', $portal_config);
        }

        ob_start();
        echo '<div class="wp-pq-wrap wp-pq-portal">';

        // Onboarding interstitial — shown until user connects Google.
        echo '  <div class="wp-pq-onboarding-overlay" id="wp-pq-onboarding-overlay" hidden>';
        echo '    <div class="wp-pq-onboarding-card">';
        echo '      <h2>Connect Your Google Account</h2>';
        echo '      <p>Switchboard needs access to your Google account so calendar events, Meet links, and email notifications come from <strong>your</strong> address.</p>';
        echo '      <ul>';
        echo '        <li>Google Calendar — events and Meet invites</li>';
        echo '        <li>Gmail — send status notifications</li>';
        echo '        <li>Google Drive — future file integrations</li>';
        echo '      </ul>';
        echo '      <button class="button button-primary" type="button" id="wp-pq-onboarding-connect">Connect Google Account</button>';
        echo '      <p style="margin-top:1rem;"><a href="#" id="wp-pq-onboarding-skip" style="color:#666;font-size:0.85em;">Skip for now</a></p>';
        echo '    </div>';
        echo '  </div>';

        // Mobile bar (visible ≤960px).
        echo '  <div class="wp-pq-mobile-bar" id="wp-pq-mobile-bar">';
        echo '    <button type="button" class="button" id="wp-pq-mobile-menu-btn">Menu</button>';
        echo '    <h3>Task Board</h3>';
        echo '    <button type="button" class="button button-primary" id="wp-pq-mobile-new-btn" style="min-height:34px;padding:6px 12px;">New</button>';
        echo '  </div>';

        echo '  <div class="wp-pq-app-shell">';
        echo '    <aside class="wp-pq-binder" id="wp-pq-binder">';
        echo '      <div class="wp-pq-binder-head">';
        echo '        <p class="wp-pq-kicker">Readspear</p>';
        echo '        <h2>Switchboard</h2>';
        echo '        <p class="wp-pq-panel-note">Requests, approvals, and scheduling in one calm workspace.</p>';
        echo '      </div>';
        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-action">';
        echo '        <button class="button button-primary wp-pq-primary-action" type="button" id="wp-pq-open-create">New Request</button>';
        echo '      </div>';
        // Queue — top-level nav, always visible.
        echo '      <div class="wp-pq-binder-section">';
        echo '        <div class="wp-pq-filter-nav">';
        echo '          <button class="button is-active" type="button" data-pq-section="queue" id="wp-pq-nav-queue"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span><span>Queue</span></span></button>';
        echo '        </div>';
        echo '      </div>';

        echo '      <div id="wp-pq-queue-binder-sections">';
        // View toggle
        echo '      <div class="wp-pq-binder-section">';
        echo '        <p class="wp-pq-binder-label">View</p>';
        echo '        <div class="wp-pq-view-toggle">';
        echo '          <button class="button button-primary is-active" id="wp-pq-view-board" type="button">Board</button>';
        echo '          <button class="button" id="wp-pq-view-calendar" type="button">Calendar</button>';
        echo '        </div>';
        echo '      </div>';
        // Scope — collapsible, default open
        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-scope">';
        echo '        <details class="wp-pq-admin-group" open>';
        echo '          <summary><svg class="wp-pq-summary-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>Scope</summary>';
        echo '          <div class="wp-pq-scope-inner">';
        echo '            <div id="wp-pq-binder-client-context" class="wp-pq-binder-context">Loading client scope…</div>';
        echo '            <div id="wp-pq-binder-job-context" class="wp-pq-binder-context">Loading jobs…</div>';
        echo '            <div class="wp-pq-board-filters" id="wp-pq-board-filters" hidden>';
        echo '              <label class="wp-pq-filter-control" id="wp-pq-client-filter-wrap" hidden>Client';
        echo '                <select id="wp-pq-client-filter"></select>';
        echo '              </label>';
        echo '            </div>';
        echo '          </div>';
        echo '        </details>';
        echo '      </div>';
        // Jobs — collapsible, default open
        echo '      <div class="wp-pq-binder-section" id="wp-pq-job-nav-wrap" hidden>';
        echo '        <details class="wp-pq-admin-group" open>';
        echo '          <summary><svg class="wp-pq-summary-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>Jobs</summary>';
        echo '          <div id="wp-pq-job-nav" class="wp-pq-job-nav wp-pq-filter-nav"></div>';
        echo '        </details>';
        echo '      </div>';
        echo '      </div>'; // close wp-pq-queue-binder-sections

        if ($is_manager) {
            // Administration — collapsible, default closed.
            echo '      <div class="wp-pq-binder-section">';
            echo '        <details class="wp-pq-admin-group" id="wp-pq-admin-group">';
            echo '          <summary><svg class="wp-pq-summary-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Administration</summary>';
            echo '          <div id="wp-pq-manager-nav" class="wp-pq-filter-nav wp-pq-manager-nav">';
            echo '            <button class="button" type="button" data-pq-section="clients"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span>Clients</span></span></button>';
            echo '            <button class="button" type="button" data-pq-section="billing-rollup"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><span>Billing Rollup</span></span></button>';
            echo '            <button class="button" type="button" data-pq-section="monthly-statements"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span><span>Monthly Statements</span></span></button>';
            echo '            <button class="button" type="button" data-pq-section="work-statements"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg></span><span>Work Statements</span></span></button>';
            echo '            <button class="button" type="button" data-pq-section="ai-import"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.9 5.8h6l-4.9 3.6 1.9 5.8-5-3.6-5 3.6 1.9-5.8-4.9-3.6h6z"/></svg></span><span>AI Import</span></span></button>';
            echo '            <button class="button" type="button" data-pq-section="files"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span><span>Files &amp; Links</span></span></button>';
            echo '            <button class="button" type="button" data-pq-section="invites"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></span><span>Invites</span></span></button>';
            echo '            <button class="button" type="button" data-pq-section="lanes"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg></span><span>Swimlanes</span></span></button>';
            echo '            <button class="button" type="button" data-pq-section="preferences"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.32 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span><span>Preferences</span></span></button>';
            echo '          </div>';
            echo '        </details>';
            echo '      </div>';
        }

        // Client Admin tools — shown to non-manager client admins
        if (! $is_manager && ! empty($admin_clients)) {
            echo '      <div class="wp-pq-binder-section">';
            echo '        <details class="wp-pq-admin-group" id="wp-pq-client-admin-group">';
            echo '          <summary><svg class="wp-pq-summary-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Team</summary>';
            echo '          <div id="wp-pq-client-admin-nav" class="wp-pq-filter-nav wp-pq-manager-nav">';
            echo '            <button class="button" type="button" data-pq-section="client-invites"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></span><span>Invites</span></span></button>';
            echo '          </div>';
            echo '        </details>';
            echo '      </div>';
        }

        echo '      <div class="wp-pq-binder-section wp-pq-binder-section-bottom">';
        echo '        <button class="button wp-pq-dark-mode-btn" type="button" id="wp-pq-dark-toggle"><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span><span>Dark mode</span></span></button>';
        echo '        <button class="button" type="button" id="wp-pq-open-prefs"' . ($is_manager ? ' hidden' : '') . '><span class="wp-pq-row-main"><span class="wp-pq-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.32 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span><span>Preferences</span></span></button>';
        echo '      </div>';
        echo '    </aside>';
        echo '    <main class="wp-pq-workspace">';
        echo '  <section class="wp-pq-panel wp-pq-compose-panel" id="wp-pq-create-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>New Request</h3>';
        echo '        <p class="wp-pq-panel-note">Submitter uploads files, describes needs, assigns deadline, and can request a meeting when needed.</p>';
        echo '      </div>';
        echo '      <button class="button" type="button" id="wp-pq-close-create">Close</button>';
        echo '    </div>';
        echo '    <form id="wp-pq-create-form" class="wp-pq-create-grid">';
        echo '      <label>Title <input type="text" name="title" required></label>';
        echo '      <label class="wp-pq-manager-only" id="wp-pq-create-client-wrap" hidden>Client';
        echo '        <select name="client_id" id="wp-pq-create-client"></select>';
        echo '      </label>';
        echo '      <label>Job';
        echo '        <select name="billing_bucket_id" id="wp-pq-create-bucket"></select>';
        echo '      </label>';
        echo '      <label class="wp-pq-manager-only" id="wp-pq-create-new-bucket-wrap" hidden>Create new job';
        echo '        <input type="text" name="new_bucket_name" id="wp-pq-create-new-bucket" placeholder="Job name">';
        echo '      </label>';
        echo '      <label class="wp-pq-span-2">Description <textarea name="description" rows="3"></textarea></label>';
        echo '      <label>Priority';
        echo '        <select name="priority">';
        echo '          <option value="low">Low</option>';
        echo '          <option value="normal" selected>Normal</option>';
        echo '          <option value="high">High</option>';
        echo '          <option value="urgent">Urgent</option>';
        echo '        </select>';
        echo '      </label>';
        echo '      <label>Requested Deadline <input type="datetime-local" name="requested_deadline" step="60" required></label>';
        echo '      <label class="inline wp-pq-span-2"><input type="checkbox" name="needs_meeting" id="wp-pq-create-needs-meeting"> Meeting Requested</label>';
        echo '      <div class="wp-pq-create-meeting-fields wp-pq-span-2" id="wp-pq-create-meeting-fields" hidden>';
        echo '        <label>Meeting Start <input type="datetime-local" name="meeting_starts_at" id="wp-pq-create-meeting-start" step="60"></label>';
        echo '        <label>Meeting End <input type="datetime-local" name="meeting_ends_at" id="wp-pq-create-meeting-end" step="60"></label>';
        echo '        <p class="wp-pq-panel-note wp-pq-span-2">Google Meet will invite the task requester.</p>';
        echo '      </div>';
        echo '      <label class="inline wp-pq-manager-only wp-pq-span-2"><input type="checkbox" name="is_billable" checked> Billable task</label>';
        echo '      <div class="wp-pq-create-actions wp-pq-span-2">';
        echo '        <button class="button button-primary" type="submit">Submit Request</button>';
        echo '      </div>';
        echo '    </form>';
        echo '  </section>';

        echo '  <section class="wp-pq-panel wp-pq-pref-panel" id="wp-pq-pref-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3>Preferences</h3>';
        echo '        <p class="wp-pq-panel-note">Notification controls live here now, and this panel is ready to expand later with appearance and layout options.</p>';
        echo '      </div>';
        echo '      <button class="button" type="button" id="wp-pq-close-prefs">Close</button>';
        echo '    </div>';
        echo '    <section class="wp-pq-pref-section">';
        echo '      <div class="wp-pq-pref-section-head">';
        echo '        <div>';
        echo '          <h4>Notifications</h4>';
        echo '          <p class="wp-pq-panel-note">Choose which emails you want, and whether alerts dismiss themselves or stay until you clear them.</p>';
        echo '        </div>';
      echo '      </div>';
        echo '      <div id="wp-pq-pref-list" class="wp-pq-pref-list"></div>';
        echo '      <button class="button button-primary" type="button" id="wp-pq-save-prefs">Save Preferences</button>';
        echo '    </section>';
        // Google account connection — all users.
        echo '    <section class="wp-pq-pref-section" id="wp-pq-gcal-section">';
        echo '      <div class="wp-pq-pref-section-head">';
        echo '        <div>';
        echo '          <h4>Google Account</h4>';
        echo '          <p class="wp-pq-panel-note">Connect your Google account to enable calendar events, Meet links, and email notifications from your own address.</p>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div id="wp-pq-gcal-status" class="wp-pq-gcal-status">';
        echo '        <span class="wp-pq-gcal-indicator" id="wp-pq-gcal-indicator"></span>';
        echo '      </div>';
        echo '    </section>';

        echo '    <section class="wp-pq-pref-section">';
        echo '      <div class="wp-pq-pref-section-head">';
        echo '        <div>';
        echo '          <h4>More Soon</h4>';
        echo '          <p class="wp-pq-panel-note">Appearance, type size, and layout controls will land here as the portal grows.</p>';
        echo '        </div>';
      echo '      </div>';
        echo '      <div class="wp-pq-empty-state">Preferences will keep growing here without turning into a separate admin screen.</div>';
        echo '    </section>';
        echo '  </section>';

        // Documents panel removed.

        echo '  <div id="wp-pq-alert-stack" class="wp-pq-alert-stack" aria-live="polite" aria-label="Current alerts"></div>';

        echo '  <div class="wp-pq-workspace-body">';
        echo '    <div class="wp-pq-workspace-main">';
        echo '  <section class="wp-pq-panel wp-pq-manager-panel" id="wp-pq-manager-panel" hidden>';
        echo '    <div class="wp-pq-section-heading">';
        echo '      <div>';
        echo '        <h3 id="wp-pq-manager-panel-title">Manager Workspace</h3>';
        echo '        <p class="wp-pq-panel-note" id="wp-pq-manager-panel-note">Portal-based admin tools for clients, billing, reporting, invoice drafts, and import.</p>';
        echo '      </div>';
        echo '    </div>';
        echo '    <div id="wp-pq-manager-toolbar" class="wp-pq-manager-toolbar"></div>';
        echo '    <div id="wp-pq-manager-content" class="wp-pq-manager-content"></div>';
        echo '  </section>';
        echo '  <div id="wp-pq-queue-main-sections">';
        echo '  <section class="wp-pq-board-shell">';
        echo '    <div id="wp-pq-board-filter-bar" class="wp-pq-board-filter-bar"></div>';
        echo '    <div id="wp-pq-board-panel">';
        echo '      <div id="wp-pq-board" class="wp-pq-board"></div>';
        echo '    </div>';
        echo '    <div id="wp-pq-calendar-panel" hidden>';
        echo '      <div id="wp-pq-calendar"></div>';
        echo '    </div>';
        echo '  </section>';

        echo '    </div>';
        echo '    </div>';
        echo '    <div class="wp-pq-drawer-backdrop" id="wp-pq-drawer-backdrop" hidden></div>';
        echo '    <aside class="wp-pq-drawer" id="wp-pq-task-drawer" aria-hidden="true">';
        echo '      <div class="wp-pq-drawer-header">';
        echo '        <div class="wp-pq-drawer-topline">';
        echo '          <p id="wp-pq-current-task-status" class="wp-pq-drawer-kicker">Select a task</p>';
        echo '          <button class="wp-pq-drawer-close" id="wp-pq-close-drawer" type="button" aria-label="Close task drawer">&times;</button>';
        echo '        </div>';
        echo '        <h3 id="wp-pq-current-task">Task Details</h3>';
        echo '        <div id="wp-pq-current-task-meta" class="wp-pq-drawer-context">Choose a board card or calendar item to open its workspace.</div>';
        echo '        <div id="wp-pq-current-task-guidance" class="wp-pq-guidance-callout" hidden></div>';
        echo '        <p id="wp-pq-current-task-description" class="wp-pq-drawer-description"></p>';
        echo '        <div id="wp-pq-current-task-actions" class="wp-pq-drawer-actions"></div>';
        echo '        <div id="wp-pq-task-settings-panel" class="wp-pq-task-settings-panel" hidden>';
        echo '          <div id="wp-pq-assignment-panel" class="wp-pq-setting-row">';
        echo '            <div id="wp-pq-assignment-summary" class="wp-pq-assignment-facts"></div>';
        echo '            <label class="wp-pq-setting-field"><span>Owner</span><select id="wp-pq-assignment-select"></select></label>';
        echo '          </div>';
        echo '          <label class="wp-pq-setting-field" id="wp-pq-priority-panel"><span>Priority</span>';
        echo '            <select id="wp-pq-priority-select">';
        echo '              <option value="low">Low</option>';
        echo '              <option value="normal">Normal</option>';
        echo '              <option value="high">High</option>';
        echo '              <option value="urgent">Urgent</option>';
        echo '            </select>';
        echo '          </label>';
        if ($is_manager) {
            echo '          <label class="wp-pq-setting-field" id="wp-pq-lane-panel"><span>Swimlane</span>';
            echo '            <select id="wp-pq-lane-select"><option value="0">Uncategorized</option></select>';
            echo '          </label>';
        }
        echo '          <button class="button" type="button" id="wp-pq-save-task-settings">Update</button>';
        echo '        </div>';
        echo '      </div>';
        echo '      <div id="wp-pq-task-empty" class="wp-pq-task-empty">Select a task to open its workspace and review messages, notes, files, and approvals.</div>';
        echo '      <div id="wp-pq-task-workspace" class="wp-pq-task-workspace" hidden>';
        echo '        <div class="wp-pq-workspace-tabs">';
        echo '          <button class="button button-primary is-active" type="button" id="wp-pq-tab-messages">Conversation<span class="wp-pq-tab-badge" id="wp-pq-badge-messages"></span></button>';
        echo '          <button class="button" type="button" id="wp-pq-tab-meetings">Meeting</button>';
        echo '          <button class="button" type="button" id="wp-pq-tab-files">Files</button>';
        echo '        </div>';
        echo '        <div class="wp-pq-workspace-shell">';
        echo '          <div class="wp-pq-subpanel wp-pq-workspace-panel is-active" id="wp-pq-panel-messages">';
        echo '            <p class="wp-pq-panel-note">Use @handle to notify someone directly.</p>';
        echo '            <div id="wp-pq-mention-list" class="wp-pq-mention-list"></div>';
        echo '            <ul id="wp-pq-message-list" class="wp-pq-stream"></ul>';
        echo '            <form id="wp-pq-message-form">';
        echo '              <div class="wp-pq-compose-bar">';
        echo '                <select id="wp-pq-compose-mode" class="wp-pq-compose-mode">';
        echo '                  <option value="message">Message</option>';
        echo '                  <option value="note">Note (silent)</option>';
        echo '                </select>';
        echo '                <textarea name="body" rows="2" required placeholder="Write a message\u2026"></textarea>';
        echo '              </div>';
        echo '              <button class="button" type="submit" id="wp-pq-compose-submit">Send Message</button>';
        echo '            </form>';
        echo '          </div>';
        echo '          <div class="wp-pq-subpanel wp-pq-workspace-panel" id="wp-pq-panel-meetings" hidden>';
        echo '            <h4>Meeting</h4>';
        echo '            <p class="wp-pq-panel-note" id="wp-pq-meeting-summary">Meeting details will appear here when requested.</p>';
        echo '            <ul id="wp-pq-meeting-list" class="wp-pq-stream"></ul>';
        echo '            <form id="wp-pq-meeting-form" class="wp-pq-meeting-form">';
        echo '              <label>Start <input type="datetime-local" name="starts_at" step="900"></label>';
        echo '              <label>End <input type="datetime-local" name="ends_at" step="900"></label>';
        echo '              <button class="button" type="submit">Schedule Google Meet</button>';
        echo '            </form>';
        echo '          </div>';
        echo '          <div class="wp-pq-subpanel wp-pq-workspace-panel" id="wp-pq-panel-files" hidden>';
        echo '            <h4>Files</h4>';
        echo '            <p class="wp-pq-panel-note">Paste a link to a shared Drive folder, Dropbox, or any file-sharing URL for this task.</p>';
        echo '            <div id="wp-pq-files-link-display" class="wp-pq-files-link-display"></div>';
        echo '            <form id="wp-pq-files-link-form" class="wp-pq-files-link-form">';
        echo '              <label>Files link <input type="url" name="files_link" placeholder="https://drive.google.com/..." class="regular-text"></label>';
        echo '              <button class="button" type="submit">Save Link</button>';
        echo '            </form>';
        echo '          </div>';
        echo '        </div>';
        echo '      </div>';
        echo '    </aside>';
        echo '  </div>';
        echo '    </main>';
        echo '  </div>';

        echo '  <div class="wp-pq-modal-backdrop" id="wp-pq-revision-modal-backdrop" hidden></div>';
        echo '  <section class="wp-pq-modal" id="wp-pq-revision-modal" hidden aria-hidden="true" aria-labelledby="wp-pq-revision-title">';
        echo '    <div class="wp-pq-modal-card wp-pq-modal-card-compact">';
        echo '      <div class="wp-pq-section-heading">';
        echo '        <div>';
        echo '          <p class="wp-pq-kicker">Revision request</p>';
        echo '          <h3 id="wp-pq-revision-title">What needs to change?</h3>';
        echo '          <p class="wp-pq-panel-note" id="wp-pq-revision-summary">Add a short note so the requester knows what to revise.</p>';
        echo '        </div>';
        echo '        <button class="button" type="button" id="wp-pq-close-revision-modal">Cancel</button>';
        echo '      </div>';
        echo '      <form id="wp-pq-revision-form">';
        echo '        <label>Revision note <textarea name="revision_note" rows="3" required></textarea></label>';
        echo '        <label class="inline"><input type="checkbox" name="post_message" value="1" checked> Post this to task messages</label>';
        echo '        <div class="wp-pq-modal-actions">';
        echo '          <button class="button" type="button" id="wp-pq-cancel-revision">Cancel</button>';
        echo '          <button class="button button-primary" type="submit">Send to Revisions</button>';
        echo '        </div>';
        echo '      </form>';
        echo '    </div>';
        echo '  </section>';
        echo '  <div class="wp-pq-modal-backdrop" id="wp-pq-move-modal-backdrop" hidden></div>';
        echo '  <section class="wp-pq-modal" id="wp-pq-move-modal" hidden aria-hidden="true" aria-labelledby="wp-pq-move-title">';
        echo '    <div class="wp-pq-modal-card">';
        echo '      <div class="wp-pq-section-heading">';
        echo '        <div>';
        echo '          <p class="wp-pq-kicker">Queue decision</p>';
        echo '          <h3 id="wp-pq-move-title">How should this move apply?</h3>';
        echo '          <p class="wp-pq-panel-note" id="wp-pq-move-summary">Choose whether this move should also change priority or dates.</p>';
        echo '        </div>';
        echo '      </div>';
        echo '      <form id="wp-pq-move-form">';
        echo '        <div class="wp-pq-choice wp-pq-choice-static">';
        echo '          <span><strong>Always included</strong><small>This move updates task order. If you moved the task into a new column, it also changes status.</small></span>';
        echo '        </div>';
        echo '        <fieldset class="wp-pq-choice-group">';
        echo '          <legend><strong>Priority change</strong><small>Only apply a priority change if this move should affect urgency.</small></legend>';
        echo '          <label class="wp-pq-choice">';
        echo '            <input type="radio" name="priority_direction" value="keep" checked>';
        echo '            <span><strong>Keep current priority</strong><small>Move the task without changing priority.</small></span>';
        echo '          </label>';
        echo '          <label class="wp-pq-choice">';
        echo '            <input type="radio" name="priority_direction" value="up">';
        echo '            <span><strong>Raise priority</strong><small>Promote the task one level.</small></span>';
        echo '          </label>';
        echo '          <label class="wp-pq-choice">';
        echo '            <input type="radio" name="priority_direction" value="down">';
        echo '            <span><strong>Lower priority</strong><small>Downgrade the task one level.</small></span>';
        echo '          </label>';
        echo '        </fieldset>';
        echo '        <label class="wp-pq-choice" id="wp-pq-move-date-swap-option" hidden>';
          echo '          <input type="checkbox" name="swap_due_dates" value="1">';
          echo '          <span><strong>Swap due dates</strong><small id="wp-pq-move-date-swap-hint">Exchange deadlines with the displaced task so dates follow priority order.</small></span>';
        echo '        </label>';
        echo '        <label class="wp-pq-choice" id="wp-pq-move-meeting-option" hidden>';
          echo '          <input type="checkbox" name="request_meeting" value="1">';
          echo '          <span><strong>Open meeting scheduling next</strong><small>Mark the task as meeting requested and open the Google Meet scheduler after this move.</small></span>';
        echo '        </label>';
        echo '        <label class="wp-pq-choice" id="wp-pq-move-email-option" hidden>';
          echo '          <input type="checkbox" name="send_update_email" value="1">';
          echo '          <span><strong>Send update email now</strong><small>Email the client members who can see this job with an immediate status summary.</small></span>';
        echo '        </label>';
        echo '        <div class="wp-pq-modal-actions">';
        echo '          <button class="button" type="button" id="wp-pq-cancel-move">Cancel</button>';
        echo '          <button class="button button-primary" type="submit" id="wp-pq-apply-move">Apply Change</button>';
        echo '        </div>';
        echo '      </form>';
        echo '    </div>';
        echo '  </section>';
        echo '  <div class="wp-pq-modal-backdrop" id="wp-pq-completion-modal-backdrop" hidden></div>';
        echo '  <section class="wp-pq-modal" id="wp-pq-completion-modal" hidden aria-hidden="true" aria-labelledby="wp-pq-completion-title">';
        echo '    <div class="wp-pq-modal-card">';
        echo '      <div class="wp-pq-section-heading">';
        echo '        <div>';
        echo '          <p class="wp-pq-kicker">Completion</p>';
        echo '          <h3 id="wp-pq-completion-title">Mark this task done</h3>';
        echo '          <p class="wp-pq-panel-note" id="wp-pq-completion-summary">Capture the billing details needed to close this task and write it to the work ledger.</p>';
        echo '        </div>';
        echo '        <button class="button" type="button" id="wp-pq-close-completion-modal">Cancel</button>';
        echo '      </div>';
        echo '      <form id="wp-pq-completion-form" class="wp-pq-create-grid">';
        echo '        <label>Billing mode';
        echo '          <select name="billing_mode" id="wp-pq-completion-billing-mode" required>';
        echo '            <option value="fixed_fee">Fixed fee</option>';
        echo '            <option value="hourly">Hourly</option>';
        echo '            <option value="pass_through_expense">Pass-through expense</option>';
        echo '            <option value="non_billable">Non-billable</option>';
        echo '          </select>';
        echo '        </label>';
        echo '        <label>Billing category <input type="text" name="billing_category" id="wp-pq-completion-billing-category" placeholder="Support, retainer, reimbursement, etc."></label>';
        echo '        <label class="wp-pq-span-2">Work summary <textarea name="work_summary" id="wp-pq-completion-work-summary" rows="4" required></textarea></label>';
        echo '        <p class="wp-pq-panel-note wp-pq-span-2" id="wp-pq-completion-mode-note">Fixed-fee work needs a short summary and billing category. Hours, rate, and amount stay optional.</p>';
        echo '        <label data-completion-field="hours">Hours <input type="number" name="hours" id="wp-pq-completion-hours" min="0" step="0.25" inputmode="decimal" placeholder="0.00"></label>';
        echo '        <label data-completion-field="rate">Rate <input type="number" name="rate" id="wp-pq-completion-rate" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></label>';
        echo '        <label data-completion-field="amount">Amount <input type="number" name="amount" id="wp-pq-completion-amount" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></label>';
        echo '        <label data-completion-field="expense_reference">Expense reference <input type="text" name="expense_reference" id="wp-pq-completion-expense-reference" placeholder="Vendor invoice, support pass-through, receipt ID"></label>';
        echo '        <label class="wp-pq-span-2" data-completion-field="non_billable_reason">Non-billable reason <textarea name="non_billable_reason" id="wp-pq-completion-non-billable-reason" rows="3" placeholder="Internal support, goodwill, admin cleanup, etc."></textarea></label>';
        echo '        <div class="wp-pq-modal-actions wp-pq-span-2">';
        echo '          <button class="button" type="button" id="wp-pq-cancel-completion">Cancel</button>';
        echo '          <button class="button button-primary" type="submit" id="wp-pq-apply-completion">Mark Done</button>';
        echo '        </div>';
        echo '      </form>';
        echo '    </div>';
        echo '  </section>';
        echo '  <div class="wp-pq-modal-backdrop" id="wp-pq-delete-modal-backdrop" hidden></div>';
        echo '  <section class="wp-pq-modal" id="wp-pq-delete-modal" hidden aria-hidden="true" aria-labelledby="wp-pq-delete-title">';
        echo '    <div class="wp-pq-modal-card wp-pq-modal-card-compact">';
        echo '      <div class="wp-pq-section-heading">';
        echo '        <div>';
        echo '          <p class="wp-pq-kicker">Delete task</p>';
        echo '          <h3 id="wp-pq-delete-title">Delete this task permanently?</h3>';
        echo '          <p class="wp-pq-panel-note" id="wp-pq-delete-summary">This removes the task and its related messages, notes, files, meetings, and notifications.</p>';
        echo '        </div>';
        echo '        <button class="button" type="button" id="wp-pq-close-delete-modal">Cancel</button>';
        echo '      </div>';
        echo '      <div class="wp-pq-modal-actions">';
        echo '        <button class="button" type="button" id="wp-pq-cancel-delete">Cancel</button>';
        echo '        <button class="button button-primary wp-pq-button-danger" type="button" id="wp-pq-confirm-delete">Delete Task</button>';
        echo '      </div>';
        echo '    </div>';
        echo '  </section>';
        echo '  <div class="wp-pq-floating-meeting" id="wp-pq-floating-meeting" hidden>';
        echo '    <div class="wp-pq-floating-meeting-header">';
        echo '      <h4 id="wp-pq-floating-meeting-title">Schedule Meeting</h4>';
        echo '      <button type="button" class="wp-pq-floating-meeting-close" id="wp-pq-floating-meeting-close" aria-label="Close">&times;</button>';
        echo '    </div>';
        echo '    <p class="wp-pq-panel-note" id="wp-pq-floating-meeting-summary"></p>';
        echo '    <form id="wp-pq-floating-meeting-form" class="wp-pq-floating-meeting-body">';
        echo '      <label>Start <input type="datetime-local" name="starts_at" step="900"></label>';
        echo '      <label>End <input type="datetime-local" name="ends_at" step="900"></label>';
        echo '      <div class="wp-pq-floating-meeting-actions">';
        echo '        <button class="button" type="button" id="wp-pq-floating-meeting-skip">Skip for now</button>';
        echo '        <button class="button button-primary" type="submit">Schedule Google Meet</button>';
        echo '      </div>';
        echo '    </form>';
        echo '  </div>';
        echo '  <div id="wp-pq-tooltip"></div>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}
