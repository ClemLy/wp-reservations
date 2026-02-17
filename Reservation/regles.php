<?php
    // Empêche l'accès direct
    defined('ABSPATH') or die('No script kiddies please!');


    function traiter_reservation()
    {
        if (isset($_POST['reservation_nonce']) && wp_verify_nonce($_POST['reservation_nonce'], 'reservation_action'))
        {
            global $wpdb;
            $table_reservations = $wpdb->prefix . 'reservations';
            $table_vehicules    = $wpdb->prefix . 'vehicules';
            $table_points       = $wpdb->prefix . 'points';
            $table_validations  = $wpdb->prefix . 'validations';

            $nom              = sanitize_text_field($_POST['nom']);
            $prenom           = sanitize_text_field($_POST['prenom']);
            $email            = sanitize_email($_POST['email']);
            $telephone        = sanitize_text_field($_POST['telephone']);
            $notes            = sanitize_textarea_field($_POST['notes']);
            $date_reservation = sanitize_text_field($_POST['date_reservation']);
            $horaire          = sanitize_text_field($_POST['horaire_reservation']);
            $vehicule         = sanitize_text_field($_POST['vehicule']);
            $utilisateur_id   = (int) get_current_user_id();


            /* --- Récupération de données --- */

            // Vérifier si la période choisie est déjà réservée par quelqu'un d'autre
            $existe = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_reservations 
                    WHERE vehicule = %s 
                    AND date_reservation = %s 
                    AND statut = 'validée'
                    AND (
                        horaire_reservation = %s 
                        OR (horaire_reservation = 'full_day' AND %s IN ('half_day_morning', 'half_day_afternoon')) 
                        OR (horaire_reservation IN ('half_day_morning', 'half_day_afternoon') AND %s = 'full_day')
                        OR (horaire_reservation = 'full_day_weekend' AND %s IN ('half_day_morning_weekend', 'half_day_afternoon_weekend'))
                        OR (horaire_reservation IN ('half_day_morning_weekend', 'half_day_afternoon_weekend') AND %s = 'full_day_weekend')
                    )",
                    $vehicule, $date_reservation, $horaire, $horaire, $horaire, $horaire, $horaire
                )
            );




            // Vérifier si l'utilisateur avait déjà une réservation pour cette date
            $reservation_existante = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_reservations 
                    WHERE utilisateur_id = %d 
                    AND vehicule = %s
                    AND date_reservation = %s
                    AND (
                        horaire_reservation = %s 
                        OR (horaire_reservation = 'full_day' AND %s IN ('half_day_morning', 'half_day_afternoon')) 
                        OR (horaire_reservation IN ('half_day_morning', 'half_day_afternoon') AND %s = 'full_day')
                        OR (horaire_reservation = 'full_day_weekend' AND %s IN ('half_day_morning_weekend', 'half_day_afternoon_weekend'))
                        OR (horaire_reservation IN ('half_day_morning_weekend', 'half_day_afternoon_weekend') AND %s = 'full_day_weekend')

                    )",
                    $utilisateur_id, $vehicule, $date_reservation, $horaire, $horaire, $horaire, $horaire, $horaire
                )
            );





            // Vérifier si la date est un jour d'indisponibilité pour ce véhicule
            $indisponible = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_vehicules
                    WHERE nom = %s AND FIND_IN_SET(%s, jours_indisponibilites) > 0",
                    $vehicule, $date_reservation
                )
            );


            // Récupérer l'ID du véhicule
            $vehicule_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_vehicules WHERE nom = %s", $vehicule));
            if (!$vehicule_id)
            {
                echo '<p class="message-error">Véhicule introuvable.</p>';
                return;
            }

            // Vérifier si la date sélectionnée est un week-end
            $date_obj = new DateTime($date_reservation);
            $day_of_week = $date_obj->format('N'); // 6 = Samedi, 7 = Dimanche
            $is_weekend = ($day_of_week >= 6);

            // Récupérer les jours fériés depuis la base de données
            $jours_feries = $wpdb->get_var("SELECT jours_feries FROM $table_vehicules WHERE id = $vehicule_id");
            if (!empty($jours_feries))
            { 
                $jours_feries_array = explode(',', $jours_feries);  // Transformer la chaîne en tableau de dates
            }

            // Vérifier si la date sélectionnée est un jour férié
            if (isset($jours_feries_array) && !empty($jours_feries_array) && in_array($date_reservation, $jours_feries_array))
            {
                $is_weekend = true; // Si c'est un jour férié, le jour est considéré comme un week-end
            }


            // Adapter le type d'horaire en fonction du week-end
            if ($is_weekend)
            {
                if ($horaire === 'half_day_morning')
                {
                    $horaire = 'half_day_morning_weekend';
                }
                elseif ($horaire === 'half_day_afternoon')
                {
                    $horaire = 'half_day_afternoon_weekend';
                }
                elseif ($horaire === 'full_day')
                {
                    $horaire = 'full_day_weekend';
                }
            }


            // Récupérer les points nécessaires pour la réservation
            $points_requis = $wpdb->get_var($wpdb->prepare(
                "SELECT 
                    CASE 
                        WHEN %s = 'half_day_morning' OR %s = 'half_day_afternoon' THEN points_halfday
                        WHEN %s = 'full_day' THEN points_fullday
                        WHEN %s = 'half_day_morning_weekend' OR %s = 'half_day_afternoon_weekend' THEN points_halfweek
                        WHEN %s = 'full_day_weekend' THEN points_fullweek
                        ELSE 0
                    END
                FROM $table_vehicules
                WHERE id = %d",
                $horaire, $horaire, $horaire, $horaire, $horaire, $horaire, $vehicule_id
            ));



            // Récupérer les points de l'utilisateur pour ce véhicule
            $points_utilisateur = $wpdb->get_var($wpdb->prepare(
                "SELECT points_utilisateur FROM $table_points WHERE utilisateur_id = %d AND vehicule_id = %d",
                $utilisateur_id, $vehicule_id
            ));



            // Vérifier s'il existe déjà une réservation "en attente"
            $reservation_attente = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT r.utilisateur_id, p.points_utilisateur FROM $table_reservations r
                    INNER JOIN $table_points p ON r.utilisateur_id = p.utilisateur_id
                    WHERE r.vehicule = %s
                    AND r.date_reservation = %s
                    AND r.statut = 'en attente'
                    AND r.horaire_reservation = %s",
                    $vehicule, $date_reservation, $horaire
                )
            );



            // Récupérer les permissions de réservation du véhicule en fonction de son ID
            $permissions_reservation = $wpdb->get_row($wpdb->prepare(
                "SELECT permissions_reservation FROM $table_vehicules WHERE id = %d",
                $vehicule_id
            ));


            // Récupérer les permissions de réservation pour le véhicule
            $permissions = json_decode($permissions_reservation->permissions_reservation, true);

            $table_usermeta = $wpdb->prefix . 'usermeta';
            $capaibilities = $wpdb->prefix . 'capabilities';
            // Récupérer le rôle de l'utilisateur qui tente de réserver
            $role_utilisateur_meta = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $table_usermeta WHERE user_id = %d AND meta_key = %s",
                $utilisateur_id, $capaibilities
            ));

            // Désérialiser la valeur récupérée
            $role_utilisateur_data = maybe_unserialize($role_utilisateur_meta);

            // Vérifier que c'est bien un tableau et extraire le premier rôle
            $role_utilisateur = is_array($role_utilisateur_data) ? array_key_first($role_utilisateur_data) : '';


           // Créer un objet DateTime
            $date_obj = new DateTime($date_reservation);

            // Récupérer le jour de la semaine en anglais
            $jour_anglais = $date_obj->format('l'); // Exemple: "Tuesday"

            // Mappage vers le jour en français
            $jours = [
                'Sunday' => 'Dimanche',
                'Monday' => 'Lundi',
                'Tuesday' => 'Mardi',
                'Wednesday' => 'Mercredi',
                'Thursday' => 'Jeudi',
                'Friday' => 'Vendredi',
                'Saturday' => 'Samedi'
            ];

            // Récupérer le jour en français
            $jour_actuel = $jours[$jour_anglais];



            // Récupérer les permissions des créneaux du véhicule en fonction de son ID
            $permissions_creneaux = $wpdb->get_row($wpdb->prepare(
                "SELECT permissions_creneaux FROM $table_vehicules WHERE id = %d",
                $vehicule_id
            ));

            // Récupérer les créneaux de réservation pour le véhicule
            $creneaux = json_decode($permissions_creneaux->permissions_creneaux, true);

            // Récupérer la période que l'utilisateur veut réserver
            if ($horaire === 'half_day_morning' || $horaire === 'half_day_morning_weekend')
            {
                $periode = 'morning';
            }
            elseif ($horaire === 'half_day_afternoon' || $horaire === 'half_day_afternoon_weekend')
            {
                $periode = 'afternoon';
            }
            else
            {
                $periode = 'fullday';
            }

            // Récupérer le message indiquant le refus de la réservation sur ce créneau
            $message_creneaux = $wpdb->get_var($wpdb->prepare(
                "SELECT message_creneaux FROM $table_vehicules WHERE id = %d",
                $vehicule_id
            ));

            



            // Vérifier si l'utilisateur a le statut "Validé" pour ce véhicule
            $statut_validation = $wpdb->get_var($wpdb->prepare(
                "SELECT statut FROM $table_validations WHERE utilisateur_id = %d AND vehicule_id = %d",
                $utilisateur_id, $vehicule_id
            ));


            // Convertir la date de réservation en timestamp
            $date_reservation_timestamp = strtotime($date_reservation);

            // Récupérer la date actuelle sans l'heure
            $date_actuelle_timestamp = strtotime(date('Y-m-d'));



            // Récupérer la valeur 'validation_auto_time' pour le véhicule
            $validation_auto_time = $wpdb->get_var($wpdb->prepare(
                "SELECT validation_auto_time FROM $table_vehicules WHERE id = %d",
                $vehicule_id
            ));

            // Calculer le temps limite pour la réservation (ajouter validation_auto_time en heures à la date de création)
            $date_limite = strtotime($date_reservation) - ($validation_auto_time) * 3600; // 3600 = 1 heure en secondes

            // Récupérer l'heure actuelle
            $now = time(); 




            /* --- Vérifications --- */
            
            echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

            // Vérifier si l'horaire est déjà réservé
            if ($existe > 0)
            {
                afficherAlerte("Ce créneau horaire est déjà réservé. Votre demande ne peut être validée.", "Erreur", "error");
                return;
            }
            
            // Vérifier si l'utilisateur a déjà une réservation pour ce véhicule
            if ($reservation_existante > 0)
            {
                afficherAlerte("Vous avez déjà une réservation pour cette date.", "Erreur", "error");
                return;
            }
            
            // Vérifier si le véhicule est indisponible à cette date
            if ($indisponible > 0)
            {
                afficherAlerte("Réservation impossible à cette date.", "Indisponible", "error");
                return;
            }
            
            // Vérifier si le véhicule est réservé par quelqu'un qui a moins de points
            if ($reservation_attente)
            {
                $points_utilisateur_existant = $reservation_attente->points_utilisateur;
                
                if ($points_utilisateur + 1 > $points_utilisateur_existant)
                {
                    afficherAlerte("Un autre utilisateur à déjà réservé ce créneau,
                                            et est prioritaire en raison de son nombre de points inférieur au votre.
                                            Votre demande ne peut être validée.", "Points élevés", "error");
                    return;
                }
            }
            
            // Vérifier si la personne a un rôle qui peut réserver pour ce jour
            if (!isset($permissions[$jour_actuel][$role_utilisateur]))
            {
                afficherAlerte("Vous ne pouvez réserver ce créneau, conformément aux règles de réservations définies. 
                                        Veuillez consulter les conditions de réservation plus haut", "Accès refusé", "warning");
                return;
            }

            // Vérifier si la personne a un rôle qui peut réserver pour ce créneau
            if (!isset($creneaux[$jour_actuel][$periode]))
            {
                afficherAlerte($message_creneaux, "Accès refusé", "warning");
                return;
            }
            
            // Si l'utilisateur n'a pas de validation ou s'il est "Refusé", on bloque la réservation et on envoie un mail
            if ($statut_validation !== 'Validé')
            {
                afficherAlerte("Vous ne pouvez actuellement pas réaliser cette réservation. Un email avec des instructions spécifiques vous parviendra sous 5 minutes.", "Validation requise", "warning");
            
                envoyer_mail_reservation($email, 'capacite', $vehicule, $date_reservation, $horaire, $nom, $prenom);
                return;
            }
            
            // Vérifier si la date de réservation est antérieure à aujourd'hui
            if ($date_reservation_timestamp < $date_actuelle_timestamp)
            {
                afficherAlerte("Réservation Impossible : La date est expirée.", "Date invalide", "error");
                return;
            }
            
            // Vérifier si l'heure actuelle est après la date limite
            /*if ($now >= $date_limite)
            {
                afficherAlerte("Vous ne pouvez réserver ce créneau, conformément aux règles de réservations définies. 
                                Veuillez consulter les conditions de réservation plus haut", "Temps écoulé", "error");
                return;
            }*/


            /* --- Insertion --- */

            // Si l'utilisateur n'a pas encore de points pour ce véhicule
            if ($points_utilisateur === null)
            {
                $points_utilisateur = 0;
            }

            // Déterminer le statut de la réservation
            if ($points_utilisateur == 0)
            {
                $statut_reservation = 'validée';
            }
            else
            {
                $statut_reservation = 'en attente';
            }


            // Insertion dans la table avec la date sélectionnée
            $wpdb->insert(
                $table_reservations,
                [
                    'utilisateur_id'      => $utilisateur_id,
                    'nom'                 => $nom,
                    'prenom'              => $prenom,
                    'email'               => $email,
                    'telephone'           => $telephone,
                    'notes'               => $notes,
                    'vehicule'            => $vehicule,
                    'date_reservation'    => $date_reservation,
                    'horaire_reservation' => $horaire,
                    'statut'              => $statut_reservation
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if ($statut_reservation === 'en attente')
            {
                afficherAlerte(
                    "Réservation en attente d'acceptation. Vous recevrez une confirmation $validation_auto_time heure(s) avant la date de votre réservation.",
                    "En attente de validation",
                    "info"
                );
            }
            else
            {
                afficherAlerte(
                    "Réservation effectuée avec succès pour $vehicule !",
                    "Succès",
                    "success"
                );
            }

            envoyer_mail_reservation($email, $statut_reservation, $vehicule, $date_reservation, $horaire, $nom, $prenom);




            // Vérifier si l'utilisateur a déjà une ligne pour ce véhicule dans la table des points
            $existe_points = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_points WHERE utilisateur_id = %d AND vehicule_id = %d",
                $utilisateur_id, $vehicule_id
            ));

            if ($existe_points > 0)
            {
                // Mise à jour des points disponibles
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_points 
                    SET points_utilisateur = points_utilisateur + %d
                    WHERE utilisateur_id = %d AND vehicule_id = %d",
                    $points_requis, $utilisateur_id, $vehicule_id
                ));
            }
            else
            {
                // Insérer les points pour l'utilisateur
                $wpdb->insert(
                    $table_points,
                    [
                        'utilisateur_id'     => $utilisateur_id,
                        'vehicule_id'        => $vehicule_id,
                        'points_utilisateur' => $points_requis
                    ],
                    ['%d', '%d', '%d']
                );
            }

            // Récupérer les points mis à jour après la modification
            $points_utilisateur = $wpdb->get_var($wpdb->prepare(
                "SELECT points_utilisateur FROM $table_points WHERE utilisateur_id = %d AND vehicule_id = %d",
                $utilisateur_id, $vehicule_id
            ));

            // Appeler la fonction de vérifications des autres réservations
            verifier_annulation_reservation($vehicule, $vehicule_id, $date_reservation, $horaire, $points_utilisateur);
        }
    }
    add_action('init', 'traiter_reservation');


    // Fonction pour annuler les réservations en attente lorsque quelqu'un avec moins de points réserve
    function verifier_annulation_reservation($vehicule, $vehicule_id, $date_reservation, $horaire, $points_utilisateur)
    {
        global $wpdb;
        $table_reservations = $wpdb->prefix . 'reservations';
        $table_vehicules    = $wpdb->prefix . 'vehicules';
        $table_points       = $wpdb->prefix . 'points';

        // Vérifier si des réservations en attente existent pour cette période
        $reservations_en_attente = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, p.points_utilisateur, r.utilisateur_id, r.nom, r.prenom
                FROM $table_reservations r
                INNER JOIN $table_points p ON r.utilisateur_id = p.utilisateur_id
                WHERE r.vehicule = %s
                AND r.date_reservation = %s
                AND r.statut = 'en attente'
                AND p.vehicule_id = (SELECT id FROM $table_vehicules WHERE nom = %s)
                AND (
                    r.horaire_reservation = %s
                    OR (r.horaire_reservation = 'full_day' AND %s IN ('half_day_morning', 'half_day_afternoon'))
                    OR (%s = 'full_day' AND r.horaire_reservation IN ('half_day_morning', 'half_day_afternoon'))
                    OR (r.horaire_reservation = 'full_day_weekend' AND %s IN ('half_day_morning_weekend', 'half_day_afternoon_weekend'))
                    OR (%s = 'full_day_weekend' AND r.horaire_reservation IN ('half_day_morning_weekend', 'half_day_afternoon_weekend'))
                )",
                $vehicule, $date_reservation, $vehicule, $horaire, $horaire, $horaire, $horaire, $horaire
            )
        );

        
        foreach ($reservations_en_attente as $reservation)
        {
            $nom    = $reservation->nom;
            $prenom = $reservation->prenom;

            /*---------------------------*/
            /*--- COMPARAISONS POINTS ---*/
            /*---------------------------*/

            // Si des réservations en attente existent et que l'utilisateur a moins de points
            if ($points_utilisateur < $reservation->points_utilisateur)
            {
                // Annuler la réservation en attente de l'utilisateur avec moins de points
                $wpdb->update(
                    $table_reservations,
                    array('statut' => 'refusée'),
                    array('id' => $reservation->id)
                );

                // Récupérer les points exacts que l'utilisateur avait engagés pour cette réservation
                $points_annules = $wpdb->get_var($wpdb->prepare(
                    "SELECT 
                        CASE 
                            WHEN r.horaire_reservation = 'half_day_morning' OR r.horaire_reservation = 'half_day_afternoon' THEN v.points_halfday
                            WHEN r.horaire_reservation = 'full_day' THEN v.points_fullday
                            WHEN r.horaire_reservation = 'half_day_morning_weekend' OR r.horaire_reservation = 'half_day_afternoon_weekend' THEN v.points_halfweek
                            WHEN r.horaire_reservation = 'full_day_weekend' THEN v.points_fullweek
                            ELSE 0
                        END
                    FROM $table_reservations r
                    INNER JOIN $table_vehicules v ON r.vehicule = v.nom
                    WHERE r.id = %d",
                    $reservation->id
                ));

                
                // Retirer ces points de l'utilisateur
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_points 
                    SET points_utilisateur = points_utilisateur - %d
                    WHERE utilisateur_id = %d AND vehicule_id = %d",
                    $points_annules, $reservation->utilisateur_id, $vehicule_id
                ));

                // Récupérer l'email de l'utilisateur affecté
                $email = $wpdb->get_var($wpdb->prepare(
                    "SELECT email FROM $table_reservations WHERE id = %d",
                    $reservation->id
                ));

                // Envoyer un email de refus à l'utilisateur affecté
                envoyer_mail_reservation($email, 'refusée', $vehicule, $date_reservation, $horaire, $nom, $prenom);
            }
        }
    }



    // Fonction qui vérifie et valide les réservations automatiquement
    function validation_auto_reservation()
    {   
        global $wpdb;
        $table_reservations = $wpdb->prefix . 'reservations';
        $table_vehicules    = $wpdb->prefix . 'vehicules';

        // Requête pour récupérer les réservations en attente
        $reservations = $wpdb->get_results("SELECT * FROM $table_reservations WHERE statut = 'en attente'");

        foreach ($reservations as $reservation)
        {
            $email = $wpdb->get_var($wpdb->prepare(
                "SELECT email FROM $table_reservations WHERE id = %d",
                $reservation->id
            ));

            $vehicule         = $reservation->vehicule;
            $date_reservation = $reservation->date_reservation;
            $horaire          = $reservation->horaire_reservation;
            $nom              = $reservation->nom;
            $prenom           = $reservation->prenom;

            
            $vehicule_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_vehicules WHERE nom = %s", $vehicule));

            // Récupérer la valeur 'validation_auto_time' pour le véhicule
            $validation_auto_time = $wpdb->get_var($wpdb->prepare(
                "SELECT validation_auto_time FROM $table_vehicules WHERE id = %d",
                $vehicule_id
            ));

            // Calculer le temps limite pour la réservation (ajouter validation_auto_time en heures à la date de création)
            $date_limite = strtotime($date_reservation) - ($validation_auto_time) * 3600; // 3600 = 1 heure en secondes

            // Vérifier si le temps limite est dépassé
            if ( time() > $date_limite )
            {
                // Si le temps limite est dépassé, changer le statut à "validée"
                $wpdb->update(
                    $table_reservations,
                    array('statut' => 'validée'), // Exemple : mettre le statut sur "validée"
                    array('id' => $reservation->id)
                );

                // Envoyer un email à l'utilisateur pour l'informer de la validation
                envoyer_mail_reservation($email, 'validée', $vehicule, $date_reservation, $horaire, $nom, $prenom);
            }
        }
    }

    // Fonction pour envoyer un email de rappel pour les réservations
    function rappel_reservation()
    {
        global $wpdb;
        $table_reservations = $wpdb->prefix . 'reservations';

        // Récupérer les réservations pour lesquelles le statut est "validée"
        $reservations = $wpdb->get_results("SELECT * FROM $table_reservations WHERE statut = 'validée'");

        foreach ($reservations as $reservation)
        {
            $email            = $reservation->email;
            $vehicule         = $reservation->vehicule;
            $date_reservation = $reservation->date_reservation;
            $horaire          = $reservation->horaire_reservation;
            $nom              = $reservation->nom;
            $prenom           = $reservation->prenom;

            // Calculer la différence de temps entre la réservation et maintenant (en secondes)
            $time_diff = strtotime($date_reservation) - time();

            // Si la réservation est dans 24 heures, envoyer un mail avec le statut 'J-1'
            if ($time_diff <= 86400 && $time_diff > 0) // 86400 secondes = 24 heures
            {
                // Envoyer un email à l'utilisateur pour l'informer de l'approche de la réservation
                envoyer_mail_reservation($email, 'J-1', $vehicule, $date_reservation, $horaire, $nom, $prenom);
            }
        }
    }


    


    // --- Afficher une alerte ---
    function afficherAlerte($message, $titre, $icone)
    {
        $message_escaped = esc_js($message);
        $title_escaped   = esc_js($titre);
        $icon_escaped    = esc_js($icone);

        echo "<script>console.log('Affichage alerte : $message_escaped');</script>";

        // Ajouter un check pour s'assurer que Swal est bien chargé
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: '$icon_escaped',
                            title: '$title_escaped',
                            text: '$message_escaped',
                            confirmButtonColor: '#3085d6'
                        });
                    } else {
                        console.error('SweetAlert2 n\'est pas chargé correctement');
                    }
                });
            </script>";
    }
?>