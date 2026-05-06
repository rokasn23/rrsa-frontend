<?php
/*
Plugin Name: RRSA Sugar Calendar Frontend
Description: Adds a shortcode to add an event to Sugar Calendar events from frontend
Version: 1.2.0
Author: RN
*/


// TODO make this plugin check if sugar calendar lite exists (database table wp_sc_events exists)
// and write proper comments

if (!defined('ABSPATH')) exit;

class RRSA_SC_Frontend_Event_Plugin {

    public function __construct() {
        add_shortcode('rrsa_sc_add_event', [$this, 'render_button']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_sc_create_event', [$this, 'create_event']);
        add_action('wp_ajax_nopriv_sc_create_event', [$this, 'create_event']);
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'sc-frontend-css',
            plugin_dir_url(__FILE__) . 'assets/css/frontend.css'
        );

        wp_enqueue_script(
            'sc-frontend-js',
            plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script('sc-frontend-js', 'SCFrontend', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    private function get_filtered_categories() {
        // this is to make unwanted categories not show up
        $terms = get_terms([
            'taxonomy' => 'sc_event_category',
            'hide_empty' => false
        ]);

        $exclude = [];

        $json_path = plugin_dir_path(__FILE__) . 'config/category-filter.json';

        if (file_exists($json_path)) {
            $json = json_decode(file_get_contents($json_path), true);
            if (!empty($json['exclude'])) {
                $exclude = $json['exclude'];
            }
        }

        $filtered = [];

        foreach ($terms as $term) {
            if (!in_array($term->name, $exclude)) {
                $filtered[] = $term;
            }
        }
        return $filtered;
    }
 
    public function render_button() {
        // this makes a shortcode that creates a button for an input form
        $categories = $this->get_filtered_categories();

        ob_start();
        ?>

        <button id="sc-open-modal" class="sc-add-event-btn">Add Event</button>

        <div id="sc-modal" class="sc-modal">
            <div class="sc-modal-content">
                <span class="sc-close">&times;</span>

                <h3>Add Event</h3>

                <form id="sc-event-form">
                    <input type="text" name="title" placeholder="Event name" required>

                    <textarea name="description" placeholder="Description"></textarea>

                    <label>Date</label>
                    <input type="date" name="date" required>

                    <label>Start Time</label>
                    <input type="time" name="start_time" required>

                    <label>End Time</label>
                    <input type="time" name="end_time" required>

                    <label>Category</label>
                    <select name="category" required>
                        <option value="">Select category</option>

                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>">
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                    <button type="submit">Create Event</button>
                </form>

                <div id="sc-response"></div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    public function create_event() {

        $title       = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content     = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $date        = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $start_time  = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time    = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
        $calendar_id = isset($_POST['category']) ? intval($_POST['category']) : 0;

        if (!$title || !$date || !$start_time || !$end_time || !$calendar_id) {
            return false;
        }
        //TODO check if this can be remade
        // builds datetime strings (Sugar Calendar Lite stores timestamps)
        $start_datetime = strtotime($date . ' ' . $start_time);
        $end_datetime   = strtotime($date . ' ' . $end_time);

        // 1. we create event as normal wp post
        // 2. then connect taxonomy (assigns calendar)
        // 3. then insert event to sugar calendar events db (easier than untangling internal functions which did not cooperate)
        // 1.
        $event_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'sc_event',
        ] );

        if ( is_wp_error( $event_id ) || ! $event_id ) {
            wp_send_json_error('Event creation failed');
            return false;
        }
        // 2.
        if ( ! empty( $calendar_id ) ) {
            wp_set_object_terms(
                $event_id,
                (int) $calendar_id,
                'sc_event_category'
            );
        }
        // 3.
        $start_dt = date( 'Y-m-d H:i:s', $start_datetime );
        $end_dt   = date( 'Y-m-d H:i:s', $end_datetime );

        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'sc_events',
            [
                'object_id'   => $event_id,
                'object_type' => 'post',
                'object_subtype' => 'sc_event',
                'title'       => $title,
                'content'     => $content,
                'status'      => 'publish',
                'start'       => $start_dt,
                'end'         => $end_dt,
                'all_day'     => 0,
                'date_created'  => current_time( 'mysql' ),
                'date_modified' => current_time( 'mysql' ),
                'uuid'        => "urn:uuid:" . strval(wp_generate_uuid4()),
            ],
            [
                '%d', // object_id
                '%s', // object_type
                '%s', // object_subtype
                '%s', // title
                '%s', // content
                '%s', // status
                '%s', // start
                '%s', // end
                '%d', // all_day
                '%s', // date_created
                '%s', // date_modified
                '%s', // uuid
            ]
        );

        wp_send_json_success('Event created');
        header("Refresh:0");
    }
}

new RRSA_SC_Frontend_Event_Plugin();