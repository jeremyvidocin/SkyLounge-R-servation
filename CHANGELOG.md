# 📋 Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).

---

## [1.0.0] - 2025-12-19

### 🚀 Ajouté
- **Système de réservation complet** avec calendrier interactif
- **Booking Engine v2** avec système de locks anti-double réservation
- **Génération automatique de contrats PDF** avec numérotation séquentielle
- **Intégration WooCommerce** pour le paiement sécurisé
- **Dashboard administrateur** style Cal.com/Linear
- **Système de notifications** temps réel pour les nouvelles réservations
- **API REST** pour la vérification des disponibilités
- **Système CRON** pour la maintenance automatique
- **Conformité RGPD** avec consentement au checkout
- **Metabox personnalisées** pour la gestion des réservations

### 🏗️ Architecture
- Modularisation en Code Snippets séparés pour maintenance facilitée
- Configuration centralisée via `coworking-config`
- Système de logging intégré pour le debugging

### 🔒 Sécurité
- Validation des données côté serveur
- Système de locks avec transients WordPress
- Protection contre les injections SQL via API WordPress

---

## 🔮 Roadmap

### [1.1.0] - Prévu Q1 2026
- [ ] Dashboard analytics avancé
- [ ] Export des réservations en Excel
- [ ] Notifications par SMS (Twilio)
- [ ] Intégration calendrier Google/Outlook

### [1.2.0] - Prévu Q2 2026
- [ ] Application mobile (React Native)
- [ ] Système de fidélité/abonnements
- [ ] Multi-sites / Multi-espaces
