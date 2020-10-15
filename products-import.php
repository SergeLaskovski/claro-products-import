<?php

/**

 * @wordpress-plugin
 * Plugin Name:       Claro Products Import
 * Plugin URI:        
 * Description:       Parses Excel file imported from CLARO MYOB AccountRight
 * Version:           1.0.0
 * Author:            Serge Laskovski
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class CLARO_PRODUCT_IMPORT{



    public function __construct(){
        
        add_action( 'admin_menu',  array( $this, 'add_submenu_csv_parse' ) );

    }

   
    

    public function add_submenu_csv_parse(){
        add_submenu_page( 'edit.php?post_type=product', 'Upload Claro Products', 'Upload Claro Products', 'manage_options', 'upload_claro_products', array( $this, 'csv_parser' ) );
    }

    public function csv_parser(){
        require_once  dirname( __FILE__ ) . '/includes/csv_parser.php';
        $xml_parser = new CSV_Parser;
    }

}

$upload_claro_products = new CLARO_PRODUCT_IMPORT;