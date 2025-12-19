<?php

/**
 * Coworking WC Order Complete
 */
/**
 * Coworking WC Order Complete
 */
/**
 * Coworking WC Order Complete
 */
/**
 * 6/7 — Création d'une réservation CPT quand la commande WooCommerce devient "completed"
 * Version : 1.0
 */

add_action('woocommerce_order_status_completed', 'cw_create_reservation_on_completed_order');

function cw_create_reservation_on_completed_order($order_id) {

    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($order->get_items() as $item_id => $item) {

        // Vérifier si c’est bien une réservation coworking
        $offre_id = intval($item->get_meta('_cw_offre_id'));
        if (!$offre_id) continue;

        $start    = $item->get_meta('_cw_start');
        $end      = $item->get_meta('_cw_end');
        $formule  = $item->get_meta('_cw_formule');
        $quantity = (int) ($item->get_meta('_cw_quantity') ?: 1);
        $price    = floatval($item->get_meta('_cw_price'));

        // Vérification minimale
        if (!$start || !$end) continue;

        // Créer un CPT "cw_reservation"
        $resa_post_id = wp_insert_post([
            'post_type'   => 'cw_reservation',
            'post_title'  => "Réservation #$order_id • Offre $offre_id",
            'post_status' => 'publish',
        ]);

        if (!$resa_post_id) continue;

        // Stockage des données utiles
        update_post_meta($resa_post_id, '_cw_offre_id', $offre_id);
        update_post_meta($resa_post_id, '_cw_offre_name', get_the_title($offre_id));
        update_post_meta($resa_post_id, '_cw_start', $start);
        update_post_meta($resa_post_id, '_cw_start_date', $start); // Compatibilité
        update_post_meta($resa_post_id, '_cw_end', $end);
        update_post_meta($resa_post_id, '_cw_end_date', $end); // Compatibilité
        update_post_meta($resa_post_id, '_cw_formule', $formule);
        update_post_meta($resa_post_id, '_cw_formula', $formule); // Compatibilité
        update_post_meta($resa_post_id, '_cw_quantity', $quantity);
        update_post_meta($resa_post_id, '_cw_price', $price);

        update_post_meta($resa_post_id, '_cw_order_id', $order_id);
        update_post_meta($resa_post_id, '_cw_customer_id', $order->get_customer_id());
        update_post_meta($resa_post_id, '_cw_customer_email', $order->get_billing_email());
        update_post_meta($resa_post_id, '_cw_customer_name', $order->get_formatted_billing_full_name());

        update_post_meta($resa_post_id, '_cw_created_at', time());

        // Mise à jour automatique du JSON dans l'offre
        cw_update_offer_json_after_reservation($offre_id, $start, $end, $formule, $order_id, $quantity);
    }
}


/**
 * Ajoute la réservation au JSON de l'offre
 */
function cw_update_offer_json_after_reservation($offre_id, $start, $end, $formule, $order_id, $quantity = 1) {

    $json = get_field('reservations_json', $offre_id);
    $arr  = json_decode($json, true);
    if (!is_array($arr)) $arr = [];

    $arr[] = [
        'start'    => $start,
        'end'      => $end,
        'formule'  => $formule,
        'quantity' => (int) $quantity,
        'order'    => intval($order_id)
    ];

    // Nettoyage du JSON
    if (function_exists('cw_clean_reservations_json_array')) {
        $arr = cw_clean_reservations_json_array($arr);
    }

    update_field('reservations_json', json_encode($arr, JSON_PRETTY_PRINT), $offre_id);
}
