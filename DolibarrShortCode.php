<?php

add_shortcode('check_dolibarr_status', function () {
    $transient_key = 'dolibarr_status';
    $last_check    = get_transient('dolibarr_status_time');

    if (isset($_GET['refresh_dolibarr'])) {
        delete_transient($transient_key);
        delete_transient('dolibarr_status_time');
    }

    $status = get_transient($transient_key);
    if ($status === false) {
        $data = DolibarrObject::fetchFromDolibarr('/status', 1, 1);
        $status = (is_array($data) || is_object($data)) ? 'ok' : 'ko';
        set_transient($transient_key, $status, HOUR_IN_SECONDS);
        set_transient('dolibarr_status_time', current_time('mysql'), HOUR_IN_SECONDS);
    }

    $last_check_display = $last_check ? " (dernier test à " . date('H:i', strtotime($last_check)) . ")" : "";
    $color = ($status === 'ok') ? 'green' : 'red';
    $label = ($status === 'ok') ? '🟢 Dolibarr : OK' : '🔴 Dolibarr : KO';
    $url = add_query_arg('refresh_dolibarr', '1');

    return <<<HTML
        <div style="margin:1em 0;">
            <a href="{$url}" style="display:inline-block;padding:10px 20px;background-color:{$color};color:white;border-radius:5px;text-decoration:none;">
                {$label}{$last_check_display}
            </a>
        </div>
    HTML;
});