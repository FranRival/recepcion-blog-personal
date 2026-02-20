<?php
/*
Plugin Name: Automation Hours Viewer
Description: Displays hours from the Automation API.
Version: 1.0
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

    // Si la API devuelve un array de resultados, tomar el primero
    if (isset($data[0])) {
        $data = $data[0];
    }

    if (empty($data) || !isset($data['date'], $data['hours'], $data['source'])) {
        return '<div class="automation-error">No hours data available.</div>';
    }

    $date   = esc_html($data['date']);
    $hours  = esc_html($data['hours']);
    $source = esc_html($data['source']);

    $output  = '<div class="automation-hours">';
    $output .= '<div class="automation-date"><strong>Date:</strong> ' . $date . '</div>';
    $output .= '<div class="automation-hours-value"><strong>Hours:</strong> ' . $hours . '</div>';
    $output .= '<div class="automation-source"><strong>Source:</strong> ' . $source . '</div>';
    $output .= '</div>';

    
    // 3️⃣ Guardar en cache por 5 minutos
    set_transient('automation_hours_cache', $output, 5 * MINUTE_IN_SECONDS);

    return $output;
}

add_shortcode('automation_hours', 'automation_hours_shortcode');