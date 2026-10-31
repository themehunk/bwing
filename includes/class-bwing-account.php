<?php
/**
 * Front-end log in, registration and account pages.
 *
 * @package BWing
 */

defined( 'ABSPATH' ) || exit;

class BWing_Account {

	/** @var WP_Error|null */
	private static $errors = null;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 5 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
	}

	/** Members without editing rights do not need the admin bar. */
	public static function admin_bar( $show ) {
		return $show && current_user_can( 'edit_posts' );
	}

	public static function errors_html( $errors ) {
		if ( ! $errors || ! $errors->has_errors() ) {
			return '';
		}
		$o = '<div class="bw-notice bw-notice--error" role="alert"><ul>';
		foreach ( $errors->get_error_messages() as $m ) {
			$o .= '<li>' . wp_kses( $m, array( 'a' => array( 'href' => array() ), 'strong' => array() ) ) . '</li>';
		}
		return $o . '</ul></div>';
	}

	private static function redirect_target( $fallback ) {
		$to = isset( $_REQUEST['redirect_to'] ) ? trim( rawurldecode( wp_unslash( $_REQUEST['redirect_to'] ) ) ) : ''; // phpcs:ignore
		// wp_validate_redirect() accepts '' as valid, so an empty target must fall back explicitly.
		return '' === $to ? $fallback : wp_validate_redirect( $to, $fallback );
	}

	private static function rate_limited( $key, $max ) {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$tkey  = 'bwing_rl_' . $key . '_' . md5( $ip );
		$count = (int) get_transient( $tkey );
		set_transient( $tkey, $count + 1, HOUR_IN_SECONDS );
		return $count >= $max;
	}

	public static function handle() {
		$action = isset( $_POST['bwing_action'] ) ? sanitize_key( $_POST['bwing_action'] ) : ''; // phpcs:ignore
		if ( ! in_array( $action, array( 'login', 'register' ), true ) ) {
			return;
		}
		self::$errors = new WP_Error();
		if ( ! isset( $_POST['_bwnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_bwnonce'] ), 'bwing_' . $action ) ) {
			self::$errors->add( 'nonce', __( 'Your session expired. Please reload the page and try again.', 'bwing' ) );
			return;
		}
		'login' === $action ? self::do_login() : self::do_register();
	}

	private static function do_login() {
		if ( self::rate_limited( 'login', 20 ) ) {
			self::$errors->add( 'limit', __( 'Too many attempts. Please wait a while and try again.', 'bwing' ) );
			return;
		}
		$login = sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) );
		if ( is_email( $login ) ) {
			$u     = get_user_by( 'email', $login );
			$login = $u ? $u->user_login : $login;
		}
		$user = wp_signon( array(
			'user_login'    => $login,
			'user_password' => (string) wp_unslash( $_POST['pwd'] ?? '' ), // phpcs:ignore
			'remember'      => ! empty( $_POST['rememberme'] ),
		), is_ssl() );
		if ( is_wp_error( $user ) ) {
			self::$errors->add( 'login', __( 'Wrong username/email or password.', 'bwing' ) . ' <a href="' . esc_url( wp_lostpassword_url() ) . '">' . __( 'Forgot your password?', 'bwing' ) . '</a>' );
			return;
		}
		wp_safe_redirect( self::redirect_target( bwing_url( 'account' ) ) );
		exit;
	}

	private static function do_register() {
		if ( ! BWing_Settings::get( 'tools', 'allow_registration', 1 ) ) {
			self::$errors->add( 'closed', __( 'Registration is currently closed.', 'bwing' ) );
			return;
		}
		if ( ! empty( $_POST['website2'] ) ) {
			wp_safe_redirect( home_url( '/' ) ); // Honeypot.
			exit;
		}
		if ( self::rate_limited( 'register', 5 ) ) {
			self::$errors->add( 'limit', __( 'Too many sign-ups from your network. Please try again later.', 'bwing' ) );
			return;
		}
		$name  = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ), true );
		$email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
		$pass  = (string) wp_unslash( $_POST['user_pass'] ?? '' ); // phpcs:ignore
		if ( ! $name || ! validate_username( $name ) || mb_strlen( $name ) < 3 ) {
			self::$errors->add( 'user', __( 'Choose a username of at least 3 letters or numbers.', 'bwing' ) );
		} elseif ( username_exists( $name ) ) {
			self::$errors->add( 'user', __( 'That username is taken.', 'bwing' ) );
		}
		if ( ! is_email( $email ) ) {
			self::$errors->add( 'email', __( 'Please enter a valid email address.', 'bwing' ) );
		} elseif ( email_exists( $email ) ) {
			self::$errors->add( 'email', __( 'An account with this email already exists.', 'bwing' ) . ' <a href="' . esc_url( bwing_url( 'login' ) ) . '">' . __( 'Log in', 'bwing' ) . '</a>' );
		}
		if ( strlen( $pass ) < 8 ) {
			self::$errors->add( 'pass', __( 'Use a password of at least 8 characters.', 'bwing' ) );
		}
		if ( self::$errors->has_errors() ) {
			return;
		}
		$id = wp_insert_user( array(
			'user_login' => $name,
			'user_email' => $email,
			'user_pass'  => $pass,
			'role'       => 'subscriber',
		) );
		if ( is_wp_error( $id ) ) {
			self::$errors->add( 'save', $id->get_error_message() );
			return;
		}
		wp_new_user_notification( $id, null, 'admin' );
		wp_set_current_user( $id );
		wp_set_auth_cookie( $id, true, is_ssl() );
		wp_safe_redirect( self::redirect_target( bwing_url( 'account' ) ) );
		exit;
	}

	private static function logged_in_box() {
		$u = wp_get_current_user();
		/* translators: %s display name */
		return '<div class="bw-auth"><p>' . esc_html( sprintf( __( 'You are logged in as %s.', 'bwing' ), $u->display_name ) ) . '</p><div class="bw-auth__actions"><a class="bw-btn bw-btn--primary" href="' . esc_url( bwing_url( 'account' ) ) . '">' . esc_html__( 'My account', 'bwing' ) . '</a><a class="bw-btn bw-btn--ghost" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Log out', 'bwing' ) . '</a></div></div>';
	}

	private static function keep_redirect() {
		$to = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : ''; // phpcs:ignore
		return $to ? '<input type="hidden" name="redirect_to" value="' . esc_attr( $to ) . '">' : '';
	}

	public static function login_form() {
		if ( is_user_logged_in() ) {
			return BWing_Render::wrap( 'auth', self::logged_in_box() );
		}
		$log = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : ''; // phpcs:ignore
		$o   = '<div class="bw-auth"><h1 class="bw-auth__title">' . esc_html__( 'Log in', 'bwing' ) . '</h1><p class="bw-auth__sub">' . esc_html__( 'Welcome back. Log in to submit tools and track their review.', 'bwing' ) . '</p>';
		$o  .= self::errors_html( self::$errors );
		$o  .= '<form class="bw-form" method="post" action="' . esc_url( get_permalink() ) . '">' . wp_nonce_field( 'bwing_login', '_bwnonce', true, false ) . '<input type="hidden" name="bwing_action" value="login">' . self::keep_redirect();
		$o  .= '<label>' . esc_html__( 'Username or email', 'bwing' ) . '<input type="text" name="log" autocomplete="username" required value="' . esc_attr( $log ) . '"></label>';
		$o  .= '<label>' . esc_html__( 'Password', 'bwing' ) . '<input type="password" name="pwd" autocomplete="current-password" required></label>';
		$o  .= '<div class="bw-form__split"><label class="bw-form__check"><input type="checkbox" name="rememberme" value="1"> ' . esc_html__( 'Remember me', 'bwing' ) . '</label><a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Forgot password?', 'bwing' ) . '</a></div>';
		$o  .= '<button class="bw-btn bw-btn--primary bw-btn--block" type="submit">' . esc_html__( 'Log in', 'bwing' ) . '</button></form>';
		if ( BWing_Settings::get( 'tools', 'allow_registration', 1 ) ) {
			$o .= '<p class="bw-auth__switch">' . esc_html__( 'New here?', 'bwing' ) . ' <a href="' . esc_url( bwing_url( 'register' ) ) . '">' . esc_html__( 'Create a free account', 'bwing' ) . '</a></p>';
		}
		return BWing_Render::wrap( 'auth', $o . '</div>' );
	}

	public static function register_form() {
		if ( is_user_logged_in() ) {
			return BWing_Render::wrap( 'auth', self::logged_in_box() );
		}
		if ( ! BWing_Settings::get( 'tools', 'allow_registration', 1 ) ) {
			return BWing_Render::wrap( 'auth', '<div class="bw-auth"><p>' . esc_html__( 'Registration is currently closed.', 'bwing' ) . '</p></div>' );
		}
		$v  = function ( $k ) {
			return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore
		};
		$o  = '<div class="bw-auth"><h1 class="bw-auth__title">' . esc_html__( 'Create your account', 'bwing' ) . '</h1><p class="bw-auth__sub">' . esc_html__( 'Free account to submit AI tools, themes and plugins.', 'bwing' ) . '</p>';
		$o .= self::errors_html( self::$errors );
		$o .= '<form class="bw-form" method="post" action="' . esc_url( get_permalink() ) . '">' . wp_nonce_field( 'bwing_register', '_bwnonce', true, false ) . '<input type="hidden" name="bwing_action" value="register">' . self::keep_redirect();
		$o .= '<div class="bw-form__hp" aria-hidden="true"><label>Website 2<input type="text" name="website2" tabindex="-1" autocomplete="off"></label></div>';
		$o .= '<label>' . esc_html__( 'Username', 'bwing' ) . '<input type="text" name="user_login" autocomplete="username" minlength="3" maxlength="60" required value="' . esc_attr( $v( 'user_login' ) ) . '"></label>';
		$o .= '<label>' . esc_html__( 'Email', 'bwing' ) . '<input type="email" name="user_email" autocomplete="email" required value="' . esc_attr( $v( 'user_email' ) ) . '"></label>';
		$o .= '<label>' . esc_html__( 'Password', 'bwing' ) . '<input type="password" name="user_pass" autocomplete="new-password" minlength="8" required><small>' . esc_html__( 'At least 8 characters.', 'bwing' ) . '</small></label>';
		$o .= '<button class="bw-btn bw-btn--primary bw-btn--block" type="submit">' . esc_html__( 'Create account', 'bwing' ) . '</button></form>';
		$o .= '<p class="bw-auth__switch">' . esc_html__( 'Already have an account?', 'bwing' ) . ' <a href="' . esc_url( bwing_url( 'login' ) ) . '">' . esc_html__( 'Log in', 'bwing' ) . '</a></p>';
		return BWing_Render::wrap( 'auth', $o . '</div>' );
	}

	public static function account() {
		if ( ! is_user_logged_in() ) {
			return self::login_form();
		}
		$u      = wp_get_current_user();
		$status = array(
			'publish' => array( __( 'Live', 'bwing' ), 'ok' ),
			'pending' => array( __( 'In review', 'bwing' ), 'wait' ),
			'draft'   => array( __( 'Draft', 'bwing' ), 'wait' ),
			'trash'   => array( __( 'Not approved', 'bwing' ), 'no' ),
		);
		/* translators: %s name */
		$o  = '<div class="bw-account__head"><div>' . get_avatar( $u->ID, 64, '', '', array( 'class' => 'bw-avatar bw-avatar--img' ) ) . '</div><div><h1 class="bw-auth__title">' . esc_html( sprintf( __( 'Hi, %s', 'bwing' ), $u->display_name ) ) . '</h1><p class="bw-auth__sub">' . esc_html( $u->user_email ) . '</p></div>';
		$o .= '<div class="bw-auth__actions"><a class="bw-btn bw-btn--primary bw-btn--sm" href="' . esc_url( bwing_url( 'submit-tool' ) ) . '">+ ' . esc_html__( 'Submit a tool', 'bwing' ) . '</a><a class="bw-btn bw-btn--ghost bw-btn--sm" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Log out', 'bwing' ) . '</a></div></div>';
		$tools = BWing_Tools::user_tools( $u->ID );
		$o    .= '<section class="bw-panel"><h2>' . esc_html__( 'My submissions', 'bwing' ) . '</h2>';
		if ( $tools ) {
			$o .= '<div class="bw-tablewrap"><table class="bw-table"><thead><tr><th>' . esc_html__( 'Tool', 'bwing' ) . '</th><th>' . esc_html__( 'Submitted', 'bwing' ) . '</th><th>' . esc_html__( 'Status', 'bwing' ) . '</th></tr></thead><tbody>';
			foreach ( $tools as $t ) {
				$s     = isset( $status[ $t->post_status ] ) ? $status[ $t->post_status ] : array( $t->post_status, 'wait' );
				$name  = 'publish' === $t->post_status ? '<a href="' . esc_url( get_permalink( $t ) ) . '">' . esc_html( $t->post_title ) . '</a>' : esc_html( $t->post_title );
				$o    .= '<tr><td><strong>' . $name . '</strong></td><td>' . esc_html( get_the_date( '', $t ) ) . '</td><td><span class="bw-status bw-status--' . esc_attr( $s[1] ) . '">' . esc_html( $s[0] ) . '</span></td></tr>';
			}
			$o .= '</tbody></table></div>';
		} else {
			$o .= '<p class="bw-muted">' . esc_html__( 'You have not submitted any tools yet.', 'bwing' ) . '</p>';
		}
		$o .= '</section>';
		return BWing_Render::wrap( 'account', '<div class="bw-account">' . $o . '</div>' );
	}
}
