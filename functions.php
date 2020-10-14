<?php

// This are the files that control the stock of the Ninja Sets
require_once( __DIR__ . '/Custom_Code/ninja_sets_functions.php');
require_once( __DIR__ . '/Custom_Code/ninja_sets_data.php');


// Add here what it's supposed to be executed throughout the website
add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );
function my_theme_enqueue_styles() {
    // Schedule the update cron job if it's not already shceduled
    if ( ! wp_next_scheduled( 'ninja_update_interval' ) ) {
        wp_schedule_event( time(), 'update_interval', 'ninja_update_interval' );
    }
    $parent_style = 'parent-style';
 
    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_style ),
        wp_get_theme()->get('Version')
    );
}



// -------------------------------------------- Remove the WooCommerce tab for "Stock Manager" type users
add_action( 'admin_menu', 'remove_woocommerce_tab_from_dashboard' );
function remove_woocommerce_tab_from_dashboard() {
    if ( ! current_user_can( 'publish_products' ) ) {
        remove_menu_page( 'woocommerce' );
    }
}


// -------------------------------------------- Remove the "Trash" and "Duplicate" options for "Stock Manager" type users
add_action( 'admin_footer', 'remove_trash' );
function remove_trash() {
    if ( ! current_user_can( 'publish_products' ) ) {
        echo ('<style>
        div.row-actions span.trash,
        div.row-actions span.duplicate,
        div.row-actions span.edit,
        div.row-actions span.view,
        div#wpfooter,
        #wp-admin-bar-root-default,
        li#wp-admin-bar-new-content,
        #adminmenuback,
        #adminmenuwrap,
        li#wp-admin-bar-archive,
        a.page-title-action[href="https://ninjasake.store/wp-admin/post-new.php?post_type=product"],
        #adminmenu ul.wp-submenu.wp-submenu-wrap,
        table.form-table.woocommerce-exporter-options > tbody > tr:nth-child(n):not(:first-child)
        {
            display: none;
        }
        /* .inline-edit-row.inline-edit-row-post.quick-edit-row.quick-edit-row-post.inline-edit-product.inline-editor input {
             pointer-events:none;
        }*/
        </style>');
        echo ( '<script> $( "input" )..attr("disabled", "disabled"); </script>' );
    }
}




// -------------------------------------------- RESTRICTION FOR COUPONS
add_filter( 'woocommerce_coupon_is_valid', function( $is_valid, $coupon ) {

    global $woocommerce;

    // MY FIRST SAKE COUPON
    if ( in_array( strtolower($coupon->get_code()) , ['myfirstsake', 'INGRESE ACÁ EL NOMREB DEL CUPÓN']) )
    {
        if ( ! is_user_logged_in() )
        {
            throw new Exception( __('<strong><a style="color: white;" href="/ログイン">ログイン</a>・<a  style="color: white;" href="/登録">登録</a></strong>いただくことで、'. '<strong>「' . $coupon->get_code() . '」</strong>' . 'がご使用になれます。', 'woocommerce' ), 100 );
            return false;
        } elseif ( $woocommerce->cart->cart_contents_count < 6 )
        {
            throw new Exception( __( '<strong>「' . $coupon->get_code() . '」</strong>' . 'を使う前に、カートにカップ酒を6本以上入れてください。', 'woocommerce' ), 100 );
            return false;
        } else
        {
            return $is_valid;
        }
    }
    
    // 30 CUPS RESTRICTION COUPON 業務用
    if ( in_array( strtolower($coupon->get_code()) , ['supersake', 'INGRESE ACÁ EL NOMREB DEL CUPÓN']) )
    {
        // if ( ! is_user_logged_in() )
        // {
        //     throw new Exception( __('<strong><a style="color: white;" href="/ログイン">ログイン</a>・<a  style="color: white;" href="/登録">登録</a></strong>いただくことで、'. '<strong>「' . $coupon->get_code() . '」</strong>' . 'がご使用になれます。', 'woocommerce' ), 100 );
        //     return false;
        // } else
        if ( $woocommerce->cart->cart_contents_count < 30 )
        {
            throw new Exception( __( '<strong>「' . $coupon->get_code() . '」</strong>は送料無料のクーポンです。<br><strong>「' . $coupon->get_code() . '」</strong>を使う前に、カートにカップ酒を30本以上入れてください。', 'woocommerce' ), 100 );
            return false;
        } else
        {
            return $is_valid;
        }
    }

    return $is_valid;

}, 10, 2 );




// -------------------------------------------- Loads the custom JavaScript file into the Checkout Page
function add_jscript_checkout() {
echo '<script src="https://tests.ninjasake.store/wp-content/themes/Divi-Child/Custom_Code/checkout.js"></script>';
}
add_action( 'woocommerce_before_checkout_form', 'add_jscript_checkout', 10, 1);


// -------------------------------------------- Adds SKUs and product images to WooCommerce order emails
function sww_modify_wc_order_emails( $args ) {
// bail if this is sent to the admin
//    if ( $args['sent_to_admin'] ) {
//        return $args; 
//    }
  $args['show_sku'] = true;
  $args['show_image'] = true;
  $args['image_size'] = array( 34, 48 );
  return $args;
}
add_filter( 'woocommerce_email_order_items_args', 'sww_modify_wc_order_emails' );




// -------------------------------------------- Add "Add to Cart" buttons in Divi shop pages
add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 20 );




// -------------------------------------------- Remove the result count from WooCommerce
remove_action( 'woocommerce_before_shop_loop' , 'woocommerce_result_count', 20 );



// -------------------------------------------- Database cleaner (Deletes empty products from the Database)
// // add_action( 'wp_head', 'clean_stock' ); 
// function clean_stock() {
//     global $wpdb;
//     $exist = $no_exist = $deleted = 0;
//     $product_id_min = 0;
//     $product_id_max = 6000;
//     $start = microtime(true);
//     for ($i = $product_id_min; $i <= $product_id_max; $i++) {
//         $table_name = "wp_wc_product_meta_lookup";   
//         $prepared_query = $wpdb->prepare ( "SELECT `sku` FROM `$table_name` WHERE `wp_wc_product_meta_lookup`.`product_id` = $i" );
//         $select_result = $wpdb->get_results ( $prepared_query );
//         echo "<script>console.log('El product_id #" . $i . " devuelve un valor de tipo: " . gettype($select_result[0]->sku) . " ' );</script>";
//         if ( is_null( $select_result[0]->sku ) ) {
//             echo "<script>console.log('The product #" . $i . " doesn't exist, the cycle continues.' );</script>";
//             $no_exist++;
//             continue;
//         } elseif ( $select_result[0]->sku != "" ) {
//             echo "<script>console.log('Product #" . $i . " has the following SKU: " . $select_result[0]->sku . " ' );</script>";
//             $exist++;
//             continue;
//         } else {
//             $wpdb->delete( $table_name, array( 'product_id' => $i ) );
//             $deleted++;
//             echo "<script>console.log('The product #" . $i . " has been successfully deleted.' );</script>";
//         }
//     } 
//     echo "<script>console.log('The execution time was: " . ( microtime(true) - $start )  . "  seconds' );</script>";
//     echo "<script>console.log('Deleted entries: " . $deleted  . "' );</script>";
//     echo "<script>console.log('Entries with data (existing products): " . $exist  . "' );</script>";
//     echo "<script>console.log('Skipped products (don't exist): " . $no_exist  . "' );</script>";
// }