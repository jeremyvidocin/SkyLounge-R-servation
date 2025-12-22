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
- **Contrainte** : Pas de plugins payants = développement sur-mesure
- **Livrables** : ~400 KB de code PHP, 14 modules

### Questions potentielles :
- *"Pourquoi WordPress ?"* → C'est l'écosystème existant, pas mon choix
- *"Pourquoi pas Amelia ?"* → Besoin de customisation poussée + pas de budget plugins

---

## SLIDE 3 : SITE WORDPRESS
**Durée : 2-3 min**

### Ce que tu dis :
> "Avant de parler du système de réservation, j'ai d'abord dû construire le site lui-même. C'était ma première vraie expérience complète avec WordPress, ACF et Elementor."

### Points clés :
- **Police Gilroy** : Charte graphique premium du site
- **Couleurs** : Bleu SkyLounge #1e73be, vert success #10b981
- **Elementor Pro** : Page builder pour l'intégration
- **Pages créées** : Accueil, landing, singles, archives

### 💡 Démarche Design :
> "J'ai utilisé **Lovable** pour maquetter certains composants avant de les intégrer. Ça m'a permis de tester rapidement le rendu visuel avant de passer sur Elementor."

### Pourquoi mentionner Lovable :
- Montre que tu **réfléchis avant de coder**
- Prototypage rapide = gain de temps
- Tu sais utiliser des outils modernes

### ⚠️ Si on te demande plus de détails :
> "C'était pour moi, pour visualiser le design avant l'intégration. Ça m'a aidé à structurer mes idées."

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

### Ce que tu expliques :
> "Le frontend ne fait jamais d'appel direct à la base de données. Tout passe par l'API REST, ce qui permet un découplage propre."

---

## SLIDE 11 : MODULES PHP
**Durée : 3 min**

### Ce que tu dis :
> "Le code est découpé en 14 modules indépendants. Je ne vais pas tous les détailler, on va se concentrer sur les plus critiques."

### Points clés à mentionner :
- **P1-3** : Core (config, booking-engine, disponibilités) - LE CŒUR
- **P4** : Frontend (calendrier JS complet)
- **P5** : Hook WooCommerce (après paiement)
- Les autres sont des features additionnelles (admin, notif, PDF, RGPD...)

### Chiffre clé :
> "~400 KB de code PHP au total, architecture modulaire via Code Snippets"

### ⚠️ Ne pas s'attarder :
Cette slide donne une vue d'ensemble. Le détail vient dans les slides suivantes.

---

## SLIDES 12-14 : CODE
**Durée : 8 min** ⚠️ SLIDES IMPORTANTES

### Approche :
- Le code est déjà affiché sur les slides
- Explique la **logique métier**, pas la syntaxe ligne par ligne
- Montre que tu comprends ce que tu as codé

### Fichiers présentés :

#### 1. booking-engine-v2.php (Slide 12)
- Fonction `coworking_check_availability_with_locks()`
- Vérifie réservations confirmées ET locks temporaires
- Nettoyage auto des locks expirés

#### 2. API REST (Slide 13)
- 3 endpoints : GET availability, POST add-to-cart, DELETE release-lock
- Communication frontend/backend découplée

#### 3. Flow complet (Slide 14)
- 6 étapes de la sélection au paiement
- Le lock est créé AVANT l'ajout au panier (moment clé !)

### Attitude :
- Ne pas lire le code mot pour mot
- Expliquer **pourquoi** chaque étape existe
- Répondre aux questions avec assurance

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
> "Je suis disponible pour vos questions."

---

## 🔥 CONSEILS GÉNÉRAUX

### À FAIRE :
- ✅ Parler lentement et clairement
- ✅ Regarder ton audience, pas l'écran
- ✅ Admettre quand tu ne sais pas ("Je vais vérifier et revenir vers vous")
- ✅ Être honnête sur les limites
- ✅ Prendre ton temps sur les slides techniques

### À NE PAS FAIRE :
- ❌ Lire les slides mot pour mot
- ❌ Dire "c'est simple" ou "c'est facile"
- ❌ Inventer une réponse si tu ne sais pas
- ❌ Aller trop vite sur les parties techniques
- ❌ S'excuser constamment

---

## 📚 GLOSSAIRE TECHNIQUE - DÉFINITIONS À CONNAÎTRE

### WordPress & Écosystème

| Terme | Définition simple | Si on te demande plus |
|-------|-------------------|----------------------|
| **CPT (Custom Post Type)** | Un type de contenu personnalisé dans WordPress. Comme les "Articles" ou "Pages" mais créé sur-mesure. | "WordPress a par défaut les posts et pages. Un CPT permet de créer ses propres types, ici j'ai créé 'Offres Coworking' avec ses propres champs." |
| **ACF (Advanced Custom Fields)** | Plugin qui permet d'ajouter des champs personnalisés aux CPT. | "Sans ACF, je devrais tout coder en PHP. ACF me donne une interface admin pour créer des champs comme 'prix', 'capacité', etc." |
| **Elementor** | Page builder visuel pour WordPress. Drag & drop. | "C'est un éditeur WYSIWYG qui permet de construire des pages sans coder le HTML/CSS à la main." |
| **WooCommerce** | Plugin e-commerce pour WordPress. Gère le panier, paiement, commandes. | "C'est la solution e-commerce la plus utilisée sur WordPress. Je l'utilise uniquement pour le tunnel de paiement." |
| **Code Snippets** | Plugin qui permet d'ajouter du code PHP sans modifier le thème. | "Au lieu de modifier functions.php ou créer un plugin, j'ajoute des snippets indépendants. Plus facile à maintenir." |
| **Hook (Action/Filter)** | Point d'ancrage dans WordPress pour exécuter du code à un moment précis. | "Une action = j'exécute du code quand un événement se produit (ex: après paiement). Un filter = je modifie une donnée avant qu'elle soit affichée." |
| **Shortcode** | Balise entre crochets qui exécute du PHP. Ex: `[coworking_calendar]` | "C'est un raccourci. J'écris `[coworking_calendar]` dans une page et ça affiche mon calendrier complet." |

### Concepts Techniques Généraux

| Terme | Définition simple | Si on te demande plus |
|-------|-------------------|----------------------|
| **REST API** | Interface qui permet à deux systèmes de communiquer via HTTP (GET, POST, DELETE...) | "Le frontend JS appelle une URL comme `/wp-json/coworking/v1/availability` et reçoit du JSON. C'est découplé du backend." |
| **Endpoint** | Une URL spécifique de l'API qui fait une action précise. | "J'ai 3 endpoints : un pour récupérer les dispos, un pour ajouter au panier, un pour annuler un lock." |
| **JSON** | Format de données texte, léger et lisible. Clé-valeur. | "C'est comme un tableau associatif mais en texte. `{\"date\": \"2025-01-15\", \"status\": \"booked\"}`. Facile à lire et parser." |
| **Transient** | Variable temporaire stockée en base WordPress avec une durée de vie (TTL). | "C'est comme une variable de session mais côté serveur. Elle expire automatiquement après X secondes." |
| **TTL (Time To Live)** | Durée de vie d'une donnée avant qu'elle expire automatiquement. | "Mon lock a un TTL de 20 minutes. Après ça, il disparaît tout seul, pas besoin de le supprimer manuellement." |
| **CRON** | Tâche planifiée qui s'exécute automatiquement à intervalles réguliers. | "WordPress a son propre système CRON. J'ai une tâche qui tourne chaque nuit pour vérifier la cohérence des données." |
| **Nonce** | Token de sécurité unique pour vérifier qu'une requête est légitime. | "Ça empêche les attaques CSRF. Le frontend envoie un token, le backend vérifie qu'il est valide." |

### Concepts de Concurrence

| Terme | Définition simple | Si on te demande plus |
|-------|-------------------|----------------------|
| **Race Condition** | Bug quand deux processus accèdent à la même ressource en même temps. | "Deux users cliquent en même temps → sans protection, les deux peuvent réserver la même place." |
| **Lock (Verrou)** | Mécanisme qui bloque une ressource temporairement pour un seul utilisateur. | "Quand User A sélectionne une date, je pose un lock. User B voit que c'est 'en cours' et ne peut pas réserver." |
| **Mutex** | Un type de lock qui garantit qu'un seul processus accède à une ressource. | "Mutex = Mutual Exclusion. C'est le concept théorique, mon implémentation utilise les transients WordPress." |
| **Pessimistic Locking** | On verrouille la ressource AVANT de la modifier. | "C'est ce que je fais : je lock AVANT l'ajout au panier. Approche prudente." |
| **Optimistic Locking** | On vérifie au moment de sauvegarder si quelqu'un d'autre a modifié. | "L'alternative serait de vérifier au moment du paiement. Risque : l'utilisateur a perdu 10 min pour rien." |
| **Atomique** | Opération qui s'exécute entièrement ou pas du tout, pas d'état intermédiaire. | "set_transient() est atomique : soit le lock est créé, soit il ne l'est pas. Pas de lock 'à moitié'." |

### Base de Données & Performance

| Terme | Définition simple | Si on te demande plus |
|-------|-------------------|----------------------|
| **wpdb** | Classe PHP de WordPress pour interagir avec la base de données. | "C'est l'équivalent d'un ORM basique. Je fais `$wpdb->get_results()` pour exécuter du SQL." |
| **JOIN** | Requête SQL qui combine des données de plusieurs tables. | "Un JOIN est coûteux en performance. Mon JSON évite les JOINs car tout est dans un seul champ." |
| **Cache** | Stockage temporaire pour éviter de recalculer/requêter les mêmes données. | "Mon JSON dans ACF est un cache. Plutôt que requêter toutes les réservations à chaque fois, je lis un seul champ." |
| **Désynchronisation** | Quand deux sources de données ne sont plus cohérentes. | "Si le JSON dit 'disponible' mais qu'il y a une réservation dans le CPT → désync. Mon CRON corrige ça." |

### Sécurité & RGPD

| Terme | Définition simple | Si on te demande plus |
|-------|-------------------|----------------------|
| **CSRF** | Attaque où un site malveillant fait exécuter une action à un user connecté. | "Sans nonce, un attaquant pourrait créer un lien qui ajoute une réservation à l'insu de l'utilisateur." |
| **Sanitize** | Nettoyer une entrée utilisateur pour éviter les injections. | "`sanitize_text_field()` enlève les balises HTML et caractères dangereux d'une chaîne." |
| **RGPD** | Règlement européen sur la protection des données personnelles. | "Je dois : demander le consentement, anonymiser les IPs, permettre la suppression des données." |
| **Consentement explicite** | L'utilisateur doit activement accepter (pas de case pré-cochée). | "Une checkbox que l'user doit cocher lui-même avant de pouvoir payer." |
| **Anonymisation IP** | Masquer une partie de l'adresse IP pour ne pas identifier la personne. | "Je remplace le dernier octet par 0. `192.168.1.123` devient `192.168.1.0`." |

---

## 🎯 QUESTIONS DIFFICILES ANTICIPÉES

| Question | Réponse courte | Réponse détaillée si on insiste |
|----------|----------------|--------------------------------|
| "Pourquoi pas utiliser un ORM ?" | WordPress n'a pas d'ORM natif, et wpdb suffit pour ce use case | "Un ORM comme Doctrine ou Eloquent ajouterait une dépendance lourde. wpdb fait le job pour des requêtes simples. Si le projet grossissait, je considérerais un ORM." |
| "C'est scalable ?" | Pour le volume actuel oui, mais si explosion → migration table SQL | "Le JSON est rapide jusqu'à quelques milliers de réservations. Au-delà, je migrerais vers une table SQL dédiée avec index." |
| "Et les tests ?" | Pas implémentés, c'est dans les améliorations futures | "J'aurais dû commencer par les tests. Maintenant que le code fonctionne, ajouter PHPUnit est dans ma roadmap." |
| "Tu referais quoi différemment ?" | Maquetter l'archi CPT avant, et commencer par le système de réservation | "J'ai perdu du temps sur les templates avant de clarifier le besoin. Prochaine fois : specs d'abord." |
| "Pourquoi pas React ?" | Zéro build, léger, pas de complexité inutile pour ce use case | "React aurait demandé une toolchain (npm, webpack, etc.). Pour un calendrier, Vanilla JS suffit et pèse 30KB vs 150KB." |
| "Le JSON peut-il corrompre ?" | CRON de vérification quotidien + rebuild si désync | "Le CRON compare le JSON avec les vrais CPT chaque nuit. Si désync détectée, il rebuild le JSON." |
| "Pourquoi Transients et pas Redis ?" | Transients sont natifs WordPress, Redis demande une config serveur | "Les transients utilisent la table wp_options par défaut. Si Redis est configuré, WordPress l'utilise automatiquement." |
| "Comment tu gères les paiements échoués ?" | Le lock expire automatiquement, la place redevient disponible | "Si le paiement échoue, le lock a un TTL de 20 min max. Après expiration, la date est à nouveau réservable." |
| "Qu'est-ce qui se passe si le serveur crash pendant une réservation ?" | Les transients sont en base de données, donc persistants | "Même si le serveur redémarre, le lock est toujours là car stocké en base. Il expirera naturellement après le TTL." |
| "C'est sécurisé ?" | Nonces WordPress + sanitization + capability checks | "Chaque requête API vérifie le nonce (anti-CSRF), les inputs sont sanitizés, et je vérifie les permissions utilisateur." |

---

## ⏱️ TIMING RÉCAPITULATIF

| Section | Durée |
|---------|-------|
| Slides 1-4 (Intro, WP) | 9 min |
| Slides 5-6 (Jira) | 5 min |
| Slides 7-9 (Difficultés, Décisions) | 11 min |
| Slides 10-11 (Architecture) | 5 min |
| Slides 12-14 (Code) | 8 min |
| Slide 15 (Métriques) | 3 min |
| Slides 16-17 (Limites, Conclusion) | 4 min |
| **TOTAL** | **45 min** |

---

**Bonne présentation ! Tu vas déchirer ! 🚀**
