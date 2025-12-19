<?php
/**
 * =============================================================================
 * COWORKING CONTRACT SYSTEM - VERSION 2.1 PREMIUM
 * =============================================================================
 *
 * Système complet de génération automatique de contrats PDF pour les
 * réservations d'espaces de coworking SkyLounge.
 *
 * Ce module gère l'ensemble du cycle de vie des contrats :
 * - Génération automatique après paiement (si seuils atteints)
 * - Template HTML premium avec design professionnel
 * - Conversion en PDF via DOMPDF (plugin WPO WCPDF)
 * - Envoi par email avec pièce jointe
 * - Accès client sécurisé via "Mon compte" WooCommerce
 * - Interface admin avec métabox dédiée
 * - Page de configuration des informations entreprise
 * - Numérotation séquentielle annuelle (CW-YYYY-NNNNN)
 * - Système de vérification publique avec QR Code
 *
 * CONDITIONS DE GÉNÉRATION AUTOMATIQUE (AU MOINS UNE) :
 * - Durée de réservation >= 7 jours
 * - Montant total >= 200 EUR
 * - Formule "semaine" ou "mois"
 *
 * SÉCURITÉ :
 * - Protection contre la traversée de répertoire
 * - Tokens d'accès sécurisés avec hash_equals()
 * - Validation des nonces AJAX
 * - Headers de sécurité (X-Content-Type-Options, X-Frame-Options)
 *
 * PRÉREQUIS :
 * - WordPress 6.0+
 * - WooCommerce 8.0+ (compatible HPOS)
 * - ACF Pro 6.0+ pour les champs personnalisés
 * - (Optionnel) Plugin "PDF Invoices & Packing Slips for WooCommerce"
 *   pour la génération PDF native (sinon fallback HTML)
 *
 * @package     SkyLounge_Coworking
 * @subpackage  Contracts
 * @author      Jérémy VIDOCIN <contact@skylounge.fr>
 * @copyright   2025 SkyLounge Coworking
 * @license     Proprietary
 * @version     2.1.0
 * @since       1.0.0
 *
 * @see CW_CONTRACT_VERSION      Version actuelle du système
 * @see CW_CONTRACT_DIR          Répertoire de stockage des contrats
 * @see CW_CONTRACT_MIN_DAYS     Seuil de durée pour génération auto
 * @see CW_CONTRACT_MIN_AMOUNT   Seuil de montant pour génération auto
 *
 * @link https://github.com/jeremyvidocin/SkyLounge-R-servation
 */

// Sécurité: empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/* =============================================================================
   CONFIGURATION & CONSTANTES
============================================================================= */

// Version du système
define('CW_CONTRACT_VERSION', '2.1');

// Répertoire de stockage des contrats
define('CW_CONTRACT_DIR', WP_CONTENT_DIR . '/uploads/coworking-contracts/');
define('CW_CONTRACT_URL', WP_CONTENT_URL . '/uploads/coworking-contracts/');

// Seuils pour génération automatique de contrat
// Un contrat sera généré si AU MOINS UNE de ces conditions est remplie:
define('CW_CONTRACT_MIN_DAYS', 7);       // Générer si >= 7 jours
define('CW_CONTRACT_MIN_AMOUNT', 200);   // OU si >= 200 EUR
define('CW_CONTRACT_FORMULAS', ['mois', 'semaine']); // OU si formule dans cette liste

/* =============================================================================
   INFORMATIONS ENTREPRISE
============================================================================= */

/**
 * Récupère les informations légales de l'entreprise pour le contrat.
 *
 * Les valeurs sont stockées en options WordPress et configurables
 * via le menu WooCommerce > Contrats Coworking.
 *
 * @since 1.0.0
 * @since 2.1.0 Ajout du champ RCS
 *
 * @return array {
 *     Tableau associatif des informations entreprise.
 *
 *     @type string $name     Raison sociale de l'entreprise.
 *     @type string $legal    Forme juridique et capital.
 *     @type string $address  Adresse complète du siège social.
 *     @type string $siret    Numéro SIRET (14 chiffres).
 *     @type string $tva      Numéro de TVA intracommunautaire.
 *     @type string $email    Email de contact principal.
 *     @type string $phone    Téléphone de contact.
 *     @type string $website  URL du site web.
 *     @type string $logo_url URL du logo entreprise.
 *     @type string $rcs      Numéro RCS et ville d'immatriculation.
 * }
 *
 * @example
 * $company = cw_get_company_info();
 * echo $company['name']; // "SkyLounge Coworking"
 * echo $company['siret']; // "12345678901234"
 */
function cw_get_company_info() {
    return [
        'name'      => get_option('cw_company_name', get_bloginfo('name')),
        'legal'     => get_option('cw_company_legal', 'SAS au capital de 10 000 EUR'),
        'address'   => get_option('cw_company_address', ''),
        'siret'     => get_option('cw_company_siret', ''),
        'tva'       => get_option('cw_company_tva', ''),
        'email'     => get_option('cw_company_email', get_option('admin_email')),
        'phone'     => get_option('cw_company_phone', ''),
        'website'   => get_option('cw_company_website', home_url()),
        'logo_url'  => get_option('cw_company_logo', ''),
        'rcs'       => get_option('cw_company_rcs', ''),
    ];
}

/* =============================================================================
   INITIALISATION - Création du répertoire de stockage
============================================================================= */

add_action('init', function() {
    // Créer le répertoire si nécessaire
    if (!file_exists(CW_CONTRACT_DIR)) {
        wp_mkdir_p(CW_CONTRACT_DIR);

        // Protection .htaccess (empêche l'accès direct aux fichiers)
        $htaccess = CW_CONTRACT_DIR . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\nDeny from all");
        }

        // Index.php de sécurité
        $index = CW_CONTRACT_DIR . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }
    }
});

/* =============================================================================
   NOTIFICATIONS ADMIN - PDF FALLBACK WARNING
============================================================================= */

add_action('admin_notices', function() {
    // Notification si fallback HTML utilisé (plugin PDF absent)
    if (get_transient('cw_pdf_fallback_warning')) {
        $plugin_url = admin_url('plugin-install.php?s=woocommerce+pdf+invoices&tab=search&type=term');
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Coworking Contrats :</strong> Un contrat a été généré en HTML car le plugin PDF n\'est pas disponible. ';
        echo 'Pour générer des PDF, installez <a href="' . esc_url($plugin_url) . '">PDF Invoices & Packing Slips for WooCommerce</a>.</p>';
        echo '</div>';
    }
});

/* =============================================================================
   NUMÉROTATION DES CONTRATS
============================================================================= */

/**
 * Génère un numéro de contrat unique avec numérotation séquentielle annuelle.
 *
 * Le compteur est réinitialisé chaque année (stocké en option WordPress).
 * Format : CW-YYYY-NNNNN (ex: CW-2025-00001)
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return string Numéro de contrat unique au format CW-YYYY-NNNNN.
 *
 * @example
 * $number = cw_generate_contract_number();
 * // Retourne : "CW-2025-00042" (42ème contrat de 2025)
 *
 * @see get_option()    Récupère le compteur actuel.
 * @see update_option() Incrémente le compteur.
 */
function cw_generate_contract_number() {
    $year = date('Y');
    $option_key = 'cw_contract_counter_' . $year;

    $counter = (int) get_option($option_key, 0);
    $counter++;
    update_option($option_key, $counter);

    return sprintf('CW-%s-%05d', $year, $counter);
}

/**
 * Vérifie si un contrat doit être généré pour une commande donnée.
 *
 * Un contrat est généré si AU MOINS UNE de ces conditions est remplie :
 * - La formule est "semaine" ou "mois" (CW_CONTRACT_FORMULAS)
 * - La durée totale >= 7 jours (CW_CONTRACT_MIN_DAYS)
 * - Le montant total >= 200 EUR (CW_CONTRACT_MIN_AMOUNT)
 *
 * @since 1.0.0
 * @since 2.0.0 Ajout de la vérification par formule.
 *
 * @param int $order_id ID de la commande WooCommerce.
 *
 * @return bool True si un contrat doit être généré, false sinon.
 *
 * @example
 * if (cw_should_generate_contract($order_id)) {
 *     cw_generate_contract_pdf($order_id);
 * }
 *
 * @see CW_CONTRACT_MIN_DAYS    Seuil de durée minimum.
 * @see CW_CONTRACT_MIN_AMOUNT  Seuil de montant minimum.
 * @see CW_CONTRACT_FORMULAS    Formules qui génèrent toujours un contrat.
 */
function cw_should_generate_contract($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return false;

    foreach ($order->get_items() as $item) {
        $offre_id = intval($item->get_meta('_cw_offre_id'));
        if (!$offre_id) continue;

        $formule  = $item->get_meta('_cw_formule');
        $quantity = (int) ($item->get_meta('_cw_quantity') ?: 1);
        $price    = (float) ($item->get_meta('_cw_price') ?: $item->get_total());

        // Calcul du nombre de jours
        $bloc_days = ['journee' => 1, 'demi_journee' => 0.5, 'semaine' => 7, 'mois' => 30];
        $total_days = ($bloc_days[$formule] ?? 1) * $quantity;

        // Vérification des seuils - si AU MOINS UN est atteint, on génère
        if (in_array($formule, CW_CONTRACT_FORMULAS)) return true;
        if ($total_days >= CW_CONTRACT_MIN_DAYS) return true;
        if ($price >= CW_CONTRACT_MIN_AMOUNT) return true;
    }

    return false;
}

/* =============================================================================
   TEMPLATE HTML DU CONTRAT - DESIGN PREMIUM
============================================================================= */

/**
 * Génère le template HTML complet du contrat avec design professionnel.
 *
 * Le template inclut :
 * - En-tête avec logo et informations entreprise
 * - Identification des parties (prestataire / client)
 * - Détails de la réservation (espace, dates, tarifs)
 * - Articles juridiques (12 articles)
 * - Zone de signatures avec QR Code de vérification
 * - Conditions générales sur page 2
 *
 * Le HTML est optimisé pour la conversion PDF via DOMPDF :
 * - Pagination correcte avec @page CSS
 * - Évitement des coupures de texte (page-break-inside: avoid)
 * - Polices compatibles (Segoe UI, DejaVu Sans)
 *
 * @since 1.0.0
 * @since 2.1.0 Refonte complète du design, ajout QR Code, pagination DOMPDF.
 *
 * @param int    $order_id        ID de la commande WooCommerce.
 * @param string $contract_number Numéro de contrat (ex: CW-2025-00042).
 *
 * @return string HTML complet du contrat, ou chaîne vide si données manquantes.
 *
 * @example
 * $html = cw_generate_contract_html(123, 'CW-2025-00042');
 * if (!empty($html)) {
 *     // Convertir en PDF ou afficher
 * }
 *
 * @see cw_get_company_info()      Récupère les infos entreprise.
 * @see cw_get_included_services() Récupère les services de l'offre.
 */
function cw_generate_contract_html($order_id, $contract_number) {
    $order = wc_get_order($order_id);
    if (!$order) return '';

    $company = cw_get_company_info();

    // Récupérer les données de réservation
    $reservation_data = [];
    foreach ($order->get_items() as $item) {
        $offre_id = intval($item->get_meta('_cw_offre_id'));
        if (!$offre_id) continue;

        $reservation_data = [
            'offre_id'    => $offre_id,
            'offre_name'  => $item->get_meta('_cw_offre_name') ?: get_the_title($offre_id),
            'start'       => $item->get_meta('_cw_start'),
            'end'         => $item->get_meta('_cw_end'),
            'formule'     => $item->get_meta('_cw_formule'),
            'quantity'    => (int) ($item->get_meta('_cw_quantity') ?: 1),
            'unit_price'  => (float) ($item->get_meta('_cw_unit_price') ?: 0),
            'total_price' => (float) ($item->get_meta('_cw_price') ?: $item->get_total()),
        ];
        break;
    }

    if (empty($reservation_data)) return '';

    // Calcul durée
    $bloc_days = ['journee' => 1, 'demi_journee' => 0.5, 'semaine' => 7, 'mois' => 30];
    $total_days = ($bloc_days[$reservation_data['formule']] ?? 1) * $reservation_data['quantity'];

    // Labels corrigés pour le français
    $formule_labels = [
        'demi_journee' => ['singular' => 'Demi-journée', 'plural' => 'Demi-journées'],
        'journee'      => ['singular' => 'Journée', 'plural' => 'Journées'],
        'semaine'      => ['singular' => 'Semaine', 'plural' => 'Semaines'],
        'mois'         => ['singular' => 'Mois', 'plural' => 'Mois'] // Pas de 's' au pluriel
    ];

    $formule_label = $formule_labels[$reservation_data['formule']] ?? ['singular' => 'Unité', 'plural' => 'Unités'];
    $formule_display = $reservation_data['quantity'] > 1 ? $formule_label['plural'] : $formule_label['singular'];

    // Client
    $client_name    = $order->get_formatted_billing_full_name();
    $client_email   = $order->get_billing_email();
    $client_phone   = $order->get_billing_phone();
    $client_company = $order->get_billing_company();
    $client_address = $order->get_formatted_billing_address();

    // Dates formatées
    $start_formatted = date_i18n('d F Y', strtotime($reservation_data['start']));
    $end_formatted   = date_i18n('d F Y', strtotime($reservation_data['end']));
    $today_formatted = date_i18n('d F Y');

    // Services inclus
    $services = cw_get_included_services($reservation_data['offre_id']);

    // Calcul prix unitaire
    if ($reservation_data['unit_price'] <= 0 && $reservation_data['quantity'] > 0) {
        $reservation_data['unit_price'] = $reservation_data['total_price'] / $reservation_data['quantity'];
    }

    // URL de vérification sécurisée (sans admin-ajax visible)
    $verification_id = wp_hash($order_id . $contract_number . wp_salt());
    update_post_meta($order_id, '_cw_verification_id', $verification_id);
    $verification_url = home_url('/verifier-contrat/' . $verification_id);
    
    // QR Code via QuickChart (alternative à Google Charts)
    $qr_code_url = 'https://quickchart.io/qr?size=100&text=' . urlencode($verification_url);

    // Coordonnées d'urgence (à personnaliser)
    $emergency_phone = get_option('cw_emergency_phone', $company['phone']);
    $emergency_email = get_option('cw_emergency_email', $company['email']);

    // =========================================================================
    // COULEURS DE MARQUE - Alignées sur la charte graphique SkyLounge
    // =========================================================================
    $brand_primary = '#1e73be';      // Bleu SkyLounge (identique au calendrier)
    $brand_secondary = '#155a96';    // Bleu foncé
    $brand_accent = '#5AB7E2';       // Bleu clair (accent)
    $brand_success = '#10b981';      // Vert (validations)
    $brand_light = '#e8f4fd';        // Fond bleu très clair
    $brand_border = '#d1d5db';       // Bordures grises

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat <?php echo esc_html($contract_number); ?></title>
    <style>
        /* =====================================================================
           DOMPDF - PAGINATION CORRECTE
        ===================================================================== */
        @page {
            size: A4;
            margin: 15mm 15mm 25mm 15mm;
            @bottom-center {
                content: "Contrat n°<?php echo esc_html($contract_number); ?> — Page " counter(page) " sur " counter(pages);
                font-size: 7.5pt;
                color: #64748b;
                font-family: 'Segoe UI', sans-serif;
                margin-bottom: 10mm;
            }
        }

        body {
            counter-reset: page 1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #1e293b;
            background: #fff;
            margin: 0;
            padding: 0;
        }

        /* =====================================================================
           EN-TÊTE PREMIUM
        ===================================================================== */
        .header {
            display: table;
            width: 100%;
            padding: 0 0 18px 0;
            border-bottom: 3px solid <?php echo $brand_primary; ?>;
            margin-bottom: 20px;
            page-break-after: avoid;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 30%;
        }

        .header-center {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            width: 40%;
        }

        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 30%;
        }

        .logo {
            max-width: 120px;
            max-height: 60px;
        }

        .company-name {
            font-size: 20pt;
            font-weight: 700;
            color: <?php echo $brand_primary; ?>;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 4px;
        }

        .company-tagline {
            font-size: 8pt;
            color: <?php echo $brand_secondary; ?>;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .contract-badge {
            background: <?php echo $brand_primary; ?>;
            color: #fff;
            padding: 8px 15px;
            font-size: 9pt;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .contract-number-badge {
            background: <?php echo $brand_accent; ?>;
            color: <?php echo $brand_primary; ?>;
            padding: 6px 12px;
            font-size: 10pt;
            font-weight: 700;
            display: inline-block;
            margin-top: 5px;
        }

        /* =====================================================================
           TITRE PRINCIPAL
        ===================================================================== */
        .main-title {
            text-align: center;
            margin: 25px 0 20px 0;
            padding: 15px 0;
            border-top: 1px solid <?php echo $brand_border; ?>;
            border-bottom: 1px solid <?php echo $brand_border; ?>;
            page-break-after: avoid;
        }

        .main-title h1 {
            font-size: 14pt;
            font-weight: 700;
            color: <?php echo $brand_primary; ?>;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 6px;
        }

        .main-title .subtitle {
            font-size: 10pt;
            color: #64748b;
            font-style: italic;
        }

        .contract-meta {
            text-align: center;
            font-size: 9pt;
            color: #64748b;
            margin-bottom: 20px;
        }

        /* =====================================================================
           ARTICLES - Optimisé pour pagination
        ===================================================================== */
        .article {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .article-title {
            font-size: 10.5pt;
            font-weight: 700;
            color: <?php echo $brand_primary; ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            padding: 8px 12px;
            background: <?php echo $brand_light; ?>;
            border-left: 4px solid <?php echo $brand_accent; ?>;
            page-break-after: avoid;
        }

        .article-content {
            padding: 0 12px;
            text-align: justify;
            font-size: 9.5pt;
        }

        .article-content p {
            margin-bottom: 8px;
        }

        /* =====================================================================
           TABLEAUX PARTIES
        ===================================================================== */
        .parties-grid {
            display: table;
            width: 100%;
            margin: 12px 0;
            page-break-inside: avoid;
        }

        .party-box {
            display: table-cell;
            width: 48%;
            vertical-align: top;
            padding: 15px;
            border: 1px solid <?php echo $brand_border; ?>;
            background: #fff;
        }

        .party-box.prestataire {
            border-left: 4px solid <?php echo $brand_primary; ?>;
        }

        .party-box.client {
            border-left: 4px solid <?php echo $brand_accent; ?>;
        }

        .party-spacer {
            display: table-cell;
            width: 4%;
        }

        .party-label {
            font-size: 8pt;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 10px;
            margin-bottom: 10px;
            display: inline-block;
        }

        .party-box.prestataire .party-label {
            background: <?php echo $brand_primary; ?>;
        }

        .party-box.client .party-label {
            background: <?php echo $brand_accent; ?>;
            color: #fff;
        }

        .party-name {
            font-size: 11pt;
            font-weight: 700;
            color: <?php echo $brand_primary; ?>;
            margin-bottom: 8px;
        }

        .party-details {
            font-size: 9pt;
            line-height: 1.6;
            color: #475569;
        }

        .party-details .label {
            color: #94a3b8;
            font-size: 8pt;
        }

        /* =====================================================================
           TABLEAUX DONNÉES
        ===================================================================== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 9.5pt;
            page-break-inside: avoid;
        }

        .data-table th,
        .data-table td {
            padding: 10px 14px;
            text-align: left;
            border: 1px solid <?php echo $brand_border; ?>;
        }

        .data-table th {
            background: <?php echo $brand_light; ?>;
            font-weight: 600;
            color: <?php echo $brand_primary; ?>;
            width: 35%;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .data-table td {
            background: #fff;
        }

        .data-table .highlight-row th,
        .data-table .highlight-row td {
            background: <?php echo $brand_primary; ?>;
            color: #fff;
            font-weight: 700;
        }

        .data-table .highlight-row th {
            color: #fff;
        }

        /* =====================================================================
           LISTE DE SERVICES
        ===================================================================== */
        .services-list {
            margin: 10px 0 10px 0;
            padding: 0;
            list-style: none;
        }

        .services-list li {
            position: relative;
            padding: 6px 0 6px 25px;
            font-size: 9.5pt;
            border-bottom: 1px dotted <?php echo $brand_border; ?>;
        }

        .services-list li:last-child {
            border-bottom: none;
        }

        .services-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: <?php echo $brand_accent; ?>;
            font-weight: 700;
            font-size: 11pt;
        }

        /* =====================================================================
           ENCADRÉ IMPORTANT (CLAUSE JURIDIQUE)
        ===================================================================== */
        .legal-notice {
            border: 2px solid <?php echo $brand_primary; ?>;
            margin: 15px 0;
            background: #fff;
            page-break-inside: avoid;
        }

        .legal-notice-header {
            background: <?php echo $brand_primary; ?>;
            color: #fff;
            padding: 8px 15px;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        .legal-notice-content {
            padding: 15px;
            font-size: 9pt;
            text-align: justify;
        }

        .legal-notice-content p {
            margin-bottom: 8px;
        }

        .legal-notice-content strong {
            color: <?php echo $brand_primary; ?>;
        }

        /* =====================================================================
           COORDONNÉES D'URGENCE
        ===================================================================== */
        .emergency-box {
            background: linear-gradient(135deg, <?php echo $brand_light; ?> 0%, #fff 100%);
            border: 1px solid <?php echo $brand_accent; ?>;
            border-left: 4px solid <?php echo $brand_accent; ?>;
            padding: 12px 15px;
            margin: 15px 0;
            page-break-inside: avoid;
        }

        .emergency-title {
            font-size: 9pt;
            font-weight: 700;
            color: <?php echo $brand_primary; ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .emergency-title::before {
            content: "☎ ";
        }

        .emergency-content {
            display: table;
            width: 100%;
        }

        .emergency-item {
            display: table-cell;
            width: 50%;
            font-size: 9pt;
        }

        .emergency-label {
            color: #64748b;
            font-size: 8pt;
        }

        .emergency-value {
            font-weight: 600;
            color: <?php echo $brand_primary; ?>;
        }

        /* =====================================================================
           SIGNATURES AVEC QR CODE
        ===================================================================== */
        .signatures-section {
            margin-top: 20px;
            page-break-inside: avoid;
            page-break-before: auto;
        }

        .signatures-title {
            font-size: 10pt;
            font-weight: 700;
            color: <?php echo $brand_primary; ?>;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid <?php echo $brand_border; ?>;
        }

        .acceptance-text {
            text-align: justify;
            font-size: 9pt;
            color: #475569;
            margin-bottom: 20px;
            padding: 10px;
            background: <?php echo $brand_light; ?>;
            border-radius: 4px;
        }

        .signatures-grid {
            display: table;
            width: 100%;
            page-break-inside: avoid;
        }

        .signature-box {
            display: table-cell;
            width: 35%;
            vertical-align: top;
            text-align: center;
            padding: 10px;
        }

        .qr-box {
            display: table-cell;
            width: 30%;
            vertical-align: middle;
            text-align: center;
            padding: 10px;
        }

        .signature-label {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .signature-line {
            border-bottom: 1px solid <?php echo $brand_primary; ?>;
            height: 50px;
            margin-bottom: 8px;
        }

        .signature-name {
            font-size: 9pt;
            font-weight: 600;
            color: <?php echo $brand_primary; ?>;
        }

        .signature-date {
            font-size: 8pt;
            color: #64748b;
            font-style: italic;
            margin-top: 5px;
        }

        .qr-code {
            width: 80px;
            height: 80px;
            margin: 0 auto 8px auto;
        }

        .qr-label {
            font-size: 7pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* =====================================================================
           PAGE 2 - CONDITIONS GÉNÉRALES
        ===================================================================== */
        .page-break {
            page-break-before: always;
        }

        .conditions-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid <?php echo $brand_primary; ?>;
            page-break-after: avoid;
        }

        .conditions-header h2 {
            font-size: 12pt;
            font-weight: 700;
            color: <?php echo $brand_primary; ?>;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .sub-article {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .sub-article-title {
            font-size: 9.5pt;
            font-weight: 700;
            color: <?php echo $brand_primary; ?>;
            margin-bottom: 6px;
            padding-left: 10px;
            border-left: 3px solid <?php echo $brand_accent; ?>;
        }

        .sub-article p {
            font-size: 9pt;
            text-align: justify;
            margin-bottom: 5px;
            padding-left: 10px;
        }

        .sub-article ul {
            margin: 8px 0 8px 25px;
            font-size: 9pt;
        }

        .sub-article li {
            margin-bottom: 4px;
        }

        /* =====================================================================
           UTILITAIRES
        ===================================================================== */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-10 { margin-top: 10px; }
        .mb-10 { margin-bottom: 10px; }
        
        /* =====================================================================
           ÉVITER LES COUPURES MALHEUREUSES
        ===================================================================== */
        .keep-together {
            page-break-inside: avoid;
        }
        
        .break-before {
            page-break-before: always;
        }
        
        .break-after {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <!-- ===================================================================
         PAGE 1 : CONTRAT PRINCIPAL
    ==================================================================== -->

    <!-- EN-TÊTE PREMIUM -->
    <div class="header">
        <div class="header-left">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="<?php echo esc_url($company['logo_url']); ?>" alt="<?php echo esc_attr($company['name']); ?>" class="logo">
            <?php endif; ?>
        </div>
        <div class="header-center">
            <div class="company-name"><?php echo esc_html($company['name']); ?></div>
            <div class="company-tagline">Espace de Coworking Premium</div>
        </div>
        <div class="header-right">
            <div class="contract-badge">CONTRAT</div>
            <div class="contract-number-badge"><?php echo esc_html($contract_number); ?></div>
        </div>
    </div>

    <!-- TITRE PRINCIPAL -->
    <div class="main-title">
        <h1>Contrat de Prestation de Services</h1>
        <div class="subtitle">Mise à disposition d'espace de travail équipé</div>
    </div>

    <div class="contract-meta">
        Établi le <?php echo esc_html($today_formatted); ?>
    </div>

    <!-- ARTICLE 1 : LES PARTIES -->
    <div class="article keep-together">
        <div class="article-title">Article 1 — Identification des Parties</div>
        <div class="article-content">
            <p>Le présent contrat est conclu entre :</p>

            <div class="parties-grid">
                <div class="party-box prestataire">
                    <div class="party-label">Le Prestataire</div>
                    <div class="party-name"><?php echo esc_html($company['name']); ?></div>
                    <div class="party-details">
                        <?php echo esc_html($company['legal']); ?><br>
                        <span class="label">Siège :</span> <?php echo esc_html($company['address']); ?><br>
                        <span class="label">SIRET :</span> <?php echo esc_html($company['siret']); ?><br>
                        <?php if ($company['rcs']): ?><span class="label">RCS :</span> <?php echo esc_html($company['rcs']); ?><br><?php endif; ?>
                        <span class="label">Email :</span> <?php echo esc_html($company['email']); ?><br>
                        <?php if ($company['phone']): ?><span class="label">Tél. :</span> <?php echo esc_html($company['phone']); ?><?php endif; ?>
                    </div>
                </div>
                <div class="party-spacer"></div>
                <div class="party-box client">
                    <div class="party-label">Le Client</div>
                    <div class="party-name"><?php echo esc_html($client_name); ?></div>
                    <div class="party-details">
                        <?php if ($client_company): ?><?php echo esc_html($client_company); ?><br><?php endif; ?>
                        <?php echo wp_kses_post(str_replace('<br/>', '<br>', $client_address)); ?><br>
                        <span class="label">Email :</span> <?php echo esc_html($client_email); ?><br>
                        <?php if ($client_phone): ?><span class="label">Tél. :</span> <?php echo esc_html($client_phone); ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <p style="text-align: center; font-size: 8.5pt; color: #64748b; margin-top: 10px;">
                Ci-après dénommés individuellement « la Partie » et collectivement « les Parties ».
            </p>
        </div>
    </div>

    <!-- ARTICLE 2 : OBJET DU CONTRAT -->
    <div class="article keep-together">
        <div class="article-title">Article 2 — Objet du Contrat</div>
        <div class="article-content">
            <p>Le présent contrat définit les conditions de mise à disposition d'un espace de travail équipé dénommé <strong>« <?php echo esc_html($reservation_data['offre_name']); ?> »</strong>, situé au :</p>
            <p style="text-align: center; font-weight: 600; color: <?php echo $brand_primary; ?>; margin: 10px 0;">
                <?php echo esc_html($company['address']); ?>
            </p>
            <p>Cette mise à disposition comprend les prestations suivantes :</p>
            <ul class="services-list">
                <?php foreach ($services as $service): ?>
                    <li><?php echo esc_html($service); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- ARTICLE 3 : DURÉE -->
    <div class="article keep-together">
        <div class="article-title">Article 3 — Durée du Contrat</div>
        <div class="article-content">
            <p>Le présent contrat est conclu pour une durée déterminée :</p>

            <table class="data-table">
                <tr>
                    <th>Date de début</th>
                    <td><?php echo esc_html($start_formatted); ?></td>
                </tr>
                <tr>
                    <th>Date de fin</th>
                    <td><?php echo esc_html($end_formatted); ?> (inclus)</td>
                </tr>
                <tr>
                    <th>Durée totale</th>
                    <td><strong><?php echo $reservation_data['quantity']; ?> <?php echo esc_html($formule_display); ?></strong> (soit <?php echo $total_days; ?> jour<?php echo $total_days > 1 ? 's' : ''; ?>)</td>
                </tr>
            </table>

            <p>Le contrat prend fin de plein droit à l'échéance prévue. <strong>Il n'est pas soumis à tacite reconduction.</strong> Toute prolongation fera l'objet d'une nouvelle convention.</p>
        </div>
    </div>

    <!-- ARTICLE 4 : CONDITIONS FINANCIÈRES -->
    <div class="article keep-together">
        <div class="article-title">Article 4 — Conditions Financières</div>
        <div class="article-content">
            <p>En contrepartie des prestations décrites, le Client s'acquitte de la redevance suivante :</p>

            <table class="data-table">
                <tr>
                    <th>Formule</th>
                    <td><?php echo esc_html($formule_display); ?></td>
                </tr>
                <tr>
                    <th>Tarif unitaire</th>
                    <td><?php echo number_format($reservation_data['unit_price'], 2, ',', ' '); ?> € TTC / <?php echo esc_html($formule_label['singular']); ?></td>
                </tr>
                <tr>
                    <th>Quantité</th>
                    <td><?php echo $reservation_data['quantity']; ?> <?php echo esc_html($formule_display); ?></td>
                </tr>
                <tr class="highlight-row">
                    <th>Montant Total TTC</th>
                    <td><?php echo number_format($reservation_data['total_price'], 2, ',', ' '); ?> €</td>
                </tr>
                <tr>
                    <th>Statut</th>
                    <td style="color: <?php echo $brand_success; ?>; font-weight: 600;">✓ Réglé intégralement (Commande n°<?php echo intval($order_id); ?>)</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- ARTICLE 5 : NATURE JURIDIQUE -->
    <div class="article keep-together">
        <div class="article-title">Article 5 — Nature Juridique du Contrat</div>
        <div class="article-content">
            <div class="legal-notice">
                <div class="legal-notice-header">⚖ Clause Essentielle et Déterminante</div>
                <div class="legal-notice-content">
                    <p>Les Parties reconnaissent que le présent contrat constitue un <strong>contrat de prestation de services</strong> au sens des articles 1101 et suivants du Code civil, et non un bail.</p>
                    <p>La mise à disposition de l'espace s'accompagne de services substantiels (accueil, ménage, maintenance, équipements, WiFi) constituant l'élément essentiel de la prestation.</p>
                    <p><strong>Ce contrat ne confère au Client aucun droit réel sur les locaux</strong>, ni aucun droit au maintien dans les lieux au-delà de la durée convenue. Il ne peut être requalifié en bail commercial, professionnel ou civil.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- COORDONNÉES D'URGENCE -->
    <div class="emergency-box keep-together">
        <div class="emergency-title">Coordonnées d'urgence — Assistance 7j/7</div>
        <div class="emergency-content">
            <div class="emergency-item">
                <span class="emergency-label">Téléphone :</span><br>
                <span class="emergency-value"><?php echo esc_html($emergency_phone); ?></span>
            </div>
            <div class="emergency-item">
                <span class="emergency-label">Email :</span><br>
                <span class="emergency-value"><?php echo esc_html($emergency_email); ?></span>
            </div>
        </div>
    </div>

    <!-- SIGNATURES AVEC QR CODE -->
    <div class="signatures-section">
        <div class="signatures-title">Acceptation du Contrat</div>
        
        <div class="acceptance-text">
            En procédant au paiement de la réservation, le Client reconnaît avoir pris connaissance de l'ensemble des stipulations du présent contrat, des Conditions Générales de Vente et du Règlement Intérieur du Prestataire, et les accepter sans réserve.
        </div>

        <div class="signatures-grid">
            <div class="signature-box">
                <div class="signature-label">Pour le Prestataire</div>
                <div class="signature-line"></div>
                <div class="signature-name"><?php echo esc_html($company['name']); ?></div>
            </div>
            <div class="qr-box">
                <img src="<?php echo esc_url($qr_code_url); ?>" alt="QR Code de vérification" class="qr-code">
                <div class="qr-label">Scanner pour vérifier<br>l'authenticité du contrat</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Pour le Client</div>
                <div class="signature-line"></div>
                <div class="signature-name"><?php echo esc_html($client_name); ?></div>
                <div class="signature-date">Accepté électroniquement le <?php echo esc_html($today_formatted); ?></div>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         PAGE 2 : CONDITIONS GÉNÉRALES
    ==================================================================== -->
    <div class="page-break"></div>

    <div class="conditions-header">
        <h2>Conditions Générales du Contrat</h2>
    </div>

    <div class="sub-article">
        <div class="sub-article-title">Article 6 — Obligations du Client</div>
        <p>Le Client s'engage à :</p>
        <ul>
            <li>Utiliser l'espace exclusivement à des fins professionnelles licites</li>
            <li>Respecter le règlement intérieur de l'espace</li>
            <li>Maintenir l'espace en état de propreté et signaler toute anomalie</li>
            <li>Ne pas sous-louer ou mettre l'espace à disposition de tiers</li>
            <li>Ne pas modifier les locaux ou équipements</li>
            <li>Respecter les horaires d'accès communiqués</li>
            <li>Justifier d'une assurance responsabilité civile professionnelle valide</li>
        </ul>
        <p>Le Client est seul responsable de ses effets personnels. Le Prestataire décline toute responsabilité en cas de vol, perte ou détérioration.</p>
    </div>

    <div class="sub-article">
        <div class="sub-article-title">Article 7 — Obligations du Prestataire</div>
        <p>Le Prestataire s'engage à :</p>
        <ul>
            <li>Mettre à disposition un espace conforme à la description</li>
            <li>Assurer le bon fonctionnement des équipements et services</li>
            <li>Maintenir les espaces communs en état de propreté</li>
            <li>Informer le Client de toute interruption prévisible</li>
            <li>Respecter la confidentialité des informations du Client</li>
        </ul>
        <p>Le Prestataire conserve un libre accès aux espaces pour la maintenance.</p>
    </div>

    <div class="sub-article">
        <div class="sub-article-title">Article 8 — Résiliation</div>
        <p>Le contrat peut être résilié de plein droit par le Prestataire, sans préavis ni indemnité, en cas de :</p>
        <ul>
            <li>Non-respect du règlement intérieur ou des présentes stipulations</li>
            <li>Comportement nuisant à la tranquillité des autres utilisateurs</li>
            <li>Usage contraire à la destination des locaux</li>
            <li>Défaut d'assurance responsabilité civile</li>
        </ul>
        <p>Aucun remboursement en cas de résiliation anticipée du fait du Client, sauf disposition contraire des CGV.</p>
    </div>

    <div class="sub-article">
        <div class="sub-article-title">Article 9 — Responsabilité et Assurance</div>
        <p>Le Client déclare être titulaire d'une assurance responsabilité civile professionnelle couvrant les dommages aux locaux, équipements ou tiers.</p>
        <p>La responsabilité du Prestataire est limitée aux dommages directs résultant d'une faute prouvée. Elle n'est pas engagée en cas de force majeure, fait d'un tiers ou faute du Client.</p>
    </div>

    <div class="sub-article">
        <div class="sub-article-title">Article 10 — Protection des Données Personnelles</div>
        <p>Les données personnelles sont traitées conformément au RGPD et à la loi Informatique et Libertés. Le Client dispose d'un droit d'accès, rectification, effacement et portabilité. Politique complète sur <?php echo esc_url($company['website']); ?>.</p>
    </div>

    <div class="sub-article">
        <div class="sub-article-title">Article 11 — Documents Contractuels</div>
        <p>Le présent contrat est complété par :</p>
        <ul>
            <li>Les Conditions Générales de Vente (CGV)</li>
            <li>Le Règlement Intérieur de l'espace</li>
            <li>La fiche descriptive de l'offre souscrite</li>
        </ul>
        <p>En cas de contradiction, les stipulations du présent contrat prévalent.</p>
    </div>

    <div class="sub-article">
        <div class="sub-article-title">Article 12 — Droit Applicable et Juridiction</div>
        <p>Le présent contrat est soumis au droit français. En cas de différend, les Parties rechercheront une solution amiable. À défaut d'accord sous 30 jours, le litige sera soumis aux tribunaux compétents du ressort du siège social du Prestataire.</p>
    </div>

</body>
</html>
    <?php
    return ob_get_clean();
}

/**
 * =============================================================================
 * CONFIGURATION ADDITIONNELLE - À AJOUTER DANS VOTRE PAGE ADMIN
 * =============================================================================
 * 
 * Ajoutez ces options dans votre page de configuration WordPress pour 
 * gérer les coordonnées d'urgence :
 */

// Hook pour ajouter les champs de configuration supplémentaires
add_action('admin_init', function() {
    // Champ téléphone d'urgence
    register_setting('cw_contract_settings', 'cw_emergency_phone');
    add_settings_field(
        'cw_emergency_phone',
        'Téléphone d\'urgence',
        function() {
            $value = get_option('cw_emergency_phone', '');
            echo '<input type="tel" name="cw_emergency_phone" value="' . esc_attr($value) . '" class="regular-text">';
            echo '<p class="description">Numéro affiché dans la section "Coordonnées d\'urgence" du contrat</p>';
        },
        'cw_contract_settings_page',
        'cw_contract_emergency_section'
    );

    // Champ email d'urgence
    register_setting('cw_contract_settings', 'cw_emergency_email');
    add_settings_field(
        'cw_emergency_email',
        'Email d\'urgence',
        function() {
            $value = get_option('cw_emergency_email', '');
            echo '<input type="email" name="cw_emergency_email" value="' . esc_attr($value) . '" class="regular-text">';
            echo '<p class="description">Email affiché dans la section "Coordonnées d\'urgence" du contrat</p>';
        },
        'cw_contract_settings_page',
        'cw_contract_emergency_section'
    );
});

/**
 * Récupère la liste des services inclus pour une offre de coworking.
 *
 * Tente de récupérer les services personnalisés depuis ACF (champ 'services_inclus').
 * Si le champ n'existe pas ou est vide, retourne une liste de services par défaut.
 *
 * @since 1.0.0
 *
 * @param int $offre_id ID du post CPT "offre" de coworking.
 *
 * @return array Liste des services inclus (chaînes de caractères).
 *
 * @example
 * $services = cw_get_included_services(42);
 * // Retourne : ['WiFi haut débit', 'Café offert', ...]
 *
 * @see get_field() Fonction ACF pour récupérer les champs personnalisés.
 */
function cw_get_included_services($offre_id) {
    // Services par défaut
    $default_services = [
        'Accès à l\'espace de travail dédié',
        'WiFi haut débit sécurisé',
        'Électricité et climatisation',
        'Accès aux espaces communs (cuisine, sanitaires)',
        'Ménage des parties communes',
        'Café et thé offerts',
    ];

    // Tenter de récupérer depuis ACF si disponible
    if (function_exists('get_field')) {
        $custom_services = get_field('services_inclus', $offre_id);
        if ($custom_services && is_array($custom_services)) {
            return $custom_services;
        }
    }

    return $default_services;
}

/* =============================================================================
   GENERATION PDF
============================================================================= */

/**
 * Génère le PDF du contrat
 * Utilise DOMPDF via le plugin WooCommerce PDF Invoices
 *
 * @param int $order_id ID de la commande
 * @param string|null $existing_number Numéro existant (pour régénération)
 * @return array Résultat de la génération
 */
function cw_generate_contract_pdf($order_id, $existing_number = null) {
    // Vérifier si le contrat existe déjà (sauf si on régénère)
    if ($existing_number === null) {
        $existing = get_post_meta($order_id, '_cw_contract_file', true);
        if ($existing && file_exists(CW_CONTRACT_DIR . $existing)) {
            return [
                'success' => true,
                'file' => $existing,
                'number' => get_post_meta($order_id, '_cw_contract_number', true),
                'already_exists' => true
            ];
        }
    }

    // Utiliser le numéro existant ou en générer un nouveau
    $contract_number = $existing_number ?: cw_generate_contract_number();

    // Générer le HTML
    $html = cw_generate_contract_html($order_id, $contract_number);
    if (empty($html)) {
        return ['success' => false, 'message' => 'Données de réservation introuvables'];
    }

    // Créer le répertoire si nécessaire
    $year_month = date('Y/m');
    $dir = CW_CONTRACT_DIR . $year_month . '/';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    $filename = $contract_number . '.pdf';
    $filepath = $dir . $filename;
    $relative_path = $year_month . '/' . $filename;

    $pdf_generated = false;

    // Méthode 1: DOMPDF via WooCommerce PDF Invoices & Packing Slips
    if (!$pdf_generated) {
        $wcpdf_path = WP_PLUGIN_DIR . '/woocommerce-pdf-invoices-packing-slips/vendor/autoload.php';
        if (file_exists($wcpdf_path)) {
            try {
                require_once $wcpdf_path;

                if (class_exists('Dompdf\Dompdf')) {
                    $options = new \Dompdf\Options();
                    $options->set('isRemoteEnabled', true);
                    $options->set('isHtml5ParserEnabled', true);
                    $options->set('defaultFont', 'DejaVu Sans');
                    $options->set('isFontSubsettingEnabled', true);
                    $options->set('defaultMediaType', 'print');
                    $options->set('isPhpEnabled', false);
                    $options->set('debugCss', false);
                    $options->set('debugLayout', false);

                    $dompdf = new \Dompdf\Dompdf($options);
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->set_option('enable_css_float', true);
                    $dompdf->render();

                    file_put_contents($filepath, $dompdf->output());
                    $pdf_generated = true;

                    error_log('CW Contract: PDF généré avec succès - ' . $contract_number);
                }
            } catch (Exception $e) {
                error_log('CW Contract PDF Error: ' . $e->getMessage());
            }
        }
    }

    // Méthode 2: Dompdf déjà chargé
    if (!$pdf_generated && class_exists('Dompdf\Dompdf')) {
        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            file_put_contents($filepath, $dompdf->output());
            $pdf_generated = true;
        } catch (Exception $e) {
            error_log('CW Contract PDF Error (direct): ' . $e->getMessage());
        }
    }

    // Fallback: HTML avec bouton impression
    if (!$pdf_generated) {
        $filename = $contract_number . '.html';
        $filepath = $dir . $filename;
        $relative_path = $year_month . '/' . $filename;

        // Ajouter barre d'impression
        $print_bar = '
        <style>
            @media print { .print-bar { display: none !important; } .container { margin-top: 0 !important; } }
            .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #1e40af; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; z-index: 9999; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
            .print-bar span { font-size: 14px; }
            .print-bar button { background: white; color: #1e40af; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.2s; }
            .print-bar button:hover { background: #f0f9ff; transform: scale(1.02); }
            .container { margin-top: 70px !important; }
        </style>';

        $html = str_replace('</head>', $print_bar . '</head>', $html);
        $html = str_replace('<body>', '<body><div class="print-bar"><span>Contrat ' . esc_html($contract_number) . '</div>', $html);

        file_put_contents($filepath, $html);

        error_log('CW Contract: Fallback HTML généré - ' . $contract_number);
    }

    // Hash pour vérification
    $file_hash = hash_file('sha256', $filepath);

    // Stocker les métadonnées
    update_post_meta($order_id, '_cw_contract_number', $contract_number);
    update_post_meta($order_id, '_cw_contract_file', $relative_path);
    update_post_meta($order_id, '_cw_contract_hash', $file_hash);
    update_post_meta($order_id, '_cw_contract_generated', current_time('mysql'));
    update_post_meta($order_id, '_cw_contract_format', $pdf_generated ? 'pdf' : 'html');

    return [
        'success' => true,
        'file' => $relative_path,
        'number' => $contract_number,
        'format' => $pdf_generated ? 'pdf' : 'html',
        'hash' => $file_hash
    ];
}

/* =============================================================================
   HOOK: GENERATION AUTOMATIQUE APRES PAIEMENT
============================================================================= */

add_action('woocommerce_order_status_completed', 'cw_maybe_generate_contract_on_complete', 20);

/**
 * Génère automatiquement un contrat lorsqu'une commande passe en statut "Terminée".
 *
 * Cette fonction est hookée sur 'woocommerce_order_status_completed' avec une priorité
 * de 20 pour s'exécuter après les actions WooCommerce standard.
 *
 * Workflow :
 * 1. Vérifie si la commande nécessite un contrat (via cw_should_generate_contract())
 * 2. Vérifie si un contrat n'existe pas déjà
 * 3. Génère le PDF/HTML
 * 4. Ajoute une note à la commande
 * 5. Envoie l'email au client
 *
 * @since 1.0.0
 * @since 2.0.0 Ajout de la notification admin si fallback HTML.
 *
 * @param int $order_id ID de la commande WooCommerce qui vient d'être complétée.
 *
 * @return void
 *
 * @see cw_should_generate_contract() Vérifie les seuils de génération.
 * @see cw_generate_contract_pdf()    Génère le fichier PDF/HTML.
 * @see cw_send_contract_email()      Envoie le contrat par email.
 *
 * @hook woocommerce_order_status_completed
 */
function cw_maybe_generate_contract_on_complete($order_id) {
    // Vérifier si c'est une commande coworking nécessitant un contrat
    if (!cw_should_generate_contract($order_id)) {
        return;
    }

    // Vérifier si le contrat existe déjà
    if (get_post_meta($order_id, '_cw_contract_number', true)) {
        return;
    }

    // Générer le contrat
    $result = cw_generate_contract_pdf($order_id);

    if ($result['success']) {
        $order = wc_get_order($order_id);
        if ($order) {
            // Note avec alerte si fallback HTML (PDF non disponible)
            $note = sprintf('Contrat généré automatiquement : %s (%s)', $result['number'], strtoupper($result['format']));

            if ($result['format'] === 'html') {
                $note .= ' - ATTENTION: Plugin PDF non disponible, contrat en HTML uniquement';
                // Transient pour notification admin
                set_transient('cw_pdf_fallback_warning', true, DAY_IN_SECONDS);
            }

            $order->add_order_note($note);
        }

        // Envoyer le contrat par email
        cw_send_contract_email($order_id);
    } else {
        error_log('CW Contract Generation Failed for Order #' . $order_id . ': ' . ($result['message'] ?? 'Unknown error'));

        // Ajouter une note visible sur la commande
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('ERREUR: Échec génération contrat - ' . ($result['message'] ?? 'erreur inconnue'));
        }
    }
}

/* =============================================================================
   ENVOI EMAIL AVEC CONTRAT
============================================================================= */

/**
 * Envoie le contrat par email au client avec le fichier en pièce jointe.
 *
 * L'email contient :
 * - Récapitulatif de la réservation (espace, dates, montant)
 * - Lien sécurisé vers le contrat en ligne
 * - Fichier PDF/HTML en pièce jointe
 * - Informations de contact de l'entreprise
 *
 * Met à jour les métadonnées de la commande :
 * - _cw_contract_sent : Date d'envoi
 * - _cw_contract_email_status : 'sent' ou 'failed'
 *
 * @since 1.0.0
 * @since 2.1.0 Ajout du lien sécurisé et amélioration du template email.
 *
 * @param int $order_id ID de la commande WooCommerce.
 *
 * @return bool True si l'email a été envoyé avec succès, false sinon.
 *
 * @example
 * $result = cw_generate_contract_pdf($order_id);
 * if ($result['success']) {
 *     cw_send_contract_email($order_id);
 * }
 *
 * @see wp_mail()                       Fonction WordPress d'envoi d'email.
 * @see cw_generate_contract_secure_link() Génère le lien d'accès sécurisé.
 */
function cw_send_contract_email($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return false;

    $contract_file = get_post_meta($order_id, '_cw_contract_file', true);
    $contract_number = get_post_meta($order_id, '_cw_contract_number', true);

    if (!$contract_file || !$contract_number) return false;

    $filepath = CW_CONTRACT_DIR . $contract_file;
    if (!file_exists($filepath)) return false;

    $client_email = $order->get_billing_email();
    $client_name = $order->get_billing_first_name();
    $company = cw_get_company_info();

    // Données de réservation
    $reservation_data = [];
    foreach ($order->get_items() as $item) {
        $offre_id = intval($item->get_meta('_cw_offre_id'));
        if (!$offre_id) continue;

        $reservation_data = [
            'offre_name' => $item->get_meta('_cw_offre_name') ?: get_the_title($offre_id),
            'start'      => $item->get_meta('_cw_start'),
            'end'        => $item->get_meta('_cw_end'),
            'price'      => (float) ($item->get_meta('_cw_price') ?: $item->get_total()),
        ];
        break;
    }

    if (empty($reservation_data)) return false;

    // Lien sécurisé
    $secure_link = cw_generate_contract_secure_link($order_id);

    // Email
    $subject = sprintf('Votre contrat - %s', $reservation_data['offre_name']);

    $message = sprintf(
'Bonjour %s,

Votre réservation est confirmée !

Vous trouverez ci-joint votre contrat de prestation de services.

VOTRE RÉSERVATION
-----------------
Espace : %s
Du : %s
Au : %s
Montant : %s EUR (payé)

Accéder au contrat en ligne : %s

Ce contrat récapitule les conditions de votre réservation. En procédant au paiement, vous avez accepté ces conditions ainsi que nos CGV et règlement intérieur.

Des questions ? Répondez à cet email.

À bientôt !

--
%s
%s
%s',
        $client_name,
        $reservation_data['offre_name'],
        date_i18n('d/m/Y', strtotime($reservation_data['start'])),
        date_i18n('d/m/Y', strtotime($reservation_data['end'])),
        number_format($reservation_data['price'], 2, ',', ' '),
        $secure_link,
        $company['name'],
        $company['address'],
        $company['phone']
    );

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $company['name'], $company['email']),
        sprintf('Reply-To: %s', $company['email']),
    ];

    $attachments = [$filepath];

    $sent = wp_mail($client_email, $subject, $message, $headers, $attachments);

    if ($sent) {
        update_post_meta($order_id, '_cw_contract_sent', current_time('mysql'));
        update_post_meta($order_id, '_cw_contract_email_status', 'sent');
        $order->add_order_note(sprintf('Contrat envoyé par email à %s', $client_email));
    } else {
        update_post_meta($order_id, '_cw_contract_email_status', 'failed');
        $order->add_order_note(sprintf('Échec envoi contrat à %s', $client_email));
    }

    return $sent;
}

/* =============================================================================
   ACCÈS SÉCURISÉ AU CONTRAT (LIEN PUBLIC)
============================================================================= */

/**
 * Génère un lien sécurisé pour accéder au contrat sans authentification.
 *
 * Le token est généré à partir de :
 * - L'ID de la commande
 * - La clé de commande WooCommerce (order_key)
 * - Le sel WordPress (wp_salt)
 *
 * Ce lien peut être partagé avec le client par email ou SMS.
 * Il reste valide indéfiniment tant que le token n'est pas régénéré.
 *
 * @since 1.0.0
 * @since 2.1.0 Utilisation de wp_hash() pour une meilleure sécurité.
 *
 * @param int $order_id ID de la commande WooCommerce.
 *
 * @return string URL complète avec token d'accès, ou chaîne vide si erreur.
 *
 * @example
 * $link = cw_generate_contract_secure_link(123);
 * // Retourne : "https://skylounge.fr/?cw_contract=123&token=abc123..."
 *
 * @see wp_hash()        Génère un hash sécurisé.
 * @see add_query_arg()  Construit l'URL avec paramètres.
 */
function cw_generate_contract_secure_link($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return '';

    $token = wp_hash($order_id . $order->get_order_key() . wp_salt());
    update_post_meta($order_id, '_cw_contract_access_token', $token);

    return add_query_arg([
        'cw_contract' => $order_id,
        'token' => $token
    ], home_url('/'));
}

add_action('template_redirect', 'cw_handle_contract_download');

/**
 * Gère le téléchargement public d'un contrat via lien sécurisé.
 *
 * Vérifications de sécurité :
 * - Validation du token avec hash_equals() (timing-safe)
 * - Protection contre la traversée de répertoire (path traversal)
 * - Logging des tentatives d'accès non autorisées
 * - Headers de sécurité (X-Content-Type-Options, X-Frame-Options)
 *
 * @since 1.0.0
 * @since 2.1.0 Renforcement sécurité (hash_equals, realpath check).
 *
 * @return void Termine l'exécution avec exit() après envoi du fichier.
 *
 * @hook template_redirect
 *
 * @see cw_generate_contract_secure_link() Génère le lien d'accès.
 * @see cw_wrap_html_contract()            Wrapper pour affichage HTML.
 */
function cw_handle_contract_download() {
    if (!isset($_GET['cw_contract']) || !isset($_GET['token'])) {
        return;
    }

    // Validation et sanitization des entrées
    $order_id = intval($_GET['cw_contract']);
    $token = sanitize_text_field($_GET['token']);

    // Validation basique
    if ($order_id <= 0 || empty($token) || strlen($token) < 32) {
        wp_die('Paramètres invalides.', 'Erreur', ['response' => 400]);
    }

    // Vérification du token avec comparaison timing-safe
    $stored_token = get_post_meta($order_id, '_cw_contract_access_token', true);
    if (!$stored_token || !hash_equals($stored_token, $token)) {
        // Log de tentative d'accès non autorisé
        error_log(sprintf('CW Contract: Tentative d\'accès non autorisé au contrat #%d depuis %s',
            $order_id,
            sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        ));
        wp_die('Lien invalide ou expiré.', 'Accès refusé', ['response' => 403]);
    }

    $contract_file = get_post_meta($order_id, '_cw_contract_file', true);
    if (!$contract_file) {
        wp_die('Contrat introuvable.', 'Erreur', ['response' => 404]);
    }

    // Sécurité: protection contre la traversée de répertoire
    $filepath = CW_CONTRACT_DIR . $contract_file;
    $realpath = realpath($filepath);
    $real_contract_dir = realpath(CW_CONTRACT_DIR);

    if ($realpath === false || $real_contract_dir === false || strpos($realpath, $real_contract_dir) !== 0) {
        error_log('CW Contract: Tentative de traversée de répertoire détectée');
        wp_die('Erreur de sécurité.', 'Accès refusé', ['response' => 403]);
    }

    if (!file_exists($filepath)) {
        wp_die('Fichier introuvable.', 'Erreur', ['response' => 404]);
    }

    $format = get_post_meta($order_id, '_cw_contract_format', true);

    // Headers de sécurité
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Si c'est un PDF, servir directement
    if ($format === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($contract_file) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($filepath);
        exit;
    }

    // Si c'est du HTML, afficher avec un wrapper élégant
    $html_content = file_get_contents($filepath);
    $contract_number = get_post_meta($order_id, '_cw_contract_number', true);

    echo cw_wrap_html_contract($html_content, $contract_number);
    exit;
}

/**
 * Encapsule un contrat HTML dans une interface style visionneuse PDF.
 *
 * Crée une page web élégante avec :
 * - Barre d'outils fixe (style Chrome PDF viewer)
 * - Boutons d'impression et téléchargement
 * - Rendu du contrat sur fond de page A4
 * - Styles responsives pour mobile
 * - Mode impression optimisé
 *
 * @since 2.0.0
 * @since 2.1.0 Amélioration du design et ajout boutons interactifs.
 *
 * @param string $html_content    Contenu HTML brut du contrat.
 * @param string $contract_number Numéro de contrat pour le titre.
 *
 * @return string HTML complet de la page avec wrapper.
 *
 * @example
 * $wrapped = cw_wrap_html_contract($html, 'CW-2025-00042');
 * echo $wrapped;
 */
function cw_wrap_html_contract($html_content, $contract_number) {
    // Extraire le contenu du body
    preg_match('/<body[^>]*>(.*?)<\/body>/is', $html_content, $body_match);
    $body_content = $body_match[1] ?? $html_content;

    // Extraire les styles
    preg_match('/<style[^>]*>(.*?)<\/style>/is', $html_content, $style_match);
    $original_styles = $style_match[1] ?? '';

    return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat ' . esc_html($contract_number) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            background: #525659;
            min-height: 100vh;
        }

        /* Barre d\'outils style PDF viewer */
        .pdf-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: #323639;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .pdf-toolbar-title {
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            font-weight: 500;
        }

        .pdf-toolbar-actions {
            display: flex;
            gap: 12px;
        }

        .pdf-toolbar-btn {
            background: #4a4d50;
            border: none;
            color: #fff;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .pdf-toolbar-btn:hover {
            background: #5a5d60;
        }

        .pdf-toolbar-btn.primary {
            background: #1a73e8;
        }

        .pdf-toolbar-btn.primary:hover {
            background: #1557b0;
        }

        /* Container du document */
        .pdf-container {
            padding: 76px 20px 40px 20px;
            display: flex;
            justify-content: center;
        }

        /* Page A4 */
        .pdf-page {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 18mm 25mm 18mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            margin-bottom: 20px;
        }

        /* Styles du contrat */
        ' . $original_styles . '

        /* Override pour le body du contrat */
        .pdf-page {
            font-family: "Times New Roman", Times, Georgia, serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .pdf-page {
                width: 100%;
                min-height: auto;
                padding: 15mm 12mm;
            }
        }

        @media print {
            .pdf-toolbar { display: none !important; }
            .pdf-container { padding: 0 !important; }
            .pdf-page {
                box-shadow: none !important;
                margin: 0 !important;
                width: 100% !important;
            }
            html, body { background: #fff !important; }
        }
    </style>
</head>
<body>
    <div class="pdf-toolbar">
        <div class="pdf-toolbar-title">
            Contrat ' . esc_html($contract_number) . '
        </div>
        <div class="pdf-toolbar-actions">
            <button class="pdf-toolbar-btn" onclick="window.print()">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                </svg>
                Imprimer
            </button>
            <button class="pdf-toolbar-btn primary" onclick="window.print()">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                </svg>
                Enregistrer PDF
            </button>
        </div>
    </div>

    <div class="pdf-container">
        <div class="pdf-page">
            ' . $body_content . '
        </div>
    </div>
</body>
</html>';
}

/* =============================================================================
   INTERFACE ADMIN - METABOX SUR LES COMMANDES
============================================================================= */

add_action('add_meta_boxes', 'cw_add_contract_metabox');

/**
 * Enregistre la metabox "Contrat Coworking" sur les pages de commande.
 *
 * Compatible avec HPOS (High-Performance Order Storage) de WooCommerce.
 * Détecte automatiquement si HPOS est activé pour utiliser le bon screen ID.
 *
 * @since 1.0.0
 * @since 2.0.0 Compatibilité HPOS ajoutée.
 *
 * @return void
 *
 * @hook add_meta_boxes
 *
 * @see cw_render_contract_metabox() Callback de rendu de la metabox.
 */
function cw_add_contract_metabox() {
    // Compatibilité HPOS
    $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')
        && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id('shop-order')
        : 'shop_order';

    add_meta_box(
        'cw_contract_metabox',
        'Contrat Coworking',
        'cw_render_contract_metabox',
        $screen,
        'side',
        'default'
    );
}

/**
 * Affiche le contenu de la metabox "Contrat Coworking".
 *
 * Affiche selon le contexte :
 * - Si contrat existant : numéro, format, dates, boutons d'actions
 * - Si contrat requis : alerte + bouton de génération
 * - Si contrat non requis : message informatif
 *
 * Actions disponibles :
 * - Voir le contrat (nouvel onglet)
 * - Télécharger
 * - Renvoyer par email
 * - Régénérer (avec même numéro)
 *
 * @since 1.0.0
 * @since 2.1.0 Ajout du bouton "Régénérer".
 *
 * @param WP_Post|WC_Order $post_or_order Objet commande (HPOS) ou post (legacy).
 *
 * @return void
 *
 * @see cw_should_generate_contract() Vérifie si un contrat est requis.
 */
function cw_render_contract_metabox($post_or_order) {
    $order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);
    if (!$order) return;

    $order_id = $order->get_id();

    // Vérifier si c'est une commande coworking
    $is_coworking = false;
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('_cw_offre_id')) {
            $is_coworking = true;
            break;
        }
    }

    if (!$is_coworking) {
        echo '<p style="color:#6b7280; font-size:13px;">Cette commande n\'est pas une réservation coworking.</p>';
        return;
    }

    $contract_number = get_post_meta($order_id, '_cw_contract_number', true);
    $contract_file = get_post_meta($order_id, '_cw_contract_file', true);
    $contract_sent = get_post_meta($order_id, '_cw_contract_sent', true);
    $contract_format = get_post_meta($order_id, '_cw_contract_format', true);
    $contract_generated = get_post_meta($order_id, '_cw_contract_generated', true);

    echo '<div style="padding:8px 0;">';

    if ($contract_number) {
        // Contrat existant
        echo '<div style="background:#dcfce7; border:1px solid #86efac; border-radius:8px; padding:14px; margin-bottom:14px;">';
        echo '<div style="font-weight:600; color:#166534; font-size:14px; margin-bottom:8px;">✓ Contrat généré</div>';
        echo '<div style="font-size:12px; color:#166534;">';
        echo '<strong>N° :</strong> ' . esc_html($contract_number) . '<br>';
        echo '<strong>Format :</strong> ' . strtoupper($contract_format ?: 'PDF') . '<br>';
        if ($contract_generated) {
            echo '<strong>Créé le :</strong> ' . date_i18n('d/m/Y à H:i', strtotime($contract_generated)) . '<br>';
        }
        if ($contract_sent) {
            echo '<strong>Envoyé le :</strong> ' . date_i18n('d/m/Y à H:i', strtotime($contract_sent));
        } else {
            echo '<strong>Envoi :</strong> <span style="color:#b91c1c;">Non envoyé</span>';
        }
        echo '</div>';
        echo '</div>';

        // Boutons
        echo '<div style="display:flex; flex-direction:column; gap:8px;">';

        $view_url = admin_url('admin-ajax.php?action=cw_view_contract&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce('cw_view_contract'));
        echo '<a href="' . esc_url($view_url) . '" target="_blank" class="button" style="text-align:center;">👁 Voir le contrat</a>';

        $download_url = admin_url('admin-ajax.php?action=cw_download_contract&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce('cw_download_contract'));
        echo '<a href="' . esc_url($download_url) . '" class="button" style="text-align:center;">⬇ Télécharger</a>';

        $resend_url = admin_url('admin-ajax.php?action=cw_resend_contract&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce('cw_resend_contract'));
        echo '<a href="' . esc_url($resend_url) . '" class="button" style="text-align:center;" onclick="return confirm(\'Renvoyer le contrat par email au client ?\');">✉ Renvoyer par email</a>';

        echo '<hr style="margin:8px 0; border:none; border-top:1px solid #e5e7eb;">';

        $regenerate_url = admin_url('admin-ajax.php?action=cw_regenerate_contract&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce('cw_regenerate_contract'));
        echo '<a href="' . esc_url($regenerate_url) . '" class="button" style="text-align:center; color:#b91c1c; border-color:#fca5a5;" onclick="return confirm(\'⚠️ ATTENTION\\n\\nCette action va :\\n- Supprimer l\'ancien contrat\\n- Générer un nouveau contrat avec le même numéro\\n- Renvoyer automatiquement par email\\n\\nContinuer ?\');">🔄 Régénérer le contrat</a>';

        echo '</div>';

    } else {
        // Pas de contrat
        $should_generate = cw_should_generate_contract($order_id);

        if ($should_generate) {
            echo '<div style="background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:14px; margin-bottom:14px;">';
            echo '<div style="font-weight:600; color:#92400e; font-size:13px; margin-bottom:4px;">⚠ Contrat requis</div>';
            echo '<div style="font-size:12px; color:#92400e;">Cette réservation nécessite un contrat.</div>';
            echo '</div>';
        } else {
            echo '<div style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:8px; padding:14px; margin-bottom:14px;">';
            echo '<div style="font-size:12px; color:#6b7280;">Contrat non requis (durée courte ou montant faible).</div>';
            echo '</div>';
        }

        $generate_url = admin_url('admin-ajax.php?action=cw_generate_contract&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce('cw_generate_contract'));
        echo '<a href="' . esc_url($generate_url) . '" class="button button-primary" style="width:100%; text-align:center;">📄 Générer le contrat</a>';
    }

    echo '</div>';
}

/* =============================================================================
   AJAX HANDLERS (ADMIN)
============================================================================= */

// Générer un contrat
add_action('wp_ajax_cw_generate_contract', 'cw_ajax_generate_contract');

/**
 * Handler AJAX pour générer un nouveau contrat depuis l'admin.
 *
 * Sécurité :
 * - Vérification du nonce WordPress
 * - Vérification de la capacité 'manage_woocommerce'
 * - Validation de l'ID de commande
 *
 * Après génération, redirige vers la page de commande avec un message
 * de succès ou d'erreur via query string.
 *
 * @since 1.0.0
 *
 * @return void Redirige et termine l'exécution.
 *
 * @hook wp_ajax_cw_generate_contract
 *
 * @see cw_generate_contract_pdf() Génère le fichier PDF/HTML.
 * @see cw_send_contract_email()   Envoie l'email au client.
 */
function cw_ajax_generate_contract() {
    // Vérification du nonce
    if (!check_ajax_referer('cw_generate_contract', '_wpnonce', false)) {
        wp_die('Requête invalide - nonce expiré', 'Erreur de sécurité', ['response' => 403]);
    }

    // Vérification des permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Accès refusé - permissions insuffisantes', 'Erreur', ['response' => 403]);
    }

    // Validation de l'ID
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    if ($order_id <= 0) {
        wp_die('ID de commande invalide', 'Erreur', ['response' => 400]);
    }

    // Vérifier que la commande existe
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die('Commande introuvable', 'Erreur', ['response' => 404]);
    }

    $result = cw_generate_contract_pdf($order_id);

    if ($result['success']) {
        cw_send_contract_email($order_id);
        wp_redirect(cw_get_order_edit_url($order_id) . '&cw_contract_generated=1');
    } else {
        wp_redirect(cw_get_order_edit_url($order_id) . '&cw_contract_error=1');
    }
    exit;
}

// Voir un contrat
add_action('wp_ajax_cw_view_contract', 'cw_ajax_view_contract');

/**
 * Handler AJAX pour afficher un contrat dans le navigateur (admin).
 *
 * Sert le fichier PDF inline ou affiche le HTML dans un wrapper élégant.
 * Inclut des vérifications de sécurité contre la traversée de répertoire.
 *
 * @since 1.0.0
 * @since 2.1.0 Ajout protection path traversal.
 *
 * @return void Termine avec exit() après envoi du fichier.
 *
 * @hook wp_ajax_cw_view_contract
 */
function cw_ajax_view_contract() {
    // Vérification du nonce
    if (!check_ajax_referer('cw_view_contract', '_wpnonce', false)) {
        wp_die('Requête invalide', 'Erreur de sécurité', ['response' => 403]);
    }

    // Vérification des permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Accès refusé', 'Erreur', ['response' => 403]);
    }

    // Validation de l'ID
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    if ($order_id <= 0) {
        wp_die('ID de commande invalide', 'Erreur', ['response' => 400]);
    }

    $contract_file = get_post_meta($order_id, '_cw_contract_file', true);
    if (!$contract_file) {
        wp_die('Contrat introuvable', 'Erreur', ['response' => 404]);
    }

    // Sécurité: s'assurer que le chemin est bien dans le dossier contracts
    $filepath = CW_CONTRACT_DIR . $contract_file;
    $realpath = realpath($filepath);
    $real_contract_dir = realpath(CW_CONTRACT_DIR);

    // Protection contre les attaques de traversée de répertoire
    if ($realpath === false || $real_contract_dir === false || strpos($realpath, $real_contract_dir) !== 0) {
        wp_die('Chemin de fichier invalide', 'Erreur de sécurité', ['response' => 403]);
    }

    if (!file_exists($filepath)) {
        wp_die('Fichier introuvable', 'Erreur', ['response' => 404]);
    }

    $format = get_post_meta($order_id, '_cw_contract_format', true);

    // Headers de sécurité
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');

    // Si c'est un PDF, servir directement
    if ($format === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($contract_file) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    // Si c'est du HTML, afficher avec wrapper élégant
    $html_content = file_get_contents($filepath);
    $contract_number = get_post_meta($order_id, '_cw_contract_number', true);
    echo cw_wrap_html_contract($html_content, $contract_number);
    exit;
}

// Télécharger un contrat
add_action('wp_ajax_cw_download_contract', 'cw_ajax_download_contract');

/**
 * Handler AJAX pour télécharger un contrat (admin).
 *
 * Force le téléchargement avec Content-Disposition: attachment.
 * Génère un nom de fichier sécurisé (caractères alphanumériques uniquement).
 *
 * Headers de sécurité :
 * - X-Content-Type-Options: nosniff
 * - Cache-Control: private, no-cache
 *
 * @since 1.0.0
 *
 * @return void Termine avec exit() après envoi du fichier.
 *
 * @hook wp_ajax_cw_download_contract
 */
function cw_ajax_download_contract() {
    // Vérification du nonce
    if (!check_ajax_referer('cw_download_contract', '_wpnonce', false)) {
        wp_die('Requête invalide', 'Erreur de sécurité', ['response' => 403]);
    }

    // Vérification des permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Accès refusé', 'Erreur', ['response' => 403]);
    }

    // Validation de l'ID
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    if ($order_id <= 0) {
        wp_die('ID de commande invalide', 'Erreur', ['response' => 400]);
    }

    $contract_file = get_post_meta($order_id, '_cw_contract_file', true);
    if (!$contract_file) {
        wp_die('Contrat introuvable', 'Erreur', ['response' => 404]);
    }

    // Sécurité: protection traversée de répertoire
    $filepath = CW_CONTRACT_DIR . $contract_file;
    $realpath = realpath($filepath);
    $real_contract_dir = realpath(CW_CONTRACT_DIR);

    if ($realpath === false || $real_contract_dir === false || strpos($realpath, $real_contract_dir) !== 0) {
        wp_die('Chemin de fichier invalide', 'Erreur de sécurité', ['response' => 403]);
    }

    if (!file_exists($filepath)) {
        wp_die('Fichier introuvable', 'Erreur', ['response' => 404]);
    }

    $format = get_post_meta($order_id, '_cw_contract_format', true);
    $content_type = ($format === 'pdf') ? 'application/pdf' : 'text/html';
    $contract_number = get_post_meta($order_id, '_cw_contract_number', true);

    // Nom de fichier sécurisé
    $safe_filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $contract_number) . '.' . ($format === 'pdf' ? 'pdf' : 'html');

    // Headers de sécurité
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    readfile($filepath);
    exit;
}

// Renvoyer un contrat
add_action('wp_ajax_cw_resend_contract', 'cw_ajax_resend_contract');

/**
 * Handler AJAX pour renvoyer un contrat par email (admin).
 *
 * Réutilise le contrat existant sans le régénérer.
 * Redirige vers la page de commande avec message de succès/erreur.
 *
 * @since 1.0.0
 *
 * @return void Redirige et termine l'exécution.
 *
 * @hook wp_ajax_cw_resend_contract
 *
 * @see cw_send_contract_email() Envoie l'email avec pièce jointe.
 */
function cw_ajax_resend_contract() {
    check_ajax_referer('cw_resend_contract');

    if (!current_user_can('manage_woocommerce')) {
        wp_die('Accès refusé', 'Erreur', ['response' => 403]);
    }

    $order_id = intval($_GET['order_id']);
    if ($order_id <= 0) {
        wp_die('ID de commande invalide', 'Erreur', ['response' => 400]);
    }

    $sent = cw_send_contract_email($order_id);

    // Redirection compatible HPOS
    $redirect_base = cw_get_order_edit_url($order_id);
    $param = $sent ? 'cw_contract_sent=1' : 'cw_contract_email_error=1';
    wp_redirect($redirect_base . '&' . $param);
    exit;
}

// Régénérer un contrat (supprimer l'ancien et en créer un nouveau)
add_action('wp_ajax_cw_regenerate_contract', 'cw_ajax_regenerate_contract');

/**
 * Handler AJAX pour régénérer un contrat avec le même numéro (admin).
 *
 * Workflow :
 * 1. Supprime l'ancien fichier physique
 * 2. Efface les métadonnées (sauf numéro et token)
 * 3. Génère un nouveau PDF/HTML avec le même numéro
 * 4. Envoie automatiquement par email
 * 5. Ajoute une note à la commande
 *
 * Utile pour corriger un contrat après modification des informations
 * entreprise ou correction d'une erreur.
 *
 * @since 2.1.0
 *
 * @return void Redirige et termine l'exécution.
 *
 * @hook wp_ajax_cw_regenerate_contract
 *
 * @see cw_generate_contract_pdf() Génère le nouveau fichier.
 */
function cw_ajax_regenerate_contract() {
    check_ajax_referer('cw_regenerate_contract');

    if (!current_user_can('manage_woocommerce')) {
        wp_die('Accès refusé', 'Erreur', ['response' => 403]);
    }

    $order_id = intval($_GET['order_id']);
    if ($order_id <= 0) {
        wp_die('ID de commande invalide', 'Erreur', ['response' => 400]);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die('Commande introuvable', 'Erreur', ['response' => 404]);
    }

    // Récupérer l'ancien numéro de contrat pour le conserver
    $old_contract_number = get_post_meta($order_id, '_cw_contract_number', true);
    $old_contract_file = get_post_meta($order_id, '_cw_contract_file', true);

    // Supprimer l'ancien fichier physique
    if ($old_contract_file) {
        $old_filepath = CW_CONTRACT_DIR . $old_contract_file;
        if (file_exists($old_filepath)) {
            @unlink($old_filepath);
        }
    }

    // Supprimer les metas du contrat
    delete_post_meta($order_id, '_cw_contract_file');
    delete_post_meta($order_id, '_cw_contract_hash');
    delete_post_meta($order_id, '_cw_contract_generated');
    delete_post_meta($order_id, '_cw_contract_format');
    delete_post_meta($order_id, '_cw_contract_sent');
    delete_post_meta($order_id, '_cw_contract_email_status');
    // On garde _cw_contract_number et _cw_contract_access_token pour conserver le même numéro

    // Régénérer le contrat avec le même numéro
    $result = cw_generate_contract_pdf($order_id, $old_contract_number);

    if ($result['success']) {
        // Envoyer le nouveau contrat par email
        cw_send_contract_email($order_id);

        $order->add_order_note(
            sprintf('🔄 Contrat régénéré : %s (%s) - Envoyé par email',
                $result['number'],
                strtoupper($result['format'])
            )
        );

        wp_redirect(cw_get_order_edit_url($order_id) . '&cw_contract_regenerated=1');
    } else {
        wp_redirect(cw_get_order_edit_url($order_id) . '&cw_contract_error=1');
    }
    exit;
}

/**
 * Obtient l'URL d'édition d'une commande WooCommerce.
 *
 * Compatible avec HPOS (High-Performance Order Storage).
 * Détecte automatiquement si HPOS est activé pour générer la bonne URL.
 *
 * @since 2.0.0
 *
 * @param int $order_id ID de la commande WooCommerce.
 *
 * @return string URL d'édition de la commande dans l'admin.
 *
 * @example
 * $url = cw_get_order_edit_url(123);
 * // HPOS activé : "admin.php?page=wc-orders&action=edit&id=123"
 * // Legacy : "post.php?post=123&action=edit"
 */
function cw_get_order_edit_url($order_id) {
    if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') &&
        wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
        return admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
    }
    return admin_url('post.php?post=' . $order_id . '&action=edit');
}

/* =============================================================================
   ADMIN NOTICES
============================================================================= */

add_action('admin_notices', 'cw_contract_admin_notices');

/**
 * Affiche les notifications admin après les actions sur les contrats.
 *
 * Messages affichés selon les query strings :
 * - cw_contract_generated : Succès génération
 * - cw_contract_regenerated : Succès régénération
 * - cw_contract_sent : Succès envoi email
 * - cw_contract_error : Erreur génération
 * - cw_contract_email_error : Erreur envoi email
 *
 * @since 1.0.0
 *
 * @return void
 *
 * @hook admin_notices
 */
function cw_contract_admin_notices() {
    if (isset($_GET['cw_contract_generated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>✅ Contrat généré et envoyé avec succès !</p></div>';
    }
    if (isset($_GET['cw_contract_regenerated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>🔄 Contrat régénéré et renvoyé avec succès !</p></div>';
    }
    if (isset($_GET['cw_contract_sent'])) {
        echo '<div class="notice notice-success is-dismissible"><p>✉️ Contrat renvoyé par email avec succès !</p></div>';
    }
    if (isset($_GET['cw_contract_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Erreur lors de la génération du contrat. Vérifiez les logs WordPress.</p></div>';
    }
    if (isset($_GET['cw_contract_email_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Erreur lors de l\'envoi de l\'email. Vérifiez votre configuration SMTP.</p></div>';
    }
}

/* =============================================================================
   PAGE DE CONFIGURATION (WOOCOMMERCE > CONTRATS COWORKING)
============================================================================= */

add_action('admin_menu', 'cw_add_contract_settings_page');

/**
 * Ajoute la page de configuration dans le menu WooCommerce.
 *
 * Crée un sous-menu "Contrats" sous WooCommerce permettant de :
 * - Configurer les informations entreprise
 * - Voir les statistiques de génération
 * - Consulter les règles de génération automatique
 *
 * @since 1.0.0
 *
 * @return void
 *
 * @hook admin_menu
 *
 * @see cw_render_contract_settings_page() Callback de rendu.
 */
function cw_add_contract_settings_page() {
    add_submenu_page(
        'woocommerce',
        'Contrats Coworking',
        'Contrats',
        'manage_woocommerce',
        'cw-contracts-settings',
        'cw_render_contract_settings_page'
    );
}

/**
 * Affiche la page de configuration des contrats.
 *
 * Fonctionnalités :
 * - Formulaire de configuration des informations entreprise
 * - Affichage des seuils de génération automatique
 * - Statistiques (compteurs par année)
 * - Chemin du répertoire de stockage
 *
 * @since 1.0.0
 * @since 2.0.0 Ajout des statistiques et des règles de génération.
 *
 * @return void
 */
function cw_render_contract_settings_page() {
    // Sauvegarder les paramètres
    if (isset($_POST['cw_save_settings']) && check_admin_referer('cw_contract_settings')) {
        update_option('cw_company_name', sanitize_text_field($_POST['cw_company_name']));
        update_option('cw_company_legal', sanitize_text_field($_POST['cw_company_legal']));
        update_option('cw_company_address', sanitize_text_field($_POST['cw_company_address']));
        update_option('cw_company_siret', sanitize_text_field($_POST['cw_company_siret']));
        update_option('cw_company_tva', sanitize_text_field($_POST['cw_company_tva']));
        update_option('cw_company_email', sanitize_email($_POST['cw_company_email']));
        update_option('cw_company_phone', sanitize_text_field($_POST['cw_company_phone']));
        update_option('cw_company_website', esc_url_raw($_POST['cw_company_website']));
        update_option('cw_company_rcs', sanitize_text_field($_POST['cw_company_rcs']));

        echo '<div class="notice notice-success"><p>Paramètres enregistrés !</p></div>';
    }

    $company = cw_get_company_info();
    ?>
    <div class="wrap">
        <h1>Configuration des contrats</h1>
        <p>Ces informations apparaîtront sur tous les contrats générés pour vos réservations.</p>

        <form method="post" action="">
            <?php wp_nonce_field('cw_contract_settings'); ?>

            <table class="form-table">
                <tr>
                    <th><label for="cw_company_name">Nom de l'entreprise *</label></th>
                    <td><input type="text" id="cw_company_name" name="cw_company_name" value="<?php echo esc_attr($company['name']); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="cw_company_legal">Forme juridique *</label></th>
                    <td>
                        <input type="text" id="cw_company_legal" name="cw_company_legal" value="<?php echo esc_attr($company['legal']); ?>" class="regular-text" placeholder="SAS au capital de 10 000 EUR" required>
                        <p class="description">Ex: SAS au capital de 10 000 EUR, SARL, Auto-entrepreneur...</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="cw_company_address">Adresse complète *</label></th>
                    <td><input type="text" id="cw_company_address" name="cw_company_address" value="<?php echo esc_attr($company['address']); ?>" class="large-text" required></td>
                </tr>
                <tr>
                    <th><label for="cw_company_siret">Numéro SIRET *</label></th>
                    <td><input type="text" id="cw_company_siret" name="cw_company_siret" value="<?php echo esc_attr($company['siret']); ?>" class="regular-text" placeholder="123 456 789 00012" required></td>
                </tr>
                <tr>
                    <th><label for="cw_company_rcs">RCS</label></th>
                    <td><input type="text" id="cw_company_rcs" name="cw_company_rcs" value="<?php echo esc_attr($company['rcs']); ?>" class="regular-text" placeholder="RCS Paris"></td>
                </tr>
                <tr>
                    <th><label for="cw_company_tva">N° TVA intracommunautaire</label></th>
                    <td><input type="text" id="cw_company_tva" name="cw_company_tva" value="<?php echo esc_attr($company['tva']); ?>" class="regular-text" placeholder="FR12 123456789"></td>
                </tr>
                <tr>
                    <th><label for="cw_company_email">Email de contact *</label></th>
                    <td><input type="email" id="cw_company_email" name="cw_company_email" value="<?php echo esc_attr($company['email']); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="cw_company_phone">Téléphone</label></th>
                    <td><input type="text" id="cw_company_phone" name="cw_company_phone" value="<?php echo esc_attr($company['phone']); ?>" class="regular-text" placeholder="01 23 45 67 89"></td>
                </tr>
                <tr>
                    <th><label for="cw_company_website">Site web</label></th>
                    <td><input type="url" id="cw_company_website" name="cw_company_website" value="<?php echo esc_attr($company['website']); ?>" class="regular-text"></td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="cw_save_settings" class="button button-primary" value="Enregistrer">
            </p>
        </form>

        <hr>

        <h2>Règles de génération automatique</h2>
        <p>Un contrat est généré automatiquement lorsqu'une commande passe en statut "Terminée" ET remplit l'une de ces conditions :</p>
        <ul style="list-style:disc; margin-left:25px;">
            <li>Formule = <strong>Semaine</strong> ou <strong>Mois</strong></li>
            <li>Durée totale >= <strong><?php echo CW_CONTRACT_MIN_DAYS; ?> jours</strong></li>
            <li>Montant >= <strong><?php echo CW_CONTRACT_MIN_AMOUNT; ?> EUR</strong></li>
        </ul>
        <p><em>Pour modifier ces seuils, éditez les constantes au début du snippet.</em></p>

        <hr>

        <h2>Statistiques</h2>
        <?php
        $year = date('Y');
        $counter = (int) get_option('cw_contract_counter_' . $year, 0);
        $prev_year = (int) get_option('cw_contract_counter_' . ($year - 1), 0);
        ?>
        <table class="widefat" style="max-width:400px;">
            <tr>
                <td>Contrats générés en <?php echo $year; ?></td>
                <td><strong><?php echo $counter; ?></strong></td>
            </tr>
            <tr>
                <td>Contrats générés en <?php echo $year - 1; ?></td>
                <td><strong><?php echo $prev_year; ?></strong></td>
            </tr>
        </table>

        <hr>

        <h2>Emplacement des fichiers</h2>
        <p><code><?php echo CW_CONTRACT_DIR; ?></code></p>

    </div>
    <?php
}

/* =============================================================================
   PAGE DE VÉRIFICATION PUBLIQUE
============================================================================= */

/**
 * Crée automatiquement la page de vérification des contrats.
 *
 * Cette page permet à quiconque scanne le QR Code d'un contrat de
 * vérifier son authenticité sans avoir besoin de se connecter.
 *
 * Crée une page WordPress avec le slug 'verifier-contrat' si elle
 * n'existe pas déjà. La page est marquée avec une meta '_cw_verification_page'.
 *
 * @since 2.1.0
 *
 * @return void
 *
 * @hook init
 *
 * @see cw_handle_public_verification() Gère l'affichage de la page.
 */
function cw_create_verification_page() {
    // Créer la page si elle n'existe pas
    $page_title = 'Vérifier un contrat';
    $page_slug = 'verifier-contrat';
    
    $existing_page = get_page_by_path($page_slug);
    
    if (!$existing_page) {
        $page_data = array(
            'post_title'    => $page_title,
            'post_name'     => $page_slug,
            'post_content'  => '<!-- Contenu généré par le système de contrats -->',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_author'   => 1,
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id) {
            update_post_meta($page_id, '_cw_verification_page', true);
        }
    }
}

add_action('init', 'cw_create_verification_page');

/**
 * Gère l'affichage de la page de vérification publique des contrats.
 *
 * Recherche la commande correspondant au hash de vérification passé
 * dans l'URL et affiche une page de confirmation avec :
 * - Numéro de contrat
 * - Nom du client
 * - Date de génération
 * - Nom de l'entreprise
 *
 * Si le hash n'est pas trouvé, affiche un message d'erreur.
 *
 * @since 2.1.0
 *
 * @global WP_Query $wp_query Objet de requête WordPress.
 *
 * @return void Termine avec exit() après affichage.
 *
 * @hook template_redirect (priorité 5)
 */
function cw_handle_public_verification() {
    global $wp_query;
    
    if (isset($wp_query->query_vars['verification_id'])) {
        $verification_id = sanitize_text_field($wp_query->query_vars['verification_id']);
        $order_id = false;
        $contract_number = '';
        
        // Chercher la commande avec ce hash
        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => '_cw_verification_id',
            'meta_value' => $verification_id,
        ]);
        
        if (!empty($orders)) {
            $order = reset($orders);
            $order_id = $order->get_id();
            $contract_number = get_post_meta($order_id, '_cw_contract_number', true);
            $client_name = $order->get_formatted_billing_full_name();
            $company = cw_get_company_info();
            $contract_date = get_post_meta($order_id, '_cw_contract_generated', true);
        }
        
        // Afficher la page de vérification
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Vérification du contrat</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    padding: 20px;
                }
                
                .verification-card {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    max-width: 500px;
                    width: 100%;
                    overflow: hidden;
                }
                
                .card-header {
                    background: linear-gradient(135deg, #1e73be 0%, #155a96 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                
                .card-header h1 {
                    font-size: 24px;
                    font-weight: 600;
                    margin-bottom: 10px;
                }
                
                .card-header p {
                    opacity: 0.9;
                    font-size: 14px;
                }
                
                .card-body {
                    padding: 40px 30px;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 30px;
                }
                
                .status-valid {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .status-invalid {
                    background: #fee2e2;
                    color: #991b1b;
                }
                
                .contract-info {
                    background: #f8fafc;
                    border-radius: 12px;
                    padding: 24px;
                    margin-bottom: 30px;
                }
                
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 12px 0;
                    border-bottom: 1px solid #e2e8f0;
                }
                
                .info-row:last-child {
                    border-bottom: none;
                }
                
                .info-label {
                    color: #64748b;
                    font-size: 14px;
                }
                
                .info-value {
                    color: #1e293b;
                    font-weight: 600;
                    font-size: 14px;
                    text-align: right;
                }
                
                .company-footer {
                    text-align: center;
                    padding-top: 20px;
                    border-top: 1px solid #e2e8f0;
                }
                
                .company-name {
                    color: #1e73be;
                    font-weight: 600;
                    font-size: 16px;
                    margin-bottom: 5px;
                }
                
                .verification-date {
                    color: #94a3b8;
                    font-size: 12px;
                    margin-top: 20px;
                }
                
                .verification-icon {
                    font-size: 64px;
                    margin-bottom: 20px;
                }
                
                .btn-download {
                    display: inline-block;
                    background: #1e73be;
                    color: white;
                    padding: 12px 24px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 14px;
                    transition: background 0.2s;
                    margin-top: 20px;
                }
                
                .btn-download:hover {
                    background: #155a96;
                }
            </style>
        </head>
        <body>
            <div class="verification-card">
                <div class="card-header">
                    <div class="verification-icon">
                        <?php if ($order_id): ?>
                            ✅
                        <?php else: ?>
                            ❌
                        <?php endif; ?>
                    </div>
                    <h1>
                        <?php if ($order_id): ?>
                            Contrat Authentifié
                        <?php else: ?>
                            Contrat Non Trouvé
                        <?php endif; ?>
                    </h1>
                    <p>
                        <?php if ($order_id): ?>
                            Ce contrat a été émis par
                        <?php else: ?>
                            Le lien de vérification est invalide
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="card-body">
                    <?php if ($order_id): ?>
                        <div class="status-badge status-valid">✓ Valide</div>
                        
                        <div class="contract-info">
                            <div class="info-row">
                                <span class="info-label">N° Contrat</span>
                                <span class="info-value"><?php echo esc_html($contract_number); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Client</span>
                                <span class="info-value"><?php echo esc_html($client_name); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Date d'émission</span>
                                <span class="info-value"><?php echo $contract_date ? date_i18n('d/m/Y', strtotime($contract_date)) : 'N/A'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Statut</span>
                                <span class="info-value" style="color: #10b981;">✓ Actif</span>
                            </div>
                        </div>
                        
                        <div class="company-footer">
                            <div class="company-name"><?php echo esc_html($company['name']); ?></div>
                            <p style="color: #64748b; font-size: 14px; margin-top: 5px;"><?php echo esc_html($company['address']); ?></p>
                            
                            <?php 
                            $secure_link = cw_generate_contract_secure_link($order_id);
                            if ($secure_link): ?>
                                <a href="<?php echo esc_url($secure_link); ?>" class="btn-download" target="_blank">
                                    📄 Télécharger le contrat
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="verification-date">
                            Vérifié le <?php echo date_i18n('d/m/Y à H:i'); ?>
                        </div>
                    <?php else: ?>
                        <div class="status-badge status-invalid">✗ Invalide</div>
                        
                        <div class="contract-info">
                            <p style="color: #64748b; text-align: center; padding: 20px;">
                                Ce contrat n'a pas pu être authentifié.<br>
                                Le lien peut être expiré ou le contrat peut avoir été supprimé.
                            </p>
                        </div>
                        
                        <div class="company-footer">
                            <p style="color: #64748b; font-size: 14px;">
                                Pour plus d'informations, contactez directement l'entreprise.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

add_action('template_redirect', 'cw_handle_public_verification', 5);

/**
 * Enregistre les règles de réécriture d'URL pour la vérification publique.
 *
 * Crée une URL propre pour la vérification des contrats :
 * - /verifier-contrat/{hash} → index.php?verification_id={hash}
 *
 * Le hash est généré via wp_hash() lors de la création du contrat
 * et stocké en meta '_cw_verification_id'.
 *
 * @since 2.1.0
 *
 * @return void
 *
 * @hook init
 *
 * @see add_rewrite_rule() Ajoute la règle de réécriture.
 * @see add_rewrite_tag()  Enregistre le tag personnalisé.
 */
function cw_add_verification_rewrite() {
    add_rewrite_rule(
        '^verifier-contrat/([a-zA-Z0-9]+)/?$',
        'index.php?verification_id=$matches[1]',
        'top'
    );
    
    add_rewrite_tag('%verification_id%', '([a-zA-Z0-9]+)');
}

add_action('init', 'cw_add_verification_rewrite');

/* =============================================================================
   FLUSH REWRITE RULES À L'ACTIVATION
============================================================================= */

/**
 * Rafraîchit les règles de réécriture lors de l'activation du plugin.
 *
 * Nécessaire pour que les URLs de vérification fonctionnent immédiatement
 * après l'activation, sans avoir à sauvegarder les permalinks manuellement.
 *
 * @since 2.1.0
 */
register_activation_hook(__FILE__, function() {
    cw_add_verification_rewrite();
    flush_rewrite_rules();
});

/**
 * Nettoie les règles de réécriture lors de la désactivation du plugin.
 *
 * Retire les règles personnalisées pour éviter les erreurs 404
 * après désactivation du système de contrats.
 *
 * @since 2.1.0
 */
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

/* =============================================================================
   FIN DU SYSTÈME DE CONTRATS COWORKING v2.1
============================================================================= *//* =============================================================================
   FIN DU SYSTÈME DE CONTRATS COWORKING v2.1
============================================================================= */