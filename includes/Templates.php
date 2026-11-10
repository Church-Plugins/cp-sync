<?php

namespace CP_Sync;

/**
 * Loads theme files in appropriate hierarchy: 1) child theme,
 * 2) parent template, 3) plugin resources. will look in the cp-sync/
 * directory in a theme and the templates/ directory in the plugin
 *
 * @param string $template template file to search for
 * @param array  $args     additional arguments to affect the template path
 *                         - namespace
 *                         - plugin_path
 *                         - disable_view_check - bypass the check to see if the view is enabled
 *
 * @return template path
 **/
function get_template_hierarchy( $template, $args = [] ) {
	if ( ! is_array( $args ) ) {
		// func_get_args() is read before any parameter is reassigned below, so it reflects the original values passed to this function; the PHP 7+ current-value behavior does not apply here.
		$passed        = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
		$args          = [];
		$backwards_map = [ 'namespace', 'plugin_path' ];
		$count         = count( $passed );

		if ( $count > 1 ) {
			for ( $i = 1; $i < $count; $i ++ ) {
				$args[ $backwards_map[ $i - 1 ] ] = $passed[ $i ];
			}
		}
	}

	$args = wp_parse_args(
		$args, [
			'namespace'          => '/',
			'plugin_path'        => '',
			'disable_view_check' => false,
		]
	);

	/**
	 * @var string $namespace
	 * @var string $plugin_path
	 * @var bool   $disable_view_check
	 */
	extract( $args );

	// append .php to file name
	if ( substr( $template, - 4 ) != '.php' && false === strpos( $template, '.json' ) ) {
		$template .= '.php';
	}

	// Allow base path for templates to be filtered
	$template_base_paths = apply_filters( 'cp_sync_template_paths', ( array ) cp_sync()->get_plugin_path() );

	// backwards compatibility if $plugin_path arg is used
	if ( $plugin_path && ! in_array( $plugin_path, $template_base_paths ) ) {
		array_unshift( $template_base_paths, $plugin_path );
	}

	$file = false;

	/* potential scenarios:

	- the user has no template overrides
		-> we can just look in our plugin dirs, for the specific path requested, don't need to worry about the namespace
	- the user created template overrides without the namespace, which reference non-overrides without the namespace and, their own other overrides without the namespace
		-> we need to look in their theme for the specific path requested
		-> if not found, we need to look in our plugin views for the file by adding the namespace
	- the user has template overrides using the namespace
		-> we should look in the theme dir, then the plugin dir for the specific path requested, don't need to worry about the namespace

	*/

	// check if there are overrides at all
	if ( locate_template( [ 'cp-sync/' ] ) ) {
		$overrides_exist = true;
	} else {
		$overrides_exist = false;
	}

	if ( $overrides_exist ) {
		// check the theme for specific file requested
		$file = locate_template( [ 'cp-sync/' . $template ], false, false );
		if ( ! $file ) {
			// if not found, it could be our plugin requesting the file with the namespace,
			// so check the theme for the path without the namespace
			$files = [];
			foreach ( array_keys( $template_base_paths ) as $namespace ) {
				if ( ! empty( $namespace ) && ! is_numeric( $namespace ) ) {
					$files[] = 'cp-sync' . str_replace( $namespace, '', $template );
				}
			}
			$file = locate_template( $files, false, false );
			if ( $file ) {
				_deprecated_function(
					sprintf(
						/* translators: %s: path to the template override file, relative to the theme's cp-sync/ directory */
						esc_html__( 'Template overrides should be moved to the correct subdirectory: %s', 'cp-sync' ),
						esc_html( str_replace( get_stylesheet_directory() . '/cp-sync/', '', $file ) )
					),
					'3.2',
					esc_html( $template )
				);
			}
		} else {
			$file = apply_filters( 'cp_sync_template', $file, $template );
		}
	}

	// if the theme file wasn't found, check our plugins views dirs
	if ( ! $file ) {

		foreach ( $template_base_paths as $template_base_path ) {

			// make sure directories are trailingslashed
			$template_base_path = ! empty( $template_base_path ) ? trailingslashit( $template_base_path ) : $template_base_path;

			$file = $template_base_path . 'templates/' . $template;

			$file = apply_filters( 'cp_sync_template', $file, $template );

			// return the first one found
			if ( file_exists( $file ) ) {
				break;
			} else {
				$file = false;
			}
		}
	}

	return apply_filters( 'cp_sync_template_' . $template, $file );
}

/**
 * Includes the template part
 * 
 * @param $template
 * @param $args
 *
 * @since  1.0.0
 *
 * @author Tanner Moushey
 */
function get_template_part( $template, $args = [], $return = false ) {
	$template_path = get_template_hierarchy( $template );

	if ( ! is_string( $template_path ) || '' === $template_path || ! file_exists( $template_path ) ) {
		return $return ? '' : null;
	}

	if ( ! is_array( $args ) ) {
		$args = [];
	}

	$render = static function () use ( $template_path, $args ) {
		if ( $args ) {
			// Template variables are the documented way themes receive data. EXTR_SKIP
			// keeps this function's own locals from being overwritten.
			extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		include $template_path;
	};

	if ( $return ) {
		ob_start();
		$render();
		return ob_get_clean();
	}

	$render();
}

/**
 * Print a theme template part the way a block template does.
 *
 * The template part block runs the part's own shortcodes before its blocks
 * render, and adds the wrapping element. block_template_part() does neither.
 * A value a block prints from the request, such as the search box, therefore
 * never reaches do_shortcode().
 *
 * @since 1.2.0
 *
 * @param string $slug Part slug and wrapping element: header or footer.
 * @return void
 */
function print_block_template_part( $slug ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output, as in core.
	echo do_blocks( sprintf( '<!-- wp:template-part {"slug":"%1$s","tagName":"%1$s"} /-->', $slug ) );
}

/**
 * Render a plugin template into the block theme canvas.
 *
 * get_the_block_template_html() runs autoembed and do_shortcode over the stored
 * content. Theme parts have already had that pass when they print, and ChMS and
 * request values must not get one, so the whole capture is protected.
 *
 * The file loads at the same scope as the WordPress template loader, so it can
 * read the query globals and cannot change this function's variables. wp_head()
 * still runs after the capture and prints what the template registered.
 *
 * @since 1.2.0
 *
 * @param string $file     Plugin or theme override template.
 * @param mixed  $template Incoming template_include path.
 * @return string Canvas path, or an empty string when the file should load directly.
 */
function render_for_block_canvas( $file, $template ) {
	$canvas = block_canvas_template( $template );

	if ( '' === $canvas ) {
		return '';
	}

	global $_wp_current_template_content, $post;

	$previous = ( isset( $post ) && $post instanceof \WP_Post ) ? $post : null;

	ob_start();
	load_template( $file, false );
	$_wp_current_template_content = Groups\Display::protect_block_template_html( ob_get_clean() );

	if ( $previous instanceof \WP_Post && function_exists( 'setup_postdata' ) ) {
		$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the post the template loop moved.
		setup_postdata( $previous );
	} elseif ( function_exists( 'wp_reset_postdata' ) ) {
		wp_reset_postdata();
	}

	return $canvas;
}

/**
 * Canvas path for the current request.
 *
 * A block theme can still hand template_include a PHP template when a more
 * specific PHP file wins over the block index. Looking up the index canvas
 * before the capture is what puts the markup in the canvas, because the lookup
 * replaces the template content. An incoming canvas is kept, because looking it
 * up again would register the canvas head hooks a second time.
 *
 * @since 1.2.0
 *
 * @param mixed $template Incoming template path.
 * @return string Canvas path, or an empty string when there is no canvas.
 */
function block_canvas_template( $template ) {
	if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
		return '';
	}

	if ( ! function_exists( 'locate_block_template' ) ) {
		return '';
	}

	if ( is_template_canvas( $template ) ) {
		return $template;
	}

	$located = locate_block_template( '', 'index', array( 'index' ) );

	return is_template_canvas( $located ) ? $located : '';
}

/**
 * Whether a path is the WordPress block template canvas.
 *
 * @since 1.2.0
 *
 * @param mixed $path Template path.
 * @return bool
 */
function is_template_canvas( $path ) {
	return is_string( $path )
		&& '' !== $path
		&& file_exists( $path )
		&& 'template-canvas.php' === basename( $path );
}