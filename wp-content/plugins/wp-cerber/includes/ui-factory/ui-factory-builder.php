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
 * A Fluent Builder for creating composite UI fragments.
 *
 * This class provides a convenient, chainable interface for assembling complex
 * content from various parts (text, raw HTML, other UI elements). It simplifies
 * the process of building a `children` array for a container element.
 *
 * It's render-type agnostic - can be used with any renderer.
 *
 * @since 9.6.9.6
 *
 */
class CRB_UI_Fragment_Builder {
	/**
	 * @var CRB_UI_Element[]
	 */
	private array $parts = [];

	/**
	 * Factory method to start a new fragment chain.
	 *
	 * @return self
	 *
	 * @since 9.6.9.6
	 */
	public static function create(): self {
		return new self();
	}

	/**
	 * Adds a plain, safely escaped text node to the fragment.
	 *
	 * @param string $text The text content to add.
	 *
	 * @return self
	 *
	 * @since 9.6.9.6
	 */
	public function add_text( string $text ): self {
		if ( ! empty( $text ) ) {
			$this->parts[] = new CRB_UI_Element( 'text', [ 'content' => $text ] );
		}

		return $this;
	}

	/**
	 * Adds a raw, unescaped HTML string to the fragment.
	 * Use with caution and only for trusted HTML from other helpers.
	 *
	 * @param string $html The raw HTML string.
	 *
	 * @return self
	 *
	 * @since 9.6.9.6
	 */
	public function add_html( string $html ): self {
		if ( ! empty( $html ) ) {
			$this->parts[] = new CRB_UI_Element( 'html', [ 'content' => $html ] );
		}

		return $this;
	}

	/**
	 * Adds a pre-built UI element to the fragment.
	 *
	 * @param CRB_UI_Element $element The element to add.
	 *
	 * @return self
	 *
	 * @since 9.6.9.6
	 */
	public function add_element( CRB_UI_Element $element ): self {
		$this->parts[] = $element;

		return $this;
	}

	/**
	 * Assembles the collected parts into a single container element.
	 *
	 * @param string $wrapper_tag The HTML tag for the container (e.g., 'div', 'span').
	 * @param array $attributes HTML attributes for the container.
	 *
	 * @return CRB_UI_Element The final container element, ready to be rendered.
	 *
	 * @since 9.6.9.6
	 */
	public function to_element( string $wrapper_tag = 'div', array $attributes = [] ): CRB_UI_Element {
		return new CRB_UI_Element( $wrapper_tag, [], $attributes, $this->parts );
	}

	/**
	 * Assembles and immediately renders the fragment.
	 *
	 * @param string $wrapper_tag The HTML tag for the container.
	 * @param array $attributes HTML attributes for the container.
	 *
	 * @return string The rendered HTML string.
	 *
	 * @since 9.6.9.6
	 */
	public function render( string $wrapper_tag = 'div', array $attributes = [] ): string {
		$element = $this->to_element( $wrapper_tag, $attributes );

		return crb_ui_renderer()->render_element( $element );
	}
}
