<?php

/**
 * =============================================================================
 * COWORKING CONFIGURATION CENTRALE
 * =============================================================================
 *
 * ⚠️  CE FICHIER DOIT ÊTRE CHARGÉ EN PREMIER (priorité 1 dans Code Snippets)
 *
 * Configuration centralisée du système de réservation coworking.
 * Toutes les constantes, IDs produits et paramètres globaux sont définis ici.
 *
 * CONTENU :
 * - Section 1 : IDs des produits WooCommerce
 * - Section 2 : Fonctions de résolution produit/offre
 * - Section 3 : Paramètres par défaut (capacités, durées)
 * - Section 4 : Configuration des formules et tarifs
 *
 * MODIFICATION :
 * Pour changer les IDs produits après recréation :
 * 1. Trouvez le nouvel ID dans WooCommerce > Produits
 * 2. Modifiez CW_PRODUCT_ID_BUREAU ou CW_PRODUCT_ID_SALLE ci-dessous
 *
 * @package    SkyLounge_Coworking
 * @subpackage Configuration
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    1.0.0
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : IDS PRODUITS WOOCOMMERCE
   =============================================================================
   
   Ces IDs correspondent aux produits WooCommerce utilisés pour la facturation.
   Chaque type d'espace a son propre produit "placeholder" dans WooCommerce.
   
   📍 Comment trouver l'ID d'un produit :
   1. Allez dans WooCommerce > Produits
   2. Survolez le produit concerné
   3. L'ID apparaît dans l'URL : ?post=1913
   
   ⚠️ Si vous recréez les produits, mettez à jour ces valeurs !
============================================================================= */

/**
 * ID du produit WooCommerce pour les bureaux individuels.
 * Produit : "Réservation Bureau" (prix à 0€, recalculé dynamiquement)
 */
if (!defined('CW_PRODUCT_ID_BUREAU')) {
    define('CW_PRODUCT_ID_BUREAU', 1913);
}

/**
 * ID du produit WooCommerce pour les salles de réunion.
 * Produit : "Réservation Salle" (prix à 0€, recalculé dynamiquement)
 */
if (!defined('CW_PRODUCT_ID_SALLE')) {
    define('CW_PRODUCT_ID_SALLE', 1917);
}

/* =============================================================================
   SECTION 2 : FONCTIONS HELPERS PRODUITS
   =============================================================================
   Fonctions utilitaires pour la gestion des produits coworking.
============================================================================= */

/**
 * Retourne la liste des IDs produits coworking.
 *
 * @since 1.0.0
 *
 * @return int[] Tableau des IDs produits WooCommerce.
 */
if (!function_exists('cw_get_product_ids')) {
    function cw_get_product_ids() {
        return [CW_PRODUCT_ID_BUREAU, CW_PRODUCT_ID_SALLE];
    }
}

/**
 * Vérifie si un produit est un produit coworking.
 *
 * @since 1.0.0
 *
 * @param int $product_id L'ID du produit à vérifier.
 *
 * @return bool True si c'est un produit coworking.
 */
if (!function_exists('cw_is_coworking_product')) {
    function cw_is_coworking_product($product_id) {
        return in_array((int)$product_id, cw_get_product_ids(), true);
    }
}

/**
 * Résout l'ID produit WooCommerce pour une offre donnée.
 *
 * Stratégie de résolution (dans l'ordre) :
 * 1. Champ ACF 'produit_woocommerce' (relation directe)
 * 2. Champ ACF 'type_offre' (bureau/salle → ID correspondant)
 * 3. Détection par titre (contient "bureau" ou "salle")
 * 4. Fallback : ID Bureau par défaut
 *
 * @since 1.0.0
 *
 * @param int $offre_id L'ID de l'offre coworking (CPT).
 *
 * @return int L'ID du produit WooCommerce correspondant.
 */
if (!function_exists('cw_get_product_id_for_offre')) {
    function cw_get_product_id_for_offre($offre_id) {
        // 1. D'abord essayer via ACF (relation produit_woocommerce)
        if (function_exists('get_field')) {
            $field = get_field('produit_woocommerce', $offre_id);
            if ($field) {
                if (is_array($field)) {
                    $first = reset($field);
                    if (is_object($first) && isset($first->ID)) return (int)$first->ID;
                    if (is_numeric($first)) return (int)$first;
                }
                if (is_object($field) && isset($field->ID)) return (int)$field->ID;
                if (is_numeric($field)) return (int)$field;
            }
        }

        // 2. Fallback : détection par type_offre (champ ACF)
        if (function_exists('get_field')) {
            $type_offre = get_field('type_offre', $offre_id);
            if ($type_offre === 'bureau') return CW_PRODUCT_ID_BUREAU;
            if ($type_offre === 'salle') return CW_PRODUCT_ID_SALLE;
        }

        // 3. Fallback ultime : détection par titre
        $title = strtolower(get_the_title($offre_id));
        if (strpos($title, 'bureau') !== false) return CW_PRODUCT_ID_BUREAU;
        if (strpos($title, 'salle') !== false) return CW_PRODUCT_ID_SALLE;

        // 4. Default : Bureau
        return CW_PRODUCT_ID_BUREAU;
    }
}

/* =============================================================================
   CONFIGURATION DES LOCKS (reservations temporaires)
   ============================================================================= */

if (!defined('CW_LOCK_TTL_SINGLE')) {
    define('CW_LOCK_TTL_SINGLE', 20 * MINUTE_IN_SECONDS); // 20 min si capacite = 1
}

if (!defined('CW_LOCK_TTL_MULTI')) {
    define('CW_LOCK_TTL_MULTI', 5 * MINUTE_IN_SECONDS);   // 5 min si capacite > 1
}

/* =============================================================================
   CAPACITE PAR DEFAUT
   ============================================================================= */

if (!defined('CW_DEFAULT_CAPACITY')) {
    define('CW_DEFAULT_CAPACITY', 1); // Capacite par defaut si non configuree
}

/* =============================================================================
   LOGGING & DEBUG
   ============================================================================= */

/**
 * Log centralise pour le systeme coworking
 * Les logs apparaissent dans wp-content/debug.log si WP_DEBUG_LOG est actif
 *
 * @param string $message Message a logger
 * @param string $level   Niveau: 'info', 'warning', 'error'
 * @param array  $context Donnees additionnelles
 */
if (!function_exists('cw_log')) {
    function cw_log($message, $level = 'info', $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            // En production, ne logger que les erreurs
            if ($level !== 'error') return;
        }

        $prefix = '[CW-' . strtoupper($level) . ']';
        $log_message = $prefix . ' ' . $message;

        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        error_log($log_message);
    }
}

/* =============================================================================
   VERIFICATION DES DEPENDANCES
   ============================================================================= */

/**
 * Verifie que WooCommerce est actif
 */
if (!function_exists('cw_check_woocommerce')) {
    function cw_check_woocommerce() {
        if (!function_exists('WC') || !class_exists('WooCommerce')) {
            cw_log('WooCommerce non actif - systeme de reservation desactive', 'error');
            return false;
        }
        return true;
    }
}

/**
 * Verifie que ACF est actif
 */
if (!function_exists('cw_check_acf')) {
    function cw_check_acf() {
        if (!function_exists('get_field')) {
            cw_log('ACF non actif - certaines fonctionnalites peuvent etre limitees', 'warning');
            return false;
        }
        return true;
    }
}

/**
 * Verifie que le plugin PDF est actif (pour les contrats)
 */
if (!function_exists('cw_check_pdf_plugin')) {
    function cw_check_pdf_plugin() {
        // WPO WCPDF utilise cette classe
        if (!class_exists('WPO_WCPDF')) {
            return false;
        }
        return true;
    }
}

/* =============================================================================
   NOTIFICATION ADMIN SI CONFIG MANQUANTE
   ============================================================================= */

add_action('admin_notices', function() {
    // Seulement pour les admins
    if (!current_user_can('manage_options')) return;

    // Verifier que les produits existent
    $bureau = wc_get_product(CW_PRODUCT_ID_BUREAU);
    $salle = wc_get_product(CW_PRODUCT_ID_SALLE);

    $errors = [];

    if (!$bureau) {
        $errors[] = sprintf(
            'Produit Bureau (ID %d) introuvable. <a href="%s">Creer le produit</a> ou modifier CW_PRODUCT_ID_BUREAU.',
            CW_PRODUCT_ID_BUREAU,
            admin_url('post-new.php?post_type=product')
        );
    }

    if (!$salle) {
        $errors[] = sprintf(
            'Produit Salle (ID %d) introuvable. <a href="%s">Creer le produit</a> ou modifier CW_PRODUCT_ID_SALLE.',
            CW_PRODUCT_ID_SALLE,
            admin_url('post-new.php?post_type=product')
        );
    }

    if (!empty($errors)) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>Coworking - Configuration requise:</strong></p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        foreach ($errors as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
});
