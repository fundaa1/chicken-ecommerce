<?php
/**
 * Order management functionality
 */

if (!defined('ABSPATH')) exit;

class Chicken_Ecommerce_Order_Management {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->setupHooks();
    }

    private function setupHooks() {
        // Admin hooks
        add_action('admin_menu', [$this, 'addOrderManagementMenu']);
        add_action('add_meta_boxes', [$this, 'addOrderMetaBoxes']);
        add_action('save_post_shop_order', [$this, 'saveOrderMeta']);
        
        // Order list hooks
        add_filter('manage_edit-shop_order_columns', [$this, 'addOrderColumns']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'renderOrderColumns'], 10, 2);
        
        // Order actions
        add_action('woocommerce_order_actions', [$this, 'addOrderActions']);
        add_action('woocommerce_order_action_send_pickup_notification', [$this, 'sendPickupNotification']);
    }

    public function addOrderManagementMenu() {
        add_submenu_page(
            'woocommerce',
            __('Weight-Based Orders', 'chicken-ecommerce-shipping'),
            __('Weight Orders', 'chicken-ecommerce-shipping'),
            'manage_woocommerce',
            'weight-based-orders',
            [$this, 'renderOrderManagementPage']
        );
    }

    public function renderOrderManagementPage() {
        $orders = $this->getWeightBasedOrders();
        ?>
        <div class="wrap">
            <h1><?php _e('Weight-Based Orders', 'chicken-ecommerce-shipping'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Order', 'chicken-ecommerce-shipping'); ?></th>
                        <th><?php _e('Date', 'chicken-ecommerce-shipping'); ?></th>
                        <th><?php _e('Weight', 'chicken-ecommerce-shipping'); ?></th>
                        <th><?php _e('Shipping Method', 'chicken-ecommerce-shipping'); ?></th>
                        <th><?php _e('Status', 'chicken-ecommerce-shipping'); ?></th>
                        <th><?php _e('Actions', 'chicken-ecommerce-shipping'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($order->ID); ?>">
                                    #<?php echo $order->ID; ?>
                                </a>
                            </td>
                            <td><?php echo $order->post_date; ?></td>
                            <td><?php echo get_post_meta($order->ID, '_order_weight', true); ?> kg</td>
                            <td><?php echo $this->getShippingMethod($order->ID); ?></td>
                            <td><?php echo wc_get_order_status_name($order->post_status); ?></td>
                            <td>
                                <?php
                                $actions = [
                                    'view' => [
                                        'url' => get_edit_post_link($order->ID),
                                        'name' => __('View', 'chicken-ecommerce-shipping')
                                    ]
                                ];

                                if ($this->isPickupOrder($order->ID)) {
                                    $actions['notify'] = [
                                        'url' => wp_nonce_url(admin_url('admin-ajax.php?action=send_pickup_notification&order_id=' . $order->ID), 'send_pickup_notification'),
                                        'name' => __('Send Pickup Notification', 'chicken-ecommerce-shipping')
                                    ];
                                }

                                foreach ($actions as $action) {
                                    echo '<a href="' . esc_url($action['url']) . '">' . esc_html($action['name']) . '</a> | ';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function getWeightBasedOrders() {
        $args = [
            'post_type' => 'shop_order',
            'post_status' => ['wc-processing', 'wc-completed'],
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_order_weight',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        return get_posts($args);
    }

    public function addOrderMetaBoxes() {
        add_meta_box(
            'weight_shipping_details',
            __('Weight & Shipping Details', 'chicken-ecommerce-shipping'),
            [$this, 'renderOrderMetaBox'],
            'shop_order',
            'side',
            'default'
        );
    }

    public function renderOrderMetaBox($post) {
        $order = wc_get_order($post->ID);
        $weight = get_post_meta($post->ID, '_order_weight', true);
        $pickup_location = get_post_meta($post->ID, '_pickup_location', true);
        $shipping_method = $this->getShippingMethod($post->ID);
        ?>
        <div class="weight-shipping-details">
            <p>
                <strong><?php _e('Order Weight:', 'chicken-ecommerce-shipping'); ?></strong><br>
                <?php echo $weight ? $weight . ' kg' : __('Not set', 'chicken-ecommerce-shipping'); ?>
            </p>
            <p>
                <strong><?php _e('Shipping Method:', 'chicken-ecommerce-shipping'); ?></strong><br>
                <?php echo esc_html($shipping_method); ?>
            </p>
            <?php if ($pickup_location) : ?>
                <p>
                    <strong><?php _e('Pickup Location:', 'chicken-ecommerce-shipping'); ?></strong><br>
                    <?php echo esc_html($this->getPickupLocationName($pickup_location)); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function addOrderColumns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_total') {
                $new_columns['order_weight'] = __('Weight', 'chicken-ecommerce-shipping');
            }
        }
        
        return $new_columns;
    }

    public function renderOrderColumns($column, $post_id) {
        if ($column === 'order_weight') {
            $weight = get_post_meta($post_id, '_order_weight', true);
            echo $weight ? $weight . ' kg' : '-';
        }
    }

    public function addOrderActions($actions) {
        $actions['send_pickup_notification'] = __('Send Pickup Notification', 'chicken-ecommerce-shipping');
        return $actions;
    }

    public function sendPickupNotification($order) {
        $pickup_location = get_post_meta($order->get_id(), '_pickup_location', true);
        if ($pickup_location) {
            $this->sendPickupEmail($order, $pickup_location);
            $order->add_order_note(__('Pickup notification email sent to customer.', 'chicken-ecommerce-shipping'));
        }
    }

    private function sendPickupEmail($order, $pickup_location) {
        $to = $order->get_billing_email();
        $subject = sprintf(__('Order #%s Ready for Pickup', 'chicken-ecommerce-shipping'), $order->get_order_number());
        
        $message = sprintf(
            __('Dear %s,<br><br>Your order #%s is ready for pickup at:<br><strong>%s</strong><br><br>Please bring your order confirmation email and a valid ID.<br><br>Thank you for your business!', 'chicken-ecommerce-shipping'),
            $order->get_billing_first_name(),
            $order->get_order_number(),
            $this->getPickupLocationName($pickup_location)
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        wp_mail($to, $subject, $message, $headers);
    }

    private function getShippingMethod($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return '';

        $shipping_methods = $order->get_shipping_methods();
        if (empty($shipping_methods)) return '';

        $method = reset($shipping_methods);
        return $method->get_method_title();
    }

    private function isPickupOrder($order_id) {
        $shipping_method = $this->getShippingMethod($order_id);
        return strpos($shipping_method, 'Pickup') !== false;
    }

    private function getPickupLocationName($location_id) {
        $locations = [
            'store1' => __('Main Store - 123 Chicken Street', 'chicken-ecommerce-shipping'),
            'store2' => __('Farm Supply Center - 456 Poultry Road', 'chicken-ecommerce-shipping'),
            'store3' => __('Rural Outlet - 789 Coop Lane', 'chicken-ecommerce-shipping')
        ];

        return isset($locations[$location_id]) ? $locations[$location_id] : $location_id;
    }
}

// Initialize the order management
add_action('plugins_loaded', function() {
    Chicken_Ecommerce_Order_Management::getInstance();
}); 