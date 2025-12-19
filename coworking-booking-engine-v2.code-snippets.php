<?php

/**
 * =============================================================================
 * COWORKING BOOKING ENGINE V2
 * =============================================================================
 *
 * Moteur principal du système de réservation coworking.
 * Gère le cycle complet : vérification disponibilité → lock → panier → finalisation.
 *
 * FONCTIONNALITÉS PRINCIPALES :
 * - API REST pour ajout au panier (/cart-add)
 * - Système de locks temporaires anti-double réservation
 * - Support multi-quantité (plusieurs unités de temps)
 * - Intégration WooCommerce transparente
 * - Gestion des annulations et remboursements
 *
 * DÉPENDANCES :
 * - coworking-config.php (priorité 1) : Configuration centrale
 * - systeme-disponibilite.php : Calcul des disponibilités
 * - WooCommerce : Gestion panier et paiements
 * - ACF Pro : Champs personnalisés des offres
 *
 * @package    SkyLounge_Coworking
 * @subpackage Booking_Engine
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    4.0.0
 *
 * @see coworking_check_availability_with_locks() Vérification disponibilité
 * @see coworking_add_lock()                      Création de lock temporaire
 * @see coworking_finalize_reservation()          Finalisation après paiement
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : CONFIGURATION DES LOCKS
   =============================================================================
   Les locks sont des réservations temporaires qui empêchent la double réservation.
   Le TTL (Time To Live) est adapté selon la capacité de l'offre :
   - Capacité = 1 (bureau unique) : 20 min pour finaliser le paiement
   - Capacité > 1 (salle partagée) : 5 min suffisent
============================================================================= */

/**
 * Calcule le TTL (durée de vie) d'un lock selon la capacité de l'offre.
 *
 * La logique métier est la suivante :
 * - Un bureau privé (capacité 1) est unique, donc on laisse plus de temps
 *   au client pour finaliser son paiement (20 minutes)
 * - Une salle partagée (capacité > 1) peut accueillir plusieurs réservations,
 *   donc on réduit le temps de lock pour libérer les places plus vite (5 minutes)
 *
 * @since 2.0.0
 *
 * @param int $offre_id L'ID de l'offre coworking (post type 'offre-coworking').
 *
 * @return int Le TTL en secondes (1200 ou 300).
 *
 * @example
 * $ttl = cw_get_lock_ttl(123);
 * // Bureau capacité 1 → 1200 (20 min)
 * // Salle capacité 5  → 300 (5 min)
 */
function cw_get_lock_ttl($offre_id) {
    $capacity = (int) get_field('capacite_max', $offre_id);

    if ($capacity <= 1) {
        return 20 * MINUTE_IN_SECONDS; // 20 minutes pour bureau unique
    } else {
        return 5 * MINUTE_IN_SECONDS;  // 5 minutes pour espace partagé
    }
}

/**
 * Retourne le nombre de jours correspondant à une formule de réservation.
 *
 * Utilisé pour calculer la date de fin à partir de la date de début
 * et de la quantité choisie par le client.
 *
 * @since 1.0.0
 *
 * @param string $formule Le type de formule ('journee', 'semaine', 'mois').
 *
 * @return int Le nombre de jours pour une unité de cette formule.
 *
 * @example
 * cw_get_bloc_days('semaine'); // Retourne 7
 */
function cw_get_bloc_days($formule) {
    switch ($formule) {
        case 'journee': return 1;
        case 'semaine': return 7;
        case 'mois':    return 30;
        default:        return 1;
    }
}

/**
 * Calcule la date de fin d'une réservation.
 *
 * La formule est : date_fin = date_debut + (jours_par_formule × quantité) - 1
 * Le "-1" est important car le jour de début compte dans la durée.
 *
 * @since 1.0.0
 *
 * @param string $start_date La date de début au format 'Y-m-d'.
 * @param string $formule    Le type de formule ('journee', 'semaine', 'mois').
 * @param int    $quantity   Le nombre d'unités réservées.
 *
 * @return string La date de fin au format 'Y-m-d'.
 *
 * @example
 * cw_calculate_end_date('2025-01-15', 'semaine', 2);
 * // 2 semaines = 14 jours, donc fin = 15 + 13 = 28 janvier
 * // Retourne '2025-01-28'
 */
function cw_calculate_end_date($start_date, $formule, $quantity) {
    $bloc_days = cw_get_bloc_days($formule);
    $total_days = $bloc_days * $quantity;
    return date('Y-m-d', strtotime($start_date . ' + ' . ($total_days - 1) . ' days'));
}

/* =============================================================================
   SECTION 2 : ENREGISTREMENT DU CUSTOM POST TYPE
   =============================================================================
   Le CPT 'cw_reservation' stocke toutes les réservations confirmées.
   Il n'est pas accessible en front-end (public: false) mais visible en admin.
============================================================================= */

/**
 * Enregistre le Custom Post Type 'cw_reservation'.
 *
 * Ce CPT est la source de vérité pour toutes les réservations confirmées.
 * Il est créé automatiquement après validation du paiement WooCommerce.
 *
 * Caractéristiques :
 * - Non public (pas d'URL front-end)
 * - Visible dans l'admin WordPress
 * - Création manuelle désactivée (uniquement via hooks WooCommerce)
 *
 * @since 1.0.0
 * @hook init
 */
add_action('init', function() {
    register_post_type('cw_reservation', [
        'labels' => [
            'name'          => 'Réservations Coworking',
            'singular_name' => 'Réservation',
            'menu_name'     => 'Réservations',
            'all_items'     => 'Toutes les réservations',
            'view_item'     => 'Voir la réservation',
            'edit_item'     => 'Modifier la réservation',
            'search_items'  => 'Rechercher une réservation',
        ],
        'public'       => false,           // Pas d'accès front-end
        'show_ui'      => true,            // Visible dans l'admin
        'supports'     => ['title', 'custom-fields'],
        'menu_icon'    => 'dashicons-calendar-alt',
        'capabilities' => ['create_posts' => false], // Pas de création manuelle
        'map_meta_cap' => true,
    ]);
});

/* =============================================================================
   SECTION 3 : RÉSOLUTION DU PRODUIT WOOCOMMERCE
   ============================================================================= */

/**
 * Résout l'ID du produit WooCommerce associé à une offre coworking.
 *
 * Cette fonction utilise en priorité la configuration centralisée
 * (coworking-config.php) et utilise un fallback pour la compatibilité
 * avec les anciennes versions utilisant un champ ACF 'produit_woocommerce'.
 *
 * @since 1.0.0
 * @deprecated 2.0.0 Utiliser cw_get_product_id_for_offre() de coworking-config.php
 *
 * @param int $offre_id L'ID de l'offre coworking.
 *
 * @return int L'ID du produit WooCommerce, ou 0 si non trouvé.
 */
function cw_resolve_product_id_from_offre($offre_id) {
    // Priorité : utiliser la configuration centralisée
    if (function_exists('cw_get_product_id_for_offre')) {
        return cw_get_product_id_for_offre($offre_id);
    }

    // Fallback legacy : champ ACF 'produit_woocommerce'
    $field = get_field('produit_woocommerce', $offre_id);
    if (!$field) return 0;
    
    if (is_array($field)) {
        $first = reset($field);
        if (is_object($first) && isset($first->ID)) return intval($first->ID);
        if (is_numeric($first)) return intval($first);
    }
    if (is_object($field) && isset($field->ID)) return intval($field->ID);
    if (is_numeric($field)) return intval($field);
    
    return 0;
}

/* =============================================================================
   SECTION 4 : VÉRIFICATION DE DISPONIBILITÉ
   =============================================================================
   Fonction centrale qui vérifie si une plage de dates est disponible
   en tenant compte des réservations confirmées ET des locks temporaires.
============================================================================= */

/**
 * Vérifie la disponibilité d'une offre sur une plage de dates.
 *
 * Cette fonction est CRITIQUE pour éviter les doubles réservations.
 * Elle vérifie :
 * 1. Les réservations confirmées (stockées dans reservations_json)
 * 2. Les locks temporaires (transients WordPress)
 * 3. Les dates bloquées manuellement par l'admin
 *
 * Pour chaque jour de la plage demandée, on compte le nombre de places
 * déjà prises et on compare avec la capacité maximale.
 *
 * @since 1.0.0
 *
 * @param int    $offre_id   L'ID de l'offre coworking.
 * @param string $start_date La date de début souhaitée (format 'Y-m-d').
 * @param string $end_date   La date de fin souhaitée (format 'Y-m-d').
 *
 * @return array {
 *     Résultat de la vérification.
 *
 *     @type bool        $available  True si toute la plage est disponible.
 *     @type string|null $fail_date  La première date non disponible, ou null.
 * }
 *
 * @example
 * $check = coworking_check_availability_with_locks(123, '2025-01-15', '2025-01-20');
 * if (!$check['available']) {
 *     echo "Indisponible le " . $check['fail_date'];
 * }
 */
function coworking_check_availability_with_locks($offre_id, $start_date, $end_date) {
    // Récupérer la capacité maximale de l'offre
    $capacity = (int) get_field('capacite_max', $offre_id);
    if ($capacity <= 0) $capacity = 1;

    // Charger les réservations confirmées depuis le JSON
    $reservations_json = get_field('reservations_json', $offre_id) ?: '[]';
    $confirmed_res = json_decode($reservations_json, true);
    if (!is_array($confirmed_res)) $confirmed_res = [];

    // Charger et nettoyer les locks actifs (supprimer les expirés)
    $locks = get_transient('cw_locks_' . $offre_id);
    if (!is_array($locks)) $locks = [];
    $now = time();
    $locks = array_filter($locks, function($l) use ($now) {
        return isset($l['expires_at']) && $l['expires_at'] > $now;
    });

    // Mettre à jour les locks nettoyés dans le transient
    set_transient('cw_locks_' . $offre_id, $locks, cw_get_lock_ttl($offre_id));

    // Charger les dates bloquées manuellement (format: YYYY-MM-DD # commentaire)
    $manual_raw = get_field('dates_indisponibles_manuel', $offre_id) ?: '';
    $manual_lines = array_filter(array_map('trim', explode("\n", $manual_raw)));
    $manual_block = array_map(function($line) {
        $parts = explode('#', $line);
        return trim($parts[0]);
    }, $manual_lines);

    $current = strtotime($start_date);
    $end = strtotime($end_date);
    $is_valid = true;
    $first_fail = null;

    while ($current <= $end) {
        $date_str = date('Y-m-d', $current);
        $count = 0;

        if (in_array($date_str, $manual_block)) {
            $is_valid = false;
            $first_fail = $date_str;
            break;
        }

        foreach ($confirmed_res as $r) {
            if (!isset($r['start']) || !isset($r['end'])) continue;
            if ($date_str >= $r['start'] && $date_str <= $r['end']) {
                $count += (int)($r['quantity'] ?? 1);
            }
        }

        foreach ($locks as $l) {
            if (!isset($l['start']) || !isset($l['end'])) continue;
            if ($date_str >= $l['start'] && $date_str <= $l['end']) {
                $count += (int)($l['quantity'] ?? 1);
            }
        }

        if ($count >= $capacity) {
            $is_valid = false;
            $first_fail = $date_str;
            break;
        }

        $current = strtotime('+1 day', $current);
    }

    return ['available' => $is_valid, 'fail_date' => $first_fail];
}

/* Add / remove locks
 *
 * NOTE TECHNIQUE (race condition):
 * Il existe un risque theorique de race condition si deux clients
 * reservent exactement au meme moment (fenetre de quelques ms).
 * Le risque est mitige par:
 * 1. La double verification (cart-add + checkout via cw_final_security_check)
 * 2. Les transients WordPress qui utilisent des transactions DB
 * 3. La probabilite tres faible en pratique
 *
 * Pour une solution atomique, envisager: verrou SQL avec GET_LOCK()
 * A implementer si le volume de reservations simultanees augmente.
 */
function coworking_add_lock($offre_id, $data) {
    $key = 'cw_locks_' . $offre_id;
    $locks = get_transient($key);
    if (!is_array($locks)) $locks = [];

    $now = time();
    $locks = array_filter($locks, function($l) use ($now) {
        return isset($l['expires_at']) && $l['expires_at'] > $now;
    });

    $ttl = cw_get_lock_ttl($offre_id);

    $locks[] = [
        'start'      => $data['start'],
        'end'        => $data['end'],
        'quantity'   => $data['quantity'] ?? 1,
        'expires_at' => time() + $ttl,
        'token'      => $data['token'],
        'lock_type'  => ($ttl >= 15 * MINUTE_IN_SECONDS) ? 'strict' : 'flexible'
    ];

    set_transient($key, $locks, $ttl);
}

function coworking_remove_lock_by_token($offre_id, $token) {
    $key = 'cw_locks_' . $offre_id;
    $locks = get_transient($key);
    if (!is_array($locks)) $locks = [];

    $locks = array_filter($locks, function($l) use ($token) {
        return ($l['token'] ?? '') !== $token;
    });

    set_transient($key, $locks, cw_get_lock_ttl($offre_id));
}

/* 4) Endpoint cart-add - AVEC QUANTITÉ */
add_action('rest_api_init', function() {
    register_rest_route('coworking/v1', '/cart-add', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function(WP_REST_Request $req) {

            $p = $req->get_json_params();
            $offre_id = (int) ($p['offre_id'] ?? 0);
            $formule  = sanitize_text_field($p['formule'] ?? '');
            $start    = sanitize_text_field($p['start'] ?? '');
            $quantity = (int) ($p['quantity'] ?? 1);

            // Log de debug (utile pour diagnostic a distance)
            if (function_exists('cw_log')) {
                cw_log('cart-add attempt', 'info', [
                    'offre_id' => $offre_id,
                    'formule' => $formule,
                    'start' => $start,
                    'quantity' => $quantity
                ]);
            }

            // Valider quantité
            if ($quantity < 1) $quantity = 1;

            // Calculer la date de fin
            $end = cw_calculate_end_date($start, $formule, $quantity);

            if (!$offre_id || !$formule || !$start) {
                if (function_exists('cw_log')) {
                    cw_log('cart-add echec: parametres manquants', 'warning', $p);
                }
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Informations incomplètes. Veuillez rafraîchir la page et réessayer.',
                    'code' => 'MISSING_PARAMS'
                ], 400);
            }

            // Vérifier date minimum J+1
            if ($start <= coworking_today_date()) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'La réservation doit commencer au plus tôt demain.',
                    'code' => 'DATE_TOO_SOON'
                ], 400);
            }

            // Récupérer les prix
            $price_day   = (float) get_field('prix_journee', $offre_id);
            $price_week  = (float) get_field('prix_semaine', $offre_id);
            $price_month = (float) get_field('prix_mois', $offre_id);

            // Calculer le prix unitaire selon la formule
            $unit_price = 0;
            if ($formule === 'journee') $unit_price = $price_day;
            elseif ($formule === 'semaine') $unit_price = $price_week ?: ($price_day * 5);
            elseif ($formule === 'mois') $unit_price = $price_month ?: ($price_week * 4);
            else $unit_price = $price_day;

            // Prix total = prix unitaire × quantité
            $final_price = $unit_price * $quantity;

            if ($final_price <= 0) {
                if (function_exists('cw_log')) {
                    cw_log('cart-add echec: prix zero ou negatif', 'error', [
                        'offre_id' => $offre_id,
                        'formule' => $formule,
                        'unit_price' => $unit_price,
                        'final_price' => $final_price
                    ]);
                }
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Le tarif de cette offre n\'est pas configuré. Contactez-nous.',
                    'code' => 'PRICE_NOT_CONFIGURED'
                ], 500);
            }

            // Vérifier disponibilité de toute la plage
            $check = coworking_check_availability_with_locks($offre_id, $start, $end);
            if (!$check['available']) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Cette date n\'est plus disponible : ' . date('d/m/Y', strtotime($check['fail_date'])) . '. Un autre client vient peut-être de réserver.',
                    'code' => 'DATE_UNAVAILABLE'
                ], 409);
            }

            // Résoudre le produit WooCommerce (utilise la config centralisee)
            $product_id = cw_resolve_product_id_from_offre($offre_id);

            if (!$product_id) {
                if (function_exists('cw_log')) {
                    cw_log('cart-add echec: produit WC non trouve', 'error', ['offre_id' => $offre_id]);
                }
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Configuration incomplète. Contactez l\'administrateur.',
                    'code' => 'PRODUCT_NOT_FOUND'
                ], 500);
            }

            if (!function_exists('WC')) {
                if (function_exists('cw_log')) {
                    cw_log('cart-add echec: WooCommerce inactif', 'error');
                }
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Le système de paiement est temporairement indisponible.',
                    'code' => 'WC_INACTIVE'
                ], 500);
            }

            // Créer le lock
            $cart_token = wp_generate_password(12, false);
            coworking_add_lock($offre_id, [
                'start' => $start,
                'end' => $end,
                'token' => $cart_token,
                'quantity' => 1 // Le lock prend 1 slot, pas N
            ]);

            // Initialiser session WC
            if (!WC()->session) WC()->initialize_session();
            wc_load_cart();

            // Données panier avec quantité
            $cart_item_data = [
                'coworking_data' => [
                    'offre_id'    => $offre_id,
                    'offre_name'  => get_the_title($offre_id),
                    'formule'     => $formule,
                    'start'       => $start,
                    'end'         => $end,
                    'quantity'    => $quantity,
                    'unit_price'  => (float)$unit_price,
                    'real_price'  => (float)$final_price,
                    'lock_token'  => $cart_token
                ]
            ];

            $added = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
            if (!$added) {
                coworking_remove_lock_by_token($offre_id, $cart_token);
                if (function_exists('cw_log')) {
                    cw_log('cart-add echec: WC()->cart->add_to_cart a echoue', 'error', [
                        'offre_id' => $offre_id,
                        'product_id' => $product_id
                    ]);
                }
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Impossible d\'ajouter au panier. Veuillez réessayer.',
                    'code' => 'CART_ADD_FAILED'
                ], 500);
            }

            // Créer CPT temporaire
            $resa_id = wp_insert_post([
                'post_type'  => 'cw_reservation',
                'post_title' => sprintf('Temp - Offre %d - %s', $offre_id, $start),
                'post_status'=> 'draft'
            ]);

            if ($resa_id) {
                update_post_meta($resa_id, '_cw_offre_id', $offre_id);
                update_post_meta($resa_id, '_cw_offre_name', get_the_title($offre_id));
                update_post_meta($resa_id, '_cw_formule', $formule);
                update_post_meta($resa_id, '_cw_start', $start);
                update_post_meta($resa_id, '_cw_end', $end);
                update_post_meta($resa_id, '_cw_quantity', $quantity);
                update_post_meta($resa_id, '_cw_unit_price', $unit_price);
                update_post_meta($resa_id, '_cw_price', $final_price);
                update_post_meta($resa_id, '_cw_lock_token', $cart_token);
            }

            // Log succes
            if (function_exists('cw_log')) {
                cw_log('cart-add succes', 'info', [
                    'offre_id' => $offre_id,
                    'start' => $start,
                    'end' => $end,
                    'price' => $final_price,
                    'token' => $cart_token
                ]);
            }

            return new WP_REST_Response([
                'success' => true,
                'cart_url' => wc_get_checkout_url(), // Direct checkout, pas panier
                'message' => 'Réservation ajoutée, redirection vers le paiement...'
            ], 200);
        }
    ]);
});

/* 5-7) Cart & Order handling - AVEC QUANTITÉ */
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['coworking_data']['real_price'])) {
            $cart_item['data']->set_price((float) $cart_item['coworking_data']['real_price']);
        }
    }
}, 10);

add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['coworking_data'])) {
        $d = $cart_item['coworking_data'];
        $item_data[] = ['key' => 'Offre', 'value' => $d['offre_name'] ?? ''];
        $item_data[] = ['key' => 'Formule', 'value' => ucfirst($d['formule'] ?? '')];

        // Afficher la quantité si > 1
        $qty = (int)($d['quantity'] ?? 1);
        if ($qty > 1) {
            $unit_label = $d['formule'] === 'journee' ? 'jours' : ($d['formule'] === 'semaine' ? 'semaines' : 'mois');
            $item_data[] = ['key' => 'Durée', 'value' => $qty . ' ' . $unit_label];
        }

        $item_data[] = ['key' => 'Du', 'value' => date_i18n('d/m/Y', strtotime($d['start'] ?? ''))];
        $item_data[] = ['key' => 'Au', 'value' => date_i18n('d/m/Y', strtotime($d['end'] ?? ''))];
    }
    return $item_data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['coworking_data'])) {
        $d = $values['coworking_data'];
        $item->add_meta_data('_cw_offre_id', $d['offre_id'] ?? '');
        $item->add_meta_data('_cw_offre_name', $d['offre_name'] ?? '');
        $item->add_meta_data('_cw_start', $d['start'] ?? '');
        $item->add_meta_data('_cw_end', $d['end'] ?? '');
        $item->add_meta_data('_cw_formule', $d['formule'] ?? '');
        $item->add_meta_data('_cw_quantity', $d['quantity'] ?? 1);
        $item->add_meta_data('_cw_unit_price', $d['unit_price'] ?? '');
        $item->add_meta_data('_cw_price', $d['real_price'] ?? '');
        $item->add_meta_data('_cw_lock_token', $d['lock_token'] ?? '');

        // Métas visibles
        $item->add_meta_data('Offre', $d['offre_name'] ?? '', true);
        $item->add_meta_data('Formule', ucfirst($d['formule'] ?? ''), true);

        $qty = (int)($d['quantity'] ?? 1);
        if ($qty > 1) {
            $unit_label = $d['formule'] === 'journee' ? 'jours' : ($d['formule'] === 'semaine' ? 'semaines' : 'mois');
            $item->add_meta_data('Durée', $qty . ' ' . $unit_label, true);
        }

        $item->add_meta_data('Du', date_i18n('d/m/Y', strtotime($d['start'] ?? '')), true);
        $item->add_meta_data('Au', date_i18n('d/m/Y', strtotime($d['end'] ?? '')), true);
    }
}, 10, 4);

/* 8) FINALIZATION - AVEC QUANTITÉ */
add_action('woocommerce_order_status_completed', 'coworking_finalize_reservation');
add_action('woocommerce_order_status_processing', 'coworking_finalize_reservation');
add_action('woocommerce_order_status_on-hold', 'coworking_finalize_reservation');
add_action('woocommerce_order_status_pending', 'coworking_finalize_reservation');
add_action('woocommerce_checkout_order_processed', 'coworking_finalize_new_order', 20, 1);

function coworking_finalize_reservation($order_id) {
    // Verrou transactionnel
    $lock_key = 'cw_finalize_lock_' . $order_id;

    if (get_transient($lock_key)) {
        return;
    }

    set_transient($lock_key, true, 60);

    $order = wc_get_order($order_id);
    if (!$order) {
        delete_transient($lock_key);
        return;
    }

    // Vérifier si déjà traité
    if ($order->get_meta('_cw_reservation_finalized') === 'yes') {
        delete_transient($lock_key);
        return;
    }

    // Vérifier RGPD
    $rgpd_consent = get_post_meta($order_id, '_cw_rgpd_consent', true);
    if (!$rgpd_consent) {
        $rgpd_consent = $order->get_meta('_cw_rgpd_consent');
    }

    $is_admin_order = get_post_meta($order_id, '_cw_admin_created', true) === 'yes'
                      || $order->get_meta('_cw_admin_created') === 'yes';

    if ($rgpd_consent !== 'yes') {
        if (!$is_admin_order) {
            delete_transient($lock_key);
            return;
        }
        $order->update_meta_data('_cw_rgpd_consent', 'yes');
        $order->update_meta_data('_cw_rgpd_consent_date', current_time('mysql'));
        $order->update_meta_data('_cw_rgpd_consent_ip', 'admin_manual');
        $order->save();
    }

    foreach ($order->get_items() as $item) {
        $offre_id = $item->get_meta('_cw_offre_id');
        if (!$offre_id) continue;

        $start = $item->get_meta('_cw_start');
        $end   = $item->get_meta('_cw_end');
        $formule = $item->get_meta('_cw_formule');
        $quantity = (int)($item->get_meta('_cw_quantity') ?: 1);
        $unit_price = $item->get_meta('_cw_unit_price');
        $price = $item->get_meta('_cw_price');
        $lock_token = $item->get_meta('_cw_lock_token');
        $offre_name = $item->get_meta('_cw_offre_name') ?: get_the_title($offre_id);

        // Récupérer JSON existant
        $json = function_exists('get_field') ? get_field('reservations_json', $offre_id) : false;
        if ($json === false || $json === null) {
            $json = get_post_meta($offre_id, 'reservations_json', true);
        }
        if (!$json) $json = '[]';

        $reservations = json_decode($json, true);
        if (!is_array($reservations)) $reservations = [];

        // Vérifier doublon
        $already_exists = false;
        foreach ($reservations as $res) {
            if (isset($res['order']) && intval($res['order']) === intval($order_id)) {
                $already_exists = true;
                break;
            }
        }

        // Ajouter si pas déjà présent
        if (!$already_exists) {
            // IMPORTANT: quantity ici = nombre d'espaces réservés (toujours 1 dans l'UI actuelle)
            // Ne pas confondre avec la durée ($quantity qui est le nombre de semaines/jours)
            $spaces_reserved = 1; // Un client = 1 espace, même pour plusieurs semaines

            $reservations[] = [
                'start' => $start,
                'end' => $end,
                'formule' => $formule,
                'quantity' => $spaces_reserved,
                'order' => (int)$order_id
            ];

            $reservations = array_values($reservations);
            $new_json = json_encode($reservations, JSON_PRETTY_PRINT);

            if (function_exists('update_field')) {
                $updated = update_field('reservations_json', $new_json, $offre_id);
                if (!$updated) {
                    update_post_meta($offre_id, 'reservations_json', $new_json);
                }
            } else {
                update_post_meta($offre_id, 'reservations_json', $new_json);
            }
        }

        // Créer/Mettre à jour CPT
        $existing = get_posts([
            'post_type' => 'cw_reservation',
            'meta_query' => [['key' => '_cw_lock_token', 'value' => $lock_token]],
            'post_status' => 'draft',
            'posts_per_page' => 1
        ]);

        if (!empty($existing)) {
            $post_id = $existing[0]->ID;
            wp_update_post([
                'ID' => $post_id,
                'post_title' => sprintf('Résa #%s - %s', $order_id, $offre_name),
                'post_status' => 'publish'
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_type' => 'cw_reservation',
                'post_title' => sprintf('Résa #%s - %s', $order_id, $offre_name),
                'post_status' => 'publish'
            ]);
        }

        if ($post_id) {
            update_post_meta($post_id, '_cw_offre_id', $offre_id);
            update_post_meta($post_id, '_cw_offre_name', $offre_name);
            update_post_meta($post_id, '_cw_formule', $formule);
            update_post_meta($post_id, '_cw_start', $start);
            update_post_meta($post_id, '_cw_end', $end);
            update_post_meta($post_id, '_cw_quantity', $quantity);
            update_post_meta($post_id, '_cw_unit_price', $unit_price);
            update_post_meta($post_id, '_cw_price', $price);
            update_post_meta($post_id, '_cw_customer_name', $order->get_formatted_billing_full_name());
            update_post_meta($post_id, '_cw_customer_email', $order->get_billing_email());
            update_post_meta($post_id, '_cw_order_id', $order_id);
            update_post_meta($post_id, '_cw_rgpd_consent_date', $order->get_meta('_cw_rgpd_consent_date'));
            update_post_meta($post_id, '_cw_lock_token', '');
        }

        // Supprimer le lock
        if ($lock_token) {
            coworking_remove_lock_by_token($offre_id, $lock_token);
        }
    }

    // Marquer comme finalisé
    $order->update_meta_data('_cw_reservation_finalized', 'yes');
    $order->save();

    delete_transient($lock_key);
}

function coworking_finalize_new_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $items = $order->get_items();
    $has_coworking = false;

    foreach ($items as $item) {
        if ($item->get_meta('_cw_offre_id')) {
            $has_coworking = true;
            break;
        }
    }

    if (!$has_coworking) return;

    coworking_finalize_reservation($order_id);
}

/* 9) CANCELLATION */
add_action('before_delete_post', 'coworking_handle_post_deletion', 10, 1);

function coworking_handle_post_deletion($post_id) {
    if (get_post_type($post_id) !== 'shop_order') {
        return;
    }

    $order = wc_get_order($post_id);
    if (!$order) return;

    $has_coworking_items = false;
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('_cw_offre_id')) {
            $has_coworking_items = true;
            break;
        }
    }

    if ($has_coworking_items) {
        coworking_cancel_reservation($post_id);
    }
}

add_action('woocommerce_order_status_cancelled', 'coworking_cancel_reservation');
add_action('woocommerce_order_status_refunded', 'coworking_cancel_reservation');
add_action('woocommerce_order_status_trash', 'coworking_cancel_reservation');

function coworking_cancel_reservation($order_id) {
    static $processed_orders = [];
    if (in_array($order_id, $processed_orders)) {
        return;
    }
    $processed_orders[] = $order_id;

    $order = wc_get_order($order_id);
    if (!$order) return;

    if ($order->get_meta('_cw_cancellation_processed') === 'yes') {
        return;
    }

    foreach ($order->get_items() as $item) {
        $offre_id = $item->get_meta('_cw_offre_id');
        if (!$offre_id) continue;

        $lock_token = $item->get_meta('_cw_lock_token');
        if ($lock_token) {
            coworking_remove_lock_by_token($offre_id, $lock_token);
        }

        // Nettoyage JSON
        $json = get_field('reservations_json', $offre_id) ?: '[]';
        $res = json_decode($json, true);

        if (is_array($res)) {
            $original_count = count($res);
            $new_res = array_filter($res, function($r) use ($order_id) {
                return !isset($r['order']) || intval($r['order']) !== intval($order_id);
            });

            if (count($new_res) !== $original_count) {
                update_field('reservations_json', json_encode(array_values($new_res), JSON_PRETTY_PRINT), $offre_id);
            }
        }

        // Mettre CPT à la corbeille
        $q = new WP_Query([
            'post_type' => 'cw_reservation',
            'meta_query' => [[
                'key' => '_cw_order_id',
                'value' => $order_id,
                'compare' => '='
            ]],
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $post_id = get_the_ID();

                $current_status = get_post_status($post_id);
                if ($current_status !== 'trash') {
                    wp_update_post(['ID' => $post_id, 'post_status' => 'trash']);
                }
            }
            wp_reset_postdata();
        }
    }

    $order->update_meta_data('_cw_cancellation_processed', 'yes');
    $order->save();
}

/* 10-11) Stock management */
add_filter('woocommerce_product_get_manage_stock', 'cw_disable_stock_management', 10, 2);
add_filter('woocommerce_product_variation_get_manage_stock', 'cw_disable_stock_management', 10, 2);

function cw_disable_stock_management($value, $product) {
    // Utilise la config centralisee
    if (function_exists('cw_is_coworking_product') && cw_is_coworking_product($product->get_id())) {
        return false;
    }
    return $value;
}

add_filter('woocommerce_product_is_in_stock', 'cw_force_in_stock', 10, 2);
function cw_force_in_stock($status, $product) {
    // Utilise la config centralisee
    if (function_exists('cw_is_coworking_product') && cw_is_coworking_product($product->get_id())) {
        return true;
    }
    return $status;
}

/* 12) Final security check */
add_action('woocommerce_check_cart_items', 'cw_final_security_check');

function cw_final_security_check() {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $cart = WC()->cart->get_cart();

    foreach ($cart as $item_key => $item) {
        if (empty($item['coworking_data'])) continue;

        $d = $item['coworking_data'];
        $offre_id = (int)$d['offre_id'];
        $start = $d['start'];
        $end = $d['end'];
        $my_token = $d['lock_token'] ?? '';

        $locks = get_transient('cw_locks_' . $offre_id);
        if (!is_array($locks)) $locks = [];

        $now = time();
        $active_locks = [];
        $i_still_have_my_lock = false;

        foreach ($locks as $l) {
            if (isset($l['expires_at']) && $l['expires_at'] > $now) {
                $active_locks[] = $l;
                if (isset($l['token']) && $l['token'] === $my_token) {
                    $i_still_have_my_lock = true;
                }
            }
        }

        if ($i_still_have_my_lock) continue;

        set_transient('cw_locks_' . $offre_id, $active_locks, cw_get_lock_ttl($offre_id));

        $check = coworking_check_availability_with_locks($offre_id, $start, $end);

        if (!$check['available']) {
            wc_add_notice(sprintf(
                "<strong>Attention :</strong> Le créneau du %s n'est plus disponible.",
                date_i18n('d/m/Y', strtotime($start))
            ), 'error');

            WC()->cart->remove_cart_item($item_key);
        } else {
            coworking_add_lock($offre_id, ['start' => $start, 'end' => $end, 'token' => $my_token]);
        }
    }
}

/**
 * Admin Booking - AVEC QUANTITÉ
 */
function cw_create_admin_booking($offre_id, $formule, $start, $end, $client_name, $client_email, $payment_status, $phone = '', $notes = '', $payment_method = '', $quantity = 1) {

    // Validation
    if (!$start || !$end) {
        return ['success' => false, 'message' => 'Dates invalides.'];
    }

    $start_date = date('Y-m-d', strtotime($start));
    $end_date   = date('Y-m-d', strtotime($end));

    // Si quantity fournie, recalculer end_date
    if ($quantity > 1) {
        $end_date = cw_calculate_end_date($start_date, $formule, $quantity);
    }

    // Vérification disponibilité
    $check = coworking_check_availability_with_locks($offre_id, $start_date, $end_date);

    if (!$check['available']) {
        $fail_date = date('d/m/Y', strtotime($check['fail_date']));
        return [
            'success' => false,
            'message' => "IMPOSSIBLE : La date du $fail_date est bloquée ou complète."
        ];
    }

    // Lock
    $lock_token = wp_generate_password(12, false);
    coworking_add_lock($offre_id, [
        'start' => $start_date,
        'end' => $end_date,
        'token' => $lock_token,
        'quantity' => 1
    ]);

    // Calcul prix
    $price_day   = (float) get_field('prix_journee', $offre_id);
    $price_week  = (float) get_field('prix_semaine', $offre_id);
    $price_month = (float) get_field('prix_mois', $offre_id);

    $unit_price = 0;
    if ($formule === 'journee') $unit_price = $price_day;
    elseif ($formule === 'semaine') $unit_price = $price_week ?: ($price_day * 5);
    elseif ($formule === 'mois') $unit_price = $price_month ?: ($price_week * 4);
    else $unit_price = $price_day;

    $final_price = $unit_price * $quantity;

    // Commande WooCommerce
    $order = wc_create_order();
    if (is_wp_error($order)) {
        coworking_remove_lock_by_token($offre_id, $lock_token);
        return ['success' => false, 'message' => 'Erreur WP : ' . $order->get_error_message()];
    }

    // Client
    $name_parts = explode(' ', $client_name, 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

    $order->set_address([
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $client_email,
        'phone'      => $phone
    ], 'billing');

    // Produit (utilise la config centralisee avec fallbacks)
    $product_id = cw_resolve_product_id_from_offre($offre_id);

    $product = wc_get_product($product_id);
    if ($product) {
        $product->set_price($final_price);
        $product->set_regular_price($final_price);
        $item_id = $order->add_product($product, 1);

        if ($item_id) {
            $item = $order->get_item($item_id);
            $item->add_meta_data('_cw_offre_id', $offre_id);
            $item->add_meta_data('_cw_offre_name', get_the_title($offre_id));
            $item->add_meta_data('_cw_start', $start_date);
            $item->add_meta_data('_cw_end', $end_date);
            $item->add_meta_data('_cw_formule', $formule);
            $item->add_meta_data('_cw_quantity', $quantity);
            $item->add_meta_data('_cw_unit_price', $unit_price);
            $item->add_meta_data('_cw_price', $final_price);
            $item->add_meta_data('_cw_lock_token', $lock_token);
            $item->save();
        }
    }

    // Marqueurs
    $order->update_meta_data('_cw_admin_created', 'yes');
    $order->update_meta_data('_cw_rgpd_consent', 'yes');
    $order->update_meta_data('_cw_rgpd_consent_date', current_time('mysql'));

    if ($notes) $order->add_order_note($notes);

    $order->calculate_totals();
    $order->save();

    $order->update_status($payment_status, 'Réservation Admin Manuelle');

    $order_id = $order->get_id();

    coworking_finalize_reservation($order_id);

    return ['success' => true, 'order_id' => $order_id];
}

/* AUTO-SYNC CPT → JSON */
add_action('wp_trash_post', 'cw_sync_on_delete_cpt');
add_action('before_delete_post', 'cw_sync_on_delete_cpt');

function cw_sync_on_delete_cpt($post_id) {
    if (get_post_type($post_id) !== 'cw_reservation') return;

    $order_id = get_post_meta($post_id, '_cw_order_id', true);
    $offre_id = get_post_meta($post_id, '_cw_offre_id', true);

    if (!$offre_id) return;

    $json = get_field('reservations_json', $offre_id) ?: '[]';
    $reservations = json_decode($json, true);

    if (is_array($reservations)) {
        $initial_count = count($reservations);

        $reservations = array_filter($reservations, function($r) use ($order_id) {
            if ($order_id && isset($r['order']) && intval($r['order']) == intval($order_id)) {
                return false;
            }
            return true;
        });

        if (count($reservations) !== $initial_count) {
            update_field('reservations_json', json_encode(array_values($reservations), JSON_PRETTY_PRINT), $offre_id);
        }
    }
}
