<?php

/**
 * =============================================================================
 * COWORKING JSON TOOLS - UTILITAIRES DE MANIPULATION JSON
 * =============================================================================
 *
 * Outils sécurisés pour manipuler le champ ACF 'reservations_json'.
 * Ce champ stocke les réservations confirmées de chaque offre au format JSON
 * pour une lecture rapide par le calendrier (évite les requêtes CPT coûteuses).
 *
 * FONCTIONNALITÉS :
 * - Nettoyage et validation du JSON (suppression entrées corrompues)
 * - Reconstruction du JSON depuis WooCommerce (source de vérité)
 * - Endpoint REST sécurisé pour rebuild manuel (admin only)
 *
 * STRUCTURE DU JSON :
 * [
 *   {
 *     "start": "2025-01-15",        // Date début (YYYY-MM-DD)
 *     "end": "2025-01-20",          // Date fin (YYYY-MM-DD)
 *     "formule": "semaine",         // Type de formule
 *     "quantity": 1,                // Nombre de places réservées
 *     "order": 12345                // ID de la commande WooCommerce
 *   },
 *   ...
 * ]
 *
 * SÉCURITÉ :
 * - Aucune clé secrète en dur (utilisation des permissions WordPress)
 * - Vérification current_user_can('manage_options') pour les endpoints admin
 * - Validation regex sur les formats de date
 *
 * @package    SkyLounge_Coworking
 * @subpackage JSON_Tools
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    2.0.0 Sécurisation des endpoints
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : NETTOYAGE DU JSON
   =============================================================================
   Fonction de validation et nettoyage des entrées JSON.
   Utilisée après chaque modification pour garantir l'intégrité des données.
============================================================================= */

/**
 * Nettoie et valide un tableau de réservations JSON.
 *
 * Cette fonction :
 * - Supprime les entrées non-tableaux
 * - Vérifie le format des dates (YYYY-MM-DD)
 * - S'assure que les champs requis sont présents
 * - Normalise les quantités (minimum 1)
 * - Ré-indexe le tableau
 *
 * @since 1.0.0
 *
 * @param array $arr Le tableau de réservations à nettoyer.
 *
 * @return array Le tableau nettoyé avec uniquement les entrées valides.
 *
 * @example
 * $dirty = [
 *     ['start' => '2025-01-15', 'end' => '2025-01-20', 'formule' => 'semaine'],
 *     ['start' => 'invalid', 'end' => '2025-01-20'],  // Sera supprimé
 *     null,  // Sera supprimé
 * ];
 * $clean = cw_clean_reservations_json_array($dirty);
 * // Retourne uniquement la première entrée
 */
function cw_clean_reservations_json_array($arr) {
    // Si l'entrée n'est pas un tableau, retourner un tableau vide
    if (!is_array($arr)) return [];

    $clean = [];
    $date_regex = '/^\d{4}-\d{2}-\d{2}$/'; // Pattern YYYY-MM-DD

    foreach ($arr as $item) {
        // Ignorer les entrées non-tableaux
        if (!is_array($item)) continue;

        // Extraire les valeurs avec fallbacks
        $start = $item['start'] ?? '';
        $end   = $item['end'] ?? '';
        $form  = $item['formule'] ?? '';
        $qty   = intval($item['quantity'] ?? 1);
        $order = intval($item['order'] ?? 0);

        // Valider les champs obligatoires
        if (!$start || !$end) continue;
        
        // Valider le format des dates
        if (!preg_match($date_regex, $start)) continue;
        if (!preg_match($date_regex, $end)) continue;

        // Ajouter l'entrée nettoyée
        $clean[] = [
            'start'    => $start,
            'end'      => $end,
            'formule'  => $form,
            'quantity' => max(1, $qty), // Minimum 1
            'order'    => $order
        ];
    }

    // Ré-indexer le tableau (clés numériques consécutives)
    return array_values($clean);
}

/* =============================================================================
   SECTION 2 : RECONSTRUCTION DU JSON
   =============================================================================
   Reconstruit le JSON d'une offre en parcourant les commandes WooCommerce.
   Utilisé en cas de désynchronisation ou corruption des données.
============================================================================= */

/**
 * Reconstruit le JSON reservations_json depuis les commandes WooCommerce.
 *
 * Parcourt toutes les commandes WooCommerce (completed, processing)
 * contenant l'offre spécifiée et recrée le JSON à partir de ces données.
 *
 * WooCommerce est la SOURCE DE VÉRITÉ, le JSON n'est qu'un cache.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb Instance de la base de données WordPress.
 *
 * @param int $offre_id L'ID de l'offre coworking à reconstruire.
 *
 * @return int Le nombre de réservations trouvées et ajoutées au JSON.
 *
 * @example
 * $count = cw_rebuild_reservations_json(123);
 * echo "Offre 123 reconstruite avec $count réservations";
 */
function cw_rebuild_reservations_json($offre_id) {
    global $wpdb;
    
    // Requête SQL pour trouver toutes les commandes avec cette offre
    // Jointure sur les tables WooCommerce pour récupérer les métadonnées
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT order_items.order_id, order_itemmeta.meta_value as offre_id
        FROM {$wpdb->prefix}woocommerce_order_items as order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_itemmeta 
            ON order_items.order_item_id = order_itemmeta.order_item_id
        LEFT JOIN {$wpdb->posts} as posts 
            ON order_items.order_id = posts.ID
        WHERE order_itemmeta.meta_key = '_cw_offre_id'
        AND order_itemmeta.meta_value = %d
        AND posts.post_status IN ('wc-completed', 'wc-processing')
    ", $offre_id));
    
    $new_reservations = [];
    
    // Parcourir chaque commande trouvée
    foreach ($results as $row) {
        $order = wc_get_order($row->order_id);
        if (!$order) continue;
        
        // Parcourir les items de la commande
        foreach ($order->get_items() as $item) {
            $item_offre_id = $item->get_meta('_cw_offre_id');
            
            // Vérifier que c'est bien l'offre recherchée
            if (intval($item_offre_id) !== intval($offre_id)) continue;
            
            $start   = $item->get_meta('_cw_start');
            $end     = $item->get_meta('_cw_end');
            $formule = $item->get_meta('_cw_formule');
            
            if ($start && $end) {
                $new_reservations[] = [
                    'start'    => $start,
                    'end'      => $end,
                    'formule'  => $formule,
                    'quantity' => 1,
                    'order'    => (int) $row->order_id
                ];
            }
        }
    }
    
    // Sauvegarder le nouveau JSON (avec pretty print pour lisibilité)
    $json_content = json_encode(array_values($new_reservations), JSON_PRETTY_PRINT);
    update_field('reservations_json', $json_content, $offre_id);
    
    // Log en mode debug
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("REBUILD JSON: Offre $offre_id reconstruite avec " . count($new_reservations) . " réservations");
    }
    
    return count($new_reservations);
}

/* ==============================================================
   3) Endpoint REST SÉCURISÉ - Version corrigée
============================================================== */

add_action('rest_api_init', function() {
    // Endpoint pour les admins uniquement
    register_rest_route('coworking/v1', '/rebuild-json/(?P<offre_id>\d+)', [
        'methods' => 'POST',
        'permission_callback' => function(WP_REST_Request $request) {
            return current_user_can('manage_options');
        },
        'callback' => function(WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
            
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Nonce invalide'
                ], 403);
            }

            $offre_id = (int) $request->get_param('offre_id');
            
            if (!$offre_id) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'ID manquant'
                ], 400);
            }
            
            $result = cw_rebuild_reservations_json($offre_id);
            
            return new WP_REST_Response([
                'success' => true,
                'offre_id' => $offre_id,
                'offre_name' => get_the_title($offre_id),
                'count' => count($result),
                'message' => 'JSON resynchronisé avec succès'
            ], 200);
        }
    ]);
});
