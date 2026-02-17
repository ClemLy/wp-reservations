<?php
    function afficher_liste_reservations()
    {
        if (!current_user_can('read'))
        {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'reservations';

        // Vérification de l'existence de la table
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name)
        {
            echo '<div class="error"><p>La table des réservations n\'existe pas.</p></div>';
            return;
        }

        // Récupération de la date sélectionnée ou date du jour par défaut
        $date_selectionnee = isset($_GET['date_reservation']) ? $_GET['date_reservation'] : date('Y-m-d');

        // Calcul des dates précédente et suivante
        $date_precedente = date('Y-m-d', strtotime($date_selectionnee . ' -1 day'));
        $date_suivante   = date('Y-m-d', strtotime($date_selectionnee . ' +1 day'));

        

        // Récupération des véhicules distincts dans la base de données
        $vehicules = $wpdb->get_col("SELECT DISTINCT vehicule FROM $table_name ORDER BY vehicule ASC");

        // Récupération du véhicule sélectionné (par défaut, "Tous")
        $vehicule_selectionne = isset($_GET['vehicule']) ? sanitize_text_field($_GET['vehicule']) : '';

        // Construction de la requête SQL avec filtre sur le véhicule
        $sql = "SELECT id, nom, prenom, telephone, vehicule, notes, statut FROM $table_name WHERE DATE(date_reservation) = %s";
        $params = [$date_selectionnee];

        if (!empty($vehicule_selectionne))
        {
            $sql .= " AND vehicule = %s";
            $params[] = $vehicule_selectionne;
        }

        $sql .= " ORDER BY 
            FIELD(statut, 'validée', 'en attente', 'refusée'),
            date_reservation DESC"; 

        $reservations = $wpdb->get_results($wpdb->prepare($sql, ...$params));

        ?>
        <div class="wrap">
            <h1>Liste des Réservations</h1>

            <!-- Sélecteur de date -->
            <form method="GET" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">

                <!-- Flèche précédente -->
                <button type="submit" name="date_reservation" value="<?php echo esc_attr($date_precedente); ?>" class="button" onclick="disableDateInput()">← Jour précédent</button>

                <!-- Sélecteur de date -->
                <input type="date" id="date_reservation" name="date_reservation" value="<?php echo esc_attr($date_selectionnee); ?>" onchange="this.form.submit()">

                <!-- Flèche suivante -->
                <button type="submit" name="date_reservation" value="<?php echo esc_attr($date_suivante); ?>" class="button" onclick="disableDateInput()">Jour suivant →</button>

                <br><br>

                <!-- Sélecteur de véhicule -->
                <label for="vehicule">Véhicule :</label>
                <select name="vehicule" id="vehicule" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <?php foreach ($vehicules as $vehicule): ?>
                        <option value="<?php echo esc_attr($vehicule); ?>" <?php selected($vehicule_selectionne, $vehicule); ?>>
                            <?php echo esc_html($vehicule); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>


            <br>

            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Véhicule</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Téléphone</th>
                        <th>Commentaires</th>
                        <th>Détails</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($reservations)
                    {
                        foreach ($reservations as $reservation)
                        {
                            // Vérifie si la réservation a une note
                            $commentaires = empty($reservation->notes) ? 'Aucune note ❌ ' : '⚠️ Note présente';

                            // Détermine le statut avec une classe CSS
                            $statut = esc_html($reservation->statut);
                            $classe_statut = '';
                            switch ($statut)
                            {
                                case 'validée':
                                    $classe_statut = 'statut-valide';
                                    break;
                                case 'refusée':
                                    $classe_statut = 'statut-refusee';
                                    break;
                                case 'en attente':
                                    $classe_statut = 'statut-attente';
                                    break;
                                case 'annulée':
                                    $classe_statut = 'statut-refusee';
                            }
                            
                            echo '<tr>';
                            echo '<td>' . esc_html($reservation->vehicule) . '</td>';
                            echo '<td>' . esc_html($reservation->nom) . '</td>';
                            echo '<td>' . esc_html($reservation->prenom) . '</td>';
                            echo '<td>' . esc_html($reservation->telephone) . '</td>';
                            echo '<td>' . esc_html($commentaires) . '</td>';
                            echo '<td>
                                    <a href="?page=detail_reservation&id=' . intval($reservation->id) . '&date_reservation=' . esc_attr($date_selectionnee) . '">Voir le détail</a>
                                </td>';
                            echo '<td>
                                    <span class="statut ' . $classe_statut . '">' . $statut . '</span>
                                </td>';
                            echo '</tr>';
                        }
                    }
                    else
                    {
                        echo '<tr><td colspan="6">Aucune réservation trouvée pour cette date.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <script>
            // Script pour soumettre le formulaire automatiquement lors du changement de date
            document.getElementById('date_reservation').addEventListener('change', function() {
                this.form.submit();
            });

            // Fonction pour désactiver le champ de date lorsque l'utilisateur clique sur les boutons de navigation
            function disableDateInput() {
                document.getElementById('date_reservation').disabled = true;
            }
        </script>

        <style>
            /* Style général des statuts */
            .statut {
                padding: 5px 10px;
                font-weight: bold;
                border-radius: 5px;
            }

            /* Statut validé (vert) */
            .statut-valide {
                background-color: #4CAF50;
                color: white;
            }

            /* Statut refusé (rouge) */
            .statut-refusee {
                background-color: #FF5733;
                color: white;
            }

            /* Statut en attente (jaune) */
            .statut-attente {
                background-color: #FFC107;
                color: black;
            }
        </style>

        <?php
    }








    function afficher_detail_reservation()
    {
        if (!current_user_can('read'))
        {
            return;
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservations';
    
        // Récupération de l'ID de la réservation
        $reservation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
        if ($reservation_id <= 0)
        {
            echo '<div class="error"><p>ID de réservation invalide.</p></div>';
            return;
        }
    
        // Récupération des détails de la réservation dans la base de données
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $reservation_id
        ));
    
        if (!$reservation)
        {
            echo '<div class="error"><p>Réservation introuvable.</p></div>';
            return;
        }


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




        // Vérifier si un commentaire accueil existe déjà
        $commentaire_accueil = get_post_meta($reservation_id, 'commentaire_accueil', true);

        // Valeur par défaut si vide
        if (empty($commentaire_accueil))
        {
            $commentaire_accueil = "Trousseau de clefs numéro :\nChèque de caution : Non OK";
        }


        // Traitement du commentaire accueil si soumis
        if (isset($_POST['save_commentaire_accueil']))
        {
            $nouveau_commentaire = sanitize_textarea_field($_POST['commentaire_accueil']);
            update_post_meta($reservation_id, 'commentaire_accueil', $nouveau_commentaire);
            $commentaire_accueil = $nouveau_commentaire;
            echo '<div class="updated"><p>Commentaire enregistré avec succès.</p></div>';
        }


        

        // Traitement de la suppression de la réservation
        if (isset($_POST['annuler_reservation']) && !empty($_POST['reservation_id']))
        {
            $reservation_id = intval($_POST['reservation_id']);
            $table_reservations = $wpdb->prefix . 'reservations';
            $table_vehicules    = $wpdb->prefix . 'vehicules';
            $table_points       = $wpdb->prefix . 'points';

            $motif_annulation = !empty($_POST['motif_annulation']) ? sanitize_text_field($_POST['motif_annulation']) : null;

            // Récupérer les points engagés pour la réservation
            $points_rembourses = $wpdb->get_var($wpdb->prepare(
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

            if ($reservation)
            {
                $utilisateur_id = $reservation->utilisateur_id;
                $vehicule_id    = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_vehicules WHERE nom = %s", $reservation->vehicule));

                // Rembourser les points à l'utilisateur
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_points 
                    SET points_utilisateur = points_utilisateur - %d
                    WHERE utilisateur_id = %d AND vehicule_id = %d",
                    $points_rembourses, $utilisateur_id, $vehicule_id
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

                    envoyer_mail_reservation($email_utilisateur, 'annulée', $vehicule, $date_reservation, $horaire, $nom, $prenom, $motif_annulation);
                }

                // Recharge la page pour voir les mises à jour
                wp_redirect($_SERVER['REQUEST_URI']);
                echo '<div class="updated"><p>Réservation annulée avec succès.</p></div>';
                exit;
            }
        }



        

        /* --- Affichage de la page --- */
        ?>
        <div class="wrap">
            <h1>Détail de la réservation</h1>

            <!-- 🚗 Véhicule -->
            <h2>Véhicule : <?= esc_html($reservation->vehicule); ?></h2>
            <br>

            <!-- 👤 Utilisateur -->
            <h2>Informations utilisateur</h2>
            <table class="widefat fixed striped">
                <tr>
                    <th><strong>Nom</strong></th>
                    <th><strong>Prénom</strong></th>
                    <th><strong>Email</strong></th>
                    <th><strong>Téléphone</strong></th>
                </tr>
                <tr>
                    <td><?= esc_html($reservation->nom); ?></td>
                    <td><?= esc_html($reservation->prenom); ?></td></th>
                    <td><?= esc_html($reservation->email); ?></td></th>
                    <td><?= esc_html($reservation->telephone); ?></td></th>
                </tr>
            </table>

            <!-- 📝 Note utilisateur -->
            <div style="margin-top: 1em; padding: 1em; background: #f8f8f8; border-left: 4px solid #0073aa;">
                <strong>Note de l'utilisateur :</strong><br>
                <?= esc_html(!empty($reservation->notes) ? $reservation->notes : 'Aucune note'); ?>
            </div>

            <br>

            <!-- 📆 Réservation -->
            <h2>Détails de la réservation</h2>
            <table class="widefat fixed striped">
                <tr>
                    <th><strong>Date de réservation</strong></th>
                    <th><strong>Période</strong></th>
                    <th><strong>Statut</strong></th>
                </tr>
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
                    <td><?= esc_html($periode); ?></td>
                    <td><?= esc_html($reservation->statut); ?></td>
                </tr>
            </table>
            <br>

            <!-- 🧾 Commentaire Accueil + Note admin -->
            <h2>Suivi administratif</h2>
            <table class="widefat fixed striped">
                <tr>
                    <th><strong>Commentaire Accueil</strong></th>
                    <th><strong>Note Administrateur</strong></th>
                </tr>
                <tr>
                    <td>
                        <form method="POST">
                            <textarea id="commentaire_accueil" name="commentaire_accueil" rows="4" style="width: 100%;"><?= esc_textarea($commentaire_accueil); ?></textarea>
                            <br>
                            <button type="submit" name="save_commentaire_accueil" class="button button-primary">Enregistrer le commentaire</button>
                        </form>
                    </td>
                    <td>
                        <?= esc_html(get_user_meta($reservation->utilisateur_id, 'note_admin', true) ?: 'Aucune note enregistrée.'); ?>
                    </td>
                </tr>
            </table>

            <br>

            <!-- ❌ Annulation -->
            <?php if (!in_array('accueil_vehicule', (array) wp_get_current_user()->roles)) : ?>
                <h2>Annulation de la réservation</h2>
                <form method="post">
                    <input type="hidden" name="reservation_id" value="<?= esc_attr($reservation->id); ?>">
                    <label for="motif_annulation"><strong>Motif (facultatif) :</strong></label><br>
                    <input type="text" name="motif_annulation" style="width: 60%;" placeholder="Indiquez un motif si besoin">
                    <br><br>
                    <button type="submit" name="annuler_reservation" class="button button-primary" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette réservation ? Cette action est irréversible.');">
                        Annuler définitivement
                    </button>
                </form>
            <?php endif; ?>

            <br><br>
            <a href="?page=wp-reservations&date_reservation=<?= esc_attr($_GET['date_reservation']); ?>" class="button">← Retour à la liste</a>
        </div>
    <?php
    }
?>