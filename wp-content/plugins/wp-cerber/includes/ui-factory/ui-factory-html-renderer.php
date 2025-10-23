<?php
/*
	Copyright (C) 2025 CERBER TECH INC., https://wpcerber.com

    Licenced under the GNU GPL.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

/**
 * Class CRB_UI_Element
 *
 * Represents a generic UI component with type, props, and optional children.
 * Serves as a transport-level data object (DTO) to describe UI elements independently of rendering implementation.
 *
 * @since 9.6.9.5
 */
class CRB_UI_Element {
	/**
	 * The type of the UI element (e.g., 'button', 'table').
	 *
	 * @var string
	 */
	public string $type = '';

	/**
	 * Logical, component-specific properties that define behavior and content.
	 * (e.g., 'label' for a button, 'columns' and 'rows' for a table).
	 * @var array
	 */
	public array $props = [];

	/**
	 * Standard HTML attributes to be rendered directly onto the element's tag.
	 * (e.g., 'class', 'id', 'data-*').
	 *
	 * @var array
	 */
	public array $attributes = [];

	/**
	 * An array of nested CRB_UI_Element objects for composition.
	 * @var CRB_UI_Element[]
	 */
	public array $children = [];

	/**
	 * Initializes a UI element with type, props, attributes, and children.
	 *
	 * @param string $type The type of the UI component (e.g. 'button', 'table').
	 * @param array $props Component-specific properties (e.g. label, icon name).
	 * @param array $attributes HTML attributes (e.g. class, id, title).
	 * @param array $children Optional nested CRB_UI_Element instances.
	 */
	public function __construct( string $type, array $props = [], array $attributes = [], array $children = [] ) {
		$this->type = $type;
		$this->props = $props;
		$this->attributes = $attributes;
		$this->children = $children;
	}
}

/**
 * Interface CRB_UI_Renderer
 *
 * Defines a rendering interface and contract for transforming UI elements into visual output.
 * Supports pluggable rendering backends for HTML, JSON, or other UI targets.
 * Enables renderer substitution and interface-based decoupling of presentation logic.
 *
 * @since 9.6.9.5
 */
interface CRB_UI_Renderer {
	/**
	 * Renders a given UI element into output.
	 *
	 * @param CRB_UI_Element $element The UI element to render.
	 *
	 * @return string The rendered output as a string.
	 */
	public function render_element( CRB_UI_Element $element ): string;
}

/**
 * Class CRB_UI_Html_Renderer
 *
 * Implements CRB_UI_Renderer to render CRB_UI_Element data into standard HTML markup.
 *
 * @since 9.6.9.5
 */
class CRB_UI_Html_Renderer implements CRB_UI_Renderer {

	/**
	 * A list of HTML attributes that should be treated as URLs and sanitized accordingly.
	 *
	 * @var string[]
	 */
	private const URL_ATTRIBUTES = [ 'href', 'src', 'action', 'formaction' ];
	private const SELF_CLOSING_TAGS = [ 'img', 'br', 'hr', 'input' ];

	/**
	 * Main entry point for rendering. Dispatches to the appropriate method.
	 *
	 * @param CRB_UI_Element $element The UI element to render.
	 *
	 * @return string Rendered HTML.
	 * @throws \InvalidArgumentException If an unknown element type is requested.
	 *
	 * @since 9.6.9.5
	 */
	public function render_element( CRB_UI_Element $element ): string {

		switch ( $element->type ) {
			// --- High-level, convenient renderers ---
			case 'simple_table':
				return $this->render_simple_table( $element->props, $element->attributes );
			case 'standard_table':
				return $this->render_plain_table( $element->props, $element->attributes );
			case 'rich_table':
				return $this->render_table_from_spec( $element->props, $element->attributes );

			// --- Specific component renderers ---
			case 'button':
				return $this->render_button( $element->props, $element->attributes );
			case 'link':
				return $this->render_link( $element->props, $element->attributes );
			case 'link_tiny_button':
				return $this->render_link_tiny_button( $element->props, $element->attributes );
			case 'link_nav_item':
				return $this->render_link_nav_item( $element->props, $element->attributes );
			case 'confirmation_link':
				return $this->render_confirmation_link( $element->props, $element->attributes );

			// --- Generic Tag Renderers (for manual tree building) ---
			case 'table':
			case 'thead':
			case 'tbody':
			case 'tfoot':
			case 'tr':
			case 'th':
			case 'td':
			case 'ul':
			case 'li':
			case 'div':
			case 'span':
			case 'p':
			case 'b':
			case 'i':
			case 'h1':
			case 'h2':
			case 'h3':
// New since 9.6.9.8
			case 'form':
			case 'label':
			case 'input':
			case 'select':
			case 'option':
			case 'br':
			case 'hr':

				return $this->render_generic_tag( $element );

			// Handle plain text content
			case 'text':
				return crb_escape_html( $element->props['content'] ?? '' );

			// This is a temporary gateway for legacy code that returns raw HTML.
			// It bypasses the standard escaping mechanism.
			// WARNING: Only use this for trusted, escaped HTML output.
			case 'raw_html':
				return (string) ($element->props['content'] ?? '');

			default:
				throw new \InvalidArgumentException( "Unknown element type requested: '{$element->type}'" );
		}
	}

	/**
	 * Renders a complete table from a declarative specification array.
	 *
	 * The spec in $props can contain:
	 * 'columns' => (array) Defines the table headers. The keys of this array are used to map
	 * data from the 'cells' array in each row.
	 * Format: [ 'column_key' => [ 'label' => 'Title', 'attributes' => [] ], ... ]
	 * 'rows' => (array) An array of row data.
	 * Format: [ [ 'cells' => [ 'column_key' => 'content' or CRB_UI_Element ], 'attributes' => [] ], ... ]
	 * 'render_header' => (bool) Whether to render the <thead>. Defaults to true.
	 * 'render_footer' => (bool) Whether to render the <tfoot> as a copy of the header. Defaults to false.
	 *
	 * @note This method renders rows by iterating over the `cells` provided for each row,
	 *  rather than enforcing the global `columns` structure on every row. This provides the flexibility
	 *  needed for features like `colspan`. However, it also means the caller is responsible for ensuring
	 *  cell consistency for standard data rows. Advanced features that rely on a strict, uniform grid
	 *  (e.g., column sorting, fixed layouts) might not be suitable for tables with irregular row structures.
	 *
	 * @param array $props The table specification.
	 * @param array $attributes HTML attributes for the <table> tag.
	 *
	 * @return string The rendered HTML table.
	 *
	 * @since 9.6.9.5
	 */
	protected function render_table_from_spec( array $props, array $attributes = [] ): string {

		$columns = $props['columns'] ?? [];
		$rows = $props['rows'] ?? [];
		$render_header = $props['render_header'] ?? true;
		$render_footer = $props['render_footer'] ?? false;

		$html = '<table' . $this->build_attributes( $attributes ) . '>';
		$header = '';

		// Render the table header if requested

		if ( $render_header ) {
			$header = $this->render_table_header_or_footer( $columns );
			$html .= '<thead>' . $header . '</thead>';
		}

		// Render the table body

		$html .= '<tbody>';

		if ( empty( $rows ) ) {

			// Handle the empty state for the table

			$column_count = count( $columns );
			$colspan_attr = $column_count > 0 ? ' colspan="' . $column_count . '"' : '';
			$html .= '<tr><td' . $colspan_attr . '>No Data To Display</td></tr>';
		}
		else {

			// Render the table rows

			foreach ( $rows as $row_data ) {

				$row_attributes = $this->build_attributes( $row_data['attributes'] ?? [] );
				$html .= '<tr' . $row_attributes . '>';

				foreach ( ( $row_data['cells'] ?? [] ) as $cell ) {
					$td_attributes_string = '';

					if ( is_array( $cell ) ) {
						$cell_content = $cell['content'] ?? '';
						$td_attributes_string = $this->build_attributes( $cell['attributes'] ?? [] );
					}
					else {
						$cell_content = $cell;
					}

					$html .= '<td' . $td_attributes_string . '>';

					if ( $cell_content instanceof CRB_UI_Element ) {
						$html .= $this->render_element( $cell_content );
					}
					elseif ( $cell_content !== null ) {
						$html .= crb_escape_html( (string) $cell_content );
					}

					$html .= '</td>';
				}

				$html .= '</tr>';
			}
		}
		$html .= '</tbody>';

		// Render the table footer if requested (as a copy of the header)

		if ( $render_footer ) {
			if ( ! $header ) {
				$header = $this->render_table_header_or_footer( $columns );
			}

			$html .= '<tfoot>' . $header . '</tfoot>';
		}

		$html .= '</table>';

		return $html;
	}

	/**
	 * Renders a basic table directly from a 2D numerically indexed array.
	 * This is a convenience method for basic data display without complex specs.
	 *
	 * @param array $props Expects 'data' (2D array) and optional 'headers' (1D array).
	 * @param array $attributes HTML attributes for the <table> tag.
	 *
	 * @return string The rendered HTML table.
	 *
	 * @since 9.6.9.5
	 */
	protected function render_simple_table( array $props, array $attributes = [] ): string {
		$data = $props['data'] ?? [];
		$headers = $props['headers'] ?? [];

		$html = '<table' . $this->build_attributes( $attributes ) . '>';

		// Optional headers

		if ( $headers ) {

			$columns = array_map( function ( $h ) {
				return [ 'label' => $h ];
			}, $headers );

			$header = $this->render_table_header_or_footer( $columns );
			$html .= '<thead>' . $header . '</thead>';
		}

		// Table body

		$html .= '<tbody>';
		if ( empty( $data ) ) {
			$column_count = count( $headers );
			$colspan_attr = $column_count > 0 ? ' colspan="' . $column_count . '"' : '';
			$html .= '<tr><td' . $colspan_attr . '>No Data To Display</td></tr>';
		}
		else {
			foreach ( $data as $row ) {
				$html .= '<tr>';
				foreach ( (array) $row as $cell ) {
					$html .= '<td>' . crb_escape_html( (string) $cell ) . '</td>';
				}
				$html .= '</tr>';
			}
		}
		$html .= '</tbody>';

		$html .= '</table>';

		return $html;
	}

	/**
	 * Acts as an adapter for rendering a simple table.
	 * It converts a simple 2D array into the rich specification format required by
	 * render_table_from_spec(), then calls it to perform the actual rendering.
	 * This promotes code reuse and provides a convenient API for simple tables.
	 *
	 * @param array $props Expects 'data' (2D array) and optional 'headers' (1D array).
	 * @param array $attributes HTML attributes for the <table> tag.
	 *
	 * @return string The rendered HTML table.
	 *
	 * @since 9.6.9.5
	 */
	protected function render_plain_table( array $props, array $attributes = [] ): string {
		$data = $props['data'] ?? [];
		$headers = $props['headers'] ?? [];

		// Transform the simple data into the rich 'spec' format.

		$spec_props = [
			'columns'       => [],
			'rows'          => [],
			'render_header' => ! empty( $headers ),
			'render_footer' => $props['render_footer'] ?? false, // Pass through footer flag
		];

		// Create column definitions from headers

		$column_keys = ! empty( $headers ) ? array_keys( $headers ) : array_keys( $data[0] ?? [] );
		foreach ( $column_keys as $key ) {
			$spec_props['columns'][ $key ] = [ 'label' => $headers[ $key ] ?? '' ];
		}

		// Create row and cell structures

		foreach ( $data as $row_items ) {
			$cells = [];
			foreach ( (array) $row_items as $index => $cell_content ) {
				// The key here must match the column key (numeric index)
				$cells[ $index ] = [ 'content' => $cell_content ];
			}
			$spec_props['rows'][] = [ 'cells' => $cells ];
		}

		// 2. Call the powerful spec renderer with the transformed props.
		return $this->render_table_from_spec( $spec_props, $attributes );
	}

	/**
	 * Renders the content for a <thead> or <tfoot> section.
	 *
	 * @param array $columns The column definitions array.
	 *
	 * @return string The rendered HTML for the section.
	 *
	 * @since 9.6.9.5
	 */
	private function render_table_header_or_footer( array $columns ): string {
		if ( empty( $columns ) ) {
			return '';
		}

		$html = '<tr>';

		foreach ( $columns as $column_data ) {
			$label = $column_data['label'] ?? '';
			$th_attributes = $this->build_attributes( $column_data['attributes'] ?? [] );
			$html .= '<th' . $th_attributes . '>' . crb_escape_html( $label ) . '</th>';
		}

		$html .= '</tr>';

		return $html;
	}

	/**
	 * Renders any generic HTML tag with attributes and children.
	 *
	 * @param CRB_UI_Element $element The element to render.
	 *
	 * @return string The rendered HTML.
	 *
	 * @since 9.6.9.5
	 */
	protected function render_generic_tag( CRB_UI_Element $element ): string {
		$tag_name = $element->type;
		$attributes_string = $this->build_attributes( $element->attributes );
		$inner_html = '';

		// A tag can have children OR text content, but not both. Children take precedence.
		if ( ! empty( $element->children ) ) {
			foreach ( $element->children as $child ) {
				$inner_html .= $this->render_element( $child );
			}
		}
		elseif ( isset( $element->props['content'] ) ) {
			// Render text content only if there are no children.
			$inner_html = crb_escape_html( (string) $element->props['content'] );
		}

		if ( in_array( $tag_name, self::SELF_CLOSING_TAGS, true ) ) {
			return '<' . $tag_name . $attributes_string . ' />';
		}

		return '<' . $tag_name . $attributes_string . '>' . $inner_html . '</' . $tag_name . '>';
	}

	/**
	 * Renders an <a> (anchor) HTML element.
	 *
	 * @param array $props Must include 'label' and optionally 'href'.
	 * @param array $attributes HTML attributes for the anchor tag.
	 *
	 * @return string HTML anchor tag.
	 *
	 * @since 9.6.9.5
	 */
	protected function render_link( array $props, array $attributes = [] ): string {
		if ( isset( $props['href'] ) ) {
			$attributes['href'] = $props['href'];
		}
		$label = $props['label'] ?? '';

		return '<a' . $this->build_attributes( $attributes ) . '>' . crb_escape_html( $label ) . '</a>';
	}

	/**
	 * Renders a link that triggers a JavaScript confirmation dialog before proceeding.
	 *
	 * @param array $props Must include 'label', 'href', and optional 'message'.
	 * @param array $attributes Additional HTML attributes.
	 *
	 * @return string The rendered HTML for the confirmation link.
	 *
	 * @since 9.6.9.6
	 */
	protected function render_confirmation_link( array $props, array $attributes = [] ): string {

		$link_attributes = $attributes;

		$link_attributes['class'] = trim( ( $attributes['class'] ?? '' ) . ' crb-confirm-action' );

		if ( $message = $props['message'] ?? '' ) {
			$link_attributes['data-user_message'] = $message;
		}

		return $this->render_link( $props, $link_attributes );
	}

	/**
	 * Renders an anchor styled as a small button.
	 *
	 * @param array $props Must include 'label' and 'href'.
	 * @param array $attributes HTML attributes for the anchor tag.
	 *
	 * @return string            HTML anchor tag with button styling.
	 *
	 * @since 9.6.9.5
	 */
	protected function render_link_tiny_button( array $props, array $attributes = [] ): string {
		$link_atts = array_merge( $attributes, [
			'class' => trim( ( $attributes['class'] ?? '' ) . ' crb-button-tiny' )
		] );

		return $this->render_link( $props, $link_atts );
	}

	/**
	 * Renders a navigation link styled with crb-nav-link class.
	 *
	 * @param array $props Must include 'label' and 'href'.
	 * @param array $attributes HTML attributes for the anchor tag.
	 *
	 * @return string            HTML anchor tag styled as a nav item.
	 *
	 * @since 9.6.9.5
	 */
	protected function render_link_nav_item( array $props, array $attributes = [] ): string {
		$link_atts = array_merge( $attributes, [
			'class' => trim( ( $attributes['class'] ?? '' ) . ' crb-nav-link' )
		] );

		return $this->render_link( $props, $link_atts );
	}

	/**
	 * Renders a <button> HTML element.
	 *
	 * @param array $props Properties like 'label'.
	 * @param array $attributes HTML attributes for the button tag.
	 *
	 * @return string            HTML string representing the button.
	 *
	 * @since 9.6.9.5
	 */
	protected function render_button( array $props, array $attributes = [] ): string {
		$label = $props['label'] ?? '';

		return '<button' . $this->build_attributes( $attributes ) . '>' . crb_escape_html( $label ) . '</button>';
	}

	/**
	 * Converts key-value pairs to a safe, properly escaped HTML attribute string.
	 *
	 * @param array $attrs Raw attributes to encode.
	 *
	 * @return string Escaped and concatenated attribute string.
	 *
	 * @since 9.6.9.5
	 */
	private function build_attributes( array $attrs ): string {
		$parts = [];

		foreach ( $attrs as $key => $value ) {
			if ( $value === null || $value === false ) {
				continue;
			}

			$escaped_key = crb_attr_escape( $key );

			if ( $value === true ) {
				$parts[] = $escaped_key;
				continue;
			}

			$escaped_value = in_array( strtolower( $key ), self::URL_ATTRIBUTES, true )
				? crb_escape_url( (string) $value )
				: crb_attr_escape( (string) $value );

			$parts[] = $escaped_key . '="' . $escaped_value . '"';
		}

		return $parts ? ' ' . implode( ' ', $parts ) : '';
	}
}
