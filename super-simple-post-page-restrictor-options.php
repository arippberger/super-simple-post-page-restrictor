<?php
if ( !class_exists( 'Super_Simple_Post_Page_Options' ) ) {
    class Super_Simple_Post_Page_Options {
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

            //get options
            $this->options = get_option( 'ss_pp_restrictor_option' );

            //get post types set on settings page
            $applicable_post_types = $this->options['post_type_select'];

            $post_types = get_post_types(); //get all post types

            if ( is_array($applicable_post_types) && is_array($post_types) ) {
                foreach ( $post_types as $post_type ) {
                    if ( in_array( $post_type, $applicable_post_types ) ) {
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
            <label class="small-text" for="post_restriction_checkbox"><?php _e( 'Restrict post/page content to logged-in users?', 'ss_pp_restrictor' ); ?></label>
            <br />
            <input type="checkbox" name="ss_pp_restrictor_checkbox" id="ss_pp_restrictor_checkbox" value="1" <?php checked( $checked ); ?> />
          </p><?php

        }

        /**
        * Hook to save and save $_POST variables
        */
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
                'Page unavailable text', // Title
                array( $this, 'page_unavailable_text_callback' ), // Callback
                'ss_pp_restrictor', // Page
                'ss_pp_restrictor_settings' // Section
            );


            add_settings_field(
                'post_type_select', // ID
                'Apply to which post types?', // Title
                array( $this, 'post_type_select_callback' ), // Callback
                'ss_pp_restrictor', // Page
                'ss_pp_restrictor_settings' // Section
            );

            add_settings_field(
                'user_role_select', // ID
                'Never display restricted content for which user types?', // Title
                array( $this, 'user_role_select_callback' ), // Callback
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

            if( isset( $input['page_unavailable_text'] ) ) {
                $new_input['page_unavailable_text'] = sanitize_text_field( $input['page_unavailable_text'] );
            }

            if( isset( $input['post_type_select'] ) ) {

                if ( is_array( $input['post_type_select'] ) ) {

                	$all_post_types = get_post_types();

                    foreach ($input['post_type_select'] as $key => $value ) {

						//sanitize via whitelist - if input does not exist in existing post types, set value to blank string
                    	if ( in_array( $value, $all_post_types ) ) {
                        	$new_input['post_type_select'][ $key ] = $value;
                        } else {
                        	$new_input['post_type_select'][ $key ] = '';
                        }
                        
                    }
                } else {
                    $new_input['post_type_select'] = sanitize_text_field( $input['post_type_select'] );
                }
            }

            if( isset( $input['user_role_select'] ) ) {

            	$editable_roles = array_reverse( get_editable_roles() );
                
                if ( is_array( $input['user_role_select'] ) ) {
                    foreach ($input['user_role_select'] as $key => $value ) {
                        
                  		//sanitize via whitelist - if input does not exist in editable roles, set value to blank string
                        if ( array_key_exists( $value, $editable_roles ) ) {
                        	$new_input['user_role_select'][ $key ] = $value;
                        } else {
                        	$new_input['user_role_select'][ $key ] = '';
                        }
                        
                    }
                } else {
                    $new_input['user_role_select'] = sanitize_text_field( $input['user_role_select'] );
                }
            }

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
                '<label class="small-text" for="page_unavailable_text">Enter the text you&apos;d like to display when content is restricted.<br>Defaults to "This content is currently unavailable to you".</label>',
                isset( $this->options['page_unavailable_text'] ) ? esc_attr( $this->options['page_unavailable_text']) : '',
                array( 'label_for' => 'page_unavailable_text')
            );
        }

        public function post_type_select_callback() {

            $all_post_types = get_post_types();
            $selected_post_types = $this->options['post_type_select'];

            if ( is_array( $all_post_types ) ) {
                echo '<select id="post_type_select" data-placeholder="Select some post types" name="ss_pp_restrictor_option[post_type_select][]" multiple>';
                foreach ( $all_post_types as $key => $post_type ) {
                    $selected = '';
                    if ( is_array( $selected_post_types ) ) {
                        $selected = in_array( $post_type, $selected_post_types ) ? 'selected' : '';
                    }

                    printf('<option value="%s" %s>%s</option>', $post_type, $selected, $post_type);
                }
                echo '</select>';
            }

        }


        public function user_role_select_callback() {

            $selected_user_roles = isset($this->options['user_role_select']) ? $this->options['user_role_select'] : array();
            ?>
            <select id="user_role_select" data-placeholder="Select some user roles" name="ss_pp_restrictor_option[user_role_select][]" multiple>';
              <?php $this->wp_dropdown_roles( $selected_user_roles );?>
            </select><br>
            <label class="small-text" for="user_role_select">Selected user roles will never be able to see restricted content - even when logged in.</label><?php
        }



        /*modified wp_dropdown_roles() function - copied from /wp-admin/includes/template.php */
        private function wp_dropdown_roles( $selected = false ) {

            $output = '';

            $editable_roles = array_reverse( get_editable_roles() );

            //error_log(print_r($editable_roles, true));

            if ( !is_array( $selected ) ) {
              $selected = array ( $selected );
            }


            foreach ( $editable_roles as $role => $details ) {
              $name = translate_user_role($details['name'] );
              if ( in_array( $role, $selected ) ) {// preselect specified role
                $output .= "\n\t<option selected='selected' value='" . esc_attr($role) . "'>$name</option>";
              }
              else {
                $output .= "\n\t<option value='" . esc_attr($role) . "'>$name</option>";
              }
            }


            echo $output;

        }
    }
}
?>
