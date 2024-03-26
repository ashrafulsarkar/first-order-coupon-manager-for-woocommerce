<?php
/**
 * Plugin Name: First Order Coupon Manager for WooCommerce
 * Plugin URI: https://github.com/ashrafulsarkar/first-order-coupon-manager-woocommerce
 * Description: Maintain the first-order discount using this plugin.
 * Requires Plugins: woocommerce
 * Version: 1.2.2
 * Author: Ashraful Sarkar
 * Author URI: https://github.com/ashrafulsarkar
 * Text Domain: wfocd
 * Domain Path: /languages
 * Requires at least: 4.6
 * Requires PHP: 7.0
 * License:      GNU General Public License v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class FirstOrderCouponManager {

    public function __construct(){}

    /**
     * init()
     */
    public function init() {
        add_action( 'plugin_loaded', [ $this, 'wfocd_textdomain_load' ] );
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_coupon_options_usage_restriction', [$this, 'wfocd_action_woocommerce_coupon_options_usage_restriction'], 10, 2);
            add_action('woocommerce_coupon_options_save', [$this, 'wfocd_action_woocommerce_coupon_options_save'], 10, 2);
            add_filter('woocommerce_coupon_is_valid', [$this, 'wfocd_filter_woocommerce_coupon_is_valid'], 10, 3);
            add_action('woocommerce_checkout_update_order_review', [$this, 'wfocd_woocommerce_checkout_update_order_review']);
        } else {
            add_action('admin_notices', [$this, 'admin_notices']);
        }
    }

    /**
     * Text Domain Load
     */
    public function wfocd_textdomain_load() {
        load_plugin_textdomain('wfocd', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Admin notice
     */
    public function admin_notices() {
?>
        <div class="error">
            <p><?php echo wp_kses(
                    sprintf(
                        __('<strong>%s</strong> addon requires %s to be <strong>installed</strong> and <strong>activated</strong>.', 'wfocd'),
                        __('First Order Coupon Manager for WooCommerce', 'wfocd'),
                        sprintf('<a href="%s" target="_blank"><strong>%s</strong></a>', esc_attr('https://wordpress.org/plugins/woocommerce'), __('WooCommerce', 'wfocd')),
                    ),
                    [
                        'a' => [
                            'href' => [],
                            'target' => ['_blank']
                        ],
                        'strong' => []
                    ]
                ); ?>
            </p>
        </div>
<?php
    }

    // Add new field - usage restriction tab
    public function wfocd_action_woocommerce_coupon_options_usage_restriction($coupon_get_id, $coupon) {
        woocommerce_wp_checkbox(array(
            'id' => 'wfocd_first_order',
            'label' => __('First order only', 'wfocd'),
            'description' => __('Check this box if the coupon cannot be used after first order.', 'wfocd'),
        ));
    }


    // Save data
    public function wfocd_action_woocommerce_coupon_options_save($post_id, $coupon) {

        $coupon->update_meta_data('wfocd_first_order', isset($_POST['wfocd_first_order']) ? 'yes' : 'no');
        $coupon->save();
    }

    // Update order review
    public function wfocd_woocommerce_checkout_update_order_review($posted_data) {
        global $woocommerce;
        
        $post = [];
        $vars = explode('&', $posted_data);
        foreach ($vars as $k => $value){
            $v = explode('=', urldecode($value));
            $post[$v[0]] = $v[1];
        }
        
        $update = [];
        if( isset($post['billing_first_name']) && !empty($post['billing_first_name']) ){
            $update['billing_first_name'] = wc_clean( wp_unslash($post['billing_first_name']) );
        }
        if( isset($post['billing_last_name']) && !empty($post['billing_last_name']) ){
            $update['billing_last_name'] = wc_clean( wp_unslash($post['billing_last_name']) );
        }
        if( isset($post['billing_email']) && !empty($post['billing_email']) ){
            $update['billing_email'] = wc_clean( wp_unslash($post['billing_email']) );
        }

        if( !empty($update) ){
            WC()->customer->set_props($update);
            WC()->customer->save();
        }
    }

    // Valid
    public function wfocd_filter_woocommerce_coupon_is_valid($is_valid, $coupon, $discount) {
        // Get meta
        $wfocd_first_order = $coupon->get_meta('wfocd_first_order');

        // NOT empty
        if ($wfocd_first_order == 'yes') {
            global $woocommerce;

            if (is_user_logged_in()) {
                $user_id = get_current_user_id();

                // retrieve all orders
                $customer_orders = wc_get_orders([
                    'customer_id'  => $user_id,
                    'limit'      => -1
                ]);

            } else {
                $customer = WC()->cart->get_customer();
                $email = $customer->get_billing_email();
                $first_name = $customer->get_billing_first_name();
                $last_name = $customer->get_billing_last_name();
                
                if( empty($email) || empty($first_name) || empty($last_name) ) {
                    wc_add_notice(__('Please login or add this coupon code once checkout form has been filled', 'wfocd'), 'error');
                    return false;
                }
                
                // retrieve all orders by email
                $customer_orders_email = wc_get_orders([
                    'customer' => $email,
                    'limit'      => -1
                ]);

                // retrieve all orders by first & last name
                $customer_orders_name = wc_get_orders([
                    'billing_first_name' => $first_name,
                    'billing_last_name' => $last_name
                ]);
				
				$customer_orders = array_merge($customer_orders_email, $customer_orders_name);
            }

            if (count($customer_orders) > 0) {
                $statuses = array('failed', 'cancelled', 'refunded');

                // loop thru orders, if the order is not falled into failed, cancelled or refund then it consider valid
                foreach ($customer_orders as $tmp_order) {
                    $order = wc_get_order($tmp_order->get_id());
                    if (!in_array($order->get_status(), $statuses)) {
                        // if this customer already ordered, we remove the coupon
                        wc_add_notice(__("This Coupon is only applicable for first order.", 'wfocd'), 'error');

                        return false;
                    }
                }
                
            }
        }
        // customer has no order, so valid to use this coupon
        return true;
    }
}

/**
 * wfocd_init Function
 */
function wfocd_init() {
    $wfocd_init = new FirstOrderCouponManager();
    $wfocd_init->init();
}
add_action('init', 'wfocd_init');