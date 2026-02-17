<?php
    // Empêche l'accès direct
    defined('ABSPATH') or die('No script kiddies please!');


    // Formulaire de réservation
    function afficher_formulaire_reservation($vehicule, $current_user)
    {
        global $wpdb;
        
        // Récupérer les dates réservées pour ce véhicule
        $table_reservations = $wpdb->prefix . 'reservations';
        $table_vehicules    = $wpdb->prefix . 'vehicules';
        $table_points       = $wpdb->prefix . 'points';

        $reservations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date_reservation, horaire_reservation, statut FROM $table_reservations WHERE vehicule = %s",
                $vehicule->nom
            )
        );
        
        // Convertir les données en un format utilisable en JS
        $dates_reservees = array_values(array_map(function($reservation) {
            return $reservation->date_reservation . ' ' . $reservation->horaire_reservation . ' ' . $reservation->statut;
        }, array_filter($reservations, function($res) {
            return $res->statut !== 'annulée' && $res->statut !== 'refusée';
        })));
        


        // Récupérer les jours d'indisponibilité du véhicule
        $jours_indisponibles = !empty($vehicule->jours_indisponibilites) ? explode(',', $vehicule->jours_indisponibilites) : [];


        // Gestion de l'annulation de la réservation si le bouton est cliqué
        if (isset($_POST['annuler_reservation']) && !empty($_POST['reservation_id']))
        {
            $reservation_id = intval($_POST['reservation_id']);
            
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
                $reservation_id
            ));

            // Récupérer l'utilisateur et le véhicule associés à cette réservation
            $reservation = $wpdb->get_row($wpdb->prepare(
                "SELECT utilisateur_id, email, vehicule, date_reservation, horaire_reservation, nom, prenom FROM $table_reservations WHERE id = %d",
                $reservation_id
            ));

            // Récupérer l'ID de l'utilisateur et du véhicule
            $utilisateur_id = $reservation->utilisateur_id;
            $vehicule_id    = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_vehicules WHERE nom = %s", $reservation->vehicule));

            // Retirer les points de l'utilisateur
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_points 
                SET points_utilisateur = points_utilisateur - %d
                WHERE utilisateur_id = %d AND vehicule_id = %d",
                $points_annules, $utilisateur_id, $vehicule_id
            ));

            // Rendre le statut annulée
            $wpdb->update(
                $table_reservations,
                array(
                    'statut' => 'annulée',
                    'date_annulation' => date('Y-m-d') // Ajout de la date actuelle
                ),
                array('id' => $reservation_id)
            );

            // Récupérer l'e-mail de l'utilisateur
            $email_utilisateur = $reservation->email;

            // Vérifier si l'email est récupéré avant d'envoyer l'e-mail
            if (!empty($email_utilisateur))
            {
                $vehicule         = $reservation->vehicule;
                $date_reservation = $reservation->date_reservation;
                $horaire          = $reservation->horaire_reservation;
                $nom              = $reservation->nom;  
                $prenom           = $reservation->prenom;

                envoyer_mail_reservation($email_utilisateur, 'annulée', $vehicule, $date_reservation, $horaire, $nom, $prenom);
            }

            // Recharge la page pour voir les mises à jour
            wp_redirect($_SERVER['REQUEST_URI']);
            exit;
        }


        ob_start();

        // Récupérer l'URL actuelle pour la redirection après la déconnexion
        $current_url = home_url($_SERVER['REQUEST_URI']);
        ?>
        <div class="reservation-container">
            <div class="reservation-contenu">
                <!-- Afficher les informations du véhicule si véhicule disponible -->
                <?php if ($vehicule->statut === 'disponible') : ?>
                    <!-- Calendrier de réservation -->
                    <div class="calendrier-reservation">
                        <h3>Choisir une date pour <?= esc_html($vehicule->nom); ?></h3>
                        <div id="calendar-<?= esc_attr($vehicule->nom); ?>"
                            data-reserved-dates='<?= json_encode($dates_reservees); ?>'
                            data-unavailable-dates='<?= json_encode($jours_indisponibles); ?>'>
                        </div>

                        <div class="legende-calendrier">   
                            <div class="legende-item">
                                <span class="block couleur-verte"></span>
                                <span class="texte-legende">Libre</span>
                            </div>
                            <div class="legende-item">
                                <span class="block couleur-rouge"></span>
                                <span class="texte-legende">Réservé</span>
                            </div>
                            <div class="legende-item">
                                <span class="block couleur-orange"></span>
                                <span class="texte-legende">En attente</span>
                            </div>
                            <div class="legende-item">
                                <span class="block couleur-violette"></span>
                                <span class="texte-legende">Indisponible</span>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire de réservation -->
                    <?php if (is_user_logged_in()) : ?>
                    <div class="formulaire-reservation">
                        <h3>Réservez <?= esc_html($vehicule->nom); ?></h3>

                        <?php
                        // Générer l'URL de déconnexion avec redirection vers la page actuelle
                        $logout_url = wp_logout_url($current_url);

                        echo '<a href="' . esc_url($logout_url) . '" class="logout-link">Se déconnecter</a>';

                        // Vérifier si l'utilisateur est connecté pour activer/désactiver le formulaire
                        $form_disabled = !is_user_logged_in() ? 'disabled' : '';

                        // Remplir automatiquement le formulaire avec les informations de l'utilisateur connecté
                        $nom       = !empty($current_user->last_name) ? $current_user->last_name : '';
                        $prenom    = !empty($current_user->first_name) ? $current_user->first_name : '';
                        $email     = !empty($current_user->user_email) ? $current_user->user_email : '';
                        $telephone = !empty($current_user->telephone) ? $current_user->telephone : '';

                        // Afficher le formulaire avec ou sans champ activé selon la connexion
                        echo '<form class="form-plugin-reservation" method="POST" action="">
                                ' . wp_nonce_field('reservation_action', 'reservation_nonce') . '

                                <input type="hidden" name="vehicule" value="' . esc_attr($vehicule->nom) . '">
                                
                                <label for="nom">Nom</label>
                                <input type="text" name="nom" value="' . esc_attr($nom) . '" readonly required>

                                <label for="prenom">Prénom</label>
                                <input type="text" name="prenom" value="' . esc_attr($prenom) . '" readonly required>

                                <label for="email">Email</label>
                                <input type="email" name="email" value="' . esc_attr($email) . '" readonly required>

                                <label for="telephone">Téléphone</label>
                                <input type="tel" name="telephone" value="' . esc_attr($telephone) . '" ' . $form_disabled . ' required>

                                <label for="notes">Notes supplémentaires</label>
                                <textarea name="notes" rows="4" ' . $form_disabled . '></textarea>

                                <label for="date_reservation-' . esc_attr($vehicule->nom) . '">Date sélectionnée</label>
                                <span id="display_date-'. esc_attr($vehicule->nom) . '">Aucune date sélectionnée</span>
                                <input type="hidden" name="date_reservation" id="date_reservation-'. esc_attr($vehicule->nom) . '"' . $form_disabled . '>

                                <label for="horaire_reservation-' . esc_attr($vehicule->nom) . '">Horaire sélectionné</label>
                                <select name="horaire_reservation" id="horaire_reservation-' . esc_attr($vehicule->nom) . '" ' . $form_disabled . '>

                                    ' . (!empty($vehicule->start_time_full_day) && !empty($vehicule->end_time_full_day) ?
                                        '<option value="full_day">Toute la journée (' . esc_html(date('H:i', strtotime($vehicule->start_time_full_day))) . ' - ' . esc_html(date('H:i', strtotime($vehicule->end_time_full_day))) . ')</option>' : '') . '

                                    ' . (!empty($vehicule->start_time_half_day) && !empty($vehicule->end_time_half_day) ?
                                        '<option value="half_day_morning">Matin (' . esc_html(date('H:i', strtotime($vehicule->start_time_half_day))) . ' - ' . esc_html(date('H:i', strtotime($vehicule->end_time_half_day))) . ')</option>' : '') . '

                                    ' . (!empty($vehicule->start_time_half_day2) && !empty($vehicule->end_time_half_day2) ?
                                        '<option value="half_day_afternoon">Après-midi (' . esc_html(date('H:i', strtotime($vehicule->start_time_half_day2))) . ' - ' . esc_html(date('H:i', strtotime($vehicule->end_time_half_day2))) . ')</option>' : '') . '

                                </select>

                                <button type="submit" ' . $form_disabled . '>Valider la demande</button>
                            </form>';
                        ?>
                    </div>
            </div>

                    
                <!-- Afficher les réservations à annuler -->
                <?php
                // Vérifier si l'utilisateur a des réservations actives
                $user_id = get_current_user_id();
                $table_reservations = $wpdb->prefix . 'reservations';

                $reservations_utilisateur = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, date_reservation, horaire_reservation, statut 
                        FROM $table_reservations 
                        WHERE utilisateur_id = %d AND vehicule = %s
                        ORDER BY date_reservation ASC",
                        $user_id,
                        $vehicule->nom
                    )
                );

                if (!empty($reservations_utilisateur)) : ?>
                    <div class="tableau-reservations">
                        <h3>Vos réservations pour <?= esc_html($vehicule->nom); ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Horaire</th>
                                    <th>Statut</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations_utilisateur as $reservation) : ?>
                                    <tr>
                                        <td>
                                            <?php 
                                                $date_reservation = DateTime::createFromFormat('Y-m-d', $reservation->date_reservation);
                                                
                                                if ($date_reservation)
                                                {
                                                    // Créez un formateur de date pour le français
                                                    $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                                                    $formatted_date = $formatter->format($date_reservation);
                                                    echo esc_html($formatted_date);
                                                }
                                                else
                                                {
                                                    echo 'Date invalide';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                // Déterminer l'heure de la réservation
                                                $horaire_reservation = $reservation->horaire_reservation;;

                                                // Déterminer si c'est la matinée, l'après-midi ou la journée entière
                                                $periode = '';
                                                if ($horaire_reservation == "half_day_morning" || $horaire_reservation == "half_day_morning_weekend")
                                                {
                                                    $periode = 'Matinée';
                                                }
                                                elseif ($horaire_reservation == "half_day_afternoon" || $horaire_reservation == "half_day_afternoon_weekend")
                                                {
                                                    $periode = 'Après-midi';
                                                }
                                                else
                                                {
                                                    $periode = 'Journée entière';
                                                }

                                                echo esc_html($periode);
                                            ?>
                                        </td>
                                        <td><?= esc_html($reservation->statut); ?></td>
                                        <td>
                                            <?php
                                                $aujourdhui = new DateTime();
                                                
                                                $date_reservation_obj = DateTime::createFromFormat('Y-m-d', $reservation->date_reservation);
                                                $peut_annuler = in_array($reservation->statut, ['validée', 'en attente']) && $date_reservation_obj >= $aujourdhui;

                                                if ($peut_annuler) :
                                            ?>
                                                <form class="formulaire-annulation" method="POST">
                                                    <input type="hidden" name="reservation_id" value="<?= esc_attr($reservation->id); ?>">
                                                    <button type="submit" name="annuler_reservation" class="annuler-btn" onclick="return confirm('Êtes-vous sûr de vouloir annuler votre réservation ? Cette action est irréversible.');">Annuler</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Si l'utilisateur n'est pas connecté -->
                <?php else : ?>
                    <div class="formulaire-reservation">
                        <p>Vous devez être connecté pour réserver.</p>
                        <a href="<?= esc_url(home_url('/connexion?redirect_to=' . urlencode($current_url))); ?>" class="button">Se connecter</a>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <!-- Message si le véhicule est indisponible -->
                <div class="vehicule-indisponible">
                    <p style="color: red; font-weight: bold;">Véhicule Indisponible pour le moment</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
?>