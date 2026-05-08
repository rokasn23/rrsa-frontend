<?php
/*
Plugin Name: RRSA frontend
Description: Adds a shortcode to add an event to My Calendar (by Joe Dolson) plugin trough website's frontend
Version: 1.3.0
Author: RN
*/


// TODO make this plugin check if my calendar exists (database table wp_my_calendar_events exists)
// and write proper comments

if (!defined('ABSPATH')) exit;

class RRSA_Frontend_Event_Plugin {

    public function __construct() {
        add_shortcode('rrsa_add_event', [$this, 'render_button']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_rrsa_create_event', [$this, 'create_event']);
        add_action('wp_ajax_nopriv_rrsa_create_event', [$this, 'create_event']);
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'rrsa-frontend-css',
            plugin_dir_url(__FILE__) . 'assets/css/frontend.css'
        );

        wp_enqueue_script(
            'rrsa-frontend-js',
            plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script('rrsa-frontend-js', 'RRSAFrontend', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }

    private function get_filtered_categories() {
        // this is to make unwanted categories not show up
        $terms = get_terms([
            'taxonomy' => 'mc-event-category',
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

    private function get_recipients() {
        $json_path = plugin_dir_path(__FILE__) . 'config/email-recipient.json';

        if (file_exists($json_path)) {
            $json = json_decode(file_get_contents($json_path), true);
            if (!empty($json['recipient'])) {
                $recipients = $json['recipient'];
            }
        }

        return $recipients;
    }

    public function render_button() {
        // this makes a shortcode that creates a button for an input form
        $categories = $this->get_filtered_categories();

        ob_start();
        ?>

        <button id="rrsa-open-modal" class="rrsa-add-event-btn">Add Event</button>

        <div id="rrsa-modal" class="rrsa-modal">
            <div class="rrsa-modal-content">
                <span class="rrsa-close">&times;</span>

                <h3>Add Event</h3>

                <form id="rrsa-event-form">
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

                <div id="rrsa-response"></div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    // this entire function has a problem that it doesnt update calendars that you can view in website, 
    // to see changes you must refresh events tab in admin panel
    public function create_event() {

        if ( ! function_exists( 'my_calendar_save' ) ) {
            return new WP_Error( 'my_calendar_missing', 'My Calendar plugin is not active.' );
        }

        $title       = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content     = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $date        = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $start_time  = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time    = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
        $calendar_id = isset($_POST['category']) ? intval($_POST['category']) : 1;

        if (!$title || !$date || !$start_time || !$end_time || !$calendar_id) {
            return false;
        }
        
        global $wpdb;

        if($calendar_id != 1) {
            $term = get_term($calendar_id);

            if ( ! $term || is_wp_error( $term ) ) {
                return new WP_Error( 'invalid_term', 'Invalid taxonomy term.' );
            }
            
            $mc_category_id = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT category_id
                    FROM {$wpdb->prefix}my_calendar_categories
                    WHERE category_name = %s
                    LIMIT 1
                    ",
                    $term->name //reusing this
                )
            );

            if ( ! $mc_category_id ) {
                return new WP_Error( 'missing_mc_category', 'No matching My Calendar category found.' );
            }

            $calendar_id = $mc_category_id;
        }
        
        $event = array(
            'event_title'       => $title,
            'event_desc'        => $content,
            'event_begin'       => $date,
            'event_end'         => $date,
            'event_time'        => $start_time,
            'event_endtime'     => $end_time,
            'event_category'    => $calendar_id,

            // to prevent invalid event
            'event_recur'       => "S1",
            'event_repeats'     => "",
            'event_author'      => 1,
            'event_host'        => 1,
            'event_link'        => "",
            'event_access'      => "",
            'event_image'       => ""
        );

        /*
        * my_calendar_save() expects:
        * array($valid,
        *   $event,
        *   $raw_event,   <- can be $event in this situation
        *   $message
        * )
        */
        $data = array(true, $event, $event, '');
        $result = my_calendar_save( 'add', $data );
        $this->send_email_after_adding_event($result["event_id"], $term->name);
        
        wp_send_json_success('Event created');

    }
    //sends a formatted email using
    public function send_email_after_adding_event($event_id, $category){

        $new_event_link = strval($_SERVER['HTTP_HOST']) . "/wp-admin/admin.php?page=my-calendar&mode=edit&event_id=" . strval($event_id);

        // $to = $this->get_recipients();
        $to = "example@example.com";
        $subject = 'Įkeltas naujas įvykis į kalendorių';
        $message = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
            </head>
            <body>
                <p>Įkeltas naujas įvykis į kalendorių: '. $category . '</p>

                <p>
                    <a href="'. $new_event_link . '" target="_blank">
                        test2
                    </a>
                </p>

                <p style="font-size: 8px;">
                    '. current_datetime() .' 
                </p>
            </body>
            </html>
            ';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail( $to, $subject, $message, $headers );
    }
}
    

new RRSA_Frontend_Event_Plugin();