<?php
/**
 * Plugin Name: Chicken Ecommerce Shipping
 * Description: Custom shipping rules and cart functionality for chicken farming supplies
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: chicken-ecommerce-shipping
 */

if (!defined('ABSPATH')) exit;

class ChickenEcommerceShipping {
    private static $instance = null;
    private $redis;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->initRedis();
        $this->setupHooks();
    }

    private function initRedis() {
        if (class_exists('Redis')) {
            $this->redis = new Redis();
            $this->redis->connect('redis', 6379);
        }
    }

    private function setupHooks() {
        // Cart and checkout hooks
        add_filter('woocommerce_cart_item_price', [$this, 'displayCartItemPrice'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'displayCartItemSubtotal'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'calculateCartTotals'], 10, 1);
        
        // Shipping hooks
        add_filter('woocommerce_package_rates', [$this, 'filterShippingRates'], 10, 2);
        add_action('woocommerce_before_shipping_calculator', [$this, 'displayShippingInfo']);
        
        // Checkout hooks
        add_action('woocommerce_checkout_before_customer_details', [$this, 'displayCheckoutNotice']);
        add_filter('woocommerce_checkout_fields', [$this, 'customizeCheckoutFields']);
        
        // Order hooks
        add_action('woocommerce_checkout_order_processed', [$this, 'processOrder'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'handleOrderStatusChange'], 10, 4);
    }

    public function displayCartItemPrice($price, $cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        $weight = $product->get_meta('_product_weight');
        
        if ($weight) {
            $price .= ' <small>(' . $weight . ' kg)</small>';
        }
        
        return $price;
    }

    public function displayCartItemSubtotal($subtotal, $cart_item, $cart_item_key) {
        $product = $cart_item['data'];
        $weight = $product->get_meta('_product_weight');
        $quantity = $cart_item['quantity'];
        
        if ($weight) {
            $total_weight = $weight * $quantity;
            $subtotal .= ' <small>(' . $total_weight . ' kg total)</small>';
        }
        
        return $subtotal;
    }

    public function calculateCartTotals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $total_weight = 0;
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $weight = $product->get_meta('_product_weight');
            if ($weight) {
                $total_weight += $weight * $cart_item['quantity'];
            }
        }

        // Store total weight in session for shipping calculations
        WC()->session->set('cart_total_weight', $total_weight);
    }

    public function filterShippingRates($rates, $package) {
        $total_weight = WC()->session->get('cart_total_weight', 0);
        
        // If weight is over 50kg, only show pickup options
        if ($total_weight > 50) {
            foreach ($rates as $rate_key => $rate) {
                if (strpos($rate->method_id, 'pickup') === false) {
                    unset($rates[$rate_key]);
                }
            }
        }
        
        return $rates;
    }

    public function displayShippingInfo() {
        $total_weight = WC()->session->get('cart_total_weight', 0);
        ?>
        <div class="shipping-info">
            <p>
                <strong>Shipping Information:</strong><br>
                - Orders under 50kg: Available for delivery or pickup<br>
                - Orders over 50kg: Pickup only<br>
                Current order weight: <?php echo number_format($total_weight, 2); ?> kg
            </p>
        </div>
        <?php
    }

    public function displayCheckoutNotice() {
        $total_weight = WC()->session->get('cart_total_weight', 0);
        if ($total_weight > 50) {
            wc_add_notice(
                'Your order exceeds 50kg. Delivery is not available. Please select a pickup location.',
                'notice'
            );
        }
    }

    public function customizeCheckoutFields($fields) {
        // Add pickup location field
        $fields['billing']['pickup_location'] = [
            'label' => 'Pickup Location',
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => 25,
            'type' => 'select',
            'options' => $this->getPickupLocations()
        ];

        return $fields;
    }

    private function getPickupLocations() {
        $locations = [
            '' => 'Select a pickup location',
            'store1' => 'Main Store - 123 Chicken Street',
            'store2' => 'Farm Supply Center - 456 Poultry Road',
            'store3' => 'Rural Outlet - 789 Coop Lane'
        ];

        return apply_filters('chicken_ecommerce_pickup_locations', $locations);
    }

    public function processOrder($order_id, $posted_data, $order) {
        // Store pickup location
        if (isset($posted_data['pickup_location'])) {
            update_post_meta($order_id, '_pickup_location', sanitize_text_field($posted_data['pickup_location']));
        }

        // Store order weight
        $total_weight = WC()->session->get('cart_total_weight', 0);
        update_post_meta($order_id, '_order_weight', $total_weight);
    }

    public function handleOrderStatusChange($order_id, $old_status, $new_status, $order) {
        if ($new_status === 'processing') {
            // Send pickup notification if applicable
            $pickup_location = get_post_meta($order_id, '_pickup_location', true);
            if ($pickup_location) {
                $this->sendPickupNotification($order, $pickup_location);
            }
        }
    }

    private function sendPickupNotification($order, $pickup_location) {
        $to = $order->get_billing_email();
        $subject = 'Order Ready for Pickup - ' . get_bloginfo('name');
        
        $message = sprintf(
            'Dear %s,<br><br>Your order #%s is ready for pickup at:<br><strong>%s</strong><br><br>Please bring your order confirmation email and a valid ID.<br><br>Thank you for your business!',
            $order->get_billing_first_name(),
            $order->get_order_number(),
            $this->getPickupLocations()[$pickup_location]
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        wp_mail($to, $subject, $message, $headers);
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    ChickenEcommerceShipping::getInstance();
}); 