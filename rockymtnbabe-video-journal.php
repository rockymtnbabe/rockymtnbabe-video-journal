<?php
/**
 * Plugin Name: RockyMtnBabe Video Journal
 * Description: Lightweight R2-backed video catalog and Elementor gallery shortcode.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: rmb-video-journal
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class RMB_Video_Journal {
    const VERSION = '0.1.0';
    const POST_TYPE = 'rmb_video';
    const TAXONOMY = 'rmb_video_category';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_content' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_fields' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
        add_shortcode( 'rmb_video_gallery', array( __CLASS__, 'gallery_shortcode' ) );
    }

    public static function activate() {
        self::register_content();
        flush_rewrite_rules();
    }
    public static function deactivate() { flush_rewrite_rules(); }

    public static function register_content() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array(
                'name' => 'Videos', 'singular_name' => 'Video',
                'add_new_item' => 'Add New Video', 'edit_item' => 'Edit Video',
                'menu_name' => 'Video Journal', 'all_items' => 'All Videos'
            ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'show_in_rest' => true, 'menu_icon' => 'dashicons-video-alt3',
            'supports' => array( 'title', 'thumbnail', 'editor' ),
            'capability_type' => 'post', 'map_meta_cap' => true,
            'exclude_from_search' => true, 'has_archive' => false,
            'rewrite' => false,
        ) );
        register_taxonomy( self::TAXONOMY, self::POST_TYPE, array(
            'labels' => array( 'name' => 'Video Categories', 'singular_name' => 'Video Category' ),
            'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true,
            'show_in_rest' => true, 'public' => false, 'rewrite' => false,
        ) );
        add_post_type_support( self::POST_TYPE, 'thumbnail' );
    }

    public static function add_meta_boxes() {
        add_meta_box( 'rmb_video_details', 'Video Details', array( __CLASS__, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'rmb_save_video', 'rmb_video_nonce' );
        $url = get_post_meta( $post->ID, '_rmb_video_url', true );
        $date = get_post_meta( $post->ID, '_rmb_video_date', true );
        echo '<p><label for="rmb_video_url"><strong>Cloudflare R2 MP4 URL</strong></label></p>';
        echo '<input type="url" id="rmb_video_url" name="rmb_video_url" value="' . esc_attr( $url ) . '" class="widefat" placeholder="https://videos.rockymtnbabe.com/fashion/example.mp4" />';
        echo '<p class="description">Only HTTPS MP4 files on videos.rockymtnbabe.com are accepted. Store videos in R2, not the WordPress Media Library.</p>';
        echo '<p><label for="rmb_video_date"><strong>Journal date</strong></label></p>';
        echo '<input type="date" id="rmb_video_date" name="rmb_video_date" value="' . esc_attr( $date ) . '" />';
        echo '<p class="description">YYYY-MM-DD. Used for gallery sorting and the visible date. Add a Featured Image for the portrait thumbnail and assign a Video Category.</p>';
    }

    public static function valid_url( $url ) {
        $url = esc_url_raw( trim( $url ) );
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || strtolower( $parts['host'] ?? '' ) !== 'videos.rockymtnbabe.com' ) { return ''; }
        if ( ! preg_match( '/\.mp4$/i', $parts['path'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return ''; }
        return $url;
    }

    public static function save_fields( $post_id ) {
        if ( ! isset( $_POST['rmb_video_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rmb_video_nonce'] ) ), 'rmb_save_video' ) ) { return; }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
        if ( isset( $_POST['rmb_video_url'] ) ) {
            $url = self::valid_url( wp_unslash( $_POST['rmb_video_url'] ) );
            if ( $url ) { update_post_meta( $post_id, '_rmb_video_url', $url ); }
            else { delete_post_meta( $post_id, '_rmb_video_url' ); }
        }
        if ( isset( $_POST['rmb_video_date'] ) ) {
            $date = sanitize_text_field( wp_unslash( $_POST['rmb_video_date'] ) );
            if ( self::valid_date( $date ) ) { update_post_meta( $post_id, '_rmb_video_date', $date ); }
            else { delete_post_meta( $post_id, '_rmb_video_date' ); }
        }
    }

    private static function valid_date( $date ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { return false; }
        $parts = explode( '-', $date );
        return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
    }

    public static function admin_assets( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== self::POST_TYPE ) { return; }
        // Uses WordPress's standard featured image/media controls; no external admin dependencies.
    }

    public static function gallery_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'limit' => '30', 'category' => '', 'month' => '' ), $atts, 'rmb_video_gallery' );
        $limit = max( 1, min( 100, absint( $atts['limit'] ) ) );
        $month = sanitize_text_field( (string) $atts['month'] );
        if ( $month !== '' && ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) { return ''; }
        $category = sanitize_title( (string) $atts['category'] );
        $tax_query = array();
        if ( $category !== '' ) {
            $tax_query[] = array( 'taxonomy' => self::TAXONOMY, 'field' => 'slug', 'terms' => $category );
        }
        $query_args = array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish',
            'posts_per_page' => -1, 'no_found_rows' => true,
        );
        if ( $tax_query ) { $query_args['tax_query'] = $tax_query; }
        // Catalog date is a separate editorial field; sorting is done below to cover missing dates safely.
        $posts = get_posts( $query_args );
        $videos = array();
        foreach ( $posts as $post ) {
            $url = self::valid_url( get_post_meta( $post->ID, '_rmb_video_url', true ) );
            $date = get_post_meta( $post->ID, '_rmb_video_date', true );
            $date = self::valid_date( $date ) ? $date : get_the_date( 'Y-m-d', $post );
            $thumb_id = get_post_thumbnail_id( $post->ID );
            if ( ! $url || ! $thumb_id || ( $month && substr( $date, 0, 7 ) !== $month ) ) { continue; }
            $videos[] = array( 'post' => $post, 'url' => $url, 'date' => $date, 'thumb_id' => $thumb_id );
        }
        usort( $videos, static function( $a, $b ) {
            $compare = strcmp( $b['date'], $a['date'] );
            return $compare ?: ( $b['post']->ID <=> $a['post']->ID );
        } );
        $videos = array_slice( $videos, 0, $limit );
        if ( ! $videos ) { return current_user_can( 'edit_posts' ) ? '<p>No published videos with valid R2 URL and Featured Image found.</p>' : ''; }
        wp_enqueue_style( 'rmb-video-journal', plugin_dir_url( __FILE__ ) . 'assets/css/video-journal.css', array(), self::VERSION );
        wp_enqueue_script( 'rmb-video-journal', plugin_dir_url( __FILE__ ) . 'assets/js/video-lightbox.js', array(), self::VERSION, true );
        $id = wp_unique_id( 'rmb-video-gallery-' );
        ob_start();
        echo '<div class="rmb-video-grid" id="' . esc_attr( $id ) . '">';
        foreach ( $videos as $video ) {
            $title = get_the_title( $video['post'] );
            $date_string = wp_date( 'M j, Y', strtotime( $video['date'] . ' 12:00:00' ) );
            echo '<article class="rmb-video-card">';
            echo '<button type="button" class="rmb-video-thumb" data-video="' . esc_url( $video['url'] ) . '" aria-label="' . esc_attr( 'Play ' . $title ) . '">';
            echo wp_get_attachment_image( $video['thumb_id'], 'large', false, array( 'alt' => $title, 'loading' => 'lazy' ) );
            echo '<span class="rmb-video-brand" aria-hidden="true">ROCKYMTNBABE</span>';
            echo '<span class="rmb-video-date" aria-hidden="true">' . esc_html( $date_string ) . '</span>';
            echo '<span class="rmb-video-play" aria-hidden="true">▶</span></button>';
            echo '<div class="rmb-video-caption"><span class="rmb-video-caption-date">' . esc_html( $date_string ) . '</span>';
            echo '<button type="button" class="rmb-video-title" data-video="' . esc_url( $video['url'] ) . '">' . esc_html( $title ) . '</button></div>';
            echo '</article>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
RMB_Video_Journal::init();
register_activation_hook( __FILE__, array( 'RMB_Video_Journal', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RMB_Video_Journal', 'deactivate' ) );
