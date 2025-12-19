<?php

/**
 * =============================================================================
 * DATE HELPER - FONCTION UTILITAIRE TIMEZONE-AWARE
 * =============================================================================
 *
 * Fonction helper globale pour obtenir la date du jour en respectant
 * le fuseau horaire configuré dans WordPress.
 *
 * IMPORTANCE :
 * Cette fonction DOIT être utilisée partout où une date "aujourd'hui" est
 * nécessaire au lieu de date('Y-m-d'). Cela garantit la cohérence des
 * dates sur tout le système, même si le serveur est dans un autre fuseau.
 *
 * POURQUOI NE PAS UTILISER date() DIRECTEMENT ?
 * - date() utilise le timezone du serveur (souvent UTC)
 * - current_time() utilise le timezone WordPress (Réglages > Général)
 * - Pour Paris, UTC+1/UTC+2, cela peut créer un décalage d'un jour
 *
 * FONCTIONNEMENT :
 * - current_time('timestamp') : Retourne le timestamp ajusté au timezone WP
 * - date_i18n('Y-m-d', ...)   : Formate la date localement
 *
 * UTILISATION :
 * ```php
 * $today = coworking_today_date();  // '2025-01-15'
 *
 * // Comparaison de dates
 * if ($reservation_date >= coworking_today_date()) {
 *     // Réservation future ou aujourd'hui
 * }
 * ```
 *
 * @package    SkyLounge_Coworking
 * @subpackage Helpers
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    1.0.0
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne la date du jour au format Y-m-d (timezone WordPress).
 *
 * @since 1.0.0
 *
 * @return string La date du jour au format 'YYYY-MM-DD'.
 *
 * @example
 * $today = coworking_today_date();
 * // '2025-01-15'
 */
if (!function_exists('coworking_today_date')) {
    function coworking_today_date() {
        return date_i18n('Y-m-d', current_time('timestamp'));
    }
}
