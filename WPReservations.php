<?php
/*
Plugin Name: WP-Réservations
Description: Gestion de réservation avec gestion utilisateurs et cumul de points selon réservations. Adapté aux CSE.
Version: 1.1.3
Author: Liziweb
Author URI: https://liziweb.com
*/
    // Sécurité pour éviter un accès direct au fichier
    defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


    // Inclure le fichier contenant les gestions des comptes
    include_once plugin_dir_path(__FILE__) . '/Compte/compte.php';

    // Inclure le fichier de réservation
    include plugin_dir_path(__FILE__) . 'Reservation/formulaire_reservation.php';
    include plugin_dir_path(__FILE__) . 'Reservation/regles.php';
    include plugin_dir_path(__FILE__) . 'Reservation/mail.php';

    // Inclure les fichiers administrateurs
    include plugin_dir_path(__FILE__) . 'Admin/liste_reservations.php';
    include plugin_dir_path(__FILE__) . 'Admin/users.php';
    include plugin_dir_path(__FILE__) . 'Admin/vehicules.php';


    class WPReservations
    {
        // Constructeur de la classe
        public function __construct()
        {
            register_activation_hook(__FILE__, array($this, 'install'));
            register_deactivation_hook(__FILE__, array($this, 'uninstall'));

            // Ajout de l'auteur Clémentin LY
            add_filter('plugin_row_meta', function ($links, $file)
            {
                if (plugin_basename(__FILE__) === $file)
                {
                    $links[] = '<a href="https://github.com/ClemLy" target="_blank">Clémentin LY</a>';
                }
                return $links;
            }, 10, 2);

            // Ajouter les hooks pour les actions et filtres
            add_action('init', array($this, 'init_hooks'));
        }

        // Méthode pour enregistrer les hooks
        public function init_hooks()
        {
            // Tâches Cron

            // Tâche cron pour la validation automatique des réservations
            if (!wp_next_scheduled('auto_validate_reservations'))
            {
                wp_schedule_event(time(), 'hourly', 'auto_validate_reservations');
            }

            add_action('auto_validate_reservations', 'validation_auto_reservation');


            // Tâche cron pour le rappel des réservations
            if (!wp_next_scheduled('auto_rappel_reservations'))
            {
                wp_schedule_event(time(), 'daily', 'auto_rappel_reservations');
            }

            add_action('auto_rappel_reservations', 'rappel_reservation');





            // Shortcodes
            add_shortcode('formulaire_inscription',  array($this,'afficher_formulaire_inscription'));
            add_shortcode('formulaire_connexion',  array($this,'afficher_formulaire_connexion'));
            add_shortcode('vehicule', array($this, 'afficher_formulaire_reservation_shortcode'));


            // Modifier l'expéditeur des e-mails
            add_filter('wp_mail_from_name', array($this, 'custom_mail_from_name'));
    
            // Charger les scripts JS de réservation
            add_action('wp_enqueue_scripts', array($this, 'charger_scripts_reservation'));

            // Charger le style CSS
            add_action('wp_enqueue_scripts', array($this, 'charger_style'));

            // Ajouter le menu d'administration pour le plugin
            add_action('admin_menu', array($this, 'ajouter_menu'));
        }




        // --- Activation & Désactivation du plugin ---
        public function install()
        {
            $this->ajouter_roles();
            $this->ajouter_champ_activation_utilisateur();
            
            $this->creer_tables_personnalisees();
        }

        public function uninstall()
        {
            $this->supprimer_roles();
            $this->supprimer_champ_activation_utilisateur();

            $this->supprimer_tables_personnalisees();

            wp_clear_scheduled_hook( 'auto_validate_reservations' );  // Nettoyage de la tâche cron lors de la désinstallation
            wp_clear_scheduled_hook( 'auto_rappel_reservations' );  // Nettoyage de la tâche cron lors de la désinstallation
        }



        // --- Gestion des rôles ---

        public function ajouter_roles()
        {
            add_role('admin_vehicule', 'AdministrateurVehicule', array(
                'read' => true,
                'access_reservation' => true, // Gérer les réservations, et les utilisateurs
                'manage_options' => true,     // Gérer les véhicules
            ));
        
            add_role('accueil_vehicule', 'AccueilVehicule', array(
                'read' => true,
                'access_reservation' => true, // Gérer les réservations, et les utilisateurs
            ));

            add_role('salarie', 'Salarié');
            add_role('retraite', 'Retraité');
            add_role('externe', 'Externe');

            $role = get_role('administrator');
            $role->add_cap('admin_vehicule');
            $role->add_cap('accueil_vehicule');
        }

        public function supprimer_roles()
        {
            remove_role('admin_vehicule');
            remove_role('accueil_vehicule');
            remove_role('salarie');
            remove_role('retraite');
            remove_role('externe');
        }





        // --- Gestion de la table usermeta ---

        public function ajouter_champ_activation_utilisateur()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'usermeta';

            if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'activation_key'"))
            {
                $wpdb->query("ALTER TABLE $table ADD activation_key VARCHAR(255) DEFAULT NULL");
            }
        }

        public function supprimer_champ_activation_utilisateur()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'usermeta';

            if ($wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'activation_key'"))
            {
                $wpdb->query("ALTER TABLE $table DROP COLUMN activation_key");
            }
        }



        // --- Création des tables personnalisées ---
        public function creer_tables_personnalisees()
        {
            global $wpdb;

            // Table pour les réservations
            $table_reservations = $wpdb->prefix . 'reservations';
            $charset_collate = $wpdb->get_charset_collate();

            $sql_reservations = "CREATE TABLE IF NOT EXISTS $table_reservations (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                utilisateur_id bigint(20) NOT NULL,
                nom varchar(100) NOT NULL,
                prenom varchar(100) NOT NULL,
                email varchar(100) NOT NULL,
                telephone varchar(20) NOT NULL,
                notes text NOT NULL,
                vehicule varchar(100) NOT NULL,
                date_reservation date NOT NULL,
                horaire_reservation varchar(50) NOT NULL,
                statut varchar(255) NOT NULL,
                date_annulation DATETIME NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            // Table pour les véhicules
            $table_vehicules = $wpdb->prefix . 'vehicules';
            $sql_vehicules = "CREATE TABLE IF NOT EXISTS $table_vehicules (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                nom varchar(100) NOT NULL,
                description text NOT NULL,
                validation_capacite TINYINT(1) DEFAULT 0 NOT NULL,
                image varchar(255) DEFAULT '' NOT NULL,
                email_admin varchar(100) NOT NULL,
                email_reservation varchar(100) NOT NULL,
                points_halfday int(11) NOT NULL,
                points_fullday int(11) NOT NULL,
                points_halfweek int(11) NOT NULL,
                points_fullweek int(11) NOT NULL,
                start_time_full_day TIME DEFAULT NULL,
                end_time_full_day TIME DEFAULT NULL,
                start_time_half_day TIME DEFAULT NULL,
                end_time_half_day TIME DEFAULT NULL,
                start_time_half_day2 TIME DEFAULT NULL,
                end_time_half_day2 TIME DEFAULT NULL,
                statut varchar(20) DEFAULT 'disponible' NOT NULL,
                jours_indisponibilites text NOT NULL,
                jours_feries text NOT NULL,
                permissions_reservation text NOT NULL,
                permissions_creneaux text NOT NULL,
                message_creneaux text NOT NULL,
                validation_auto_time int(11) NOT NULL,

                -- Emails transactionnels
                subject_valid text NOT NULL,
                body_valid text NOT NULL,
                subject_refus text NOT NULL,
                body_refus text NOT NULL,
                subject_rappel text NOT NULL,
                body_rappel text NOT NULL,
                subject_attente text NOT NULL,
                body_attente text NOT NULL,
                subject_capacite text NOT NULL,
                body_capacite text NOT NULL,

                PRIMARY KEY  (id)
            ) $charset_collate;";


            // Table pour les points des utilisateurs
            $table_points = $wpdb->prefix . 'points';
            $sql_points = "CREATE TABLE IF NOT EXISTS $table_points (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                utilisateur_id bigint(20) UNSIGNED NOT NULL,
                vehicule_id mediumint(9) NOT NULL,
                points_utilisateur int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                FOREIGN KEY (utilisateur_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
                FOREIGN KEY (vehicule_id) REFERENCES {$wpdb->prefix}vehicules(id) ON DELETE CASCADE
            ) $charset_collate;";


            // Table pour les validations des capacités des utilisateurs
            $table_validations = $wpdb->prefix . 'validations';
            $sql_validations = "CREATE TABLE IF NOT EXISTS $table_validations (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                utilisateur_id bigint(20) UNSIGNED NOT NULL,
                vehicule_id mediumint(9) NOT NULL,
                statut ENUM('Validé', 'Refusé') NOT NULL DEFAULT 'Refusé',
                PRIMARY KEY  (id),
                FOREIGN KEY (utilisateur_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
                FOREIGN KEY (vehicule_id) REFERENCES {$wpdb->prefix}vehicules(id) ON DELETE CASCADE
            ) $charset_collate;";


            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_reservations);
            dbDelta($sql_vehicules);
            dbDelta($sql_points);
            dbDelta($sql_validations);

        }

        // --- Suppression des tables personnalisées ---
        public function supprimer_tables_personnalisees()
        {
            /*global $wpdb;
            $table_name = $wpdb->prefix . 'reservations';
            $wpdb->query("DROP TABLE IF EXISTS $table_name");

            /*global $wpdb;
            $table_name = $wpdb->prefix . 'vehicules';
            $wpdb->query("DROP TABLE IF EXISTS $table_name");*/

            /*global $wpdb;
            $table_name = $wpdb->prefix . 'points';
            $wpdb->query("DROP TABLE IF EXISTS $table_name");*/

            /*global $wpdb;
            $table_name = $wpdb->prefix . 'validations';
            $wpdb->query("DROP TABLE IF EXISTS $table_name");*/
        }




        // --- Shortcodes ---


        // Shortcode pour afficher le formulaire d'inscription
        public function afficher_formulaire_inscription()
        {
            ob_start();
            afficher_formulaire_inscription();
            return ob_get_clean();
        }

        // Shortcode pour afficher le formulaire de connexion
        public function afficher_formulaire_connexion()
        {
            ob_start();
            afficher_formulaire_connexion();
            return ob_get_clean();
        }


        // Shortcode pour afficher le formulaire de réservation
        public function afficher_formulaire_reservation_shortcode($atts)
        {
            global $wpdb;
            $table_vehicules = $wpdb->prefix . 'vehicules';

            // Récupérer les attributs du shortcode
            $atts = shortcode_atts(array(
                'nom' => ''
            ), $atts);
            
            $nom_vehicule = sanitize_text_field($atts['nom']);

            // Vérifier si le véhicule existe
            $vehicule = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_vehicules WHERE nom = %s",
                $nom_vehicule
            ));

            if (!$vehicule)
            {
                return '<p style="color: red;">Véhicule introuvable ou shortcode incorrect.</p>';
            }

            // Récupérer l'utilisateur actuel
            $current_user = wp_get_current_user();

            // Appeler la fonction qui affiche le formulaire de réservation
            return afficher_formulaire_reservation($vehicule, $current_user);
        }





        // --- Actions et filtres ---


        // Modifier l'expéditeur des e-mails
        public function custom_mail_from_name()
        {
            return get_bloginfo('name'); // Nom du site
        }

        // Charger les scripts JS de réservation
        public function charger_scripts_reservation()
        {
            wp_enqueue_style('fullcalendar-style', 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.5/main.min.css');
            wp_enqueue_script('fullcalendar-core', 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.5/index.global.min.js', array(), null, true);
            wp_enqueue_script('fullcalendar-daygrid', 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.5/index.global.min.js', array('fullcalendar-core'), null, true);
            wp_enqueue_script('fullcalendar-locale-fr', 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.5/locales/fr.global.min.js', array('fullcalendar-core'), null, true);
            wp_enqueue_script('fullcalendar-interaction', 'https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.5/index.global.min.js', array('fullcalendar-core'), null, true);
            wp_enqueue_script('reservation-calendrier', plugin_dir_url(__FILE__) . 'Reservation/calendrier.js', array('fullcalendar-core'), null, true);
        }

        // Enregistrer et charger le style CSS
        public function charger_style()
        {
            wp_enqueue_style('plugin-style', plugin_dir_url(__FILE__) . 'style.css');
        }



        // Ajouter le menu d'administration pour le plugin
        public function ajouter_menu()
        {
            // Menu principal
            add_menu_page(
                'Liste des Réservations',      // Titre de la page
                'WP-Réservations',             // Titre dans la barre de menu
                'access_reservation',          // Capacité requise
                'wp-reservations',             // Slug unique
                'afficher_liste_reservations', // Fonction dans liste_reservations.php
                'dashicons-calendar-alt',      // Icône pour le menu
                20                             // Position
            );

            // Sous-menu caché : Détail de la réservation
            add_submenu_page(
                null,                         // Pas de menu parent (page cachée)
                'Détail Réservation',         // Titre de la page
                'Détail Réservation',         // Texte dans le sous-menu
                'access_reservation',         // Capacité requise
                'detail_reservation',         // Slug de la page
                'afficher_detail_reservation' // Fonction dans liste_reservations.php
            );

            // Sous-menu : Liste des utilisateurs
            add_submenu_page(
                'wp-reservations',            // Slug du menu parent
                'Liste des Utilisateurs',     // Titre de la page
                'Utilisateurs',               // Texte du sous-menu
                'access_reservation',         // Capacité requise
                'wp-reservations-users',      // Slug du sous-menu
                'afficher_liste_utilisateurs' // Fonction dans users.php
            );

            // Sous-menu caché : Détails Utilisateur
            add_submenu_page(
                null,                           // Slug du menu parent
                'Détails Utilisateur',          // Titre de la page
                'Détails Utilisateur',          // Titre du menu
                'access_reservation',                         // Capacité requise
                'wp-reservations-user-details', // Slug de la page
                'afficher_details_utilisateur'  // Fonction dans users.php
            );

            // Sous-menu : Gestion des Véhicules
            add_submenu_page(
                'wp-reservations',           // Slug du menu parent
                'Gestion des Véhicules',     // Titre de la page
                'Véhicules',                 // Texte du sous-menu
                'manage_options',            // Capacité requise
                'wp-reservations-vehicules', // Slug du sous-menu
                'afficher_vehicules'         // Fonction dans vehicules.php
            );

            // Sous-menu caché : Ajouter un Véhicule
            add_submenu_page(
                null,                          // Slug du menu parent
                'Ajouter un Véhicule',         // Titre de la page
                'Ajouter Véhicule',            // Texte du sous-menu
                'manage_options',              // Capacité requise
                'gestion_vehicule',            // Slug de la page
                'afficher_formulaire_vehicule' // Fonction dans vehicules.php
            );
        }
    }

    new WPReservations();
?>