<?php
/**
 * Template for displaying educational articles
 */

get_header();

while (have_posts()) :
    the_post();
    
    $reading_time = get_post_meta(get_the_ID(), '_reading_time', true);
    $difficulty_level = get_post_meta(get_the_ID(), '_difficulty_level', true);
    $related_products = get_post_meta(get_the_ID(), '_related_products', true);
    $related_products = $related_products ? explode(',', $related_products) : [];
    
    ?>
    <div class="educational-article">
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <div class="article-meta">
                    <span class="reading-time">
                        <i class="fas fa-clock"></i> <?php echo esc_html($reading_time); ?> min read
                    </span>
                    <span class="difficulty-level">
                        <i class="fas fa-signal"></i> <?php echo esc_html(ucfirst($difficulty_level)); ?>
                    </span>
                </div>
                
                <h1 class="entry-title"><?php the_title(); ?></h1>
                
                <div class="entry-meta">
                    <span class="posted-on">
                        <i class="fas fa-calendar"></i> <?php echo get_the_date(); ?>
                    </span>
                    <span class="author">
                        <i class="fas fa-user"></i> <?php the_author(); ?>
                    </span>
                </div>
            </header>

            <?php if (has_post_thumbnail()) : ?>
                <div class="featured-image">
                    <?php the_post_thumbnail('full', ['class' => 'img-fluid']); ?>
                </div>
            <?php endif; ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <?php if (!empty($related_products)) : ?>
                <div class="related-products">
                    <h3>Related Products</h3>
                    <div class="products-grid">
                        <?php
                        foreach ($related_products as $product_id) {
                            $product = wc_get_product($product_id);
                            if ($product) :
                                ?>
                                <div class="product-card">
                                    <a href="<?php echo esc_url($product->get_permalink()); ?>">
                                        <?php echo $product->get_image('thumbnail'); ?>
                                        <h4><?php echo esc_html($product->get_name()); ?></h4>
                                        <span class="price"><?php echo $product->get_price_html(); ?></span>
                                    </a>
                                </div>
                                <?php
                            endif;
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="related-articles">
                <h3>Related Articles</h3>
                <div class="articles-grid" id="related-articles-container">
                    <!-- Articles will be loaded via AJAX -->
                </div>
            </div>

            <footer class="entry-footer">
                <div class="article-categories">
                    <?php
                    $categories = get_the_terms(get_the_ID(), 'article_category');
                    if ($categories && !is_wp_error($categories)) :
                        ?>
                        <span class="categories-label">Categories:</span>
                        <?php
                        foreach ($categories as $category) {
                            echo '<a href="' . esc_url(get_term_link($category)) . '">' . esc_html($category->name) . '</a>';
                        }
                    endif;
                    ?>
                </div>

                <div class="article-tags">
                    <?php
                    $tags = get_the_terms(get_the_ID(), 'article_tag');
                    if ($tags && !is_wp_error($tags)) :
                        ?>
                        <span class="tags-label">Tags:</span>
                        <?php
                        foreach ($tags as $tag) {
                            echo '<a href="' . esc_url(get_term_link($tag)) . '">' . esc_html($tag->name) . '</a>';
                        }
                    endif;
                    ?>
                </div>
            </footer>
        </article>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Load related articles via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'get_related_articles',
                post_id: <?php echo get_the_ID(); ?>
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    const container = $('#related-articles-container');
                    response.data.forEach(function(article) {
                        container.append(`
                            <div class="article-card">
                                <a href="${article.url}">
                                    <img src="${article.thumbnail}" alt="${article.title}">
                                    <h4>${article.title}</h4>
                                    <p>${article.excerpt}</p>
                                </a>
                            </div>
                        `);
                    });
                }
            }
        });
    });
    </script>

    <style>
    .educational-article {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }

    .article-meta {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        color: #666;
    }

    .article-meta i {
        margin-right: 0.5rem;
    }

    .entry-title {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        line-height: 1.2;
    }

    .entry-meta {
        display: flex;
        gap: 1rem;
        color: #666;
        margin-bottom: 2rem;
    }

    .featured-image {
        margin-bottom: 2rem;
    }

    .featured-image img {
        width: 100%;
        height: auto;
        border-radius: 8px;
    }

    .entry-content {
        font-size: 1.1rem;
        line-height: 1.8;
        margin-bottom: 3rem;
    }

    .related-products,
    .related-articles {
        margin-top: 3rem;
        padding-top: 2rem;
        border-top: 1px solid #eee;
    }

    .products-grid,
    .articles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 2rem;
        margin-top: 1rem;
    }

    .product-card,
    .article-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: transform 0.2s;
    }

    .product-card:hover,
    .article-card:hover {
        transform: translateY(-5px);
    }

    .product-card img,
    .article-card img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .product-card h4,
    .article-card h4 {
        padding: 1rem;
        margin: 0;
        font-size: 1.1rem;
    }

    .article-card p {
        padding: 0 1rem 1rem;
        margin: 0;
        color: #666;
    }

    .entry-footer {
        margin-top: 3rem;
        padding-top: 2rem;
        border-top: 1px solid #eee;
    }

    .article-categories,
    .article-tags {
        margin-bottom: 1rem;
    }

    .categories-label,
    .tags-label {
        font-weight: bold;
        margin-right: 0.5rem;
    }

    .article-categories a,
    .article-tags a {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        margin: 0.25rem;
        background: #f5f5f5;
        border-radius: 20px;
        color: #333;
        text-decoration: none;
        font-size: 0.9rem;
    }

    .article-categories a:hover,
    .article-tags a:hover {
        background: #e0e0e0;
    }

    @media (max-width: 768px) {
        .educational-article {
            padding: 1rem;
        }

        .entry-title {
            font-size: 2rem;
        }

        .products-grid,
        .articles-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

<?php
endwhile;

get_footer(); 