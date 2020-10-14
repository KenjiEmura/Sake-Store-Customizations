<?php


// -------------------------------------------------------------------------------------------------------------------
// ----------------------------- Checks the stock of the cart items vs the qty the user wants to add
// -------------------------------------------------------------------------------------------------------------------
// add_filter( 'woocommerce_add_to_cart', 'add_to_cart_stock_filter', 10, 3 );
add_filter( 'woocommerce_add_to_cart_validation', 'add_to_cart_stock_filter', 10, 3 );
function add_to_cart_stock_filter( $passed, $add_product_id, $add_product_qty ) {

    // ----------To make this function work with the wc-ajax=add_to_cart, you have to disable all the echo comments (no idea what causes that behaviour)
    global $ninja_sets;

    $added_product = wc_get_product( $add_product_id );

    // echo "<script>console.log( 'add_product_id: " . json_encode( $add_product_id ) . "' );</script>";
    // echo "<script>console.log( 'added_product: " . json_encode( $added_product->get_title() ) . "' );</script>";
    
    // This is the array that is going to be used to store the information from the cart
    $cart_items_array = array();
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $product = $cart_item['data'];
        array_push( $cart_items_array, array( 
            "name" => $product->get_title(),
            "id" => $product->get_id(),
            "key" => $cart_item_key,
            "sku"  => $product->get_sku(),
            "cart_qty" => $cart_item['quantity'],
            "stock_qty" => $product->get_stock_quantity()
            ) );
    }
    
    // echo "<script>console.log( 'cart_items_array: " . json_encode( $cart_items_array ) . "' );</script>";
        
    // Traversing and filling the created cart-array with the current cart information
    foreach ( $cart_items_array as $cart_item ) {
        foreach ( $ninja_sets as $the_set ) {
            if ( $cart_item["sku"] == $the_set["sku"] ) {
                foreach ( $the_set["items"] as $bundle_item ) {
                    if ( in_array( $bundle_item["sku"], array_column( $cart_items_array, "sku") ) ) {
                        
                        // Searches for the index of the cart-array that matches the bundle_item["sku"]
                        // uses that index to access that same cart-item's qty to increment it by the bundle_qty multiplied by the amount of sets that were added to the cart
                        $index_individual_cart_item = array_search( $bundle_item["sku"], array_column( $cart_items_array, "sku") );
                        $index_bundle_cart_item = array_search( $the_set["sku"], array_column( $cart_items_array, "sku") );
                        
                        $individual_item_qty = $cart_items_array[ $index_individual_cart_item ]["cart_qty"];
                        $bundle_qty = $bundle_item["qty"] * $cart_items_array[ $index_bundle_cart_item ]["cart_qty"];
                        
                        $total_individual_item_qty = $individual_item_qty + $bundle_qty;

                        // The total cart-quantity of the product is updated
                        $cart_items_array[ $index_individual_cart_item ]["cart_qty"] = $total_individual_item_qty;

                        // Checking if the added product is already on the cart
                        if ( $added_product->get_sku() == $cart_item["sku"] ) {
                            //Checks if the added product qty * bundle qty + current cart qty is lower than the stock qty
                            if ( ( $bundle_item["qty"] * $add_product_qty ) + $cart_items_array[ $index_individual_cart_item ]["cart_qty"] > $cart_items_array[ $index_individual_cart_item ]["stock_qty"] ) {
                                // echo "<script>console.log( 'We got inside the conditional for thisproduct: " . $cart_item["name"] . "' );</script>";
                                $message = "<strong>「" . $added_product->get_title() . "」</strong>を" . $add_product_qty . "つ追加できません。<strong>「" . $bundle_item["name"] . "」</strong>を使ってる商品がカートに入れましたので、加算する在庫が足りません。";
                                wc_add_notice( $message, 'error' );
                                $passed = FALSE;
                                return $passed;
                            }
                        } 
                    }
                }
            }
        }
        
        // echo "<script>console.log( 'The foreach loop is over, cart_items_array has this info: " . json_encode( $cart_items_array ) . "' );</script>";
        
        if ( $added_product->get_sku() == $cart_item["sku"] ) {
            
            $product_index = array_search( $cart_item["sku"], array_column( $cart_items_array, "sku") );
            // echo "<script>console.log( 'FIRST conditional for this product: " . $cart_item["name"] . $cart_item["sku"] . "' );</script>";
            // echo "<script>console.log( 'cart_item[cart_qty]: " . $cart_item["cart_qty"] . "' );</script>";
            // echo "<script>console.log( 'cart_item[stock_qty]: " . $cart_items_array[ $product_index ]["stock_qty"] . "' );</script>";
            
            if ( $cart_items_array[$product_index]["cart_qty"] + $add_product_qty > $cart_items_array[$product_index]["stock_qty"] ) {
                // echo "<script>console.log( 'SECOND conditional for thisproduct: " . $cart_item["name"] . "' );</script>";
                $message = "<strong>「" . $added_product->get_title() . "」</strong>を" . $add_product_qty . "つ追加できません。<strong>「" . $cart_item["name"] . "」</strong>を使ってる商品がカートに入れましたので、加算する在庫が足りません。";
                wc_add_notice( $message, 'error' );
                $passed = FALSE;
                return $passed;
            }
        }
    }   
    return $passed;
}






// -------------------------------------------------------------------------------------------------------------------
// --------- Handles the stock of the Ninja Sets when an order is received or the status of the order changes
// -------------------------------------------------------------------------------------------------------------------

global $are_we_restocking;

function ninja_set_stock_management( $order_id ) {
    
    // Call the global variable that contain the sets information
    global $ninja_sets;
    global $are_we_restocking;
    
    // Get the current order's instance
    $order = wc_get_order( $order_id );
    
    foreach ( $order->get_items() as $order_item ) {
        
        // Get the product's instance (product of the order)
        $product = $order_item->get_product();
        
        // Traverse the provided array that contains the information about the sets
        foreach ( $ninja_sets as $set ) {
            
            // If the SKU of the order is equal to the SKU of any of the defined sets
            if ( $product->get_sku() == $set["sku"] ) {
                
                // Get the ordered quantity of SETS, not individual items (i.e. 2 Nyan sets will contain 20 umeshu, 20 shidaizumi, etc.)
                $quantity = $order_item->get_quantity();
                
                
                foreach ( $set["items"] as $bundle_item ) {
                    $get_product = wc_get_product( wc_get_product_id_by_sku( $bundle_item["sku"] ) );
                    if ( $are_we_restocking ) {
                        $get_product->set_stock_quantity( $get_product->get_stock_quantity() + ( $bundle_item["qty"] * $quantity) );
                    } else {
                        $get_product->set_stock_quantity( $get_product->get_stock_quantity() - ( $bundle_item["qty"] * $quantity) );
                    }
                    $get_product->save();
                }
            }
        }
    }
}
function adding_stock_boolean_to_false() {
    global $are_we_restocking;
    $are_we_restocking = FALSE;
}
function adding_stock_boolean_to_true() {
    global $are_we_restocking;
    $are_we_restocking = TRUE;
}

// These are the triggers that will fire the stock handling function

// Cancelled order
add_action( 'woocommerce_cancelled_order', 'ninja_set_stock_management', 10, 1 );
add_action( 'woocommerce_order_status_pending_to_cancelled_notification', 'ninja_set_stock_management', 10, 1 );
add_action( 'woocommerce_order_status_processing_to_cancelled_notification', 'ninja_set_stock_management', 10, 1 );
add_action( 'woocommerce_order_status_on-hold_to_cancelled_notification', 'ninja_set_stock_management', 10, 1 );

add_action( 'woocommerce_cancelled_order', 'adding_stock_boolean_to_true', 1, 1 );
add_action( 'woocommerce_order_status_pending_to_cancelled_notification', 'adding_stock_boolean_to_true', 1, 1 );
add_action( 'woocommerce_order_status_processing_to_cancelled_notification', 'adding_stock_boolean_to_true', 1, 1 );
add_action( 'woocommerce_order_status_on-hold_to_cancelled_notification', 'adding_stock_boolean_to_true', 1, 1 );

// Order received, status changed from cancelled to processing, pending or on-hold
add_action( 'woocommerce_checkout_order_processed', 'ninja_set_stock_management', 10, 1 );
add_action( 'woocommerce_order_status_cancelled_to_processing_notification', 'ninja_set_stock_management', 10, 1 );
add_action( 'woocommerce_order_status_cancelled_to_pending_notification', 'ninja_set_stock_management', 10, 1 );
add_action( 'woocommerce_order_status_cancelled_to_on-hold_notification', 'ninja_set_stock_management', 10, 1 );

add_action( 'woocommerce_checkout_order_processed', 'adding_stock_boolean_to_false', 1, 1 );
add_action( 'woocommerce_order_status_cancelled_to_processing_notification', 'adding_stock_boolean_to_false', 1, 1 );
add_action( 'woocommerce_order_status_cancelled_to_pending_notification', 'adding_stock_boolean_to_false', 1, 1 );
add_action( 'woocommerce_order_status_cancelled_to_on-hold_notification', 'adding_stock_boolean_to_false', 1, 1 );






// -------------------------------------------------------------------------------------------------------------------
// ----------------------------- Updates the stock of the Ninja Sets based on a cron job
// -------------------------------------------------------------------------------------------------------------------
// Add a new time intervals for cron jobs
add_filter( 'cron_schedules', 'ninja_update_interval' );
function ninja_update_interval( $schedules ) {
    $schedules['update_interval'] = array(
        'interval'  => 20000,
        'display'   => __( 'Every xxx seconds', 'textdomain' )
    );
    return $schedules;
}
// Hook the stock update function to the scheduled action
add_action( 'ninja_update_interval', 'update_ninja_sets' );
function update_ninja_sets() {
    
    // Call the global variable that contain the sets information
    global $ninja_sets;

    foreach ( $ninja_sets as $the_set ) {

        $product_bundle = wc_get_product( wc_get_product_id_by_sku( $the_set["sku"] ) );

        $stock_array = array();

        foreach ( $the_set["items"] as $bundle_item ) {
            $obtained_product = wc_get_product( wc_get_product_id_by_sku( $bundle_item["sku"] ) );
            array_push( $stock_array , $obtained_product->get_stock_quantity() / $bundle_item["qty"] );
        }

        $stock = min( $stock_array );
        $product_bundle->set_stock_quantity( $stock );
        $product_bundle->save();
    }

}





// -------------------------------------------------------------------------------------------------------------------
// ----------------------------- Updates the stock of the different sets when the product is accessed
// -------------------------------------------------------------------------------------------------------------------
// add_action( 'woocommerce_before_single_product', 'ninja_sets_stock' );
function ninja_sets_stock() {

    // Call the global variable that contain the sets information
    global $ninja_sets;

    global $product;

    $sku = $product->get_sku();

    foreach ( $ninja_sets as $the_set ) {

        if ( $the_set["sku"] == $sku ) {

            $product_bundle = wc_get_product( wc_get_product_id_by_sku( $the_set["sku"] ) );

            $stock_array = array();

            foreach ( $the_set["items"] as $bundle_item ) {
                $obtained_product = wc_get_product( wc_get_product_id_by_sku( $bundle_item["sku"] ) );
                array_push( $stock_array , $obtained_product->get_stock_quantity() / $bundle_item["qty"] );
            }

            $stock = min( $stock_array );
            $product_bundle->set_stock_quantity( $stock );
            $product_bundle->save();

        }
    }
}