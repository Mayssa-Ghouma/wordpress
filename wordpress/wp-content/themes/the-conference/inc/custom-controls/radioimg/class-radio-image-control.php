<?php
/**
 * The Conference Customizer Radio Image Control.
 * 
 * @package The Conference
*/

// Exit if accessed directly.
if( ! defined( 'ABSPATH' ) ){
	exit;
}

if( ! class_exists( 'The_Conference_Radio_Image_Control' ) ){
	/**
	 * Radio Image control (modified radio).
    */
	class The_Conference_Radio_Image_Control extends WP_Customize_Control {

		public $type = 'radio-image';
        
        public $tooltip = '';
        public $feat_class = '';
        
		public function to_json() {
			parent::to_json();
			
            if ( isset( $this->default ) ) {
				$this->json['default'] = $this->default;
			} else {
				$this->json['default'] = $this->setting->default;
			}
			
            $this->json['value']   = $this->value();
			$this->json['choices'] = $this->choices;
			$this->json['link']    = $this->get_link();
            $this->json['id']      = $this->id;
            $this->json['tooltip'] = $this->tooltip;
            $this->json['feat_class'] = $this->feat_class;
						
            $this->json['inputAttrs'] = '';
			foreach ( $this->input_attrs as $attr => $value ) {
				$this->json['inputAttrs'] .= $attr . '="' . esc_attr( $value ) . '" ';
			}
		}
        
        public function enqueue() {            
            wp_enqueue_style( 'the-conference-radio-image', get_template_directory_uri() . '/inc/custom-controls/radioimg/radio-image.css', null );
            wp_enqueue_script( 'the-conference-radio-image', get_template_directory_uri() . '/inc/custom-controls/radioimg/radio-image.js', array( 'jquery' ), false, true ); //for radio-image                
        }

		protected function content_template() {
			?>
			<# if ( data.tooltip ) { #>
				<a href="#" class="tooltip hint--left" data-hint="{{ data.tooltip }}"><span class='dashicons dashicons-info'></span></a>
			<# } #>
			<# if ( data.label || data.description ) { #>
				<label class="customizer-text">
					<# if ( data.label ) { #>
						<span class="customize-control-title">{{{ data.label }}}</span>
					<# } #>
					<# if ( data.description ) { #>
						<span class="description customize-control-description">{{{ data.description }}}</span>
					<# } #>
				</label>
			<# } #>
			<div id="input_{{ data.id }}" class="image {{data.feat_class}}">
				<# for ( key in data.choices ) { #>
					<input {{{ data.inputAttrs }}} class="image-select" type="radio" value="{{ key }}" name="_customize-radio-{{ data.id }}" id="{{ data.id }}{{ key }}" {{{ data.link }}}<# if ( data.value === key ) { #> checked="checked"<# } #>>
						<label for="{{ data.id }}{{ key }}">
							<img src="{{ data.choices[ key ] }}">
							<span class="image-clickable"></span>
						</label>
					</input>
				<# } #>
			</div>
			<?php
		}
	}
}