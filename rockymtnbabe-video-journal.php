<?php
/**
 * Plugin Name: RockyMtnBabe Video Journal
 * Description: Lightweight R2-backed video catalog and Elementor gallery shortcode.
 * Version: 0.1.3
 * Requires PHP: 7.4
 * Text Domain: rmb-video-journal
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class RMB_Video_Journal {
    const VERSION = '0.1.3';
    const POST_TYPE = 'rmb_video';
    const TAXONOMY = 'rmb_video_category';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_content' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_fields' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
        add_shortcode( 'rmb_video_gallery', array( __CLASS__, 'gallery_shortcode' ) );
        // Enqueue styles before the document head is printed, including in Elementor preview.
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_gallery_styles' ) );
        add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue_gallery_styles' ) );
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

    /**
     * Render a category-driven R2 path. The full URL remains stored in the original
     * _rmb_video_url metadata field for backwards compatibility with existing videos.
     */
    public static function render_meta_box( $post ) {
        wp_nonce_field( 'rmb_save_video', 'rmb_video_nonce' );
        $url  = (string) get_post_meta( $post->ID, '_rmb_video_url', true );
        $date = (string) get_post_meta( $post->ID, '_rmb_video_date', true );
        $folder = '';
        $filename = '';
        if ( self::valid_url( $url ) ) {
            $path = ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
            $segments = explode( '/', $path );
            if ( count( $segments ) === 2 ) {
                $folder = sanitize_title( $segments[0] );
                $filename = $segments[1];
            }
        }
        if ( $folder === '' ) {
            $assigned = get_the_terms( $post->ID, self::TAXONOMY );
            if ( is_array( $assigned ) && ! is_wp_error( $assigned ) ) {
                $folder = $assigned[0]->slug;
            }
        }
        $terms = get_terms( array( 'taxonomy' => self::TAXONOMY, 'hide_empty' => false ) );
        if ( is_wp_error( $terms ) ) { $terms = array(); }

        echo '<p><strong>Cloudflare R2 Video</strong></p>';
        echo '<p><label for="rmb_video_folder"><strong>Category / R2 folder</strong></label></p>';
        echo '<select id="rmb_video_folder" name="rmb_video_folder" class="widefat">';
        echo '<option value="">Select a video category...</option>';
        $found_folder = false;
        foreach ( $terms as $term ) {
            if ( $term->slug === $folder ) { $found_folder = true; }
            echo '<option value="' . esc_attr( $term->slug ) . '" ' . selected( $folder, $term->slug, false ) . '>' . esc_html( $term->name ) . ' (' . esc_html( $term->slug ) . '/)</option>';
        }
        // Do not lose the folder of a legacy URL if its category was deleted.
        if ( $folder !== '' && ! $found_folder ) {
            echo '<option value="' . esc_attr( $folder ) . '" selected>' . esc_html( $folder ) . '/ (existing folder; category missing)</option>';
        }
        echo '</select>';
        echo '<p class="description">Choose the R2 folder here. This also assigns the matching Video Category when you save. To add a category, use Video Journal → Video Categories, then reopen this editor.</p>';
        echo '<p><label for="rmb_video_filename"><strong>MP4 filename</strong></label></p>';
        echo '<input type="text" id="rmb_video_filename" name="rmb_video_filename" value="' . esc_attr( $filename ) . '" class="widefat" placeholder="2026-10-29_fashion_pink-latex-mini.mp4" spellcheck="false" autocomplete="off" />';
        echo '<p class="description">Enter only the filename, including .mp4. No spaces or slashes.</p>';
        echo '<p><strong>Generated URL (preview)</strong></p>';
        echo '<p id="rmb_video_url_preview" style="overflow-wrap:anywhere;padding:10px;background:#f6f7f7;border:1px solid #dcdcde" aria-live="polite">' . esc_html( $url ? $url : 'https://videos.rockymtnbabe.com/' ) . '</p>';
        echo '<p class="description">Base address: https://videos.rockymtnbabe.com/ — WordPress stores the completed URL; the file itself must already exist in R2.</p>';
        echo '<p><label for="rmb_video_date"><strong>Journal date</strong></label></p>';
        echo '<input type="date" id="rmb_video_date" name="rmb_video_date" value="' . esc_attr( $date ) . '" />';
        echo '<p class="description">YYYY-MM-DD. Used for gallery sorting and visible date. Add a Featured Image for the portrait thumbnail.</p>';
    }

    private static function valid_folder( $folder ) {
        return is_string( $folder ) && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $folder );
    }

    private static function valid_filename( $filename ) {
        return is_string( $filename ) && preg_match( '/^[a-z0-9][a-z0-9_-]*\.mp4$/i', $filename );
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
        // Keep the original _rmb_video_url field so existing catalog entries and
        // the front-end shortcode require no migration.
        if ( isset( $_POST['rmb_video_folder'], $_POST['rmb_video_filename'] ) ) {
            $folder = sanitize_title( wp_unslash( $_POST['rmb_video_folder'] ) );
            $filename = trim( sanitize_text_field( wp_unslash( $_POST['rmb_video_filename'] ) ) );
            if ( self::valid_folder( $folder ) && self::valid_filename( $filename ) ) {
                $url = self::valid_url( 'https://videos.rockymtnbabe.com/' . $folder . '/' . $filename );
                if ( $url ) {
                    update_post_meta( $post_id, '_rmb_video_url', $url );
                    $term = get_term_by( 'slug', $folder, self::TAXONOMY );
                    if ( $term && ! is_wp_error( $term ) ) {
                        wp_set_object_terms( $post_id, array( (int) $term->term_id ), self::TAXONOMY, false );
                    }
                }
            } elseif ( $folder === '' && $filename === '' ) {
                // An intentionally cleared field removes the R2 link.
                delete_post_meta( $post_id, '_rmb_video_url' );
            }
            // Incomplete or invalid inputs do not destroy a previously saved URL.
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
        wp_enqueue_script(
            'rmb-video-journal-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/video-admin.js',
            array(),
            self::VERSION,
            true
        );
    }

    /**
     * Register and enqueue gallery CSS early. Elementor can render shortcode content
     * after wp_head, at which point enqueueing for the first time is too late.
     * Enqueueing this small, scoped stylesheet up front fixes editor preview.
     */
    public static function enqueue_gallery_styles() {
        wp_enqueue_style(
            'rmb-video-journal',
            plugin_dir_url( __FILE__ ) . 'assets/css/video-journal.css',
            array(),
            self::VERSION
        );
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
        wp_enqueue_script( 'rmb-video-journal', plugin_dir_url( __FILE__ ) . 'assets/js/video-lightbox.js', array(), self::VERSION, true );
        $id = wp_unique_id( 'rmb-video-gallery-' );
        ob_start();
        echo '<div class="rmb-video-grid" id="' . esc_attr( $id ) . '">';
        foreach ( $videos as $video ) {
            $title = get_the_title( $video['post'] );
            $date_string = wp_date( 'M j, Y', strtotime( $video['date'] . ' 12:00:00' ) );
            echo '<article class="rmb-video-card">';
            echo '<button type="button" class="rmb-video-thumb" data-video="' . esc_url( $video['url'] ) . '" aria-label="' . esc_attr( 'Play ' . $title ) . '">';
            echo wp_get_attachment_image( $video['thumb_id'], 'full', false, array( 'alt' => $title, 'loading' => 'lazy' ) );
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
