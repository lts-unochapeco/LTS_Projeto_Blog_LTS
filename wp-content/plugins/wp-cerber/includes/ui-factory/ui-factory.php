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

/*

== A Developer Guide for "UI Factory" ==

Core Concept

This guide explains how to use the "UI Factory" rendering system to generate HTML elements
within the project. The system is designed to decouple business logic from presentation, resulting in cleaner,
more secure code that is ready for a future transition to Web Components.

Instead of outputting HTML directly (echo '...'), you create a UI element specification using the CRB_UI_Element PHP object.

This specification is then passed to a renderer, which generates safe HTML code.

1. Getting the Renderer Instance

To generate any element, always use the global helper function crb_ui_renderer(). It returns the currently active renderer instance (which is CRB_UI_Html_Renderer for now).

$ui = crb_ui_renderer();

2. Creating an Element

Any UI element is described by a CRB_UI_Element object.

Its constructor accepts:

- type (string): The element type (e.g., 'button', 'table', 'div'). Required.
- props (array): Logical, type-specific properties (e.g., 'label' for a button, 'rows' for a table).
- attributes (array): Standard HTML attributes (class, id, data-*).
- children (array): Nested CRB_UI_Element objects for building a tree structure.

3. Rendering Methods

There are two primary ways to render elements: using high-level builders or by building the element tree directly.

3.A. High-Level Builders (Recommended for Standard Tasks)

For complex elements like tables, use dedicated types (rich_table, standard_table) that accept a declarative specification in the $props array.

Example 1: Rendering a Complex Table (rich_table)

$table_props = [
    'columns' => [
        'name' => ['label' => 'User'],
        'action' => ['label' => 'Action', 'attributes' => ['class' => 'text-center']],
    ],
    'rows' => [
        [
            'attributes' => ['class' => 'active-row'],
            'cells' => [
                'name' => 'Anna',
                'action' => new CRB_UI_Element('button', [], ['label' => 'Edit']),
            ]
        ]
    ],
    'render_footer' => true,
];

$table = new CRB_UI_Element('rich_table', $table_props, ['class' => 'table']);

echo crb_ui_renderer()->render_element($table);

Example 2: Rendering a Simple Table (simple_table)

Use this for displaying simple, non-interactive data from a 2D array.

$plain_data = [
    ['Row 1, Cell 1', 'Row 1, Cell 2'],
    ['Row 2, Cell 1', 'Row 2, Cell 2'],
];

$plain_headers = ['Column A', 'Column B'];

$table_props = [
    'headers' => $plain_headers,
    'data' => $plain_data,
];

$simple_table = new CRB_UI_Element('simple_table', $table_props, ['class' => 'my-cool-table']);

echo crb_ui_renderer()->render_element($simple_table);

3.B. Direct Tree Building (For Full Control)

You can construct any HTML fragment by creating a tree of basic elements (div, p, span, etc.) and nesting them using the children property.

Example: Rendering a Custom Card

$card_content = new CRB_UI_Element('div', [], ['class' => 'card-body'], [
    new CRB_UI_Element('p', ['content' => 'This is the card content.'], ['class' => 'card-text']),
    new CRB_UI_Element('button', ['label' => 'Learn More'], ['class' => 'btn']),
]);

$card = new CRB_UI_Element('div', [], ['class' => 'card'], [$card_content]);

echo $ui->render_element($card);

IMPORTANT: All HTML generation must go through crb_ui_renderer()->render_element().
This ensures security, consistency, and an easy migration path to new technologies in the future.

 */

spl_autoload_register( function ( $class_name ) {
	static $classes = [
		'CRB_UI_Html_Renderer'    => '/ui-factory-html-renderer.php',
		'CRB_UI_Element'          => '/ui-factory-html-renderer.php',
		'CRB_UI_Fragment_Builder' => '/ui-factory-builder.php',
		'CRB_UI_Form_Builder'     => '/ui-factory-form-builder.php',
	];

	if ( $file = $classes[ $class_name ] ?? '' ) {
		require_once( __DIR__ . $file );
	}
} );

/**
 * UI Factory - Main Accessor
 *
 * Returns the active UI renderer instance for the application.
 *
 * This function acts as a simple Service Locator/Factory, providing a single
 * point of control for switching the entire UI rendering engine.
 *
 * @return CRB_UI_Renderer The active renderer instance.
 *
 * @since 9.6.9.5
 */
function crb_ui_renderer(): CRB_UI_Renderer {
	static $instance = null;

	if ( $instance === null ) {
		$instance = new CRB_UI_Html_Renderer();
	}

	return $instance;
}

/**
 * The core low-level factory for creating UI Element objects.
 *
 * This function serves as the primary, declarative way to build UI trees.
 * It is designed to be simple, predictable, and stateless. Its main responsibility
 * is to transform a simple, developer-friendly format into a valid CRB_UI_Element DTO.
 *
 * It automatically handles the conversion of string content into 'text' elements,
 * allowing for a clean and readable syntax.
 *
 * --- USAGE EXAMPLES ---
 *
 * // 1. Simple paragraph with text
 * $p = crb_ui_element('p', ['class' => 'lead'], 'Hello, World!');
 *
 * // 2. A div with nested elements
 * $container = crb_ui_element('div', ['class' => 'container'], [
 * crb_ui_element('h1', [], 'Title'),
 * crb_ui_element('p', [], 'Some description text.'),
 * ]);
 *
 * // 3. An empty element
 * $divider = crb_ui_element('hr');
 *
 * // 4. A link
 * $link = crb_ui_element('a', ['href' => 'https://example.com'], 'Click here');
 *
 * @param string $type The element type (e.g., 'div', 'p', 'a', 'text').
 * @param array $attributes Optional. An associative array of HTML attributes (e.g., ['class' => '...', 'href' => '...']).
 * @param mixed $content Optional. The content or children of the element. Can be:
 * - a string (will be converted to a 'text' element).
 * - a CRB_UI_Element object.
 * - an array of strings or CRB_UI_Element objects.
 * - null for an empty element.
 *
 * @param array $props Optional. Component-specific properties (e.g. label, icon name, raw HTML).
 *
 * @return CRB_UI_Element A new instance of the UI element DTO.
 *
 * @since 9.6.9.8
 */
function crb_ui_element( string $type, array $attributes = [], $content = null, array $props = [] ): CRB_UI_Element {
	$children = [];

	$content_items = is_array( $content ) ? $content : [ $content ];

	foreach ( $content_items as $item ) {
		if ( $item instanceof CRB_UI_Element ) {
			$children[] = $item;
		}
		elseif ( is_string( $item ) || is_numeric( $item ) ) {
			if ( (string) $item !== '' ) {
				$children[] = new CRB_UI_Element( 'text', [ 'content' => (string) $item ] );
			}
		}
	}

	return new CRB_UI_Element( $type, $props, $attributes, $children );
}


/**
 * == THIS IS A PROPOSAL: it requires renderer support (e.g. CRB_UI_Html_Renderer) to be implemented as of 9/28/2025 ==
 *
 * A convenient shortcut for creating a 'fragment' element.
 *
 * A fragment is a special type of element that has no HTML tag itself.
 * When rendered, only its children are outputted. This is useful for grouping
 * a list of elements without adding an extra wrapping div.
 *
 * --- USAGE EXAMPLE ---
 *
 * // Returns a list of list items, without a wrapping <ul>
 * function get_list_items() {
 * return crb_ui_fragment([
 * crb_ui_element('li', [], 'Item 1'),
 * crb_ui_element('li', [], 'Item 2'),
 * ]);
 * }
 *
 * @param array $children An array of child elements (strings or CRB_UI_Element objects).
 *
 * @return CRB_UI_Element A new instance of a fragment element.
 *
 * @since 9.6.9.8
 */
function crb_ui_fragment( array $children ): CRB_UI_Element {
	return crb_ui_element( 'fragment', [], $children );
}
