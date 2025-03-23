<?php
/**
 * Plugin Name: Chicken Ecommerce Optimizer
 * Description: Performance optimization and caching for chicken farming ecommerce site
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: chicken-ecommerce-optimizer
 */

if (!defined('ABSPATH')) exit;

class ChickenEcommerceOptimizer {
    private static $instance = null;
    private $redis;
    private $cache_prefix = 'chicken_ecommerce_';
    private $cache_expiry = 3600; // 1 hour

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
        // Performance hooks
        add_action('wp_enqueue_scripts', [$this, 'optimizeScripts']);
        add_action('wp_enqueue_scripts', [$this, 'optimizeStyles']);
        add_filter('script_loader_tag', [$this, 'addAsyncDeferAttributes'], 10, 3);
        add_filter('style_loader_tag', [$this, 'addPreloadAttribute'], 10, 4);
        
        // Image optimization
        add_filter('wp_get_attachment_image_attributes', [$this, 'addLazyLoading']);
        add_filter('wp_get_attachment_image', [$this, 'addLazyLoadingToImage']);
        
        // Caching hooks
        add_action('save_post', [$this, 'clearPostCache'], 10, 3);
        add_action('deleted_post', [$this, 'clearPostCache']);
        add_action('woocommerce_update_product', [$this, 'clearProductCache']);
        add_action('woocommerce_new_order', [$this, 'clearOrderCache']);
        
        // Database optimization
        add_action('wp_scheduled_delete', [$this, 'cleanupTransients']);
        add_action('wp_scheduled_delete', [$this, 'cleanupExpiredCaches']);
        
        // Security hooks
        add_action('init', [$this, 'setupSecurityHeaders']);
        add_filter('wp_headers', [$this, 'addSecurityHeaders']);
        add_action('login_init', [$this, 'setupLoginSecurity']);
    }

    public function optimizeScripts() {
        if (!is_admin()) {
            wp_enqueue_script('lazysizes', plugins_url('assets/js/lazysizes.min.js', __FILE__), [], '5.3.2', true);
            wp_enqueue_script('intersection-observer', plugins_url('assets/js/intersection-observer.min.js', __FILE__), [], '0.12.2', true);
        }
    }

    public function optimizeStyles() {
        if (!is_admin()) {
            wp_enqueue_style('critical-css', plugins_url('assets/css/critical.min.css', __FILE__));
        }
    }

    public function addAsyncDeferAttributes($tag, $handle, $src) {
        if (in_array($handle, ['jquery', 'jquery-core', 'jquery-migrate'])) {
            return $tag;
        }

        if (strpos($handle, 'async') !== false) {
            return str_replace(' src', ' async src', $tag);
        }

        if (strpos($handle, 'defer') !== false) {
            return str_replace(' src', ' defer src', $tag);
        }

        return $tag;
    }

    public function addPreloadAttribute($tag, $handle, $href, $media) {
        if (in_array($handle, ['critical-css'])) {
            return str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag);
        }
        return $tag;
    }

    public function addLazyLoading($attributes) {
        $attributes['loading'] = 'lazy';
        $attributes['class'] = isset($attributes['class']) ? $attributes['class'] . ' lazyload' : 'lazyload';
        return $attributes;
    }

    public function addLazyLoadingToImage($html) {
        return str_replace('<img', '<img loading="lazy" class="lazyload"', $html);
    }

    public function clearPostCache($post_id, $post = null, $update = false) {
        if ($post && $post->post_type === 'product') {
            $this->clearProductCache($post_id);
        }
        
        $cache_key = $this->cache_prefix . 'post_' . $post_id;
        $this->redis->del($cache_key);
    }

    public function clearProductCache($product_id) {
        $cache_key = $this->cache_prefix . 'product_' . $product_id;
        $this->redis->del($cache_key);
        
        // Clear related products cache
        $related_key = $this->cache_prefix . 'related_products_' . $product_id;
        $this->redis->del($related_key);
    }

    public function clearOrderCache($order_id) {
        $cache_key = $this->cache_prefix . 'order_' . $order_id;
        $this->redis->del($cache_key);
    }

    public function cleanupTransients() {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_chicken_ecommerce_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_chicken_ecommerce_%'");
    }

    public function cleanupExpiredCaches() {
        if ($this->redis) {
            $keys = $this->redis->keys($this->cache_prefix . '*');
            foreach ($keys as $key) {
                if (!$this->redis->ttl($key)) {
                    $this->redis->del($key);
                }
            }
        }
    }

    public function setupSecurityHeaders() {
        if (!is_admin()) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        }
    }

    public function addSecurityHeaders($headers) {
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        $headers['Permissions-Policy'] = 'geolocation=(), microphone=(), camera=()';
        return $headers;
    }

    public function setupLoginSecurity() {
        // Rate limiting for login attempts
        $ip = $_SERVER['REMOTE_ADDR'];
        $attempts_key = $this->cache_prefix . 'login_attempts_' . $ip;
        
        if ($this->redis) {
            $attempts = $this->redis->get($attempts_key);
            if ($attempts && $attempts > 5) {
                wp_die('Too many login attempts. Please try again later.');
            }
        }
    }

    public function cacheData($key, $data, $expiry = null) {
        if ($this->redis) {
            $cache_key = $this->cache_prefix . $key;
            $expiry = $expiry ?: $this->cache_expiry;
            return $this->redis->setex($cache_key, $expiry, serialize($data));
        }
        return false;
    }

    public function getCachedData($key) {
        if ($this->redis) {
            $cache_key = $this->cache_prefix . $key;
            $data = $this->redis->get($cache_key);
            return $data ? unserialize($data) : false;
        }
        return false;
    }
}

// Initialize the optimizer
add_action('plugins_loaded', function() {
    ChickenEcommerceOptimizer::getInstance();
}); 