<?php
/**
 * Custom WordPress login page branding (logo, colours, background).
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inject custom CSS into the WordPress login page <head>.
 * Styles the background, logo, form card, inputs, and submit button.
 */
function cotlas_custom_login_styles() {
    $background_image = plugin_dir_url( COTLAS_ADMIN_FILE ) . 'assets/img/login-banner.webp';
    $logo_image = plugin_dir_url( COTLAS_ADMIN_FILE ) . 'assets/img/img/cotlas-logo-full.png';
    ?>
    <style type="text/css">
        body.login {
            min-height: 100vh;
            background: #eef3f9 url('<?php echo esc_url($background_image); ?>') center center / cover no-repeat fixed;
            color: #1e293b;
        }

        body.login div#login {
            position: relative;
            width: min(380px, calc(100% - 32px));
            padding: 100px 0 32px;
        }

        body.login h1 {
            margin-bottom: 22px;
        }

        body.login h1 a {
            width: 150px;
            height: 35px;
            margin: 0 auto;
            background: url('<?php echo esc_url($logo_image); ?>') center center / contain no-repeat;
        }

        .login #login_error,
        .login .message,
        .login .success {
            margin: 0 0 18px;
            border: 0;
            border-left: 4px solid #60a5fa;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.88);
            box-shadow: 0 20px 50px rgba(8, 15, 30, 0.16);
            color: #1f2937;
        }

        .login form {
            margin-top: 0;
            padding: 28px 28px 24px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 6px;
            background: #f4faff;
            box-shadow: 0 28px 80px rgba(8, 15, 30, 0.22);
            backdrop-filter: blur(16px);
        }

        .login label {
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        .login form .input,
        .login input[type="text"],
        .login input[type="password"] {
            min-height: 50px;
            margin-top: 6px;
            border: 1px solid #d7deea;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: none;
            color: #0f172a;
            font-size: 15px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .login form .input:focus,
        .login input[type="text"]:focus,
        .login input[type="password"]:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.14);
        }

        .login .button.wp-hide-pw {
            color: #64748b;
        }

        .login .forgetmenot {
            margin-top: 6px;
        }

        .login .forgetmenot label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            font-weight: 500;
        }

        .login .button-primary {
            min-height: 48px;
            padding: 0 20px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.28);
            text-shadow: none;
            font-weight: 700;
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
        }

        .login .button-primary:hover,
        .login .button-primary:focus {
            transform: translateY(-1px);
            filter: brightness(1.04);
            box-shadow: 0 18px 34px rgba(37, 99, 235, 0.34);
        }

        .login #nav,
        .login #backtoblog {
            margin: 18px 0 0;
            padding: 0 4px;
            text-align: left;
        }

        .login #nav a,
        .login #backtoblog a {
            color: #334155;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.18s ease;
        }

        .login #nav a:hover,
        .login #backtoblog a:hover,
        .login #nav a:focus,
        .login #backtoblog a:focus {
            opacity: 0.8;
        }
        .login #backtoblog a:hover, .login #nav a:hover, .login h1 a:hover {
            color: #8bceff;
        }
        .login .privacy-policy-page-link {
            margin-top: 16px;
        }

        .login .privacy-policy-page-link a {
            color: #64748b;
        }

        @media (max-width: 480px) {
            body.login div#login {
                width: calc(100% - 75px);
                padding-top: 120px;
            }

            .login form {
                padding: 22px 18px 18px;
                border-radius: 20px;
            }

            body.login h1 a {
                width: 150px;
                height: 35px;
            }
        }
    </style>
    <?php
}
add_action('login_head', 'cotlas_custom_login_styles');

/** Point the login logo link back to the home page. */
add_filter('login_headerurl', function () {
    return home_url('/');
});

/** Use the site name as the login logo tooltip. */
add_filter('login_headertext', function () {
    return get_bloginfo('name');
});
