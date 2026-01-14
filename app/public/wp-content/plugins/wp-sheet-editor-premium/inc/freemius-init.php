<?php

defined( 'ABSPATH' ) || exit;
// Create a helper function for easy SDK access.
if ( !function_exists( 'vgse_freemius' ) ) {
    function vgse_freemius()
    {
        if ( ! class_exists( 'wpseFsNull' ) ) {
             class wpseFsNull {
                 function is_registered() {
                     return true;
            }
                 function can_use_premium_code() {
                     return true;
                 }
 
                 function can_use_premium_code__premium_only() {
                     return true;
                 }
 
                 function get_id() {
                     return;
                 }
 
                 function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
                     add_filter( $tag, $function_to_add, $priority, $accepted_args );
                 }
 
                 function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
                     add_action( $tag, $function_to_add, $priority, $accepted_args );
                 }
                 function checkout_url() {
                     return;
        }
        
                 function get_account_url() {
                     return;
    }
                 function pricing_url() {
                     return;
                 }
             }
         }
         return new wpseFsNull();
     }

}
// Init Freemius.
vgse_freemius();
vgse_freemius()->add_filter( 'show_deactivation_feedback_form', '__return_false' );
// Signal that SDK was initiated.
do_action( 'vgse_freemius_loaded' );