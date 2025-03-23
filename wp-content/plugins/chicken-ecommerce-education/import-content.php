<?php
/**
 * Script to import educational content from markdown files
 */

require_once('../../../../wp-load.php');

function import_educational_content() {
    $markdown_file = __DIR__ . '/../../educational-content.md';
    if (!file_exists($markdown_file)) {
        die('Educational content markdown file not found.');
    }

    $content = file_get_contents($markdown_file);
    $articles = parse_markdown_content($content);

    foreach ($articles as $article) {
        create_educational_post($article);
    }
}

function parse_markdown_content($content) {
    $articles = [];
    $current_article = [];
    $lines = explode("\n", $content);

    foreach ($lines as $line) {
        if (strpos($line, '# ') === 0) {
            if (!empty($current_article)) {
                $articles[] = $current_article;
            }
            $current_article = [
                'title' => trim(substr($line, 2)),
                'content' => '',
                'categories' => [],
                'tags' => []
            ];
        } elseif (strpos($line, '## ') === 0) {
            $current_article['content'] .= "\n<h2>" . trim(substr($line, 3)) . "</h2>\n";
        } elseif (strpos($line, '### ') === 0) {
            $current_article['content'] .= "\n<h3>" . trim(substr($line, 4)) . "</h3>\n";
        } elseif (strpos($line, '**') !== false) {
            $current_article['content'] .= "\n<strong>" . trim(str_replace('**', '', $line)) . "</strong>\n";
        } elseif (strpos($line, '*') !== false) {
            $current_article['content'] .= "\n<em>" . trim(str_replace('*', '', $line)) . "</em>\n";
        } elseif (strpos($line, '- ') === 0) {
            $current_article['content'] .= "\n<li>" . trim(substr($line, 2)) . "</li>\n";
        } elseif (strpos($line, '1. ') === 0) {
            $current_article['content'] .= "\n<ol><li>" . trim(substr($line, 3)) . "</li></ol>\n";
        } elseif (strpos($line, '> ') === 0) {
            $current_article['content'] .= "\n<blockquote>" . trim(substr($line, 2)) . "</blockquote>\n";
        } elseif (strpos($line, '`') !== false) {
            $current_article['content'] .= "\n<code>" . trim(str_replace('`', '', $line)) . "</code>\n";
        } elseif (strpos($line, '---') === 0) {
            $current_article['content'] .= "\n<hr>\n";
        } elseif (strpos($line, '[') !== false && strpos($line, '](') !== false) {
            preg_match('/\[(.*?)\]\((.*?)\)/', $line, $matches);
            if (count($matches) === 3) {
                $current_article['content'] .= "\n<a href=\"{$matches[2]}\">{$matches[1]}</a>\n";
            }
        } elseif (strpos($line, '![') !== false && strpos($line, '](') !== false) {
            preg_match('/!\[(.*?)\]\((.*?)\)/', $line, $matches);
            if (count($matches) === 3) {
                $current_article['content'] .= "\n<img src=\"{$matches[2]}\" alt=\"{$matches[1]}\">\n";
            }
        } elseif (strpos($line, 'Category:') === 0) {
            $categories = explode(',', trim(substr($line, 9)));
            $current_article['categories'] = array_map('trim', $categories);
        } elseif (strpos($line, 'Tags:') === 0) {
            $tags = explode(',', trim(substr($line, 5)));
            $current_article['tags'] = array_map('trim', $tags);
        } elseif (!empty(trim($line))) {
            $current_article['content'] .= "\n<p>" . trim($line) . "</p>\n";
        }
    }

    if (!empty($current_article)) {
        $articles[] = $current_article;
    }

    return $articles;
}

function create_educational_post($article) {
    // Check if post already exists
    $existing_post = get_page_by_title($article['title'], OBJECT, 'educational');
    if ($existing_post) {
        echo "Article '{$article['title']}' already exists. Skipping...\n";
        return;
    }

    // Create post
    $post_data = [
        'post_title' => $article['title'],
        'post_content' => $article['content'],
        'post_status' => 'publish',
        'post_type' => 'educational',
        'post_author' => 1
    ];

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        echo "Error creating article '{$article['title']}': {$post_id->get_error_message()}\n";
        return;
    }

    // Set categories
    if (!empty($article['categories'])) {
        $category_ids = [];
        foreach ($article['categories'] as $category) {
            $term = term_exists($category, 'article_category');
            if (!$term) {
                $term = wp_insert_term($category, 'article_category');
            }
            if (!is_wp_error($term)) {
                $category_ids[] = $term['term_id'];
            }
        }
        wp_set_object_terms($post_id, $category_ids, 'article_category');
    }

    // Set tags
    if (!empty($article['tags'])) {
        $tag_ids = [];
        foreach ($article['tags'] as $tag) {
            $term = term_exists($tag, 'article_tag');
            if (!$term) {
                $term = wp_insert_term($tag, 'article_tag');
            }
            if (!is_wp_error($term)) {
                $tag_ids[] = $term['term_id'];
            }
        }
        wp_set_object_terms($post_id, $tag_ids, 'article_tag');
    }

    // Set meta data
    update_post_meta($post_id, '_reading_time', 5); // Default reading time
    update_post_meta($post_id, '_difficulty_level', 'beginner'); // Default difficulty level

    echo "Successfully created article: {$article['title']}\n";
}

// Run the import
import_educational_content(); 