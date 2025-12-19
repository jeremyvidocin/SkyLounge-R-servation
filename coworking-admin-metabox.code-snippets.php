<?php

/**
 * =============================================================================
 * COWORKING ADMIN METABOX - DÉTAILS RÉSERVATION
 * =============================================================================
 *
 * Affiche une métabox complète sur la page d'édition des réservations.
 * Lecture seule pour éviter les modifications manuelles non contrôlées.
 *
 * FONCTIONNALITÉS :
 * - Affichage des informations de réservation dans l'admin
 * - Liens vers l'offre et la commande WooCommerce associées
 * - Injection des détails réservation dans les emails WooCommerce
 * - Formatage français des dates et prix
 *
 * INFORMATIONS AFFICHÉES :
 * - Espace réservé (avec liens admin + front)
 * - Formule et quantité
 * - Dates de début et fin
 * - Prix total payé
 * - Nom du client
 * - Lien vers la commande WooCommerce
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
   SECTION 1 : HELPERS DE FORMATAGE
   =============================================================================
   Fonctions utilitaires pour formater les dates et prix en français.
============================================================================= */

/**
 * Formate une date ISO en format français (dd/mm/YYYY).
 *
 * @since 1.0.0
 *
 * @param string $iso La date au format ISO (YYYY-MM-DD).
 *
 * @return string La date formatée en français, ou chaîne vide si invalide.
 *
 * @example
 * cw_format_date_fr('2025-01-15'); // '15/01/2025'
 */
if (!function_exists('cw_format_date_fr')) {
    function cw_format_date_fr($iso) {
        if (!$iso) return '';
        $ts = strtotime($iso);
        if ($ts === false) return esc_html($iso);
        return date_i18n('d/m/Y', $ts);
    }
}

/**
 * Formate un montant en prix WooCommerce.
 *
 * Utilise wc_price() pour le formatage avec symbole € et séparateurs.
 *
 * @since 1.0.0
 *
 * @param float|string $amount Le montant à formater.
 *
 * @return string Le prix formaté HTML, ou chaîne vide si vide.
 *
 * @example
 * cw_format_price(199.50); // '199,50 €'
 */
if (!function_exists('cw_format_price')) {
    function cw_format_price($amount) {
        if ($amount === '' || $amount === null) return '';
        return wc_price((float)$amount);
    }
}

/* =============================================================================
   SECTION 2 : ENREGISTREMENT DE LA MÉTABOX
   =============================================================================
   Ajoute une métabox sur la page d'édition du CPT cw_reservation.
============================================================================= */

/**
 * Enregistre la métabox "Détails de la réservation".
 *
 * @since 1.0.0
 * @hook add_meta_boxes
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'cw_reservation_details',           // ID unique
        '📅 Détails de la réservation',     // Titre
        'cw_render_reservation_metabox',    // Callback de rendu
        'cw_reservation',                    // Post type
        'normal',                            // Contexte (normal/side/advanced)
        'high'                               // Priorité
    );
});

/**
 * Affiche le contenu de la métabox (lecture seule).
 *
 * @since 1.0.0
 *
 * @param WP_Post $post L'objet post de la réservation.
 *
 * @return void
 */
function cw_render_reservation_metabox($post) {
    // Vérification des permissions
    if (!current_user_can('edit_posts')) {
        echo '<p>Accès restreint.</p>';
        return;
    }

    // Récupérer les metas propres (8 champs attendus)
    $offre_id   = get_post_meta($post->ID, '_cw_offre_id', true);
    $offre_name = get_post_meta($post->ID, '_cw_offre_name', true);
    $formule    = get_post_meta($post->ID, '_cw_formule', true);
    $start      = get_post_meta($post->ID, '_cw_start', true);
    $end        = get_post_meta($post->ID, '_cw_end', true);
    $price      = get_post_meta($post->ID, '_cw_price', true);
    $cust_name  = get_post_meta($post->ID, '_cw_customer_name', true);
    $order_id   = get_post_meta($post->ID, '_cw_order_id', true);

    // Fallbacks lisibles
    if (!$offre_name && $offre_id) {
        $offre_name = get_the_title($offre_id);
    }

    // Format
    $formule_label = $formule ? ucfirst($formule) : '-';
    $start_fr = cw_format_date_fr($start);
    $end_fr   = cw_format_date_fr($end);
    $price_fmt = cw_format_price($price);

    // Lien admin vers la commande WooCommerce si existe
    $order_link = '';
    if ($order_id) {
        $order_post = get_post($order_id);
        if ($order_post) {
            $order_edit_url = admin_url('post.php?post=' . intval($order_id) . '&action=edit');
            $order_link = sprintf('<a href="%s">#%d</a>', esc_url($order_edit_url), intval($order_id));
        } else {
            $order_link = '#' . intval($order_id);
        }
    }

    // Lien vers l'offre (front / back)
    $offre_edit_link = '';
    if ($offre_id) {
        $edit_url = admin_url('post.php?post=' . intval($offre_id) . '&action=edit');
        $permalink = get_permalink($offre_id);
        $offre_edit_link = sprintf(
            '<a href="%s" target="_blank">%s</a> <small>(<a href="%s" target="_blank">Voir la fiche</a>)</small>',
            esc_url($edit_url),
            esc_html($offre_name ?: 'Offre #' . intval($offre_id)),
            esc_url($permalink)
        );
    }

    // Output HTML (lecture seule)
    ?>
    <div style="font-family:system-ui, -apple-system, Roboto, 'Segoe UI', Arial; line-height:1.45;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="width:160px; padding:6px 8px; vertical-align:top; font-weight:600;">Offre</td>
                <td style="padding:6px 8px;"><?php echo $offre_edit_link ? $offre_edit_link : esc_html($offre_name ?: '—'); ?></td>
            </tr>

            <tr>
                <td style="padding:6px 8px; font-weight:600;">Formule</td>
                <td style="padding:6px 8px;"><?php echo esc_html($formule_label); ?></td>
            </tr>

            <tr>
                <td style="padding:6px 8px; font-weight:600;">Du</td>
                <td style="padding:6px 8px;"><?php echo esc_html($start_fr ?: '—'); ?></td>
            </tr>

            <tr>
                <td style="padding:6px 8px; font-weight:600;">Au</td>
                <td style="padding:6px 8px;"><?php echo esc_html($end_fr ?: '—'); ?></td>
            </tr>

            <tr>
                <td style="padding:6px 8px; font-weight:600;">Prix</td>
                <td style="padding:6px 8px;"><?php echo $price_fmt ?: '—'; ?></td>
            </tr>

            <tr>
                <td style="padding:6px 8px; font-weight:600;">Client</td>
                <td style="padding:6px 8px;"><?php echo esc_html($cust_name ?: '—'); ?></td>
            </tr>

            <tr>
                <td style="padding:6px 8px; font-weight:600;">Commande</td>
                <td style="padding:6px 8px;"><?php echo $order_link ?: '—'; ?></td>
            </tr>
        </table>

        <?php
        // Optionnel : afficher meta brutes pour debug (commenté)
        // echo '<pre style="margin-top:8px;">' . esc_html(print_r(get_post_meta($post->ID), true)) . '</pre>';
        ?>
    </div>
    <?php
}

/* ------------------------------------------------------------
   Emails WooCommerce : injecter une section "Détails réservation"
   (s'affiche dans l'email de commande côté client)
------------------------------------------------------------*/

/**
 * Affiche les réservations associées à une commande dans les emails.
 * Hook sur 'woocommerce_email_after_order_table' pour apparaître sous le tableau commande.
 */
add_action('woocommerce_email_after_order_table', 'cw_email_reservation_section', 10, 4);

function cw_email_reservation_section($order, $sent_to_admin, $plain_text, $email) {
    // On ne veut pas injecter cette section dans tous les emails (ex: admin new order)
    // Nous ciblons les emails destinés au client (customer processing/completed)
    if ($sent_to_admin) return;

    // Parcourir les items de la commande, chercher les metas _cw_offre_id
    $items = $order->get_items();
    $reservations = [];

    foreach ($items as $item) {
        $offre_id = $item->get_meta('_cw_offre_id', true);
        if (!$offre_id) continue;

        $reservations[] = [
            'offre_name' => $item->get_meta('_cw_offre_name', true) ?: get_the_title($offre_id),
            'formule'    => $item->get_meta('_cw_formule', true),
            'start'      => $item->get_meta('_cw_start', true),
            'end'        => $item->get_meta('_cw_end', true),
            'price'      => $item->get_meta('_cw_price', true),
        ];
    }

    if (empty($reservations)) return;

    // Rendu HTML ou texte selon le mail
    if ($plain_text) {
        echo "\n---- Détails de votre réservation ----\n";
        foreach ($reservations as $r) {
            echo 'Offre : ' . strip_tags($r['offre_name']) . "\n";
            echo 'Formule : ' . ucfirst($r['formule']) . "\n";
            echo 'Du : ' . cw_format_date_fr($r['start']) . "\n";
            echo 'Au : ' . cw_format_date_fr($r['end']) . "\n";
            echo 'Prix : ' . strip_tags(cw_format_price($r['price'])) . "\n";
            echo "-------------------------------------\n";
        }
        echo "\n";
    } else {
        // HTML
        echo '<h2 style="font-size:18px; margin-top:20px; margin-bottom:10px;">🔔 Détails de votre réservation</h2>';
        echo '<table cellspacing="0" cellpadding="6" style="width:100%; border-collapse:collapse;">';
        foreach ($reservations as $r) {
            echo '<tr>';
            echo '<td style="width:160px; font-weight:600; vertical-align:top;">Offre</td>';
            echo '<td>' . esc_html($r['offre_name']) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<td style="font-weight:600;">Formule</td>';
            echo '<td>' . esc_html(ucfirst($r['formule'])) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<td style="font-weight:600;">Du</td>';
            echo '<td>' . esc_html(cw_format_date_fr($r['start'])) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<td style="font-weight:600;">Au</td>';
            echo '<td>' . esc_html(cw_format_date_fr($r['end'])) . '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<td style="font-weight:600;">Prix</td>';
            echo '<td>' . cw_format_price($r['price']) . '</td>';
            echo '</tr>';

            // spacer
            echo '<tr><td colspan="2" style="padding-top:8px;"></td></tr>';
        }
        echo '</table>';
    }
}

/**
 * Ajoute un lien ADMIN dans les emails pour la secrétaire
 * Avec recherche automatique de la réservation
 */
add_action('woocommerce_email_order_details', function($order, $sent_to_admin, $plain_text, $email) {
    // Uniquement pour l'admin
    if (!$sent_to_admin) return;
    
    $items = $order->get_items();
    foreach ($items as $item) {
        $offre_id = $item->get_meta('_cw_offre_id', true);
        if (!$offre_id) continue;
        
        // Chercher la réservation CPT liée à cette commande
        $reservation = get_posts([
            'post_type' => 'cw_reservation',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [[
                'key' => '_cw_order_id',
                'value' => $order->get_id()
            ]]
        ]);
        
        $planning_url = admin_url('admin.php?page=cw-planning');
        
        if (!empty($reservation)) {
            // Lien direct pour éditer la résa
            $edit_url = admin_url('post.php?post=' . $reservation[0]->ID . '&action=edit');
            
            if (!$plain_text) {
                echo '<div style="margin:20px 0; padding:15px; background:#f0f9ff; border-radius:6px; border:1px solid #bae6fd;">';
                echo '<h4 style="margin-top:0; color:#0369a1;">📋 Réservation Coworking</h4>';
                echo '<p style="margin:10px 0;">';
                echo '<a href="' . esc_url($edit_url) . '" style="display:inline-block; padding:8px 16px; background:#0ea5e9; color:white; text-decoration:none; border-radius:4px; font-weight:bold; margin-right:10px;">';
                echo '📝 Éditer cette réservation';
                echo '</a>';
                echo '<a href="' . esc_url($planning_url) . '" style="display:inline-block; padding:8px 16px; background:#64748b; color:white; text-decoration:none; border-radius:4px; font-weight:bold;">';
                echo '📊 Voir le planning complet';
                echo '</a>';
                echo '</p>';
                echo '</div>';
            } else {
                echo "\n\n========================================\n";
                echo "RÉSERVATION COWORKING - ACCÈS ADMIN\n";
                echo "========================================\n";
                echo "Éditer la réservation : " . $edit_url . "\n";
                echo "Planning complet : " . $planning_url . "\n";
                echo "========================================\n\n";
            }
        } else {
            // Réservation pas encore créée (cas statut "pending")
            if (!$plain_text) {
                echo '<div style="margin:20px 0; padding:15px; background:#fef3c7; border-radius:6px; border:1px solid #f59e0b;">';
                echo '<h4 style="margin-top:0; color:#92400e;">⚠️ Réservation en attente</h4>';
                echo '<p style="margin:10px 0;">Cette commande n\'a pas encore généré de réservation dans le système (statut : ' . $order->get_status() . ').</p>';
                echo '<p style="margin:10px 0;">';
                echo '<a href="' . esc_url($planning_url) . '" style="display:inline-block; padding:8px 16px; background:#f59e0b; color:white; text-decoration:none; border-radius:4px; font-weight:bold;">';
                echo '📊 Accéder au planning';
                echo '</a>';
                echo '</p>';
                echo '</div>';
            }
        }
        
        break; // Une seule réservation par commande
    }
}, 10, 4);

/* ------------------------------------------------------------
   End file
------------------------------------------------------------*/
