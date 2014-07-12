<?php
/*
Plugin Name: Super Simple Post / Page Restrictor
Plugin URI: https://github.com/arippberger/super-simple-post-page-restrictor
Description: Adds a super simple post / page restriction option
Version: 1.0
Author: arippberger
Author URI: http://alecrippberger.com
License: GPL2
*/
/*  Copyright 2014 Alec Rippberger

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( !class_exists( 'Super_Simple_Page_Post_Restrictor' ) ) {

	class Super_Simple_Page_Post_Restrictor {

		public function __construct() {

			add_action( 'init', array( $this, 'init_frontend' ) );

			//initialize admin
			if ( is_admin() ) {
				$this->admin_includes();
			}

		} // End __construct()

		public function init_frontend() {

			//hook main function
			//add_action( 'pre_get_posts', array( $this, 'dump_query' ) );
			add_action( 'the_post', array( $this, 'clean_post' ), 1 );

			//enqueue scripts and styles
			add_action( 'admin_enqueue_scripts', array( $this, 'super_simple_post_restrictor_admin_scripts' ) );

			//restrict feed content
			// add_action('rss_head', array( $this, 'restrict_feed' ) );
			// add_action('rss2_head', array( $this, 'restrict_feed' ) );

		} // End init()

		public function admin_includes() {
			// loads the admin settings page and adds functionality to the order admin
			require_once( 'super-simple-post-page-restrictor-options.php' );
			$this->admin = new Super_simple_post_page_options();
		}

		public function super_simple_post_restrictor_admin_scripts(){
			wp_enqueue_style( 'chosen-styles', plugin_dir_url( __FILE__ ) . '/assets/css/chosen.min.css' );
			wp_enqueue_script( 'chosen-script', plugin_dir_url( __FILE__ ) . '/assets/js/chosen.jquery.min.js', array( 'jquery' ), '1.1.0', true );
			wp_enqueue_script( 'chosen-init', plugin_dir_url( __FILE__ ) . '/assets/js/chosen-init.js', array( 'jquery', 'chosen-script') );
		}

		public function clean_post( $post_object ) {

			//var_dump($post_object);

			$this->options = get_option( 'ss_pp_restrictor_option' );

			//get and store current post checkbox
			$this->current_post_checkbox = get_post_meta( $post_object->ID, 'ss_pp_restrictor_checkbox', true );

			//see if current post type is restricted
			$restricted_post_type = false;
			if ( is_array( $this->options['post_type_select'] ) ) {
				if ( in_array(  $post_object->post_type, $this->options['post_type_select'] ) ) {
					$restricted_post_type = true;
				}
			}

			//get array of roles that may NEVER access content
			$restricted_roles = $this->options['user_role_select'];

			//get array of current user roles
			$current_user_roles = $this->get_current_user_roles();

			//all users start with access
			$current_user_can_access = true;

			//loop through current user roles and check if any roles are in restricted roles array
			foreach ($current_user_roles as $key => $role) {
				if ( in_array( $role, $restricted_roles) ) {
					//restrict access
					$current_user_can_access = false;
				}
			}

			//if current post is restricted and user is not logged in - OR - check if current post is restricted and user can't access
			if ( $this->current_post_checkbox && !is_user_logged_in() && $restricted_post_type || $restricted_post_type && $this->current_post_checkbox && !$current_user_can_access ) {
				add_filter('the_content', array( $this, 'filter_content' ) );
				add_filter('the_excerpt', array( $this, 'filter_excerpt' ) );
				// add_action('rss_head', array( $this, 'restrict_feed' ) );
				// add_action('rss2_head', array( $this, 'restrict_feed' ) );
			} else {

			}

		}
	
		public function restrict_feed(){
			die('this is the rss_head');
			add_filter('the_content', array( $this, 'filter_feed_content' ) );
		}

		public function filter_feed_content($content) {
			global $wp_query;
			if (is_feed() && $this->current_post_checkbox) {
				$this->page_unavailable_text = $this->options['page_unavailable_text'];
				$post_content = !empty( $this->page_unavailable_text ) ? $this->page_unavailable_text : 'This content is currently unavailable to you. ';
				return $post_content;
			} else {
				return $content;
			}
			
		}		
		
		public function filter_content($content) {
			if ( $this->current_post_checkbox ) {

				$this->page_unavailable_text = $this->options['page_unavailable_text'];
				$post_content = !empty( $this->page_unavailable_text ) ? $this->page_unavailable_text : 'This content is currently unavailable to you. ';
				return $post_content;
			} else {
				return $content;
			}

		}

		public function filter_excerpt($excerpt) {
			if ( $this->current_post_checkbox ) {

				$this->page_unavailable_text = $this->options['page_unavailable_text'];
				$post_content = !empty( $this->page_unavailable_text ) ? $this->page_unavailable_text : 'This content is currently unavailable to you. ';
				return $post_content;
			} else {
				return $excerpt;
			}

		}

		/**
		 * Returns the role of the current user. 
		 *
		 * @return array containing the names of the current users role(s)
		 **/
		private function get_current_user_roles() {
			global $wp_roles;
			$current_user = wp_get_current_user();
			$roles = $current_user->roles;

			$translated_roles = array();
				
			foreach ($roles as $key => $role) {
				//$translated_roles[] = isset($wp_roles->role_names[$role]) ? translate_user_role($wp_roles->role_names[$role] ) : $role;
				$translated_roles[] = $role;
			}

			return $translated_roles;
		}

	} // End Class
}
global $super_simple_page_post_restrictor;
$super_simple_page_post_restrictor = new Super_Simple_Page_Post_Restrictor();

?>
