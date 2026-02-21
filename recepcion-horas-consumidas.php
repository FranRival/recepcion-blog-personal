<?php
/*
Plugin Name: Automation Hours Viewer
Description: Displays hours from the Automation API.
Version: 1.11.XX
Author: Emmanuel
*/

if (!defined('ABSPATH')) {
    exit;
}

function automation_hours_shortcode() {
    // 1️⃣ Intentar obtener datos cacheados
    $cached_data = get_transient('automation_hours_cache');

    if ($cached_data !== false) {
        return $cached_data;
    }

    //2️⃣ Si no hay cache, llamar API
    $api_url = 'https://overneatly-untarnished-lisa.ngrok-free.dev/api/hours';


	$response = wp_remote_get($api_url, array(
    'timeout' => 10,
    'headers' => array(
        'x-api-key' => AUTOMATION_API_KEY
    )
));

    if (is_wp_error($response)) {
        return '<div class="automation-error">Unable to retrieve hours at the moment.</div>';
    }

    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code !== 200) {
        return '<div class="automation-error">API returned error (' . esc_html($status_code) . ').</div>';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    
    $hours_by_date = [];

    if (!empty($data) && is_array($data)) {
        foreach ($data as $item) {
            if (isset($item['date'], $item['hours'])) {
                $hours_by_date[$item['date']] = $item['hours'];
            }
        }
    }


    if (empty($hours_by_date)) {
    return '<div class="automation-error">No hours data available.</div>';
}
    
    $output  = '<div class="automation-wrapper">';
    $output .= '<div class="months-row">';

    $year = date('Y');
    $start = new DateTime($year . '-01-01');
    $end   = new DateTime($year . '-12-31');
    $end->modify('+1 day'); // incluir el último día

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $week_index = 0;
    $day_count = 0;

    // ===========LOOP SOLO PARA MESES ====
    foreach ($period as $date_obj) {

    $day_of_month = $date_obj->format('j');
    $month_label = $date_obj->format('M');

    // Cada 7 días aumenta columna
    if ($day_count % 7 == 0) {
        $week_index++;
    }

    // Si es el primer día del mes
    if ($day_of_month == 1) {
        $output .= '<span class="month-label" style="grid-column:' . $week_index . ';">' . esc_html($month_label) . '</span>';
    }

    $day_count++;
}

$output .= '</div>';

// =========AHORA ABRIMOS GRID =====
$output .= '<div class="automation-grid">';

//Reiniciamos periodo porque ya lo consumimos
$period = new DatePeriod($start, $interval, $end);

// =====LOOP PARA DIAS ====
foreach ($period as $date_obj){
    $date = $date_obj->format('Y-m-d');
    $hours = isset($hours_by_date[$date]) ? $hours_by_date[$date] : 0;

    if ((float)$hours === 0.0) {
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

$output .= '</div>'; // cerrar automation-grid
$output .= '</div>'; // cerrar automation-wrapper


// Guardar en cache
set_transient('automation_hours_cache', $output, 5 * MINUTE_IN_SECONDS);

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
    // Aquí luego pondremos la llamada a AWS
}


function automation_hours_styles() {
    echo '
    <style>

    .automation-wrapper {
        display: inline-block;
    }

    .months-row {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: 14px;
        gap: 4px;
        margin-bottom: 6px;
        font-size: 10px;
    }

    .month-label {
        position: relative;
        transform: translateX(-4px);
        font-weight: bold;
    }

    .automation-grid {
        display: grid;
        grid-template-rows: repeat(7, 14px);
        grid-auto-flow: column;
        gap: 4px;
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