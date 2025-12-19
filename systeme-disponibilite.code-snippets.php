<?php

/**
 * =============================================================================
 * SYSTÈME DE DISPONIBILITÉ - COWORKING
 * =============================================================================
 *
 * Calcule et expose les disponibilités des offres coworking via API REST.
 * Ce module est consommé par le calendrier front-end pour afficher
 * les jours disponibles/indisponibles.
 *
 * FONCTIONNALITÉS :
 * - Calcul des disponibilités par mois avec support multi-quantité
 * - Prise en compte des réservations confirmées + locks temporaires
 * - Gestion des dates bloquées manuellement
 * - API REST publique pour le calendrier JavaScript
 *
 * ENDPOINTS :
 * - GET /wp-json/coworking/v1/availability/{offre_id}?month=YYYY-MM
 * - POST /wp-json/coworking/v1/calculate-price
 *
 * @package    SkyLounge_Coworking
 * @subpackage Availability
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    3.0.0 Support multi-quantité
 *
 * @see coworking_get_availability_for_month() Calcul principal des disponibilités
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

/* =============================================================================
   SECTION 1 : FONCTIONS HELPER
   =============================================================================
   Fonctions utilitaires réutilisables dans tout le système.
============================================================================= */

/**
 * Retourne la date du jour au format ISO (YYYY-MM-DD).
 *
 * Utilise le timezone WordPress configuré dans Réglages > Général
 * pour éviter les décalages horaires.
 *
 * @since 1.0.0
 *
 * @return string La date du jour au format 'Y-m-d'.
 *
 * @example
 * $today = coworking_today_date(); // '2025-12-19'
 */
if (!function_exists('coworking_today_date')) {
    function coworking_today_date() {
        return date_i18n('Y-m-d', current_time('timestamp'));
    }
}

/**
 * Compte le nombre de places réservées pour une date donnée.
 *
 * Parcourt le tableau des réservations et additionne les quantités
 * pour toutes les réservations qui incluent la date spécifiée.
 *
 * @since 2.0.0
 *
 * @param string $date         La date à vérifier (format 'Y-m-d').
 * @param array  $reservations Tableau de réservations avec clés 'start', 'end', 'quantity'.
 *
 * @return int Le nombre total de places réservées ce jour-là.
 *
 * @example
 * $reservations = [
 *     ['start' => '2025-01-15', 'end' => '2025-01-20', 'quantity' => 2],
 *     ['start' => '2025-01-18', 'end' => '2025-01-22', 'quantity' => 1],
 * ];
 * $count = count_reservations_for_date('2025-01-18', $reservations);
 * // Retourne 3 (2 + 1)
 */
if (!function_exists('count_reservations_for_date')) {
    function count_reservations_for_date($date, $reservations) {
        $count = 0;

        foreach ($reservations as $r) {
            $start = $r['start'] ?? '';
            $end   = $r['end'] ?? '';
            $qty   = (int) ($r['quantity'] ?? 1);

            // Vérifier si la date est dans la plage de la réservation
            if ($date >= $start && $date <= $end) {
                $count += $qty;
            }
        }

        return $count;
    }
}

/* =============================================================================
   SECTION 2 : CALCUL DES DISPONIBILITÉS
   =============================================================================
   Fonction principale qui génère le tableau de disponibilité pour un mois.
============================================================================= */

/**
 * Calcule les disponibilités d'une offre pour un mois donné.
 *
 * Cette fonction est le cœur du système de calendrier. Elle retourne
 * un tableau associatif avec l'état de chaque jour du mois :
 * - 'available' : nombre de places restantes
 * - 'status' : 'available', 'low', 'full', 'past', 'blocked'
 *
 * Le calcul prend en compte :
 * 1. Les réservations confirmées (reservations_json)
 * 2. Les locks temporaires (transients)
 * 3. Les dates bloquées manuellement
 * 4. Les jours passés
 *
 * @since 2.0.0
 * @since 3.0.0 Ajout du support multi-quantité
 *
 * @param int    $offre_id L'ID de l'offre coworking.
 * @param string $month    Le mois au format 'YYYY-MM' (ex: '2025-01').
 *
 * @return array Tableau associatif [date => ['available' => int, 'status' => string]].
 *
 * @example
 * $availability = coworking_get_availability_for_month(123, '2025-01');
 * // [
 * //   '2025-01-01' => ['available' => 0, 'status' => 'past'],
 * //   '2025-01-15' => ['available' => 5, 'status' => 'available'],
 * //   '2025-01-20' => ['available' => 1, 'status' => 'low'],
 * //   '2025-01-25' => ['available' => 0, 'status' => 'full'],
 * // ]
 */
if (!function_exists('coworking_get_availability_for_month')) {
    function coworking_get_availability_for_month(int $offre_id, string $month) {

        // Récupérer la capacité maximale (fallback: 7 places)
        $capacity = (int) get_field('capacite_max', $offre_id);
        if ($capacity <= 0) $capacity = 7;

        // Charger les réservations confirmées
        $reservations_json = get_field('reservations_json', $offre_id) ?: '[]';
        $reservations = json_decode($reservations_json, true);
        if (!is_array($reservations)) $reservations = [];

        // Ajouter les locks actifs comme "pseudo-réservations"
        $locks = get_transient('cw_locks_' . $offre_id);
        if (is_array($locks)) {
            $now = time();
            foreach ($locks as $lock) {
                if (isset($lock['expires_at']) && $lock['expires_at'] > $now) {
                    $reservations[] = [
                        'start'    => $lock['start'],
                        'end'      => $lock['end'],
                        'quantity' => $lock['quantity'] ?? 1
                    ];
                }
            }
        }

        // Charger les dates bloquées manuellement (format: YYYY-MM-DD # commentaire)
        $manual_raw = get_field('dates_indisponibles_manuel', $offre_id) ?: '';
        $manual_lines = array_filter(array_map('trim', explode("\n", $manual_raw)));
        $manual_block = array_map(function($line) {
            $parts = explode('#', $line);
            return trim($parts[0]);
        }, $manual_lines);

        // Parser le mois demandé
        $p = explode('-', $month);
        if (count($p) !== 2) return [];
        $year = (int) $p[0];
        $month_num = (int) $p[1];
        if ($month_num < 1 || $month_num > 12) return [];

        // Calculer le nombre de jours dans le mois
        $days = cal_days_in_month(CAL_GREGORIAN, $month_num, $year);
        $today = coworking_today_date();

        $availability = [];

        // Parcourir chaque jour du mois
        for ($d = 1; $d <= $days; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month_num, $d);

            // Vérifier si le jour est passé (on inclut aujourd'hui comme passé)
            $is_past = ($date <= $today);
            
            // Vérifier si le jour est bloqué manuellement
            $is_manual = in_array($date, $manual_block);

            $reserved = count_reservations_for_date($date, $reservations);
            $slots = max(0, $capacity - $reserved);

            $status = 'available';

            if ($is_past || $is_manual) {
                $status = 'unavailable';
            } elseif ($slots == 0) {
                $status = 'full';
            } elseif ($slots <= 2) {
                $status = 'low';
            }

            $availability[$date] = [
                'date' => $date,
                'status' => $status,
                'slots' => $slots,
                'capacity' => $capacity,
                'is_past' => $is_past
            ];
        }

        return $availability;
    }
}

/* ------------------------------------------------------------
   REST API — AVAILABILITY
------------------------------------------------------------ */

add_action('rest_api_init', function() {
    register_rest_route('coworking/v1', '/availability/(?P<offre_id>\d+)', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function(WP_REST_Request $req) {

            $offre_id = (int) $req->get_param('offre_id');
            $month = $req->get_param('month') ?: date('Y-m');

            if (!$offre_id) {
                return new WP_REST_Response(['success' => false, 'message' => 'Offre manquante'], 400);
            }

            $availability = coworking_get_availability_for_month($offre_id, $month);

            return new WP_REST_Response([
                'success' => true,
                'offre_id' => $offre_id,
                'month' => $month,
                'availability' => $availability,
                'prix' => [
                    'journee' => (float) get_field('prix_journee', $offre_id),
                    'semaine' => (float) get_field('prix_semaine', $offre_id),
                    'mois'    => (float) get_field('prix_mois', $offre_id),
                ],
            ], 200);
        }
    ]);
});

/* ------------------------------------------------------------
   REST API — CALCULATE PRICE (AVEC QUANTITÉ)
------------------------------------------------------------ */

add_action('rest_api_init', function() {
    register_rest_route('coworking/v1', '/calculate-price', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function(WP_REST_Request $req) {

            $p = $req->get_json_params();

            $offre_id = (int) ($p['offre_id'] ?? 0);
            $formule  = sanitize_text_field($p['formule'] ?? '');
            $start    = sanitize_text_field($p['start_date'] ?? '');
            $quantity = (int) ($p['quantity'] ?? 1);

            if ($quantity < 1) $quantity = 1;

            if (!$offre_id || !$formule || !$start) {
                return new WP_REST_Response(['success' => false, 'message' => 'Paramètres manquants'], 400);
            }

            // Vérifier date minimum J+1
            if ($start <= coworking_today_date()) {
                return new WP_REST_Response(['success' => false, 'message' => 'La date doit être au minimum demain (J+1).'], 400);
            }

            // Calculer la date de fin selon formule et quantité
            $bloc_days = 1;
            switch ($formule) {
                case 'journee': $bloc_days = 1; break;
                case 'semaine': $bloc_days = 7; break;
                case 'mois': $bloc_days = 30; break;
            }

            $total_days = $bloc_days * $quantity;
            $end = date('Y-m-d', strtotime($start . ' + ' . ($total_days - 1) . ' days'));

            $ts_start = strtotime($start);
            $ts_end   = strtotime($end);

            // Collecter les mois nécessaires
            $months = [];
            $cur = strtotime(date('Y-m-01', $ts_start));
            $last = strtotime(date('Y-m-01', $ts_end));
            while ($cur <= $last) {
                $months[] = date('Y-m', $cur);
                $cur = strtotime('+1 month', $cur);
            }

            // Récupérer disponibilités
            $all = [];
            foreach ($months as $m) {
                $a = coworking_get_availability_for_month($offre_id, $m);
                $all = array_merge($all, $a);
            }

            // Vérifier chaque jour
            for ($t = $ts_start; $t <= $ts_end; $t += 86400) {
                $d = date('Y-m-d', $t);

                if (!isset($all[$d])) {
                    return new WP_REST_Response(['success' => false, 'message' => "Jour hors disponibilité : $d"], 400);
                }

                if ($all[$d]['slots'] <= 0) {
                    return new WP_REST_Response(['success' => false, 'message' => "Jour complet : " . date('d/m/Y', strtotime($d))], 400);
                }
            }

            // Calculer le prix
            $day   = (float) get_field('prix_journee', $offre_id);
            $week  = (float) get_field('prix_semaine', $offre_id);
            $month = (float) get_field('prix_mois', $offre_id);

            $unit_price = 0;
            switch ($formule) {
                case 'journee': $unit_price = $day; break;
                case 'semaine': $unit_price = $week ?: ($day * 5); break;
                case 'mois':    $unit_price = $month ?: ($week * 4); break;
                default:        $unit_price = 0;
            }

            $total_price = $unit_price * $quantity;

            return new WP_REST_Response([
                'success' => true,
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                'price' => $total_price,
                'range' => [$start, $end],
                'total_days' => $total_days
            ], 200);
        }
    ]);
});
