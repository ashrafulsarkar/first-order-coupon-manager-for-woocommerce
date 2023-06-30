<?php
/**
 * Plugin Name: First Order Coupon Manager for WooCommerce
 * Plugin URI: https://github.com/ashrafulsarkar/first-order-coupon-manager-woocommerce
 * Description: Maintain the first-order discount using this plugin.
 * Version: 1.1.0
 * Author: Ashraful Sarkar
 * Author URI: https://github.com/ashrafulsarkar
 * Text Domain: wfocd
 * Domain Path: /languages/
 * Requires at least: 4.6
 * Requires PHP: 7.0
 * License:      GNU General Public License v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class FirstOrderCouponManager
{

    public function __construct(){}

    /**
     * init()
     */
    public function init()
    {
        add_action('plugin_loaded', array($this, 'wfocd_textdomain_load'));
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_coupon_options_usage_restriction', array($this, 'wfocd_action_woocommerce_coupon_options_usage_restriction'), 10, 2);
            add_action('woocommerce_coupon_options_save', array($this, 'wfocd_action_woocommerce_coupon_options_save'), 10, 2);
            add_filter('woocommerce_coupon_is_valid', array($this, 'wfocd_filter_woocommerce_coupon_is_valid'), 10, 3);
        } else {
            add_action('admin_notices', array($this, 'admin_notices'));
        }
    }

    /**
     * Text Domain Load
     */
    public function wfocd_textdomain_load()
    {
        load_plugin_textdomain('wfocd', false, plugin_dir_path(__FILE__) . '/languages');
    }

    /**
     * Admin notice
     */
    public function admin_notices()
    {
?>
        <div class="error">
            <p><?php echo wp_kses(
                    sprintf(
                        __('<strong>%s</strong> addon requires %s to be <strong>installed</strong> and <strong>activated</strong>.', 'wfocd'),
                        __('First Order Coupon Manager for WooCommerce', 'wfocd'),
                        sprintf('<a href="%s" target="_blank"><strong>%s</strong></a>', esc_attr('https://wordpress.org/plugins/woocommerce'), __('WooCommerce', 'wfocd')),
                    ),
                    array(
                        'a'      => array(
                            'href'  => array(),
                            'target' => array('_blank')
                        ),
                        'strong' => array()
                    )
                ); ?>
            </p>
        </div>
<?php
    }

    // Add new field - usage restriction tab
    public function wfocd_action_woocommerce_coupon_options_usage_restriction($coupon_get_id, $coupon)
    {
        woocommerce_wp_checkbox(array(
            'id' => 'wfocd_first_order',
            'label' => __('First order only', 'wfocd'),
            'description' => __('Check this box if the coupon cannot be used after first order.', 'wfocd'),
        ));
    }


    // Save data
    public function wfocd_action_woocommerce_coupon_options_save($post_id, $coupon)
    {

        $coupon->update_meta_data('wfocd_first_order', isset($_POST['wfocd_first_order']) ? 'yes' : 'no');
        $coupon->save();
    }

    // Valid
    public function wfocd_filter_woocommerce_coupon_is_valid($is_valid, $coupon, $discount)
    {
        // Get meta
        $wfocd_first_order = $coupon->get_meta('wfocd_first_order');

        // NOT empty
        if ($wfocd_first_order == 'yes') {
            global $woocommerce;

            if (is_user_logged_in()) {
                $user_id = get_current_user_id();

                // retrieve all orders
                $customer_orders = wc_get_orders(array(
                    'customer_id'  => $user_id,
                    'limit'      => -1
                ));
                if (count($customer_orders) > 0) {
                    $has_ordered = false;

                    $statuses = array('failed', 'cancelled', 'refunded');

                    // loop thru orders, if the order is not falled into failed, cancelled or refund then it consider valid
                    foreach ($customer_orders as $tmp_order) {
                        $order = wc_get_order($tmp_order->get_id());
                        if (!in_array($order->get_status(), $statuses)) {
                            $has_ordered = true;
                        }
                    }
                    // if this customer already ordered, we remove the coupon
                    if ($has_ordered == true) {
                        wc_add_notice(__("This Coupon is only applicable for first order.", 'wfocd'), 'error');
                        $is_valid = false;
                    }
                } else {
                    // customer has no order, so valid to use this coupon
                    $is_valid = true;
                }
            } else {
                // new user is valid
                $is_valid = true;
            }
        }
        return $is_valid;
    }
}

/**
 * wfocd_init Function
 */
function wfocd_init()
{
    $wfocd_init = new FirstOrderCouponManager();
    $wfocd_init->init();
}
add_action('init', 'wfocd_init');
