# Présentation Technique SkyLounge Réservation
## Durée : 45 minutes

---

# SLIDE 1 - TITRE (1 min)

```
╔══════════════════════════════════════════════════════════════╗
║                                                              ║
║              🏢 SKYLOUNGE COWORKING                          ║
║                                                              ║
║         Système de Réservation sur WordPress                 ║
║                                                              ║
║─────────────────────────────────────────────────────────────║
║                                                              ║
║  Présenté par : Jérémy VIDOCIN                              ║
║  Date : Décembre 2025                                        ║
║                                                              ║
║  Stack : WordPress + WooCommerce + ACF + Code Snippets       ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
```

**À dire :**
- "Bonjour, je vais vous présenter le système de réservation que j'ai développé pour SkyLounge, un espace de coworking."
- "C'est une solution 100% WordPress, sans plugin payant externe hormis Elementor Pro pour le design."

---

# SLIDE 2 - CONTEXTE & PROBLÉMATIQUE (2 min)

## Le Besoin Client

| Problème | Solution Apportée |
|----------|-------------------|
| Réservation manuelle par téléphone/email | Automatisation complète en ligne |
| Risque de double réservation | Système de locks temps réel |
| Pas de paiement intégré | WooCommerce checkout |
| Pas de contrat | Génération PDF automatique |
| Pas de conformité RGPD | Consentement + anonymisation |

## Contraintes Techniques

- **Budget limité** → Plugins gratuits uniquement
- **Pas de développeur dédié** → Code maintenable via Code Snippets
- **Multi-espaces** → Bureaux individuels + Salles de réunion
- **Formules flexibles** → Journée / Semaine / Mois

**À dire :**
- "Le client avait un processus 100% manuel : les gens appelaient ou envoyaient un email pour réserver."
- "Le risque principal était la double réservation et le manque de traçabilité."

---

# SLIDE 3 - DÉMO LIVE : PARCOURS CLIENT (10 min)

## Étapes à montrer :

### 1. Page Offre (2 min)
- Afficher une offre coworking avec le calendrier
- Montrer les tarifs affichés (journée/semaine/mois)
- Montrer les jours disponibles (vert) vs indisponibles (gris)

### 2. Sélection de dates (2 min)
- Cliquer sur une date de début
- Sélectionner une formule (ex: semaine)
- Montrer le calcul du prix en temps réel
- Cliquer sur "Réserver"

### 3. Checkout (3 min)
- Montrer les champs simplifiés (Prénom, Nom, Email, Téléphone)
- **IMPORTANT : Pas de création de compte** - Checkout invité
- Montrer la checkbox RGPD obligatoire
- Simuler un paiement (si environnement de test)

### 4. Confirmation (3 min)
- Montrer l'email de confirmation
- Montrer le contrat PDF généré
- Expliquer que tout est envoyé par email (pas d'espace client)

**À dire :**
- "Le client ne crée JAMAIS de compte. Tout est géré par email."
- "C'est un choix volontaire pour simplifier l'expérience utilisateur."

---

# SLIDE 4 - DÉMO LIVE : INTERFACE ADMIN (7 min)

## Étapes à montrer :

### 1. Dashboard WordPress (2 min)
- Widget "Arrivées du jour/demain"
- Badge de notification sur le menu
- Accès rapide au planning

### 2. Liste des Réservations (2 min)
- Colonnes personnalisées (Client, Dates, Formule, Espace, État)
- Badges colorés par formule
- Tri par dates

### 3. Détail d'une Réservation (2 min)
- Métabox "Détails de la réservation"
- Lien vers la commande WooCommerce
- Lien vers l'offre
- Informations client

### 4. Configuration (1 min)
- Montrer où sont les produits WooCommerce (ID 1913, 1917)
- Montrer le menu WooCommerce > Contrats Coworking
- Expliquer la configuration centralisée

**À dire :**
- "L'admin voit les nouvelles réservations en temps réel grâce au système de notifications."
- "Les colonnes sont personnalisées pour afficher uniquement les informations métier pertinentes."

---

# SLIDE 5 - ARCHITECTURE TECHNIQUE (5 min)

```
┌─────────────────────────────────────────────────────────────────┐
│                         ARCHITECTURE                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   ┌─────────────┐                     ┌─────────────┐          │
│   │  FRONTEND   │                     │   BACKEND   │          │
│   │             │                     │             │          │
│   │ Calendrier  │──── REST API ──────▶│  Booking    │          │
│   │ (Vanilla JS)│                     │  Engine     │          │
│   │             │◀───── JSON ─────────│             │          │
│   └─────────────┘                     └──────┬──────┘          │
│                                              │                  │
│                                              ▼                  │
│   ┌─────────────┐                     ┌─────────────┐          │
│   │ WooCommerce │◀─────────────────────│   LOCKS     │          │
│   │  Checkout   │                     │ (Transients)│          │
│   └──────┬──────┘                     └─────────────┘          │
│          │                                                      │
│          ▼                                                      │
│   ┌─────────────┐     ┌─────────────┐     ┌─────────────┐      │
│   │   Orders    │────▶│     CPT     │────▶│    JSON     │      │
│   │ WooCommerce │     │ Réservation │     │   (Cache)   │      │
│   └─────────────┘     └─────────────┘     └─────────────┘      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Points clés à expliquer :

1. **Séparation Frontend/Backend** via REST API
2. **Système de Locks** pour éviter la double réservation
3. **Double stockage** : CPT (source de vérité) + JSON (performance)
4. **WooCommerce** comme moteur de paiement

**À dire :**
- "Le calendrier communique avec le backend uniquement via REST API."
- "Les locks sont stockés en transients WordPress, avec un TTL adapté à la capacité de l'espace."

---

# SLIDE 6 - PLONGÉE DANS LE CODE (15 min)

## Structure des Modules (3 min)

```
📁 SkyLounge Réservation/
│
├── 📄 coworking-config.code-snippets.php        ← Priorité 1
├── 📄 coworking-booking-engine-v2.code-snippets.php
├── 📄 systeme-disponibilite.code-snippets.php
├── 📄 calendrier-coworking-v2.code-snippets.php
├── 📄 coworking-wc-order-complete.code-snippets.php
├── 📄 coworking-admin-*.code-snippets.php
├── 📄 coworking-cron.code-snippets.php
├── 📄 coworking-generation-contrats.code-snippets.php
├── 📄 rgpd-consentement-checkout.code-snippets.php
└── 📄 woocommerce-tunnel-de-vente.code-snippets.php
```

## Code Review : Booking Engine (5 min)

Ouvrir `coworking-booking-engine-v2.code-snippets.php` et montrer :

### 1. Système de Locks (ligne ~148)
```php
function coworking_add_lock($offre_id, $data) {
    $key = 'cw_locks_' . $offre_id;
    $locks = get_transient($key);
    // ... ajout du lock avec token unique
    set_transient($key, $locks, cw_get_lock_ttl($offre_id));
}
```

### 2. Endpoint REST cart-add (ligne ~177)
```php
register_rest_route('coworking/v1', '/cart-add', [
    'methods' => 'POST',
    'callback' => function(WP_REST_Request $req) {
        // 1. Validation des paramètres
        // 2. Vérification disponibilité
        // 3. Création du lock
        // 4. Ajout au panier WooCommerce
        // 5. Redirection vers checkout
    }
]);
```

### 3. Finalisation après paiement (ligne ~400+)
```php
// Hook sur woocommerce_order_status_completed
// → Création du CPT cw_reservation
// → Mise à jour du JSON reservations_json
// → Suppression du lock
```

## Code Review : CRON Maintenance (4 min)

Ouvrir `coworking-cron.code-snippets.php` et montrer :

```php
function coworking_run_daily_maintenance() {
    coworking_clean_expired_locks();      // Transients expirés
    coworking_clean_orphaned_locks();     // Locks sans commande
    coworking_clean_old_drafts();         // Brouillons > 24h
    coworking_repair_reservations_json(); // Cohérence JSON/WC
    coworking_anonymize_old_reservations(); // RGPD 3 ans
}
```

## Code Review : Configuration Centralisée (3 min)

Ouvrir `coworking-config.code-snippets.php` et montrer :

```php
// IDs Produits WooCommerce
define('CW_PRODUCT_ID_BUREAU', 1913);
define('CW_PRODUCT_ID_SALLE', 1917);

// Fonctions helpers
function cw_is_coworking_product($product_id) {
    return in_array((int)$product_id, cw_get_product_ids(), true);
}
```

**À dire :**
- "Tous les IDs sont centralisés ici. Si on recrée les produits, on modifie UN seul fichier."
- "Les fonctions helpers sont utilisées partout dans le code."

---

# SLIDE 7 - SÉCURITÉ & CONFORMITÉ (3 min)

## Mesures de Sécurité

| Mesure | Implémentation |
|--------|----------------|
| Injection SQL | API WordPress (wpdb::prepare) |
| XSS | sanitize_text_field(), esc_html() |
| CSRF | Nonces WordPress |
| Permissions | current_user_can() |
| Double réservation | Locks + Double vérification |

## Conformité RGPD

| Exigence | Implémentation |
|----------|----------------|
| Consentement explicite | Checkbox obligatoire checkout |
| Minimisation données | Seulement Nom, Email, Téléphone |
| Droit à l'oubli | CRON anonymisation après 3 ans |
| Transparence | Lien politique confidentialité |
| IP anonymisée | Dernier octet masqué (192.168.1.0) |

**À dire :**
- "Le RGPD est natif, pas un ajout après coup."
- "Les données sont automatiquement anonymisées après 3 ans."

---

# SLIDE 8 - POINTS D'AMÉLIORATION (2 min)

## Ce qui pourrait être amélioré

| Point | Priorité | Effort |
|-------|----------|--------|
| Tests unitaires (PHPUnit) | Moyenne | 2-3 jours |
| Documentation Swagger API | Faible | 1 jour |
| Race condition (verrouillage SQL) | Faible* | 1 jour |
| Export Excel des réservations | Moyenne | 1 jour |

*La race condition est mitigée par la double vérification et reste très improbable en pratique.

## Roadmap Future

- Q1 2026 : Dashboard analytics
- Q2 2026 : Multi-sites / Multi-espaces
- 2026+ : Application mobile (si volume suffisant)

**À dire :**
- "Le système est prêt pour la production mais pas parfait."
- "J'ai documenté les limitations connues."

---

# SLIDE 9 - QUESTIONS (selon temps restant)

## Questions fréquentes préparées

**Q: Pourquoi Code Snippets et pas un plugin custom ?**
> Maintenabilité : pas de build, pas de déploiement complexe. Chaque module est indépendant et peut être désactivé individuellement.

**Q: Que se passe-t-il si WooCommerce plante ?**
> Le système vérifie `function_exists('WC')` avant chaque opération et retourne une erreur propre.

**Q: Comment gérer les pics de charge ?**
> Les transients utilisent le cache object si disponible. Le JSON évite les requêtes CPT coûteuses.

**Q: Pourquoi pas de création de compte client ?**
> Choix métier pour simplifier le parcours. Tout est géré par email (confirmation + contrat).

---

# CHECKLIST AVANT PRÉSENTATION

- [ ] Environnement de test fonctionnel
- [ ] Une réservation de test prête à montrer
- [ ] VSCode ouvert avec les fichiers clés
- [ ] Email de test accessible pour montrer la confirmation
- [ ] PDF de contrat de test disponible
- [ ] Mode debug WordPress désactivé (ou logs visibles)
- [ ] Connexion internet stable (si démo en ligne)

---

# TIMING DÉTAILLÉ

| Section | Durée | Total |
|---------|-------|-------|
| Introduction | 3 min | 3 min |
| Démo Client | 10 min | 13 min |
| Démo Admin | 7 min | 20 min |
| Architecture | 5 min | 25 min |
| Code Review | 15 min | 40 min |
| Sécurité | 3 min | 43 min |
| Améliorations | 2 min | 45 min |

**Conseil :** Garde 5 min de marge pour les questions en cours de route.
