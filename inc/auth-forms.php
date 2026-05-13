<?php
/**
 * Custom Auth – Shortcodes & Asset Enqueuing
 *
 * Registers:
 *  [cotlas_login]            – Login form
 *  [cotlas_register]         – Registration form
 *  [cotlas_forgot_password]  – Forgot-password (sends WP reset email)
 *
 * Shortcode attributes (login & register):
 *   redirect  – URL to go to after success (overrides settings-page default).
 *               Ignored if the role-based redirect rule is more specific.
 *   class     – Extra CSS class(es) added to the outer .cotlas-auth-wrap div.
 *
 * @package Cotlas_Admin
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Asset enqueuing
// ---------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'cotlas_auth_enqueue_assets' );

function cotlas_auth_enqueue_assets() {
    if ( ! get_option( 'cotlas_auth_enabled' ) ) {
        return;
    }

    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    $version    = '1.0.0';

    wp_enqueue_style(
        'cotlas-auth',
        $plugin_url . 'assets/css/auth-forms.css',
        [],
        $version
    );

    wp_enqueue_script(
        'cotlas-auth',
        $plugin_url . 'assets/js/auth-forms.js',
        [],          // no jQuery dependency – uses native fetch
        $version,
        true         // footer
    );

    wp_localize_script( 'cotlas-auth', 'cotlasAuth', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'cotlas_auth_nonce' ),
        'i18n'    => [
            'connectionError' => __( 'Connection error. Please try again.', 'cotlas-admin' ),
            'loading'         => __( 'Please wait…', 'cotlas-admin' ),
        ],
    ] );

    // Cloudflare Turnstile – only load if a key exists AND at least one custom form toggle is on
    $ts_key = get_option( 'turnstile_site_key' );
    if ( $ts_key && ( get_option( 'cotlas_auth_turnstile_login' ) || get_option( 'cotlas_auth_turnstile_register' ) ) ) {
        wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true );
    }
}

// ---------------------------------------------------------------------------
// Helper: render honeypot field
// ---------------------------------------------------------------------------
function cotlas_auth_honeypot_field() {
    if ( ! get_option( 'cotlas_auth_honeypot', 1 ) ) {
        return '';
    }
    // Visually hidden from humans; bots fill it; tabindex=-1 prevents focus
    return '<div class="cotlas-hp" aria-hidden="true">'
         . '<label for="cotlas-hp-city">City</label>'
         . '<input type="text" id="cotlas-hp-city" name="cc-city" value="" autocomplete="off" tabindex="-1" />'
         . '</div>';
}

// ---------------------------------------------------------------------------
// [cotlas_login]
// ---------------------------------------------------------------------------
add_shortcode( 'cotlas_login', 'cotlas_login_shortcode' );

function cotlas_login_shortcode( $atts ) {
    if ( ! get_option( 'cotlas_auth_enabled' ) ) {
        return '';
    }
    // Already logged in – show nothing (overlay will hide itself via JS too)
    if ( is_user_logged_in() ) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'redirect' => '',
            'class'    => '',
        ],
        $atts,
        'cotlas_login'
    );

    $site_key       = get_option( 'turnstile_site_key' );
    $show_turnstile = $site_key && get_option( 'cotlas_auth_turnstile_login' );

    // Redirect: shortcode attr > URL query param > empty (AJAX handler will use role default)
    $redirect = '';
    if ( $atts['redirect'] ) {
        $redirect = esc_url_raw( $atts['redirect'] );
    } elseif ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore
        $redirect = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore
    }

    $extra_class = sanitize_html_class( $atts['class'] );

    $register_slug = get_option( 'cotlas_auth_register_slug' ) ?: 'register';
    $login_slug    = get_option( 'cotlas_auth_login_slug' )    ?: 'login';
    $forgot_slug   = get_option( 'cotlas_auth_forgot_slug' )   ?: 'reset-password';
    $register_url  = home_url( '/' . $register_slug . '/' );
    $forgot_url    = home_url( '/' . $forgot_slug . '/' );

    ob_start();
    ?>
    <div class="cotlas-auth-wrap cotlas-login-wrap<?php echo $extra_class ? ' ' . esc_attr( $extra_class ) : ''; ?>"
         data-cotlas-wrap="login">
        <div class="cotlas-auth-messages" aria-live="polite" role="alert" hidden></div>

        <form class="cotlas-auth-form cotlas-login-form"
              data-cotlas-form="login"
              method="post"
              action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
              novalidate>

            <input type="hidden" name="action"          value="cotlas_login" />
            <input type="hidden" name="cotlas_nonce"    value="<?php echo esc_attr( wp_create_nonce( 'cotlas_auth_nonce' ) ); ?>" />
            <input type="hidden" name="cotlas_redirect" value="<?php echo esc_attr( $redirect ); ?>" />

            <div class="cotlas-field">
                <label for="cotlas-login-user">
                    <?php esc_html_e( 'Username or Email', 'cotlas-admin' ); ?>
                </label>
                <input type="text"
                       id="cotlas-login-user"
                       name="log"
                       required
                       autocomplete="username"
                       spellcheck="false"
                       autocapitalize="off" />
            </div>

            <div class="cotlas-field">
                <label for="cotlas-login-pass">
                    <?php esc_html_e( 'Password', 'cotlas-admin' ); ?>
                </label>
                <input type="password"
                       id="cotlas-login-pass"
                       name="pwd"
                       required
                       autocomplete="current-password" />
            </div>

            <div class="cotlas-field cotlas-remember">
                <label>
                    <input type="checkbox" name="rememberme" value="forever" />
                    <?php esc_html_e( 'Remember me', 'cotlas-admin' ); ?>
                </label>
            </div>

            <?php echo cotlas_auth_honeypot_field(); // phpcs:ignore ?>

            <?php if ( $show_turnstile ) : ?>
                <div class="cotlas-field">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
                </div>
            <?php endif; ?>

            <div class="cotlas-field">
                <button type="submit" class="cotlas-btn cotlas-btn-primary">
                    <span class="cotlas-btn-text"><?php esc_html_e( 'Log In', 'cotlas-admin' ); ?></span>
                    <span class="cotlas-btn-spinner" hidden aria-hidden="true"></span>
                </button>
            </div>

            <div class="cotlas-auth-links">
                <?php if ( get_option( 'users_can_register' ) ) : ?>
                    <a href="<?php echo esc_url( $register_url ); ?>">
                        <?php esc_html_e( 'Create an account', 'cotlas-admin' ); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( $forgot_url ); ?>">
                    <?php esc_html_e( 'Forgot password?', 'cotlas-admin' ); ?>
                </a>
            </div>

        </form>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// [cotlas_register]
// ---------------------------------------------------------------------------
add_shortcode( 'cotlas_register', 'cotlas_register_shortcode' );

function cotlas_register_shortcode( $atts ) {
    if ( ! get_option( 'cotlas_auth_enabled' ) ) {
        return '';
    }
    if ( is_user_logged_in() ) {
        return '';
    }

    if ( ! get_option( 'users_can_register' ) ) {
        return '<p class="cotlas-auth-notice">'
             . esc_html__( 'User registration is currently disabled.', 'cotlas-admin' )
             . '</p>';
    }

    $atts = shortcode_atts(
        [
            'redirect' => '',
            'class'    => '',
        ],
        $atts,
        'cotlas_register'
    );

    $site_key       = get_option( 'turnstile_site_key' );
    $show_turnstile = $site_key && get_option( 'cotlas_auth_turnstile_register' );

    $redirect    = $atts['redirect'] ? esc_url_raw( $atts['redirect'] ) : '';
    $extra_class = sanitize_html_class( $atts['class'] );
    $login_slug  = get_option( 'cotlas_auth_login_slug' ) ?: 'login';
    $login_url   = home_url( '/' . $login_slug . '/' );

    ob_start();
    ?>
    <div class="cotlas-auth-wrap cotlas-register-wrap<?php echo $extra_class ? ' ' . esc_attr( $extra_class ) : ''; ?>"
         data-cotlas-wrap="register">
        <div class="cotlas-auth-messages" aria-live="polite" role="alert" hidden></div>

        <form class="cotlas-auth-form cotlas-register-form"
              data-cotlas-form="register"
              method="post"
              action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
              novalidate>

            <input type="hidden" name="action"          value="cotlas_register" />
            <input type="hidden" name="cotlas_nonce"    value="<?php echo esc_attr( wp_create_nonce( 'cotlas_auth_nonce' ) ); ?>" />
            <input type="hidden" name="cotlas_redirect" value="<?php echo esc_attr( $redirect ); ?>" />

            <div class="cotlas-field">
                <label for="cotlas-reg-username">
                    <?php esc_html_e( 'Username', 'cotlas-admin' ); ?>
                </label>
                <input type="text"
                       id="cotlas-reg-username"
                       name="user_login"
                       required
                       autocomplete="username"
                       spellcheck="false"
                       autocapitalize="off" />
            </div>

            <div class="cotlas-field">
                <label for="cotlas-reg-email">
                    <?php esc_html_e( 'Email Address', 'cotlas-admin' ); ?>
                </label>
                <input type="email"
                       id="cotlas-reg-email"
                       name="user_email"
                       required
                       autocomplete="email" />
            </div>

            <?php echo cotlas_auth_honeypot_field(); // phpcs:ignore ?>

            <?php if ( $show_turnstile ) : ?>
                <div class="cotlas-field">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
                </div>
            <?php endif; ?>

            <div class="cotlas-field">
                <button type="submit" class="cotlas-btn cotlas-btn-primary">
                    <span class="cotlas-btn-text"><?php esc_html_e( 'Create Account', 'cotlas-admin' ); ?></span>
                    <span class="cotlas-btn-spinner" hidden aria-hidden="true"></span>
                </button>
            </div>

            <div class="cotlas-auth-links">
                <a href="<?php echo esc_url( $login_url ); ?>">
                    <?php esc_html_e( 'Already have an account? Log in', 'cotlas-admin' ); ?>
                </a>
            </div>

        </form>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// [cotlas_forgot_password]
// ---------------------------------------------------------------------------
add_shortcode( 'cotlas_forgot_password', 'cotlas_forgot_password_shortcode' );

function cotlas_forgot_password_shortcode( $atts ) {
    if ( ! get_option( 'cotlas_auth_enabled' ) ) {
        return '';
    }
    if ( is_user_logged_in() ) {
        return '';
    }

    $atts        = shortcode_atts( [ 'class' => '' ], $atts, 'cotlas_forgot_password' );
    $extra_class = sanitize_html_class( $atts['class'] );
    $login_slug  = get_option( 'cotlas_auth_login_slug' ) ?: 'login';
    $login_url   = home_url( '/' . $login_slug . '/' );

    ob_start();
    ?>
    <div class="cotlas-auth-wrap cotlas-forgot-wrap<?php echo $extra_class ? ' ' . esc_attr( $extra_class ) : ''; ?>"
         data-cotlas-wrap="forgot_password">
        <div class="cotlas-auth-messages" aria-live="polite" role="alert" hidden></div>

        <form class="cotlas-auth-form cotlas-forgot-form"
              data-cotlas-form="forgot_password"
              method="post"
              action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
              novalidate>

            <input type="hidden" name="action"       value="cotlas_forgot_password" />
            <input type="hidden" name="cotlas_nonce" value="<?php echo esc_attr( wp_create_nonce( 'cotlas_auth_nonce' ) ); ?>" />

            <div class="cotlas-field">
                <label for="cotlas-forgot-user">
                    <?php esc_html_e( 'Username or Email Address', 'cotlas-admin' ); ?>
                </label>
                <input type="text"
                       id="cotlas-forgot-user"
                       name="user_login"
                       required
                       autocomplete="email"
                       spellcheck="false"
                       autocapitalize="off" />
            </div>

            <?php echo cotlas_auth_honeypot_field(); // phpcs:ignore ?>

            <div class="cotlas-field">
                <button type="submit" class="cotlas-btn cotlas-btn-primary">
                    <span class="cotlas-btn-text"><?php esc_html_e( 'Send Reset Link', 'cotlas-admin' ); ?></span>
                    <span class="cotlas-btn-spinner" hidden aria-hidden="true"></span>
                </button>
            </div>

            <div class="cotlas-auth-links">
                <a href="<?php echo esc_url( $login_url ); ?>">
                    &larr; <?php esc_html_e( 'Back to Login', 'cotlas-admin' ); ?>
                </a>
            </div>

        </form>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// [cotlas_auth_panel]
//
// Combines login + register + forgot-password into ONE container.
// Links between forms switch panels in-place — no page navigation.
// Perfect for use inside modal/popup overlays.
//
// Attributes:
//   redirect  – post-login redirect URL (same as [cotlas_login])
//   class     – extra CSS class on the outer wrapper
//   panel     – which panel to show first: login (default), register, forgot
// ---------------------------------------------------------------------------
add_shortcode( 'cotlas_auth_panel', 'cotlas_auth_panel_shortcode' );

function cotlas_auth_panel_shortcode( $atts ) {
    if ( ! get_option( 'cotlas_auth_enabled' ) ) {
        return '';
    }
    if ( is_user_logged_in() ) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'redirect' => '',
            'class'    => '',
            'panel'    => 'login',
        ],
        $atts,
        'cotlas_auth_panel'
    );

    $site_key         = get_option( 'turnstile_site_key' );
    $ts_login         = $site_key && get_option( 'cotlas_auth_turnstile_login' );
    $ts_register      = $site_key && get_option( 'cotlas_auth_turnstile_register' );
    $can_register     = (bool) get_option( 'users_can_register' );
    $extra_class      = sanitize_html_class( $atts['class'] );
    $active_panel     = in_array( $atts['panel'], [ 'login', 'register', 'forgot' ], true ) ? $atts['panel'] : 'login';

    $redirect = '';
    if ( $atts['redirect'] ) {
        $redirect = esc_url_raw( $atts['redirect'] );
    } elseif ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore
        $redirect = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore
    }

    $nonce = wp_create_nonce( 'cotlas_auth_nonce' );
    $ajax  = esc_url( admin_url( 'admin-ajax.php' ) );

    ob_start();
    ?>
    <div class="cotlas-auth-panel<?php echo $extra_class ? ' ' . esc_attr( $extra_class ) : ''; ?>"
         data-cotlas-panel-root>

        <?php /* ── LOGIN PANEL ─────────────────────────────────────────── */ ?>
        <div class="cotlas-auth-wrap cotlas-login-wrap"
             data-cotlas-wrap="login"
             data-cotlas-panel="login"
             <?php if ( $active_panel !== 'login' ) echo 'hidden'; ?>>

            <div class="cotlas-auth-messages" aria-live="polite" role="alert" hidden></div>

            <form class="cotlas-auth-form cotlas-login-form"
                  data-cotlas-form="login"
                  method="post"
                  action="<?php echo $ajax; ?>"
                  novalidate>

                <input type="hidden" name="action"          value="cotlas_login" />
                <input type="hidden" name="cotlas_nonce"    value="<?php echo esc_attr( $nonce ); ?>" />
                <input type="hidden" name="cotlas_redirect" value="<?php echo esc_attr( $redirect ); ?>" />

                <div class="cotlas-field">
                    <label for="cotlas-p-login-user"><?php esc_html_e( 'Username or Email', 'cotlas-admin' ); ?></label>
                    <input type="text" id="cotlas-p-login-user" name="log" required
                           autocomplete="username" spellcheck="false" autocapitalize="off" />
                </div>

                <div class="cotlas-field">
                    <label for="cotlas-p-login-pass"><?php esc_html_e( 'Password', 'cotlas-admin' ); ?></label>
                    <input type="password" id="cotlas-p-login-pass" name="pwd" required
                           autocomplete="current-password" />
                </div>

                <div class="cotlas-field cotlas-remember">
                    <label>
                        <input type="checkbox" name="rememberme" value="forever" />
                        <?php esc_html_e( 'Remember me', 'cotlas-admin' ); ?>
                    </label>
                </div>

                <?php echo cotlas_auth_honeypot_field(); // phpcs:ignore ?>

                <?php if ( $ts_login ) : ?>
                    <div class="cotlas-field">
                        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
                    </div>
                <?php endif; ?>

                <div class="cotlas-field">
                    <button type="submit" class="cotlas-btn cotlas-btn-primary">
                        <span class="cotlas-btn-text"><?php esc_html_e( 'Log In', 'cotlas-admin' ); ?></span>
                        <span class="cotlas-btn-spinner" hidden aria-hidden="true"></span>
                    </button>
                </div>

                <div class="cotlas-auth-links">
                    <?php if ( $can_register ) : ?>
                        <a href="#" data-cotlas-switch="register">
                            <?php esc_html_e( 'Create an account', 'cotlas-admin' ); ?>
                        </a>
                    <?php endif; ?>
                    <a href="#" data-cotlas-switch="forgot">
                        <?php esc_html_e( 'Forgot password?', 'cotlas-admin' ); ?>
                    </a>
                </div>

            </form>
        </div>

        <?php /* ── REGISTER PANEL ──────────────────────────────────────── */ ?>
        <?php if ( $can_register ) : ?>
        <div class="cotlas-auth-wrap cotlas-register-wrap"
             data-cotlas-wrap="register"
             data-cotlas-panel="register"
             <?php if ( $active_panel !== 'register' ) echo 'hidden'; ?>>

            <div class="cotlas-auth-messages" aria-live="polite" role="alert" hidden></div>

            <form class="cotlas-auth-form cotlas-register-form"
                  data-cotlas-form="register"
                  method="post"
                  action="<?php echo $ajax; ?>"
                  novalidate>

                <input type="hidden" name="action"          value="cotlas_register" />
                <input type="hidden" name="cotlas_nonce"    value="<?php echo esc_attr( $nonce ); ?>" />
                <input type="hidden" name="cotlas_redirect" value="<?php echo esc_attr( $redirect ); ?>" />

                <div class="cotlas-field">
                    <label for="cotlas-p-reg-username"><?php esc_html_e( 'Username', 'cotlas-admin' ); ?></label>
                    <input type="text" id="cotlas-p-reg-username" name="user_login" required
                           autocomplete="username" spellcheck="false" autocapitalize="off" />
                </div>

                <div class="cotlas-field">
                    <label for="cotlas-p-reg-email"><?php esc_html_e( 'Email Address', 'cotlas-admin' ); ?></label>
                    <input type="email" id="cotlas-p-reg-email" name="user_email" required
                           autocomplete="email" />
                </div>

                <?php echo cotlas_auth_honeypot_field(); // phpcs:ignore ?>

                <?php if ( $ts_register ) : ?>
                    <div class="cotlas-field">
                        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
                    </div>
                <?php endif; ?>

                <div class="cotlas-field">
                    <button type="submit" class="cotlas-btn cotlas-btn-primary">
                        <span class="cotlas-btn-text"><?php esc_html_e( 'Create Account', 'cotlas-admin' ); ?></span>
                        <span class="cotlas-btn-spinner" hidden aria-hidden="true"></span>
                    </button>
                </div>

                <div class="cotlas-auth-links">
                    <a href="#" data-cotlas-switch="login">
                        <?php esc_html_e( 'Already have an account? Log in', 'cotlas-admin' ); ?>
                    </a>
                </div>

            </form>
        </div>
        <?php endif; ?>

        <?php /* ── FORGOT PASSWORD PANEL ───────────────────────────────── */ ?>
        <div class="cotlas-auth-wrap cotlas-forgot-wrap"
             data-cotlas-wrap="forgot_password"
             data-cotlas-panel="forgot"
             <?php if ( $active_panel !== 'forgot' ) echo 'hidden'; ?>>

            <div class="cotlas-auth-messages" aria-live="polite" role="alert" hidden></div>

            <form class="cotlas-auth-form cotlas-forgot-form"
                  data-cotlas-form="forgot_password"
                  method="post"
                  action="<?php echo $ajax; ?>"
                  novalidate>

                <input type="hidden" name="action"       value="cotlas_forgot_password" />
                <input type="hidden" name="cotlas_nonce" value="<?php echo esc_attr( $nonce ); ?>" />

                <div class="cotlas-field">
                    <label for="cotlas-p-forgot-user"><?php esc_html_e( 'Username or Email Address', 'cotlas-admin' ); ?></label>
                    <input type="text" id="cotlas-p-forgot-user" name="user_login" required
                           autocomplete="email" spellcheck="false" autocapitalize="off" />
                </div>

                <?php echo cotlas_auth_honeypot_field(); // phpcs:ignore ?>

                <div class="cotlas-field">
                    <button type="submit" class="cotlas-btn cotlas-btn-primary">
                        <span class="cotlas-btn-text"><?php esc_html_e( 'Send Reset Link', 'cotlas-admin' ); ?></span>
                        <span class="cotlas-btn-spinner" hidden aria-hidden="true"></span>
                    </button>
                </div>

                <div class="cotlas-auth-links">
                    <a href="#" data-cotlas-switch="login">
                        &larr; <?php esc_html_e( 'Back to Login', 'cotlas-admin' ); ?>
                    </a>
                </div>

            </form>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
