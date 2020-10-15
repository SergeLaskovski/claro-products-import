<?php

class CSV_Parser {

    public function __construct(){
        
        ?>
        <h2>Upload CSV file</h2>
        <form method="post" enctype="multipart/form-data">
            CSV file structure: <strong>Item Number|Item Name|Size|Colour|Barcode|Selling Price|Category|Variation|Back Order|Hide</strong> <br><br>
            <input type="file" name="csv_to_parse"/>
            <input type="submit" name="submit_csv" value="Upload"/>
        </form>

        <?php
        
        //Settings
        require_once("settings.php");
        
        $csv_data = array();
        if (isset($_FILES['csv_to_parse']) && ($_FILES['csv_to_parse']['error'] == UPLOAD_ERR_OK)) {
            $mimes = array('application/vnd.ms-excel','text/plain','text/csv','text/tsv');
            
            if(in_array($_FILES['csv_to_parse']['type'],$mimes)){
                $csv_data = $this->parse_csv($_FILES['csv_to_parse']);
                
                $this->$CSV_DATA_MATCH = new stdClass();
                if(count($csv_data)>0){
                    //array to match csv field list with fields
                    for($i=0;$i<count($csv_data[0]);$i++){
                        if(strlen($csv_data[0][$i]) >0){
                            $this->$CSV_DATA_MATCH->{(string) $csv_data[0][$i]} = (integer) $i;
                        }
                    }
                    $this->$CSV_DATA_MATCH = (array) $this->$CSV_DATA_MATCH;
                    $this->count_products = 0;
                    $this->count_images = 0;
                    $this->loop_through_csv($csv_data);
                    echo "<hr>";
                    echo "Products added: ". $this->count_products;
                    echo "<br>Images added: ". $this->count_images;
                }
                else {
                    $err .= "No data in CSV file<br>";
                }
            } 
            else {
                $err .= "No CSV data<br>";
            }

        } 
        else {
            $err .= "File not uploaded<br>";
        }
        echo "<strong>".$err."</strong>";
        //echo $this->$CSV_FIELDS_STRUCTURE;
    }


    private function parse_csv(){
        $row = 0;
        if (($handle = fopen($_FILES['csv_to_parse']["tmp_name"], "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {

                $csv_data[$row] = $data;

                $row++;
            }
            fclose($handle);
        }
        return $csv_data;
    }

    private function loop_through_csv($csv_data=array()){
        $duplicate_id = null;
        $update_id = null;
        $variable_product_name = '';
        $category_name="";
        $is_variable = false;
        $VAR_PROD = array();
        //for($i=100;$i<110;$i++){
        for($i=1;$i<count($csv_data);$i++){
            

            //check if product is duplicate and delete if true
            $sku = $csv_data[$i][(integer) $this->$CSV_DATA_MATCH['Item Number']];
            if(strlen($sku)>0){
                $this->check_exist($sku);
                
                //check if it is a variation
                $variable_product_name = $csv_data[$i][(integer) $this->$CSV_DATA_MATCH['Variation']];
                if(strlen($variable_product_name)>0){
                    //if it is a varible product add it to array of variable products

                    $VAR_PROD[$variable_product_name][] = $csv_data[$i];
                }
                else{
                    //if it's not a variation create simple product
                    $this->add_new_product($csv_data[$i], $is_variable);
                }
            }


        }
        //loop throught array of variable producs
        foreach($VAR_PROD as $VARIABLE_PARENT_NAME => $VARIABLE_DATA){
            $this->create_variable($VARIABLE_PARENT_NAME,$VARIABLE_DATA);

        }

    }

    private function check_exist($code, $name=false){
        $duplicate_id = false;
        if(!$name){
            //check if product with currenr sku exists and delete if true
            $args = array(
                'post_type'        => 'product',
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $code  
                    )
                )
            ); 
            $query = new WP_Query( $args );
            $duplicates = $query->posts;
            if ( ! empty( $duplicates ) ) {
                foreach($duplicates as $duplicate){
                    $duplicate_id = $duplicate->ID;
                    if( $duplicate_id ){ 
                        $this->delete_product($duplicate_id);
                    }
                }
                
            }
        }
        else{
            //check by name
            $page = get_page_by_title( $code );
            if ( $page->ID ){
                $this->delete_product($page->ID);
            }
        }

        
    }

    private function delete_product($duplicate_id){
        if( has_post_thumbnail( $duplicate_id ) ) {
            $attachment_id = get_post_thumbnail_id( $duplicate_id );
            wp_delete_attachment($attachment_id, true);
        }
    
        if(wp_delete_post( $duplicate_id, true )){
            echo "Duplicate product ".$duplicate_id." deleted<br>";
        }
        

    }

    private function get_category($cat_name=''){

        $cat_name = trim($cat_name);
        $cats = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'name' => $cat_name
        ));
        if ( $cats ) {
            
            foreach($cats as $cat ){
                $cat_id = $cat->term_id;
            }
            return $cat_id;
        } else {
            return 0;
        }
    }



    private function add_new_product($data, $is_variable=false, $duplicate_id=null){
        //function to create new simple product
        $product = new WC_Product_Simple();
        
        $cat = $data[(integer) $this->$CSV_DATA_MATCH['Category']];
        $product->set_category_ids(array($this->get_category($cat)));
        $product->set_name($data[(integer) $this->$CSV_DATA_MATCH['Item Name']]);
        $product->set_sku($data[(integer) $this->$CSV_DATA_MATCH['Item Number']]);
        $product->set_regular_price($data[$this->$CSV_DATA_MATCH['Selling Price']]);
        $product->set_manage_stock(true);
        if($data[$this->$CSV_DATA_MATCH['Back Order']]=="1"){$back = "yes";}else{$back="no";}
        $product->set_backorders($back);
        if($data[$this->$CSV_DATA_MATCH['Hide']]=="1"){$status = "draft";}else{$status="publish";}
        $product->set_status($status);
        $product->set_description($data[(integer) $this->$CSV_DATA_MATCH['Item Name']]."\n Barcode:".$data[(integer) $this->$CSV_DATA_MATCH['Barcode']]);
        
        if(strlen($data[(integer) $this->$CSV_DATA_MATCH['Size']])>1){
            $product->update_meta_data( 'attribute_'.'pa_size', $data[(integer) $this->$CSV_DATA_MATCH['Size']] );
        }
                
        if(strlen($data[(integer) $this->$CSV_DATA_MATCH['Colour']])>1){
            $product->update_meta_data( 'attribute_'.'pa_color', $data[(integer) $this->$CSV_DATA_MATCH['Colour']] );
        }

        $new_product_id = $product->save();

        
        if($new_product_id ){
            echo "<br><br>Product <strong>".$data[$this->$CSV_DATA_MATCH['Item Name']]." (".$data[(integer) $this->$CSV_DATA_MATCH['Item Number']].")</strong> added<br>";
            $this->count_products++;
            
            //add featured image
            $this->add_image($new_product_id, $data[(integer) $this->$CSV_DATA_MATCH['Item Number']]);
       
        }
        else{echo "<br><br>Product ".$data[$this->$CSV_DATA_MATCH['Item Name']]." failed!!!<br>";}
        return true;
    }



    private function create_variable($VAR_PARENT_NAME, $VAR_DATA){

        //check if variable product with current name exists and delete it if yes
        $this->check_exist($VAR_PARENT_NAME, true);

        //get availbel variations from data
        foreach($VAR_DATA as $key=>$data){
            $variations_arr[] = $data[$this->$CSV_DATA_MATCH['Colour']]." ".$data[$this->$CSV_DATA_MATCH['Size']];
            $cat_name = $data[$this->$CSV_DATA_MATCH['Category']];
        }

        //Create main product
        $product = new WC_Product_Variable();
            //$product->set_sku(time());
            $product->set_name($VAR_PARENT_NAME);
            $product->set_category_ids(array($this->get_category($cat_name)));
            

            //Create the attribute object
            $attribute = new WC_Product_Attribute();
                $attribute->set_id( 0 ); // -> SET to 0
                $attribute->set_name( 'variant' ); 
                $attribute->set_options( $variations_arr);
                $attribute->set_position( 0 );
                $attribute->set_visible( 1 );
                $attribute->set_variation( 1 );
                $product->set_attributes(array($attribute));
            //Save main product to get its id
            $id = $product->save();


        $variation = null;
        //create variations
        foreach($VAR_DATA as $key=>$data){
            $variation = new WC_Product_Variation();
                $variation->set_parent_id($id);
                $variation->set_name($data[(integer) $this->$CSV_DATA_MATCH['Item Name']]);
                $variation->set_sku($data[(integer) $this->$CSV_DATA_MATCH['Item Number']]);
                $variation->set_regular_price($data[$this->$CSV_DATA_MATCH['Selling Price']]);
                $variation->set_manage_stock(true);
                if($data[$this->$CSV_DATA_MATCH['Back Order']]=="1"){$back = "yes";}else{$back="no";}
                $variation->set_backorders($back);
                if($data[$this->$CSV_DATA_MATCH['Hide']]=="1"){$status = "draft";}else{$status="publish";}
                $variation->set_status($status);
                $variation->update_meta_data( 'barcode', $data[(integer) $this->$CSV_DATA_MATCH['Barcode']] );
                $variation->set_description($data[(integer) $this->$CSV_DATA_MATCH['Item Name']]."\nBarcode: ".$data[(integer) $this->$CSV_DATA_MATCH['Barcode']]);

                $variation->set_attributes(array(
                    'variant' =>  $data[$this->$CSV_DATA_MATCH['Colour']]." ".$data[$this->$CSV_DATA_MATCH['Size']]
                ));

             $variation_id = $variation->save();
             $this->count_products++;
             echo "<br><br>Product <strong>".$data[$this->$CSV_DATA_MATCH['Item Name']]." (".$data[(integer) $this->$CSV_DATA_MATCH['Item Number']].")</strong> added<br>";
            //set image (add it to parent variable product gallery too)
            $this->add_image($variation_id, $data[(integer) $this->$CSV_DATA_MATCH['Item Number']], $id);

        }

    }



    private function add_image($postId, $product_code, $add_to_parent=false){
        $product_code = trim($product_code);
        $DirPath = ABSPATH."product-img";
        $IMG_ARR = glob ($DirPath."/".$product_code.'*.[jJ][pP][gG]');
        $IMGFilePath = $IMG_ARR[0];
         if($IMGFilePath){
            //get product description from filename and put it to post excerpt
            $IMGFileName = str_replace($DirPath."/","",$IMGFilePath);
            $productDescr = str_replace($product_code,"",$IMGFileName);
            $productDescr = str_ireplace(".jpg","",$productDescr);
            $productDescr = trim($productDescr);
            wp_update_post( array('ID' => $postId, 'post_excerpt' => $productDescr ) );

            //prepare upload image to WordPress Media Library
            $upload = wp_upload_bits($IMGFileName , null, file_get_contents($IMGFilePath, FILE_USE_INCLUDE_PATH));
            // check and return file type
            $imageFile = $upload['file'];
            $wpFileType = wp_check_filetype($imageFile, null);
            // Attachment attributes for file
            $attachment = array(
                'post_mime_type' => $wpFileType['type'],  // file type
                'post_title' => $imageFile,  // sanitize and use image name as file name
                'post_content' => '',  // could use the image description here as the content
                'post_status' => 'inherit'
                );
            // insert and return attachment id
            $attachmentId = wp_insert_attachment( $attachment, $imageFile, $postId );

            // insert and return attachment metadata
            $attachmentData = wp_generate_attachment_metadata( $attachmentId, $imageFile);
            
            // update and return attachment metadata
            wp_update_attachment_metadata( $attachmentId, $attachmentData );
            
            // finally, associate attachment id to post id
            $success = set_post_thumbnail( $postId, $attachmentId );
           
            // was featured image associated with post?
            if($success){

                $this->count_images++;
                echo  'Image set<br><br>';
                
                if($add_to_parent){
                    $gall_arr = [];
                    //add to parent product featured image. 
                    if(has_post_thumbnail( $add_to_parent )){

                        $product = new WC_Product_Variable( $add_to_parent);

                        $gall_arr =  $product->get_gallery_attachment_ids();
                        array_push($gall_arr,$attachmentId);
                        $product->set_gallery_image_ids($gall_arr);
                        $product->save();
                    }
                    else{
                        set_post_thumbnail( $add_to_parent, $attachmentId );
                    }
                }

                return true;
            
            } else {

                echo  'Image <strong>NOT</strong> set<br><br>';
                return false;
            
            }
            
        }
    }

}


?>