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

    $api_url = 'http://localhost:3000/api/hours';
    $api_key = 'TU_API_KEY_AQUI';

    $response = wp_remote_get($api_url, [
        'headers' => [
            'x-api-key' => $api_key
        ]
    ]);

    if (is_wp_error($response)) {
        return 'Error connecting to API';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data)) {
        return 'No hours found.';
    }

    $output = '<ul>';

    foreach ($data as $row) {
        $output .= '<li>';
        $output .= 'Date: ' . esc_html($row['date']) . ' | ';
        $output .= 'Hours: ' . esc_html($row['hours']) . ' | ';
        $output .= 'Source: ' . esc_html($row['source']);
        $output .= '</li>';
    }

    $output .= '</ul>';

    return $output;
}

add_shortcode('automation_hours', 'automation_hours_shortcode');
