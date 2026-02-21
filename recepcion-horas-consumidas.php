<?php
/*
Plugin Name: Automation Hours Viewer
Description: Displays hours from the Automation API.
Version: 1.10
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

    
    $output  = '<div class="automation-grid">';

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $hours = isset($hours_by_date[$date]) ? $hours_by_date[$date] : 0;

    // definir nivel de color
    if ($hours == 0) {
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

// Guardar en cache
set_transient('automation_hours_cache', $output, 5 * MINUTE_IN_SECONDS);

return $output;
}

add_shortcode('automation_hours', 'automation_hours_shortcode');

function automation_hours_styles() {
    echo '
    <style>
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
add_action('wp_head', 'automation_hours_styles');