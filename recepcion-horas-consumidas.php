<?php
/*
Plugin Name: Automation Hours Viewer
Description: Displays hours from the Automation API.
Version: 1.11.008
Author: Emmanuel
*/

if (!defined('ABSPATH')) {
    exit;
}

function automation_hours_shortcode() {

    global $wpdb;
    $table_name = $wpdb->prefix . 'automation_hours';

    $atts = shortcode_atts(
        array(
            'year' => date('Y'),
        ),
        $atts
    );

    $year = intval($atts['year']);

    // Obtener datos desde base de datos
    $results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT date, hours FROM $table_name WHERE YEAR(date) = %d",
        $year
    ),
    ARRAY_A
    );

    $hours_by_date = array();

    if (!empty($results)) {
        foreach ($results as $row) {
            $hours_by_date[$row['date']] = $row['hours'];
        }
    }

    if (empty($hours_by_date)) {
        return '<div class="automation-error">No hours data available.</div>';
    }

    $output  = '<div class="automation-wrapper">';
    $output .= '<div class="months-row">';

    $atts = shortcode_atts(
    array(
        'year' => date('Y'),
    ),
    $atts
    );

    
    $start = new DateTime($year . '-01-01');
    $end   = new DateTime($year . '-12-31');
    $end->modify('+1 day');

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $week_index = 0;
    $day_count = 0;

    // LOOP MESES
    foreach ($period as $date_obj) {

        $day_of_month = $date_obj->format('j');
        $month_label = $date_obj->format('M');

        if ($day_count % 7 == 0) {
            $week_index++;
        }

        if ($day_of_month == 1) {
            $output .= '<span class="month-label" style="grid-column:' . $week_index . ';">' . esc_html($month_label) . '</span>';
        }

        $day_count++;
    }

    $output .= '</div>';
    $output .= '<div class="automation-container">';
  
    $output .= '<div class="weekdays">';
    $output .= '<span>Mon</span>';
    $output .= '<span>Tue</span>';
    $output .= '<span>Wed</span>';
    $output .= '<span>Thu</span>';
    $output .= '<span>Fri</span>';
    $output .= '<span>Sat</span>';
    $output .= '<span>Sun</span>';
    $output .= '</div>';
    $output .= '<div class="automation-grid">';

     $first_day_of_year = (int)$start->format('N'); 
    // N = 1 (Mon) a 7 (Sun)

    for ($i = 1; $i < $first_day_of_year; $i++) {
        $output .= '<div class="day level-0 empty"></div>';
    }

    // Reiniciar periodo
    $period = new DatePeriod($start, $interval, $end);

    // LOOP DIAS
    foreach ($period as $date_obj){

        $date = $date_obj->format('Y-m-d');
        $hours = isset($hours_by_date[$date]) ? (float)$hours_by_date[$date] : 0.0;

        if ($hours === 0.0) {
            $level = 'level-0';
        } elseif ($hours < 2) {
            $level = 'level-1';
        } elseif ($hours < 4) {
            $level = 'level-2';
        } else {
            $level = 'level-3';
        }

        $output .= '<div class="day ' . esc_attr($level) . '" title="' . esc_attr($date . ' - ' . $hours . 'h') . '"></div>';
    }

    $output .= '</div>';
    $output .= '</div>';

    $output .= '</div>';
    return $output;
}

add_shortcode('automation_hours', 'automation_hours_shortcode');

add_action('wp_head', 'automation_hours_styles');

register_activation_hook(__FILE__, 'automation_schedule_daily_sync');

function automation_schedule_daily_sync() {
    if (!wp_next_scheduled('automation_daily_sync')) {
        wp_schedule_event(time(), 'daily', 'automation_daily_sync');
    }
}

add_action('automation_daily_sync', 'automation_sync_from_api');

register_activation_hook(__FILE__, 'automation_create_table');

function automation_create_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'automation_hours';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        date DATE NOT NULL,
        hours FLOAT NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY date (date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


function automation_sync_from_api() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'automation_hours';
    $api_url = 'https://100.53.230.237:3000/api/hours';

    $response = wp_remote_get($api_url, array(
        'timeout' => 20,
        'headers' => array(
            'x-api-key' => AUTOMATION_API_KEY
        )
    ));

    if (is_wp_error($response)) {
        return;
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        return;
    }

    $body = wp_remote_retrieve_body($response);
    error_log('API RESPONSE: ' . $body);
    $data = json_decode($body, true);

    if (empty($data) || !is_array($data)) {
        return;
    }

    foreach ($data as $item) {
        if (isset($item['date'], $item['hours'])) {

            $wpdb->replace(
                $table_name,
                array(
                    'date'  => sanitize_text_field($item['date']),
                    'hours' => floatval($item['hours'])
                ),
                array('%s', '%f')
            );
        }
    }

}


function automation_hours_styles() {
    echo '
    <style>

    .automation-wrapper {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }

    .automation-container {
        display: flex;
        gap: 8px;
        min-width: max-content;
    }

    .weekdays {
        display: grid;
        grid-template-rows: repeat(7, 14px);
        font-size: 10px;
        align-items: center;
    }

    .weekdays span {
        height: 14px;
        line-height: 14px;
    }

    .months-row {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: 14px;
        gap: 4px;
        margin-bottom: 6px;
        font-size: 10px;
        min-width: max-content;
    }

    .month-label {
        position: relative;
        transform: translateX(-4px);
        font-weight: bold;
        white-space: nowrap;
    }

    .automation-grid {
        display: grid;
        grid-template-rows: repeat(7, 14px);
        grid-auto-flow: column;
        gap: 4px;
        min-width: max-content;
    }
    
    .empty {
    visibility: hidden;
    }

    .day {
        width: 14px;
        height: 14px;
        border-radius: 3px;
    }

    .level-0 { background: #ebedf0; }
    .level-1 { background: #9be9a8; }
    .level-2 { background: #40c463; }
    .level-3 { background: #216e39; }

    </style>
    ';
}