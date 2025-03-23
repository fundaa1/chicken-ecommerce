<?php
/**
 * Plugin Name: Chicken Ecommerce Launch
 * Description: Launch preparation and testing tools for chicken farming ecommerce site
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: chicken-ecommerce-launch
 */

if (!defined('ABSPATH')) exit;

class ChickenEcommerceLaunch {
    private static $instance = null;
    private $redis;
    private $cache_prefix = 'chicken_ecommerce_launch_';

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
        // Admin menu
        add_action('admin_menu', [$this, 'addLaunchMenu']);
        
        // Testing hooks
        add_action('wp_ajax_run_load_test', [$this, 'runLoadTest']);
        add_action('wp_ajax_check_payment_gateways', [$this, 'checkPaymentGateways']);
        add_action('wp_ajax_verify_ssl', [$this, 'verifySSL']);
        
        // Documentation hooks
        add_action('admin_init', [$this, 'generateDocumentation']);
        
        // Launch checklist
        add_action('admin_init', [$this, 'checkLaunchRequirements']);
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function enqueueAdminAssets($hook) {
        if (strpos($hook, 'chicken-ecommerce-launch') === false) {
            return;
        }
        
        wp_enqueue_style(
            'chicken-ecommerce-launch-admin',
            plugins_url('assets/css/launch-admin.css', __FILE__),
            [],
            '1.0.0'
        );
        
        wp_enqueue_style(
            'chicken-ecommerce-launch-process',
            plugins_url('assets/css/launch-process.css', __FILE__),
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'chicken-ecommerce-launch-admin',
            plugins_url('assets/js/launch-admin.js', __FILE__),
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_enqueue_script(
            'chicken-ecommerce-launch-process',
            plugins_url('assets/js/launch-process.js', __FILE__),
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('chicken-ecommerce-launch-process', 'chicken_ecommerce_launch', [
            'nonce' => wp_create_nonce('launch_process_nonce'),
            'i18n' => [
                'confirm_launch' => __('Are you sure you want to start the launch process? This will put the site in maintenance mode and perform final optimizations.', 'chicken-ecommerce-launch')
            ]
        ]);
    }

    public function addLaunchMenu() {
        add_menu_page(
            __('Launch Preparation', 'chicken-ecommerce-launch'),
            __('Launch Prep', 'chicken-ecommerce-launch'),
            'manage_options',
            'chicken-ecommerce-launch',
            [$this, 'renderLaunchPage'],
            'dashicons-rocket',
            30
        );
    }

    public function renderLaunchPage() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'testing';
        ?>
        <div class="wrap">
            <h1><?php _e('Launch Preparation', 'chicken-ecommerce-launch'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=chicken-ecommerce-launch&tab=testing" class="nav-tab <?php echo $current_tab === 'testing' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Testing', 'chicken-ecommerce-launch'); ?>
                </a>
                <a href="?page=chicken-ecommerce-launch&tab=documentation" class="nav-tab <?php echo $current_tab === 'documentation' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Documentation', 'chicken-ecommerce-launch'); ?>
                </a>
                <a href="?page=chicken-ecommerce-launch&tab=checklist" class="nav-tab <?php echo $current_tab === 'checklist' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Launch Checklist', 'chicken-ecommerce-launch'); ?>
                </a>
                <a href="?page=chicken-ecommerce-launch&tab=process" class="nav-tab <?php echo $current_tab === 'process' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Launch Process', 'chicken-ecommerce-launch'); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'testing':
                        $this->renderTestingTab();
                        break;
                    case 'documentation':
                        $this->renderDocumentationTab();
                        break;
                    case 'checklist':
                        $this->renderChecklistTab();
                        break;
                    case 'process':
                        $this->renderLaunchProcessTab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function renderTestingTab() {
        ?>
        <div class="testing-section">
            <h2><?php _e('Performance Testing', 'chicken-ecommerce-launch'); ?></h2>
            <p><?php _e('Run comprehensive tests to ensure optimal performance before launch.', 'chicken-ecommerce-launch'); ?></p>
            
            <div class="test-buttons">
                <button type="button" class="button button-primary" id="run-load-test">
                    <?php _e('Run Load Test', 'chicken-ecommerce-launch'); ?>
                </button>
                <button type="button" class="button button-primary" id="check-payment-gateways">
                    <?php _e('Check Payment Gateways', 'chicken-ecommerce-launch'); ?>
                </button>
                <button type="button" class="button button-primary" id="verify-ssl">
                    <?php _e('Verify SSL Certificate', 'chicken-ecommerce-launch'); ?>
                </button>
            </div>
            
            <div id="test-results" class="test-results">
                <!-- Results will be displayed here -->
            </div>
        </div>
        <?php
    }

    private function renderDocumentationTab() {
        ?>
        <div class="documentation-section">
            <h2><?php _e('Documentation', 'chicken-ecommerce-launch'); ?></h2>
            <p><?php _e('Generate and manage documentation for the site.', 'chicken-ecommerce-launch'); ?></p>
            
            <div class="documentation-buttons">
                <button type="button" class="button button-primary" id="generate-user-docs">
                    <?php _e('Generate User Documentation', 'chicken-ecommerce-launch'); ?>
                </button>
                <button type="button" class="button button-primary" id="generate-dev-docs">
                    <?php _e('Generate Developer Documentation', 'chicken-ecommerce-launch'); ?>
                </button>
            </div>
            
            <div id="documentation-results" class="documentation-results">
                <!-- Documentation will be displayed here -->
            </div>
        </div>
        <?php
    }

    private function renderChecklistTab() {
        $requirements = $this->getLaunchRequirements();
        ?>
        <div class="checklist-section">
            <h2><?php _e('Launch Checklist', 'chicken-ecommerce-launch'); ?></h2>
            <p><?php _e('Verify all requirements are met before launch.', 'chicken-ecommerce-launch'); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Requirement', 'chicken-ecommerce-launch'); ?></th>
                        <th><?php _e('Status', 'chicken-ecommerce-launch'); ?></th>
                        <th><?php _e('Action', 'chicken-ecommerce-launch'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requirements as $requirement) : ?>
                        <tr>
                            <td><?php echo esc_html($requirement['name']); ?></td>
                            <td>
                                <span class="status-<?php echo $requirement['status'] ? 'success' : 'error'; ?>">
                                    <?php echo $requirement['status'] ? __('Passed', 'chicken-ecommerce-launch') : __('Failed', 'chicken-ecommerce-launch'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!$requirement['status']) : ?>
                                    <a href="<?php echo esc_url($requirement['action_url']); ?>" class="button button-small">
                                        <?php _e('Fix', 'chicken-ecommerce-launch'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderLaunchProcessTab() {
        $launch_process = Chicken_Ecommerce_Launch_Process::getInstance();
        $launch_process->renderLaunchProcessPage();
    }

    public function runLoadTest() {
        check_ajax_referer('launch_test_nonce', 'nonce');
        
        $results = [
            'page_load_times' => $this->testPageLoadTimes(),
            'server_response' => $this->testServerResponse(),
            'database_performance' => $this->testDatabasePerformance(),
            'cache_efficiency' => $this->testCacheEfficiency()
        ];
        
        wp_send_json_success($results);
    }

    public function checkPaymentGateways() {
        check_ajax_referer('launch_test_nonce', 'nonce');
        
        $results = [];
        $gateways = WC()->payment_gateways()->payment_gateways();
        
        foreach ($gateways as $gateway) {
            $results[$gateway->id] = [
                'enabled' => $gateway->enabled === 'yes',
                'test_mode' => $gateway->testmode === 'yes',
                'settings' => $gateway->settings
            ];
        }
        
        wp_send_json_success($results);
    }

    public function verifySSL() {
        check_ajax_referer('launch_test_nonce', 'nonce');
        
        $site_url = get_site_url();
        $ssl_info = $this->checkSSL($site_url);
        
        wp_send_json_success($ssl_info);
    }

    private function testPageLoadTimes() {
        $pages = [
            'home' => home_url(),
            'shop' => wc_get_page_permalink('shop'),
            'cart' => wc_get_page_permalink('cart'),
            'checkout' => wc_get_page_permalink('checkout')
        ];
        
        $results = [];
        foreach ($pages as $page => $url) {
            $start_time = microtime(true);
            $response = wp_remote_get($url);
            $end_time = microtime(true);
            
            $results[$page] = [
                'load_time' => round(($end_time - $start_time) * 1000, 2),
                'status_code' => wp_remote_retrieve_response_code($response),
                'success' => !is_wp_error($response)
            ];
        }
        
        return $results;
    }

    private function testServerResponse() {
        $results = [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
        
        return $results;
    }

    private function testDatabasePerformance() {
        global $wpdb;
        
        $results = [
            'query_time' => 0,
            'table_size' => 0,
            'optimization_status' => true
        ];
        
        // Test query performance
        $start_time = microtime(true);
        $wpdb->get_results("SELECT * FROM {$wpdb->posts} LIMIT 1");
        $results['query_time'] = round((microtime(true) - $start_time) * 1000, 2);
        
        // Get database size
        $results['table_size'] = $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()");
        
        return $results;
    }

    private function testCacheEfficiency() {
        $results = [
            'redis_connected' => false,
            'cache_hits' => 0,
            'cache_misses' => 0
        ];
        
        if ($this->redis) {
            $results['redis_connected'] = true;
            
            // Test cache performance
            $test_key = $this->cache_prefix . 'test_key';
            $this->redis->set($test_key, 'test_value');
            $results['cache_hits'] = $this->redis->get($test_key) ? 1 : 0;
            $results['cache_misses'] = $this->redis->get('non_existent_key') ? 0 : 1;
        }
        
        return $results;
    }

    private function checkSSL($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        
        $response = curl_exec($ch);
        $ssl_info = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
        $cert_info = curl_getinfo($ch, CURLINFO_CERTINFO);
        
        curl_close($ch);
        
        return [
            'valid' => $ssl_info === 0,
            'cert_info' => $cert_info,
            'url' => $url
        ];
    }

    private function getLaunchRequirements() {
        return [
            [
                'name' => __('SSL Certificate', 'chicken-ecommerce-launch'),
                'status' => is_ssl(),
                'action_url' => admin_url('admin.php?page=chicken-ecommerce-launch&tab=testing')
            ],
            [
                'name' => __('Payment Gateways', 'chicken-ecommerce-launch'),
                'status' => $this->checkPaymentGatewaysStatus(),
                'action_url' => admin_url('admin.php?page=wc-settings&tab=checkout')
            ],
            [
                'name' => __('Performance Optimization', 'chicken-ecommerce-launch'),
                'status' => $this->checkPerformanceStatus(),
                'action_url' => admin_url('admin.php?page=chicken-ecommerce-launch&tab=testing')
            ],
            [
                'name' => __('Security Measures', 'chicken-ecommerce-launch'),
                'status' => $this->checkSecurityStatus(),
                'action_url' => admin_url('admin.php?page=chicken-ecommerce-launch&tab=testing')
            ],
            [
                'name' => __('Documentation', 'chicken-ecommerce-launch'),
                'status' => $this->checkDocumentationStatus(),
                'action_url' => admin_url('admin.php?page=chicken-ecommerce-launch&tab=documentation')
            ]
        ];
    }

    private function checkPaymentGatewaysStatus() {
        $gateways = WC()->payment_gateways()->payment_gateways();
        foreach ($gateways as $gateway) {
            if ($gateway->enabled === 'yes') {
                return true;
            }
        }
        return false;
    }

    private function checkPerformanceStatus() {
        $load_times = $this->testPageLoadTimes();
        foreach ($load_times as $page => $data) {
            if ($data['load_time'] > 2000) { // 2 seconds
                return false;
            }
        }
        return true;
    }

    private function checkSecurityStatus() {
        return is_ssl() && 
               defined('DISALLOW_FILE_EDIT') && 
               DISALLOW_FILE_EDIT && 
               defined('FORCE_SSL_ADMIN') && 
               FORCE_SSL_ADMIN;
    }

    private function checkDocumentationStatus() {
        $docs_path = WP_CONTENT_DIR . '/documentation/';
        return file_exists($docs_path . 'user-guide.md') && 
               file_exists($docs_path . 'developer-guide.md');
    }
}

// Initialize the launch preparation plugin
add_action('plugins_loaded', function() {
    ChickenEcommerceLaunch::getInstance();
}); 