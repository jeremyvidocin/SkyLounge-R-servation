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

### 🎤 Discours complet :

> "Bonjour à tous, merci d'être là pour cette revue technique.
> 
> Je vais vous présenter **SkyLounge Réservation**, le projet principal sur lequel j'ai travaillé pendant mon alternance. C'est un système de réservation complet pour des espaces de coworking, avec paiement en ligne.
> 
> La présentation est structurée en **5 parties** : d'abord je vous présente le contexte et le site WordPress, ensuite l'organisation du travail avec Jira, puis les difficultés techniques rencontrées et mes décisions d'architecture. On passera ensuite sur le code avec des extraits concrets, et je terminerai par un bilan chiffré et les limites que j'ai identifiées.
> 
> Au total, comptez environ 45 minutes. N'hésitez pas à m'interrompre si vous avez des questions."

---

## SLIDE 2 : CONTEXTE
**Durée : 2-3 min**

### 🎤 Discours complet :

> "Alors, le contexte. L'entreprise SkyLounge gère plusieurs espaces de coworking à Paris. Jusqu'ici, les réservations se faisaient par téléphone ou par email, ce qui posait des problèmes de disponibilité en temps réel et de double-booking.
> 
> **Le besoin était clair** : permettre aux clients de réserver un espace en ligne, de voir les disponibilités en temps réel, et de payer directement sur le site.
> 
> **La contrainte principale** : on ne pouvait pas utiliser de plugins de réservation payants comme Amelia ou Bookly. Ces plugins coûtent entre 60 et 150€ par an, et surtout ils ne correspondent pas exactement au besoin métier. Par exemple, la tarification est différente selon le type d'espace, et on voulait un calendrier très spécifique.
> 
> **J'ai donc développé une solution 100% sur-mesure**. Le stack technique, c'est WordPress 6.x avec WooCommerce pour le paiement, ACF pour les champs personnalisés, et Code Snippets pour organiser mon code PHP en modules indépendants.
> 
> Au total, ça représente environ **400 KB de code**, répartis en **14 modules PHP** et un gros fichier JavaScript de 33KB pour le calendrier interactif."

### 💡 Si on te demande :
- *"Pourquoi WordPress ?"* → "C'était l'écosystème déjà en place chez le client. Je n'ai pas choisi la stack, je me suis adapté."
- *"Pourquoi pas Amelia ?"* → "Deux raisons : le coût, et surtout le besoin de customisation poussée. Amelia ne gère pas notre logique de tarification multi-espaces."

---

## SLIDE 3 : SITE WORDPRESS
**Durée : 2-3 min**

### 🎤 Discours complet :

> "Avant de parler du système de réservation, il faut savoir que j'ai aussi construit le site lui-même. C'était ma première vraie expérience complète avec l'écosystème WordPress professionnel.
> 
> Pour le design, j'ai utilisé **la police Gilroy** qui donne un aspect premium et moderne. Les couleurs principales sont le bleu SkyLounge et un vert pour les éléments de succès, les confirmations.
> 
> J'ai intégré les pages avec **Elementor Pro**, le page builder. Ça permet de construire des pages visuellement, mais derrière il y a quand même du code custom pour les fonctionnalités dynamiques.
> 
> **Un point sur ma démarche de design** : avant de me lancer dans l'intégration Elementor, j'ai utilisé **Lovable** pour maquetter rapidement certains composants. C'est un outil de prototypage. Ça m'a permis de visualiser le rendu final, de tester des variantes de layout, avant de passer du temps sur l'intégration réelle.
> 
> C'était pour moi, pour structurer mes idées. Ça m'a fait gagner du temps parce que j'avais une vision claire avant de coder."

### 💡 Si on te demande sur Lovable :
> "C'est un outil de prototypage rapide. Je l'ai utilisé pour mes maquettes personnelles, pour ne pas partir dans l'intégration à l'aveugle. Ce n'était pas pour valider avec le client, c'était pour moi."

---

## SLIDE 4 : CPT (Custom Post Types)
**Durée : 2-3 min**

### 🎤 Discours complet :

> "WordPress utilise nativement des 'Articles' et des 'Pages'. Mais pour un projet métier, on a besoin de types de contenu personnalisés. C'est ce qu'on appelle les **Custom Post Types**, ou CPT.
> 
> J'en ai créé **4 pour ce projet** :
> 
> **Immeubles** : ça stocke les informations des bâtiments - adresse, photos, description des espaces communs.
> 
> **Annuaire** : c'est la liste des entreprises déjà présentes dans le coworking. Ça fait partie de l'argumentaire commercial, montrer qu'il y a déjà une communauté.
> 
> **Bail Commercial** : pour les locations longue durée, les contrats annuels. C'est un autre business model que le coworking à la journée.
> 
> Et le plus important : **Offres Coworking**. C'est le cœur du système de réservation. Chaque offre a un prix, une capacité, des photos, et surtout un champ `reservations_json` qui contient toutes les réservations sous forme de JSON.
> 
> C'était ma **première création de CPT**. J'ai appris à structurer les relations entre les post types et les champs ACF. Par exemple, une offre de coworking est rattachée à un immeuble via un champ relationnel."

### 💡 Si on te demande :
- *"Pourquoi JSON plutôt qu'une table SQL ?"* → "On verra ça dans les décisions techniques, c'est un choix assumé avec des trade-offs."
- *"ACF c'est quoi exactement ?"* → "Advanced Custom Fields. C'est un plugin qui permet de créer des champs personnalisés visuellement, sans coder les meta boxes à la main."

---

## SLIDE 5 : JIRA - PHASES
**Durée : 3 min**

### 🎤 Discours complet :

> "Pour organiser le travail, j'ai utilisé **Jira** avec une méthodologie par phases. J'avais entre 80 et 100 tickets au total, répartis en 4 grandes phases.
> 
> **Phase 1 : Setup** - C'est la configuration initiale. Installation et paramétrage de tous les plugins nécessaires : WooCommerce pour le e-commerce, ACF pour les champs personnalisés, SMTP pour l'envoi d'emails, Wordfence pour la sécurité, Rank Math pour le SEO. C'est invisible pour l'utilisateur final, mais c'est la fondation.
> 
> **Phase 2 : Design** - Création de la charte graphique, intégration des templates Elementor, création des CPT qu'on vient de voir. C'est la partie visible du site.
> 
> **Phase 3 : Réservation** - C'est le gros du travail. Le calendrier interactif côté frontend, le booking engine côté backend, l'API REST, les notifications email. C'est là où j'ai passé le plus de temps.
> 
> **Phase 4 : Conformité** - RGPD, conditions générales, politique de confidentialité, et documentation technique pour la maintenance.
> 
> Cette organisation m'a permis d'avoir une vision claire de l'avancement et de prioriser les tickets par phase."

---

## SLIDE 6 : LEÇON JIRA
**Durée : 2 min**

### 🎤 Discours complet :

> "Et là, je vais être honnête avec vous sur une erreur que j'ai faite.
> 
> Au début du projet, la vision n'était pas totalement claire sur l'architecture des CPT. J'ai commencé par créer des templates - des Singles et des Archives - pour **tous** les CPT, en pensant qu'on en aurait besoin.
> 
> Résultat : j'ai passé du temps à développer des pages qui n'étaient pas prioritaires. Par exemple, j'ai fait un template d'archive pour les baux commerciaux, alors que ce n'était pas le besoin immédiat.
> 
> J'ai dû **refactorer**. Passer d'une V1 où j'avais tout développé, à une V2 où je me suis concentré sur ce qui était vraiment nécessaire pour la mise en production.
> 
> **La leçon que j'en tire** : maquetter l'architecture AVANT de coder. Valider avec le client ce qui est vraiment nécessaire pour le MVP. Comme on dit : 'Une heure de planification peut économiser dix heures de refactoring.'
> 
> C'est une erreur de junior, et je ne la referai pas."

---

## SLIDE 7 : DIFFICULTÉS
**Durée : 2 min**

### 🎤 Discours complet :

> "Maintenant, parlons des difficultés techniques. J'en ai rencontré plusieurs, et je les ai classées par criticité.
> 
> **En rouge, le plus critique : la Race Condition**. C'est quand deux utilisateurs tentent de réserver le même créneau au même moment. Sans protection, on peut se retrouver avec deux réservations pour une seule place. On va détailler ça dans la slide suivante.
> 
> **En orange : la désynchronisation des données**. J'ai un JSON qui sert de cache pour les disponibilités, et parfois il pouvait se désynchroniser des vraies réservations. J'ai résolu ça avec une tâche CRON qui vérifie la cohérence chaque nuit.
> 
> **En jaune : le Guest Checkout**. WooCommerce permet d'acheter sans créer de compte. Le problème, c'est que dans ce cas l'ID utilisateur est null. J'ai dû stocker les informations de réservation dans les metadata de la commande plutôt que dans un profil utilisateur.
> 
> **Également en jaune : la génération de PDF**. Pour les contrats et factures. La librairie que j'utilisais avait des problèmes de rendu. J'ai opté pour un template HTML avec du CSS print, et un fallback si le PDF ne se génère pas."

---

## SLIDE 8 : DEEP DIVE LOCKS
**Durée : 5 min** ⚠️ SLIDE IMPORTANTE

### 🎤 Discours complet :

> "Je vais maintenant détailler le problème le plus critique : la race condition. C'est un classique en développement web, mais c'était la première fois que je devais le résoudre en conditions réelles.
> 
> **Le scénario problématique** : imaginez deux utilisateurs, User A et User B. User A ouvre le calendrier et sélectionne le 15 janvier à 14h00 pile. Une seconde plus tard, User B fait exactement la même chose. Les deux voient le créneau comme disponible.
> 
> User B est plus rapide à payer, il valide à 14h00 et 25 secondes. La réservation est créée. Mais User A, lui, a toujours le créneau dans son panier, et il paie à 14h00 et 30 secondes. Sans protection, **les deux paiements passent et on a deux réservations pour une seule place**.
> 
> **Ma démarche de résolution** : j'ai d'abord recherché les patterns existants. Il y a le mutex, le pessimistic locking qui verrouille avant, l'optimistic locking qui vérifie au moment de sauvegarder.
> 
> J'ai choisi le **pessimistic locking avec les WordPress Transients**. Pourquoi ? Parce que c'est natif à WordPress, je n'ai pas besoin d'installer Redis ou de configurer un système externe. Et les transients ont un TTL automatique - Time To Live - ce qui veut dire que le verrou expire tout seul si l'utilisateur abandonne son panier.
> 
> **Le fonctionnement** : quand un utilisateur sélectionne une date, je crée un transient avec une clé unique basée sur le produit et la date. Si un autre utilisateur essaie de sélectionner la même date, la fonction `set_transient` échoue parce que la clé existe déjà. Il voit alors 'En cours de réservation' au lieu de 'Disponible'.
> 
> **Le TTL est adaptatif** : 20 minutes pour un bureau privé, parce que c'est une ressource rare et le parcours de paiement peut être long. 5 minutes pour une place en open space, parce que c'est moins critique.
> 
> **Si le transient expire pendant le paiement** - par exemple si quelqu'un met 25 minutes - le hook WooCommerce `woocommerce_checkout_process` recrée le lock juste avant le paiement."

### 💡 Si on te demande :
- *"Pourquoi pas une vraie transaction SQL ?"* → "WordPress n'expose pas facilement les transactions SQL via wpdb. Il aurait fallu écrire du SQL brut avec BEGIN/COMMIT. Les transients sont atomiques et suffisent pour ce use case."
- *"C'est vraiment atomique ?"* → "Oui, `set_transient` utilise `add_option` en interne qui est atomique au niveau SQL. Si deux requêtes arrivent en même temps, une seule réussit."

---

## SLIDE 9 : DÉCISIONS TECHNIQUES
**Durée : 5 min** ⚠️ SLIDE IMPORTANTE

### 🎤 Discours complet :

> "Maintenant, je vais vous présenter mes décisions techniques. Chaque choix a des avantages et des inconvénients - des trade-offs. Je vais vous expliquer pourquoi j'ai fait ces choix.
> 
> **Première décision : Code Snippets plutôt qu'un plugin custom.**
> 
> Un plugin WordPress classique, c'est un dossier avec des fichiers PHP qu'on déploie via FTP ou Git. Code Snippets, c'est un plugin qui permet d'ajouter du code PHP directement depuis l'admin WordPress, sans toucher au système de fichiers.
> 
> L'avantage : le déploiement est instantané. Je modifie le code dans l'interface admin, je sauve, c'est en production. Pas besoin de pipeline de déploiement.
> 
> L'inconvénient : pas de vrai versioning Git natif. J'ai contourné ça en exportant régulièrement mes snippets en JSON, que je commite dans un repo Git. C'est un workflow manuel, mais ça fonctionne.
> 
> **Deuxième décision : stocker les réservations en JSON dans ACF plutôt qu'une table SQL dédiée.**
> 
> Le champ `reservations_json` contient un tableau JSON avec toutes les réservations d'une offre. Pour afficher le calendrier, je lis un seul champ au lieu de faire des requêtes SQL avec des JOINs.
> 
> L'avantage : c'est extrêmement rapide en lecture. Une seule requête pour avoir toutes les disponibilités d'un mois.
> 
> L'inconvénient : risque de désynchronisation si le JSON n'est pas mis à jour correctement. J'ai un CRON qui tourne chaque nuit pour vérifier la cohérence et rebuilder le JSON si nécessaire.
> 
> **Troisième décision : les Transients pour le système de locks.**
> 
> On vient d'en parler. C'est natif WordPress, le TTL est automatique.
> 
> Le point d'attention : si le site utilise Redis comme cache, les transients sont stockés dans Redis au lieu de la base de données. Le comportement peut être légèrement différent. Ici le site n'utilise pas Redis, donc pas de problème.
> 
> **Quatrième décision : Vanilla JavaScript plutôt que React.**
> 
> Le calendrier fait environ 1100 lignes de JavaScript pur, sans framework. Ça pèse 33KB.
> 
> L'avantage : zéro toolchain. Pas de npm, pas de webpack, pas de build. Je modifie le fichier JS, c'est en ligne.
> 
> L'inconvénient : si l'application grossit beaucoup, ça sera moins maintenable qu'un framework avec des composants. Mais pour ce use case, c'est suffisant."

### 💡 Si on te demande :
- *"Tu le referais en React ?"* → "Pour ce projet, non. Si le calendrier devait devenir une vraie SPA avec beaucoup d'interactions, peut-être. Mais là le rapport effort/bénéfice ne justifiait pas React."

---

## SLIDE 10 : ARCHITECTURE
**Durée : 2 min**

### 🎤 Discours complet :

> "Voici l'architecture globale du système. Je l'ai organisée en 4 couches.
> 
> **La couche Frontend** : c'est ce que voit l'utilisateur. Le calendrier JavaScript qui affiche les disponibilités, le calcul du prix en temps réel quand on sélectionne plusieurs jours, et le tunnel de paiement WooCommerce.
> 
> **La couche API REST** : c'est le point de communication entre le frontend et le backend. J'ai créé 3 endpoints. Un GET pour récupérer les disponibilités d'un mois. Un POST pour ajouter une réservation au panier. Un DELETE pour libérer un lock si l'utilisateur annule.
> 
> C'est important : le frontend ne fait **jamais** d'appel direct à la base de données. Tout passe par l'API. Ça permet un découplage propre. Si demain on veut refaire le frontend en React ou en application mobile, le backend ne change pas.
> 
> **La couche Backend** : c'est le Booking Engine. Il gère les locks, vérifie les disponibilités, valide les règles métier - par exemple qu'on ne peut pas réserver dans le passé, ou que la capacité n'est pas dépassée.
> 
> **La couche Data** : les réservations confirmées sont stockées dans un CPT, les commandes dans WooCommerce Orders, et le JSON dans ACF sert de cache pour les lectures rapides."

---

## SLIDE 11 : MODULES PHP
**Durée : 3 min**

### 🎤 Discours complet :

> "Le code PHP est découpé en **14 modules indépendants**. Je ne vais pas tous les détailler, mais je vais vous donner une vue d'ensemble.
> 
> Les modules sont organisés par priorité. **P1 à P3**, c'est le cœur : la configuration globale, le booking engine avec la logique de réservation, et le système de disponibilités.
> 
> **P4**, c'est le frontend - le calendrier JavaScript complet, le shortcode qui l'affiche, et le CSS associé.
> 
> **P5**, c'est le hook WooCommerce qui se déclenche après le paiement. C'est lui qui crée la réservation définitive et met à jour le JSON.
> 
> Les autres modules sont des features additionnelles : l'interface d'administration, les notifications email, la génération de PDF pour les contrats, la conformité RGPD avec le consentement au checkout.
> 
> Au total, ça représente environ **400 KB de code PHP**. L'avantage de cette architecture modulaire, c'est que je peux activer ou désactiver un module sans impacter les autres. Par exemple, si je veux désactiver les notifications email temporairement, je désactive juste ce snippet."

---

## SLIDES 12-14 : CODE
**Durée : 8 min** ⚠️ SLIDES IMPORTANTES

### 🎤 Discours Slide 12 - Booking Engine :

> "Là on rentre dans le code. Vous avez sous les yeux la fonction `coworking_check_availability_with_locks`. C'est le cœur du système de vérification.
> 
> Ce que fait cette fonction : elle reçoit un product_id et une date en paramètre. Elle vérifie d'abord s'il y a déjà une **réservation confirmée** pour cette date - via le JSON qu'on a vu tout à l'heure. Si oui, elle retourne false.
> 
> Ensuite, elle vérifie s'il y a un **lock actif** - un transient - pour cette date. Si quelqu'un d'autre a ce créneau dans son panier, elle retourne aussi false.
> 
> Vous voyez aussi le nettoyage automatique des locks expirés. Ça garantit que la fonction renvoie toujours un état propre.
> 
> La beauté de cette fonction, c'est qu'elle abstrait toute la complexité. Le code appelant fait juste `if (coworking_check_availability_with_locks($id, $date))` et il a sa réponse."

### 🎤 Discours Slide 13 - API REST :

> "Ici, les 3 endpoints de l'API REST.
> 
> `GET /wp-json/coworking/v1/availability` : le frontend appelle cette URL avec un product_id et un mois. Le backend renvoie un tableau JSON avec chaque jour du mois et son statut - disponible, réservé, en cours de réservation.
> 
> `POST /wp-json/coworking/v1/add-to-cart` : quand l'utilisateur clique sur 'Réserver', ça appelle cet endpoint. Il crée le lock, ajoute le produit au panier WooCommerce, et renvoie l'URL du checkout.
> 
> `DELETE /wp-json/coworking/v1/release-lock` : si l'utilisateur annule, ça libère le lock pour que la date redevienne disponible.
> 
> Chaque endpoint vérifie le **nonce** pour la sécurité anti-CSRF, et **sanitize** les inputs pour éviter les injections."

### 🎤 Discours Slide 14 - Flow complet :

> "Et voilà le flow complet en 6 étapes.
> 
> L'utilisateur ouvre le calendrier, le JavaScript appelle l'API pour récupérer les disponibilités du mois. Il sélectionne une date, le JS appelle add-to-cart qui crée le lock et redirige vers le checkout. Le paiement se fait via WooCommerce et Stripe. Après paiement, le hook `woocommerce_order_status_completed` se déclenche, crée la réservation définitive dans le CPT, met à jour le JSON, et envoie l'email de confirmation.
> 
> Ce qui est important ici, c'est que chaque étape est **indépendante** et **testable**. Si demain le paiement échoue, le lock expire et le système revient à un état cohérent."

---

## SLIDE 15 : MÉTRIQUES
**Durée : 3 min** ⚠️ SLIDE QUI IMPRESSIONNE

### 🎤 Discours complet :

> "Maintenant, les chiffres du projet. C'est important de quantifier ce qu'on a produit.
> 
> **Volume de code** : environ 5000 lignes de PHP réparties en 14 modules, et 1100 lignes de JavaScript pour le calendrier interactif. Au total, ça fait environ 400 KB de code.
> 
> **Architecture** : 3 endpoints REST pour la communication frontend/backend, 4 Custom Post Types pour structurer les données.
> 
> **Budget plugins de réservation** : on n'a pas utilisé de solution payante. Pour comparaison, Amelia Pro coûte environ 300€ par an. Ici on a une solution sur-mesure, adaptée exactement au besoin métier, sans coût de licence récurrent.
> 
> **Performance** : le temps de réponse de l'API est inférieur à 100 millisecondes. C'est rapide parce qu'on lit le JSON plutôt que de faire des requêtes SQL complexes.
> 
> **Et le plus important** : depuis la mise en place du système de locks, on a **zéro double booking**. C'était le problème critique, et il est résolu.
> 
> Ces chiffres montrent qu'on peut faire du développement sur-mesure, qualité production, sans exploser le budget."

---

## SLIDE 16 : LIMITES & AMÉLIORATIONS
**Durée : 3 min** ⚠️ SLIDE QUI MONTRE TA MATURITÉ

### 🎤 Discours complet :

> "Je vais maintenant être honnête sur les limites de ma solution. Je pense que c'est important de savoir ce qui pourrait être amélioré.
> 
> **Première limite : pas de tests unitaires.** Le code fonctionne, il est en production, mais il n'y a pas de tests automatisés. Si je modifie une fonction, je n'ai pas de filet de sécurité pour détecter les régressions.
> 
> C'est un risque que j'ai identifié. L'amélioration serait d'ajouter PHPUnit pour tester au moins les fonctions critiques comme la vérification de disponibilité.
> 
> **Deuxième limite : la dépendance à Code Snippets.** Si quelqu'un désactive le plugin Code Snippets par erreur, tout le système de réservation s'arrête. C'est fragile.
> 
> L'amélioration serait de migrer vers un vrai plugin custom, avec un dossier dans wp-content/plugins, versionné sur Git. Ce serait plus robuste et plus professionnel.
> 
> **Troisième limite : le JSON peut grossir.** Pour l'instant les performances sont excellentes, mais si le nombre de réservations explose, le champ JSON pourrait devenir trop gros. La lecture resterait rapide, mais l'écriture pourrait ralentir.
> 
> L'amélioration serait de monitorer la taille du JSON et, si nécessaire, de migrer vers une table SQL dédiée avec des index.
> 
> Ce qui est important ici, c'est que **je connais les limites de ma solution**. Je ne les cache pas. Ça fait partie de la maturité technique de savoir où sont les points de fragilité."

---

## SLIDE 17 : CONCLUSION
**Durée : 2 min**

### 🎤 Discours complet :

> "Pour conclure, je vais résumer ce que j'ai livré, ce que j'ai appris, et ce que je ferais différemment.
> 
> **Ce que j'ai livré** : un système de réservation complet et fonctionnel. Avec un calendrier interactif, un système anti-double booking, une interface d'administration pour visualiser les réservations, la conformité RGPD avec consentement et anonymisation, et une documentation technique pour la maintenance.
> 
> **Ce que j'ai appris** : techniquement, j'ai appris à architecturer un projet WordPress modulaire, à gérer les race conditions avec des mécanismes de locking, à créer une API REST propre, à utiliser les hooks WooCommerce pour intégrer le paiement, à implémenter la conformité RGPD. Et au-delà du code, j'ai appris l'importance de la planification et du debugging en production.
> 
> **Ce que je ferais différemment** : je maquetterais l'architecture des CPT avant de commencer le développement, pour éviter le refactoring. Et j'ajouterais des tests dès le début du projet, pas après.
> 
> Voilà, c'est la fin de ma présentation. Est-ce que vous avez des questions ?"

### 💡 Transition vers les questions :
> Attends quelques secondes en silence. Regarde l'audience. Si personne ne parle, tu peux dire : "N'hésitez pas, sur la technique, sur l'organisation, sur les choix... je suis ouvert."

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
