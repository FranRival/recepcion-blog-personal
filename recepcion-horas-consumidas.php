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

    $api_url = 'https://overneatly-untarnished-lisa.ngrok-free.dev/api/hours';


	$response = wp_remote_get($api_url, array(
    'headers' => array(
        'x-api-key' => AUTOMATION_API_KEY
    )
));

    if (is_wp_error($response)) {
        return '<div class="automation-error">Unable to retrieve hours at the moment.</div>';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Si la API devuelve un array de resultados, tomar el primero
    if (isset($data[0])) {
        $data = $data[0];
    }

    return '<pre>' . print_r($data, true) . '</pre>';

    $date   = esc_html($data['date']);
    $hours  = esc_html($data['hours']);
    $source = esc_html($data['source']);

    $output  = '<div class="automation-hours">';
    $output .= '<div class="automation-date"><strong>Date:</strong> ' . $date . '</div>';
    $output .= '<div class="automation-hours-value"><strong>Hours:</strong> ' . $hours . '</div>';
    $output .= '<div class="automation-source"><strong>Source:</strong> ' . $source . '</div>';
    $output .= '</div>';

    return $output;
}

add_shortcode('automation_hours', 'automation_hours_shortcode');