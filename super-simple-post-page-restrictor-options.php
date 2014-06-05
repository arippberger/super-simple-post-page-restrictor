<?php
if ( !class_exists( 'Super_simple_post_page_options' ) ) {
    class Super_simple_post_page_options {
        /**
         * Holds the values to be used in the fields callbacks
         */
        private $options;

        /**
         * Start up
         */
        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
            add_action( 'admin_init', array( $this, 'page_init' ) );
            add_action( 'add_meta_boxes', array( $this, 'add_post_restriction_checkbox' ) );
            add_action( 'save_post', array( $this, 'save_post_restriction_checkbox' ), 13, 2 );
            
        }

        /**
        * Setup checkbox meta
        */
        public function setup_post_restriction_checkbox() {

        }

        /**
        * Add checkbox to posts/pages which will allow users to select whether a post/page should be restricted
        */
        public function add_post_restriction_checkbox() {
            $post_types = get_post_types(); //get all post types
            foreach ( $post_types as $post_type ) {
                add_meta_box(
                    'ss_pp_restriction_checkbox', // Unique ID
                    esc_html__( 'Restrict Post?', 'ss_pp_restrictor' ),   
                    array( $this, 'post_restriction_checkbox' ),   
                    $post_type,    
                    'side',   
                    'default'    
                );
            }
        }

        /**
        * Display meta box. 
        */
        public function post_restriction_checkbox( $object, $box ) {

          wp_nonce_field( basename( __FILE__ ), 'post_restriction_checkbox_nonce' );
          $checked = get_post_meta( $object->ID, 'ss_pp_restrictor_checkbox', true ); 
          //var_dump( $checked ); 
          ?>
          <p>
            <label for="post_restriction_checkbox"><?php _e( 'Restrict post/page content to logged-in users?', 'ss_pp_restrictor' ); ?></label>
            <br />
            <input type="checkbox" name="ss_pp_restrictor_checkbox" id="ss_pp_restrictor_checkbox" value="1" <?php checked( $checked ); ?> />
          </p><?php 

        }      

        public function save_post_restriction_checkbox( $post_id, $post ) {

            // error_log(print_r($post_id));
            // error_log(print_r($post));

            //verify nonce
            if ( !isset( $_POST['post_restriction_checkbox_nonce'] ) || !wp_verify_nonce( $_POST['post_restriction_checkbox_nonce'], basename( __FILE__ ) ) ) {
                //error_log('nonce not valid');
                return $post_id;
            }
                
            //get current post type
            $post_type = get_post_type_object( $post->post_type );

            //ensure current user can edit post
            if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) {
                //error_log('user cannot edit post');
                return $post_id;
            }

            //new checkbox value
            $new_checkbox_value = ( isset( $_POST[ 'ss_pp_restrictor_checkbox' ] ) ? filter_var( $_POST[ 'ss_pp_restrictor_checkbox' ], FILTER_SANITIZE_NUMBER_INT ) : '' );

            

            //get old checkbox value
            $checkbox_value = get_post_meta( $post_id, 'ss_pp_restrictor_checkbox', true );

            //if new value added and there is no current value
            if ( $new_checkbox_value && '' == $checkbox_value ) {

                add_post_meta( $post_id, 'ss_pp_restrictor_checkbox', $new_checkbox_value, true );

            } else if ( $new_checkbox_value && $new_checkbox_value != $checkbox_value ) { //if new checkbox value submitted and it doesn't match old

                update_post_meta( $post_id, 'ss_pp_restrictor_checkbox', $new_checkbox_value );

            } else if ( '' == $new_checkbox_value && $checkbox_value ) { //if new checkbox value is empty and old exists, delete new

                delete_post_meta( $post_id, 'ss_pp_restrictor_checkbox', $checkbox_value ); 

            }

        }

        /**
         * Add options page
         */
        public function add_plugin_page() {
            // This page will be under "Settings"
            add_options_page(
                'Settings Admin', 
                'Super Simple Post / Page Restrictor', 
                'manage_options', 
                'ss_pp_restrictor', 
                array( $this, 'create_admin_page' )
            );
        }

        /**
         * Options page callback
         */
        public function create_admin_page() {
            
            // Set class property
            $this->options = get_option( 'ss_pp_restrictor_option' );

            //var_dump($this->options);

            ?>
            <div class="wrap">
                <?php screen_icon(); ?>
                <h2>Super Simple Post / Page Restrictor</h2>           
                <form method="post" action="options.php">
                <?php
                    // This prints out all hidden setting fields
                    settings_fields( 'ss_pp_restrictor_option_group' );   
                    do_settings_sections( 'ss_pp_restrictor' );
                    submit_button(); 
                ?>
                </form>
            </div>
            <?php
        }

        /**
         * Register and add settings
         */
        public function page_init() {        
            register_setting(
                'ss_pp_restrictor_option_group', // Option group
                'ss_pp_restrictor_option', // Option name
                array( $this, 'sanitize' ) // Sanitize
            );

            add_settings_section(
                'ss_pp_restrictor_settings', // ID
                'Super Simple Post / Page Restrictor Settings', // Title
                array( $this, 'print_section_info' ), // Callback
                'ss_pp_restrictor' // Page
            );  

            //add setting for ftp server
            add_settings_field(
                'page_unavailable_text', // ID
                'Page Unavailable Text', // Title 
                array( $this, 'page_unavailable_text_callback' ), // Callback
                'ss_pp_restrictor', // Page
                'ss_pp_restrictor_settings' // Section           
            );                    

        }

        /**
         * Sanitize each setting field as needed
         *
         * @param array $input Contains all settings fields as array keys
         */
        public function sanitize( $input ) {
            $new_input = array();

            if( isset( $input['page_unavailable_text'] ) )
                $new_input['page_unavailable_text'] = sanitize_text_field( $input['page_unavailable_text'] );

            return $new_input;

        }

        /** 
         * Print the Section text
         */
        public function print_section_info() {
            // print 'Enter your settings below:';
        }

        /** 
         * Get the settings option array and print one of its values
         */
        public function page_unavailable_text_callback() {
            printf(
                '<textarea id="page_unavailable_text" name="ss_pp_restrictor_option[page_unavailable_text]">%s</textarea><br>' .
                '<label for="page_unavailable_text">Enter the text you&apos;d like to display when content is restricted.<br>Defaults to "This content is currently unavailable to you".</label>',
                isset( $this->options['page_unavailable_text'] ) ? esc_attr( $this->options['page_unavailable_text']) : '', 
                array( 'label_for' => 'page_unavailable_text')
            );
        }         

    }
}
?>