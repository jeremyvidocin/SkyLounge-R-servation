<?php

/**
 * =============================================================================
 * COWORKING ADMIN COLUMNS - PERSONNALISATION DU TABLEAU ADMIN
 * =============================================================================
 *
 * Personnalise l'affichage du tableau des réservations dans l'admin WordPress.
 * Remplace les colonnes par défaut par des informations métier pertinentes.
 *
 * COLONNES PERSONNALISÉES :
 * - Client : Nom du client (depuis _cw_customer_name)
 * - Dates : Période de réservation avec formatage français
 * - Formule : Badge coloré (journée/semaine/mois)
 * - Espace : Nom de l'offre réservée
 * - État : Statut de la réservation (Payé/En attente)
 * - Créée le : Date de création du CPT
 *
 * FONCTIONNALITÉS ADDITIONNELLES :
 * - Tri par dates de réservation
 * - Widget dashboard "Arrivées du jour"
 *
 * @package    SkyLounge_Coworking
 * @subpackage Admin
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    1.0.0
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : DÉFINITION DES COLONNES
   =============================================================================
   Remplace les colonnes par défaut du CPT cw_reservation.
============================================================================= */

/**
 * Définit les colonnes personnalisées pour le tableau admin des réservations.
 *
 * L'ordre des colonnes dans le tableau correspond à l'ordre de définition ici.
 * On conserve la colonne 'cb' (checkbox) pour les actions groupées.
 *
 * @since 1.0.0
 * @hook manage_cw_reservation_posts_columns
 *
 * @param array $columns Les colonnes par défaut WordPress.
 *
 * @return array Les colonnes personnalisées.
 */
add_filter('manage_cw_reservation_posts_columns', function($columns) {
    // Conserver uniquement la checkbox des actions groupées
    $new_columns = ['cb' => $columns['cb']];
    
    // Définir les colonnes métier dans l'ordre d'affichage souhaité
    $new_columns['client']  = 'Client';           // Nom du client
    $new_columns['dates']   = 'Dates';            // Période de réservation
    $new_columns['formule'] = 'Formule';          // Type de formule
    $new_columns['offre']   = 'Espace';           // Nom de l'offre
    $new_columns['status']  = 'État';             // Statut (Publié = Confirmé)
    $new_columns['date']    = 'Créée le';         // Date de création WordPress
    
    return $new_columns;
});

/* =============================================================================
   SECTION 2 : RENDU DES DONNÉES
   =============================================================================
   Affiche les données correspondant à chaque colonne personnalisée.
============================================================================= */

/**
 * Affiche le contenu de chaque colonne personnalisée.
 *
 * Switch sur le nom de la colonne pour déterminer quelles données afficher.
 * Utilise les post_meta stockées lors de la création de la réservation.
 *
 * @since 1.0.0
 * @hook manage_cw_reservation_posts_custom_column
 *
 * @param string $column  Le nom de la colonne à afficher.
 * @param int    $post_id L'ID du post (réservation) en cours.
 */
add_action('manage_cw_reservation_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        
        // Colonne Client : Nom en gras
        case 'client':
            $name = get_post_meta($post_id, '_cw_customer_name', true);
            echo '<strong>' . esc_html($name ?: 'Inconnu') . '</strong>';
            break;

        // Colonne Dates : Format "Du XX/XX au XX/XX/XXXX"
        case 'dates':
            $start = get_post_meta($post_id, '_cw_start', true);
            $end   = get_post_meta($post_id, '_cw_end', true);
            if ($start) {
                echo 'Du ' . date_i18n('d/m', strtotime($start));
                if ($start !== $end) {
                    echo ' au ' . date_i18n('d/m/Y', strtotime($end));
                } else {
                    echo ' (1 jour)';
                }
            }
            break;

        // Colonne Formule : Badge coloré selon le type
        case 'formule':
            $f = get_post_meta($post_id, '_cw_formule', true);
            
            // Couleurs des badges selon la formule
            // Journée = bleu clair, Semaine = vert clair, Mois = jaune clair
            $color = ($f === 'journee') 
                ? '#e0f2fe;color:#0369a1'  // Bleu
                : (($f === 'semaine') 
                    ? '#f0fdf4;color:#15803d'  // Vert
                    : '#fefce8;color:#a16207'); // Jaune
                    
            echo '<span style="background:' . $color . ';padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase;">' . esc_html($f) . '</span>';
            break;

        // Colonne Espace : Nom de l'offre
        case 'offre':
            echo esc_html(get_post_meta($post_id, '_cw_offre_name', true));
            break;
            
        // Colonne État : Publié = Payé, sinon En attente
        case 'status':
            if (get_post_status($post_id) === 'publish') {
                echo '<span style="color:#10b981;font-weight:bold;">✅ Payé & Validé</span>';
            } else {
                echo '<span style="color:#ef4444;">En attente</span>';
            }
            break;
    }
}, 10, 2);

/* =============================================================================
   SECTION 3 : COLONNES TRIABLES
   =============================================================================
   Permet de trier le tableau par certaines colonnes.
============================================================================= */

/**
 * Définit les colonnes triables du tableau admin.
 *
 * @since 1.0.0
 * @hook manage_edit-cw_reservation_sortable_columns
 *
 * @param array $columns Les colonnes triables existantes.
 *
 * @return array Les colonnes avec 'dates' ajoutée comme triable.
 */
add_filter('manage_edit-cw_reservation_sortable_columns', function($columns) {
    $columns['dates'] = 'dates';
    return $columns;
});

/* =============================================================================
   SECTION 4 : WIDGET DASHBOARD
   =============================================================================
   Widget "Arrivées du jour" affiché sur le tableau de bord WordPress.
============================================================================= */

/**
 * Enregistre le widget dashboard "Arrivées du jour".
 *
 * @since 1.0.0
 * @hook wp_dashboard_setup
 */
add_action('wp_dashboard_setup', function() {
    add_meta_box(
        'cw_dashboard_widget_today',
        '📅 Coworking : Arrivées du jour',
        'cw_render_dashboard_widget',
        'dashboard',
        'side',
        'high'
    );
});

/**
 * Affiche le contenu du widget dashboard.
 *
 * Recherche les réservations dont la date de début est aujourd'hui
 * et affiche le nom du client avec l'offre réservée.
 *
 * @since 1.0.0
 */
function cw_render_dashboard_widget() {
    $today = date('Y-m-d');
    
    // Requête pour trouver les réservations commençant aujourd'hui
    // Ici on va chercher ceux qui COMMENCENT aujourd'hui pour faire simple, ou utiliser une meta_query plus complexe
    $args = [
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => '_cw_start',
                'value' => $today,
                'compare' => '<=',
                'type' => 'DATE'
            ],
            [
                'key' => '_cw_end',
                'value' => $today,
                'compare' => '>=',
                'type' => 'DATE'
            ]
        ]
    ];
    
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        echo '<ul style="margin:0;padding:0;">';
        while ($query->have_posts()) {
            $query->the_post();
            $client = get_post_meta(get_the_ID(), '_cw_customer_name', true);
            $offre  = get_post_meta(get_the_ID(), '_cw_offre_name', true);
            echo '<li style="padding:8px 0;border-bottom:1px solid #eee;">';
            echo '<strong>👤 ' . esc_html($client) . '</strong><br>';
            echo '<span style="color:#666;font-size:12px;">📍 ' . esc_html($offre) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<p style="text-align:right;margin-top:10px;"><a href="edit.php?post_type=cw_reservation">Voir tout →</a></p>';
    } else {
        echo '<p style="color:#666;">Aucune arrivée prévue aujourd\'hui.</p>';
    }
    wp_reset_postdata();
}
