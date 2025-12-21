# 📋 Notes Speaker - Présentation SkyLounge
## Revue Technique 45 minutes

---

## 🎯 OBJECTIF DE LA PRÉSENTATION

Montrer que tu es :
- **Méthodique** - Tu as une approche structurée
- **Technique** - Tu comprends ce que tu as codé
- **Honnête** - Tu connais les limites de ta solution
- **Orienté production** - Pas du "vibe coding"

---

## STRUCTURE GLOBALE (17 slides)

| Section | Slides | Label HTML | Temps estimé |
|---------|--------|------------|--------------|
| Introduction | 1-2 | Introduction | 3 min |
| 01 - Site WordPress | 3-4 | 01 - Site WordPress | 5 min |
| 02 - Jira | 5-6 | 02 - Jira | 5 min |
| 03 - Difficultés | 7-8 | 03 - Difficultés | 6 min |
| 03 - Décisions | 9 | 03 - Décisions Techniques | 4 min |
| 03 - Application | 10-11 | 03 - Application | 4 min |
| 04 - Code | 12-14 | 04 - Code | 8 min |
| 05 - Bilan Chiffré | 15 | 05 - Bilan Chiffré | 3 min |
| Conclusion | 16-17 | 05 - Honnêteté Technique / Conclusion | 7 min |

---

## SLIDE 1 : TITRE
**Durée : 30 sec**

### Ce que tu dis :
> "Bonjour, je vais vous présenter le projet SkyLounge Réservation que j'ai développé pendant mon alternance. C'est un système de booking pour des espaces de coworking, entièrement custom sur WordPress."

### Points clés :
- Rester bref, c'est juste une intro
- Mentionner les 5 sections de la présentation

---

## SLIDE 2 : CONTEXTE
**Durée : 2-3 min**

### Ce que tu dis :
> "Le besoin : un système de réservation pour espaces coworking avec paiement en ligne. 
> La contrainte principale : budget 0€ pour les plugins de réservation type Amelia ou Bookly.
> J'ai donc développé une solution 100% custom."

### Points clés :
- **Objectif** : Insister sur le fait que c'est une vraie demande métier
- **Stack** : WordPress + WooCommerce + ACF + Code Snippets
- **Contrainte** : Pas de budget plugin = tout à faire soi-même
- **Livrables** : ~400 KB de code PHP, 14 modules

### Questions potentielles :
- *"Pourquoi WordPress ?"* → C'est l'écosystème client, pas mon choix
- *"Pourquoi pas Amelia ?"* → Budget + besoin de customisation poussée

---

## SLIDE 3 : SITE WORDPRESS
**Durée : 2-3 min**

### Ce que tu dis :
> "Avant de parler du système de réservation, j'ai d'abord dû construire le site lui-même. C'était ma première vraie expérience complète avec WordPress, ACF et Elementor."

### Points clés :
- Police Gilroy pour la charte graphique
- Couleurs : bleu #1e73be, vert #10b981
- Elementor Pro pour le page building
- Pages créées : accueil, landing, singles, archives

### Ce que tu montres :
- Si possible, ouvrir le site en live pour montrer le résultat final

---

## SLIDE 4 : CPT (Custom Post Types)
**Durée : 2-3 min**

### Ce que tu dis :
> "J'ai créé 4 Custom Post Types avec ACF. C'était une première pour moi. Le plus important c'est 'Offres Coworking' qui est lié au système de réservation via le champ reservations_json."

### Points clés :
- **Immeubles** : Données des bâtiments
- **Annuaire** : Locataires présents (aspect premium)
- **Bail Commercial** : Pour les locations longue durée
- **Offres Coworking** : Le cœur du système de réservation

### Apprentissage :
> "C'était ma première création de CPT. J'ai compris la relation entre post types et champs ACF."

---

## SLIDE 5 : JIRA - PHASES
**Durée : 3 min**

### Ce que tu dis :
> "Voici comment j'ai organisé le travail en 4 phases. J'utilise Jira pour tracker mes tickets."

### Points clés par phase :
1. **Setup** : Config plugins (SMTP, WooCommerce, ACF, Wordfence, Rank Math...)
2. **Design** : Charte graphique + CPT + Templates
3. **Réservation** : Le gros du travail (frontend + backend)
4. **Conformité** : RGPD + Documentation

### Ce que tu fais :
> "Si vous voulez, je peux ouvrir Jira pour vous montrer les tickets en détail."

---

## SLIDE 6 : LEÇON JIRA
**Durée : 2 min**

### Ce que tu dis :
> "Un point important : j'ai perdu du temps au début parce que la vision n'était pas claire sur l'architecture des CPT. J'ai d'abord créé des Singles et Archives pour tous les CPT, puis j'ai dû refactorer."

### Points clés :
- Admettre l'erreur → montre la maturité
- V1 → Refacto → V2
- Leçon : maquetter AVANT de coder, valider avec le client

### Citation importante :
> "Une heure de planification peut économiser 10 heures de refactoring"

---

## SLIDE 7 : DIFFICULTÉS
**Durée : 2 min**

### Ce que tu dis :
> "Voici les 4 principaux problèmes que j'ai rencontrés, classés par criticité."

### Points clés :
1. **🔴 Race Condition** : Le plus critique, on va le détailler
2. **🟠 Désync JSON** : Résolu avec CRON
3. **🟡 Guest Checkout** : Metadata WooCommerce
4. **🟡 PDF** : Template HTML + fallback

---

## SLIDE 8 : DEEP DIVE LOCKS
**Durée : 5 min** ⚠️ SLIDE IMPORTANTE

### Ce que tu dis :
> "Je vais détailler le problème le plus critique : la race condition sur les réservations."

### Explique le scénario :
1. User A sélectionne le 15 janvier à 14:00:00
2. User B sélectionne le même jour à 14:00:01
3. User B paie en premier à 14:00:25
4. User A paie aussi à 14:00:30
5. **Résultat : 2 réservations pour 1 place !**

### Ta démarche :
> "J'ai d'abord recherché les solutions existantes : mutex, pessimistic locking, optimistic locking. J'ai choisi les WordPress Transients parce que c'est natif, avec TTL automatique."

### Le code :
> "Le TTL est adaptatif : 20 minutes pour un bureau unique (ressource rare), 5 minutes pour une salle partagée."

### Questions potentielles :
- *"Pourquoi pas une vraie transaction SQL ?"* → WordPress n'expose pas facilement les transactions, et les transients sont atomiques
- *"Et si le transient expire pendant le paiement ?"* → Le lock est recréé au checkout si besoin

---

## SLIDE 9 : DÉCISIONS TECHNIQUES
**Durée : 5 min** ⚠️ SLIDE IMPORTANTE

### Ce que tu dis :
> "Chaque décision technique a un trade-off. Voici mes choix et pourquoi."

### Pour chaque décision :
1. **Code Snippets vs Plugin custom**
   - ✅ Déploiement instantané
   - ⚠️ Pas de vrai Git → contourné avec exports JSON

2. **JSON dans ACF vs Table SQL**
   - ✅ Lecture ultra-rapide, pas de JOIN
   - ⚠️ Risque désync → CRON de rebuild

3. **Transients pour locks**
   - ✅ Natif WordPress, TTL automatique
   - ⚠️ Attention si Redis activé (config différente)

4. **Vanilla JS vs React**
   - ✅ Zéro build, ~30KB vs ~150KB
   - ⚠️ Moins maintenable si l'app grossit

### Attitude :
> "Je suis conscient des compromis. Ce sont des choix pragmatiques pour ce projet."

---

## SLIDE 10 : ARCHITECTURE
**Durée : 2 min**

### Ce que tu dis :
> "Voici l'architecture globale. Frontend en JS, API REST, Booking Engine en PHP, et la couche data."

### Points clés :
- Frontend : Calendrier JS + Calcul prix + Checkout WC
- API REST : Communication propre et découplée
- Backend : Locks + Disponibilités + Validation
- Data : CPT Réservation + WC Orders + JSON Cache

### Ce que tu fais :
> "Après cette slide, je vous propose une démo live de l'application."

---

## SLIDE 11 : MODULES PHP
**Durée : 3 min**

### Ce que tu dis :
> "Le code est découpé en 14 modules indépendants. Voici les priorités."

### Points clés :
- **P1-3** : Core (config, booking-engine, disponibilités)
- **P4** : Frontend (calendrier JS complet)
- **P5** : Hook WooCommerce
- **P6-8** : Admin
- **P9-14** : Features additionnelles

### Chiffre clé :
> "~400 KB de code PHP au total, architecture modulaire via Code Snippets"

---

## SLIDES 12-14 : CODE
**Durée : 10 min** ⚠️ IMPORTANT - OUVRIR VS CODE

### Ce que tu fais :
**Ouvrir VS Code et naviguer dans les fichiers**

### Fichiers à montrer :

#### 1. booking-engine-v2.php
- **Ligne ~65** : `cw_get_lock_ttl()` - TTL adaptatif
- **Ligne ~220** : `coworking_check_availability_with_locks()` - Vérifie réservations ET locks
- Expliquer la logique métier

#### 2. calendrier-v2.php
- Architecture Vanilla JS
- Shortcode `[coworking_calendar]`
- Appels REST async

#### 3. wc-order-complete.php
- Hook `woocommerce_order_status_completed`
- Flow : Paiement OK → Créer CPT → Update JSON → Supprimer lock

### Attitude :
- Ne pas lire le code ligne par ligne
- Expliquer la **logique**, pas la syntaxe
- Répondre aux questions en montrant le code

---

## SLIDE 12 : FONCTION CRITIQUE
**Durée : 3 min**

### Ce que tu dis :
> "Voici la fonction la plus critique du système : la vérification de disponibilité."

### Points clés :
- Montre le vrai code `coworking_check_availability_with_locks()`
- Explique les 3 sources de données : JSON, transients, dates bloquées
- Montre le nettoyage automatique des locks expirés

### Technique :
> "Cette fonction est appelée à chaque sélection de date. Elle doit être rapide, d'où le JSON plutôt que SQL."

---

## SLIDE 13 : API REST ENDPOINTS
**Durée : 2 min**

### Ce que tu dis :
> "L'application expose 3 endpoints REST pour la communication frontend/backend."

### Points clés :
- **GET /availability** : Retourne les disponibilités pour un mois
- **POST /add-to-cart** : Crée le lock + ajoute au panier
- **DELETE /release-lock** : Libère un lock si annulation

### Sécurité :
> "Tous les endpoints vérifient le nonce WordPress et les capacités utilisateur."

---

## SLIDE 14 : FLOW COMPLET
**Durée : 3 min**

### Ce que tu dis :
> "Voici le flow complet d'une réservation, de la sélection au paiement."

### Explique chaque étape :
1. User clique sur une date → JS déclenché
2. Appel API /add-to-cart
3. **Création du LOCK** ← moment clé !
4. Ajout panier WooCommerce
5. Paiement → hook déclenché
6. Finalisation : CPT + JSON + suppression lock + email

### Point important :
> "Le lock est créé AVANT l'ajout au panier. C'est ça qui empêche le double booking."

---

## SLIDE 15 : MÉTRIQUES
**Durée : 3 min** ⚠️ SLIDE QUI IMPRESSIONNE

### Ce que tu dis :
> "Voici les chiffres du projet."

### Chiffres clés :
- ~5000 lignes PHP / ~1100 lignes JS
- 14 modules / 3 endpoints REST / 4 CPT
- Budget plugins : 0€ (vs ~300€/an Amelia Pro)
- Temps réponse API : <100ms
- Double booking évités : 100%

### Attitude :
> "Ces chiffres montrent qu'on peut faire du sur-mesure sans exploser le budget."

---

## SLIDE 16 : LIMITES & AMÉLIORATIONS
**Durée : 3 min** ⚠️ SLIDE QUI MONTRE TA MATURITÉ

### Ce que tu dis :
> "Je vais être honnête sur les limites de ma solution et ce qui pourrait être amélioré."

### Limites :
1. **Pas de tests unitaires** → Le code marche mais pas testé automatiquement
2. **Dépendance Code Snippets** → Si désactivé, tout s'arrête
3. **JSON peut grossir** → Performance à surveiller

### Améliorations :
1. **Migration plugin custom** → Vrai Git
2. **Ajouter PHPUnit** → Tests sur fonctions critiques
3. **Dashboard analytics** → Stats de réservations

### Citation finale :
> "Montrer qu'on connaît ses limites, c'est de la maturité technique."

---

## SLIDE 17 : CONCLUSION
**Durée : 2 min**

### Ce que tu dis :
> "Pour conclure, voici un récapitulatif de ce que j'ai livré et appris."

### 3 sections :
1. **Ce que j'ai livré** : Système complet, anti-double booking, admin, RGPD
2. **Ce que j'ai appris** : Architecture, race conditions, REST API, hooks, RGPD, debugging
3. **Ce que je ferais différemment** : Maquetter avant, tests dès le début

### Fin :
> "Je suis disponible pour vos questions. Je peux ouvrir VS Code pour montrer n'importe quelle partie du code."

---

## 🔥 CONSEILS GÉNÉRAUX

### À FAIRE :
- ✅ Parler lentement et clairement
- ✅ Regarder ton audience, pas l'écran
- ✅ Admettre quand tu ne sais pas ("Je vais vérifier et revenir vers vous")
- ✅ Proposer d'ouvrir VS Code pour les questions
- ✅ Être honnête sur les limites

### À NE PAS FAIRE :
- ❌ Lire les slides mot pour mot
- ❌ Dire "c'est simple" ou "c'est facile"
- ❌ Inventer une réponse si tu ne sais pas
- ❌ Aller trop vite sur les parties techniques
- ❌ S'excuser constamment

### QUESTIONS DIFFICILES ANTICIPÉES :

| Question | Réponse |
|----------|---------|
| "Pourquoi pas utiliser un ORM ?" | WordPress n'a pas d'ORM natif, et wpdb suffit pour ce use case |
| "C'est scalable ?" | Pour le volume actuel oui, mais si explosion → migration table SQL |
| "Et les tests ?" | Pas implémentés, c'est dans les améliorations futures |
| "Tu referais quoi différemment ?" | Maquetter l'archi CPT avant, et commencer par le système de réservation |
| "Pourquoi pas React ?" | Zéro build, léger, pas de complexité inutile pour ce use case |
| "Le JSON peut-il corrompre ?" | CRON de vérification quotidien + rebuild si désync |

---

## ⏱️ TIMING RÉCAPITULATIF

| Section | Durée |
|---------|-------|
| Slides 1-4 (Intro, WP) | 8 min |
| Slides 5-6 (Jira) | 5 min |
| Slides 7-9 (Difficultés, Décisions) | 10 min |
| Slides 10-11 (Architecture) | 4 min |
| Slides 12-14 (Code) | 8 min |
| Slide 15 (Métriques) | 3 min |
| Slides 16-17 (Limites, Conclusion) | 7 min |
| **TOTAL** | **45 min** |

---

**Bonne présentation ! Tu vas déchirer ! 🚀**
