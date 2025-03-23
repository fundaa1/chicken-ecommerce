<?php
/**
 * Plugin Name: Chicken Ecommerce Security
 * Description: Enhanced security measures for chicken farming ecommerce site
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: chicken-ecommerce-security
 */

if (!defined('ABSPATH')) exit;

class ChickenEcommerceSecurity {
    private static $instance = null;
    private $redis;
    private $cache_prefix = 'chicken_ecommerce_security_';
    private $max_login_attempts = 5;
    private $lockout_duration = 1800; // 30 minutes

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
        // Login security
        add_action('wp_login_failed', [$this, 'handleLoginFailure']);
        add_action('wp_login', [$this, 'handleLoginSuccess']);
        add_filter('authenticate', [$this, 'checkLoginAttempts'], 30, 3);
        
        // File security
        add_action('init', [$this, 'disableFileEditing']);
        add_filter('upload_mimes', [$this, 'restrictUploadTypes']);
        add_filter('wp_handle_upload_prefilter', [$this, 'validateFileUpload']);
        
        // API security
        add_action('init', [$this, 'disableXmlrpc']);
        add_filter('rest_authentication_errors', [$this, 'restrictRestApi']);
        
        // Admin security
        add_action('admin_init', [$this, 'setupAdminSecurity']);
        add_filter('admin_url', [$this, 'secureAdminUrl'], 10, 3);
        
        // Content security
        add_action('init', [$this, 'setupContentSecurity']);
        add_filter('wp_headers', [$this, 'addSecurityHeaders']);
        
        // Database security
        add_action('init', [$this, 'setupDatabaseSecurity']);
        add_filter('query', [$this, 'preventSqlInjection']);
    }

    public function handleLoginFailure($username) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $attempts_key = $this->cache_prefix . 'login_attempts_' . $ip;
        
        if ($this->redis) {
            $attempts = $this->redis->get($attempts_key) ?: 0;
            $attempts++;
            
            if ($attempts >= $this->max_login_attempts) {
                $this->redis->setex($attempts_key, $this->lockout_duration, $attempts);
                $this->logSecurityEvent('login_lockout', [
                    'ip' => $ip,
                    'username' => $username,
                    'attempts' => $attempts
                ]);
            } else {
                $this->redis->setex($attempts_key, $this->lockout_duration, $attempts);
            }
        }
    }

    public function handleLoginSuccess($username) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $attempts_key = $this->cache_prefix . 'login_attempts_' . $ip;
        
        if ($this->redis) {
            $this->redis->del($attempts_key);
        }
    }

    public function checkLoginAttempts($user, $username, $password) {
        if ($user instanceof WP_User) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $attempts_key = $this->cache_prefix . 'login_attempts_' . $ip;
            
            if ($this->redis) {
                $attempts = $this->redis->get($attempts_key);
                if ($attempts && $attempts >= $this->max_login_attempts) {
                    return new WP_Error(
                        'too_many_attempts',
                        sprintf(
                            __('Too many failed login attempts. Please try again in %d minutes.', 'chicken-ecommerce-security'),
                            ceil($this->lockout_duration / 60)
                        )
                    );
                }
            }
        }
        return $user;
    }

    public function disableFileEditing() {
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
    }

    public function restrictUploadTypes($mimes) {
        $allowed_types = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'pdf' => 'application/pdf'
        ];
        return $allowed_types;
    }

    public function validateFileUpload($file) {
        if ($file['type']) {
            $allowed_types = [
                'image/jpeg',
                'image/gif',
                'image/png',
                'application/pdf'
            ];
            
            if (!in_array($file['type'], $allowed_types)) {
                $file['error'] = __('File type not allowed.', 'chicken-ecommerce-security');
                return $file;
            }
        }
        
        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $file['error'] = __('File too large. Maximum size is 5MB.', 'chicken-ecommerce-security');
            return $file;
        }
        
        return $file;
    }

    public function disableXmlrpc() {
        add_filter('xmlrpc_enabled', '__return_false');
    }

    public function restrictRestApi($result) {
        if (!empty($result)) {
            return $result;
        }
        
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to access the REST API.', 'chicken-ecommerce-security'),
                ['status' => 401]
            );
        }
        
        return $result;
    }

    public function setupAdminSecurity() {
        // Remove WordPress version from admin footer
        remove_filter('update_footer', 'core_update_footer');
        
        // Disable application passwords
        add_filter('wp_is_application_passwords_available', '__return_false');
        
        // Add two-factor authentication
        if (!defined('FORCE_SSL_ADMIN')) {
            define('FORCE_SSL_ADMIN', true);
        }
    }

    public function secureAdminUrl($url, $path, $scheme) {
        if ($scheme === 'admin') {
            $scheme = 'https';
        }
        return $url;
    }

    public function setupContentSecurity() {
        if (!is_admin()) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' https:;");
        }
    }

    public function addSecurityHeaders($headers) {
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        $headers['Permissions-Policy'] = 'geolocation=(), microphone=(), camera=()';
        $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        return $headers;
    }

    public function setupDatabaseSecurity() {
        global $wpdb;
        
        // Use prepared statements
        $wpdb->show_errors = false;
        
        // Set secure database connection
        if (defined('MYSQL_CLIENT_SSL')) {
            $wpdb->query('SET SESSION ssl=1');
        }
    }

    public function preventSqlInjection($query) {
        // Basic SQL injection prevention
        $blacklist = [
            'UNION',
            'UNION ALL',
            'SELECT',
            'INSERT',
            'UPDATE',
            'DELETE',
            'DROP',
            'TRUNCATE',
            'ALTER',
            'CREATE'
        ];
        
        foreach ($blacklist as $word) {
            if (stripos($query, $word) !== false) {
                $this->logSecurityEvent('sql_injection_attempt', [
                    'query' => $query,
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                return '';
            }
        }
        
        return $query;
    }

    private function logSecurityEvent($event_type, $data) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ];
        
        $log_file = WP_CONTENT_DIR . '/security.log';
        error_log(json_encode($log_entry) . "\n", 3, $log_file);
    }
}

// Initialize the security plugin
add_action('plugins_loaded', function() {
    ChickenEcommerceSecurity::getInstance();
}); 