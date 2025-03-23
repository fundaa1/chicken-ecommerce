<?php
/**
 * Weight-based shipping method
 */

if (!defined('ABSPATH')) exit;

class WC_Weight_Based_Shipping extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'weight_based_shipping';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Weight Based Shipping', 'chicken-ecommerce-shipping');
        $this->method_description = __('Calculate shipping based on order weight', 'chicken-ecommerce-shipping');
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->instance_form_fields = [
            'title' => [
                'title' => __('Method Title', 'chicken-ecommerce-shipping'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'chicken-ecommerce-shipping'),
                'default' => __('Weight Based Shipping', 'chicken-ecommerce-shipping'),
                'desc_tip' => true,
            ],
            'base_rate' => [
                'title' => __('Base Rate', 'chicken-ecommerce-shipping'),
                'type' => 'number',
                'description' => __('Base shipping rate for orders under 50kg', 'chicken-ecommerce-shipping'),
                'default' => '10',
                'desc_tip' => true,
            ],
            'rate_per_kg' => [
                'title' => __('Rate per kg', 'chicken-ecommerce-shipping'),
                'type' => 'number',
                'description' => __('Additional rate per kg for orders under 50kg', 'chicken-ecommerce-shipping'),
                'default' => '0.5',
                'desc_tip' => true,
            ],
            'pickup_rate' => [
                'title' => __('Pickup Rate', 'chicken-ecommerce-shipping'),
                'type' => 'number',
                'description' => __('Rate for pickup orders', 'chicken-ecommerce-shipping'),
                'default' => '5',
                'desc_tip' => true,
            ],
            'max_weight' => [
                'title' => __('Maximum Weight for Delivery', 'chicken-ecommerce-shipping'),
                'type' => 'number',
                'description' => __('Maximum weight in kg for delivery option', 'chicken-ecommerce-shipping'),
                'default' => '50',
                'desc_tip' => true,
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        $total_weight = WC()->session->get('cart_total_weight', 0);
        $base_rate = $this->get_option('base_rate');
        $rate_per_kg = $this->get_option('rate_per_kg');
        $pickup_rate = $this->get_option('pickup_rate');
        $max_weight = $this->get_option('max_weight');

        // Calculate delivery rate
        $delivery_rate = $base_rate + ($total_weight * $rate_per_kg);

        // Add delivery rate if weight is under max
        if ($total_weight <= $max_weight) {
            $this->add_rate([
                'id' => $this->id . '_delivery',
                'label' => $this->get_option('title') . ' - ' . __('Delivery', 'chicken-ecommerce-shipping'),
                'cost' => $delivery_rate,
                'calc_tax' => 'per_order',
            ]);
        }

        // Add pickup rate
        $this->add_rate([
            'id' => $this->id . '_pickup',
            'label' => $this->get_option('title') . ' - ' . __('Pickup', 'chicken-ecommerce-shipping'),
            'cost' => $pickup_rate,
            'calc_tax' => 'per_order',
        ]);
    }
} 