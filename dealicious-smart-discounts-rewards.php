<?php
/*
Plugin Name: Dealicious – Smart Discounts & Rewards for WooCommerce
Plugin URI: https://github.com/wprashed/dealicious-smart-discounts-rewards/releases/tag/v1.0.0
Description: WooCommerce plugin with first-time discounts, spin-to-win, birthday emails, cart-based deals, and review points.
Version: 1.0
Author: Rashed Hossain
Author URI: https://rashed.im
Text Domain: dealicious-smart-discounts-rewards-for-woocommerce
Requires Plugins: woocommerce
Domain Path: /languages
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

class DealiciousPlugin {

    const FIRST_TIME_COUPON = 'FIRSTTIME10';

    public function __construct() {
        add_action('init', [$this, 'register_coupon']);
        add_action('woocommerce_checkout_order_processed', [$this, 'apply_first_time_discount'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'track_purchase'], 10, 1);
        add_shortcode('dealicious_spin_wheel', [$this, 'render_spin_wheel']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('comment_post', [$this, 'reward_points_for_review'], 10, 3);
        add_action('init', [$this, 'check_birthday_and_send_email']);
    }

    /* ------------------------------
     * Assets
     * ------------------------------ */
    public function enqueue_assets() {
        wp_enqueue_script(
            'dealicious-spin',
            plugin_dir_url(__FILE__) . 'assets/js/spin.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_enqueue_style(
            'dealicious-style',
            plugin_dir_url(__FILE__) . 'assets/css/style.css',
            [],
            '1.0'
        );
    }

    /* ------------------------------
     * Coupon Registration (No deprecated code)
     * ------------------------------ */
    public function register_coupon() {

        $query = new WP_Query([
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'title'          => self::FIRST_TIME_COUPON,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!$query->have_posts()) {

            $coupon_id = wp_insert_post([
                'post_title'   => self::FIRST_TIME_COUPON,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
                'post_type'    => 'shop_coupon',
            ]);

            if (!is_wp_error($coupon_id)) {
                update_post_meta($coupon_id, 'discount_type', 'percent');
                update_post_meta($coupon_id, 'coupon_amount', '10');
                update_post_meta($coupon_id, 'individual_use', 'no');
                update_post_meta($coupon_id, 'usage_limit', '1');
            }
        }

        wp_reset_postdata();
    }

    /* ------------------------------
     * First-Time Purchase Discount
     * ------------------------------ */
    public function apply_first_time_discount($order_id) {

        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => 2,
        ]);

        if (count($orders) === 1) {
            $order = wc_get_order($order_id);

            if ($order && !$order->has_coupon(self::FIRST_TIME_COUPON)) {
                $order->apply_coupon(self::FIRST_TIME_COUPON);
                $order->calculate_totals();
            }
        }
    }

    /* ------------------------------
     * Purchase Tracking (Future Use)
     * ------------------------------ */
    public function track_purchase($order_id) {
        // Reserved for future analytics or reward logic
    }

    /* ------------------------------
     * Spin Wheel Shortcode
     * ------------------------------ */
    public function render_spin_wheel() {
        ob_start();
        ?>
        <div id="dealicious-spin-wrapper">
            <button id="dealicious-spin-btn">
                <?php esc_html_e('Spin the Wheel', 'dealicious-smart-discounts-rewards-for-woocommerce'); ?>
            </button>
            <div id="dealicious-spin-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ------------------------------
     * Reward Points for Reviews
     * ------------------------------ */
    public function reward_points_for_review($comment_id, $approved, $commentdata) {

        if ($approved !== 1) {
            return;
        }

        if (empty($commentdata['comment_post_ID'])) {
            return;
        }

        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        $points = (int) get_user_meta($user_id, '_dealicious_points', true);
        update_user_meta($user_id, '_dealicious_points', $points + 10);
    }

    /* ------------------------------
     * Birthday Email
     * ------------------------------ */
    public function check_birthday_and_send_email() {

        $today = wp_date('m-d');
        $users = get_users(['fields' => ['ID', 'user_email']]);

        foreach ($users as $user) {

            $birthday = get_user_meta($user->ID, 'birthday', true);

            if (!$birthday) {
                continue;
            }

            if (wp_date('m-d', strtotime($birthday)) === $today) {

                $subject = __('Happy Birthday! Here’s 15% OFF 🎉', 'dealicious-smart-discounts-rewards-for-woocommerce');
                $message = __('Use coupon code BDAY15 today only.', 'dealicious-smart-discounts-rewards-for-woocommerce');

                wp_mail(
                    sanitize_email($user->user_email),
                    wp_strip_all_tags($subject),
                    wp_kses_post($message)
                );
            }
        }
    }
}

new DealiciousPlugin();
