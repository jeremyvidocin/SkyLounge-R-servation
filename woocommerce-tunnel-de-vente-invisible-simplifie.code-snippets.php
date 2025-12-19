<?php

/**
 * =============================================================================
 * WOOCOMMERCE INVISIBLE SALES TUNNEL - FLUX DE VENTE SIMPLIFIÉ
 * =============================================================================
 *
 * Ce module transforme WooCommerce en tunnel de vente invisible et simplifié,
 * optimisé pour la réservation de services (coworking) plutôt que la vente
 * de produits physiques.
 *
 * CONCEPT "BOUTIQUE INVISIBLE" :
 * L'utilisateur ne voit jamais la boutique WooCommerce traditionnelle.
 * Il réserve via le calendrier → ajout au panier en AJAX → redirection
 * directe vers le checkout → paiement → confirmation.
 *
 * MODIFICATIONS APPLIQUÉES :
 *
 * 1. SÉCURITÉ - Redirection des pages boutique
 *    - /boutique/           → Redirigé vers l'accueil
 *    - /produit/xyz/        → Redirigé vers l'accueil
 *    - /categorie-produit/  → Redirigé vers l'accueil
 *    - /panier/             → Redirigé vers le checkout (si panier non vide)
 *
 * 2. FLUX SIMPLIFIÉ - Skip du panier
 *    - Ajout au panier → Redirection directe vers /checkout/
 *    - Pas de page panier intermédiaire
 *
 * 3. CHECKOUT ÉPURÉ - Champs minimaux
 *    - Conservés : Prénom, Nom, Email, Téléphone
 *    - Supprimés : Adresse, Ville, Code postal, Pays, Société, Notes
 *    - Pas de section livraison (service dématérialisé)
 *
 * 4. UI NETTOYÉE
 *    - Prix "0€" masqué pour les produits coworking
 *    - Notifications "Ajouté au panier" supprimées
 *    - Breadcrumb simplifié
 *
 * PAGES AUTORISÉES :
 * - /checkout/    : Page de paiement
 * - /mon-compte/  : Espace client (commandes, contrats)
 *
 * @package    SkyLounge_Coworking
 * @subpackage WooCommerce
 * @author     Jérémy VIDOCIN
 * @since      1.0.0
 * @version    1.0.0
 *
 * @see coworking-booking-engine-v2.php  Gère l'ajout au panier via REST API
 * @see rgpd-consentement-checkout.php   Ajoute la checkbox RGPD au checkout
 */

// Sécurité : empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   SECTION 1 : PROTECTION DE LA BOUTIQUE
   =============================================================================
   Redirection des pages WooCommerce publiques vers l'accueil.
   Seuls le checkout et mon-compte restent accessibles.
============================================================================= */

/**
 * Redirige les pages boutique WooCommerce vers l'accueil.
 *
 * Pages bloquées :
 * - is_shop()             : /boutique/
 * - is_product()          : /produit/xxx/
 * - is_product_category() : /categorie-produit/xxx/
 * - is_product_tag()      : /etiquette-produit/xxx/
 *
 * @since 1.0.0
 * @hook template_redirect
 */
add_action('template_redirect', function() {
    // Liste des pages à bloquer
    $is_shop_page = is_shop();
    $is_product   = is_product();           // Page produit individuelle
    $is_category  = is_product_category();  // Archive catégorie
    $is_tag       = is_product_tag();

    // On laisse passer UNIQUEMENT le Panier (cart), la Caisse (checkout) et le Compte (my-account)
    // Note: On laisse "is_cart()" accessible pour que la redirection vers le checkout fonctionne
    if ($is_shop_page || $is_product || $is_category || $is_tag) {
        wp_safe_redirect(home_url()); // Redirection vers l'accueil
        exit;
    }
});

/**
 * 2. FLUX : Sauter l'étape "Panier" (Direct Checkout)
 * Quand votre script JS ajoute au panier, WooCommerce redirige directement vers la caisse.
 */
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    return wc_get_checkout_url();
});

// Sécurité supplémentaire : si on accède manuellement à /panier/, on va au checkout
add_action('template_redirect', function() {
    if (is_cart() && !WC()->cart->is_empty()) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
});

/**
 * 3. UI : Simplifier les champs de la page de Paiement (Checkout)
 * On ne garde que l'essentiel pour du service.
 */
add_filter('woocommerce_checkout_fields', function($fields) {
    // Supprimer toute la section expédition
    unset($fields['shipping']);

    // Nettoyer les champs de facturation
    // On garde : first_name, last_name, email, phone.
    unset($fields['billing']['billing_company']);   // Société
    unset($fields['billing']['billing_country']);   // Pays
    unset($fields['billing']['billing_address_1']); // Adresse 1
    unset($fields['billing']['billing_address_2']); // Adresse 2
    unset($fields['billing']['billing_city']);      // Ville
    unset($fields['billing']['billing_state']);     // État/Région
    unset($fields['billing']['billing_postcode']);  // Code postal

    // Supprimer les commentaires de commande ("Notes")
    unset($fields['order']['order_comments']);

    return $fields;
});

// Rendre les champs restants (Nom, Prénom, Email) obligatoires ou non
// WooCommerce le fait déjà par défaut, mais on s'assure que le téléphone est requis si besoin
add_filter( 'woocommerce_billing_fields', function($fields) {
    $fields['billing_phone']['required'] = true; // Mettre à false si le téléphone est optionnel
    return $fields;
});

/**
 * 4. VISUEL : Cacher l'affichage du prix "0€" ou "Gratuit"
 * Utile si le thème tente d'afficher le prix du produit avant le calcul final.
 */
add_filter('woocommerce_get_price_html', function($price, $product) {
    // Utilise la config centralisee (coworking-config.php)
    if (function_exists('cw_is_coworking_product') && cw_is_coworking_product($product->get_id())) {
        return ''; // Retourne vide
    }
    return $price;
}, 10, 2);

/**
 * 5. RETIRER les liens "Boutique" du fil d'ariane (Breadcrumb)
 */
add_filter('woocommerce_breadcrumb_defaults', function($defaults) {
    unset($defaults['home']);
    return $defaults;
});

// Supprime les notices " a été ajouté au panier" puisqu'on va direct au checkout
add_filter( 'wc_add_to_cart_message_html', '__return_false' );
