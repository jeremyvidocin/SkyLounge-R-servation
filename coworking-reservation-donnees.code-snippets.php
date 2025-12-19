<?php

/**
 * =============================================================================
 * COWORKING RESERVATION DATA - FRONTEND DATA PROVIDER
 * =============================================================================
 *
 * Ce module fournit les données de réservation au frontend JavaScript via un
 * shortcode WordPress. Il sert de pont entre les données ACF (backend) et
 * l'application de calendrier (frontend).
 *
 * FONCTIONNEMENT :
 * Le shortcode [coworking_resa] injecte dans la page :
 * 1. Un conteneur HTML (#coworking-app) pour le calendrier React/Vanilla
 * 2. Un objet JavaScript (window.COWORKING_DATA) avec toutes les données
 *
 * DONNÉES EXPOSÉES :
 * - post_id        : ID de l'offre coworking
 * - title          : Nom de l'espace
 * - formules       : Liste des formules disponibles (journee, semaine, mois)
 * - capacite       : Nombre de places maximum
 * - prix           : Tarifs par formule
 * - blocked_dates  : Dates déjà réservées (depuis reservations_json)
 *
 * SÉCURITÉ :
 * - Le shortcode ne fonctionne que sur les CPT 'offre-coworking'
 * - Les données sont encodées en JSON pour éviter les injections XSS
 * - Les dates bloquées sont en lecture seule (pas de modification possible)
 *
 * DÉPENDANCES :
 * - ACF Pro : Champs personnalisés de l'offre
 * - calendrier-coworking-v2.php : Application JavaScript frontend
 * - systeme-disponibilite.php : API REST pour les disponibilités dynamiques
 *
 * @package    SkyLounge_Coworking
 * @subpackage Frontend
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    1.0.0
 *
 * @see calendrier-coworking-v2.code-snippets.php  Application calendrier frontend
 * @see systeme-disponibilite.code-snippets.php    API REST disponibilités
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SHORTCODE [coworking_resa]
   =============================================================================
   Injecte les données de réservation et le conteneur pour l'application JS.
============================================================================= */

/**
 * Shortcode pour afficher l'interface de réservation coworking.
 *
 * Usage : [coworking_resa]
 *
 * Ce shortcode doit être placé dans le template single-offre-coworking.php
 * ou ajouté via un bloc Gutenberg sur les pages d'offres.
 *
 * @since 1.0.0
 *
 * @return string HTML du conteneur + données JavaScript.
 *
 * @example
 * // Dans un template PHP :
 * echo do_shortcode('[coworking_resa]');
 *
 * // Données injectées dans window.COWORKING_DATA :
 * // {
 * //   "post_id": 42,
 * //   "title": "Open Space Premium",
 * //   "formules": ["journee", "semaine"],
 * //   "capacite": 10,
 * //   "prix": {"demi_journee": 25, "journee": 45, "semaine": 200},
 * //   "blocked_dates": [{"date": "2025-01-15", "count": 2}, ...]
 * // }
 */
add_shortcode('coworking_resa', function() {
    if (!is_singular('offre-coworking')) {
        return "<p>Ce module ne peut être affiché que sur une offre coworking.</p>";
    }

    $post_id = get_the_ID();

    $data = [
        "post_id" => $post_id,
        "title" => get_the_title($post_id),
        "formules" => get_field('formules_disponibles', $post_id) ?: [],
        "capacite" => intval(get_field('capacite_max', $post_id)),
        "prix" => [
            "demi_journee" => get_field('prix_demi_journee', $post_id),
            "journee" => get_field('prix_journee', $post_id),
            "semaine" => get_field('prix_semaine', $post_id),
        ],
        "blocked_dates" => json_decode(get_field('reservations_json', $post_id) ?: "[]", true)
    ];

    return '<div id="coworking-app"></div>
            <script>window.COWORKING_DATA = '.json_encode($data).';</script>';
});
