<?php
/**
 * Cotlas Contact Form module.
 *
 * Shortcode: [cotlas_form form_type="contact_form|newsletter|grievance_form" class="" from_name="" from_email="" form_fields="name,email,phone,subject,url,message"]
 *
 * Submissions are handled on init, emails sent via wp_mail() (which respects
 * SMTP settings when enabled). Email templates are configurable per form type.
 *
 * @package CotlasAdmin
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'cotlas_form', 'cotlas_form_shortcode' );
add_action( 'init', 'cotlas_form_handle_submission' );

/**
 * Render the form.
 */
function cotlas_form_shortcode( $atts ) {
	if ( ! get_option( 'cotlas_form_enabled', 1 ) ) {
		return '';
	}
	$atts = shortcode_atts( array(
		'form_type'   => 'contact_form',
		'class'       => get_option( 'cotlas_form_custom_class', '' ),
		'from_name'   => '',
		'from_email'  => '',
		'form_fields' => '',
	), $atts, 'cotlas_form' );

	$form_type = in_array( $atts['form_type'], array( 'contact_form', 'newsletter', 'grievance_form' ), true ) ? $atts['form_type'] : 'contact_form';

	// Determine which fields to show.
	$all_fields = array( 'name', 'email', 'phone', 'url', 'subject', 'message' );
	$global_enabled = array();
	foreach ( $all_fields as $f ) {
		if ( 'url' === $f ) {
			if ( 'grievance_form' === $form_type || get_option( 'cotlas_form_field_url', 0 ) ) {
				$global_enabled[] = $f;
			}
			continue;
		}
		if ( get_option( 'cotlas_form_field_' . $f, 1 ) ) {
			$global_enabled[] = $f;
		}
	}
	if ( '' !== trim( (string) $atts['form_fields'] ) ) {
		$requested = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $atts['form_fields'] ) ) ) );
		$fields = array_values( array_intersect( $requested, $all_fields ) );
	} else {
		$fields = $global_enabled;
	}
	if ( empty( $fields ) ) {
		$fields = array( 'email' );
	}

	// Success/error message from redirect.
	$status = isset( $_GET['cotlas_form_status'] ) ? sanitize_key( wp_unslash( $_GET['cotlas_form_status'] ) ) : '';
	$msg    = '';
	if ( 'success' === $status ) {
		$msg = __( 'Thank you! Your message has been sent.', 'cotlas-admin' );
	} elseif ( 'error' === $status ) {
		$msg = __( 'Something went wrong. Please try again.', 'cotlas-admin' );
	}

	$custom_classes = array();
	foreach ( preg_split( '/\s+/', (string) $atts['class'] ) as $class ) {
		$class = sanitize_html_class( $class );
		if ( '' !== $class ) {
			$custom_classes[] = $class;
		}
	}
	$wrapper_class = trim( 'cotlas-form cotlas-form-' . $form_type . ' ' . implode( ' ', $custom_classes ) );
	$url_local_only = (bool) get_option( 'cotlas_form_url_local_only', 1 );
	$url_example    = $url_local_only ? cotlas_form_url_example() : 'https://';
	$url_label      = $url_local_only
		? sprintf( __( 'URL (paste the news article link, e.g. %s)', 'cotlas-admin' ), $url_example )
		: __( 'URL', 'cotlas-admin' );
	$required_fields = cotlas_form_required_fields( $fields, $form_type );

	ob_start();
	?>
	<div class="<?php echo esc_attr( $wrapper_class ); ?>">
		<?php if ( $msg ) : ?>
			<div class="cotlas-form-notice cotlas-form-notice-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $msg ); ?></div>
		<?php endif; ?>
		<form method="post" class="cotlas-form-form" novalidate>
			<?php wp_nonce_field( 'cotlas_form_submit', 'cotlas_form_nonce' ); ?>
			<input type="hidden" name="cotlas_form_submit" value="1">
			<input type="hidden" name="cotlas_form_type" value="<?php echo esc_attr( $form_type ); ?>">
			<input type="hidden" name="cotlas_form_from_name" value="<?php echo esc_attr( $atts['from_name'] ); ?>">
			<input type="hidden" name="cotlas_form_from_email" value="<?php echo esc_attr( $atts['from_email'] ); ?>">
			<input type="hidden" name="cotlas_form_fields" value="<?php echo esc_attr( implode( ',', $fields ) ); ?>">
			<input type="text" name="cotlas_form_hp" value="" style="display:none" tabindex="-1" autocomplete="off">

			<?php foreach ( $fields as $field ) : ?>
				<?php
				$is_required = in_array( $field, $required_fields, true );
				switch ( $field ) {
					case 'name':
						?>
						<div class="cotlas-form-field">
							<label><?php esc_html_e( 'Name', 'cotlas-admin' ); ?><?php if ( $is_required ) : ?> <span class="cotlas-form-required" aria-hidden="true">*</span><?php endif; ?></label>
							<input type="text" name="cotlas_form_name"<?php if ( $is_required ) : ?> required<?php endif; ?>>
						</div>
						<?php
						break;
					case 'email':
						?>
						<div class="cotlas-form-field">
							<label><?php esc_html_e( 'Email', 'cotlas-admin' ); ?><?php if ( $is_required ) : ?> <span class="cotlas-form-required" aria-hidden="true">*</span><?php endif; ?></label>
							<input type="email" name="cotlas_form_email"<?php if ( $is_required ) : ?> required<?php endif; ?>>
						</div>
						<?php
						break;
					case 'phone':
						?>
						<div class="cotlas-form-field">
							<label><?php esc_html_e( 'Mobile', 'cotlas-admin' ); ?><?php if ( $is_required ) : ?> <span class="cotlas-form-required" aria-hidden="true">*</span><?php endif; ?></label>
							<input type="tel" name="cotlas_form_phone"<?php if ( $is_required ) : ?> required<?php endif; ?>>
						</div>
						<?php
						break;
					case 'subject':
						?>
						<div class="cotlas-form-field">
							<label><?php esc_html_e( 'Subject', 'cotlas-admin' ); ?><?php if ( $is_required ) : ?> <span class="cotlas-form-required" aria-hidden="true">*</span><?php endif; ?></label>
							<input type="text" name="cotlas_form_subject"<?php if ( $is_required ) : ?> required<?php endif; ?>>
						</div>
						<?php
						break;
					case 'message':
						?>
						<div class="cotlas-form-field">
							<label><?php esc_html_e( 'Message', 'cotlas-admin' ); ?><?php if ( $is_required ) : ?> <span class="cotlas-form-required" aria-hidden="true">*</span><?php endif; ?></label>
							<textarea name="cotlas_form_message" rows="5"<?php if ( $is_required ) : ?> required<?php endif; ?>></textarea>
						</div>
						<?php
						break;
					case 'url':
						?>
						<div class="cotlas-form-field">
							<label><?php echo esc_html( $url_label ); ?><?php if ( $is_required ) : ?> <span class="cotlas-form-required" aria-hidden="true">*</span><?php endif; ?></label>
							<input type="text" name="cotlas_form_url" placeholder="<?php echo esc_attr( $url_example ); ?>" autocomplete="off"<?php if ( $is_required ) : ?> required<?php endif; ?>>
						</div>
						<?php
						break;
				}
				?>
			<?php endforeach; ?>

			<div class="cotlas-form-actions">
				<button type="submit" class="cotlas-form-submit"><?php esc_html_e( 'Submit', 'cotlas-admin' ); ?></button>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Determine which of the given fields are marked required.
 *
 * Name and email are required by default; the URL field is always required
 * for the grievance form.
 */
function cotlas_form_required_fields( $fields, $form_type ) {
	$required = array();
	foreach ( $fields as $field ) {
		if ( 'url' === $field && 'grievance_form' === $form_type ) {
			$required[] = $field;
			continue;
		}
		$default = in_array( $field, array( 'name', 'email' ), true ) ? 1 : 0;
		if ( get_option( 'cotlas_form_required_' . $field, $default ) ) {
			$required[] = $field;
		}
	}
	return $required;
}

/**
 * Build an example article URL that matches the site's permalink structure.
 */
function cotlas_form_url_example() {
	$home      = home_url( '/' );
	$structure = (string) get_option( 'permalink_structure', '' );
	if ( '' !== $structure && false !== strpos( $structure, '%postname%' ) ) {
		$example = strtr( $structure, array(
			'%year%'     => gmdate( 'Y' ),
			'%monthnum%' => gmdate( 'm' ),
			'%day%'      => gmdate( 'd' ),
			'%hour%'     => gmdate( 'H' ),
			'%minute%'   => gmdate( 'i' ),
			'%second%'   => gmdate( 's' ),
			'%post_id%'  => '123',
			'%category%' => 'category',
			'%postname%' => 'news-slug-one',
		) );
		$example = preg_replace( '/%[a-z_]+%/', '', $example );
		$example = preg_replace( '#/+#', '/', $example );
		$example = ltrim( (string) $example, '/' );
		if ( '' !== $example ) {
			return $home . $example;
		}
	}
	return $home . 'news-slug-one/';
}

/**
 * Resolve a pasted article URL or slug to the permalink of a published post.
 *
 * Returns an empty string when the input does not belong to a published post
 * on this site.
 */
function cotlas_form_resolve_local_url( $input ) {
	$input = trim( (string) $input );
	if ( '' === $input ) {
		return '';
	}

	$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$home_host = preg_replace( '/^www\./i', '', $home_host );

	if ( preg_match( '#^//#', $input ) ) {
		$input = 'https:' . $input;
	}

	$is_absolute = (bool) preg_match( '#^https?://#i', $input );
	if ( $is_absolute ) {
		$host = (string) wp_parse_url( $input, PHP_URL_HOST );
		$host = preg_replace( '/^www\./i', '', $host );
		if ( '' === $host || strtolower( $host ) !== strtolower( $home_host ) ) {
			return '';
		}
	} elseif ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $input ) ) {
		return '';
	}

	$post_id = $is_absolute ? (int) url_to_postid( $input ) : 0;

	if ( ! $post_id ) {
		$path     = (string) wp_parse_url( $input, PHP_URL_PATH );
		$segments = array_values( array_filter( explode( '/', $path ) ) );
		$slug     = empty( $segments ) ? '' : sanitize_title( end( $segments ) );
		if ( '' !== $slug ) {
			$post_types = array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment' ) ) );
			$post       = get_page_by_path( $slug, OBJECT, $post_types );
			if ( $post instanceof WP_Post ) {
				$post_id = (int) $post->ID;
			}
		}
	}

	if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
		return '';
	}

	$permalink = get_permalink( $post_id );
	return $permalink ? esc_url_raw( $permalink, array( 'http', 'https' ) ) : '';
}

/**
 * Handle form submission.
 */
function cotlas_form_handle_submission() {
	if ( ! get_option( 'cotlas_form_enabled', 1 ) ) {
		return;
	}
	if ( empty( $_POST['cotlas_form_submit'] ) ) {
		return;
	}
	if ( ! isset( $_POST['cotlas_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cotlas_form_nonce'] ) ), 'cotlas_form_submit' ) ) {
		return;
	}
	// Honeypot
	if ( ! empty( $_POST['cotlas_form_hp'] ) ) {
		return;
	}

	$form_type = isset( $_POST['cotlas_form_type'] ) ? sanitize_key( wp_unslash( $_POST['cotlas_form_type'] ) ) : 'contact_form';
	if ( ! in_array( $form_type, array( 'contact_form', 'newsletter', 'grievance_form' ), true ) ) {
		$form_type = 'contact_form';
	}
	$from_name  = isset( $_POST['cotlas_form_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_form_from_name'] ) ) : '';
	$from_email = isset( $_POST['cotlas_form_from_email'] ) ? sanitize_email( wp_unslash( $_POST['cotlas_form_from_email'] ) ) : '';

	$name    = isset( $_POST['cotlas_form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_form_name'] ) ) : '';
	$email   = isset( $_POST['cotlas_form_email'] ) ? sanitize_email( wp_unslash( $_POST['cotlas_form_email'] ) ) : '';
	$phone   = isset( $_POST['cotlas_form_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_form_phone'] ) ) : '';
	$subject = isset( $_POST['cotlas_form_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['cotlas_form_subject'] ) ) : '';
	$message = isset( $_POST['cotlas_form_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cotlas_form_message'] ) ) : '';

	// Validate email.
	if ( '' === $email || ! is_email( $email ) ) {
		cotlas_form_redirect( 'error' );
		return;
	}

	// Sanitize and validate the URL field.
	$raw_url   = ( isset( $_POST['cotlas_form_url'] ) && is_string( $_POST['cotlas_form_url'] ) ) ? trim( wp_unslash( $_POST['cotlas_form_url'] ) ) : '';
	$url       = '';
	$url_valid = true;

	if ( '' !== $raw_url ) {
		if ( get_option( 'cotlas_form_url_local_only', 1 ) ) {
			$url       = cotlas_form_resolve_local_url( $raw_url );
			$url_valid = ( '' !== $url );
		} else {
			if ( ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $raw_url ) ) {
				$raw_url = 'https://' . ltrim( $raw_url, '/' );
			}
			$url       = esc_url_raw( $raw_url, array( 'http', 'https' ) );
			$scheme    = wp_parse_url( $url, PHP_URL_SCHEME );
			$host      = wp_parse_url( $url, PHP_URL_HOST );
			$url_valid = ( '' !== $url && in_array( $scheme, array( 'http', 'https' ), true ) && ! empty( $host ) );
		}
	}

	if ( ! $url_valid || ( 'grievance_form' === $form_type && '' === $url ) ) {
		cotlas_form_redirect( 'error' );
		return;
	}

	// Enforce fields the admin marked as required (server-side).
	$field_values = array(
		'name'    => $name,
		'email'   => $email,
		'phone'   => $phone,
		'url'     => $url,
		'subject' => $subject,
		'message' => $message,
	);
	foreach ( $field_values as $field_name => $field_value ) {
		if ( ! isset( $_POST[ 'cotlas_form_' . $field_name ] ) ) {
			continue;
		}
		$default  = in_array( $field_name, array( 'name', 'email' ), true ) ? 1 : 0;
		$required = ( 'url' === $field_name && 'grievance_form' === $form_type ) || get_option( 'cotlas_form_required_' . $field_name, $default );
		if ( $required && '' === trim( (string) $field_value ) ) {
			cotlas_form_redirect( 'error' );
			return;
		}
	}

	$site_name = get_bloginfo( 'name' );
	$site_url  = home_url();

	$placeholders = array(
		'{name}'       => $name,
		'{email}'      => $email,
		'{phone}'      => $phone,
		'{subject}'    => $subject,
		'{url}'        => $url,
		'{message}'    => $message,
		'{site_name}'  => $site_name,
		'{site_url}'   => $site_url,
	);

	// Determine from address (form overrides SMTP).
	$effective_from_email = '' !== $from_email ? $from_email : get_option( 'cotlas_smtp_from_email', get_option( 'admin_email' ) );
	$effective_from_name  = '' !== $from_name ? $from_name : get_option( 'cotlas_smtp_from_name', $site_name );

	$admin_email = get_option( 'admin_email' );

	// --- Admin notification ---
	$admin_enabled_key = ( 'newsletter' === $form_type ) ? 'cotlas_form_email_admin_newsletter_enabled' : 'cotlas_form_email_admin_contact_enabled';
	$admin_template_key = ( 'newsletter' === $form_type ) ? 'cotlas_form_email_admin_newsletter' : 'cotlas_form_email_admin_contact';

	if ( get_option( $admin_enabled_key, 1 ) ) {
		$template = get_option( $admin_template_key, '' );
		if ( '' === trim( (string) $template ) ) {
			$template = "New submission from {site_name}\n\nName: {name}\nEmail: {email}\nPhone: {phone}\nSubject: {subject}\nMessage:\n{message}";
			if ( 'grievance_form' === $form_type ) {
				$template = "New grievance submission from {site_name}\n\nName: {name}\nEmail: {email}\nPhone: {phone}\nURL: {url}\nSubject: {subject}\nMessage:\n{message}";
			}
		}
		$body = strtr( $template, $placeholders );
		if ( 'newsletter' === $form_type ) {
			$subj = "New newsletter subscription - {$site_name}";
		} elseif ( 'grievance_form' === $form_type ) {
			$subj = "New grievance form submission - {$site_name}";
		} else {
			$subj = "New contact form submission - {$site_name}";
		}
		if ( '' !== $subject ) {
			$subj .= " ({$subject})";
		}
		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>' );
		$from_header = 'From: ' . $effective_from_name . ' <' . $effective_from_email . '>';
		array_unshift( $headers, $from_header );
		wp_mail( $admin_email, $subj, $body, $headers );
	}

	// --- User confirmation ---
	$user_enabled_key = ( 'newsletter' === $form_type ) ? 'cotlas_form_email_user_newsletter_enabled' : 'cotlas_form_email_user_contact_enabled';
	$user_template_key = ( 'newsletter' === $form_type ) ? 'cotlas_form_email_user_newsletter' : 'cotlas_form_email_user_contact';

	if ( get_option( $user_enabled_key, 1 ) ) {
		$template = get_option( $user_template_key, '' );
		if ( '' === trim( (string) $template ) ) {
			$template = "Hello {name},\n\nThank you for contacting {site_name}. We have received your message and will get back to you shortly.\n\nRegards,\n{site_name}";
		}
		$body = strtr( $template, $placeholders );
		$subj = ( 'newsletter' === $form_type ) ? "Welcome to {$site_name}" : "We received your message - {$site_name}";
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_header = 'From: ' . $effective_from_name . ' <' . $effective_from_email . '>';
		array_unshift( $headers, $from_header );
		wp_mail( $email, $subj, $body, $headers );
	}

	// --- Collect submission ---
	if ( get_option( 'cotlas_form_collection_enabled', 0 ) ) {
		cotlas_form_maybe_create_table();
		global $wpdb;
		$wpdb->insert( cotlas_form_table(), array(
			'form_type'    => $form_type,
			'name'         => $name,
			'email'        => $email,
			'phone'        => $phone,
			'url'          => $url,
			'subject'      => $subject,
			'message'      => $message,
			'from_name'    => $effective_from_name,
			'from_email'   => $effective_from_email,
			'submitted_at' => current_time( 'mysql' ),
			'ip'           => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		) );
	}

	cotlas_form_redirect( 'success' );
}

/**
 * Get the submissions table name.
 */
function cotlas_form_table() {
	global $wpdb;
	return $wpdb->prefix . 'cotlas_form_submissions';
}

/**
 * Create the submissions table if it doesn't exist (lazy).
 */
function cotlas_form_maybe_create_table() {
	global $wpdb;
	$table = cotlas_form_table();
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists === $table ) {
		$has_url = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'url' ) );
		if ( ! $has_url ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN url varchar(255) DEFAULT '' AFTER phone" );
		}
		return;
	}
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		form_type varchar(20) NOT NULL DEFAULT 'contact_form',
		name varchar(255) DEFAULT '',
		email varchar(255) DEFAULT '',
		phone varchar(50) DEFAULT '',
		url varchar(255) DEFAULT '',
		subject varchar(255) DEFAULT '',
		message text,
		from_name varchar(255) DEFAULT '',
		from_email varchar(255) DEFAULT '',
		submitted_at datetime NOT NULL,
		ip varchar(45) DEFAULT '',
		PRIMARY KEY (id),
		KEY form_type (form_type),
		KEY submitted_at (submitted_at)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
add_action( 'init', 'cotlas_form_maybe_create_table', 5 );

// Register admin page + admin bar (conditional).
add_action( 'admin_menu', 'cotlas_form_register_submissions_page' );
add_action( 'admin_bar_menu', 'cotlas_form_admin_bar', 100 );

function cotlas_form_register_submissions_page() {
	if ( ! get_option( 'cotlas_form_collection_enabled', 0 ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_submenu_page(
		'cotlas-admin-panel',
		__( 'Form Submissions', 'cotlas-admin' ),
		__( 'Form Submissions', 'cotlas-admin' ),
		'manage_options',
		'cotlas-form-submissions',
		'cotlas_form_submissions_page'
	);
}

function cotlas_form_admin_bar( $wp_admin_bar ) {
	if ( ! get_option( 'cotlas_form_collection_enabled', 0 ) ) {
		return;
	}
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$wp_admin_bar->add_node( array(
		'id'    => 'cotlas-form-submissions',
		'title' => __( 'Form Submission', 'cotlas-admin' ),
		'href'  => admin_url( 'admin.php?page=cotlas-form-submissions' ),
	) );
}

/**
 * Render the submissions page.
 */
function cotlas_form_submissions_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'cotlas-admin' ) );
	}
	global $wpdb;
	$table = cotlas_form_table();

	$contact   = $wpdb->get_results( "SELECT * FROM {$table} WHERE form_type = 'contact_form' ORDER BY submitted_at DESC", ARRAY_A );
	$grievance = $wpdb->get_results( "SELECT * FROM {$table} WHERE form_type = 'grievance_form' ORDER BY submitted_at DESC", ARRAY_A );
	$news      = $wpdb->get_results( "SELECT * FROM {$table} WHERE form_type = 'newsletter' ORDER BY submitted_at DESC", ARRAY_A );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Form Submissions', 'cotlas-admin' ); ?></h1>

		<h2><?php esc_html_e( 'Contact Form Submissions', 'cotlas-admin' ); ?></h2>
		<?php if ( empty( $contact ) ) : ?>
			<p><?php esc_html_e( 'No contact form submissions yet.', 'cotlas-admin' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>URL</th><th>Subject</th><th>Message</th><th>Submitted</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $contact as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['id'] ); ?></td>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td><?php echo esc_html( $row['email'] ); ?></td>
						<td><?php echo esc_html( $row['phone'] ); ?></td>
						<td><?php echo esc_html( $row['url'] ); ?></td>
						<td><?php echo esc_html( $row['subject'] ); ?></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
						<td><?php echo esc_html( $row['submitted_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2 style="margin-top:30px"><?php esc_html_e( 'Grievance Form Submissions', 'cotlas-admin' ); ?></h2>
		<?php if ( empty( $grievance ) ) : ?>
			<p><?php esc_html_e( 'No grievance form submissions yet.', 'cotlas-admin' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>URL</th><th>Subject</th><th>Message</th><th>Submitted</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $grievance as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['id'] ); ?></td>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td><?php echo esc_html( $row['email'] ); ?></td>
						<td><?php echo esc_html( $row['phone'] ); ?></td>
						<td><?php echo esc_html( $row['url'] ); ?></td>
						<td><?php echo esc_html( $row['subject'] ); ?></td>
						<td><?php echo esc_html( $row['message'] ); ?></td>
						<td><?php echo esc_html( $row['submitted_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2 style="margin-top:30px"><?php esc_html_e( 'Newsletter Subscribers', 'cotlas-admin' ); ?></h2>
		<?php if ( empty( $news ) ) : ?>
			<p><?php esc_html_e( 'No newsletter subscribers yet.', 'cotlas-admin' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th>ID</th><th>Name</th><th>Email</th><th>Submitted</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $news as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['id'] ); ?></td>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td><?php echo esc_html( $row['email'] ); ?></td>
						<td><?php echo esc_html( $row['submitted_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
		<?php
	}

/**
 * Redirect helper.
 */
function cotlas_form_redirect( $status ) {
	$referer = wp_get_referer();
	if ( ! $referer ) {
		$referer = home_url();
	}
	$redirect = add_query_arg( 'cotlas_form_status', $status, $referer );
	wp_safe_redirect( $redirect );
	exit;
}