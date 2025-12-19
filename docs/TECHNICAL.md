# 📚 Documentation Technique

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Modules](#modules)
5. [API Reference](#api-reference)
6. [Dépannage](#dépannage)

---

## Vue d'ensemble

Le système SkyLounge Coworking est composé de **12 modules indépendants** fonctionnant ensemble pour offrir une solution complète de réservation.

### Diagramme de flux

```
Client sélectionne créneau
        │
        ▼
┌───────────────────┐
│ Vérification      │
│ disponibilité     │
│ (temps réel)      │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ Création lock     │
│ temporaire        │
│ (5 minutes)       │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ Ajout panier      │
│ WooCommerce       │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ Checkout +        │
│ Paiement          │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ Création          │
│ réservation CPT   │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ Génération        │
│ contrat PDF       │
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│ Envoi email       │
│ confirmation      │
└───────────────────┘
```

---

## Installation

### Prérequis

| Composant | Version minimale |
|-----------|------------------|
| WordPress | 6.0+ |
| PHP | 8.0+ |
| WooCommerce | 8.0+ |
| ACF Pro | 6.0+ |
| Code Snippets | 3.0+ |

### Étapes d'installation

1. **Installer les plugins requis**
   - WooCommerce
   - Advanced Custom Fields PRO
   - Code Snippets

2. **Importer les snippets**
   - Importer chaque fichier `.code-snippets.php` dans Code Snippets

3. **Importer les champs ACF**
   - Utiliser `acf-export-2025-12-19.json` pour importer les groupes de champs

4. **Créer les produits WooCommerce**
   - Importer `wc-product-export-*.csv` ou créer manuellement

5. **Activer tous les snippets** dans l'ordre :
   - `coworking-config` (en premier)
   - Tous les autres modules

---

## Configuration

### Fichier de configuration principal

Le fichier `coworking-config.code-snippets.php` contient toutes les constantes :

```php
// Durées des locks
define('SKYLOUNGE_LOCK_DURATION', 300); // 5 minutes

// Emails
define('SKYLOUNGE_ADMIN_EMAIL', 'admin@skylounge.fr');

// Préfixe des contrats
define('SKYLOUNGE_CONTRACT_PREFIX', 'CW');
```

---

## Modules

| Module | Fichier | Description |
|--------|---------|-------------|
| 🔧 Configuration | `coworking-config` | Constantes et paramètres globaux |
| 📅 Calendrier | `calendrier-coworking-v2` | Interface de sélection des créneaux |
| ⚙️ Booking Engine | `coworking-booking-engine-v2` | Logique de réservation et locks |
| 📊 Disponibilité | `systeme-disponibilite` | Vérification temps réel |
| 📄 Contrats | `coworking-generation-contrats` | Génération PDF automatique |
| 🔔 Notifications | `coworking-notification-system` | Alertes admin/client |
| ⏰ CRON | `coworking-cron` | Tâches automatisées |
| 🛒 WooCommerce | `coworking-wc-order-complete` | Hooks post-commande |
| 📝 Admin | `page-admin-coworking` | Dashboard administrateur |
| 🗂️ Metabox | `coworking-admin-metabox` | Édition des réservations |
| 📋 Columns | `coworking-admin-columns` | Colonnes personnalisées |
| 🔒 RGPD | `rgpd-consentement-checkout` | Conformité légale |

---

## API Reference

### Endpoints disponibles

#### GET `/wp-json/skylounge/v1/availability/{product_id}`

Vérifie la disponibilité d'un espace.

**Paramètres :**
- `date` (string) : Date au format YYYY-MM-DD
- `start_time` (string) : Heure de début HH:MM
- `end_time` (string) : Heure de fin HH:MM

**Réponse :**
```json
{
  "available": true,
  "locked": false,
  "message": "Créneau disponible"
}
```

---

## Dépannage

### Problèmes courants

| Problème | Solution |
|----------|----------|
| Calendrier ne charge pas | Vérifier que le snippet `calendrier-coworking-v2` est actif |
| Double réservation | Vérifier les transients WordPress |
| PDF non généré | Vérifier les permissions du dossier uploads |
| Emails non envoyés | Configurer SMTP (WP Mail SMTP) |

### Logs

Activer le mode debug dans `coworking-config` :

```php
define('SKYLOUNGE_DEBUG', true);
```

Les logs sont disponibles dans : `wp-content/debug.log`
