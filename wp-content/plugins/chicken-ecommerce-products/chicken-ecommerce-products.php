<?php
/**
 * Plugin Name: Chicken Ecommerce Products
 * Description: Product structure and category management for chicken farming supplies
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: chicken-ecommerce-products
 */

if (!defined('ABSPATH')) exit;

class ChickenEcommerceProducts {
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
        // Product category setup
        add_action('init', [$this, 'setupProductCategories']);
        
        // Product attributes
        add_action('init', [$this, 'setupProductAttributes']);
        
        // Custom product fields
        add_action('woocommerce_product_options_general_product_data', [$this, 'addCustomProductFields']);
        add_action('woocommerce_process_product_meta', [$this, 'saveCustomProductFields']);
        
        // Bulk import functionality
        add_action('admin_menu', [$this, 'addBulkImportMenu']);
        add_action('wp_ajax_import_products', [$this, 'handleBulkImport']);
        
        // Cache product data
        add_action('woocommerce_update_product', [$this, 'clearProductCache']);
        add_action('woocommerce_new_product', [$this, 'clearProductCache']);
    }

    public function setupProductCategories() {
        $categories = [
            'equipment-supplies' => [
                'name' => 'Equipment & Supplies',
                'subcategories' => [
                    'feeders-waterers' => 'Feeders & Waterers',
                    'coops-housing' => 'Coops & Housing',
                    'incubators-brooders' => 'Incubators & Brooders',
                    'cleaning-supplies' => 'Cleaning Supplies'
                ]
            ],
            'feed-nutrition' => [
                'name' => 'Feed & Nutrition',
                'subcategories' => [
                    'starter-feed' => 'Starter Feed',
                    'layer-feed' => 'Layer Feed',
                    'broiler-feed' => 'Broiler Feed',
                    'supplements' => 'Supplements'
                ]
            ],
            'health-medication' => [
                'name' => 'Health & Medication',
                'subcategories' => [
                    'vaccines' => 'Vaccines',
                    'medications' => 'Medications',
                    'vitamins' => 'Vitamins',
                    'first-aid' => 'First Aid'
                ]
            ]
        ];

        foreach ($categories as $slug => $category) {
            $term = term_exists($category['name'], 'product_cat');
            if (!$term) {
                $term = wp_insert_term($category['name'], 'product_cat', [
                    'slug' => $slug
                ]);
            }

            if (!is_wp_error($term)) {
                foreach ($category['subcategories'] as $sub_slug => $sub_name) {
                    $sub_term = term_exists($sub_name, 'product_cat');
                    if (!$sub_term) {
                        wp_insert_term($sub_name, 'product_cat', [
                            'slug' => $sub_slug,
                            'parent' => $term['term_id']
                        ]);
                    }
                }
            }
        }
    }

    public function setupProductAttributes() {
        $attributes = [
            'weight' => [
                'name' => 'Weight',
                'type' => 'decimal',
                'order_by' => 'numeric'
            ],
            'minimum_order' => [
                'name' => 'Minimum Order',
                'type' => 'decimal',
                'order_by' => 'numeric'
            ],
            'suitable_for' => [
                'name' => 'Suitable For',
                'type' => 'select',
                'order_by' => 'menu_order',
                'options' => [
                    'broilers' => 'Broilers',
                    'layers' => 'Layers',
                    'both' => 'Both'
                ]
            ]
        ];

        foreach ($attributes as $slug => $attribute) {
            $exists = wc_attribute_exists($slug);
            if (!$exists) {
                wc_create_attribute([
                    'name' => $attribute['name'],
                    'slug' => $slug,
                    'type' => $attribute['type'],
                    'order_by' => $attribute['order_by']
                ]);

                if (isset($attribute['options'])) {
                    foreach ($attribute['options'] as $option_slug => $option_name) {
                        wc_create_attribute_term($option_name, $slug);
                    }
                }
            }
        }
    }

    public function addCustomProductFields() {
        global $woocommerce, $post;
        
        echo '<div class="options_group">';
        
        woocommerce_wp_text_input([
            'id' => '_product_weight',
            'label' => __('Product Weight (kg)', 'chicken-ecommerce-products'),
            'type' => 'number',
            'custom_attributes' => [
                'step' => '0.01',
                'min' => '0'
            ]
        ]);

        woocommerce_wp_text_input([
            'id' => '_minimum_order',
            'label' => __('Minimum Order Quantity', 'chicken-ecommerce-products'),
            'type' => 'number',
            'custom_attributes' => [
                'step' => '1',
                'min' => '1'
            ]
        ]);

        echo '</div>';
    }

    public function saveCustomProductFields($post_id) {
        $weight = isset($_POST['_product_weight']) ? sanitize_text_field($_POST['_product_weight']) : '';
        $min_order = isset($_POST['_minimum_order']) ? sanitize_text_field($_POST['_minimum_order']) : '';

        update_post_meta($post_id, '_product_weight', $weight);
        update_post_meta($post_id, '_minimum_order', $min_order);
    }

    public function addBulkImportMenu() {
        add_submenu_page(
            'woocommerce',
            'Bulk Import Products',
            'Bulk Import',
            'manage_woocommerce',
            'bulk-import-products',
            [$this, 'renderBulkImportPage']
        );
    }

    public function renderBulkImportPage() {
        ?>
        <div class="wrap">
            <h1>Bulk Import Products</h1>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('bulk_import_products', 'bulk_import_nonce'); ?>
                <input type="file" name="product_csv" accept=".csv" required>
                <input type="submit" name="submit" class="button button-primary" value="Import Products">
            </form>
        </div>
        <?php
    }

    public function handleBulkImport() {
        check_ajax_referer('bulk_import_products', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $file = $_FILES['product_csv'];
        if (!$file) {
            wp_send_json_error('No file uploaded');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            wp_send_json_error('Could not open file');
        }

        $headers = fgetcsv($handle);
        $imported = 0;
        $errors = [];

        while (($data = fgetcsv($handle)) !== false) {
            $product_data = array_combine($headers, $data);
            
            try {
                $product = new WC_Product_Simple();
                $product->set_name($product_data['name']);
                $product->set_description($product_data['description']);
                $product->set_regular_price($product_data['price']);
                $product->set_manage_stock(true);
                $product->set_stock_quantity($product_data['stock']);
                $product->set_weight($product_data['weight']);
                
                // Set categories
                $categories = explode(',', $product_data['categories']);
                $category_ids = [];
                foreach ($categories as $category) {
                    $term = get_term_by('slug', trim($category), 'product_cat');
                    if ($term) {
                        $category_ids[] = $term->term_id;
                    }
                }
                wp_set_object_terms($product->get_id(), $category_ids, 'product_cat');
                
                $product->save();
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Error importing product {$product_data['name']}: {$e->getMessage()}";
            }
        }

        fclose($handle);
        
        wp_send_json_success([
            'imported' => $imported,
            'errors' => $errors
        ]);
    }

    public function clearProductCache($product_id) {
        if ($this->redis) {
            $this->redis->del("product:{$product_id}");
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    ChickenEcommerceProducts::getInstance();
}); 