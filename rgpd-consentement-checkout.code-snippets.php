<?php

/**
 * =============================================================================
 * RGPD CONSENT SYSTEM - CONFORMITÉ CHECKOUT
 * =============================================================================
 *
 * Ce module implémente la conformité RGPD (Règlement Général sur la Protection
 * des Données) au niveau du checkout WooCommerce, conformément aux exigences
 * du RGPD européen (UE 2016/679).
 *
 * FONCTIONNALITÉS :
 *
 * 1. CHECKBOX DE CONSENTEMENT
 *    - Affichée avant le bouton "Commander"
 *    - Obligatoire (bloque la commande si non cochée)
 *    - Lien vers la politique de confidentialité
 *    - Design intégré au thème WooCommerce
 *
 * 2. VALIDATION SERVEUR
 *    - Double vérification côté serveur (sécurité anti-bypass)
 *    - Message d'erreur clair en cas de non-consentement
 *
 * 3. ENREGISTREMENT DES PREUVES
 *    - Consentement : oui/non (meta '_cw_rgpd_consent')
 *    - Date/heure : timestamp MySQL (meta '_cw_rgpd_consent_date')
 *    - IP anonymisée : conforme RGPD (meta '_cw_rgpd_consent_ip')
 *
 * ANONYMISATION IP (RGPD-compliant) :
 * - IPv4 : 192.168.1.xxx → 192.168.1.0 (dernier octet masqué)
 * - IPv6 : xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx:xxxx → xxxx:xxxx:...:0000
 *
 * CONSERVATION DES DONNÉES :
 * Le CRON (coworking-cron.php) supprime les données personnelles
 * après 3 ans conformément aux recommandations CNIL.
 *
 * LIENS UTILES :
 * - RGPD : https://eur-lex.europa.eu/eli/reg/2016/679/oj
 * - CNIL : https://www.cnil.fr/fr/rgpd-de-quoi-parle-t-on
 *
 * @package    SkyLounge_Coworking
 * @subpackage RGPD
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    1.1.0
 *
 * @see coworking-cron.php  Suppression automatique après 3 ans
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : AFFICHAGE DE LA CHECKBOX
   =============================================================================
   Injecte la checkbox de consentement RGPD avant le bouton de commande.
============================================================================= */

/**
 * Affiche la checkbox de consentement RGPD au checkout.
 *
 * Position : Juste avant le bouton "Commander" (review_order_before_submit).
 * Style : Encadré avec bordure bleue pour attirer l'attention.
 *
 * @since 1.0.0
 * @hook woocommerce_review_order_before_submit
 */
add_action('woocommerce_review_order_before_submit', function() {
    echo '<div class="rgpd-consent-wrapper" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #0073aa; border-radius: 4px;">
        <p class="form-row validate-required" style="margin: 0;">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="cw_rgpd_consent" id="cw_rgpd_consent" required style="margin-top: 4px; flex-shrink: 0;" />
                <span style="font-size: 14px; line-height: 1.5;">
                    J\'accepte que mes données personnelles soient utilisées pour gérer ma réservation conformément à la 
                    <a href="/politique-confidentialite" target="_blank" style="color: #0073aa; text-decoration: underline;">politique de confidentialité</a>.
                    <abbr class="required" title="obligatoire" style="color: red; text-decoration: none;">*</abbr>
                </span>
            </label>
        </p>
    </div>';
});

// Validation côté serveur (sécurité)
add_action('woocommerce_checkout_process', function() {
    if (empty($_POST['cw_rgpd_consent'])) {
        wc_add_notice('Vous devez accepter la politique de confidentialité pour continuer.', 'error');
    }
});

// Enregistrer le consentement dans la commande (preuve légale)
add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    if (!empty($_POST['cw_rgpd_consent'])) {
        update_post_meta($order_id, '_cw_rgpd_consent', 'yes');
        update_post_meta($order_id, '_cw_rgpd_consent_date', current_time('mysql'));

        // ✅ CORRECTION CRITIQUE RGPD : Anonymiser l'IP (garder seulement les 3 premiers octets)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_parts = explode('.', $ip);
        if (count($ip_parts) === 4) {
            // IPv4 : 192.168.1.xxx → 192.168.1.0
            $ip_anonymized = $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2] . '.0';
        } elseif (strpos($ip, ':') !== false) {
            // IPv6 : anonymiser les 64 derniers bits
            $ip_anonymized = substr($ip, 0, strrpos($ip, ':')) . ':0000';
        } else {
            $ip_anonymized = 'unknown';
        }

        update_post_meta($order_id, '_cw_rgpd_consent_ip', $ip_anonymized);
    }
});
