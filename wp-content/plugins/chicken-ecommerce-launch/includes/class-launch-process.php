<?php
/**
 * Launch Process Handler
 * 
 * Handles the final steps of the launch process
 */

class Chicken_Ecommerce_Launch_Process {
    private static $instance = null;
    private $redis;
    private $cache_prefix = 'chicken_ecommerce_launch_';
    private $launch_log = [];
    private $launch_status = [];

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
        add_action('admin_menu', [$this, 'addLaunchProcessMenu']);
        add_action('wp_ajax_start_launch_process', [$this, 'startLaunchProcess']);
        add_action('wp_ajax_check_launch_status', [$this, 'checkLaunchStatus']);
        add_action('wp_ajax_complete_launch', [$this, 'completeLaunch']);
    }

    public function addLaunchProcessMenu() {
        add_submenu_page(
            'chicken-ecommerce-launch',
            __('Launch Process', 'chicken-ecommerce-launch'),
            __('Launch Process', 'chicken-ecommerce-launch'),
            'manage_options',
            'chicken-ecommerce-launch-process',
            [$this, 'renderLaunchProcessPage']
        );
    }

    public function renderLaunchProcessPage() {
        $current_status = $this->getLaunchStatus();
        ?>
        <div class="wrap">
            <h1><?php _e('Launch Process', 'chicken-ecommerce-launch'); ?></h1>
            
            <?php if (!$current_status['in_progress'] && !$current_status['completed']) : ?>
                <div class="launch-warning">
                    <p class="notice notice-warning">
                        <?php _e('Before starting the launch process, please ensure:', 'chicken-ecommerce-launch'); ?>
                    </p>
                    <ul>
                        <li><?php _e('All testing has been completed successfully', 'chicken-ecommerce-launch'); ?></li>
                        <li><?php _e('Backup of the current site is available', 'chicken-ecommerce-launch'); ?></li>
                        <li><?php _e('DNS changes are ready to be applied', 'chicken-ecommerce-launch'); ?></li>
                        <li><?php _e('SSL certificate is properly configured', 'chicken-ecommerce-launch'); ?></li>
                    </ul>
                </div>
                
                <button type="button" class="button button-primary" id="start-launch-process">
                    <?php _e('Start Launch Process', 'chicken-ecommerce-launch'); ?>
                </button>
            <?php endif; ?>
            
            <?php if ($current_status['in_progress']) : ?>
                <div class="launch-progress">
                    <h2><?php _e('Launch Progress', 'chicken-ecommerce-launch'); ?></h2>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo esc_attr($current_status['progress']); ?>%"></div>
                    </div>
                    <p class="progress-text"><?php echo esc_html($current_status['current_step']); ?></p>
                    
                    <div class="launch-log">
                        <?php foreach ($this->launch_log as $log) : ?>
                            <div class="log-entry <?php echo esc_attr($log['status']); ?>">
                                <span class="timestamp"><?php echo esc_html($log['timestamp']); ?></span>
                                <span class="message"><?php echo esc_html($log['message']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($current_status['completed']) : ?>
                <div class="launch-complete">
                    <h2><?php _e('Launch Complete', 'chicken-ecommerce-launch'); ?></h2>
                    <p class="notice notice-success">
                        <?php _e('The site has been successfully launched!', 'chicken-ecommerce-launch'); ?>
                    </p>
                    
                    <div class="post-launch-checklist">
                        <h3><?php _e('Post-Launch Checklist', 'chicken-ecommerce-launch'); ?></h3>
                        <ul>
                            <li><?php _e('Monitor site performance', 'chicken-ecommerce-launch'); ?></li>
                            <li><?php _e('Check error logs', 'chicken-ecommerce-launch'); ?></li>
                            <li><?php _e('Verify all forms are working', 'chicken-ecommerce-launch'); ?></li>
                            <li><?php _e('Test checkout process', 'chicken-ecommerce-launch'); ?></li>
                            <li><?php _e('Monitor analytics', 'chicken-ecommerce-launch'); ?></li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function startLaunchProcess() {
        check_ajax_referer('launch_process_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $this->launch_status = [
            'in_progress' => true,
            'completed' => false,
            'progress' => 0,
            'current_step' => 'Initializing launch process...',
            'start_time' => current_time('mysql')
        ];
        
        $this->updateLaunchStatus();
        $this->addLogEntry('Launch process started', 'info');
        
        // Start the launch process
        $this->executeLaunchSteps();
        
        wp_send_json_success($this->launch_status);
    }

    private function executeLaunchSteps() {
        $steps = [
            'backup' => [$this, 'createBackup'],
            'maintenance_mode' => [$this, 'enableMaintenanceMode'],
            'cache_clear' => [$this, 'clearAllCaches'],
            'optimize_database' => [$this, 'optimizeDatabase'],
            'update_permalinks' => [$this, 'updatePermalinks'],
            'verify_ssl' => [$this, 'verifySSL'],
            'check_payment_gateways' => [$this, 'checkPaymentGateways'],
            'disable_debug_mode' => [$this, 'disableDebugMode'],
            'update_search_engines' => [$this, 'updateSearchEngines'],
            'maintenance_mode_disable' => [$this, 'disableMaintenanceMode']
        ];
        
        $total_steps = count($steps);
        $current_step = 0;
        
        foreach ($steps as $step_name => $step_callback) {
            $current_step++;
            $this->launch_status['progress'] = round(($current_step / $total_steps) * 100);
            $this->launch_status['current_step'] = 'Executing: ' . str_replace('_', ' ', $step_name);
            $this->updateLaunchStatus();
            
            try {
                call_user_func($step_callback);
                $this->addLogEntry("Step completed: {$step_name}", 'success');
            } catch (Exception $e) {
                $this->addLogEntry("Error in step {$step_name}: " . $e->getMessage(), 'error');
                throw $e;
            }
        }
        
        $this->launch_status['completed'] = true;
        $this->launch_status['in_progress'] = false;
        $this->updateLaunchStatus();
        $this->addLogEntry('Launch process completed successfully', 'success');
    }

    private function createBackup() {
        $this->addLogEntry('Creating backup...', 'info');
        
        // Create database backup
        $db_backup = $this->createDatabaseBackup();
        if (!$db_backup) {
            throw new Exception('Database backup failed');
        }
        
        // Create files backup
        $files_backup = $this->createFilesBackup();
        if (!$files_backup) {
            throw new Exception('Files backup failed');
        }
        
        $this->addLogEntry('Backup created successfully', 'success');
    }

    private function createDatabaseBackup() {
        global $wpdb;
        
        $backup_file = WP_CONTENT_DIR . '/backups/db-backup-' . date('Y-m-d-H-i-s') . '.sql';
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        
        $handle = fopen($backup_file, 'w');
        if (!$handle) {
            return false;
        }
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            $create_table = $wpdb->get_row("SHOW CREATE TABLE {$table_name}", ARRAY_N);
            fwrite($handle, $create_table[1] . ";\n\n");
            
            $rows = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
            foreach ($rows as $row) {
                $values = array_map([$wpdb, '_real_escape'], $row);
                fwrite($handle, "INSERT INTO {$table_name} VALUES ('" . implode("','", $values) . "');\n");
            }
            fwrite($handle, "\n");
        }
        
        fclose($handle);
        return true;
    }

    private function createFilesBackup() {
        $backup_dir = WP_CONTENT_DIR . '/backups/files-backup-' . date('Y-m-d-H-i-s');
        if (!mkdir($backup_dir, 0755, true)) {
            return false;
        }
        
        $dirs_to_backup = [
            ABSPATH . 'wp-content/themes',
            ABSPATH . 'wp-content/plugins',
            ABSPATH . 'wp-content/uploads'
        ];
        
        foreach ($dirs_to_backup as $dir) {
            $this->copyDirectory($dir, $backup_dir . '/' . basename($dir));
        }
        
        return true;
    }

    private function copyDirectory($source, $destination) {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                copy($file->getPathname(), $destination . '/' . $file->getRelativePathname());
            }
        }
    }

    private function enableMaintenanceMode() {
        $this->addLogEntry('Enabling maintenance mode...', 'info');
        
        $maintenance_file = ABSPATH . '.maintenance';
        $maintenance_content = "<?php\n\$upgrading = " . time() . ";\n";
        
        if (file_put_contents($maintenance_file, $maintenance_content)) {
            $this->addLogEntry('Maintenance mode enabled', 'success');
        } else {
            throw new Exception('Failed to enable maintenance mode');
        }
    }

    private function clearAllCaches() {
        $this->addLogEntry('Clearing all caches...', 'info');
        
        // Clear WordPress cache
        wp_cache_flush();
        
        // Clear Redis cache
        if ($this->redis) {
            $this->redis->flushAll();
        }
        
        // Clear browser cache headers
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        
        $this->addLogEntry('All caches cleared', 'success');
    }

    private function optimizeDatabase() {
        global $wpdb;
        
        $this->addLogEntry('Optimizing database...', 'info');
        
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        foreach ($tables as $table) {
            $table_name = $table[0];
            $wpdb->query("OPTIMIZE TABLE {$table_name}");
            $wpdb->query("REPAIR TABLE {$table_name}");
        }
        
        $this->addLogEntry('Database optimized', 'success');
    }

    private function updatePermalinks() {
        $this->addLogEntry('Updating permalinks...', 'info');
        
        flush_rewrite_rules();
        
        $this->addLogEntry('Permalinks updated', 'success');
    }

    private function verifySSL() {
        $this->addLogEntry('Verifying SSL certificate...', 'info');
        
        $site_url = get_site_url();
        $ssl_info = $this->checkSSL($site_url);
        
        if (!$ssl_info['valid']) {
            throw new Exception('SSL certificate verification failed');
        }
        
        $this->addLogEntry('SSL certificate verified', 'success');
    }

    private function checkPaymentGateways() {
        $this->addLogEntry('Checking payment gateways...', 'info');
        
        $gateways = WC()->payment_gateways()->payment_gateways();
        $active_gateways = false;
        
        foreach ($gateways as $gateway) {
            if ($gateway->enabled === 'yes') {
                $active_gateways = true;
                break;
            }
        }
        
        if (!$active_gateways) {
            throw new Exception('No active payment gateways found');
        }
        
        $this->addLogEntry('Payment gateways verified', 'success');
    }

    private function disableDebugMode() {
        $this->addLogEntry('Disabling debug mode...', 'info');
        
        $wp_config_file = ABSPATH . 'wp-config.php';
        $wp_config_content = file_get_contents($wp_config_file);
        
        // Remove or comment out debug settings
        $wp_config_content = preg_replace(
            "/define\s*\(\s*['\"]WP_DEBUG['\"].*?\);/",
            "define('WP_DEBUG', false);",
            $wp_config_content
        );
        
        if (file_put_contents($wp_config_file, $wp_config_content)) {
            $this->addLogEntry('Debug mode disabled', 'success');
        } else {
            throw new Exception('Failed to disable debug mode');
        }
    }

    private function updateSearchEngines() {
        $this->addLogEntry('Updating search engine settings...', 'info');
        
        // Update Yoast SEO settings
        if (class_exists('WPSEO_Options')) {
            update_option('wpseo', array_merge(
                get_option('wpseo'),
                ['is_public' => true]
            ));
        }
        
        // Update robots.txt
        $robots_content = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . home_url('sitemap.xml');
        file_put_contents(ABSPATH . 'robots.txt', $robots_content);
        
        $this->addLogEntry('Search engine settings updated', 'success');
    }

    private function disableMaintenanceMode() {
        $this->addLogEntry('Disabling maintenance mode...', 'info');
        
        $maintenance_file = ABSPATH . '.maintenance';
        if (file_exists($maintenance_file)) {
            if (unlink($maintenance_file)) {
                $this->addLogEntry('Maintenance mode disabled', 'success');
            } else {
                throw new Exception('Failed to disable maintenance mode');
            }
        }
    }

    public function checkLaunchStatus() {
        check_ajax_referer('launch_process_nonce', 'nonce');
        
        $status = $this->getLaunchStatus();
        wp_send_json_success($status);
    }

    public function completeLaunch() {
        check_ajax_referer('launch_process_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $this->launch_status['completed'] = true;
        $this->launch_status['in_progress'] = false;
        $this->updateLaunchStatus();
        
        $this->addLogEntry('Launch process completed', 'success');
        
        wp_send_json_success($this->launch_status);
    }

    private function getLaunchStatus() {
        if (empty($this->launch_status)) {
            $this->launch_status = get_option('chicken_ecommerce_launch_status', [
                'in_progress' => false,
                'completed' => false,
                'progress' => 0,
                'current_step' => '',
                'start_time' => ''
            ]);
        }
        return $this->launch_status;
    }

    private function updateLaunchStatus() {
        update_option('chicken_ecommerce_launch_status', $this->launch_status);
    }

    private function addLogEntry($message, $status = 'info') {
        $this->launch_log[] = [
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'status' => $status
        ];
        
        // Keep only the last 100 log entries
        if (count($this->launch_log) > 100) {
            array_shift($this->launch_log);
        }
        
        update_option('chicken_ecommerce_launch_log', $this->launch_log);
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
}

// Initialize the launch process handler
add_action('plugins_loaded', function() {
    Chicken_Ecommerce_Launch_Process::getInstance();
}); 