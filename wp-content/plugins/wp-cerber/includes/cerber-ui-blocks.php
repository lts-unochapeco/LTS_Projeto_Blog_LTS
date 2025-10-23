<?php
/*
 * Helpers, factory functions, and domain-specific functions.
 *
 * They encapsulate the logic for building complex, domain-specific CRB_UI_Element trees,
 * providing a clean and simple API for the rest of the application.
 *
 */

/**
 * Creates an HTML link with a confirmation dialog.
 *
 * When the user clicks the link, a confirmation dialog is displayed with the specified message.
 * If the user does not confirm, the action is cancelled, and the browser does not follow the lin
 *
 * @param string $url URL of the link.
 * @param string $text Link text.
 * @param string $msg Optional confirmation message.
 * @param string $class Optional class for the <a> element.
 *
 * @return string The rendered HTML of the confirmation link.
 *
 * @see crb_confirmation_link()
 *
 * @since 9.6.9.6
 */
function crb_ui_confirmation_link( string $url, string $text, string $msg = '', string $class = '' ): string {
	$props = [
		'href' => $url,
		'label' => $text,
		'message' => $msg,
	];

	$attributes = [
		'class' => $class,
	];

	$element = new CRB_UI_Element('confirmation_link', $props, $attributes);

	return crb_ui_renderer()->render_element($element);
}

/**
 * Builds a structured CRB_UI_Element object representing a diagnostic section.
 *
 * @param string $title The main title of the section.
 * @param mixed $content The main content, can be a string or a CRB_UI_Element.
 * @param array $args Optional arguments: 'subtitle', 'copy_class'.
 *
 * @return CRB_UI_Element A fully constructed UI element for the diagnostic section.
 *
 * @since 9.6.9.5
 */
function crb_ui_make_diag_section( string $title, $content, array $args = [] ): CRB_UI_Element {

	if ( is_string( $content ) ) {
		$content_element = new CRB_UI_Element( 'text', [ 'content' => $content ] );
	}
	elseif ( $content instanceof CRB_UI_Element ) {
		$content_element = $content;
	}
	else {
		$content_element = new CRB_UI_Element( 'text', [ 'content' => 'Invalid Data Provided' ] );
	}

	$header_items = [ new CRB_UI_Element( 'h3', [ 'content' => $title ] ) ];

	if ( $copy_class = $args['copy_class'] ?? '' ) {
		$header_items[] = crb_ui_copy_to_clipboard( $copy_class );
	}

	$section_children = [ crb_ui_generate_html_flex( $header_items ) ];

	if ( $subtitle = $args['subtitle'] ?? '' ) {
		$section_children[] = new CRB_UI_Element( 'div', [ 'content' => $subtitle ], [ 'class' => 'crb-diag-subtitle' ] );
	}

	$section_children[] = new CRB_UI_Element( 'div', [], [ 'class' => 'crb-diag-inner' ], [ $content_element ] );

	return new CRB_UI_Element(
		'div',
		[],
		[ 'class' => 'crb-diag-section' ],
		$section_children
	);
}

/**
 * Generates a plain HTML table using the UI Factory system.
 *
 * This function acts as a facade, preparing data and attributes before passing them
 * to the 'standard_table' element renderer, which handles the complex logic.
 *
 * @param array $table_rows Table body rows.
 * @param array $table_header Optional array of header labels.
 * @param bool $first_header If true, adds 'crb-plain-fh' class.
 * @param bool $eq If false, adds 'crb-plain-fcw' class.
 *
 * @return CRB_UI_Element The rendered HTML code for the table.
 *
 * @since 9.6.9.5
 *
 * This is a new generation of @see cerber_make_plain_table
 */
function crb_ui_make_plain_table( array $table_rows, array $table_header = [], bool $first_header = false, bool $eq = false ): CRB_UI_Element {

	$table_classes = [ 'crb-monospace' ];
	if ( $first_header ) {
		$table_classes[] = 'crb-plain-fh';
	}
	if ( ! $eq ) {
		$table_classes[] = 'crb-plain-fcw';
	}

	$props = [
		'headers' => $table_header,
		'data'    => $table_rows,
	];

	$attributes = [
		'class' => implode( ' ', $table_classes ),
	];

	$table_element = new CRB_UI_Element( 'standard_table', $props, $attributes );
	return new CRB_UI_Element( 'div', [], [ 'class' => 'crb-plain-table' ], [ $table_element ] );
}

/**
 * Helper function for creating a styled status span
 *
 * @param bool $is_positive
 * @param string $text_yes
 * @param string $text_no
 *
 * @return CRB_UI_Element
 *
 * @since 9.6.9.5
 */
function crb_ui_status_span( bool $is_positive, string $text_yes = 'YES', string $text_no = 'NO' ): CRB_UI_Element {
	$text = $is_positive ? $text_yes : $text_no;
	$color = $is_positive ? 'green' : 'red';

	return new CRB_UI_Element(
		'span',
		[ 'content' => $text ],
		[ 'style' => 'color: ' . $color ]
	);
}

/**
 * Helper function for creating a 'Copy To Clipboard' element
 *
 * @param string $source_class The class of the HTML elements to copy text content from.
 * @param bool $plain If true, the plain inner text will be copied without tags and processing, otherwise <br/> tags will be converted into new lines and other tags will be removed.
 *
 * @return CRB_UI_Element
 *
 * @since 9.6.9.5
 *
 * This is a new generation of @see crb_copy_to_clipboard
 */
function crb_ui_copy_to_clipboard( string $source_class, $plain = true ): CRB_UI_Element {
	return new CRB_UI_Element(
		'link',
		[
			'label' => __( 'Copy To Clipboard', 'wp-cerber' ),
			'href'  => '#'
		],
		[
			'class'                     => 'crb-copy-to-clipboard',
			'data-plain_text'           => ( $plain ? 1 : 0 ),
			'data-copy_clipboard_class' => crb_boring_escape( $source_class )
		]
	);
}


/**
 * Generates a flexible box layout using the UI Factory system.
 *
 * This function is a modern, drop-in replacement for the legacy version.
 * It builds a structured element tree, ensuring safety, consistency, and maintainability.
 *
 * @param array $elements An array of child elements. Each item can be a string or a pre-existing CRB_UI_Element object for composition.
 * @param string $class Optional CSS class for the flex container.
 * @param string $justify The value for the 'justify-content' CSS property.
 *
 * @return CRB_UI_Element
 *
 * --- EXAMPLE OF USAGE ---
 *
 * Advanced usage with nested UI Elements
 *
 * $footer_items = [
 *  new CRB_UI_Element('span', ['content' => '© 2025 My App']),
 *  new CRB_UI_Element('link', ['label' => 'Terms of Service', 'href' => '/terms']),
 * ];
 *
 * crb_ui_generate_html_flex($footer_items, 'site-footer', 'center');
 *
 * @since 9.6.9.5
 *
 * This is a new generation of @see crb_generate_html_flex
 */
function crb_ui_generate_html_flex( array $elements, string $class = '', string $justify = 'space-between' ): CRB_UI_Element {

	$flex_container_atts = [
		'class' => $class,
		'style' => 'display: flex; justify-content: ' . crb_attr_escape( $justify ) . ';', // Sanitize justify value
	];

	// Wrapping each child element in a <div>.

	$child_elements = [];

	foreach ( $elements as $element_content ) {
		$child_node = null;

		if ( $element_content instanceof CRB_UI_Element ) {
			$child_node = $element_content;
		}
		else {
			$child_node = new CRB_UI_Element( 'text', [ 'content' => (string) $element_content ] );
		}

		$child_elements[] = new CRB_UI_Element( 'div', [], [], [ $child_node ] );
	}

	return new CRB_UI_Element(
		'div',
		[],
		$flex_container_atts,
		$child_elements
	);
}


/**
 * Constructs a table-style hierarchical view of a key-value array using CRB_UI_Element.
 *
 * This function renders arrays as structured tables. It intelligently handles two formats:
 *
 * 1. Standard associative arrays (`['key' => 'value', ...]`).
 * 2. Numerically indexed lists of pairs (`[['key1', 'value1'], ['key2', 'value2'], ...]`), which allows for duplicate keys (e.g., for displaying HTTP headers).
 *
 * If a value is itself an array, it will be recursively rendered as a nested table.
 *
 * Special object values with `element_class` and `element_value` properties are interpreted as decorated cells, allowing for per-cell styling.
 *
 * @param string $title Optional title for the table. Rendered in a dedicated top row, spanning both columns.
 * @param array $fields Array of data to render. Nested arrays will be rendered recursively.
 * @param bool $nested Internal flag used for recursive rendering of nested tables.
 *
 * @return CRB_UI_Element|null A renderable table element, or null if fields are empty. To generate HTML output, pass the result to the rendering engine.
 *
 * @example
 *
 * // --- EXAMPLE OF USAGE ---
 *
 * // Prepare your data
 * $my_data = [
 * 'System' => 'Linux',
 * 'PHP Version' => phpversion(),
 * 'Server Info' => [
 * 'CPU' => 'Intel',
 * 'RAM' => '16GB'
 * ]
 * ];
 *
 * // Call a factory function to get the element object
 * $table_element = crb_ui_table_view('System Details', $my_data);
 *
 * // Render the element using the central renderer
 * if ($table_element) {
 *      echo crb_ui_renderer()->render_element($table_element);
 * }
 *
 * @since 9.6.9.7
 */
function crb_ui_table_view( string $title, array $fields, bool $nested = false ): ?CRB_UI_Element {

	if ( empty( $fields ) ) {
		return null;
	}

	// 1. Prepare attributes for the root <table> tag

	$table_classes = [ 'crb-fields-table' ];

	if ( $nested ) {
		$table_classes[] = 'crb-sub-table';
	}
	else {
		$table_classes[] = 'crb-top-table';
	}

	$table_attributes = [ 'class' => implode( ' ', $table_classes ) ];

	// 2. Prepare the standard specification for the 'rich_table' renderer

	$spec_props = [
		'columns'       => [
			'key'   => [ 'label' => '' ], // This table has no visible headers
			'value' => [ 'label' => '' ],
		],
		'rows'          => [],
		'render_header' => false, // Headers are never rendered in this component
	];

	// 3. Handle the optional title by adding it as the first row

	if ( $title ) {
		$spec_props['rows'][] = [
			'cells' => [
				'key' => [
					'content'    => $title,
					'attributes' => [ 'colspan' => 2 ]
				],
			]
		];
	}

	// 4. Iterate through the fields, detect their format and transform them into standard row/cell specs

	foreach ( $fields as $key => $value ) {
		$row_key = null;
		$row_value = null;

		// Check the format of the input data array

		if ( is_int( $key ) && is_array( $value )
		     && count( $value ) === 2 && array_key_exists( 0, $value )
		     && array_key_exists( 1, $value ) ) {
			// This is a list format: [ ['key', 'value'], ... ] which has integer keys.
			$row_key = $value[0];
			$row_value = $value[1];
		}
		else {
			// This is a standard associative array: [ 'key' => 'value', ... ]
			$row_key = $key;
			$row_value = $value;
		}

		$key_cell_attributes = [];

		// Handle the special object format for cell attributes

		if ( is_object( $row_value )
		     && isset( $row_value->element_class )
		     && isset( $row_value->element_value ) ) {
			$key_cell_attributes['class'] = $row_value->element_class;
			$row_value = $row_value->element_value;
		}

		$value_cell_content = null;

		if ( is_array( $row_value ) ) {
			// Recursively render nested arrays as nested tables
			$value_cell_content = crb_ui_table_view( '', $row_value, true );
		}
		else {
			$value_cell_content = new CRB_UI_Element( 'div', [ 'content' => (string) $row_value ] );
		}

		$spec_props['rows'][] = [
			'cells' => [
				'key'   => [ 'content' => $row_key, 'attributes' => $key_cell_attributes ],
				'value' => [ 'content' => $value_cell_content ],
			]
		];

	}

	return new CRB_UI_Element( 'rich_table', $spec_props, $table_attributes );
}
