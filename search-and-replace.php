<?php 

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

define('ABSPATH', dirname(__FILE__));

/**
 * Class String_Locator
 */
class String_Locator {
	
	public $string_locator_language = '';
	public $version                 = '2.4.2';
	public $notice                  = array();
	public $failed_edit             = false;
	private $path_to_use            = '';
	private $bad_http_codes         = array( '500' );
	private $bad_file_types         = array( 'rar', '7z', 'zip', 'tar', 'gz', 'jpg', 'jpeg', 'png', 'gif', 'mp3', 'mp4', 'avi', 'wmv' );
	private $excerpt_length         = 25;
	private $max_execution_time     = null;
	private $start_execution_timer  = 0;
	private $max_memory_consumption = 0;

	private $rest_namespace = 'string-locator';

	/**
	 * Construct the plugin
	 */
	function __construct() {
		$this->init();
	}

	/**
	 * The plugin initialization, ready as a stand alone function so it can be instantiated in other
	 * scenarios as well.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function init() {
		/**
		 * Define class variables requiring expressions
		 */
		$this->max_execution_time    = absint( ini_get( 'max_execution_time' ) );
		$this->start_execution_timer = microtime( true );

		if ( $this->max_execution_time > 30 ) {
			$this->max_execution_time = 30;
		}

		$this->set_memory_limit();

		//add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 11 );

		//add_action( 'wp_ajax_string-locator-get-directory-structure', array( $this, 'ajax_get_directory_structure' ) );
		//add_action( 'wp_ajax_string-locator-search', array( $this, 'ajax_file_search' ) );
		//add_action( 'wp_ajax_string-locator-clean', array( $this, 'ajax_clean_search' ) );

	}

	/**
	 * Sets up the memory limit variables.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	function set_memory_limit() {
		$memory_limit = ini_get( 'memory_limit' );

		$this->max_memory_consumption = absint( $memory_limit );

		if ( strstr( $memory_limit, 'k' ) ) {
			$this->max_memory_consumption = ( str_replace( 'k', '', $memory_limit ) * 1000 );
		}
		if ( strstr( $memory_limit, 'M' ) ) {
			$this->max_memory_consumption = ( str_replace( 'M', '', $memory_limit ) * 1000000 );
		}
		if ( strstr( $memory_limit, 'G' ) ) {
			$this->max_memory_consumption = ( str_replace( 'G', '', $memory_limit ) * 1000000000 );
		}
	}

	/**
	 * Create a set of drop-down options for picking one of the available themes.
	 *
	 * @param string $current The current selection option to match against.
	 *
	 * @return string
	 */
	public static function get_themes_options( $current = null ) {
		$options = sprintf(
			'<option value="%s" %s>&mdash; %s &mdash;</option>',
			't--',
			( 't--' === $current ? 'selected="selected"' : '' ),
			esc_html( __( 'All themes', 'string-locator' ) )
		);

		$string_locate_themes = wp_get_themes();

		foreach ( $string_locate_themes as $string_locate_theme_slug => $string_locate_theme ) {
			$string_locate_theme_data = wp_get_theme( $string_locate_theme_slug );
			$string_locate_value      = 't-' . $string_locate_theme_slug;

			$options .= sprintf(
				'<option value="%s" %s>%s</option>',
				$string_locate_value,
				( $current === $string_locate_value ? 'selected="selected"' : '' ),
				$string_locate_theme_data->Name // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			);
		}

		return $options;
	}

	public static function get_edit_form_url() {
		$url_query = String_Locator::edit_form_fields();

		return admin_url(
			sprintf(
				'tools.php?%s',
				build_query( $url_query )
			)
		);
	}

	public static function edit_form_fields( $echo = false ) {
		$fields = array(
			'page'                => ( isset( $_GET['page'] ) ? $_GET['page'] : '' ),
			'edit-file'           => ( isset( $_GET['edit-file'] ) ? $_GET['edit-file'] : '' ),
			'file-reference'      => ( isset( $_GET['file-reference'] ) ? $_GET['file-reference'] : '' ),
			'file-type'           => ( isset( $_GET['file-type'] ) ? $_GET['file-type'] : '' ),
			'string-locator-line' => ( isset( $_GET['string-locator-line'] ) ? $_GET['string-locator-line'] : '' ),
			'string-locator-path' => ( isset( $_GET['string-locator-path'] ) ? $_GET['string-locator-path'] : '' ),
		);

		$field_output = array();

		foreach ( $fields as $label => $value ) {
			$field_output[] = sprintf(
				'<input type="hidden" name="%s" value="%s">',
				esc_attr( $label ),
				esc_attr( $value )
			);
		}

		if ( $echo ) {
			echo implode( "\n", $field_output );
		}

		return $field_output;
	}

	/**
	 * Create a set of drop-down options for picking one of the available plugins.
	 *
	 * @param string $current The current selection option to match against.
	 *
	 * @return string
	 */
	public static function get_plugins_options( $current = null ) {
		$options = sprintf(
			'<option value="%s" %s>&mdash; %s &mdash;</option>',
			'p--',
			( 'p--' === $current ? 'selected="selected"' : '' ),
			esc_html( __( 'All plugins', 'string-locator' ) )
		);

		$string_locate_plugins = get_plugins();

		foreach ( $string_locate_plugins as $string_locate_plugin_path => $string_locate_plugin ) {
			$string_locate_value = 'p-' . $string_locate_plugin_path;

			$options .= sprintf(
				'<option value="%s" %s>%s</option>',
				$string_locate_value,
				( $current === $string_locate_value ? 'selected="selected"' : '' ),
				$string_locate_plugin['Name']
			);
		}

		return $options;
	}

	/**
	 * Create a set of drop-down options for picking one of the available must-use plugins.
	 *
	 * @param string $current The current selection option to match against.
	 *
	 * @return string
	 */
	public static function get_mu_plugins_options( $current = null ) {
		$options = sprintf(
			'<option value="%s" %s>&mdash; %s &mdash;</option>',
			'mup--',
			( 'mup--' === $current ? 'selected="selected"' : '' ),
			esc_html__( 'All must-use plugins', 'string-locator' )
		);

		$string_locate_plugins = get_mu_plugins();

		foreach ( $string_locate_plugins as $string_locate_plugin_path => $string_locate_plugin ) {
			$string_locate_value = 'mup-' . $string_locate_plugin_path;

			$options .= sprintf(
				'<option value="%s" %s>%s</option>',
				$string_locate_value,
				( $current === $string_locate_value ? 'selected="selected"' : '' ),
				$string_locate_plugin['Name']
			);
		}

		return $options;
	}

	/**
	 * Check if there are Must-Use plugins available on this WordPress install.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	public static function has_mu_plugins() {
		$mu_plugin_count = get_mu_plugins();

		if ( count( $mu_plugin_count ) >= 1 ) {
			return true;
		}

		return false;
	}

	/**
	 * Handles the AJAX request to prepare the search hierarchy.
	 *
	 * @return void
	 */
	function ajax_get_directory_structure() {
		
		$scan_path = $this->prepare_scan_path( $_POST['directory'] );
		if ( is_file( $scan_path->path ) ) {
			$files = array( $scan_path->path );
		} else {
			$files = $this->ajax_scan_path( $scan_path->path );
		}

		$file_chunks = array_chunk( $files, 500, true );

		$store = (object) array(
			'scan_path' => $scan_path,
			'search'    => wp_unslash( $_POST['search'] ),
			'directory' => $_POST['directory'],
			'chunks'    => count( $file_chunks ),
			'regex'     => $_POST['regex'],
		);

		$response = array(
			'total'     => count( $files ),
			'current'   => 0,
			'directory' => $scan_path,
			'chunks'    => count( $file_chunks ),
			'regex'     => $_POST['regex'],
		);

		set_transient( 'string-locator-search-overview', $store );
		//update_option( 'string-locator-search-history', array(), false );

		foreach ( $file_chunks as $count => $file_chunk ) {
			set_transient( 'string-locator-search-files-' . $count, $file_chunk );
		}

		returnJsonHttpResponse(true, $response );
	}

	/**
	 * Check if the script is about to exceed the max execution time.
	 *
	 * @since 1.9.0
	 *
	 * @return bool
	 */
	function nearing_execution_limit() {
		// Max execution time is 0 or -1 (infinite) in server config
		if ( 0 === $this->max_execution_time || - 1 === $this->max_execution_time ) {
			return false;
		}

		$built_in_delay = 2;
		$execution_time = ( microtime( true ) - $this->start_execution_timer + $built_in_delay );

		if ( $execution_time >= $this->max_execution_time ) {
			return $execution_time;
		}

		return false;
	}

	/**
	 * Check if the script is about to exceed the server memory limit.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	function nearing_memory_limit() {
		// Check if the memory limit is set t o0 or -1 (infinite) in server config
		if ( 0 === $this->max_memory_consumption || - 1 === $this->max_memory_consumption ) {
			return false;
		}

		// We give our selves a 256k memory buffer, as we need to close off the script properly as well
		$built_in_buffer    = 256000;
		$memory_use         = ( memory_get_usage( true ) + $built_in_buffer );

		if ( $memory_use >= $this->max_memory_consumption ) {
			return $memory_use;
		}

		return false;
	}

	public static function absbool( $value ) {
		if ( is_bool( $value ) ) {
			$bool = $value;
		} else {
			if ( 'false' === $value ) {
				$bool = false;
			} else {
				$bool = true;
			}
		}

		return $bool;
	}

	/**
	 * Search an individual file supplied via AJAX.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	function ajax_file_search() {
		
		$files_per_chunk = 500;
		$response        = array(
			'search'  => array(),
			'filenum' => absint( $_POST['filenum'] ),
		);

		$filenum   = absint( $_POST['filenum'] );
		$next_file = $filenum + 1;

		$next_chunk = ( ceil( ( $next_file ) / $files_per_chunk ) - 1 );
		$chunk      = ( ceil( $filenum / $files_per_chunk ) - 1 );
		if ( $chunk < 0 ) {
			$chunk = 0;
		}
		if ( $next_chunk < 0 ) {
			$next_chunk = 0;
		}

		$scan_data = get_transient( 'string-locator-search-overview' );
		$file_data = get_transient( 'string-locator-search-files-' . $chunk );

		if ( ! isset( $file_data[ $filenum ] ) ) {
			returnJsonHttpResponse(false, array(
					'continue' => false,
					'message'  => sprintf(
						/* translators: %d: The numbered reference to a file being searched. */
						'The file-number, %d, that was sent could not be found.',
						$filenum
					),
				)
			);
		}

		if ( $this->nearing_execution_limit() ) {
			returnJsonHttpResponse(false, array(
					'continue' => false,
					'message'  => sprintf(
						/* translators: %1$d: The time a PHP file can run, as defined by the server configuration. %2$d: The amount of time used by the PHP file so far. */
						'The maximum time your server allows a script to run (%1$d) is too low for the plugin to run as intended, at startup %2$d seconds have passed',
						$this->max_execution_time,
						$this->nearing_execution_limit()
					),
				)
			);
		}
		if ( $this->nearing_memory_limit() ) {
			returnJsonHttpResponse(false,
				array(
					'continue' => false,
					'message'  => sprintf(
						/* translators: %1$d: Current amount of used system memory resources. %2$d: The maximum available system memory. */
						'The memory limit is about to be exceeded before the search has started, this could be an early indicator that your site may soon struggle as well, unfortunately this means the plugin is unable to perform any searches. Current memory consumption: %1$d of %2$d bytes',
						$this->nearing_memory_limit(),
						$this->max_memory_consumption
					),
				)
			);
		}

		$is_regex = false;
		if ( isset( $scan_data->regex ) ) {
			$is_regex = $this->absbool( $scan_data->regex );
		}

		if ( $is_regex ) {
			if ( false === @preg_match( $scan_data->search, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				returnJsonHttpResponse(false,
					array(
						'continue' => false,
						'message'  => sprintf(
							/* translators: %s: The search string used. */
							'Your search string, <strong>%s</strong>, is not a valid pattern, and the search has been aborted.',
							esc_html( $scan_data->search )
						),
					)
				);
			}
		}

		while ( ! $this->nearing_execution_limit() && ! $this->nearing_memory_limit() && isset( $file_data[ $filenum ] ) ) {
			$filenum        = absint( $_POST['filenum'] );
			$search_results = null;
			$next_file      = $filenum + 1;

			$next_chunk = ( ceil( ( $next_file ) / $files_per_chunk ) - 1 );
			$chunk      = ( ceil( $filenum / $files_per_chunk ) - 1 );
			if ( $chunk < 0 ) {
				$chunk = 0;
			}
			if ( $next_chunk < 0 ) {
				$next_chunk = 0;
			}

			if ( ! isset( $file_data[ $filenum ] ) ) {
				$chunk ++;
				$file_data = get_transient( 'string-locator-search-files-' . $chunk );
				continue;
			}

			$file_name = explode( '/', $file_data[ $filenum ] );
			$file_name = end( $file_name );

			/*
			 * Check the file type, if it's an unsupported type, we skip it
			 */
			$file_type = explode( '.', $file_name );
			$file_type = strtolower( end( $file_type ) );

			/*
			 * Scan the file and look for our string, but only if it's an approved file extension
			 */
			$bad_file_types = $this->bad_file_types;
			if ( ! in_array( $file_type, $bad_file_types, true ) ) {
				$search_results = $this->scan_file( $file_data[ $filenum ], $scan_data->search, $file_data[ $filenum ], $scan_data->scan_path->type, '', $is_regex );
			}

			$response['last_file'] = $file_data[ $filenum ];
			$response['filenum']   = $filenum;
			$response['filename']  = $file_name;
			if ( $search_results ) {
				$response['search'][] = $search_results;
			}

			if ( $next_chunk !== $chunk ) {
				$file_data = get_transient( 'string-locator-search-files-' . $next_chunk );
			}

			$response['next_file'] = ( isset( $file_data[ $next_file ] ) ? $file_data[ $next_file ] : '' );

			if ( ! empty( $search_results ) ) {
				$history = array();
				$history = array_merge( $history, $search_results );
				//update_option( 'string-locator-search-history', $history, false );
			}

			$_POST['filenum'] ++;
		}

		returnJsonHttpResponse(true, $response );
	}

	/**
	 * Clean up our options used to help during the search.
	 *
	 * @return void
	 */
	function ajax_clean_search() {
		
		$scan_data = get_transient( 'string-locator-search-overview' );
		for ( $i = 0; $i < $scan_data->chunks; $i ++ ) {
			delete_transient( 'string-locator-search-files-' . $i );
		}

		returnJsonHttpResponse( true, true );
	}

	/**
	 * Create a table row for insertion into the search results list.
	 *
	 * @param array|object $item The table row item.
	 *
	 * @return string
	 */
	public static function prepare_table_row( $item ) {
		if ( ! is_object( $item ) ) {
			$item = (object) $item;
		}

		return sprintf(
			'<tr>
                <td>
                	%s
                	<div class="row-actions">
                		%s
                    </div>
                </td>
                <td>
                	%s
                </td>
                <td>
                	%d
                </td>
                <td>
                	%d
                </td>
            </tr>',
			$item->stringresult,
			( ! current_user_can( 'edit_themes' ) ? '' : sprintf(
				'<span class="edit"><a href="%1$s" aria-label="%2$s">%2$s</a></span>',
				esc_url( $item->editurl ),
				// translators: The row-action edit link label.
				esc_html__( 'Edit', 'string-locator' )
			) ),
			( ! current_user_can( 'edit_themes' ) ? $item->filename_raw : sprintf(
				'<a href="%s">%s</a>',
				esc_url( $item->editurl ),
				esc_html( $item->filename_raw )
			) ),
			esc_html( $item->linenum ),
			esc_html( $item->linepos )
		);
	}

	/**
	 * Create a full table populated with the supplied items.
	 *
	 * @param array $items An array of table rows.
	 * @param array $table_class An array of items to append to the table class along with the defaults.
	 *
	 * @return string
	 */
	public static function prepare_full_table( $items, $table_class = array() ) {
		$table_class = array_merge(
			$table_class,
			array(
				'wp-list-table',
				'widefat',
				'fixed',
				'striped',
				'tools_page_string-locator',
			)
		);

		$table_columns = sprintf(
			'<tr>
				<th scope="col" class="manage-column column-stringresult column-primary">%s</th>
				<th scope="col" class="manage-column column-filename">%s</th>
				<th scope="col" class="manage-column column-linenum">%s</th>
				<th scope="col" class="manage-column column-linepos">%s</th>
			</tr>',
			'String',
			'File',
			'Line number',
			'Line position'
		);

		$table_rows = array();
		foreach ( $items as $item ) {
			$table_rows[] = self::prepare_table_row( $item );
		}

		$table = sprintf(
			'<div class="tablenav top"><br class="clear"></div><table class="%s"><thead>%s</thead><tbody>%s</tbody><tfoot>%s</tfoot></table>',
			implode( ' ', $table_class ),
			$table_columns,
			implode( "\n", $table_rows ),
			$table_columns
		);

		return $table;
	}

	/**
	 * Create an admin edit link for the supplied path.
	 *
	 * @param string $path Path to the file we'er adding a link for.
	 * @param int $line The line in the file where our search result was found.
	 * @param int $linepos The positin in the line where the search result was found.
	 *
	 * @return string
	 */
	function create_edit_link( $path, $line = 0, $linepos = 0 ) {
		$file_type    = 'core';
		$file_slug    = '';
		$content_path = str_replace( '\\', '/', WP_CONTENT_DIR );

		$path  = str_replace( '\\', '/', $path );
		$paths = explode( '/', $path );

		$url_args = array(
			'page=string-locator',
			'edit-file=' . end( $paths ),
		);

		switch ( true ) {
			case ( in_array( 'wp-content', $paths, true ) && in_array( 'plugins', $paths, true ) ):
				$file_type     = 'plugin';
				$content_path .= '/plugins/';
				break;
			case ( in_array( 'wp-content', $paths, true ) && in_array( 'themes', $paths, true ) ):
				$file_type     = 'theme';
				$content_path .= '/themes/';
				break;
		}

		$rel_path  = str_replace( $content_path, '', $path );
		$rel_paths = explode( '/', $rel_path );

		if ( 'core' !== $file_type ) {
			$file_slug = $rel_paths[0];
		}

		$url_args[] = 'file-reference=' . $file_slug;
		$url_args[] = 'file-type=' . $file_type;
		$url_args[] = 'string-locator-line=' . absint( $line );
		$url_args[] = 'string-locator-linepos=' . absint( $linepos );
		$url_args[] = 'string-locator-path=' . urlencode( str_replace( '/', DIRECTORY_SEPARATOR, $path ) );

		$url = admin_url( $this->path_to_use . '?' . implode( '&', $url_args ) );

		return $url;
	}

	/**
	 * Parse the search option to determine what kind of search we are performing and what directory to start in.
	 *
	 * @param string $option The search-type identifier.
	 *
	 * @return bool|object
	 */
	function prepare_scan_path( $option ) {
		$data = array(
			'path' => '',
			'type' => '',
			'slug' => '',
		);

		switch ( true ) {
			case ( 't--' === $option ):
				$data['path'] = WP_CONTENT_DIR . '/themes/';
				$data['type'] = 'theme';
				break;
			case ( strlen( $option ) > 3 && 't-' === substr( $option, 0, 2 ) ):
				$data['path'] = WP_CONTENT_DIR . '/themes/' . substr( $option, 2 );
				$data['type'] = 'theme';
				$data['slug'] = substr( $option, 2 );
				break;
			case ( 'p--' === $option ):
				$data['path'] = WP_CONTENT_DIR . '/plugins/';
				$data['type'] = 'plugin';
				break;
			case ( 'mup--' === $option ):
				$data['path'] = WP_CONTENT_DIR . '/mu-plugins/';
				$data['type'] = 'mu-plugin';
				break;
			case ( strlen( $option ) > 3 && 'p-' === substr( $option, 0, 2 ) ):
				$slug = explode( '/', substr( $option, 2 ) );

				$data['path'] = WP_CONTENT_DIR . '/plugins/' . $slug[0];
				$data['type'] = 'plugin';
				$data['slug'] = $slug[0];
				break;
			case ( 'core' === $option ):
				$data['path'] = ABSPATH;
				$data['type'] = 'core';
				break;
			case ( 'wp-content' === $option ):
				$data['path'] = WP_CONTENT_DIR;
				$data['type'] = 'core';
				break;
			default:
				$data['path'] = dirname(__FILE__);
				$data['type'] = 'core';
				break;
			
		}

		if ( empty( $data['path'] ) ) {
			return false;
		}

		return (object) $data;
	}

	/**
	 * Check if a file path is valid for editing.
	 *
	 * @param string $path Path to file.
	 *
	 * @return bool
	 */
	function is_valid_location( $path ) {
		$valid   = true;
		$path    = str_replace( array( '/' ), array( DIRECTORY_SEPARATOR ), stripslashes( $path ) );
		$abspath = str_replace( array( '/' ), array( DIRECTORY_SEPARATOR ), ABSPATH );

		// Check that it is a valid file we are trying to access as well.
		if ( ! file_exists( $path ) ) {
			$valid = false;
		}

		if ( empty( $path ) ) {
			$valid = false;
		}
		if ( stristr( $path, '..' ) ) {
			$valid = false;
		}
		if ( ! stristr( $path, $abspath ) ) {
			$valid = false;
		}

		return $valid;
	}

	/**
	 * Function for including the actual plugin Admin UI page.
	 *
	 * @return mixed
	 */
	function options_page() {
		/**
		 * Don't load anything if the user can't edit themes any way
		 */
		if ( ! current_user_can( 'update_core' ) ) {
			return false;
		}

		/**
		 * Show the edit page if;
		 * - The edit file path query var is set
		 * - The edit file path query var isn't empty
		 * - The edit file path query var does not contains double dots (used to traverse directories)
		 * - The user is capable of editing files.
		 */
		if ( isset( $_GET['string-locator-path'] ) && $this->is_valid_location( $_GET['string-locator-path'] ) && current_user_can( 'edit_themes' ) ) {
			include_once( dirname( __FILE__ ) . '/../editor.php' );
		} else {
			include_once( dirname( __FILE__ ) . '/../search.php' );
		}
	}

	function admin_body_class( $class ) {
		if ( isset( $_GET['string-locator-path'] ) && $this->is_valid_location( $_GET['string-locator-path'] ) && current_user_can( 'edit_themes' ) ) {
			$class .= ' file-edit-screen';
		}

		return $class;
	}

	/**
	 * Check for inconsistencies in brackets and similar.
	 *
	 * @param string $start Start delimited.
	 * @param string $end End delimiter.
	 * @param string $string The string to scan.
	 *
	 * @return array
	 */
	function smart_scan( $start, $end, $string ) {
		$opened = array();

		$lines = explode( "\n", $string );
		for ( $i = 0; $i < count( $lines ); $i ++ ) {
			if ( stristr( $lines[ $i ], $start ) ) {
				$opened[] = $i;
			}
			if ( stristr( $lines[ $i ], $end ) ) {
				array_pop( $opened );
			}
		}

		return $opened;
	}

	/**
	 * Handler for storing the content of the code editor.
	 *
	 * Also runs over the Smart-Scan if enabled.
	 *
	 * @return void|array
	 */
	function editor_save( $request ) {
		$_POST = $request->get_params();

		$check_loopback = isset( $_POST['string-locator-loopback-check'] );
		$do_smart_scan  = isset( $_POST['string-locator-smart-edit'] );

		if ( $this->is_valid_location( $_POST['string-locator-path'] ) ) {
			$path    = urldecode( $_POST['string-locator-path'] );
			$content = stripslashes( $_POST['string-locator-editor-content'] );

			/**
			 * Send an error notice if the file isn't writable
			 */
			if ( ! is_writeable( $path ) ) {
				$this->notice[] = array(
					'type'    => 'error',
					'message' => __( 'The file could not be written to, please check file permissions or edit it manually.', 'string-locator' ),
				);

				return array(
					'notices' => $this->notice,
				);
			}

			/**
			 * If enabled, run the Smart-Scan on the content before saving it
			 */
			if ( $do_smart_scan ) {
				$open_brace  = substr_count( $content, '{' );
				$close_brace = substr_count( $content, '}' );
				if ( $open_brace !== $close_brace ) {
					$this->failed_edit = true;

					$opened = $this->smart_scan( '{', '}', $content );

					foreach ( $opened as $line ) {
						$this->notice[] = array(
							'type'    => 'error',
							'message' => sprintf(
								// translators: 1: Line number with an error.
								__( 'There is an inconsistency in the opening and closing braces, { and }, of your file on line %s', 'string-locator' ),
								'<a href="#" class="string-locator-edit-goto" data-goto-line="' . ( $line + 1 ) . '">' . ( $line + 1 ) . '</a>'
							),
						);
					}
				}

				$open_bracket  = substr_count( $content, '[' );
				$close_bracket = substr_count( $content, ']' );
				if ( $open_bracket !== $close_bracket ) {
					$this->failed_edit = true;

					$opened = $this->smart_scan( '[', ']', $content );

					foreach ( $opened as $line ) {
						$this->notice[] = array(
							'type'    => 'error',
							'message' => sprintf(
								// translators: 1: Line number with an error.
								__( 'There is an inconsistency in the opening and closing braces, [ and ], of your file on line %s', 'string-locator' ),
								'<a href="#" class="string-locator-edit-goto" data-goto-line="' . ( $line + 1 ) . '">' . ( $line + 1 ) . '</a>'
							),
						);
					}
				}

				$open_parenthesis  = substr_count( $content, '(' );
				$close_parenthesis = substr_count( $content, ')' );
				if ( $open_parenthesis !== $close_parenthesis ) {
					$this->failed_edit = true;

					$opened = $this->smart_scan( '(', ')', $content );

					foreach ( $opened as $line ) {
						$this->notice[] = array(
							'type'    => 'error',
							'message' => sprintf(
								// translators: 1: Line number with an error.
								__( 'There is an inconsistency in the opening and closing braces, ( and ), of your file on line %s', 'string-locator' ),
								'<a href="#" class="string-locator-edit-goto" data-goto-line="' . ( $line + 1 ) . '">' . ( $line + 1 ) . '</a>'
							),
						);
					}
				}

				if ( $this->failed_edit ) {
					return array(
						'notices' => $this->notice,
					);
				}
			}

			$original = file_get_contents( $path );

			$this->write_file( $path, $content );

			/**
			 * Check the status of the site after making our edits.
			 * If the site fails, revert the changes to return the sites to its original state
			 */
			if ( $check_loopback ) {
				$header = wp_remote_head( site_url() );

				if ( ! is_wp_error( $header ) && 301 === (int) $header['response']['code'] ) {
					$header = wp_remote_head( $header['headers']['location'] );
				}

				$bad_http_check = apply_filters( 'string_locator_bad_http_codes', $this->bad_http_codes );
			}

			if ( $check_loopback && is_wp_error( $header ) ) {
				$this->failed_edit = true;
				$this->write_file( $path, $original );

				// Likely loopback error, so be useful in our errors.
				if ( 'http_request_failed' === $header->get_error_code() ) {
					return array(
						'notices' => array(
							array(
								'type'    => 'error',
								'message' => __( 'Your changes were not saved, as a check of your site could not be completed afterwards. This may be due to a <a href="https://wordpress.org/support/article/loopbacks/">loopback</a> error.', 'string-locator' ),
							),
						),
					);
				}

				// Fallback error message here.
				return array(
					'notices' => array(
						array(
							'type'    => 'error',
							'message' => $header->get_error_message(),
						),
					),
				);
			} elseif ( $check_loopback && in_array( $header['response']['code'], $bad_http_check, true ) ) {
				$this->failed_edit = true;
				$this->write_file( $path, $original );

				return array(
					'notices' => array(
						array(
							'type'    => 'error',
							'message' => __( 'A 500 server error was detected on your site after updating your file. We have restored the previous version of the file for you.', 'string-locator' ),
						),
					),
				);
			} else {
				return array(
					'notices' => array(
						array(
							'type'    => 'success',
							'message' => __( 'The file has been saved', 'string-locator' ),
						),
					),
				);
			}
		} else {
			return array(
				'notices' => array(
					array(
						'type'    => 'error',
						'message' => sprintf(
							// translators: %s: The file location that was sent.
							__( 'The file location provided, <strong>%s</strong>, is not valid.', 'string-locator' ),
							$_POST['string-locator-path']
						),
					),
				),
			);
		}
	}

	/**
	 * When editing a file, this is where we write all the new content.
	 * We will break early if the user isn't allowed to edit files.
	 *
	 * @param string $path The path to the file.
	 * @param string $content The content to write to the file.
	 *
	 * @return void
	 */
	private function write_file( $path, $content ) {
		if ( ! current_user_can( 'edit_themes' ) ) {
			return;
		}

		// Verify the location is valid before we try using it.
		if ( ! $this->is_valid_location( $path ) ) {
			return;
		}

		if ( true ) {
			$content = preg_replace( '/\?>$/si', '', trim( $content ), - 1, $replaced_strings );

			if ( $replaced_strings >= 1 ) {
				$this->notice[] = array(
					'type'    => 'error',
					'message' => __( 'We detected a PHP code tag ending, this has been automatically stripped out to help prevent errors in your code.', 'string-locator' ),
				);
			}
		}

		$file        = fopen( $path, 'w' );
		$lines       = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $content ) );
		$total_lines = count( $lines );

		for ( $i = 0; $i < $total_lines; $i ++ ) {
			$write_line = $lines[ $i ];

			if ( ( $i + 1 ) < $total_lines ) {
				$write_line .= PHP_EOL;
			}

			fwrite( $file, $write_line );
		}

		fclose( $file );
	}

	/**
	 * Hook the admin notices and loop over any notices we've registered in the plugin.
	 *
	 * @return void
	 */
	function admin_notice() {
		if ( ! empty( $this->notice ) ) {
			foreach ( $this->notice as $note ) {
				printf(
					'<div class="%s"><p>%s</p></div>',
					esc_attr( $note['type'] ),
					$note['message']
				);
			}
		}
	}

	/**
	 * Scan through an individual file to look for occurrences of £string.
	 *
	 * @param string $filename The path to the file.
	 * @param string $string The search string.
	 * @param mixed $location The file location object/string.
	 * @param string $type File type.
	 * @param string $slug The plugin/theme slug of the file.
	 * @param boolean $regex Should a regex search be performed.
	 *
	 * @return array
	 */
	function scan_file( $filename, $string, $location, $type, $slug, $regex = false ) {
		if ( empty( $string ) || ! is_file( $filename ) ) {
			return array();
		}
		$output      = array();
		$linenum     = 0;
		$match_count = 0;

		if ( ! is_object( $location ) ) {
			$path     = $location;
			$location = explode( DIRECTORY_SEPARATOR, $location );
			$file     = end( $location );
		} else {
			$path = $location->getPathname();
			$file = $location->getFilename();
		}

		/*
		 * Check if the filename matches our search pattern
		 */
		if ( stristr( $file, $string ) || ( $regex && preg_match( $string, $file ) ) ) {
			$relativepath = str_replace(
				array(
					ABSPATH,
					'\\',
					'/',
				),
				array(
					'',
					DIRECTORY_SEPARATOR,
					DIRECTORY_SEPARATOR,
				),
				$path
			);
			$match_count ++;

			//$editurl = $this->create_edit_link( $path, $linenum );
			$editurl = '#';

			$path_string = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $editurl ),
				esc_html( $relativepath )
			);

			$output[] = array(
				'ID'           => $match_count,
				'linenum'      => sprintf(
					'[%s]',
					'Filename matches search'
				),
				'linepos'      => '',
				'path'         => $path,
				'filename'     => $path_string,
				'filename_raw' => $relativepath,
				'editurl'      => false,
				'stringresult' => $file,
			);
		}

		$readfile = @fopen( $filename, 'r' );
		if ( $readfile ) {
			while ( ( $readline = fgets( $readfile ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				$string_preview_is_cut = false;
				$linenum ++;
				/**
				 * If our string is found in this line, output the line number and other data
				 */
				if ( ( ! $regex && stristr( $readline, $string ) ) || ( $regex && preg_match( $string, $readline, $match, PREG_OFFSET_CAPTURE ) ) ) {
					/**
					 * Prepare the visual path for the end user
					 * Removes path leading up to WordPress root and ensures consistent directory separators
					 */
					$relativepath = str_replace(
						array(
							ABSPATH,
							'\\',
							'/',
						),
						array(
							'',
							DIRECTORY_SEPARATOR,
							DIRECTORY_SEPARATOR,
						),
						$path
					);
					$match_count ++;

					if ( $regex ) {
						$str_pos = $match[0][1];
					} else {
						$str_pos = stripos( $readline, $string );
					}

					/**
					 * Create the URL to take the user to the editor
					 */
					//$editurl = $this->create_edit_link( $path, $linenum, $str_pos );
					$editurl = '#';

					$string_preview = $readline;
					if ( strlen( $string_preview ) > ( strlen( $string ) + $this->excerpt_length ) ) {
						$string_location = strpos( $string_preview, $string );

						$string_location_start = $string_location - $this->excerpt_length;
						if ( $string_location_start < 0 ) {
							$string_location_start = 0;
						}

						$string_location_end = ( strlen( $string ) + ( $this->excerpt_length * 2 ) );
						if ( $string_location_end > strlen( $string_preview ) ) {
							$string_location_end = strlen( $string_preview );
						}

						$string_preview        = substr( $string_preview, $string_location_start, $string_location_end );
						$string_preview_is_cut = true;
					}

					if ( $regex ) {
						$string_preview = preg_replace( preg_replace( '/\/(.+)\//', '/($1)/', $string ), '<strong>$1</strong>', $string_preview );
					} else {
						$string_preview = preg_replace( '/(' . $string . ')/i', '<strong>$1</strong>', $string_preview );
					}
					if ( $string_preview_is_cut ) {
						$string_preview = sprintf(
							'&hellip;%s&hellip;',
							$string_preview
						);
					}

					$path_string = sprintf(
						'<a href="%s">%s</a>',
						$editurl,
						$relativepath
					);

					$output[] = array(
						'ID'           => $match_count,
						'linenum'      => $linenum,
						'linepos'      => $str_pos,
						'path'         => $path,
						'filename'     => $path_string,
						'filename_raw' => $relativepath,
						'editurl'      => false,
						'stringresult' => $string_preview,
					);
				}
			}

			fclose( $readfile );
		} else {
			/**
			 * The file was unreadable, give the user a friendly notification
			 */
			$output[] = array(
				'linenum'      => '#',
				// translators: 1: Filename.
				'filename'     => sprintf( 'Could not read file: %s', $filename ),
				'stringresult' => '',
			);
		}

		return $output;
	}

	function ajax_scan_path( $path ) {
		$files = array();

		$paths = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $paths as $name => $location ) {
			if ( is_dir( $location->getPathname() ) ) {
				continue;
			}

			$files[] = $location->getPathname();
		}

		return $files;
	}
}

function returnJsonHttpResponse($success, $data)
{
    // remove any string that could create an invalid JSON 
    // such as PHP Notice, Warning, logs...
    ob_clean();

    // this will clean up any previously added headers, to start clean
    header_remove(); 

    // Set the content type to JSON and charset 
    // (charset can be set to something else)
    header("Content-type: application/json; charset=utf-8");

    // Set your HTTP response code, 2xx = SUCCESS, 
    // anything else will be error, refer to HTTP documentation
    if ($success) {
        http_response_code(200);
    } else {
        http_response_code(500);
    }
    
    // encode your PHP Object or Array into a JSON string.
    // stdClass or array
    echo json_encode([ 'success' => $success, 'data' => $data]);

    // making sure nothing is added
    exit();
}

function absint( $maybeint ) {
    return abs( (int) $maybeint );
}
function wp_unslash( $value ) {
    return stripslashes( $value );
}

function set_transient($key, $data) {
	$_SESSION[$key] = $data;
}
function get_transient($key) {
	return $_SESSION[$key];
}
function delete_transient($key) {
	unset($_SESSION[$key]);
}


$search_regex = false;

if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	$SL = new String_Locator();
	if(isset($_POST['action'])) {
		if($_POST['action'] === 'string-locator-get-directory-structure') {
			$SL->ajax_get_directory_structure();		
		} else if($_POST['action'] === 'string-locator-search') {
			$SL->ajax_file_search();
		} else if($_POST['action'] === 'string-locator-clean') {
			$SL->ajax_clean_search();
		}
	}
	
	$search_regex = isset($_POST['string-locator-regex']);

	// if(isset($_POST)) {
	// 	$errors = [];
	// 	if(!isset($_POST['search']) && !empty($_POST['search'])) {
	// 		$errors = [
	// 			'search' => 'Search keyword is required'
	// 		];
	// 		returnJsonHttpResponse(false, [ $errors, 'message' => 'Search keyword is missing']);
	// 	}

	// 	returnJsonHttpResponse(true, [ 'message' => 'Message worked successfully']);
	// }	
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Search and Replace Script</title>
	<style type="text/css">
		body { padding: 0; margin: 0; }
		.wrap { max-width: 1154px; margin: auto;padding-left: 15px;padding-right: 15px;}
		.wrap>h1{margin-bottom:15px; margin-top:  10px;}
		input[type="text"] {
		    border: 1px solid #c2c6c9;
		    font-size: 1.2rem;
		    padding: 5px 10px;
		    border-radius: 3px;
		}
		label.sr-only {
    		display: none;
		}
		input[type=submit] {
		    border: 1px solid #f0f0f0;
		    padding: 10px 30px;
		    color: #fff;
		    background-color: #5a85bd;
		    border: none;
		    border-radius: 3px;
		}
		.btn_check input {
		  display: none;
		}
		.btn_check i {
		  width: 14px;
		  text-align: left;
		}
		.btn_check label{
		    display: inline-block;
		    padding: 7px 20px;
			border-radius: 25px;
		    text-decoration: none;
		    color: #333;
		    border: 1px solid #333;
		}

		.btn_check:hover label {
		    background-image: -webkit-linear-gradient(45deg, #FFC107 0%, #f76a35 100%);
		    background-image: linear-gradient(45deg, #FFC107 0%, #f76a35 100%);
		}
		.btn_check input:checked + label {
		  color: #ffffff;
		  background-color: #333;
		  -webkit-box-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
		  box-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
		}
		
		.string-locator-italics{font-style:italic}
		.string-locator-feedback{background:#fff;display:inline-block;width:100%;text-align:center}
		.string-locator-feedback.hide{display:none}
		.string-locator-feedback progress{width:100%;height:1.5em}
		.string-locator-feedback #string-locator-feedback-text{display:inline-block;text-align:center;width:100%}
		body.tools_page_string-locator.file-edit-screen #wpcontent{padding-left:0}
		body.tools_page_string-locator.file-edit-screen #wpfooter{display:none}
		body.tools_page_string-locator.file-edit-screen #wpbody-content{padding-bottom:0}
		table.tools_page_string-locator{display:none}
		table.tools_page_string-locator.restore{display:table}
		.string-locator-editor-wrapper{width:100%;height:100%;display:grid;grid-gap:0;grid-template-columns:80% 20%}
		.string-locator-editor-wrapper .notice,
		.string-locator-editor-wrapper .string-locator-header{
			height:40px;padding:4px 2px;border-bottom:1px solid #e2e4e7;background:#fff;display:flex;flex-direction:row;align-items:stretch;justify-content:space-between;z-index:30;right:0;left:0;top:0;position:sticky
		}
		#string-locator-search-form {
    display: flex;
    justify-content: space-between;
    align-content: center;
    border: 1px solid #e2e4e7;
    padding: 10px 15px;
    box-shadow: 1px 1px 4px #e2e4e7;
}

		@media (min-width: 600px){
			.string-locator-editor-wrapper .notice,
			.string-locator-editor-wrapper .string-locator-header{position:fixed;padding:8px;top:46px}
		}
		@media (min-width: 782px){
			.string-locator-editor-wrapper .notice,
			.string-locator-editor-wrapper .string-locator-header{top:32px;left:160px}
		}
		.string-locator-editor-wrapper .notice .title,
		.string-locator-editor-wrapper .string-locator-header .title{font-size:16px}
		.string-locator-editor-wrapper .notice>div,
		.string-locator-editor-wrapper .string-locator-header>div{display:inline-flex;align-items:center}
		.string-locator-editor-wrapper .notice .button,
		.string-locator-editor-wrapper .string-locator-header .button{margin:0 3px 0 12px}
		.string-locator-editor-wrapper .notice{height:fit-content;margin:0;top:89px;display:block}
		.string-locator-editor-wrapper .notice.is-dismissible{position:sticky}
		.string-locator-editor-wrapper .string-locator-editor{margin-top:57px}
		.string-locator-editor-wrapper .string-locator-sidebar{margin-top:57px;background:#fff;border-left:1px solid #e2e4e7}
		.string-locator-editor-wrapper .string-locator-sidebar .string-locator-panel{border-top:1px solid #e2e4e7;padding-bottom:10px}
		.string-locator-editor-wrapper .string-locator-sidebar .string-locator-panel:first-of-type{border-top:none}
		.string-locator-editor-wrapper .string-locator-sidebar .string-locator-panel .title{color:#191e23;border:none;box-shadow:none;font-weight:600;padding:15px;margin:0}
		.string-locator-editor-wrapper .string-locator-sidebar .string-locator-panel .row{padding:5px 15px}
		.string-locator-editor-wrapper .CodeMirror .CodeMirror-activeline .CodeMirror-activeline-background{background-color:#cfe4ff}
		.string-locator-editor-wrapper .CodeMirror .CodeMirror-activeline .CodeMirror-gutter-background{background-color:#cfe4ff}

	</style>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/react/17.0.2/umd/react.production.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/17.0.2/umd/react-dom.production.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/underscore.js/1.13.1/underscore.min.js"></script>
	<script type="text/javascript">
		window._templatize = window._templatize || ( function( window, document, _, undefined ) {
	'use strict';

	/**
	 * Utilities similar to WordPress' wp.template(). Provides option to load from a <prefix><id> script tag,
	 * or to pass in arbitrary html.
	 *
	 * Compiled templates are memoized and cached for reuse, based on the tmplName.
	 *
	 * Example usage:
	 *
	 * var template = window._templatize(); // Instantiate the template object to a var.
	 * var html = template( 'hello-world', { hello: 'Hello World' }, '<h1>{{ data.hello }}</h1>' );
	 *
	 * // The 'hello-world' template is now cached, so we can simply reference by ID, rather than passing the HTML in again.
	 * var html2 = template( 'hello-world', { hello: 'Hello Universe' } );
	 *
	 * @param  {string}   prefix  The script tag id prefix. Defaults to "tmpl-".
	 * @param  {function} gethtml A function to fetch an element by id from the dom.
	 */
	return function( prefix, gethtml ) {
		gethtml = gethtml || ( function( $ ) {
			return function( id ) {
				id = document.getElementById( id );
				return $ ? $( id ).html() : id.innerHTML;
			};
		} )( window.jQuery || window.$ );

		/*
		 * Underscore's default ERB-style templates are incompatible with PHP
		 * when asp_tags is enabled, so WordPress uses Mustache-inspired templating syntax.
		 *
		 * @see trac ticket: https://core.trac.wordpress.org/ticket/22344.
		 */
		var options = {
			evaluate    : /<#([\s\S]+?)#>/g,
			interpolate : /\{\{\{([\s\S]+?)\}\}\}/g,
			escape      : /\{\{([^\}]+?)\}\}(?!\})/g,
			variable    : 'data'
		};

		/**
		 * Fetch a JavaScript template for a string of html, id it, then return a templating function for it.
		 *
		 * @param  {string} id   A string that corresponds to the html cache array.
		 * @return {function}    A function that lazily-compiles the template requested.
		 */
		var htmlTemplate = _.memoize( function( id ) {
			var compiled;
			return function( data ) {
				if ( ! compiled ) {
					compiled = _.template( template.strings[ id ], options );
					delete template.strings[ id ];
				}

				return compiled( data );
			};
		} );

		/**
		 * wp.template() replacement.
		 *
		 * Fetch a JavaScript template for an id, and return a templating function for it.
		 *
		 * @param  {string} id   A string that corresponds to a DOM element with an id prefixed with `prefix` ("tmpl-" by default).
		 * @return {function}    A function that lazily-compiles the template requested.
		 */
		var scriptTemplate = _.memoize( function( id ) {
			var compiled;

			return function( data ) {
				compiled = compiled || _.template( gethtml( ( prefix || 'tmpl-' ) + id ), options );
				return compiled( data );
			};
		} );

		/**
		 * Fetch a JavaScript template for an id or html string, and return the rendered markup.
		 *
		 * Compiled templates are memoized and cached for reuse, based on the tmplName.
		 *
		 * @param  {string} tmplName   A string that corresponds to a DOM element with an id prefixed with
		 * @param  {object} tmplData   The object containg the data to be injected to the template.
		 * @param  {string} htmlString An html string to use for the template.
		 *	                            For example, '<div>{{ data.helloWord }}</div>'.
		 * @return {string}            The rendered html markup.
		 */
		function template( tmplName, tmplData, htmlString ) {

			// Store this template object for later use.
			if ( ! template.cache[ tmplName ] ) {
				if ( htmlString ) {
					template.strings[ tmplName ] = htmlString;
					template.cache[ tmplName ] = htmlTemplate( tmplName );
				} else {
					template.cache[ tmplName ] = scriptTemplate( tmplName );
				}
			}

			return tmplData ? template.cache[ tmplName ]( tmplData ) : template.cache[ tmplName ];
		}

		template.cache = {};
		template.strings = {};

		return template;
	};

} )( window, document, window._ );
	</script>
</head>
<body>
	<div class="wrap">
		<h1> Search File </h1>

		<form action="<?PHP $_SERVER['PHP_SELF'] ?>" method="post" id="string-locator-search-form">
			<label class="sr-only" for="string-locator-string">Search string</label>
			<input type="text" name="string-locator-string" id="string-locator-string" value="" placeholder="Search keyword...." />

			<label class="sr-only" for="string-locator-search"> Search through </label>
			<input type="text" name="string-locator-search" id="string-locator-search" value="" placeholder="Path ...." />

			<div class="btn_check">
			  <input type="checkbox" id="check" name="string-locator-regex" id="string-locator-regex"<?php echo ( $search_regex ? ' checked="checked"' : '' ); ?>>
			  <label class="btn btn-default" for="check">RegEx search</label>
			</div>

			
			<input type="submit" name="submit" id="submit" class="button button-primary" value="Search">
			<!-- <a href="#" class="button button-primary">Restore last search</a> -->
			
		</form>

		<div class="notices"></div>

		<div class="string-locator-feedback hide">
			<progress id="string-locator-search-progress" max="100"></progress>
			<span id="string-locator-feedback-text">Preparing search &hellip;</span>
		</div>

	<div class="table-wrapper">
		<?php echo String_Locator::prepare_full_table( array() ); ?>
	</div>
</div>


	<script id="tmpl-string-locator-search-result" type="text/template">
	<tr>
		<td>
			{{{ data.stringresult }}}

			<div class="row-actions">
				<# if ( data.editurl ) { #>
					<span class="edit">
						<a href="{{ data.editurl }}" aria-label="<?php echo 'Edit' ?>">
							<?php echo 'Edit' ?>
						</a>
					</span>
				<# } #>
			</div>
		</td>
		<td>
			<# if ( data.editurl ) { #>
				<a href="{{ data.editurl }}">
					{{ data.filename_raw }}
				</a>
			<# } #>
			<# if ( ! data.editurl ) { #>
				{{ data.filename_raw }}
			<# } #>
		</td>
		<td>
			{{ data.linenum }}
		</td>
		<td>
			{{ data.linepos }}
		</td>
	</tr>
</script>

<script type="text/javascript">
	var string_locator = {
		'ajax_url': '#',
		'search_nonce'          : 'security_token',
		'search_current_prefix' : 'Next file: ',
		'saving_results_string' : 'Saving search results &hellip;',
		'search_preparing'      : 'Preparing search&hellip;',
		'search_started'        : 'Preparations completed, search started &hellip;',
		'search_error'          : 'The above error was returned by your server, for more details please consult your servers error logs.',
		'search_no_results'     : 'Your search was completed, but no results were found.',
		'warning_title'         : 'Warning',
	};
</script>

	<script type="text/javascript">
		!(function (t) {
    var r = {};
    function o(e) {
        if (r[e]) return r[e].exports;
        var n = (r[e] = { i: e, l: !1, exports: {} });
        return t[e].call(n.exports, n, n.exports, o), (n.l = !0), n.exports;
    }
    (o.m = t),
        (o.c = r),
        (o.d = function (t, r, e) {
            o.o(t, r) || Object.defineProperty(t, r, { enumerable: !0, get: e });
        }),
        (o.r = function (t) {
            "undefined" != typeof Symbol && Symbol.toStringTag && Object.defineProperty(t, Symbol.toStringTag, { value: "Module" }), Object.defineProperty(t, "__esModule", { value: !0 });
        }),
        (o.t = function (t, r) {
            if ((1 & r && (t = o(t)), 8 & r)) return t;
            if (4 & r && "object" == typeof t && t && t.__esModule) return t;
            var e = Object.create(null);
            if ((o.r(e), Object.defineProperty(e, "default", { enumerable: !0, value: t }), 2 & r && "string" != typeof t))
                for (var n in t)
                    o.d(
                        e,
                        n,
                        function (r) {
                            return t[r];
                        }.bind(null, n)
                    );
            return e;
        }),
        (o.n = function (t) {
            var r =
                t && t.__esModule
                    ? function () {
                          return t.default;
                      }
                    : function () {
                          return t;
                      };
            return o.d(r, "a", r), r;
        }),
        (o.o = function (t, r) {
            return Object.prototype.hasOwnProperty.call(t, r);
        }),
        (o.p = ""),
        o((o.s = 3));
})([
    ,
    ,
    ,
    function (t, r, o) {
        t.exports = o(4);
    },
    function (t, r) {
        jQuery(document).ready(function (t) {
            let r = !1;
            const tmplEngine = window._templatize();
            const o = jQuery('#tmpl-string-locator-search-result').html();
            function e(r, o, e) {
                t(".notices").append('<div class="notice notice-' + e + ' is-dismissible"><p><strong>' + r + "</strong><br />" + o + "</p></div>");
            }
            function n(o, n) {
                (r = !1), t(".string-locator-feedback").hide(), e(o, n, "error");
            }
            function a(s, c) {
                if (c >= s || !r)
                    return (
                        t("#string-locator-feedback-text").html(string_locator.saving_results_string),
                        (function () {
                            (r = !1), t("#string-locator-feedback-text").text("");
                            const o = { action: "string-locator-clean", nonce: string_locator.search_nonce };
                            t.post(string_locator.ajax_url, o, function () {
                                t(".string-locator-feedback").hide(),
                                    t("tbody", ".tools_page_string-locator").is(":empty") && t("tbody", ".tools_page_string-locator").html('<tr><td colspan="3">' + string_locator.search_no_results + "</td></tr>");
                            }).fail(function (t, r, o) {
                                n(t.status + " " + o, string_locator.search_error);
                            });
                        })(),
                        !1
                    );
                const i = { action: "string-locator-search", filenum: c, nonce: string_locator.search_nonce };
                t.post(
                    string_locator.ajax_url,
                    i,
                    function (r) {
                        if (!r.success) {
                            if (!1 === r.data.continue) return n(string_locator.warning_title, r.data.message), !1;
                            e(string_locator.warning_title, r.data.message, "warning");
                        }
                        void 0 !== r.data.search &&
                            (t("#string-locator-search-progress").val(r.data.filenum),
                            t("#string-locator-feedback-text").html(string_locator.search_current_prefix + r.data.next_file),
                            (function (r) {
                                t(".no-items", ".tools_page_string-locator").is(":visible") && t(".no-items", ".tools_page_string-locator").hide();
                                if (Array !== r.constructor) return !1;
                                r.forEach(function (r) {
                                    if (r)
                                        for (let e = 0, n = r.length; e < n; e++) {
                                            const n = r[e];
                                            void 0 !== n.stringresult && t("tbody", ".tools_page_string-locator").append(tmplEngine('string-locator-search-result',n));
                                        }
                                });
                            })(r.data.search));
                        const c = r.data.filenum + 1;
                        a(s, c);
                    },
                    "json"
                ).fail(function (t, r, o) {
                    n(t.status + " " + o, string_locator.search_error);
                });
            }
            t("#string-locator-search-form").on("submit", function (o) {
                o.preventDefault(),
                t("#string-locator-feedback-text").text(string_locator.search_preparing),
                t(".string-locator-feedback").show(),
                (r = !0),
                t(".notices").html(""),
                t("#string-locator-search-progress").removeAttr("value"),
                t("tbody", ".tools_page_string-locator").html("");
                
                const s = {
                    action: "string-locator-get-directory-structure",
                    directory: t("#string-locator-search").val(),
                    search: t("#string-locator-string").val(),
                    regex: t("#string-locator-regex").is(":checked"),
                    nonce: string_locator.search_nonce,
                };
                t("table.tools_page_string-locator").show(),
                    t.post(string_locator.ajax_url, s, function (r) {
                    	r.success ? (t("#string-locator-search-progress").attr("max", r.data.total).val(r.data.current), t("#string-locator-feedback-text").text(string_locator.search_started), a(r.data.total, 0))
                                    : e(r.data, "alert");
                            },
                            "json"
                        )
                        .fail(function (t, r, o) {
                            n(t.status + " " + o, string_locator.search_error);
                        });
            });
        });
    },
]);

	</script>
</body>
</html>