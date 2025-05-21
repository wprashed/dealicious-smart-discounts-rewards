<?php
/*
Plugin Name: Dealicious – Smart Discounts & Rewards for WooCommerce
Description: WooCommerce plugin with first-time discounts, spin-to-win, birthday emails, cart-based deals, and review points.
Version: 1.0
Author: Rashed Hossain
*/

if (!defined('ABSPATH')) exit;

class DealiciousPlugin {

    public function __construct() {
        add_action('init', [$this, 'register_coupon']);
        add_action('woocommerce_checkout_order_processed', [$this, 'apply_first_time_discount'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'track_purchase'], 10, 1);
        add_shortcode('dealicious_spin_wheel', [$this, 'render_spin_wheel']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('comment_post', [$this, 'reward_points_for_review'], 10, 3);
        add_action('init', [$this, 'check_birthday_and_send_email']);
    }

    public function enqueue_assets() {
        wp_enqueue_script('dealicious-spin', plugin_dir_url(__FILE__) . 'assets/js/spin.js', ['jquery'], null, true);
        wp_enqueue_style('dealicious-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    }

    public function register_coupon() {
        if (!get_page_by_title('FIRSTTIME10', OBJECT, 'shop_coupon')) {
            $coupon = array(
                'post_title' => 'FIRSTTIME10',
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
                'post_type' => 'shop_coupon',
            );
            $new_coupon_id = wp_insert_post($coupon);
            update_post_meta($new_coupon_id, 'discount_type', 'percent');
            update_post_meta($new_coupon_id, 'coupon_amount', '10');
            update_post_meta($new_coupon_id, 'individual_use', 'no');
            update_post_meta($new_coupon_id, 'usage_limit', '1');
        }
    }

    public function apply_first_time_discount($order_id) {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $orders = wc_get_orders(['customer_id' => $user_id]);
            if (count($orders) === 1) {
                $order = wc_get_order($order_id);
                $order->apply_coupon('FIRSTTIME10');
            }
        }
    }

    public function track_purchase($order_id) {
        // Optional future tracking
    }

    public function render_spin_wheel() {
        return '
        <div id="spin-wrapper">
            <button id="spinBtn">🎁 Spin the Wheel</button>
            <div id="spin-result"></div>
        </div>';
    }

    public function reward_points_for_review($comment_id, $approved, $commentdata) {
        if ($approved && isset($commentdata['comment_post_ID'])) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $points = (int) get_user_meta($user_id, '_dealicious_points', true);
                update_user_meta($user_id, '_dealicious_points', $points + 10);
            }
        }
    }

    public function check_birthday_and_send_email() {
        $users = get_users();
        foreach ($users as $user) {
            $bday = get_user_meta($user->ID, 'birthday', true);
            if ($bday) {
                $today = date('m-d');
                if (date('m-d', strtotime($bday)) == $today) {
                    wp_mail($user->user_email, '🎉 Happy Birthday! Here’s 15% OFF!', 'Use code BDAY15 today only.');
                }
            }
        }
    }
}

new DealiciousPlugin();
?>
