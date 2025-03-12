<?php
/**
 * Plugin Name: AI Search
 * Description: Replaces the default search with an intelligent search system.
 * Version: 1.4
 * Author: samuelsilvapt
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class AI_Search {

    /**
     * Singleton instance.
     *
     * @var AI_Search|null
     */
    private static $instance = null;

    /**
     * Plugin version.
     */
    const VERSION = '1.0';

    /**
     * OpenAI API Key.
     */
    private $api_key;

    /**
     * Threshold for similarity score.
     */
    private $similarity_threshold;

    /**
     * Initialize the plugin.
     */
    private function __construct() {
        $this->api_key = get_option( 'ai_search_api_key', '' );
        $this->similarity_threshold = get_option( 'ai_search_similarity_threshold', 0.5 );
        $this->register_hooks();
    }

    /**
     * Get the singleton instance.
     *
     * @return AI_Search
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register hooks.
     */
    private function register_hooks() {
        add_action( 'save_post', [ $this, 'generate_embedding' ] );
        add_filter( 'posts_request', [ $this, 'custom_search_query' ], 10, 2 );
        add_action( 'admin_menu', [ $this, 'register_settings_menu' ] );
        add_action( 'admin_post_ai_search_generate_embeddings', [ $this, 'generate_selected_embeddings' ] );

    }

    /**
     * Generate embedding for a post and save it to post meta.
     *
     * @param int $post_id Post ID.
     */
    public function generate_embedding( $post_id ) {
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        $post = get_post( $post_id );
        if ( 'publish' !== $post->post_status ) {
            return;
        }

        // Sanitize post title and content
        $post_title = sanitize_text_field( $post->post_title );
        $post_content = sanitize_textarea_field( $post->post_content );

        // Combine context with content for embedding
        $content = $post_title . ' ' . $post_content;
        $embedding = $this->get_embedding( $content );

        if ( $embedding ) {
            update_post_meta( $post_id, '_ai_search_embedding', wp_json_encode( $embedding ) );
        }
    }

    /**
     * Get embedding from OpenAI.
     *
     * @param string $content Content to embed.
     *
     * @return array|false Embedding data or false on failure.
     */
    private function get_embedding( $content ) {
        $content = sanitize_textarea_field( $content );
        // Check for Cached Embedding
        $cached_embedding = get_transient( 'ai_search_embedding_' . md5( $content ) );
        if ( $cached_embedding ) {
            return $cached_embedding;
        }
        $response = wp_remote_post( 'https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'input' => $content,
                'model' => 'text-embedding-ada-002',
            ]),
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
        if ( ! isset( $body['data'][0]['embedding'] ) ) {
            return false;
        }

        // Cache the embedding for 1 day
        set_transient( 'ai_search_embedding_' . md5( $content ), $body['data'][0]['embedding'], DAY_IN_SECONDS );
        return $body['data'][0]['embedding'];
    }

    /**
     * Customize the search query.
     *
     * @param string $sql Original SQL query.
     * @param WP_Query $query WP_Query object.
     *
     * @return string Modified SQL query.
     */
    public function custom_search_query( $sql, $query ) {
        if ( ! $query->is_search || is_admin() ) {
            return $sql;
        }

        $search_query = get_search_query();
        $search_query = sanitize_text_field( $search_query );

        $query_embedding = $this->get_embedding( $search_query );
        if ( ! $query_embedding ) {
            return $sql;
        }

        $posts = get_posts([
            'numberposts' => -1,
            'post_type'   => 'any',
            'post_status' => 'publish',
            'meta_key'    => '_ai_search_embedding',
        ]);

        $similarities = [];
        foreach ( $posts as $post ) {
            $embedding_json = get_post_meta( $post->ID, '_ai_search_embedding', true );
            if ( empty( $embedding_json ) ) {
                continue;
            }

            $embedding = json_decode( $embedding_json, true );
            $similarity = $this->calculate_similarity( $query_embedding, $embedding );
            if ( $similarity >= $this->similarity_threshold ) {
                $similarities[ $post->ID ] = $similarity;
            }
        }

        arsort( $similarities );

        $sorted_ids = array_keys( $similarities );

        if ( empty( $sorted_ids ) ) {
            return "SELECT * FROM {$GLOBALS['wpdb']->posts} WHERE 1=0"; // No results.
        }

        $ids = implode( ',', array_map( 'intval', $sorted_ids ) );
        return "SELECT * FROM {$GLOBALS['wpdb']->posts} WHERE ID IN ($ids) ORDER BY FIELD(ID, $ids)";
    }

    /**
     * Calculate cosine similarity.
     *
     * @param array $a First vector.
     * @param array $b Second vector.
     *
     * @return float Cosine similarity.
     */
    private function calculate_similarity( $a, $b ) {
        $dot_product = array_sum( array_map( function ( $x, $y ) {
            return $x * $y;
        }, $a, $b ) );

        $magnitude_a = sqrt( array_sum( array_map( function ( $x ) {
            return $x ** 2;
        }, $a ) ) );

        $magnitude_b = sqrt( array_sum( array_map( function ( $x ) {
            return $x ** 2;
        }, $b ) ) );

        return $dot_product / ( $magnitude_a * $magnitude_b );
    }

    /**
     * Register settings menu in admin.
     */
    public function register_settings_menu() {
        add_options_page(
            'AI Search Settings',
            'AI Search',
            'manage_options',
            'ai-search',
            [ $this, 'settings_page' ]
        );
    }

    /**
     * Generate embedding for selected custom post type.
     * @return void
     */
    public function generate_selected_embeddings() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['cpt'] ) ) {
            wp_die( __( 'Unauthorized access', 'ai-search' ) );
        }
        
        $cpt = sanitize_text_field( $_POST['cpt'] );
        
        $posts = get_posts([
            'numberposts' => 50,
            'post_type'   => $cpt,
            'post_status' => 'publish',
            'meta_query'  => [
                [
                    'key'     => '_ai_search_embedding',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        
        foreach ( $posts as $post ) {
            $this->generate_embedding( $post->ID );
        }
        
        wp_redirect( admin_url( 'options-general.php?page=ai-search&embeddings_generated=true&tab=embeddings' ) );
        exit;
    }

    /**
     * Display settings page.
     */
    /**
     * Display settings page with tabs.
     */
    public function settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        
        echo '<div class="wrap">';
        echo '<h1>AI Search Settings</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="?page=ai-search&tab=general" class="nav-tab ' . ( $active_tab == 'general' ? 'nav-tab-active' : '' ) . '">General Settings</a>';
        echo '<a href="?page=ai-search&tab=embeddings" class="nav-tab ' . ( $active_tab == 'embeddings' ? 'nav-tab-active' : '' ) . '">Generate Embeddings</a>';
        echo '</h2>';

        if ( $active_tab == 'general' ) {
            $this->general_settings_page();
        } else {
            $this->embeddings_settings_page();
        }
        
        echo '</div>';
    }



    /**
     * General settings tab.
     */
    private function general_settings_page() {
        if ( isset( $_POST['api_key'], $_POST['similarity_threshold'] ) ) {
            check_admin_referer( 'ai_search_save_settings' );
            update_option( 'ai_search_api_key', sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) );
            update_option( 'ai_search_similarity_threshold', floatval( $_POST['similarity_threshold'] ) );
            echo '<div class="updated"><p>Settings saved successfully!</p></div>';
        }

        $api_key = get_option( 'ai_search_api_key', '' );
        $similarity_threshold = get_option( 'ai_search_similarity_threshold', 0.5 );

        echo '<form method="post" action="">';
        wp_nonce_field( 'ai_search_save_settings' );
        echo '<label for="api_key">OpenAI API Key:</label>'; 
        echo '<input type="text" id="api_key" name="api_key" value="' . esc_attr( $api_key ) . '" style="width: 100%; max-width: 400px;" />';
        echo '<br/><br/>';
        echo '<label for="similarity_threshold">Similarity Threshold (0-1):</label>';
        echo '<input type="range" id="similarity_threshold" name="similarity_threshold" min="0" max="1" step="0.01" value="' . esc_attr( $similarity_threshold ) . '" oninput="this.nextElementSibling.value = this.value">';
        echo '<output>' . esc_attr( $similarity_threshold ) . '</output>';
        echo '<br/><br/>';
        echo '<input type="submit" class="button-primary" value="Save Settings" />';
        echo '</form>';
    }

    /**
     * Embeddings settings tab.
     */
    private function embeddings_settings_page() {
        if ( isset( $_GET['embeddings_generated'] ) ) {
            echo '<div class="updated"><p>Embeddings have been generated for selected posts!</p></div>';
        }

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        echo '<h2>Generate Embeddings for Selected CPT</h2>';
        echo '<p>Select a custom post type and click the button to generate embeddings for up to 50 posts that do not yet have them.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php?action=ai_search_generate_embeddings' ) ) . '">';
        echo '<select name="cpt">';
        foreach ( $post_types as $post_type ) {
            echo '<option value="' . esc_attr( $post_type->name ) . '">' . esc_html( $post_type->label ) . '</option>';
        }
        echo '</select>';
        echo '<br/><br/>';
        echo '<input type="submit" class="button button-secondary" value="Generate Embeddings" />';
        echo '</form>';
    }


}

// Initialize the plugin.
AI_Search::get_instance();
