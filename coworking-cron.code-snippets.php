<?php

/**
 * =============================================================================
 * COWORKING CRON - MAINTENANCE AUTOMATIQUE
 * =============================================================================
 *
 * Tâches de maintenance planifiées exécutées quotidiennement à 03h00.
 * Assure la cohérence des données et le respect de la conformité RGPD.
 *
 * TÂCHES EFFECTUÉES :
 * 1. Nettoyage des locks expirés (transients)
 * 2. Suppression des locks orphelins (commandes annulées/échec)
 * 3. Suppression des brouillons cw_reservation > 24h
 * 4. Réparation du JSON reservations_json si incohérences
 * 5. Vérification cohérence JSON/CPT (log seulement)
 * 6. Anonymisation RGPD des réservations > 3 ans
 *
 * PLANIFICATION :
 * - Exécution : Quotidienne à 03h00 (heure serveur)
 * - Hook WordPress : 'coworking_daily_maintenance'
 * - Durée moyenne : < 30 secondes
 *
 * LOGS :
 * - Tous les logs sont envoyés vers wp-content/debug.log si WP_DEBUG_LOG actif
 * - Format : "Coworking CRON: [message]"
 *
 * @package    SkyLounge_Coworking
 * @subpackage Maintenance
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    2.0.0
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : ENREGISTREMENT DU CRON
   =============================================================================
   Planifie la tâche de maintenance si elle n'existe pas encore.
============================================================================= */

/**
 * Enregistre l'événement CRON quotidien au chargement de WordPress.
 *
 * Utilise wp_next_scheduled() pour éviter les doublons.
 * L'heure de 03h00 est choisie pour minimiser l'impact sur les utilisateurs.
 *
 * @since 1.0.0
 * @hook init
 */
add_action('init', function() {
    if (!wp_next_scheduled('coworking_daily_maintenance')) {
        wp_schedule_event(strtotime('03:00:00'), 'daily', 'coworking_daily_maintenance');
    }
});

/* =============================================================================
   SECTION 2 : ROUTINE PRINCIPALE
   =============================================================================
   Fonction orchestratrice qui appelle toutes les sous-tâches.
============================================================================= */

/**
 * Exécute la routine de maintenance quotidienne.
 *
 * Appelle séquentiellement toutes les fonctions de nettoyage.
 * Chaque fonction est autonome et peut échouer sans bloquer les autres.
 *
 * @since 1.0.0
 * @hook coworking_daily_maintenance
 */
add_action('coworking_daily_maintenance', 'coworking_run_daily_maintenance');

function coworking_run_daily_maintenance() {
    // 1. Nettoyer les locks temporaires expirés
    coworking_clean_expired_locks();
    
    // 2. Nettoyer les locks orphelins (sans commande associée)
    coworking_clean_orphaned_locks();
    
    // 3. Supprimer les brouillons de réservation abandonnés
    coworking_clean_old_drafts();
    
    // 4. Réparer les incohérences dans le JSON des offres
    coworking_repair_reservations_json();
    
    // 5. Vérifier la cohérence JSON/CPT (log d'alerte seulement)
    coworking_check_json_cpt_coherence();
    
    // 6. Anonymiser les données personnelles > 3 ans (RGPD)
    coworking_anonymize_old_reservations();

    error_log("Coworking CRON: Maintenance quotidienne exécutée avec succès");
}

/* =============================================================================
   SECTION 3 : NETTOYAGE DES LOCKS EXPIRÉS
   =============================================================================
   Les transients WordPress peuvent persister même après expiration.
   Cette fonction les supprime définitivement.
============================================================================= */

/**
 * Supprime les transients de locks dont le timeout est dépassé.
 *
 * Requête directe sur la table wp_options pour trouver les transients
 * 'cw_locks_*' dont le timeout est inférieur au timestamp actuel.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb Instance de la base de données WordPress.
 */
function coworking_clean_expired_locks() {
    global $wpdb;

    // Rechercher tous les timeouts de locks coworking
    $pattern = '_transient_timeout_cw_locks_%';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value 
             FROM $wpdb->options 
             WHERE option_name LIKE %s",
            $pattern
        )
    );

    $now = time();
    $cleaned = 0;

    foreach ($rows as $row) {
        $expires_at = intval($row->option_value);
        
        // Si le timestamp d'expiration est passé, supprimer le transient
        if ($expires_at < $now) {
            $key = str_replace('_transient_timeout_', '', $row->option_name);
            delete_transient($key);
            $cleaned++;
        }
    }

    if ($cleaned > 0) {
        error_log("Coworking CRON: $cleaned locks expirés supprimés");
    }
}

/* =============================================================================
   SECTION 4 : NETTOYAGE DES LOCKS ORPHELINS
   =============================================================================
   Un lock devient "orphelin" quand la commande WooCommerce associée
   est annulée, échoue ou est supprimée. Ces locks bloquent inutilement
   des créneaux.
============================================================================= */

/**
 * Supprime les locks qui n'ont plus de commande WooCommerce associée.
 *
 * Pour chaque lock, vérifie si :
 * - Le token existe dans une commande WooCommerce active
 * - Le token existe dans les items de commande
 *
 * Si aucune correspondance n'est trouvée, le lock est considéré orphelin.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb Instance de la base de données WordPress.
 */
function coworking_clean_orphaned_locks() {
    global $wpdb;
    
    // Récupérer tous les transients de locks
    $results = $wpdb->get_results(
        "SELECT option_name, option_value 
         FROM $wpdb->options 
         WHERE option_name LIKE '_transient_cw_locks_%'"
    );
    
    if (empty($results)) return;
    
    $orphaned_count = 0;
    
    foreach ($results as $row) {
        // Extraire l'ID de l'offre
        preg_match('/cw_locks_(\d+)/', $row->option_name, $matches);
        $offre_id = isset($matches[1]) ? intval($matches[1]) : 0;
        if (!$offre_id) continue;
        
        $locks = maybe_unserialize($row->option_value);
        if (!is_array($locks)) continue;
        
        $valid_locks = [];
        $now = time();
        
        foreach ($locks as $lock) {
            // Vérifier si le lock est encore valide temporellement
            if (!isset($lock['expires_at']) || $lock['expires_at'] <= $now) {
                continue; // Déjà expiré, sera nettoyé par clean_expired_locks
            }
            
            $token = $lock['token'] ?? '';
            if (!$token) {
                continue; // Lock sans token, probablement corrompu
            }
            
            // Vérifier si une commande WooCommerce existe avec ce token
            $order_exists = false;
            
            // Recherche dans les commandes WooCommerce
            $orders = wc_get_orders([
                'status' => ['pending', 'processing', 'completed', 'on-hold'],
                'limit' => 1,
                'meta_query' => [[
                    'key' => '_cw_lock_token',
                    'value' => $token,
                    'compare' => 'EXISTS'
                ]]
            ]);
            
            if (!empty($orders)) {
                // Une commande existe avec ce token, garder le lock
                $valid_locks[] = $lock;
                continue;
            }
            
            // Vérifier aussi dans les items de commande
            global $wpdb;
            $item_check = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->prefix}woocommerce_order_itemmeta 
                 WHERE meta_key = '_cw_lock_token' 
                 AND meta_value = %s",
                $token
            ));
            
            if ($item_check > 0) {
                $valid_locks[] = $lock;
            } else {
                // Aucune commande trouvée, lock orphelin
                $orphaned_count++;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Lock orphelin supprimé: offre $offre_id, token $token, dates " . ($lock['start'] ?? '') . " - " . ($lock['end'] ?? ''));
                }
            }
        }
        
        // Mettre à jour le transient avec seulement les locks valides
        if (count($valid_locks) !== count($locks)) {
            if (!empty($valid_locks)) {
                set_transient('cw_locks_' . $offre_id, $valid_locks, 15 * MINUTE_IN_SECONDS);
            } else {
                delete_transient('cw_locks_' . $offre_id);
            }
        }
    }
    
    if ($orphaned_count > 0) {
        error_log("Coworking CRON: $orphaned_count locks orphelins nettoyés");
    }
}

/* ------------------------------------------------------------
   4) Supprimer les brouillons cw_reservation > 24h
------------------------------------------------------------*/

function coworking_clean_old_drafts() {
    $threshold = strtotime('-24 hours');
    $old = get_posts([
        'post_type'      => 'cw_reservation',
        'post_status'    => 'draft',
        'date_query'     => [['before' => gmdate('Y-m-d H:i:s', $threshold)]],
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ]);

    if (!empty($old)) {
        foreach ($old as $id) {
            wp_delete_post($id, true);
        }
        error_log("Coworking CRON: ".count($old)." brouillons supprimés");
    }
}

/* ------------------------------------------------------------
   5) Réparer automatiquement reservations_json
   Version améliorée: vérifie aussi la cohérence avec WooCommerce
------------------------------------------------------------*/

function coworking_repair_reservations_json() {
    $offres = get_posts([
        'post_type'      => 'offre-coworking',
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ]);

    if (empty($offres)) return;

    foreach ($offres as $id) {
        $json = get_field('reservations_json', $id);

        // Si pas de JSON, initialiser vide
        if (!$json) {
            $json = '[]';
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            update_field('reservations_json', json_encode([], JSON_PRETTY_PRINT), $id);
            error_log("Coworking CRON: JSON réparé (corrompu) pour offre $id");
            $data = [];
        }

        $original_count = count($data);
        $changes_made = false;

        // 1. Nettoyage des entrées invalides (format)
        $data = array_filter($data, function($r) {
            return isset($r['start'], $r['end'], $r['order']) &&
                   preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['start']) &&
                   preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['end']);
        });

        // 2. Vérifier que chaque réservation correspond à une commande WC valide
        $data = array_filter($data, function($r) {
            $order_id = intval($r['order'] ?? 0);
            if (!$order_id) return false;

            $order = wc_get_order($order_id);
            if (!$order) return false;

            // Garder seulement si la commande est dans un statut valide
            $valid_statuses = ['processing', 'completed', 'on-hold'];
            return in_array($order->get_status(), $valid_statuses);
        });

        // 3. S'assurer que quantity = 1 (nombre d'espaces, pas durée)
        $data = array_map(function($r) {
            $r['quantity'] = 1; // Toujours 1 espace par réservation
            return $r;
        }, $data);

        $data = array_values($data);

        if (count($data) !== $original_count) {
            update_field('reservations_json', json_encode($data, JSON_PRETTY_PRINT), $id);
            $removed = $original_count - count($data);
            error_log("Coworking CRON: JSON nettoyé pour offre $id ($removed entrées invalides/orphelines supprimées)");
        }
    }
}

/* ------------------------------------------------------------
   5b) Vérification de cohérence JSON <-> CPT (alerte seulement)
   Ne corrige pas automatiquement pour éviter les pertes de données
------------------------------------------------------------*/

function coworking_check_json_cpt_coherence() {
    $offres = get_posts([
        'post_type'      => 'offre-coworking',
        'posts_per_page' => -1,
        'fields'         => 'ids'
    ]);

    $issues = [];

    foreach ($offres as $offre_id) {
        // Réservations dans le JSON
        $json = get_field('reservations_json', $offre_id) ?: '[]';
        $json_reservations = json_decode($json, true) ?: [];
        $json_orders = array_column($json_reservations, 'order');

        // Réservations dans les CPT
        $cpt_posts = get_posts([
            'post_type' => 'cw_reservation',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => '_cw_offre_id', 'value' => $offre_id]
            ],
            'fields' => 'ids'
        ]);

        $cpt_orders = [];
        foreach ($cpt_posts as $cpt_id) {
            $order_id = get_post_meta($cpt_id, '_cw_order_id', true);
            if ($order_id) $cpt_orders[] = (int)$order_id;
        }

        // Trouver les différences
        $in_json_not_cpt = array_diff($json_orders, $cpt_orders);
        $in_cpt_not_json = array_diff($cpt_orders, $json_orders);

        if (!empty($in_json_not_cpt) || !empty($in_cpt_not_json)) {
            $issues[] = [
                'offre_id' => $offre_id,
                'offre_name' => get_the_title($offre_id),
                'in_json_not_cpt' => $in_json_not_cpt,
                'in_cpt_not_json' => $in_cpt_not_json
            ];
        }
    }

    if (!empty($issues)) {
        error_log("Coworking COHERENCE: " . count($issues) . " offres avec désynchronisation JSON/CPT détectées");
        foreach ($issues as $issue) {
            error_log("  - Offre {$issue['offre_id']} ({$issue['offre_name']}): " .
                count($issue['in_json_not_cpt']) . " dans JSON pas CPT, " .
                count($issue['in_cpt_not_json']) . " dans CPT pas JSON");
        }
    }

    return $issues;
}

/* ------------------------------------------------------------
   6) RGPD - Anonymiser les réservations > 3 ans
------------------------------------------------------------*/

function coworking_anonymize_old_reservations() {
    $threshold = strtotime('-3 years');
    
    $old_reservations = get_posts([
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'date_query' => [
            ['before' => gmdate('Y-m-d H:i:s', $threshold)]
        ],
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);
    
    if (empty($old_reservations)) return;
    
    $anonymized_count = 0;
    
    foreach ($old_reservations as $resa_id) {
        $current_email = get_post_meta($resa_id, '_cw_customer_email', true);
        if ($current_email === 'anonyme@rgpd.local') continue;
        
        update_post_meta($resa_id, '_cw_customer_name', 'Client anonymisé (RGPD)');
        update_post_meta($resa_id, '_cw_customer_email', 'anonyme@rgpd.local');
        update_post_meta($resa_id, '_cw_anonymized_date', current_time('mysql'));
        
        $anonymized_count++;
    }
    
    if ($anonymized_count > 0) {
        error_log("RGPD: $anonymized_count réservations anonymisées automatiquement");
    }
}

/* ------------------------------------------------------------
   7) Fonction manuelle pour forcer le nettoyage (admin)
------------------------------------------------------------*/

add_action('wp_ajax_cw_force_cleanup', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Non autorisé');
    }
    
    check_ajax_referer('cw_admin_nonce', 'nonce');
    
    coworking_clean_expired_locks();
    coworking_clean_orphaned_locks();
    coworking_clean_old_drafts();
    coworking_repair_reservations_json();
    
    wp_send_json_success([
        'message' => 'Nettoyage manuel exécuté avec succès',
        'logs' => 'Voir les logs WordPress pour les détails'
    ]);
});
