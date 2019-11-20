<?php

/**
 Plugin Name: WooCommerce Deals of the Day
 Description: use shotcode like this [products limit="10" columns="4" category="deals" cat_operator="AND"]
 Version: 1.0

 @package deals
 */

// add settings
function deals_register_settings()
{
    add_option('deals_option_qty', 'should to be more one');
    register_setting('deals_options_group', 'deals_option_qty', 'deals_callback');
    add_option('deals_option_discount', '%');
    register_setting('deals_options_group', 'deals_option_discount', 'deals_callback');
}
add_action('admin_init', 'deals_register_settings');

function deals_register_options_page()
{
    add_options_page('Deals of the Day', 'Deals of the Day', 'manage_options', 'deals', 'deals_options_page');
}
add_action('admin_menu', 'deals_register_options_page');

function deals_options_page()
{
    ?>
<div>
  <?php screen_icon(); ?>
  <h2>Deals of the Day plugin settings</h2>
	<form method="post" action="options.php">
  <?php settings_fields( 'deals_options_group' ); ?>
  <p>Quantity products to deals.</p>
		<table>
			<tr valign="top">
				<th scope="row"><label for="deals_option_qty">Quantity</label></th>
				<td><input type="text" id="deals_option_qty" name="deals_option_qty"
					value="<?php echo get_option('deals_option_qty'); ?>" /></td>
			</tr>
		</table>
		<p>Discount at percentage %.</p>
		<table>
			<tr valign="top">
				<th scope="row"><label for="deals_option_discount">Discount</label></th>
				<td><input type="text" id="deals_option_discount"
					name="deals_option_discount"
					value="<?php echo get_option('deals_option_discount'); ?>" /></td>
			</tr>
		</table>
  <?php  submit_button(); ?>
  </form>
</div>
<?php
}

// creation of products
add_filter('cron_schedules', 'deals_add_daily_cron_schedule');

function deals_add_daily_cron_schedule($schedules)
{
    $schedules['daily'] = array(
        'interval' => 60,
        'display' => __('Once Daily')
    );
    return $schedules;
}
if (! wp_next_scheduled('deals_my_cron_action')) {
    wp_schedule_event(time(), 'daily', 'deals_my_cron_action');
}
add_action('deals_my_cron_action', 'deals_function_to_run');

function deals_function_to_run()
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $table = $prefix . 'posts';
    $table2 = $prefix . 'postmeta';

    $qty = get_option('deals_option_qty');
    $discount = get_option('deals_option_discount');
    $term_id = [
        'Deals'
    ];

    // delete previous products
    $old_ids = get_option('saved_id_deals');
    foreach ($old_ids as $s) {
        $id = $s;
        $sql = "DELETE FROM $table2 WHERE `post_id` = $id";
        $wpdb->get_results($sql);
        $sql = "DELETE FROM $table WHERE `ID` = $id";
        $wpdb->get_results($sql);
    }

    // get products ID
    $sql = "SELECT `ID` FROM $table WHERE `post_type` = 'product'";
    $Id = $wpdb->get_results($sql);
    // exculde variable products
    foreach ($Id as $k) {
        $id = $k->ID;
        $sql = "SELECT `ID` FROM $table WHERE `post_parent` = $id AND `post_type` = 'product_variation'";
        if (empty($wpdb->get_results($sql))) {
            $Ids[] = $k->ID;
        }
    }

    if ($qty > 1) {
        // get random IDs
        $rand_keys = array_rand($Ids, $qty);

        $wc_adp = new WC_Admin_Duplicate_Product();

        foreach ($rand_keys as $k) {

            $ID = $Ids[$k];
            // get product
            $product = wc_get_product($ID);
            if (false === $product) {
                /* translators: %s: product id */
                wp_die(sprintf(__('Product creation failed, could not find original product: %s', 'woocommerce'), $product_id));
            }

            // get current price
            $regular_price = get_post_meta($ID, '_regular_price');
            $regular_price = $regular_price[0];
            $sale_price = get_post_meta($ID, '_sale_price');
            $sale_price = $sale_price[0];
            if (! empty($sale_price)) {
                $current_price = $sale_price;
            } else {
                $current_price = $regular_price;
            }

            // get sku
            $sku = get_post_meta($ID, '_sku');
            $sku = $sku[0];
            $new_sku = $sku . '_deals';

            // make duplicated product
            $duplicate = $wc_adp->product_duplicate($product);

            // get new product ID
            $new_id = $duplicate->get_id();

            // add product in category Deals
            wp_set_object_terms($new_id, $term_id, 'product_cat');

            // update data of product
            $new_price = round($current_price - (($current_price * $discount) / 100), 2);
            update_post_meta($new_id, '_sku', $new_sku);
            update_post_meta($new_id, '_sale_price', $new_price);
            update_post_meta($new_id, '_price', $new_price);

            $sql = "SELECT `post_title` FROM $table WHERE `ID` = $ID";
            $post_title = $wpdb->get_results($sql)[0]->post_title;
            $my_post = array();
            $my_post['ID'] = $new_id;
            $my_post['post_title'] = $post_title . '(Discount - ' . $discount . '%)';
            $my_post['post_status'] = 'publish';
            $my_post['post_date'] = '2001-11-02 18:42:14';
            $my_post['post_date_gmt'] = '2001-11-02 18:42:14';
            wp_update_post($my_post);

            // save IDs in array
            $data[] = $new_id;
        }
    }
    // save IDs in DB
    update_option('saved_id_deals', $data);
    // delete AUTO-DRAFT
    $sql = "DELETE FROM $table WHERE `post_title` like 'AUTO-DRAFT%'";
    $wpdb->get_results($sql);
 }
