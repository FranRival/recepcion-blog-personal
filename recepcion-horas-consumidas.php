<?php
/*
Plugin Name: Automation Hours Viewer
Description: Displays hours from the Automation API.
Version: 1.11.58
Author: Emmanuel
*/

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function() {
    if (isset($_GET['force_sync'])) {
        automation_sync_from_api();
        echo "Sync ejecutado";
        exit;
    }
});


function automation_hours_shortcode($atts) {

    global $wpdb;
    $table_name = $wpdb->prefix . 'automation_hours';

    $selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

    $atts = shortcode_atts(
        array(
            'year' => $selected_year,
        ),
        $atts
    );

    $year = intval($atts['year']);

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

    $total_hours = 0;

    foreach ($hours_by_date as $h) {
        $total_hours += $h;
    }

    $output = '';
    $current_year = date('Y');

    $output .= '<div class="automation-wrapper">';
    $output .= '<div class="automation-card">';
    $output .= '<div class="automation-header">';
    $output .= '<div class="automation-total-hours">';
    $output .= number_format($total_hours, 1) . ' automation hours';
    $output .= '</div>';
    $output .= '<div class="header-controls">';

    $output .= '<form method="GET" class="year-selector">';
    $output .= '<select name="year" onchange="this.form.submit()">';

    for ($y = $current_year; $y >= $current_year - 5; $y--) {
        $selected = ($y == $year) ? 'selected' : '';
        $output .= "<option value='{$y}' {$selected}>{$y}</option>";
    }

    $output .= '</select>';
    $output .= '</form>';

    $output .= '<a class="automation-stats-link" href="/estadisticas">Ver estadísticas</a>';

    $output .= '</div>';
    $output .= '</div>';

    /*
    ======================================================
    CONFIGURACIÓN CALENDARIO BASE (LÓGICA TIPO GITHUB)
    ======================================================
    */

    $year_start = new DateTime($year . '-01-01');
    $calendar_start = clone $year_start;
    $calendar_start->modify('monday this week');

    $year_end = new DateTime($year . '-12-31');
    $calendar_end = clone $year_end;
    $calendar_end->modify('sunday this week');
    $calendar_end->modify('+1 day');

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($calendar_start, $interval, $calendar_end);

    /*
    ======================================================
    MESES (ALINEACIÓN CORRECTA)
    ======================================================
    */

    $output .= '<div class="months-row">';

    foreach ($period as $date_obj) {

        if ($date_obj->format('j') == 1) {

            $diff_days = $calendar_start->diff($date_obj)->days;
            $column = floor($diff_days / 7) + 1;

            $output .= '<span class="month-label" style="grid-column:' . $column . ';">'
                . esc_html($date_obj->format('M')) .
                '</span>';
        }
    }

    $output .= '</div>';
    $output .= '<div class="automation-container">';

    /*
    ======================================================
    WEEKDAYS
    ======================================================
    */

    $output .= '<div class="weekdays">';
    $output .= '<span>Mon</span>';
    $output .= '<span>Tue</span>';
    $output .= '<span>Wed</span>';
    $output .= '<span>Thu</span>';
    $output .= '<span>Fri</span>';
    $output .= '<span>Sat</span>';
    $output .= '<span>Sun</span>';
    $output .= '</div>';

    /*
    ======================================================
    GRID
    ======================================================
    */

    $output .= '<div class="automation-grid">';

    $period = new DatePeriod($calendar_start, $interval, $calendar_end);

    foreach ($period as $date_obj) {

        $date = $date_obj->format('Y-m-d');

        // No pintar días fuera del año
        if ($date_obj < $year_start || $date_obj > $year_end) {
            $output .= '<div class="day level-0 empty"></div>';
            continue;
        }

        $hours = isset($hours_by_date[$date]) ? (float)$hours_by_date[$date] : 0.0;

        $max_hours = 23.3;

        if ($hours === 0.0) {
            $level = 'level-0';
        } else {

            $ratio = $hours / $max_hours;

            if ($ratio <= 0.25) {
                $level = 'level-1';
            } elseif ($ratio <= 0.5) {
                $level = 'level-2';
            } elseif ($ratio <= 0.75) {
                $level = 'level-3';
            } else {
                $level = 'level-4'; // NUEVO nivel máximo
            }
        }

        $output .= '<div class="day ' . esc_attr($level) . '" 
    data-date="' . esc_attr($date) . '" 
    data-hours="' . esc_attr($hours) . '"></div>';
    }

    $output .= '<div id="automation-tooltip"></div>';
    $output .= '</div>'; // grid
    $output .= '</div>'; // container

    $output .= '<div class="automation-legend">';
    $output .= '<span class="legend-text">Less</span>';
    $output .= '<span class="legend-box level-0"></span>';
    $output .= '<span class="legend-box level-1"></span>';
    $output .= '<span class="legend-box level-2"></span>';
    $output .= '<span class="legend-box level-3"></span>';
    $output .= '<span class="legend-box level-4"></span>';
    $output .= '<span class="legend-text">More</span>';
    $output .= '</div>';

    $output .= '</div>'; // card
    $output .= '</div>'; // wrapper

    return $output;
}

add_shortcode('automation_hours', 'automation_hours_shortcode');

add_action('wp_head', 'automation_hours_styles');

register_activation_hook(__FILE__, 'automation_schedule_daily_sync');

function automation_schedule_daily_sync() {
    if (!wp_next_scheduled('automation_daily_sync')) {
        wp_schedule_event(time(), 'hourly', 'automation_daily_sync');
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
    $api_url = 'https://api.emmanuelibarra.com/api/hours';

    $response = wp_remote_get($api_url, array(
        'timeout' => 20,
        'headers' => array(
            'x-api-key' => AUTOMATION_API_KEY
        )
    ));

    if (is_wp_error($response)) {
        error_log('WP ERROR: ' . $response->get_error_message());
        return;
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status !== 200) {
        error_log('HTTP STATUS: ' . $status);
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data) || !is_array($data)) {
        return;
    }

    foreach ($data as $item) {
        if (isset($item['date'], $item['hours'])) {

            $wpdb->replace(
                $table_name,
                array(
                    'date'  => $item['date'],
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

    .automation-header {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        margin-bottom: 12px;
    }

    .year-selector select {
        font-size: 11px;
        padding: 3px 6px;
        border-radius: 4px;
        border: 1px solid #ccc;
        background: #fff;
        cursor: pointer;
    }

    .automation-wrapper {
        width: 100%;
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
		margin-left: 48px;
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
    .level-4 { background: #0e4429; } /* verde más intenso */


    .header-controls {
        justify-self:center;
        display:flex;
        align-items:center;
        gap:10px;
    }

    .automation-stats-link {
        font-size:11px;
        color:#0969da;
        text-decoration:none;
    }

    .automation-stats-link:hover {
        text-decoration:underline;
    }

    .automation-card{
        border:1px solid #d0d7de;
        border-radius:6px;
        padding:16px;
        background:#ffffff;

        overflow-x:auto;
        overflow-y:hidden;  
    }

    .automation-wrapper::-webkit-scrollbar {
        height:8px;
    }

    .automation-wrapper::-webkit-scrollbar-thumb {
        background:#c9d1d9;
        border-radius:4px;
    }

    .automation-wrapper::-webkit-scrollbar-track {
        background:transparent;
    }

    .automation-legend{
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:4px;
        margin-top:8px;
        font-size:10px;
    }

    .legend-box{
        width:12px;
        height:12px;
        border-radius:2px;
    }

    .legend-text{
        margin:0 4px;
    }

    .automation-card{
        border:1px solid #d0d7de;
        border-radius:6px;
        padding:16px;
        background:#ffffff;
        max-width:100%;
    }
        .automation-total-hours{
        justify-self:start;
        font-size:13px;
        font-weight:600;
        color:#24292f;
    }

    #automation-tooltip{
        position:absolute;
        background:#24292f;
        color:#fff;
        font-size:11px;
        padding:6px 8px;
        border-radius:6px;
        pointer-events:none;
        opacity:0;
        transform:translate(-50%,-120%);
        white-space:nowrap;
        z-index:9999;
        transition:opacity .15s ease;
    }

    .day.active{
        position:relative;
        transform:scale(3);
        z-index:20;
        box-shadow:0 2px 6px rgba(0,0,0,.2);
    }

    .day.active::after{
        content:attr(data-date) " — " attr(data-hours) "h";
        position:absolute;
        top:18px;
        left:50%;
        transform:translateX(-50%);
        background:#24292f;
        color:#fff;
        font-size:10px;
        padding:4px 6px;
        border-radius:4px;
        white-space:nowrap;
    }

    </style>
    ';
}

add_action('wp_footer','automation_hours_script');

function automation_hours_script(){
?>

<script>

    document.addEventListener("DOMContentLoaded", function(){

        const days = document.querySelectorAll(".automation-grid .day");

        days.forEach(function(el){

            el.addEventListener("click", function(e){

                e.stopPropagation();

                days.forEach(d => d.classList.remove("active"));

                if(!this.dataset.date) return;

                this.classList.add("active");

            });

        });

        document.addEventListener("click", function(){
            days.forEach(d => d.classList.remove("active"));
        });

    });

</script>

<?php
}


/*
CAMBIOS
1. 
2. Enviar a una pagina de estadisticas. 
3. Mostrar la fecha y la cantidad de horas al colocar el curso sobre los cuadros. Informacion de: horas. Cantidad de Scripts trabajando. 
4. 
5. 
6. Cantidad de horas automatizadas en la esquina superior izqueirda. 
*/