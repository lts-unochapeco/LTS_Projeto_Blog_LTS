<?php
/**
 * A high-level, self-contained builder for creating complex forms with layouts.
 *
 * This class serves as a "Level 1" architectural abstraction. Its primary role is to
 * provide a clean, fluent interface for declaratively constructing forms, abstracting
 * away the underlying "Level 2" DSL (`crb_ui_element()`). The builder manages the
 * creation of various field types, their arrangement into columns, and the final
 * assembly into a single <form> DTO (CRB_UI_Element).
 *
 * This approach promotes clean, readable, and maintainable application code by
 * separating the "what" (the declaration of a form's structure) from the "how"
 * (the low-level construction of individual HTML tags).
 *
 * --- USAGE EXAMPLE ---
 *
 * $form_element = CRB_UI_Form_Builder::create('login-form', 'post', '/login')
 * ->add_hidden_field('nonce', 'xyz123')
 * ->add_field('text', 'username', 'Username')
 * ->add_field('password', 'pass', 'Password')
 * ->add_checkbox('remember_me', 'Remember Me')
 * ->add_submit('Login')
 * ->to_element();
 *
 * @since 9.6.9.8
 */
final class CRB_UI_Form_Builder {

	private string $id;
	private string $method;
	private string $action;

	/** @var CRB_UI_Element[][] */
	private array $columns = [ [] ];
	private int $current_column = 0;

	/** @var CRB_UI_Element|null */
	private ?CRB_UI_Element $submit_container = null;

	/** @var CRB_UI_Element[] */
	private array $hidden_fields = [];

	private function __construct( string $id, string $method, string $action ) {
		$this->id = $id;
		$this->method = $method;
		$this->action = $action;
	}

	/**
	 * Instantiates the builder via a static factory method.
	 *
	 * @param string $id The form's HTML 'id' attribute.
	 * @param string $method The form's HTTP 'method' attribute ('get' or 'post').
	 * @param string $action The form's 'action' URL.
	 *
	 * @return self
	 */
	public static function create( string $id, string $method = 'post', string $action = '' ): self {
		return new self( $id, $method, $action );
	}

	/**
	 * Appends a standard form field (e.g., text, number, date) to the current column.
	 * Delegates creation to an internal factory method.
	 *
	 * @param string $type The input's 'type' attribute.
	 * @param string $name The input's 'name' attribute.
	 * @param string $label The text for the associated <label>.
	 * @param string $hint An optional hint text displayed below the input. Defaults to an empty string.
	 * @param array $input_attrs Additional HTML attributes for the <input> element.
	 *
	 * @return self
	 */
	public function add_field( string $type, string $name, string $label, string $hint = '', array $input_attrs = [] ): self {
		$this->columns[ $this->current_column ][] = $this->make_form_field( $type, $name, $label, $hint, $input_attrs );

		return $this;
	}

	/**
	 * Appends a standard checkbox field to the current column.
	 * Delegates creation to an internal factory method.
	 *
	 * @param string $name The checkbox's 'name' attribute.
	 * @param string $label The text for the associated <label>.
	 * @param string $value The checkbox's 'value' attribute.
	 * @param array $p_attrs Additional HTML attributes for the wrapping <p> element.
	 *
	 * @return self
	 */
	public function add_checkbox( string $name, string $label, string $value = '1', array $p_attrs = [] ): self {
		$this->columns[ $this->current_column ][] = $this->make_checkbox_field( $name, $label, $value, $p_attrs );

		return $this;
	}

	/**
	 * Appends a <select> dropdown field to the current column.
	 *
	 * @param string $name The select's 'name' attribute.
	 * @param string $label The text for the associated <label>.
	 * @param array $options An associative array of options ['value' => 'Text', ...].
	 * @param array $select_attrs Additional HTML attributes for the <select> element.
	 *
	 * @return self
	 */
	public function add_select( string $name, string $label, array $options, array $select_attrs = [] ): self {
		$this->columns[ $this->current_column ][] = $this->make_select_field( $name, $label, $options, $select_attrs );

		return $this;
	}

	/**
	 * Appends a hidden input field to the form.
	 * These fields will be rendered at the beginning of the form.
	 *
	 * @param string $name The input's 'name' attribute.
	 * @param string $value The input's 'value' attribute.
	 *
	 * @return self
	 */
	public function add_hidden_field( string $name, string $value ): self {
		$this->hidden_fields[] = crb_ui_element( 'input', [
			'type'  => 'hidden',
			'name'  => $name,
			'value' => $value,
		] );

		return $this;
	}

	/**
	 * Appends a pre-constructed, custom element DTO to the current column.
	 * Use for non-standard elements not covered by other builder methods.
	 *
	 * @param CRB_UI_Element $element The element DTO to add.
	 *
	 * @return self
	 */
	public function add_custom_element( CRB_UI_Element $element ): self {
		$this->columns[ $this->current_column ][] = $element;

		return $this;
	}

	/**
	 * Appends a raw HTML string to the current column.
	 * This is a gateway for legacy code and bypasses safety checks.
	 *
	 * @note For temporary use only. Should be replaced with native components.
	 * @warning: Only use this for trusted, escaped HTML output.
	 *
	 * @param string $html The raw HTML to inject.
	 *
	 * @return self
	 */
	public function add_raw_html( string $html ): self {
		$this->columns[ $this->current_column ][] = crb_ui_element( 'raw_html', [], '', [ 'content' => $html ] );

		return $this;
	}

	/**
	 * Advances the insertion point to the next layout column.
	 * All subsequent `add_*` calls will populate this new column.
	 *
	 * @return self
	 */
	public function next_column(): self {
		$this->current_column ++;
		$this->columns[ $this->current_column ] = [];

		return $this;
	}

	/**
	 * Defines the Submit button for the form.
	 * The button will be rendered after the main content columns.
	 *
	 * @param string $value The text displayed on the button (its 'value' attribute).
	 * @param array $input_attrs Additional HTML attributes for the <input> element.
	 *
	 * @return self
	 */
	public function add_submit( string $value, array $input_attrs = [] ): self {
		$default_attrs = [
			'type'  => 'submit',
			'class' => 'button button-primary',
			'value' => $value,
		];

		$this->submit_container = crb_ui_element( 'div', [], [
			crb_ui_element( 'p', [], [
				crb_ui_element( 'input', array_merge( $default_attrs, $input_attrs ) ),
			] ),
		] );

		return $this;
	}

	/**
	 * Finalizes the build process and returns the complete <form> element DTO.
	 *
	 * @return CRB_UI_Element The fully constructed form element tree.
	 */
	public function to_element(): CRB_UI_Element {
		$column_elements = [];
		foreach ( $this->columns as $fields ) {
			if ( ! empty( $fields ) ) {
				$column_elements[] = crb_ui_element( 'div', [], $fields );
			}
		}

		$form_children = array_merge(
			$this->hidden_fields,
			[ crb_ui_element( 'div', [], $column_elements ) ]
		);

		if ( $this->submit_container ) {
			$form_children[] = $this->submit_container;
		}

		return crb_ui_element( 'form', [
			'id'     => $this->id,
			'method' => $this->method,
			'action' => $this->action
		], $form_children );
	}

	// --- Private Field Factories ---

	/**
	 * Internal factory for creating the DTO for a standard form field block.
	 * Encapsulates the specific DOM structure (p > label + br + input + hint).
	 *
	 * @param string $type The input's 'type' attribute.
	 * @param string $name The input's 'name' attribute.
	 * @param string $label The text for the associated <label>.
	 * @param string $hint An optional hint text. An empty string is ignored.
	 * @param array $input_attrs Additional HTML attributes for the <input> element.
	 *
	 * @return CRB_UI_Element
	 */
	private function make_form_field( string $type, string $name, string $label, string $hint = '', array $input_attrs = [] ): CRB_UI_Element {
		$id = 'field-' . str_replace( [ '[', ']' ], [ '-', '' ], $name );

		$children = [
			crb_ui_element( 'label', [ 'for' => $id ], $label ),
			crb_ui_element( 'br' ),
			crb_ui_element( 'input', array_merge( [
				'id'   => $id,
				'type' => $type,
				'name' => $name,
			], $input_attrs ) ),
		];

		if ( $hint !== '' ) {
			$children[] = crb_ui_element( 'br' );
			$children[] = crb_ui_element( 'span', [ 'class' => 'crb-input-hint' ], $hint );
		}

		return crb_ui_element( 'p', [], $children );
	}

	/**
	 * Internal factory for creating the DTO for a standard checkbox field.
	 * Encapsulates the specific DOM structure (p > input + label).
	 *
	 * @param string $name The checkbox's 'name' attribute.
	 * @param string $label The text for the associated <label>.
	 * @param string $value The checkbox's 'value' attribute.
	 * @param array $p_attrs Additional HTML attributes for the wrapping <p> element.
	 *
	 * @return CRB_UI_Element
	 */
	private function make_checkbox_field( string $name, string $label, string $value = '1', array $p_attrs = [] ): CRB_UI_Element {
		$id = 'field-' . $name;

		return crb_ui_element( 'p', array_merge( [ 'class' => 'crb-has-checkbox' ], $p_attrs ), [
			crb_ui_element( 'input', [ 'id' => $id, 'type' => 'checkbox', 'name' => $name, 'value' => $value ] ),
			crb_ui_element( 'label', [ 'for' => $id ], $label ),
		] );
	}

	/**
	 * Internal factory for creating the DTO for a <select> dropdown field.
	 *
	 * @param string $name The select's 'name' attribute.
	 * @param string $label The text for the associated <label>.
	 * @param array $options An associative array of options ['value' => 'Text', ...].
	 * @param array $select_attrs Additional HTML attributes for the <select> element.
	 *
	 * @return CRB_UI_Element
	 */
	private function make_select_field( string $name, string $label, array $options, array $select_attrs = [] ): CRB_UI_Element {
		$id = 'field-' . str_replace( [ '[', ']' ], [ '-', '' ], $name );

		$option_elements = [];
		foreach ( $options as $value => $text ) {
			$option_attrs = [ 'value' => $value ];
			$option_elements[] = crb_ui_element( 'option', $option_attrs, $text );
		}

		return crb_ui_element( 'p', [], [
			crb_ui_element( 'label', [ 'for' => $id ], $label ),
			crb_ui_element( 'select', array_merge( [ 'id' => $id, 'name' => $name ], $select_attrs ), $option_elements ),
		] );
	}
}

