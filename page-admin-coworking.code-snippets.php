<?php

/**
 * =============================================================================
 * PAGE ADMIN COWORKING - TABLEAU DE BORD PREMIUM
 * =============================================================================
 *
 * Interface d'administration complète pour la gestion des réservations
 * coworking. Design moderne inspiré de Cal.com, Linear et Notion.
 *
 * FONCTIONNALITÉS :
 *
 * 1. DASHBOARD "AUJOURD'HUI"
 *    - Vue des réservations du jour
 *    - Statistiques en temps réel (occupations, revenus)
 *    - Alertes et notifications importantes
 *
 * 2. PLANNING MENSUEL
 *    - Calendrier avec vue globale des occupations
 *    - Code couleur par espace/offre
 *    - Navigation mois par mois
 *    - Export CSV des données
 *
 * 3. LISTE DES RÉSERVATIONS
 *    - Tableau filtrable et triable
 *    - Recherche par client, date, espace
 *    - Actions rapides (voir, modifier, annuler)
 *    - Pagination avancée
 *
 * 4. GESTION DES OFFRES
 *    - Vue des espaces et leurs tarifs
 *    - Taux d'occupation par espace
 *    - Lien vers l'édition ACF
 *
 * 5. STATISTIQUES & RAPPORTS
 *    - Graphiques de revenus (semaine, mois, année)
 *    - Taux d'occupation moyen
 *    - Top clients et espaces populaires
 *    - Export PDF des rapports
 *
 * DESIGN SYSTEM :
 * - Variables CSS pour la cohérence
 * - Sidebar fixe avec navigation
 * - Cards avec ombres et bordures subtiles
 * - Responsive (adapté aux tablettes)
 *
 * SÉCURITÉ :
 * - Capability 'manage_options' requise
 * - Sanitization de tous les inputs
 * - Nonces AJAX pour les actions
 *
 * @package    SkyLounge_Coworking
 * @subpackage Admin
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    2.0.0
 *
 * @see coworking-admin-columns.php  Colonnes personnalisées du CPT
 * @see coworking-admin-metabox.php  Metabox de détails réservation
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : MENU WORDPRESS
   =============================================================================
   Ajout du menu "Planning" dans l'administration WordPress.
============================================================================= */

/**
 * Ajoute le menu principal "Planning Coworking" dans l'admin.
 *
 * Position : 3 (juste après le Dashboard)
 * Icône : dashicons-calendar-alt
 * Capability : manage_options (admin uniquement)
 *
 * @since 1.0.0
 * @hook admin_menu
 */
add_action('admin_menu', function() {
    add_menu_page(
        'Planning Coworking',      // Titre de la page
        'Planning',                // Texte du menu
        'manage_options',          // Capability requise
        'cw-planning',             // Slug du menu
        'cw_render_premium_dashboard', // Fonction de rendu
        'dashicons-calendar-alt',  // Icône
        3                          // Position (après Dashboard)
    );
});

/* =============================================================================
   SECTION 2 : RENDU DU DASHBOARD
   =============================================================================
   Génération de l'interface d'administration premium.
============================================================================= */

/**
 * Affiche le tableau de bord complet de gestion coworking.
 *
 * Gère les différents onglets :
 * - today    : Réservations du jour
 * - calendar : Planning mensuel
 * - list     : Liste des réservations
 * - stats    : Statistiques et rapports
 *
 * @since 1.0.0
 *
 * @global $_GET['tab'] Onglet actif (default: 'today')
 */
function cw_render_premium_dashboard() {
    // Récupération de l'onglet actif avec sanitization
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'today';
    ?>
    
    <!-- Application Admin Coworking -->
    <div class="cw-app">
        
        <!-- =================================================================
             CSS DU DASHBOARD - DESIGN SYSTEM PREMIUM
             ================================================================= -->
        <style>
            /* ============================================
               RESET & VARIABLES CSS
            ============================================ */
            .cw-app {
                /* Couleurs principales */
                --color-primary: #276890;
                --color-primary-hover: #1e5270;
                --color-primary-light: #e8f4fc;
                
                /* États */
                --color-success: #10b981;
                --color-success-light: #d1fae5;
                --color-warning: #f59e0b;
                --color-warning-light: #fef3c7;
                --color-danger: #ef4444;
                --color-danger-light: #fee2e2;
                
                /* Nuances de gris */
                --color-gray-50: #f9fafb;
                --color-gray-100: #f3f4f6;
                --color-gray-200: #e5e7eb;
                --color-gray-300: #d1d5db;
                --color-gray-400: #9ca3af;
                --color-gray-500: #6b7280;
                --color-gray-600: #4b5563;
                --color-gray-700: #374151;
                --color-gray-800: #1f2937;
                --color-gray-900: #111827;
                
                /* Layout */
                --sidebar-width: 240px;
                --header-height: 64px;
                
                /* Rayons et ombres */
                --radius-sm: 6px;
                --radius-md: 8px;
                --radius-lg: 12px;
                --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
                --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
                --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
                --transition: all 0.2s ease;

                /* Typographie */
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                
                /* Layout principal */
                background: var(--color-gray-50);
                margin: -20px -20px 0 -20px;
                padding: 0;
                min-height: 100vh;
                display: flex;
            }

            /* ============================================
               SIDEBAR - Navigation latérale
            ============================================ */
            .cw-sidebar {
                width: var(--sidebar-width);
                min-width: var(--sidebar-width);
                background: #fff;
                border-right: 1px solid var(--color-gray-200);
                display: flex;
                flex-direction: column;
                height: calc(100vh - 32px); /* Hauteur moins admin bar */
                position: sticky;
                top: 32px; /* Colle sous l'admin bar WordPress */
                overflow-y: auto;
            }

            .cw-sidebar-header {
                padding: 24px;
                border-bottom: 1px solid var(--color-gray-100);
            }

            .cw-sidebar-logo {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .cw-sidebar-logo-icon {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, var(--color-primary) 0%, #3b82f6 100%);
                border-radius: var(--radius-md);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-size: 20px;
            }

            .cw-sidebar-logo-text {
                font-size: 18px;
                font-weight: 700;
                color: var(--color-gray-900);
            }

            .cw-sidebar-logo-sub {
                font-size: 12px;
                color: var(--color-gray-500);
                font-weight: 400;
            }

            /* Search */
            .cw-sidebar-search {
                padding: 16px 20px;
            }

            .cw-search-input {
                width: 100%;
                padding: 10px 12px 10px 36px;
                border: 1px solid var(--color-gray-200);
                border-radius: var(--radius-md);
                font-size: 14px;
                background: var(--color-gray-50);
                transition: var(--transition);
            }

            .cw-search-input:focus {
                outline: none;
                border-color: var(--color-primary);
                background: #fff;
                box-shadow: 0 0 0 3px var(--color-primary-light);
            }

            .cw-search-wrapper {
                position: relative;
            }

            .cw-search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--color-gray-400);
                font-size: 14px;
            }

            /* Navigation */
            .cw-sidebar-nav {
                flex: 1;
                padding: 8px 12px;
                overflow-y: auto;
            }

            .cw-nav-section {
                margin-bottom: 24px;
            }

            .cw-nav-section-title {
                font-size: 11px;
                font-weight: 600;
                color: var(--color-gray-400);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding: 8px 12px;
                margin-bottom: 4px;
            }

            .cw-nav-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 12px;
                border-radius: var(--radius-md);
                color: var(--color-gray-600);
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                transition: var(--transition);
                cursor: pointer;
                border: none;
                background: none;
                width: 100%;
                text-align: left;
            }

            .cw-nav-item:hover {
                background: var(--color-gray-100);
                color: var(--color-gray-900);
            }

            .cw-nav-item.active {
                background: var(--color-primary-light);
                color: var(--color-primary);
            }

            .cw-nav-item-icon {
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }

            .cw-nav-item-badge {
                margin-left: auto;
                background: var(--color-danger);
                color: #fff;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 10px;
            }

            .cw-nav-item-badge.warning {
                background: var(--color-warning);
            }

            .cw-nav-item-badge.success {
                background: var(--color-success);
            }

            /* Sidebar Footer */
            .cw-sidebar-footer {
                padding: 16px 20px;
                border-top: 1px solid var(--color-gray-100);
            }

            .cw-sidebar-user {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .cw-sidebar-avatar {
                width: 36px;
                height: 36px;
                background: var(--color-gray-200);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
                font-weight: 600;
                color: var(--color-gray-600);
            }

            .cw-sidebar-user-info {
                flex: 1;
            }

            .cw-sidebar-user-name {
                font-size: 14px;
                font-weight: 600;
                color: var(--color-gray-800);
            }

            .cw-sidebar-user-role {
                font-size: 12px;
                color: var(--color-gray-500);
            }

            /* ============================================
               MAIN CONTENT
            ============================================ */
            .cw-main {
                flex: 1;
                padding: 24px 32px;
                min-width: 0; /* Fix pour le flex overflow */
                overflow-x: hidden;
            }

            /* Header */
            .cw-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 24px;
            }

            .cw-header-left h1 {
                font-size: 28px;
                font-weight: 700;
                color: var(--color-gray-900);
                margin: 0 0 4px 0;
            }

            .cw-header-left p {
                font-size: 14px;
                color: var(--color-gray-500);
                margin: 0;
            }

            .cw-header-actions {
                display: flex;
                gap: 12px;
            }

            /* KPI Cards */
            .cw-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }

            .cw-kpi-card {
                background: #fff;
                border-radius: var(--radius-lg);
                padding: 20px;
                border: 1px solid var(--color-gray-200);
            }

            .cw-kpi-label {
                font-size: 12px;
                font-weight: 500;
                color: var(--color-gray-500);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
            }

            .cw-kpi-value {
                font-size: 32px;
                font-weight: 700;
                color: var(--color-gray-900);
                line-height: 1;
            }

            .cw-kpi-sub {
                font-size: 13px;
                color: var(--color-gray-500);
                margin-top: 8px;
            }

            .cw-kpi-trend {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 12px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 4px;
            }

            .cw-kpi-trend.up {
                background: var(--color-success-light);
                color: var(--color-success);
            }

            .cw-kpi-trend.down {
                background: var(--color-danger-light);
                color: var(--color-danger);
            }

            /* Cards */
            .cw-card {
                background: #fff;
                border-radius: var(--radius-lg);
                border: 1px solid var(--color-gray-200);
                margin-bottom: 24px;
                overflow: hidden;
            }

            .cw-card-header {
                padding: 20px 24px;
                border-bottom: 1px solid var(--color-gray-100);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .cw-card-title {
                font-size: 16px;
                font-weight: 600;
                color: var(--color-gray-900);
                margin: 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .cw-card-title-icon {
                font-size: 18px;
            }

            .cw-card-body {
                padding: 24px;
            }

            .cw-card-body.no-padding {
                padding: 0;
            }

            /* Badges */
            .cw-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
            }

            .cw-badge.success { background: var(--color-success-light); color: #065f46; }
            .cw-badge.warning { background: var(--color-warning-light); color: #92400e; }
            .cw-badge.danger { background: var(--color-danger-light); color: #991b1b; }
            .cw-badge.info { background: var(--color-primary-light); color: var(--color-primary); }
            .cw-badge.neutral { background: var(--color-gray-100); color: var(--color-gray-600); }

            /* Buttons */
            .cw-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 10px 18px;
                border: none;
                border-radius: var(--radius-md);
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: var(--transition);
                text-decoration: none;
            }

            .cw-btn-primary {
                background: var(--color-primary);
                color: #fff;
            }

            .cw-btn-primary:hover {
                background: var(--color-primary-hover);
                color: #fff;
            }

            .cw-btn-secondary {
                background: var(--color-gray-100);
                color: var(--color-gray-700);
            }

            .cw-btn-secondary:hover {
                background: var(--color-gray-200);
                color: var(--color-gray-900);
            }

            .cw-btn-danger {
                background: var(--color-danger-light);
                color: #991b1b;
            }

            .cw-btn-danger:hover {
                background: #fecaca;
            }

            .cw-btn-ghost {
                background: transparent;
                color: var(--color-gray-600);
            }

            .cw-btn-ghost:hover {
                background: var(--color-gray-100);
            }

            .cw-btn-sm {
                padding: 6px 12px;
                font-size: 13px;
            }

            /* Tables */
            .cw-table {
                width: 100%;
                border-collapse: collapse;
            }

            .cw-table thead th {
                background: var(--color-gray-50);
                padding: 12px 16px;
                text-align: left;
                font-size: 12px;
                font-weight: 600;
                color: var(--color-gray-500);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid var(--color-gray-200);
            }

            .cw-table tbody td {
                padding: 16px;
                border-bottom: 1px solid var(--color-gray-100);
                font-size: 14px;
                color: var(--color-gray-700);
            }

            .cw-table tbody tr:hover {
                background: var(--color-gray-50);
            }

            .cw-table tbody tr:last-child td {
                border-bottom: none;
            }

            .cw-table-client {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .cw-table-avatar {
                width: 36px;
                height: 36px;
                background: linear-gradient(135deg, var(--color-primary) 0%, #3b82f6 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-size: 14px;
                font-weight: 600;
            }

            .cw-table-client-info {
                display: flex;
                flex-direction: column;
            }

            .cw-table-client-name {
                font-weight: 600;
                color: var(--color-gray-900);
            }

            .cw-table-client-email {
                font-size: 13px;
                color: var(--color-gray-500);
            }

            /* Forms */
            .cw-form-grid {
                display: grid;
                gap: 20px;
            }

            .cw-form-grid-2 { grid-template-columns: repeat(2, 1fr); }
            .cw-form-grid-3 { grid-template-columns: repeat(3, 1fr); }
            .cw-form-grid-4 { grid-template-columns: repeat(4, 1fr); }

            .cw-form-group {
                display: flex;
                flex-direction: column;
            }

            .cw-form-label {
                font-size: 13px;
                font-weight: 600;
                color: var(--color-gray-700);
                margin-bottom: 8px;
            }

            .cw-form-input,
            .cw-form-select {
                padding: 10px 14px;
                border: 1px solid var(--color-gray-300);
                border-radius: var(--radius-md);
                font-size: 14px;
                transition: var(--transition);
                background: #fff;
            }

            .cw-form-input:focus,
            .cw-form-select:focus {
                outline: none;
                border-color: var(--color-primary);
                box-shadow: 0 0 0 3px var(--color-primary-light);
            }

            .cw-form-input:read-only {
                background: var(--color-gray-50);
                color: var(--color-gray-600);
            }

            .cw-form-help {
                font-size: 12px;
                color: var(--color-gray-500);
                margin-top: 6px;
            }

            /* Alerts */
            .cw-alert {
                padding: 16px 20px;
                border-radius: var(--radius-md);
                margin-bottom: 20px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }

            .cw-alert-icon {
                font-size: 18px;
                flex-shrink: 0;
            }

            .cw-alert-content strong {
                display: block;
                margin-bottom: 2px;
            }

            .cw-alert-content p {
                margin: 0;
                font-size: 13px;
                opacity: 0.9;
            }

            .cw-alert-info {
                background: var(--color-primary-light);
                color: var(--color-primary);
            }

            .cw-alert-warning {
                background: var(--color-warning-light);
                color: #92400e;
            }

            .cw-alert-danger {
                background: var(--color-danger-light);
                color: #991b1b;
            }

            .cw-alert-success {
                background: var(--color-success-light);
                color: #065f46;
            }

            /* Empty State */
            .cw-empty {
                text-align: center;
                padding: 48px 24px;
            }

            .cw-empty-icon {
                font-size: 48px;
                margin-bottom: 16px;
                opacity: 0.5;
            }

            .cw-empty-title {
                font-size: 16px;
                font-weight: 600;
                color: var(--color-gray-700);
                margin: 0 0 8px 0;
            }

            .cw-empty-text {
                font-size: 14px;
                color: var(--color-gray-500);
                margin: 0;
            }

            /* Collapsible */
            .cw-collapsible-header {
                cursor: pointer;
                user-select: none;
            }

            .cw-collapsible-header:hover {
                background: var(--color-gray-50);
            }

            .cw-collapsible-icon {
                transition: transform 0.2s;
            }

            .cw-collapsible.open .cw-collapsible-icon {
                transform: rotate(180deg);
            }

            /* Calendar View */
            .cw-calendar-filters {
                display: flex;
                gap: 12px;
                align-items: flex-end;
                margin-bottom: 20px;
            }

            /* Animations */
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .cw-animate-in {
                animation: fadeIn 0.3s ease;
            }

            /* Toast Notifications */
            .cw-toast {
                position: fixed;
                top: 48px;
                right: 24px;
                background: #fff;
                padding: 16px 20px;
                border-radius: var(--radius-md);
                box-shadow: var(--shadow-lg);
                border-left: 4px solid var(--color-success);
                z-index: 9999;
                animation: slideIn 0.3s ease;
            }

            @keyframes slideIn {
                from { opacity: 0; transform: translateX(20px); }
                to { opacity: 1; transform: translateX(0); }
            }

            /* Responsive */
            @media (max-width: 1400px) {
                .cw-kpi-grid {
                    grid-template-columns: repeat(2, 1fr);
                }

                .cw-sidebar {
                    --sidebar-width: 220px;
                    width: var(--sidebar-width);
                    min-width: var(--sidebar-width);
                }
            }

            @media (max-width: 1100px) {
                .cw-app {
                    flex-direction: column;
                }

                .cw-sidebar {
                    width: 100%;
                    min-width: 100%;
                    height: auto;
                    position: relative;
                    top: 0;
                    border-right: none;
                    border-bottom: 1px solid var(--color-gray-200);
                }

                .cw-sidebar-nav {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    padding: 12px 16px;
                }

                .cw-nav-section {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-bottom: 0;
                }

                .cw-nav-section-title {
                    display: none;
                }

                .cw-nav-item {
                    padding: 8px 12px;
                    font-size: 13px;
                }

                .cw-sidebar-header,
                .cw-sidebar-search,
                .cw-sidebar-footer {
                    display: none;
                }

                .cw-main {
                    padding: 20px;
                }

                .cw-kpi-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media (max-width: 768px) {
                .cw-kpi-grid {
                    grid-template-columns: 1fr;
                }

                .cw-form-grid-2,
                .cw-form-grid-3,
                .cw-form-grid-4 {
                    grid-template-columns: 1fr;
                }

                .cw-header {
                    flex-direction: column;
                    gap: 16px;
                }

                .cw-header-actions {
                    width: 100%;
                }

                .cw-header-actions .cw-btn {
                    width: 100%;
                    justify-content: center;
                }

                .cw-table {
                    font-size: 13px;
                }

                .cw-table thead th,
                .cw-table tbody td {
                    padding: 10px 8px;
                }

                .cw-table-avatar {
                    width: 32px;
                    height: 32px;
                    font-size: 12px;
                }
            }
        </style>

        <!-- SIDEBAR -->
        <aside class="cw-sidebar">
            <div class="cw-sidebar-header">
                <div class="cw-sidebar-logo">
                    <div class="cw-sidebar-logo-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div>
                        <div class="cw-sidebar-logo-text">Coworking</div>
                        <div class="cw-sidebar-logo-sub">Gestion des espaces</div>
                    </div>
                </div>
            </div>

            <div class="cw-sidebar-search">
                <div class="cw-search-wrapper">
                    <span class="cw-search-icon">🔍</span>
                    <input type="text" class="cw-search-input" id="cw-global-search" placeholder="Rechercher un client...">
                    <div id="cw-search-results"></div>
                </div>
            </div>

            <nav class="cw-sidebar-nav">
                <div class="cw-nav-section">
                    <div class="cw-nav-section-title">Réservations</div>
                    <?php
                    $today_count = cw_get_today_arrivals_count();
                    $locks_count = cw_get_active_locks_count();
                    ?>
                    <a href="?page=cw-planning&tab=today" class="cw-nav-item <?php echo $current_tab === 'today' ? 'active' : ''; ?>">
                        <span class="cw-nav-item-icon">📍</span>
                        <span>Aujourd'hui</span>
                        <?php if ($today_count > 0): ?>
                            <span class="cw-nav-item-badge success"><?php echo $today_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?page=cw-planning&tab=upcoming" class="cw-nav-item <?php echo $current_tab === 'upcoming' ? 'active' : ''; ?>">
                        <span class="cw-nav-item-icon">📅</span>
                        <span>À venir</span>
                    </a>
                    <a href="?page=cw-planning&tab=new" class="cw-nav-item <?php echo $current_tab === 'new' ? 'active' : ''; ?>">
                        <span class="cw-nav-item-icon">➕</span>
                        <span>Nouvelle résa</span>
                    </a>
                    <?php if ($locks_count > 0): ?>
                    <a href="?page=cw-planning&tab=locks" class="cw-nav-item <?php echo $current_tab === 'locks' ? 'active' : ''; ?>">
                        <span class="cw-nav-item-icon">⏳</span>
                        <span>En cours</span>
                        <span class="cw-nav-item-badge warning"><?php echo $locks_count; ?></span>
                    </a>
                    <?php endif; ?>
                </div>

                <div class="cw-nav-section">
                    <div class="cw-nav-section-title">Planning</div>
                    <a href="?page=cw-planning&tab=calendar" class="cw-nav-item <?php echo $current_tab === 'calendar' ? 'active' : ''; ?>">
                        <span class="cw-nav-item-icon">📊</span>
                        <span>Calendrier</span>
                    </a>
                    <a href="?page=cw-planning&tab=blocks" class="cw-nav-item <?php echo $current_tab === 'blocks' ? 'active' : ''; ?>">
                        <span class="cw-nav-item-icon">🚫</span>
                        <span>Blocages</span>
                    </a>
                </div>

                <div class="cw-nav-section">
                    <div class="cw-nav-section-title">Administration</div>
                    <a href="?page=cw-planning&tab=stats" class="cw-nav-item <?php echo $current_tab === 'stats' ? 'active' : ''; ?>">
                        <span class="cw-nav-item-icon">📈</span>
                        <span>Statistiques</span>
                    </a>
                    <a href="?page=cw-planning&tab=maintenance" class="cw-nav-item <?php echo $current_tab === 'maintenance' ? 'active' : ''; ?>">
                        <span class="cw-nav-item-icon">⚙️</span>
                        <span>Maintenance</span>
                    </a>
                </div>
            </nav>

            <div class="cw-sidebar-footer">
                <?php $current_user = wp_get_current_user(); ?>
                <div class="cw-sidebar-user">
                    <div class="cw-sidebar-avatar">
                        <?php echo strtoupper(substr($current_user->display_name, 0, 2)); ?>
                    </div>
                    <div class="cw-sidebar-user-info">
                        <div class="cw-sidebar-user-name"><?php echo esc_html($current_user->display_name); ?></div>
                        <div class="cw-sidebar-user-role">Administrateur</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="cw-main">
            <?php
            // Afficher les KPIs sur la page d'accueil
            if ($current_tab === 'today') {
                cw_render_kpi_cards();
            }

            // Router vers la bonne section
            switch ($current_tab) {
                case 'today':
                    cw_render_section_today();
                    break;
                case 'upcoming':
                    cw_render_section_upcoming();
                    break;
                case 'new':
                    cw_render_section_new_booking();
                    break;
                case 'locks':
                    cw_render_section_locks();
                    break;
                case 'calendar':
                    cw_render_section_calendar();
                    break;
                case 'blocks':
                    cw_render_section_blocks();
                    break;
                case 'stats':
                    cw_render_section_stats();
                    break;
                case 'maintenance':
                    cw_render_section_maintenance();
                    break;
                default:
                    cw_render_section_today();
            }
            ?>
        </main>

        <!-- Search Results (positionné dans le wrapper de recherche) -->
        <style>
            .cw-search-wrapper {
                position: relative;
            }
            #cw-search-results {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                border: 1px solid var(--color-gray-200);
                border-radius: var(--radius-md);
                box-shadow: var(--shadow-lg);
                max-height: 300px;
                overflow-y: auto;
                z-index: 1000;
                margin-top: 4px;
            }
            #cw-search-results a:hover {
                background: var(--color-gray-50);
            }
        </style>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Global Search
        let searchTimeout;
        $('#cw-global-search').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();

            if (query.length < 2) {
                $('#cw-search-results').hide();
                return;
            }

            searchTimeout = setTimeout(function() {
                $.post(ajaxurl, {
                    action: 'cw_search_clients',
                    query: query,
                    nonce: '<?php echo wp_create_nonce('cw_search_nonce'); ?>'
                }, function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '';
                        response.data.forEach(function(item) {
                            html += `<a href="${item.edit_url}" style="display:block;padding:12px 16px;border-bottom:1px solid var(--color-gray-100);text-decoration:none;color:var(--color-gray-700);">
                                <strong style="color:var(--color-gray-900);">${item.name}</strong><br>
                                <span style="font-size:13px;color:var(--color-gray-500);">${item.email} • ${item.offre} • ${item.dates}</span>
                            </a>`;
                        });
                        $('#cw-search-results').html(html).show();
                    } else {
                        $('#cw-search-results').html('<div style="padding:16px;color:var(--color-gray-500);text-align:center;">Aucun résultat</div>').show();
                    }
                });
            }, 300);
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.cw-sidebar-search').length) {
                $('#cw-search-results').hide();
            }
        });

        // Auto-refresh notifications
        let lastCount = <?php echo wp_count_posts('cw_reservation')->publish; ?>;

        function checkNewReservations() {
            $.post(ajaxurl, {
                action: 'cw_check_new_reservations',
                last_count: lastCount
            }, function(response) {
                if (response.success && response.data.has_new) {
                    lastCount = response.data.new_count;
                    showToast('Nouvelle réservation !', 'Une nouvelle réservation vient d\'être effectuée.');
                }
            });
        }

        function showToast(title, message) {
            const toast = $(`<div class="cw-toast">
                <strong style="color:var(--color-gray-900);">${title}</strong>
                <p style="margin:4px 0 0 0;font-size:13px;color:var(--color-gray-600);">${message}</p>
            </div>`);

            $('body').append(toast);
            setTimeout(() => toast.fadeOut(300, function() { $(this).remove(); }), 5000);
        }

        setInterval(checkNewReservations, 60000);
    });
    </script>
    <?php
}

/* ============================================
   HELPER FUNCTIONS
============================================ */

function cw_get_today_arrivals_count() {
    $today = date('Y-m-d');
    $args = [
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => '_cw_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE'],
            ['key' => '_cw_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE']
        ]
    ];
    $query = new WP_Query($args);
    return $query->found_posts;
}

function cw_get_active_locks_count() {
    global $wpdb;
    $locks_data = $wpdb->get_results("SELECT option_value FROM $wpdb->options WHERE option_name LIKE '_transient_cw_locks_%'");
    $count = 0;
    $now = time();

    foreach ($locks_data as $row) {
        $locks = maybe_unserialize($row->option_value);
        if (!is_array($locks)) continue;
        foreach ($locks as $lock) {
            if (isset($lock['expires_at']) && $lock['expires_at'] > $now) $count++;
        }
    }
    return $count;
}

function cw_get_initials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: '??';
}

/* ============================================
   KPI CARDS
============================================ */

function cw_render_kpi_cards() {
    // CA du mois
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $ca_month = 0;
    $reservations_month = 0;

    $args = [
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            ['key' => '_cw_start', 'value' => [$month_start, $month_end], 'compare' => 'BETWEEN', 'type' => 'DATE']
        ]
    ];
    $query = new WP_Query($args);
    while ($query->have_posts()) {
        $query->the_post();
        $ca_month += floatval(get_post_meta(get_the_ID(), '_cw_price', true));
        $reservations_month++;
    }
    wp_reset_postdata();

    // Taux d'occupation (simplifié)
    $offres = get_posts(['post_type' => 'offre-coworking', 'posts_per_page' => -1]);
    $total_slots = 0;
    $used_slots = 0;
    $today = date('Y-m-d');

    foreach ($offres as $offre) {
        $capacity = (int) get_field('capacite_max', $offre->ID) ?: 1;
        $total_slots += $capacity;

        $json = get_field('reservations_json', $offre->ID) ?: '[]';
        $reservations = json_decode($json, true) ?: [];

        foreach ($reservations as $r) {
            if (isset($r['start'], $r['end']) && $today >= $r['start'] && $today <= $r['end']) {
                $used_slots += ($r['quantity'] ?? 1);
            }
        }
    }

    $occupation_rate = $total_slots > 0 ? round(($used_slots / $total_slots) * 100) : 0;

    // Prochaines arrivées
    $next_arrivals = 0;
    $next_week = date('Y-m-d', strtotime('+7 days'));
    $args = [
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            ['key' => '_cw_start', 'value' => [$today, $next_week], 'compare' => 'BETWEEN', 'type' => 'DATE']
        ]
    ];
    $query = new WP_Query($args);
    $next_arrivals = $query->found_posts;
    wp_reset_postdata();
    ?>

    <div class="cw-kpi-grid cw-animate-in">
        <div class="cw-kpi-card">
            <div class="cw-kpi-label">CA du mois</div>
            <div class="cw-kpi-value"><?php echo number_format($ca_month, 0, ',', ' '); ?> €</div>
            <div class="cw-kpi-sub"><?php echo $reservations_month; ?> réservation(s)</div>
        </div>

        <div class="cw-kpi-card">
            <div class="cw-kpi-label">Occupation aujourd'hui</div>
            <div class="cw-kpi-value"><?php echo $occupation_rate; ?>%</div>
            <div class="cw-kpi-sub"><?php echo $used_slots; ?> / <?php echo $total_slots; ?> places</div>
        </div>

        <div class="cw-kpi-card">
            <div class="cw-kpi-label">Arrivées du jour</div>
            <div class="cw-kpi-value"><?php echo cw_get_today_arrivals_count(); ?></div>
            <div class="cw-kpi-sub">client(s) présent(s)</div>
        </div>

        <div class="cw-kpi-card">
            <div class="cw-kpi-label">Cette semaine</div>
            <div class="cw-kpi-value"><?php echo $next_arrivals; ?></div>
            <div class="cw-kpi-sub">arrivée(s) à venir</div>
        </div>
    </div>
    <?php
}

/* ============================================
   SECTION: TODAY
============================================ */

function cw_render_section_today() {
    $today = date('Y-m-d');
    $args = [
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => '_cw_start', 'value' => $today, 'compare' => '<=', 'type' => 'DATE'],
            ['key' => '_cw_end', 'value' => $today, 'compare' => '>=', 'type' => 'DATE']
        ]
    ];
    $query = new WP_Query($args);
    ?>

    <div class="cw-header">
        <div class="cw-header-left">
            <h1>Aujourd'hui</h1>
            <p><?php echo date_i18n('l j F Y'); ?></p>
        </div>
        <div class="cw-header-actions">
            <a href="?page=cw-planning&tab=new" class="cw-btn cw-btn-primary">
                <span>➕</span> Nouvelle réservation
            </a>
        </div>
    </div>

    <div class="cw-card cw-animate-in">
        <div class="cw-card-header">
            <h2 class="cw-card-title">
                <span class="cw-card-title-icon">📍</span>
                Clients présents
            </h2>
            <span class="cw-badge success"><?php echo $query->found_posts; ?> client(s)</span>
        </div>

        <?php if (!$query->have_posts()): ?>
            <div class="cw-card-body">
                <div class="cw-empty">
                    <div class="cw-empty-icon">☀️</div>
                    <h3 class="cw-empty-title">Aucune arrivée aujourd'hui</h3>
                    <p class="cw-empty-text">Profitez-en pour préparer les prochaines réservations</p>
                </div>
            </div>
        <?php else: ?>
            <div class="cw-card-body no-padding">
                <table class="cw-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Espace</th>
                            <th>Période</th>
                            <th>Formule</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($query->have_posts()): $query->the_post();
                            $post_id = get_the_ID();
                            $client = get_post_meta($post_id, '_cw_customer_name', true);
                            $email = get_post_meta($post_id, '_cw_customer_email', true);
                            $offre = get_post_meta($post_id, '_cw_offre_name', true);
                            $start = get_post_meta($post_id, '_cw_start', true);
                            $end = get_post_meta($post_id, '_cw_end', true);
                            $formule = get_post_meta($post_id, '_cw_formule', true);
                            $quantity = (int) get_post_meta($post_id, '_cw_quantity', true) ?: 1;
                            $order_id = get_post_meta($post_id, '_cw_order_id', true);
                        ?>
                            <tr>
                                <td>
                                    <div class="cw-table-client">
                                        <div class="cw-table-avatar"><?php echo cw_get_initials($client); ?></div>
                                        <div class="cw-table-client-info">
                                            <span class="cw-table-client-name"><?php echo esc_html($client); ?></span>
                                            <span class="cw-table-client-email"><?php echo esc_html($email); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($offre); ?></td>
                                <td>
                                    <?php
                                    echo date_i18n('d/m', strtotime($start));
                                    if ($start !== $end) echo ' → ' . date_i18n('d/m', strtotime($end));
                                    ?>
                                </td>
                                <td>
                                    <span class="cw-badge neutral">
                                        <?php
                                        if ($quantity > 1) {
                                            $unit = $formule === 'journee' ? 'j' : ($formule === 'semaine' ? 'sem' : 'mois');
                                            echo $quantity . ' ' . $unit;
                                        } else {
                                            echo esc_html(ucfirst($formule));
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order_id): ?>
                                        <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" class="cw-btn cw-btn-ghost cw-btn-sm">
                                            Voir commande
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
    wp_reset_postdata();
}

/* ============================================
   SECTION: UPCOMING
============================================ */

function cw_render_section_upcoming() {
    $today = date('Y-m-d');
    $next_month = date('Y-m-d', strtotime('+30 days'));

    $args = [
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_key' => '_cw_start',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => [
            ['key' => '_cw_start', 'value' => $today, 'compare' => '>=', 'type' => 'DATE']
        ]
    ];
    $query = new WP_Query($args);
    ?>

    <div class="cw-header">
        <div class="cw-header-left">
            <h1>Réservations à venir</h1>
            <p>Toutes les arrivées prévues</p>
        </div>
    </div>

    <div class="cw-card cw-animate-in">
        <div class="cw-card-header">
            <h2 class="cw-card-title">
                <span class="cw-card-title-icon">📅</span>
                Prochaines arrivées
            </h2>
            <span class="cw-badge info"><?php echo $query->found_posts; ?> réservation(s)</span>
        </div>

        <?php if (!$query->have_posts()): ?>
            <div class="cw-card-body">
                <div class="cw-empty">
                    <div class="cw-empty-icon">📭</div>
                    <h3 class="cw-empty-title">Aucune réservation à venir</h3>
                    <p class="cw-empty-text">Les nouvelles réservations apparaîtront ici</p>
                </div>
            </div>
        <?php else: ?>
            <div class="cw-card-body no-padding">
                <table class="cw-table">
                    <thead>
                        <tr>
                            <th>Date d'arrivée</th>
                            <th>Client</th>
                            <th>Espace</th>
                            <th>Formule</th>
                            <th>Prix</th>
                            <th>Contrat</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($query->have_posts()): $query->the_post();
                            $post_id = get_the_ID();
                            $client = get_post_meta($post_id, '_cw_customer_name', true);
                            $email = get_post_meta($post_id, '_cw_customer_email', true);
                            $offre = get_post_meta($post_id, '_cw_offre_name', true);
                            $start = get_post_meta($post_id, '_cw_start', true);
                            $end = get_post_meta($post_id, '_cw_end', true);
                            $formule = get_post_meta($post_id, '_cw_formule', true);
                            $quantity = (int) get_post_meta($post_id, '_cw_quantity', true) ?: 1;
                            $price = get_post_meta($post_id, '_cw_price', true);
                            $order_id = get_post_meta($post_id, '_cw_order_id', true);

                            // Contrat
                            $contract_number = $order_id ? get_post_meta($order_id, '_cw_contract_number', true) : '';
                            $contract_sent = $order_id ? get_post_meta($order_id, '_cw_contract_sent', true) : '';

                            // Comparer les dates à minuit pour éviter le décalage horaire
                            $today_midnight = strtotime(date('Y-m-d'));
                            $start_midnight = strtotime($start);
                            $days_until = (int)(($start_midnight - $today_midnight) / 86400);
                            $urgency_class = $days_until <= 1 ? 'danger' : ($days_until <= 3 ? 'warning' : 'info');
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo date_i18n('d/m/Y', strtotime($start)); ?></strong>
                                    <br><span class="cw-badge <?php echo $urgency_class; ?>" style="margin-top:4px;">
                                        <?php
                                        if ($days_until == 0) echo "Aujourd'hui";
                                        elseif ($days_until == 1) echo "Demain";
                                        else echo "J-" . $days_until;
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="cw-table-client">
                                        <div class="cw-table-avatar"><?php echo cw_get_initials($client); ?></div>
                                        <div class="cw-table-client-info">
                                            <span class="cw-table-client-name"><?php echo esc_html($client); ?></span>
                                            <span class="cw-table-client-email"><?php echo esc_html($email); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($offre); ?></td>
                                <td>
                                    <span class="cw-badge neutral">
                                        <?php
                                        if ($quantity > 1) {
                                            $unit = $formule === 'journee' ? 'j' : ($formule === 'semaine' ? 'sem' : 'mois');
                                            echo $quantity . ' ' . $unit;
                                        } else {
                                            echo esc_html(ucfirst($formule));
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><strong><?php echo wc_price($price); ?></strong></td>
                                <td>
                                    <?php if ($contract_number): ?>
                                        <span class="cw-badge success" title="Envoyé le <?php echo $contract_sent ? date_i18n('d/m/Y', strtotime($contract_sent)) : 'N/A'; ?>">
                                            <?php echo esc_html($contract_number); ?>
                                        </span>
                                    <?php elseif ($order_id && function_exists('cw_should_generate_contract') && cw_should_generate_contract($order_id)): ?>
                                        <span class="cw-badge warning">Requis</span>
                                    <?php else: ?>
                                        <span class="cw-badge neutral">CGV</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order_id): ?>
                                        <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" class="cw-btn cw-btn-ghost cw-btn-sm">Voir</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
    wp_reset_postdata();
}

/* ============================================
   SECTION: NEW BOOKING
============================================ */

function cw_render_section_new_booking() {
    $offres = get_posts(['post_type' => 'offre-coworking', 'posts_per_page' => -1]);
    ?>

    <div class="cw-header">
        <div class="cw-header-left">
            <h1>Nouvelle réservation</h1>
            <p>Créer une réservation suite à un appel téléphonique</p>
        </div>
    </div>

    <?php
    // Process form
    if (isset($_POST['submit_admin_booking']) && check_admin_referer('cw_admin_book', 'cw_admin_book_nonce')) {
        $offre_id = intval($_POST['adm_offre_id']);
        $start = sanitize_text_field($_POST['adm_start']);
        $end = sanitize_text_field($_POST['adm_end']);
        $name = sanitize_text_field($_POST['adm_client_name']);
        $email = sanitize_email($_POST['adm_client_email']) ?: 'admin-resa@local.com';
        $formule = sanitize_text_field($_POST['adm_formule']);
        $payment_method = sanitize_text_field($_POST['adm_payment_method']);
        $quantity = max(1, intval($_POST['adm_quantity'] ?? 1));

        $status = 'pending';
        $send_payment_link = false;

        switch ($payment_method) {
            case 'paid_phone': $status = 'completed'; break;
            case 'paid_on_site': $status = 'on-hold'; break;
            case 'send_link': $status = 'pending'; $send_payment_link = true; break;
        }

        // Calcul date de fin avec quantité
        $bloc_days = ['journee' => 1, 'semaine' => 7, 'mois' => 30];
        $total_days = ($bloc_days[$formule] ?? 1) * $quantity;
        $end = date('Y-m-d', strtotime($start . ' + ' . ($total_days - 1) . ' days'));

        if ($start <= date('Y-m-d')) {
            echo '<div class="cw-alert cw-alert-danger"><span class="cw-alert-icon">⚠️</span><div class="cw-alert-content"><strong>Erreur</strong><p>La date de début doit être au minimum demain (J+1)</p></div></div>';
        } elseif (function_exists('cw_create_admin_booking')) {
            $result = cw_create_admin_booking($offre_id, $formule, $start, $end, $name, $email, $status, '', '', $payment_method, $quantity);

            if ($result['success']) {
                $order_id = $result['order_id'];

                if ($send_payment_link && $email !== 'admin-resa@local.com') {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $pay_url = $order->get_checkout_payment_url();
                        $subject = 'Confirmez votre réservation - Lien de paiement';
                        $message = "Bonjour $name,\n\nVotre réservation a été créée !\n\n";
                        $message .= "📅 Dates : du " . date('d/m/Y', strtotime($start)) . " au " . date('d/m/Y', strtotime($end)) . "\n";
                        $message .= "💰 Montant : " . $order->get_total() . " €\n\n";
                        $message .= "Cliquez ici pour payer : $pay_url\n\n";
                        $message .= "Cordialement";

                        $sent = wp_mail($email, $subject, $message);

                        if ($sent) {
                            $order->add_order_note("📧 Email de paiement envoyé à $email");
                            echo '<div class="cw-alert cw-alert-success"><span class="cw-alert-icon">✅</span><div class="cw-alert-content"><strong>Réservation créée !</strong><p>Commande #' . $order_id . ' • Email envoyé à ' . esc_html($email) . '</p></div></div>';
                        } else {
                            echo '<div class="cw-alert cw-alert-warning"><span class="cw-alert-icon">⚠️</span><div class="cw-alert-content"><strong>Réservation créée</strong><p>Commande #' . $order_id . ' mais email non envoyé. <a href="' . esc_url($pay_url) . '" target="_blank">Lien de paiement</a></p></div></div>';
                        }
                    }
                } else {
                    $status_label = ($status === 'completed') ? '✅ Payée' : (($status === 'on-hold') ? '⏳ Paiement sur place' : '⏳ En attente');
                    echo '<div class="cw-alert cw-alert-success"><span class="cw-alert-icon">✅</span><div class="cw-alert-content"><strong>Réservation créée !</strong><p>Commande #' . $order_id . ' • ' . $status_label . '</p></div></div>';
                }
            } else {
                echo '<div class="cw-alert cw-alert-danger"><span class="cw-alert-icon">❌</span><div class="cw-alert-content"><strong>Erreur</strong><p>' . esc_html($result['message']) . '</p></div></div>';
            }
        }
    }
    ?>

    <div class="cw-card cw-animate-in">
        <div class="cw-card-header">
            <h2 class="cw-card-title">
                <span class="cw-card-title-icon">📞</span>
                Réservation téléphonique
            </h2>
        </div>

        <div class="cw-card-body">
            <form method="post" action="" id="cw-admin-booking-form">
                <?php wp_nonce_field('cw_admin_book', 'cw_admin_book_nonce'); ?>

                <div class="cw-form-grid cw-form-grid-2" style="margin-bottom:24px;">
                    <div class="cw-form-group">
                        <label class="cw-form-label">Nom complet du client *</label>
                        <input type="text" name="adm_client_name" required class="cw-form-input" placeholder="Jean Dupont">
                    </div>
                    <div class="cw-form-group">
                        <label class="cw-form-label">Email (optionnel)</label>
                        <input type="email" name="adm_client_email" class="cw-form-input" placeholder="client@email.com">
                        <p class="cw-form-help">Nécessaire pour envoyer un lien de paiement</p>
                    </div>
                </div>

                <?php
                // Préparer les prix pour chaque offre (pour le JavaScript)
                $offres_prices = [];
                foreach ($offres as $o) {
                    $offres_prices[$o->ID] = [
                        'journee' => (float) get_field('prix_journee', $o->ID),
                        'semaine' => (float) get_field('prix_semaine', $o->ID),
                        'mois'    => (float) get_field('prix_mois', $o->ID),
                    ];
                }
                ?>

                <div class="cw-form-grid cw-form-grid-2" style="margin-bottom:24px;">
                    <div class="cw-form-group">
                        <label class="cw-form-label">Espace *</label>
                        <select name="adm_offre_id" required class="cw-form-select" id="adm_offre_id">
                            <option value="">Sélectionner un espace...</option>
                            <?php foreach($offres as $o): ?>
                                <option value="<?php echo $o->ID; ?>"><?php echo esc_html($o->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cw-form-group">
                        <label class="cw-form-label">Formule *</label>
                        <select name="adm_formule" required class="cw-form-select" id="adm_formule">
                            <option value="">Sélectionner...</option>
                            <option value="journee">Journée</option>
                            <option value="semaine">Semaine (7 jours)</option>
                            <option value="mois">Mois (30 jours)</option>
                        </select>
                    </div>
                </div>

                <div class="cw-form-grid cw-form-grid-2" style="margin-bottom:24px;">
                    <div class="cw-form-group">
                        <label class="cw-form-label">Quantité *</label>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <button type="button" id="adm_qty_minus" class="cw-btn cw-btn-secondary" style="width:40px;height:40px;padding:0;font-size:20px;">−</button>
                            <input type="number" name="adm_quantity" id="adm_quantity" value="1" min="1" max="12" class="cw-form-input" style="width:80px;text-align:center;font-size:18px;font-weight:600;">
                            <button type="button" id="adm_qty_plus" class="cw-btn cw-btn-secondary" style="width:40px;height:40px;padding:0;font-size:20px;">+</button>
                            <span id="adm_qty_label" style="color:var(--color-gray-500);font-size:14px;">journée(s)</span>
                        </div>
                    </div>

                    <div class="cw-form-group">
                        <label class="cw-form-label">Mode de paiement *</label>
                        <select name="adm_payment_method" required class="cw-form-select" id="adm_payment_method">
                            <option value="">-- Choisir --</option>
                            <option value="paid_phone">Déjà payé (CB téléphone)</option>
                            <option value="paid_on_site">Paiement à l'arrivée</option>
                            <option value="send_link">Envoyer lien de paiement</option>
                        </select>
                        <p class="cw-form-help" id="payment-help"></p>
                    </div>
                </div>

                <div class="cw-form-grid cw-form-grid-2" style="margin-bottom:24px;">
                    <div class="cw-form-group">
                        <label class="cw-form-label">Date de début *</label>
                        <input type="date" name="adm_start" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" id="adm_start" class="cw-form-input">
                        <p class="cw-form-help">Minimum : demain (J+1)</p>
                    </div>

                    <div class="cw-form-group">
                        <label class="cw-form-label">Date de fin (auto)</label>
                        <input type="date" name="adm_end" required readonly id="adm_end" class="cw-form-input">
                        <p class="cw-form-help" id="date-info"></p>
                    </div>
                </div>

                <!-- Récapitulatif prix -->
                <div id="adm_price_summary" style="display:none;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:12px;padding:20px;margin-bottom:24px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                        <div>
                            <div style="font-size:14px;color:#166534;margin-bottom:4px;">Récapitulatif</div>
                            <div id="adm_price_details" style="font-size:15px;color:#15803d;"></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:14px;color:#166534;">Total à facturer</div>
                            <div id="adm_price_total" style="font-size:28px;font-weight:700;color:#166534;"></div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:12px;padding-top:16px;border-top:1px solid var(--color-gray-100);">
                    <a href="?page=cw-planning&tab=today" class="cw-btn cw-btn-secondary">Annuler</a>
                    <button type="submit" name="submit_admin_booking" class="cw-btn cw-btn-primary">
                        <span>✓</span> Créer la réservation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Prix des offres (injecté depuis PHP)
        var offresPrices = <?php echo json_encode($offres_prices); ?>;

        // Jours par bloc selon formule
        var blocDays = {
            'journee': 1,
            'semaine': 7,
            'mois': 30
        };

        // Labels pour quantité
        var qtyLabels = {
            'journee': 'journée(s)',
            'semaine': 'semaine(s)',
            'mois': 'mois'
        };

        // Gestion mode paiement
        $('#adm_payment_method').on('change', function() {
            const method = $(this).val();
            const help = {
                'paid_phone': '✅ La réservation sera confirmée immédiatement',
                'paid_on_site': '⏳ Le créneau sera bloqué, paiement à l\'arrivée',
                'send_link': '📧 Un email avec lien de paiement sera envoyé'
            };
            $('#payment-help').text(help[method] || '').css('color', method === 'paid_phone' ? '#10b981' : (method === 'send_link' ? '#3b82f6' : '#f59e0b'));
        });

        // Gestion boutons quantité
        $('#adm_qty_minus').on('click', function() {
            var qty = parseInt($('#adm_quantity').val()) || 1;
            if (qty > 1) {
                $('#adm_quantity').val(qty - 1);
                updateAll();
            }
        });

        $('#adm_qty_plus').on('click', function() {
            var qty = parseInt($('#adm_quantity').val()) || 1;
            if (qty < 12) {
                $('#adm_quantity').val(qty + 1);
                updateAll();
            }
        });

        $('#adm_quantity').on('change', function() {
            var qty = parseInt($(this).val()) || 1;
            if (qty < 1) qty = 1;
            if (qty > 12) qty = 12;
            $(this).val(qty);
            updateAll();
        });

        // Mise à jour du label quantité selon formule
        function updateQuantityLabel() {
            var formule = $('#adm_formule').val();
            $('#adm_qty_label').text(qtyLabels[formule] || 'unité(s)');
        }

        // Calcul date de fin avec quantité
        function calculateEndDate() {
            var startDate = $('#adm_start').val();
            var formule = $('#adm_formule').val();
            var quantity = parseInt($('#adm_quantity').val()) || 1;

            if (!startDate || !formule) {
                $('#adm_end').val('');
                $('#date-info').text('');
                return;
            }

            var parts = startDate.split('-');
            var start = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));

            // Total jours = bloc × quantité
            var totalDays = (blocDays[formule] || 1) * quantity;
            var end = new Date(start);
            end.setDate(start.getDate() + totalDays - 1);

            var endStr = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0');
            $('#adm_end').val(endStr);
            $('#date-info').text(totalDays + ' jour(s)').css('color', '#10b981');
        }

        // Calcul et affichage du prix
        function calculatePrice() {
            var offreId = $('#adm_offre_id').val();
            var formule = $('#adm_formule').val();
            var quantity = parseInt($('#adm_quantity').val()) || 1;
            var startDate = $('#adm_start').val();

            // Masquer si données incomplètes
            if (!offreId || !formule || !startDate) {
                $('#adm_price_summary').hide();
                return;
            }

            // Récupérer le prix unitaire
            var prices = offresPrices[offreId];
            if (!prices) {
                $('#adm_price_summary').hide();
                return;
            }

            var unitPrice = prices[formule] || 0;

            // Fallback si pas de prix défini
            if (unitPrice === 0) {
                if (formule === 'semaine' && prices['journee']) {
                    unitPrice = prices['journee'] * 5;
                } else if (formule === 'mois' && prices['semaine']) {
                    unitPrice = prices['semaine'] * 4;
                } else if (formule === 'mois' && prices['journee']) {
                    unitPrice = prices['journee'] * 20;
                }
            }

            var totalPrice = unitPrice * quantity;

            // Formater les détails
            var formuleLabel = formule === 'journee' ? 'Journée' : (formule === 'semaine' ? 'Semaine' : 'Mois');
            var details = quantity + ' × ' + formuleLabel + ' à ' + unitPrice.toFixed(2) + ' €';

            // Afficher le récapitulatif
            $('#adm_price_details').text(details);
            $('#adm_price_total').text(totalPrice.toFixed(2) + ' €');
            $('#adm_price_summary').fadeIn(200);
        }

        // Fonction globale de mise à jour
        function updateAll() {
            updateQuantityLabel();
            calculateEndDate();
            calculatePrice();
        }

        // Événements
        $('#adm_offre_id, #adm_formule, #adm_start').on('change', updateAll);
    });
    </script>
    <?php
}

/* ============================================
   SECTION: LOCKS
============================================ */

function cw_render_section_locks() {
    global $wpdb;

    $locks_data = $wpdb->get_results("SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE '_transient_cw_locks_%'");
    $now = time();
    $active_locks = [];

    foreach ($locks_data as $row) {
        preg_match('/cw_locks_(\d+)/', $row->option_name, $matches);
        $offre_id = isset($matches[1]) ? intval($matches[1]) : 0;
        if (!$offre_id) continue;

        $locks = maybe_unserialize($row->option_value);
        if (!is_array($locks)) continue;

        foreach ($locks as $lock) {
            if (!isset($lock['expires_at']) || $lock['expires_at'] <= $now) continue;

            $active_locks[] = [
                'offre_id' => $offre_id,
                'offre_name' => get_the_title($offre_id),
                'start' => $lock['start'],
                'end' => $lock['end'],
                'token' => $lock['token'] ?? '',
                'time_left' => $lock['expires_at'] - $now,
                'type' => $lock['lock_type'] ?? 'flexible'
            ];
        }
    }

    usort($active_locks, fn($a, $b) => $a['time_left'] - $b['time_left']);
    ?>

    <div class="cw-header">
        <div class="cw-header-left">
            <h1>Réservations en cours</h1>
            <p>Clients actuellement dans le processus de paiement</p>
        </div>
    </div>

    <div class="cw-card cw-animate-in">
        <div class="cw-card-header">
            <h2 class="cw-card-title">
                <span class="cw-card-title-icon">⏳</span>
                Locks actifs
            </h2>
            <span class="cw-badge warning"><?php echo count($active_locks); ?> en cours</span>
        </div>

        <?php if (empty($active_locks)): ?>
            <div class="cw-card-body">
                <div class="cw-empty">
                    <div class="cw-empty-icon">✓</div>
                    <h3 class="cw-empty-title">Aucun lock actif</h3>
                    <p class="cw-empty-text">Personne n'est actuellement en train de réserver</p>
                </div>
            </div>
        <?php else: ?>
            <div class="cw-card-body no-padding">
                <table class="cw-table">
                    <thead>
                        <tr>
                            <th>Espace</th>
                            <th>Dates</th>
                            <th>Type</th>
                            <th>Expire dans</th>
                            <th style="width:120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_locks as $lock):
                            $minutes = ceil($lock['time_left'] / 60);
                            $is_urgent = $minutes <= 3;
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($lock['offre_name']); ?></strong></td>
                                <td>
                                    <?php
                                    echo date_i18n('d/m/Y', strtotime($lock['start']));
                                    if ($lock['start'] !== $lock['end']) echo ' → ' . date_i18n('d/m/Y', strtotime($lock['end']));
                                    ?>
                                </td>
                                <td>
                                    <span class="cw-badge <?php echo $lock['type'] === 'strict' ? 'danger' : 'warning'; ?>">
                                        <?php echo $lock['type'] === 'strict' ? 'Prioritaire' : 'Flexible'; ?>
                                    </span>
                                </td>
                                <td style="<?php echo $is_urgent ? 'color:var(--color-danger);font-weight:600;' : ''; ?>">
                                    <?php echo $minutes; ?> min
                                </td>
                                <td>
                                    <button class="cw-btn cw-btn-danger cw-btn-sm cw-force-unlock"
                                            data-offre="<?php echo $lock['offre_id']; ?>"
                                            data-token="<?php echo esc_attr($lock['token']); ?>">
                                        Libérer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.cw-force-unlock').on('click', function(e) {
            e.preventDefault();
            if (!confirm('Libérer ce créneau ?\n\nLe client perdra sa réservation en cours.')) return;

            var btn = $(this);
            btn.prop('disabled', true).text('...');

            $.post(ajaxurl, {
                action: 'cw_force_unlock_manual',
                offre_id: btn.data('offre'),
                token: btn.data('token'),
                nonce: '<?php echo wp_create_nonce('cw_unlock_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Erreur');
                    btn.prop('disabled', false).text('Libérer');
                }
            });
        });
    });
    </script>
    <?php
}

/* ============================================
   SECTION: CALENDAR
============================================ */

function cw_render_section_calendar() {
    $offres = get_posts(['post_type' => 'offre-coworking', 'posts_per_page' => -1, 'post_status' => 'publish']);
    $current_month = date('Y-m');
    ?>

    <div class="cw-header">
        <div class="cw-header-left">
            <h1>Calendrier</h1>
            <p>Vue des disponibilités par espace</p>
        </div>
    </div>

    <div class="cw-card cw-animate-in">
        <div class="cw-card-header">
            <h2 class="cw-card-title">
                <span class="cw-card-title-icon">📊</span>
                Disponibilités
            </h2>
        </div>

        <div class="cw-card-body">
            <div class="cw-calendar-filters">
                <div class="cw-form-group" style="flex:1;">
                    <label class="cw-form-label">Espace</label>
                    <select id="cw-cal-offre" class="cw-form-select">
                        <?php foreach ($offres as $offre): ?>
                            <option value="<?php echo $offre->ID; ?>"><?php echo esc_html($offre->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cw-form-group" style="flex:1;">
                    <label class="cw-form-label">Mois</label>
                    <input type="month" id="cw-cal-month" value="<?php echo $current_month; ?>" class="cw-form-input">
                </div>

                <div class="cw-form-group">
                    <label class="cw-form-label" style="opacity:0;">Action</label>
                    <button type="button" class="cw-btn cw-btn-primary" id="cw-cal-refresh">Actualiser</button>
                </div>
            </div>

            <div id="cw-calendar-display"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        function loadCalendar() {
            const offre = $('#cw-cal-offre').val();
            const month = $('#cw-cal-month').val();

            $('#cw-calendar-display').html('<div style="text-align:center;padding:40px;color:var(--color-gray-500);">Chargement...</div>');

            $.get('<?php echo home_url('/wp-json/coworking/v1/availability/'); ?>' + offre + '?month=' + month, function(data) {
                if (!data.success) {
                    $('#cw-calendar-display').html('<div class="cw-alert cw-alert-danger"><span class="cw-alert-icon">❌</span><div class="cw-alert-content"><strong>Erreur</strong><p>Impossible de charger les données</p></div></div>');
                    return;
                }

                let html = '<table class="cw-table"><thead><tr><th>Date</th><th>Statut</th><th>Disponibilité</th></tr></thead><tbody>';

                for (const date in data.availability) {
                    const info = data.availability[date];
                    let badge = info.status === 'available' ? 'success' : (info.status === 'low' ? 'warning' : (info.status === 'full' ? 'danger' : 'neutral'));
                    let label = info.status === 'available' ? 'Disponible' : (info.status === 'low' ? 'Stock faible' : (info.status === 'full' ? 'Complet' : 'Indisponible'));

                    html += `<tr>
                        <td><strong>${new Date(date + 'T12:00:00').toLocaleDateString('fr-FR', {weekday: 'short', day: 'numeric', month: 'short'})}</strong></td>
                        <td><span class="cw-badge ${badge}">${label}</span></td>
                        <td><strong>${info.slots}</strong> / ${info.capacity} places</td>
                    </tr>`;
                }

                html += '</tbody></table>';
                $('#cw-calendar-display').html(html);
            });
        }

        $('#cw-cal-refresh').on('click', loadCalendar);
        loadCalendar();
    });
    </script>
    <?php
}

/* ============================================
   SECTION: BLOCKS
============================================ */

function cw_render_section_blocks() {
    $offres = get_posts(['post_type' => 'offre-coworking', 'posts_per_page' => -1, 'post_status' => 'publish']);

    // Process form
    if (isset($_POST['cw_add_block_submit']) && check_admin_referer('cw_add_block', 'cw_block_nonce')) {
        $offre_id = intval($_POST['cw_block_offre']);
        $start = sanitize_text_field($_POST['cw_block_start']);
        $end = sanitize_text_field($_POST['cw_block_end']) ?: $start;
        $reason = sanitize_text_field($_POST['cw_block_reason']);

        if (strtotime($start) > strtotime($end)) {
            echo '<div class="cw-alert cw-alert-danger"><span class="cw-alert-icon">⚠️</span><div class="cw-alert-content"><strong>Erreur</strong><p>La date de début doit être avant la date de fin.</p></div></div>';
        } else {
            $dates_to_block = [];
            $current = strtotime($start);
            $last = strtotime($end);

            while ($current <= $last) {
                $dates_to_block[] = date('Y-m-d', $current) . ($reason ? ' # ' . $reason : '');
                $current = strtotime('+1 day', $current);
            }

            $existing = get_field('dates_indisponibles_manuel', $offre_id) ?: '';
            $existing_lines = array_filter(explode("\n", $existing));
            $final_lines = array_unique(array_merge($existing_lines, $dates_to_block));

            update_field('dates_indisponibles_manuel', implode("\n", $final_lines), $offre_id);
            echo '<div class="cw-alert cw-alert-success"><span class="cw-alert-icon">✅</span><div class="cw-alert-content"><strong>Succès</strong><p>' . count($dates_to_block) . ' jour(s) bloqué(s)</p></div></div>';
        }
    }
    ?>

    <div class="cw-header">
        <div class="cw-header-left">
            <h1>Blocages manuels</h1>
            <p>Fermetures exceptionnelles et maintenance</p>
        </div>
    </div>

    <div class="cw-card cw-animate-in">
        <div class="cw-card-header">
            <h2 class="cw-card-title">
                <span class="cw-card-title-icon">🚫</span>
                Bloquer une période
            </h2>
        </div>

        <div class="cw-card-body">
            <form method="post" action="">
                <?php wp_nonce_field('cw_add_block', 'cw_block_nonce'); ?>

                <div class="cw-form-grid cw-form-grid-4" style="margin-bottom:20px;">
                    <div class="cw-form-group">
                        <label class="cw-form-label">Espace *</label>
                        <select name="cw_block_offre" required class="cw-form-select">
                            <?php foreach ($offres as $offre): ?>
                                <option value="<?php echo $offre->ID; ?>"><?php echo esc_html($offre->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cw-form-group">
                        <label class="cw-form-label">Du *</label>
                        <input type="date" name="cw_block_start" required min="<?php echo date('Y-m-d'); ?>" class="cw-form-input">
                    </div>

                    <div class="cw-form-group">
                        <label class="cw-form-label">Au (inclus) *</label>
                        <input type="date" name="cw_block_end" required min="<?php echo date('Y-m-d'); ?>" class="cw-form-input">
                    </div>

                    <div class="cw-form-group">
                        <label class="cw-form-label">Raison</label>
                        <input type="text" name="cw_block_reason" placeholder="Ex: Maintenance" class="cw-form-input">
                    </div>
                </div>

                <button type="submit" name="cw_add_block_submit" class="cw-btn cw-btn-primary">
                    <span>🚫</span> Bloquer la période
                </button>
            </form>
        </div>
    </div>

    <?php
    // Liste des blocages existants
    foreach ($offres as $offre) {
        $blocks = get_field('dates_indisponibles_manuel', $offre->ID) ?: '';
        if (!$blocks) continue;

        $lines = array_filter(array_map('trim', explode("\n", $blocks)));
        if (empty($lines)) continue;
        ?>

        <div class="cw-card cw-animate-in" style="margin-top:20px;">
            <div class="cw-card-header">
                <h2 class="cw-card-title"><?php echo esc_html($offre->post_title); ?></h2>
                <span class="cw-badge neutral"><?php echo count($lines); ?> blocage(s)</span>
            </div>
            <div class="cw-card-body no-padding">
                <table class="cw-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Raison</th>
                            <th style="width:100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line):
                            $parts = explode('#', $line);
                            $date = trim($parts[0]);
                            $reason = isset($parts[1]) ? trim($parts[1]) : '—';
                        ?>
                            <tr>
                                <td><strong><?php echo date_i18n('d/m/Y', strtotime($date)); ?></strong></td>
                                <td><?php echo esc_html($reason); ?></td>
                                <td>
                                    <button class="cw-btn cw-btn-danger cw-btn-sm cw-unblock" data-offre="<?php echo $offre->ID; ?>" data-date="<?php echo $date; ?>">
                                        Débloquer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    ?>

    <script>
    jQuery(document).ready(function($) {
        $('.cw-unblock').on('click', function(e) {
            e.preventDefault();
            if (!confirm('Débloquer cette date ?')) return;

            var btn = $(this);
            $.post(ajaxurl, {
                action: 'cw_unblock_date',
                offre_id: btn.data('offre'),
                date: btn.data('date')
            }, function(response) {
                if (response.success) {
                    btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                }
            });
        });
    });
    </script>
    <?php
}

/* ============================================
   SECTION: STATS
============================================ */

function cw_render_section_stats() {
    // Calculs statistiques
    $today = date('Y-m-d');
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $last_month_start = date('Y-m-01', strtotime('-1 month'));
    $last_month_end = date('Y-m-t', strtotime('-1 month'));

    // CA ce mois
    $ca_this_month = 0;
    $reservations_this_month = 0;
    $args = [
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [['key' => '_cw_start', 'value' => [$month_start, $month_end], 'compare' => 'BETWEEN', 'type' => 'DATE']]
    ];
    $query = new WP_Query($args);
    while ($query->have_posts()) {
        $query->the_post();
        $ca_this_month += floatval(get_post_meta(get_the_ID(), '_cw_price', true));
        $reservations_this_month++;
    }
    wp_reset_postdata();

    // CA mois dernier
    $ca_last_month = 0;
    $args['meta_query'] = [['key' => '_cw_start', 'value' => [$last_month_start, $last_month_end], 'compare' => 'BETWEEN', 'type' => 'DATE']];
    $query = new WP_Query($args);
    while ($query->have_posts()) {
        $query->the_post();
        $ca_last_month += floatval(get_post_meta(get_the_ID(), '_cw_price', true));
    }
    wp_reset_postdata();

    // Évolution
    $evolution = $ca_last_month > 0 ? round((($ca_this_month - $ca_last_month) / $ca_last_month) * 100) : 0;
    ?>

    <div class="cw-header">
        <div class="cw-header-left">
            <h1>Statistiques</h1>
            <p>Vue d'ensemble de l'activité</p>
        </div>
    </div>

    <div class="cw-kpi-grid cw-animate-in">
        <div class="cw-kpi-card">
            <div class="cw-kpi-label">CA ce mois</div>
            <div class="cw-kpi-value"><?php echo number_format($ca_this_month, 0, ',', ' '); ?> €</div>
            <div class="cw-kpi-sub">
                <?php if ($evolution != 0): ?>
                    <span class="cw-kpi-trend <?php echo $evolution > 0 ? 'up' : 'down'; ?>">
                        <?php echo ($evolution > 0 ? '+' : '') . $evolution; ?>%
                    </span>
                    vs mois dernier
                <?php else: ?>
                    Premier mois
                <?php endif; ?>
            </div>
        </div>

        <div class="cw-kpi-card">
            <div class="cw-kpi-label">CA mois dernier</div>
            <div class="cw-kpi-value"><?php echo number_format($ca_last_month, 0, ',', ' '); ?> €</div>
            <div class="cw-kpi-sub"><?php echo date_i18n('F Y', strtotime('-1 month')); ?></div>
        </div>

        <div class="cw-kpi-card">
            <div class="cw-kpi-label">Réservations ce mois</div>
            <div class="cw-kpi-value"><?php echo $reservations_this_month; ?></div>
            <div class="cw-kpi-sub">réservation(s)</div>
        </div>

        <div class="cw-kpi-card">
            <div class="cw-kpi-label">Panier moyen</div>
            <div class="cw-kpi-value"><?php echo $reservations_this_month > 0 ? number_format($ca_this_month / $reservations_this_month, 0, ',', ' ') : 0; ?> €</div>
            <div class="cw-kpi-sub">par réservation</div>
        </div>
    </div>

    <div class="cw-alert cw-alert-info cw-animate-in">
        <span class="cw-alert-icon">💡</span>
        <div class="cw-alert-content">
            <strong>Astuce</strong>
            <p>Les statistiques détaillées sont disponibles dans WooCommerce → Rapports</p>
        </div>
    </div>
    <?php
}

/* ============================================
   SECTION: MAINTENANCE
============================================ */

function cw_render_section_maintenance() {
    $offres = get_posts(['post_type' => 'offre-coworking', 'posts_per_page' => -1, 'post_status' => 'publish']);

    // Process resync
    if (isset($_POST['cw_resync_submit']) && check_admin_referer('cw_resync_json', 'cw_resync_nonce')) {
        $offre_id = intval($_POST['cw_resync_offre']);
        if ($offre_id && function_exists('cw_rebuild_reservations_json')) {
            cw_rebuild_reservations_json($offre_id);
            echo '<div class="cw-alert cw-alert-success"><span class="cw-alert-icon">✅</span><div class="cw-alert-content"><strong>Succès</strong><p>JSON resynchronisé pour cette offre</p></div></div>';
        }
    }

    // Process check
    if (isset($_POST['cw_check_submit']) && check_admin_referer('cw_check_integrity', 'cw_check_nonce')) {
        $offre_id = intval($_POST['cw_check_offre']);
        if ($offre_id) {
            $json = get_field('reservations_json', $offre_id) ?: '[]';
            $json_count = count(json_decode($json, true) ?: []);

            $cpt_query = new WP_Query([
                'post_type' => 'cw_reservation',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [['key' => '_cw_offre_id', 'value' => $offre_id]]
            ]);
            $cpt_count = $cpt_query->found_posts;
            wp_reset_postdata();

            $synced = $json_count === $cpt_count;
            $alert_class = $synced ? 'success' : 'warning';
            $icon = $synced ? '✅' : '⚠️';

            echo '<div class="cw-alert cw-alert-' . $alert_class . '"><span class="cw-alert-icon">' . $icon . '</span><div class="cw-alert-content"><strong>Résultat</strong><p>JSON: ' . $json_count . ' | CPT: ' . $cpt_count . ($synced ? ' (synchronisé)' : ' (différence de ' . abs($json_count - $cpt_count) . ')') . '</p></div></div>';
        }
    }
    ?>

    <div class="cw-header">
        <div class="cw-header-left">
            <h1>Maintenance</h1>
            <p>Outils techniques avancés</p>
        </div>
    </div>

    <div class="cw-alert cw-alert-danger cw-animate-in">
        <span class="cw-alert-icon">⚠️</span>
        <div class="cw-alert-content">
            <strong>Zone technique</strong>
            <p>Ces outils modifient directement les données. À utiliser uniquement si vous savez ce que vous faites.</p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:20px;">
        <div class="cw-card cw-animate-in">
            <div class="cw-card-header">
                <h2 class="cw-card-title">
                    <span class="cw-card-title-icon">🔄</span>
                    Resynchroniser JSON
                </h2>
            </div>
            <div class="cw-card-body">
                <p style="color:var(--color-gray-600);font-size:14px;margin-bottom:16px;">
                    Reconstruit le JSON de disponibilité à partir des commandes WooCommerce confirmées.
                </p>
                <form method="post" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir resynchroniser ?');">
                    <?php wp_nonce_field('cw_resync_json', 'cw_resync_nonce'); ?>
                    <div class="cw-form-group" style="margin-bottom:16px;">
                        <select name="cw_resync_offre" required class="cw-form-select">
                            <option value="">Choisir un espace...</option>
                            <?php foreach($offres as $offre): ?>
                                <option value="<?php echo $offre->ID; ?>"><?php echo esc_html($offre->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="cw_resync_submit" class="cw-btn cw-btn-danger">
                        <span>🔄</span> Resynchroniser
                    </button>
                </form>
            </div>
        </div>

        <div class="cw-card cw-animate-in">
            <div class="cw-card-header">
                <h2 class="cw-card-title">
                    <span class="cw-card-title-icon">🔍</span>
                    Vérifier intégrité
                </h2>
            </div>
            <div class="cw-card-body">
                <p style="color:var(--color-gray-600);font-size:14px;margin-bottom:16px;">
                    Compare les réservations CPT avec le JSON pour détecter les incohérences.
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('cw_check_integrity', 'cw_check_nonce'); ?>
                    <div class="cw-form-group" style="margin-bottom:16px;">
                        <select name="cw_check_offre" required class="cw-form-select">
                            <option value="">Choisir un espace...</option>
                            <?php foreach($offres as $offre): ?>
                                <option value="<?php echo $offre->ID; ?>"><?php echo esc_html($offre->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="cw_check_submit" class="cw-btn cw-btn-secondary">
                        <span>🔍</span> Vérifier
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/* ============================================
   AJAX HANDLERS
============================================ */

// Search clients
add_action('wp_ajax_cw_search_clients', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Non autorisé');
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cw_search_nonce')) wp_send_json_error('Nonce invalide');

    $query = sanitize_text_field($_POST['query'] ?? '');
    if (strlen($query) < 2) wp_send_json_success([]);

    $args = [
        'post_type' => 'cw_reservation',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'meta_query' => [
            'relation' => 'OR',
            ['key' => '_cw_customer_name', 'value' => $query, 'compare' => 'LIKE'],
            ['key' => '_cw_customer_email', 'value' => $query, 'compare' => 'LIKE']
        ]
    ];

    $results = [];
    $q = new WP_Query($args);

    while ($q->have_posts()) {
        $q->the_post();
        $id = get_the_ID();
        $order_id = get_post_meta($id, '_cw_order_id', true);

        $results[] = [
            'name' => get_post_meta($id, '_cw_customer_name', true),
            'email' => get_post_meta($id, '_cw_customer_email', true),
            'offre' => get_post_meta($id, '_cw_offre_name', true),
            'dates' => date_i18n('d/m/Y', strtotime(get_post_meta($id, '_cw_start', true))),
            'edit_url' => $order_id ? admin_url('post.php?post=' . $order_id . '&action=edit') : '#'
        ];
    }
    wp_reset_postdata();

    wp_send_json_success($results);
});

// Force unlock (déjà défini dans l'ancien fichier, mais on le redéfinit ici pour être sûr)
add_action('wp_ajax_cw_force_unlock_manual', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Non autorisé');
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cw_unlock_nonce')) wp_send_json_error('Nonce invalide');

    $offre_id = intval($_POST['offre_id'] ?? 0);
    $token = sanitize_text_field($_POST['token'] ?? '');

    if (!$offre_id || !$token) wp_send_json_error('Paramètres manquants');

    if (function_exists('coworking_remove_lock_by_token')) {
        coworking_remove_lock_by_token($offre_id, $token);
        wp_send_json_success('Lock libéré');
    }

    wp_send_json_error('Fonction introuvable');
});

// Check new reservations
add_action('wp_ajax_cw_check_new_reservations', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Non autorisé');

    $last_count = intval($_POST['last_count'] ?? 0);
    $current_count = wp_count_posts('cw_reservation')->publish;

    wp_send_json_success([
        'new_count' => $current_count,
        'has_new' => $current_count > $last_count
    ]);
});

// Unblock date
add_action('wp_ajax_cw_unblock_date', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Non autorisé');

    $offre_id = intval($_POST['offre_id'] ?? 0);
    $date_to_remove = sanitize_text_field($_POST['date'] ?? '');

    if (!$offre_id || !$date_to_remove) wp_send_json_error('Paramètres manquants');

    $blocks = get_field('dates_indisponibles_manuel', $offre_id) ?: '';
    $lines = array_filter(array_map('trim', explode("\n", $blocks)));

    $new_lines = array_filter($lines, function($line) use ($date_to_remove) {
        return trim(explode('#', $line)[0]) !== $date_to_remove;
    });

    update_field('dates_indisponibles_manuel', implode("\n", array_values($new_lines)), $offre_id);
    wp_send_json_success('Date débloquée');
});
