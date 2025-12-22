# Fiches de Révision Techniques
## SkyLounge Coworking - Système de Réservation

---

# FICHE 1 : VUE D'ENSEMBLE

## Le Système en 1 phrase
> "Un système de réservation d'espaces de coworking sur WordPress utilisant WooCommerce pour le paiement et des Code Snippets modulaires pour la logique métier."

## Stack Technique

| Technologie | Version | Rôle |
|-------------|---------|------|
| WordPress | 6.x | CMS & Framework |
| WooCommerce | 8.x | Paiement & Orders |
| ACF Pro | 6.x | Custom Fields |
| Code Snippets | 3.x | Modules PHP |
| PHP | 8.x | Backend |
| JavaScript | ES6+ | Frontend |

## Chiffres Clés à Retenir

- **16 fichiers** Code Snippets
- **~400 KB** de code total
- **3 endpoints** REST API
- **2 produits** WooCommerce (Bureau: 1913, Salle: 1917)
- **1 CPT** personnalisé (cw_reservation)
- **3 ans** rétention données RGPD

---

# FICHE 2 : FLUX DE RÉSERVATION

## Étapes du Parcours Client

```
1. Client visite page Offre
         │
         ▼
2. Calendrier charge les disponibilités
   GET /coworking/v1/availability/{id}
         │
         ▼
3. Client sélectionne dates + formule
         │
         ▼
4. Client clique "Réserver"
   POST /coworking/v1/cart-add
         │
         ├── Vérification disponibilité
         ├── Création LOCK (transient)
         └── Ajout panier WooCommerce
         │
         ▼
5. Redirection vers /checkout/
   (Checkout INVITÉ - pas de compte créé)
         │
         ▼
6. Paiement validé
   Hook: woocommerce_order_status_completed
         │
         ├── Création CPT cw_reservation
         ├── Mise à jour reservations_json
         ├── Suppression du LOCK
         └── Génération contrat PDF (si conditions)
         │
         ▼
7. Email de confirmation envoyé
   (avec contrat PDF en pièce jointe)
```

## Points Critiques

| Étape | Risque | Solution |
|-------|--------|----------|
| Étape 4 | Double réservation | Lock temporaire |
| Étape 5 | Abandon panier | Lock expire (5-20 min) |
| Étape 6 | Échec paiement | CPT reste en draft |

---

# FICHE 3 : SYSTÈME DE LOCKS

## Pourquoi des Locks ?

> Empêcher deux clients de réserver le même créneau pendant que l'un est au checkout.

## Comment ça Marche

```php
// Création du lock
set_transient('cw_locks_' . $offre_id, $locks, $ttl);

// Contenu du lock
[
    'start'      => '2025-01-15',
    'end'        => '2025-01-20',
    'token'      => 'abc123xyz',     // Token unique
    'expires_at' => 1736935200,      // Timestamp expiration
    'quantity'   => 1
]
```

## TTL (Time To Live)

| Capacité | TTL | Raison |
|----------|-----|--------|
| = 1 (bureau unique) | 20 min | Espace unique, plus de temps |
| > 1 (salle partagée) | 5 min | Plusieurs places, libérer vite |

## Fonctions Clés

| Fonction | Fichier | Rôle |
|----------|---------|------|
| `coworking_add_lock()` | booking-engine | Créer un lock |
| `coworking_remove_lock_by_token()` | booking-engine | Supprimer par token |
| `coworking_clean_expired_locks()` | cron | Nettoyage quotidien |
| `coworking_clean_orphaned_locks()` | cron | Locks sans commande |

---

# FICHE 4 : STOCKAGE DES DONNÉES

## Double Stockage (JSON + CPT)

### reservations_json (ACF Field)
- **Où** : Sur chaque offre-coworking
- **Pourquoi** : Performance (lecture rapide pour calendrier)
- **Structure** :
```json
[
  {
    "start": "2025-01-15",
    "end": "2025-01-20",
    "formule": "semaine",
    "quantity": 1,
    "order": 12345
  }
]
```

### CPT cw_reservation
- **Où** : Table wp_posts
- **Pourquoi** : Source de vérité, requêtable, admin
- **Meta fields** :
  - `_cw_offre_id` : ID de l'offre
  - `_cw_offre_name` : Nom de l'offre
  - `_cw_start` : Date début
  - `_cw_end` : Date fin
  - `_cw_formule` : journee/semaine/mois
  - `_cw_quantity` : Nombre d'unités
  - `_cw_price` : Prix total
  - `_cw_order_id` : ID commande WC
  - `_cw_customer_name` : Nom client
  - `_cw_customer_email` : Email client

## Cohérence des Données

```
WooCommerce Orders
       │
       ▼ (source de vérité)
CPT cw_reservation
       │
       ▼ (synchronisé)
reservations_json (cache)
```

Le CRON vérifie la cohérence quotidiennement.

---

# FICHE 5 : API REST

## Endpoints

### 1. Disponibilités
```
GET /wp-json/coworking/v1/availability/{offre_id}
Query: ?month=2025-01

Response:
{
  "2025-01-15": { "available": 5, "status": "available" },
  "2025-01-16": { "available": 0, "status": "full" },
  "2025-01-17": { "available": 1, "status": "low" }
}
```

### 2. Ajout au Panier
```
POST /wp-json/coworking/v1/cart-add
Body:
{
  "offre_id": 123,
  "formule": "semaine",
  "start": "2025-01-15",
  "quantity": 2
}

Response (success):
{
  "success": true,
  "cart_url": "https://.../checkout/",
  "message": "Réservation ajoutée..."
}
```

### 3. Calcul Prix
```
POST /wp-json/coworking/v1/calculate-price
Body:
{
  "offre_id": 123,
  "formule": "journee",
  "quantity": 5
}

Response:
{
  "unit_price": 35,
  "quantity": 5,
  "price": 175,
  "total_days": 5
}
```

## Codes d'Erreur

| Code HTTP | Code Interne | Signification |
|-----------|--------------|---------------|
| 400 | MISSING_PARAMS | Paramètres manquants |
| 400 | DATE_TOO_SOON | Date < J+1 |
| 409 | DATE_UNAVAILABLE | Créneau déjà pris |
| 500 | PRICE_NOT_CONFIGURED | Tarif non défini |
| 500 | WC_INACTIVE | WooCommerce inactif |
| 500 | CART_ADD_FAILED | Erreur ajout panier |

---

# FICHE 6 : MODULES PAR PRIORITÉ

## Ordre de Chargement (Code Snippets)

| Priorité | Module | Fonction Principale |
|:--------:|--------|---------------------|
| **1** | coworking-config | Constantes, IDs produits, helpers |
| 2 | fonction-helper-today-date | `coworking_today_date()` |
| 3 | systeme-disponibilite | Calcul disponibilités |
| 4 | coworking-booking-engine-v2 | API REST, locks, panier |
| 5 | calendrier-coworking-v2 | Shortcode [coworking_calendar] |
| 6 | coworking-wc-order-complete | Hook après paiement |
| 7 | coworking-reservation-donnees | Données initiales JS |
| 8 | coworking-admin-columns | Colonnes tableau admin |
| 9 | coworking-admin-metabox | Métabox détails |
| 10 | page-admin-coworking | Dashboard admin |
| 11 | coworking-notification-system | Badges, alertes |
| 12 | coworking-cron | Maintenance auto |
| 13 | coworking-json-tools | Utilitaires JSON |
| 14 | coworking-generation-contrats | PDF contrats |
| 15 | woocommerce-tunnel-de-vente | Checkout simplifié |
| 16 | rgpd-consentement-checkout | Conformité RGPD |

## Dépendances Critiques

```
coworking-config (DOIT être chargé en premier)
       │
       ├──▶ booking-engine (utilise cw_get_product_ids())
       ├──▶ systeme-disponibilite
       └──▶ woocommerce-tunnel (utilise cw_is_coworking_product())
```

---

# FICHE 7 : CRON & MAINTENANCE

## Planification

- **Heure** : 03h00 (heure serveur)
- **Fréquence** : Quotidienne
- **Hook** : `coworking_daily_maintenance`

## Tâches Exécutées

| # | Fonction | Action |
|:-:|----------|--------|
| 1 | `clean_expired_locks()` | Supprime transients expirés |
| 2 | `clean_orphaned_locks()` | Supprime locks sans commande WC |
| 3 | `clean_old_drafts()` | Supprime brouillons > 24h |
| 4 | `repair_reservations_json()` | Nettoie JSON corrompu |
| 5 | `check_json_cpt_coherence()` | Log désynchronisations |
| 6 | `anonymize_old_reservations()` | RGPD : anonymise > 3 ans |

## Vérification Manuelle

```php
// Endpoint AJAX admin (admin-ajax.php)
action: cw_force_cleanup
nonce: cw_admin_nonce
```

---

# FICHE 8 : GÉNÉRATION CONTRATS PDF

## Conditions de Génération (AU MOINS UNE)

| Condition | Seuil |
|-----------|-------|
| Durée | >= 7 jours |
| Montant | >= 200 EUR |
| Formule | "semaine" ou "mois" |

## Numérotation

Format : `CW-YYYY-NNNNN`
Exemple : `CW-2025-00042` (42ème contrat de 2025)

## Stockage

```
wp-content/uploads/coworking-contracts/
├── .htaccess          (Deny from all)
├── index.php          (Silence is golden)
└── contrat-CW-2025-00042.pdf
```

## Dépendance PDF

- Plugin requis : "PDF Invoices & Packing Slips for WooCommerce"
- **Fallback** : Si plugin absent → génération HTML + notification admin

---

# FICHE 9 : SÉCURITÉ

## Validations Entrées

| Donnée | Validation |
|--------|------------|
| Texte | `sanitize_text_field()` |
| Email | `sanitize_email()` |
| Entier | `intval()` |
| Date | Regex `/^\d{4}-\d{2}-\d{2}$/` |

## Permissions

```php
// Endpoints admin seulement
'permission_callback' => function() {
    return current_user_can('manage_options');
}

// Vérification nonce
if (!wp_verify_nonce($nonce, 'wp_rest')) {
    return new WP_REST_Response(['error' => 'Nonce invalide'], 403);
}
```

## Anti Double Réservation

```
Client A          Client B
    │                 │
    ▼                 ▼
 cart-add          cart-add
    │                 │
    ▼                 ▼
 Check dispo      Check dispo
    │                 │
    ▼                 │
 Crée LOCK           │
    │                 ▼
    │             Check dispo
    │             (voit le lock de A)
    │                 │
    │                 ▼
    │             ❌ REFUSÉ
    ▼
 Checkout OK
```

---

# FICHE 10 : RGPD

## Données Collectées

| Donnée | Obligatoire | Conservation |
|--------|:-----------:|:------------:|
| Prénom | Oui | 3 ans |
| Nom | Oui | 3 ans |
| Email | Oui | 3 ans |
| Téléphone | Oui | 3 ans |
| IP | Non (anonymisée) | 3 ans |
| Consentement | Oui | 3 ans |

## Anonymisation IP

```php
// IPv4 : 192.168.1.123 → 192.168.1.0
$ip_parts = explode('.', $ip);
$ip_parts[3] = '0';
$ip_anonymized = implode('.', $ip_parts);

// IPv6 : xxxx:xxxx:...:xxxx → xxxx:xxxx:...:0000
$ip_anonymized = substr($ip, 0, strrpos($ip, ':')) . ':0000';
```

## Suppression Automatique (CRON)

```php
function coworking_anonymize_old_reservations() {
    $threshold = strtotime('-3 years');
    // Remplace nom/email par "Client anonymisé (RGPD)"
    update_post_meta($resa_id, '_cw_customer_name', 'Client anonymisé (RGPD)');
    update_post_meta($resa_id, '_cw_customer_email', 'anonyme@rgpd.local');
}
```

---

# FICHE 11 : DEBUGGING

## Logs WordPress

Activer dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Fichier : `wp-content/debug.log`

## Fonction de Log Custom

```php
cw_log('Message', 'info', ['context' => 'data']);
// → [CW-INFO] Message | Context: {"context":"data"}

// Niveaux : 'info', 'warning', 'error'
```

## Vérifications Rapides

| Action | Commande/Méthode |
|--------|------------------|
| Vérifier CRON | WP-Cron Status plugin |
| Voir transients | Options table, `_transient_cw_locks_*` |
| Tester API | `curl /wp-json/coworking/v1/availability/123?month=2025-01` |
| Forcer cleanup | Bouton admin (si implémenté) |

---

# FICHE 12 : QUESTIONS PIÈGES

## Q: "Pourquoi Code Snippets et pas un plugin custom ?"

> **R:** Maintenabilité. Chaque module est indépendant, peut être désactivé/modifié via l'interface admin sans accès FTP. Pas de build, pas de deployment pipeline. Idéal pour une équipe sans DevOps dédié.

## Q: "Et si deux clients réservent exactement en même temps ?"

> **R:** Les transients WordPress utilisent des transactions DB. En pratique le risque est très faible (quelques millisecondes de fenêtre). Si ça devient un problème, on peut ajouter un verrou SQL avec `GET_LOCK()`. Pour l'instant la double vérification au checkout suffit.

## Q: "Pourquoi stocker en JSON ET en CPT ?"

> **R:** Le JSON est un cache de lecture pour le calendrier (évite N requêtes CPT). Le CPT est la source de vérité pour l'admin et les requêtes complexes. WooCommerce Orders est l'ultime source pour la reconstruction si nécessaire.

## Q: "Pourquoi pas de création de compte client ?"

> **R:** Choix métier, pas technique. Le client veut un parcours le plus simple possible. Tout est géré par email : confirmation + contrat PDF. Si besoin futur, WooCommerce supporte les comptes.

## Q: "Comment tu gères les remboursements ?"

> **R:** Hook sur `woocommerce_order_status_refunded` qui supprime la réservation du JSON et passe le CPT en statut draft. Le créneau redevient disponible.

## Q: "Ton code est-il testé ?"

> **R:** Tests manuels exhaustifs (voir README). Pas de tests unitaires PHPUnit pour l'instant - c'est dans la roadmap. Le code est documenté et les fonctions sont isolées, ce qui faciliterait l'ajout de tests.

---

# AIDE-MÉMOIRE RAPIDE

## Fichiers Clés à Connaître par Cœur

1. **coworking-config** : IDs produits (1913, 1917), helpers
2. **coworking-booking-engine-v2** : API REST, locks, finalisation
3. **coworking-cron** : Maintenance quotidienne

## Fonctions Clés

| Fonction | Ce qu'elle fait |
|----------|-----------------|
| `cw_get_product_ids()` | Retourne [1913, 1917] |
| `cw_is_coworking_product($id)` | Vérifie si produit coworking |
| `coworking_check_availability_with_locks()` | Vérifie dispo + locks |
| `coworking_add_lock()` | Crée un lock temporaire |
| `coworking_today_date()` | Date du jour (timezone WP) |
| `cw_log()` | Log centralisé |

## Hooks WordPress Utilisés

| Hook | Type | Usage |
|------|------|-------|
| `rest_api_init` | Action | Enregistrer endpoints |
| `woocommerce_order_status_completed` | Action | Finaliser réservation |
| `woocommerce_before_calculate_totals` | Action | Forcer prix custom |
| `template_redirect` | Action | Bloquer pages boutique |
| `wp_dashboard_setup` | Action | Ajouter widget |
| `add_meta_boxes` | Action | Ajouter métabox |
