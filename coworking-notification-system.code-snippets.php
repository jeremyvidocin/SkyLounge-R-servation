<?php

/**
 * =============================================================================
 * COWORKING NOTIFICATION SYSTEM
 * =============================================================================
 *
 * Système de notifications temps réel pour les administrateurs.
 * Affiche des indicateurs visuels pour les nouvelles réservations
 * et les actions importantes.
 *
 * FONCTIONNALITÉS :
 * - Badge de notification dans le menu admin (réservations < 6h)
 * - Notification dans la barre admin (front-end, réservations < 3h)
 * - Widget dashboard "Arrivées du jour/demain"
 * - Styles CSS personnalisés pour les badges
 * - Cache transient pour optimiser les performances
 *
 * INDICATEURS :
 * - Menu Planning : Badge rouge avec compteur (cache 5 min)
 * - Barre admin : Lien cliquable avec icône calendrier (cache 2 min)
 * - Dashboard : Liste des arrivées aujourd'hui et demain
 *
 * @package    SkyLounge_Coworking
 * @subpackage Notifications
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    1.0.0
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : BADGE MENU ADMIN
   =============================================================================
   Ajoute un badge rouge au menu "Planning" quand il y a des nouvelles
   réservations dans les dernières 6 heures.
============================================================================= */

/**
 * Ajoute un badge de notification au menu Planning dans l'admin.
 *
 * Le compteur affiche le nombre de réservations créées dans les 6 dernières
 * heures. Le résultat est mis en cache pendant 5 minutes pour éviter les
 * requêtes SQL répétées à chaque chargement de page admin.
 *
 * @since 1.0.0
 * @hook admin_menu (priorité 999 pour s'exécuter après la création du menu)
 *
 * @global array $menu Le tableau des menus WordPress admin.
 * @global wpdb  $wpdb Instance de la base de données WordPress.
 */
add_action('admin_menu', 'cw_add_menu_notification_badge', 999);

function cw_add_menu_notification_badge() {
    global $menu, $wpdb;
    
    // Limiter aux administrateurs
    if (!current_user_can('manage_options')) return;
    
    // Vérifier le cache (durée : 5 minutes)
    $count = get_transient('cw_menu_badge_count');
    
    if (false === $count) {
        // Compter les réservations des dernières 6 heures
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->posts} p 
             WHERE p.post_type = 'cw_reservation' 
             AND p.post_status = 'publish' 
             AND p.post_date >= %s",
            date('Y-m-d H:i:s', strtotime('-6 hours'))
        ));
        
        // Mettre en cache le résultat
        set_transient('cw_menu_badge_count', $count, 5 * MINUTE_IN_SECONDS);
    }
    
    // Ajouter le badge HTML au menu Planning
    foreach ($menu as $key => $item) {
        if ($item[2] === 'cw-planning' && $count > 0) {
            $menu[$key][0] .= " <span class='update-plugins count-{$count}' style='background:#d63638;'>
                                  <span class='plugin-count'>{$count}</span>
                               </span>";
            break;
        }
    }
}

/* =============================================================================
   SECTION 2 : NOTIFICATION BARRE ADMIN
   =============================================================================
   Affiche une notification dans la barre admin WordPress (front-end).
   Utile quand l'admin navigue sur le site public.
============================================================================= */

/**
 * Ajoute une notification dans la barre admin WordPress.
 *
 * Affiche un lien vers le planning si des réservations ont été créées
 * dans les 3 dernières heures. Visible uniquement sur le front-end
 * (is_admin() = false) pour éviter les doublons avec le badge menu.
 *
 * @since 1.0.0
 * @hook admin_bar_menu (priorité 100)
 *
 * @param WP_Admin_Bar $admin_bar L'instance de la barre admin.
 */
add_action('admin_bar_menu', 'cw_admin_bar_notification', 100);

function cw_admin_bar_notification($admin_bar) {
    // Limiter aux admins naviguant sur le front-end
    if (!current_user_can('manage_options') || is_admin()) return;
    
    // Vérifier le cache (durée : 2 minutes)
    $recent_count = get_transient('cw_admin_bar_count');
    
    if (false === $recent_count) {
        // Compter les réservations des dernières 3 heures
        $recent_count = get_posts([
            'post_type'      => 'cw_reservation',
            'post_status'    => 'publish',
            'date_query'     => [['after' => '-3 hours']],
            'posts_per_page' => -1,
            'fields'         => 'ids'
        ]);
        
        $recent_count = count($recent_count);
        set_transient('cw_admin_bar_count', $recent_count, 2 * MINUTE_IN_SECONDS);
    }
    
    // Ajouter le nœud à la barre admin si des réservations existent
    if ($recent_count > 0) {
        $admin_bar->add_node([
            'id'    => 'cw-new-reservations',
            'title' => sprintf(
                '<span class="ab-icon dashicons-calendar-alt" style="margin-top:2px;"></span>
                 <span class="ab-label">%d nouvelle(s)</span>',
                $recent_count
            ),
            'href'  => admin_url('admin.php?page=cw-planning'),
            'meta'  => [
                'title' => 'Voir les nouvelles réservations',
                'class' => 'cw-admin-notification'
            ]
        ]);
    }
}

/* =============================================================================
   SECTION 3 : STYLES CSS DES NOTIFICATIONS
   =============================================================================
   Injecte les styles CSS nécessaires pour les badges et notifications.
============================================================================= */

/**
 * Injecte les styles CSS pour les notifications.
 *
 * @since 1.0.0
 * @hook admin_head, wp_head
 */
add_action('admin_head', 'cw_notification_styles');
add_action('wp_head', 'cw_notification_styles');

function cw_notification_styles() {
    ?>
    <style>
    /* Style pour le badge dans le menu */
    #adminmenu .toplevel_page_cw-planning .update-plugins {
        background: #d63638 !important;
        border-radius: 10px;
        min-width: 18px;
        height: 18px;
        line-height: 18px;
        margin-left: 5px;
        vertical-align: top;
    }
    
    #adminmenu .toplevel_page_cw-planning .update-plugins .plugin-count {
        font-size: 11px;
        line-height: 18px;
    }
    
    /* Style pour la barre admin */
    #wp-admin-bar-cw-new-reservations {
        background: #d63638 !important;
        animation: pulse 2s infinite;
    }
    
    #wp-admin-bar-cw-new-reservations .ab-item {
        color: #fff !important;
    }
    
    #wp-admin-bar-cw-new-reservations:hover {
        background: #c62828 !important;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
    
    /* Widget dashboard */
    .cw-urgent-resa {
        border-left: 4px solid #d63638 !important;
        background: #fff5f5 !important;
    }
    
    .cw-today-resa {
        border-left: 4px solid #10b981 !important;
        background: #f0fdf4 !important;
    }
    </style>
    <?php
}

/* ============================================================
   4. WIDGET DASHBOARD AMÉLIORÉ
============================================================ */

add_action('wp_dashboard_setup', function() {
    add_meta_box(
        'cw_dashboard_widget_enhanced',
        '📅 Coworking - Aperçu des Réservations',
        'cw_render_enhanced_dashboard_widget',
        'dashboard',
        'side',
        'high'
    );
});

function cw_render_enhanced_dashboard_widget() {
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    // Arrivées aujourd'hui
    $today_arrivals = get_posts([
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => 5,
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
        ],
        'orderby' => 'meta_value',
        'order' => 'ASC'
    ]);
    
    // Arrivées demain
    $tomorrow_arrivals = get_posts([
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => '_cw_start',
                'value' => $tomorrow,
                'compare' => '<=',
                'type' => 'DATE'
            ],
            [
                'key' => '_cw_end',
                'value' => $tomorrow,
                'compare' => '>=',
                'type' => 'DATE'
            ]
        ],
        'orderby' => 'meta_value',
        'order' => 'ASC'
    ]);
    
    echo '<div style="margin-bottom: 15px;">';
    
    // Aujourd'hui
    if (!empty($today_arrivals)) {
        echo '<div class="cw-today-resa" style="padding: 10px; margin-bottom: 10px; border-radius: 4px;">';
        echo '<h3 style="margin-top: 0; color: #065f46;">🟢 Arrivées aujourd'hui</h3>';
        echo '<ul style="margin: 0; padding: 0;">';
        foreach ($today_arrivals as $resa) {
            $client = get_post_meta($resa->ID, '_cw_customer_name', true);
            $offre = get_post_meta($resa->ID, '_cw_offre_name', true);
            echo '<li style="padding: 5px 0; border-bottom: 1px solid #e5e7eb;">';
            echo '<strong>👤 ' . esc_html($client) . '</strong><br>';
            echo '<small style="color: #6b7280;">📍 ' . esc_html($offre) . '</small>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<p style="color: #6b7280; text-align: center; padding: 10px;">✅ Aucune arrivée aujourd\'hui</p>';
    }
    
    // Demain
    if (!empty($tomorrow_arrivals)) {
        echo '<div style="padding: 10px; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;">';
        echo '<h3 style="margin-top: 0; color: #1e40af;">🔵 Arrivées demain</h3>';
        echo '<ul style="margin: 0; padding: 0;">';
        foreach ($tomorrow_arrivals as $resa) {
            $client = get_post_meta($resa->ID, '_cw_customer_name', true);
            $offre = get_post_meta($resa->ID, '_cw_offre_name', true);
            echo '<li style="padding: 5px 0; border-bottom: 1px solid #dbeafe;">';
            echo '<strong>👤 ' . esc_html($client) . '</strong><br>';
            echo '<small style="color: #6b7280;">📍 ' . esc_html($offre) . '</small>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    echo '</div>';
    
    // Lien vers planning
    echo '<p style="text-align: center; margin-top: 15px; border-top: 1px solid #e5e7eb; padding-top: 10px;">';
    echo '<a href="' . admin_url('admin.php?page=cw-planning') . '" class="button button-primary" style="width: 100%; text-align: center;">';
    echo '📊 Voir le planning complet';
    echo '</a>';
    echo '</p>';
}

/* ============================================================
   5. RESET DU COMPTEUR QUAND ON VISITE LA PAGE PLANNING
============================================================ */

add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'cw-planning') {
        // Reset les compteurs
        delete_transient('cw_menu_badge_count');
        delete_transient('cw_admin_bar_count');
    }
});
