# WP-Reservations (v1.1.3)

**WP-Reservations** est une solution complète et robuste de gestion de réservations de véhicules directement intégrée à WordPress. Développé pour répondre aux besoins des structures exigeantes (CSE, entreprises, collectivités), ce plugin automatise le flux de réservation, de la demande utilisateur à la validation administrative.

---

## Fonctionnalités Clés

### Interface Utilisateur
* **Calendrier Dynamique :** Visualisation en temps réel des disponibilités via FullCalendar.
* **Réservations Flexibles :** Gestion par demi-journées (matin/après-midi) ou journées complètes.
* **Compte Personnel :** Historique des réservations et suivi du statut (en attente, validée, refusée).

### Administration & Contrôle
* **Tableau de Bord Centralisé :** Gestion simplifiée de toutes les demandes avec filtres par date et véhicule.
* **Règles Métier :** Gestion automatique des points/capacités et blocage des réservations passées.
* **Notes Administratives :** Suivi interne des utilisateurs et commentaires d'accueil.
* **Indisponibilités :** Définition de périodes de maintenance ou de fermeture par véhicule.

### Notifications & Communication
* **Système Mail Automatisé :** Notifications automatiques lors de la création, validation, refus ou annulation.
* **Rappels J-1 :** Envoi automatique de rappels aux utilisateurs la veille de leur réservation.

---

## Installation

1. Téléchargez le dossier du plugin.
2. Déposez-le dans le répertoire `/wp-content/plugins/` de votre installation WordPress.
3. Activez le plugin via le menu **Extensions** de WordPress.
4. Utilisez le shortcode fourni dans la gestion des véhicules pour afficher le calendrier sur vos pages.

---

## Stack Technique

* **Backend :** PHP (WordPress API)
* **Frontend :** JavaScript (FullCalendar, SweetAlert2)
* **Base de données :** MySQL (Tables personnalisées `$wpdb`)
* **Styling :** CSS3 (Responsive Design)

---

## Configuration requise

* WordPress 5.0 ou supérieur
* PHP 7.4 ou supérieur