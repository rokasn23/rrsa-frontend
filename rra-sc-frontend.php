<?php
/*
Plugin Name: RRA Sugar Calendar Frontend
Description: Adds a shortcode to add an event to Sugar Calendar events from frontend
Version: 1.1.0
Author: RN
*/

if (!defined('ABSPATH')) exit;

class SC_Frontend_Event_Plugin {

    public function __construct() {
        add_shortcode('sc_add_event', [$this, 'render_button']);
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

    public function render_button() {
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

                    <label>Category ID</label>
                    <input type="number" name="category" placeholder="Category ID">

                    <button type="submit">Create Event</button>
                </form>

                <div id="sc-response"></div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    public function create_event() {

        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $date = $_POST['date'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $category = intval($_POST['category']);

        $start_datetime = strtotime($date . ' ' . $start);
        $end_datetime   = strtotime($date . ' ' . $end);

        $event_data = [
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'publish',
            'post_type'    => 'sc_event'
        ];

        $event_id = wp_insert_post($event_data);

        if (!$event_id) {
            wp_send_json_error('Event creation failed');
        }

        // Sugar Calendar meta
        update_post_meta($event_id, 'start', $start_datetime);
        update_post_meta($event_id, 'end', $end_datetime);
        update_post_meta($event_id, 'all_day', 0);

        if ($category) {
            wp_set_object_terms($event_id, [$category], 'sc_event_category');
        }

        wp_send_json_success('Event created');
    }
}

new SC_Frontend_Event_Plugin();