<?php
/**
 * Plugin Name: Chicken Ecommerce Education
 * Description: Educational content management for chicken farming resources
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: chicken-ecommerce-education
 */

if (!defined('ABSPATH')) exit;

class ChickenEcommerceEducation {
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
        // Register custom post type
        add_action('init', [$this, 'registerEducationalPostType']);
        
        // Register taxonomies
        add_action('init', [$this, 'registerTaxonomies']);
        
        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_educational', [$this, 'saveMetaBoxData']);
        
        // Add custom templates
        add_filter('single_template', [$this, 'loadCustomTemplates']);
        
        // Add schema markup
        add_action('wp_head', [$this, 'addSchemaMarkup']);
        
        // Add related articles
        add_action('wp_ajax_get_related_articles', [$this, 'getRelatedArticles']);
        add_action('wp_ajax_nopriv_get_related_articles', [$this, 'getRelatedArticles']);
    }

    public function registerEducationalPostType() {
        $labels = [
            'name' => 'Educational Articles',
            'singular_name' => 'Educational Article',
            'menu_name' => 'Educational Content',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Article',
            'edit_item' => 'Edit Article',
            'new_item' => 'New Article',
            'view_item' => 'View Article',
            'search_items' => 'Search Articles',
            'not_found' => 'No articles found',
            'not_found_in_trash' => 'No articles found in trash'
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'rewrite' => ['slug' => 'resources'],
            'show_in_rest' => true,
            'rest_base' => 'educational',
            'rest_controller_class' => 'WP_REST_Posts_Controller'
        ];

        register_post_type('educational', $args);
    }

    public function registerTaxonomies() {
        // Article Categories
        register_taxonomy('article_category', 'educational', [
            'labels' => [
                'name' => 'Article Categories',
                'singular_name' => 'Article Category'
            ],
            'hierarchical' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'article-category'],
            'show_in_rest' => true
        ]);

        // Article Tags
        register_taxonomy('article_tag', 'educational', [
            'labels' => [
                'name' => 'Article Tags',
                'singular_name' => 'Article Tag'
            ],
            'hierarchical' => false,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'article-tag'],
            'show_in_rest' => true
        ]);
    }

    public function addMetaBoxes() {
        add_meta_box(
            'educational_meta_box',
            'Article Details',
            [$this, 'renderMetaBox'],
            'educational',
            'normal',
            'high'
        );
    }

    public function renderMetaBox($post) {
        wp_nonce_field('educational_meta_box', 'educational_meta_box_nonce');
        
        $reading_time = get_post_meta($post->ID, '_reading_time', true);
        $related_products = get_post_meta($post->ID, '_related_products', true);
        $difficulty_level = get_post_meta($post->ID, '_difficulty_level', true);
        
        ?>
        <p>
            <label for="reading_time">Estimated Reading Time (minutes):</label>
            <input type="number" id="reading_time" name="reading_time" value="<?php echo esc_attr($reading_time); ?>" min="1">
        </p>
        <p>
            <label for="difficulty_level">Difficulty Level:</label>
            <select id="difficulty_level" name="difficulty_level">
                <option value="beginner" <?php selected($difficulty_level, 'beginner'); ?>>Beginner</option>
                <option value="intermediate" <?php selected($difficulty_level, 'intermediate'); ?>>Intermediate</option>
                <option value="advanced" <?php selected($difficulty_level, 'advanced'); ?>>Advanced</option>
            </select>
        </p>
        <p>
            <label for="related_products">Related Products (comma-separated product IDs):</label>
            <input type="text" id="related_products" name="related_products" value="<?php echo esc_attr($related_products); ?>">
        </p>
        <?php
    }

    public function saveMetaBoxData($post_id) {
        if (!isset($_POST['educational_meta_box_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['educational_meta_box_nonce'], 'educational_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = ['reading_time', 'difficulty_level', 'related_products'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    public function loadCustomTemplates($template) {
        if (is_singular('educational')) {
            $custom_template = locate_template(['single-educational.php']);
            if ($custom_template) {
                return $custom_template;
            }
        }
        return $template;
    }

    public function addSchemaMarkup() {
        if (!is_singular('educational')) {
            return;
        }

        $post = get_post();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->post_title,
            'description' => get_the_excerpt(),
            'image' => get_the_post_thumbnail_url($post->ID, 'full'),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author()
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url()
                ]
            ],
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c')
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    public function getRelatedArticles() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $cache_key = "related_articles:{$post_id}";
        $related_articles = $this->redis ? $this->redis->get($cache_key) : false;

        if ($related_articles === false) {
            $current_categories = wp_get_post_terms($post_id, 'article_category', ['fields' => 'ids']);
            $current_tags = wp_get_post_terms($post_id, 'article_tag', ['fields' => 'ids']);

            $args = [
                'post_type' => 'educational',
                'posts_per_page' => 3,
                'post__not_in' => [$post_id],
                'tax_query' => [
                    'relation' => 'OR',
                    [
                        'taxonomy' => 'article_category',
                        'field' => 'term_id',
                        'terms' => $current_categories
                    ],
                    [
                        'taxonomy' => 'article_tag',
                        'field' => 'term_id',
                        'terms' => $current_tags
                    ]
                ]
            ];

            $query = new WP_Query($args);
            $related_articles = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $related_articles[] = [
                        'title' => get_the_title(),
                        'excerpt' => get_the_excerpt(),
                        'url' => get_permalink(),
                        'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail')
                    ];
                }
            }

            if ($this->redis) {
                $this->redis->setex($cache_key, 3600, wp_json_encode($related_articles));
            }
        } else {
            $related_articles = json_decode($related_articles, true);
        }

        wp_send_json_success($related_articles);
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    ChickenEcommerceEducation::getInstance();
}); 