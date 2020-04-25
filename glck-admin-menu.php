<?php
/**
 * Plugin Name: _Glück | Admin Menu
 * Description: Opionated, heavily simplified, customised admin menu.
 * Version:     0.1.1
 * Author:      Caspar Hübinger
 * Author URI:  https://caspar.blog
 * Text Domain: glck-admin-menu
 * License:     GNU General Public License v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (C) 2020  Caspar Hübinger
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

namespace Glck\Admin\Menu;

defined( 'ABSPATH' ) || die();

add_action( 'plugins_loaded', function () {

	if ( ! current_user_can( 'delete_plugins' ) ) {
		return false;
	}

	$menu = new The_Menu();
	$menu->init();
});

add_action( 'init', function() {

	if ( ! current_user_can( 'delete_plugins' ) ) {
		return false;
	}

	load_plugin_textdomain( 'glck-admin-menu',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
});

/**
 * Replaces the WordPress admin menu with a customised clone and adds a
 * dedicated admin page listing all third-party menus.
 */
class The_Menu {

	private $menu = [];

	private $submenu = [];

	private $thirdparty_menu = [];

	private $thirdparty_submenu = [];

	private $core_menu = [
		'index.php',
		'edit.php',
		'upload.php',
		'edit.php?post_type=page',
		'edit-comments.php',
		'themes.php',
		'plugins.php',
		'users.php',
		'tools.php',
		'options-general.php',
		'separator1',
		'separator2',
		'separator-last',
		'edit-tags.php?taxonomy=link_category', // Old core Links
		'options.php', // Used for plugin pages sometimes
	];

	private $core_settings_submenu = [
		'options-general.php',
		'options-writing.php',
		'options-reading.php',
		'options-discussion.php',
		'options-media.php',
		'options-permalink.php',
		'options-privacy.php',
	];

	/**
	 * Initiates all interactions with the WordPress admin menu.
	 *
	 * @return bool False if doing AJAX, else true
	 */
	public function init() : bool {

		if ( wp_doing_ajax() ) {
			return false;
		}

		add_action( 'admin_menu', [ $this, 'store_menus' ], PHP_INT_MAX - 2 );
		add_action( 'admin_menu', [ $this, 'replace_menus' ], PHP_INT_MAX -1  );
		add_action( 'admin_menu', [ $this, 'register_settings_page' ], PHP_INT_MAX );
		add_action( 'admin_init', [ $this, 'hide_menus' ], 0 );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_settings_styles' ] );

		return true;
	}

	/**
	 * Adds a non-dismissinble admin notice on the Plugins page with a link to
	 * the page of this plugin that lists all third-party menus.
	 *
	 * @return bool Always true
	 */
	public function add_notice_plugin_page() : bool {
		$text = sprintf( __( 'Looking for plugin settings? %1$sGo to Plugin Settings%2$s', 'glck-admin-menu' ),
			'<a href="' . add_query_arg( 'page', 'wonderland', admin_url( 'options-general.php' ) ) . '">',
			'</a>'
		);

		print $this->render_notice_plugin_page( $text );

		return true;
	}

	/**
	 * Renders the admin notice.
	 *
	 * @param  string $text Admin notices text
	 * @return string       Admin notice HTML
	 */
	public function render_notice_plugin_page( string $text ) : string {
		$html = <<<"EOT"
	<p><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span> $text</p>
EOT;

		return $html;
	}

	/**
	 * Separates core from third-party menus, stores each in a property.
	 *
	 * @return bool Always true
	 */
	public function store_menus() : bool {
		$this->menu = $this->slice_menus( $GLOBALS['menu'] );
		$this->submenu = $this->slice_submenus( $GLOBALS['submenu'] );

		return true;
	}

	/**
	 * Extracts third-party menu items out of the core menu and stores them in
	 * a property.
	 *
	 * @param  array $menu WordPress admin menu
	 * @return array       WordPress admin menu (unchanged)
	 */
	public function slice_menus( array $menu ) : array {
		$thirdparty_menu = [];

		foreach ( $menu as $i => $item ) {
			if ( ! in_array( $item[2], $this->core_menu ) ) {
				$thirdparty_menu[ $i ] = $menu[ $i ];
			}
		}

		$this->thirdparty_menu = $thirdparty_menu;

		if ( 'plugins.php' === $GLOBALS['pagenow'] && ! empty( $thirdparty_menu ) ) {
			add_action( 'pre_current_active_plugins', [ $this, 'add_notice_plugin_page' ], 0 );
		}

		return $menu;
	}

	/**
	 * Extracts third-party submenu items out of the core submenu and stores
	 * them in a property.
	 *
	 * @param  array $submenu WordPress admin submenu
	 * @return array          WordPress admin submenu (unchanged)
	 */
	public function slice_submenus( array $submenu ) : array {
		$thirdparty_submenu = [];

		foreach ( $submenu as $parent => $item ) {
			if ( ! in_array( $parent, $this->core_menu ) ) {
				$thirdparty_submenu[ $parent ] = $item;
			}

			if ( $parent !== 'options-general.php' ) {
				continue;
			}

			foreach ( $item as $i => $submenu_data ) {
				if ( ! in_array( $submenu_data[2], $this->core_settings_submenu ) ) {
					$thirdparty_submenu[ $submenu_data[2] ] = $submenu_data;
				}
			}
		}

		$this->thirdparty_submenu = $thirdparty_submenu;

		return $submenu;
	}

	/**
	 * Defines custom menu array.
	 *
	 * @return array New menu array
	 */
	public function menu() : array {
		$m = $this->menu;
		$s = $this->submenu;

		$m_new = [
			'dashboard'        => $m[2],
			'separator-top'    => $m[4],
			'posts'            => $m[5],
			'media'            => $m[10],
			'pages'            => $m[20],
			'separator-middle' => $m[59],
			'design'           => $m[60],
			'plugins'          => $m[65],
			'users'            => $m[70],
			'tools'            => $m[75],
			'settings'         => $m[80],
			'separator-last'   => $m[99],
		];

		// Adjust some top-level menu item titles, icons and links.
		$m_new['posts'][0]    = __( 'Content', 'glck-admin-menu' ) . $this->indicator_comments_mod(); // 'Pages' → 'Content'
		$m_new['posts'][6]    = 'dashicons-edit';        // Content icon
		$m_new['design'][0]   = __( 'Design', 'glck-admin-menu' );  // 'Appearance' → 'Design'
		$m_new['design'][2]   = $s['themes.php'][6][2];  // Design link: Customizer
		$m_new['design'][6]   = 'dashicons-welcome-widgets-menus'; // Design icon
		$m_new['tools'][2]    = $s['tools.php'][20][2];  // Tools link: Site Health
		$m_new['settings'][0] = __( 'Setup', 'glck-admin-menu' ) . $this->indicator_updates();   // 'Settings' → 'Setup'

		return $m_new;
	}

	/**
	 * Helper: searches for string $needle recursively in array $haystack,
	 * returns first index of `$haystack` where `$needle` was found.
	 *
	 * @param  string $needle   Value to search for recursively
	 * @param  array  $haystack Array to search in for $needle
	 * @param  mixed  $return   Defines output: 'array' returns full subarray,
	 *                          anything else returns key of subarray
	 * @return int|array|bool   Key of sub-array in which $needle was found;
	 *                          if $return passed as `array`: complete sub-array in
	 *                          which $needle was found;
	 *                          `false` if $needle was not found.
	 */
	public function i( array $haystack, string $needle, string $return = 'key' ) {
		if ( ! is_array( $haystack ) ) {
			return false;
		}

		foreach ( $haystack as $key => $substack ) {
			if ( ! is_array( $substack ) ) {
				return false;
			}

			if ( false !== array_search( $needle, $substack ) ) {
				$key = array_search( $substack, $haystack );
				return 'array' === $return ? (array) $haystack[ $key ] : (int) $key;
			}
		}

		return false;
	}

	/**
	 * Defines custom submenu array.
	 *
	 * @return array New submenu array
	 */
	public function submenu() : array {
		$s = $this->submenu;

		$s_new = [
			'posts'               => $s['edit.php'][5],
			'comments'            => $s['edit-comments.php'][0],
			'pages'               => $s['edit.php?post_type=page'][5],
			'media'               => $s['upload.php'][5],
			'customize'           => $s['themes.php'][6],
			'theme-editor'        => $s['themes.php'][ $this->i( $s['themes.php'], 'theme-editor.php' ) ],
			'plugins'             => $s['plugins.php'][5],
			'plugin-editor'       => $s['plugins.php'][15],
			'users'               => $s['users.php'][5],
			'tools-export-content' => $s['tools.php'][15],
			'tools-site-health'   => $s['tools.php'][20],
			'tools-export-data'   => $s['tools.php'][25],
			'tools-erase-data'    => $s['tools.php'][30],
			'updates'             => isset( $s['index.php'][10] ) ? $s['index.php'][10] : null,
			'settings-general'    => $s['options-general.php'][10],
			'settings-writing'    => $s['options-general.php'][15],
			'settings-reading'    => $s['options-general.php'][20],
			'settings-discussion' => $s['options-general.php'][25],
			'settings-media'      => $s['options-general.php'][30],
			'settings-permalinks' => $s['options-general.php'][40],
			'settings-privacy'    => $s['options-general.php'][45],
		];

		// Adjust menu titles.
		$s_new['posts'][0]     = __( 'Posts' );
		$s_new['comments'][0]  = __( 'Comments' ) . $this->indicator_comments_mod();
		$s_new['pages'][0]     = __( 'Pages' );
		$s_new['media'][0]     = __( 'Media' );
		$s_new['customize'][0] = __( 'Customizer' );
		/* translators: menu item text replacement for core Export menu item */
		$s_new['tools-export-content'][0]   = __( 'Export Content', 'glck-admin-menu' );
		$s_new['plugins'][0]   = __( 'Plugins' );
		$s_new['users'][0]     = __( 'Users' );

		return $s_new;
	}

	/**
	 * Assembles the new menu.
	 *
	 * @return bool Always true
	 */
	public function replace_menus() : bool {
		$m = $this->menu();
		$s = $this->submenu();
		$s_core = $this->submenu;

		$menu = [];
		$submenu = [];

		/**
		 * Customise menu.
		*/

		// 0. Dashboard
		$menu[0] = $m['dashboard'];

		// 1. Separator (top)
		$menu[1] = $m['separator-top'];

		// 2. Content
		$menu[2] = $m['posts'];


		// 3. Separator (middle)
		$menu[3] = $m['separator-middle'];

		// 4. Design
		$menu[4] = $m['design'];

		// 5. Tools
		$menu[5] = $m['tools'];

		// 6. Here goes our own settgins page later!

		// 7. Setup
		$menu[7] = $m['settings'];

		// 8. Separator (last)
		$menu[8] = $m['separator-last'];

		// Temporarily add back third-party menus, to be removed later.
		foreach ( $this->thirdparty_menu as $i => $item ) {
			$menu[ 1000 + $i ] = $item;
		}

		/**
		 * Customies submenu.
		 */

		// Content submenu
		// 1. Add complete Posts submenu to fetch any custom taxonomies.
		$submenu[ $menu[2][2] ] = array_values( $s_core['edit.php'] );
		$submenu[ $menu[2][2] ][0][0] = __( 'Posts' );

		// 2. Remove ‘Add New’.
		unset( $submenu[ $menu[2][2] ][1] );
		array_values( $submenu[ $menu[2][2] ] );

		// 3. Add Pages, Comments, and Media.
		if ( 'open' === get_option( 'default_comment_status' ) ) {
			$submenu[ $menu[2][2] ][] = $s['comments'];
		}
		$submenu[ $menu[2][2] ][] = $s['pages'];
		$submenu[ $menu[2][2] ][] = $s['media'];

		// Design submenu
		$submenu[ $menu[4][2] ] = [
			$s['customize'],
			$s['theme-editor'],
			$s['plugin-editor'],
		];

		// Tools submenu
		$submenu[ $menu[5][2] ] = [
			$s['tools-site-health'],
			$s['tools-export-content'],
			$s['tools-export-data'],
			$s['tools-erase-data'],
		];

		// Setup submenu
		$submenu[ $menu[7][2] ] = [
			$s['settings-general'],
			$s['settings-writing'],
			$s['settings-reading'],
			$s['settings-discussion'],
			$s['settings-media'],
			$s['settings-permalinks'],
			$s['settings-privacy'],
			$s['plugins'],
			// Plugin settings
			$s['users'],
			$s['updates'],
		];

		// Temporarily add back third-party submenus, to be removed later.
		foreach ( $this->thirdparty_submenu as $parent => $item ) {
			$submenu[ $parent ] = $item;
		}

		/**
		 * Swap in customised menus.
		 */
		$GLOBALS['menu'] = $menu;
		$GLOBALS['submenu'] = $submenu;

		return true;
	}

	/**
	 * Removes all third-party menu pages from the admin menu.
	 *
	 * @return bool Always true
	 */
	public function hide_menus() : bool {
		foreach ( $this->thirdparty_menu as $i => $item ) {
			if ( ! empty( $item[2] ) ) {
				remove_menu_page( $item[2] );
			}
		}

		return true;
	}

	/**
	 * Checks is any top-level third-party menus have been identified.
	 *
	 * @return bool True if third-party menus exist, else false
	 */
	public function has_third_party_menus() : bool {
		return ! empty( $this->thirdparty_menu );
	}

	/**
	 * @todo Missing desc
	 *
	 * @return bool [description]
	 */
	public function register_settings_page() : bool {
		if ( ! $this->has_third_party_menus() ) {
			return false;
		}

		add_submenu_page(
			'options-general.php',
			__( 'Plugin Settings', 'glck-admin-menu' ),
			__( 'Plugin Settings', 'glck-admin-menu' ),
			'delete_plugins',
			'wonderland',
			[ $this, 'settings_page' ],
			8
		);

		return true;
	}


	/**
	 * Prepares the custom settings page.
	 *
	 * @return bool Always true
	 */
	public function settings_page() : bool {

		// ‘Intelligently’ decides to enqueue or to print the CSS file.
		wp_admin_css( 'glck-admin-menu' );

		$title = get_admin_page_title();

		$content = '';
		$menus = $this->thirdparty_menu;

		foreach ( $menus as $i => $menu ) {
			$content .= sprintf( '<div class="Add-on"><div class="Add-on__inside">%s</div></div>',
				$this->render_thirdparty_menu( $menu[2] )
			);
		}

		$settings_data = $this->thirdparty_submenu;
		$settings_menu = '';
		$settings_box = '';

		foreach ( $settings_data as $slug => $settings_page ) {
			if ( false !== $this->i( $menus, $slug ) ) {
				continue;
			}

			// E.g. Yoast SEO uses a fake submenu to generate its tab nav.
			if ( ! is_string( $settings_page[0] ) ) {
				continue;
			}

			$settings_menu .= sprintf( '<li><a href="%1$s">%2$s</a></li>',
				esc_url( menu_page_url( $slug, false ) ),
				esc_html( $settings_page[0] )
			);
		}

		if ( ! empty( $settings_menu ) ) {
			$settings_box .= '<div class="Add-on"><div class="Add-on__inside">';
			$settings_box .=   '<ul>';
			/* translators: heading for list of various third-party settings pages  */
			$settings_box .=   sprintf( '<li class="Add-on__list-header"><span>%s</span></li>', esc_html__( 'Plugin Settings', 'glck-admin-menu' ) );
			$settings_box .=     $settings_menu;
			$settings_box .=   '</ul>';
			$settings_box .= '</div></div>';

			$content .= $settings_box;

		}

		/* translators: link to Plugins page; 1 = opening link tag, 2 = closing link tag */
		$description = sprintf( __( 'Some of the %1$splugins%2$s you’ve activated bring their own settings. That’s what these are.', 'glck-admin-menu' ),
			'<a href="' . add_query_arg( 'plugin_status', 'active', admin_url( 'plugins.php' ) ) . '">',
			'</a>'
		);

		print $this->render_settings_page( $title, $description, $content );

		return true;
	}

	/**
	 * Renders the custom settings page.
	 *
	 * @param  string $title   Settings page title
	 * @param  string $content Settings page content
	 * @return string          Settings page HTML
	 */
	public function render_settings_page( string $title, string $description, string $content ) : string {
		$html = <<<"EOT"
<div class="wrap Add-ons">
	<h2>$title</h2>
	<p>$description</p>
	<div class="Add-ons__container">
		$content
	</div>
</div>
EOT;

		return $html;
	}

	/**
	 * Renders the menu and submenu for a given menu page slug.
	 *
	 * @param  string $menu_slug Top-level menu page slug (e.g. `gutenberg` or
	 *                           `edit.php?post_type=block_lab`)
	 * @return string            Rendered list HTML
	 */
	public function render_thirdparty_menu( string $menu_slug ) : string {
		$menu_list_html = '';
		$menus = $this->get_third_party_menu_page_link_data();
		$menu = isset( $menus[ $menu_slug ] ) ? $menus[ $menu_slug ] : null;

		$submenu_list_html = '';

		// Looking at you, Jetpack.
		$empty_submenu_list_html = __( 'No entries (yet). Try clicking on the link above, perhaps this plugin needs to be set up properly before menu items appear here.', 'glck-admin-menu' );

		$submenus = $this->get_third_party_submenu_page_link_data();
		$submenu = isset( $submenus[ $menu_slug ] ) ? $submenus[ $menu_slug ] : null;

		if ( $submenu ) {
			foreach ( $submenu as $key => $submenu_data ) {
				$submenu_list_html .= sprintf( '<li><a href="%1$s">%2$s</a></li>',
					$submenu_data['url'],
					$submenu_data['text']
				);
			}
		}

		if ( $menu ) {
			$menu_list_html .= sprintf( '<li class="Add-on__list-header"><a href="%1$s">%2$s</a>%3$s</li>',
				$menu['url'],
				$menu['text'],
				! empty( $submenu_list_html ) ? '<ul>' . $submenu_list_html . '</ul>' : '<p>' . $empty_submenu_list_html . '</p>'
			);

			$menu_list_html = sprintf( '<ul>%s</ul>', $menu_list_html );
		}

		return $menu_list_html;
	}

	/**
	 * Gets the data for third-party menus.
	 *
	 * @return array Slugs, link text and link URLs for third-party menus
	 */
	public function get_third_party_menu_page_link_data() : array {

		$data = [];
		$pages = $this->thirdparty_menu;

		foreach ( $pages as $key => $page ) {

			if ( empty( $page ) ) {
				continue;
			}

			$slug = $page[2];
			$data[ $slug ]['text'] = $page[0];

			// This can be just a keyword like `gutenberg`…
			$data[ $slug ]['url'] = $slug;
/**
 * @todo .php not sufficient for slugs with query params!
 */
			// …so we need to get the actual URL from it…
			if ( ! stripos ( $slug, '.php' ) ) {
				$data[ $slug ]['url'] = menu_page_url( $slug, false );
			}
		}

		return $data;
	}

	/**
	 * Gets the data for third-party submenus.
	 *
	 * @return array Slugs, link text and link URLs for third-party submenus
	 */
	public function get_third_party_submenu_page_link_data() : array {

		$data = [];
		$submenu_pages = $this->thirdparty_submenu;

		foreach ( $submenu_pages as $submenu_slug => $pages ) {
			foreach ( $pages as $page_key => $page ) {

				if ( empty( $page ) ) {
					continue;
				}

				$slug = $page[2];
				$data[ $submenu_slug ][ $page_key ]['text'] = $page[0];

				// This can be just a keyword like `gutenberg`…
				$data[ $submenu_slug ][ $page_key ]['url'] = $slug;

/**
 * @todo .php not sufficient for slugs with query params!
 */

				// …so we need to get the actual URL from it, so let’s check if
				// this is a .php page slug, but beware of external URLs!
				if ( ! stripos ( $slug, '.php' ) && 0 !== stripos ( $slug, 'http' ) ) {
					$data[ $submenu_slug ][ $page_key ]['url'] = menu_page_url( $slug, false );
				}
			}
		}

		return $data;
	}

	/**
	 * Registers styles for custom settings page.
	 *
	 * @return bool True if this is the settings page, else false
	 */
	public function register_settings_styles( string $hook_suffix ) : bool {
		if( $hook_suffix !== 'settings_page_wonderland' ) {
			return false;
		}

		$file_name = 'glck-admin-menu.css';
		$file = plugin_dir_path( __FILE__ ) . $file_name;

		wp_register_style(
			'glck-admin-menu',
			plugin_dir_url( __FILE__ ) . $file_name,
			[],
			$this->get_filemtime( $file )
		);

		return true;
	}

	/**
	 * Helper: retrieves `filemtime()` value of a given file.
	 *
	 * @param  string      $file Full path to file
	 * @return string|null       Value of `filemtime()` for the file, or null
	 */
	public function get_filemtime( string $file ) : ? string {
		return is_readable( $file ) ? filemtime( $file ) : null;
	}

	/**
	 * Renders a comments-in-moderation count indicator.
	 *
	 * @return string Empty string or indicator HTML
	 */
	public function indicator_comments_mod() : string {

		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		$awaiting_mod = wp_count_comments();
		$awaiting_mod = absint( $awaiting_mod->moderated );

		if ( 0 === $awaiting_mod ) {
			return '';
		}

		$awaiting_mod_i18n = number_format_i18n( $awaiting_mod );
		$awaiting_mod_text = sprintf( _n( '%s Comment in moderation', '%s Comments in moderation', $awaiting_mod ), $awaiting_mod_i18n );

		return sprintf( '&nbsp;<span class="awaiting-mod count-%1$s"><span class="pending-count" aria-hidden="true">%2$s</span><span class="comments-in-moderation-text screen-reader-text">%3$s</span></span>',
			$awaiting_mod,
			$awaiting_mod_i18n,
			$awaiting_mod_text
		);
	}

	/**
	 * Adds an update indicator to the top-level Setup (Settings) menu item.
	 *
	 * @return string Empty string or indicator HTML
	 */
	public function indicator_updates() : string {

		if ( is_multisite() || ! current_user_can( 'update_plugins' ) ) {
			return '';
		}

		$update_data = wp_get_update_data();
		$total = $update_data['counts']['total'];
		$html = "&nbsp;<span class='update-plugins count-$total'><span class='plugin-count'>" . number_format_i18n( $total ) . '</span></span>';

		return $total > 1 ? $html : '';
	}
}
