<?php
    // Fonction pour afficher la liste des utilisateurs
    function afficher_liste_utilisateurs()
    {
        if (!current_user_can('read'))
        {
            return;
        }

        // Rôles ciblés
        $roles_cibles = array('salarié' => 'Salarié', 'retraité' => 'Retraité', 'externe' => 'Externe');

        // Récupération du rôle sélectionné dans le dropdown
        $role_selectionne = isset($_GET['role_filtre']) ? sanitize_text_field($_GET['role_filtre']) : '';

        // Récupération du terme de recherche
        $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

        // Construction des arguments pour récupérer les utilisateurs
        $args = array(
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'meta_query' => array('relation' => 'OR')
        );

        // Ajout du filtre par rôle si un rôle a été sélectionné
        if (!empty($role_selectionne) && array_key_exists($role_selectionne, $roles_cibles))
        {
            $args['role'] = $role_selectionne;
        }
        else
        {
            $args['role__in'] = array_keys($roles_cibles); // Par défaut, on affiche tous les rôles ciblés
        }

        // Ajout de la recherche
        if (!empty($search_query))
        {
            // Recherche par "Prénom Nom"
            $args['meta_query'][] = array(
                'relation' => 'AND',
                array(
                    'key'     => 'first_name',
                    'value'   => explode(' ', $search_query)[0], // Premier mot
                    'compare' => 'LIKE'
                ),
                array(
                    'key'     => 'last_name',
                    'value'   => explode(' ', $search_query)[1] ?? '', // Deuxième mot (si présent)
                    'compare' => 'LIKE'
                ),
            );

            // Recherche par "Nom Prénom"
            $args['meta_query'][] = array(
                'relation' => 'AND',
                array(
                    'key'     => 'last_name',
                    'value'   => explode(' ', $search_query)[0], // Premier mot
                    'compare' => 'LIKE'
                ),
                array(
                    'key'     => 'first_name',
                    'value'   => explode(' ', $search_query)[1] ?? '', // Deuxième mot (si présent)
                    'compare' => 'LIKE'
                ),
            );
        }

        
        // Récupérer la table des véhicules
        global $wpdb;
        $table_vehicules = $wpdb->prefix . 'vehicules';

        // Récupérer les véhicules qui nécessitent une validation de capacité
        $vehicules_validants = $wpdb->get_results("SELECT id, nom FROM $table_vehicules WHERE validation_capacite = 1");
        $vehicule_selectionne = isset($_GET['vehicule_id']) ? intval($_GET['vehicule_id']) : ($vehicules_validants[0]->id ?? 0);


        $utilisateurs = get_users($args);


        ?>
        <div class="wrap">
            <h1>Liste des Utilisateurs</h1>

            <!-- Formulaire de recherche et de filtre -->
            <form method="GET">
                <input type="hidden" name="page" value="wp-reservations-users"> <!-- Garde la page active -->

                <!-- Barre de recherche -->
                <label for="search"><strong>Rechercher :</strong></label>
                <input type="text" name="search" id="search" value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>" placeholder="Nom / Prénom">

                <!-- Filtre par rôle -->
                <label for="role_filtre"><strong>Filtrer par rôle :</strong></label>
                <select name="role_filtre" id="role_filtre">
                    <option value="">Tous</option>
                    <?php foreach ($roles_cibles as $role => $label) : ?>
                        <option value="<?php echo esc_attr($role); ?>" <?php selected($role_selectionne, $role); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Filtre par véhicule -->
                <label for="vehicule_id"><strong>Afficher les autorisations par véhicule :</strong></label>
                <select name="vehicule_id" id="vehicule_id">
                    <?php foreach ($vehicules_validants as $vehicule) : ?>
                        <option value="<?php echo esc_attr($vehicule->id); ?>" <?php selected($vehicule_selectionne, $vehicule->id); ?>>
                            <?php echo esc_html($vehicule->nom); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button button-primary">Rechercher</button>
            </form>

            <br><br>

            <!-- Tableau des utilisateurs -->
            <?php if (empty($utilisateurs)) : ?>
                <p>Aucun utilisateur trouvé.</p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Validation de capacité</th>
                            <th>Email</th>
                            <th>Statut</th>
                            <th>Téléphone</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $utilisateur) : ?>
                            <tr>
                                <td><?php echo esc_html($utilisateur->last_name); ?></td>
                                <td><?php echo esc_html($utilisateur->first_name); ?></td>
                                <td>
                                    <?php
                                        $table_validations = $wpdb->prefix . 'validations';
                                        $validation = $wpdb->get_var($wpdb->prepare(
                                            "SELECT statut FROM $table_validations WHERE utilisateur_id = %d AND vehicule_id = %d",
                                            $utilisateur->ID,
                                            $vehicule_selectionne
                                        ));
                                        
                                        $texte = 'Non attribué';
                                        $classe_validation = 'validation-non';

                                        if ($validation === 'Validé')
                                        {
                                            $texte = 'Validé';
                                            $classe_validation = 'validation-valide';
                                        }
                                        elseif ($validation === 'Refusé')
                                        {
                                            $texte = 'Refusé';
                                            $classe_validation = 'validation-refusee';
                                        }

                                        echo '<span class="validation ' . $classe_validation . '">' . esc_html($texte) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo esc_html($utilisateur->user_email); ?></td>
                                <td><?php echo esc_html(implode(', ', $utilisateur->roles)); ?></td>
                                <td>
                                    <?php
                                    $telephone = get_user_meta($utilisateur->ID, 'telephone', true);
                                    echo !empty($telephone) ? esc_html($telephone) : 'Non renseigné';
                                    ?>
                                </td>
                                <td>
                                    <a href="?page=wp-reservations-user-details&user_id=<?php echo esc_attr($utilisateur->ID); ?>">Voir le détail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>

        <style>
            /* Style général des validations */
            .validation {
                padding: 5px 10px;
                font-weight: bold;
                border-radius: 5px;
                display: inline-block;
            }

            .validation-valide {
                background-color: #4CAF50;
                color: white;
            }

            .validation-refusee {
                background-color: #FF5733;
                color: white;
            }

            .validation-non {
                background-color: #ccc;
                color: black;
            }
        </style>

        <?php
    }



    function afficher_details_utilisateur()
    {
        if (!current_user_can('read') || !isset($_GET['user_id']))
        {
            return;
        }

        $user_id = intval($_GET['user_id']);
        $utilisateur = get_userdata($user_id);

        if (!$utilisateur)
        {
            echo '<div class="error"><p>Utilisateur non trouvé.</p></div>';
            return;
        }


        ?>
        <div class="wrap">
            <!-- INFORMATIONS GENERALES -->
            <h1>Détails de l'utilisateur</h1>
            <table class="widefat fixed striped">
                <tr><th>Nom</th><td><?php echo esc_html($utilisateur->last_name); ?></td></tr>
                <tr><th>Prénom</th><td><?php echo esc_html($utilisateur->first_name); ?></td></tr>
                <tr><th>Email</th><td><?php echo esc_html($utilisateur->user_email); ?></td></tr>
                <tr><th>Statut</th><td><?php echo esc_html(implode(', ', $utilisateur->roles)); ?></td></tr>
                <tr><th>Téléphone</th><td><?php echo esc_html(get_user_meta($user_id, 'telephone', true) ?: 'Non renseigné'); ?></td></tr>
            </table>


            <!-- HISTORIQUE DES POINTS -->
            <?php
                // Récupérer l'historique des points de l'utilisateur
                global $wpdb;
                $table_points    = $wpdb->prefix . 'points';
                $table_vehicules = $wpdb->prefix . 'vehicules';

                $points_historique = $wpdb->get_results($wpdb->prepare(
                    "SELECT p.points_utilisateur, v.nom AS nom_vehicule
                    FROM $table_points p
                    JOIN $table_vehicules v ON p.vehicule_id = v.id
                    WHERE p.utilisateur_id = %d
                    ORDER BY p.id DESC",
                    $user_id
                ));
            ?>

            <h2>Historique des points</h2>
            <?php if (empty($points_historique)) : ?>
                <p>Aucun point attribué.</p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Véhicule</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($points_historique as $point) : ?>
                            <tr>
                                <td><?php echo esc_html($point->nom_vehicule); ?></td>
                                <td><?php echo esc_html($point->points_utilisateur); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>


            <!-- VALIDATION DE CAPACITE -->
            <?php
                // Récupérer les véhicules nécessitant une validation de capacité
                $vehicules_a_valider = $wpdb->get_results(
                    "SELECT id, nom FROM $table_vehicules WHERE validation_capacite = 1"
                );

                
                // Récupérer les validations déjà enregistrées pour cet utilisateur
                $table_validations = $wpdb->prefix . 'validations';
                $validations_utilisateur = $wpdb->get_results($wpdb->prepare(
                    "SELECT vehicule_id, statut FROM $table_validations WHERE utilisateur_id = %d",
                    $user_id
                ));

                // Traitement du formulaire (si soumis)
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicule_id']) && isset($_POST['statut_validation']))
                {
                    $vehicule_id = intval($_POST['vehicule_id']);
                    $statut_validation = in_array($_POST['statut_validation'], ['Validé', 'Refusé']) ? $_POST['statut_validation'] : 'Refusé';

                    // Vérifier si une validation existe déjà
                    $exist = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_validations WHERE utilisateur_id = %d AND vehicule_id = %d",
                        $user_id,
                        $vehicule_id
                    ));

                    if ($exist)
                    {
                        // Mise à jour de la validation existante
                        $wpdb->update(
                            $table_validations,
                            ['statut' => $statut_validation],
                            ['utilisateur_id' => $user_id, 'vehicule_id' => $vehicule_id],
                            ['%s'],
                            ['%d', '%d']
                        );
                    }
                    else
                    {
                        // Insertion d'une nouvelle validation
                        $wpdb->insert(
                            $table_validations,
                            ['utilisateur_id' => $user_id, 'vehicule_id' => $vehicule_id, 'statut' => $statut_validation],
                            ['%d', '%d', '%s']
                        );
                    }

                    echo '<div class="updated"><p>Validation mise à jour avec succès.</p></div>';
                }
            ?>

            <?php
            // Vérifie si l'utilisateur n'a pas le rôle "AccueilVehicule"
            $current_user = wp_get_current_user();
            if (!in_array('accueil_vehicule', (array) $current_user->roles)) : ?>
                <h2>Validation de capacité</h2>
                <form method="POST">
                    <label for="vehicule_id"><strong>Sélectionner un véhicule :</strong></label>
                    <select name="vehicule_id" id="vehicule_id">
                        <?php foreach ($vehicules_a_valider as $vehicule) : ?>
                            <option value="<?php echo esc_attr($vehicule->id); ?>">
                                <?php echo esc_html($vehicule->nom); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label><input type="radio" name="statut_validation" value="Validé" checked> Valider</label>
                    <label><input type="radio" name="statut_validation" value="Refusé"> Refuser</label>

                    <button type="submit" class="button button-primary">Enregistrer</button>
                </form>
            <?php endif; ?>

            <h3>Validations existantes</h3>
            <?php if (empty($validations_utilisateur)) : ?>
                <p>Aucune validation enregistrée.</p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Véhicule</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($validations_utilisateur as $validation) : ?>
                            <tr>
                                <td><?php echo esc_html(array_column($vehicules_a_valider, 'nom', 'id')[$validation->vehicule_id] ?? 'Inconnu'); ?></td>
                                <td><?php echo esc_html($validation->statut); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>


            <!-- HISTORIQUE DES RESERVATIONS -->
            <?php 
                // Récupérer les réservations de l'utilisateur
                $table_reservations = $wpdb->prefix . 'reservations';
                $reservations = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_reservations WHERE utilisateur_id = %d ORDER BY date_reservation DESC",
                    $user_id
                ));
            ?>

            <h2>Historique des réservations</h2>
            <?php if (empty($reservations)) : ?>
                <p>Aucune réservation trouvée.</p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Véhicule</th>
                            <th>Date</th>
                            <th>Période</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation) : ?>
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
                            ?>
                            <tr>
                                <td><?php echo esc_html($reservation->vehicule); ?></td>
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
                                <td><?php echo esc_html($periode); ?></td>
                                <td>
                                    <?php echo esc_html($reservation->statut);?>
                                    <?php 
                                        // Afficher la date d'annulation si la réservation est annulée
                                        if ($reservation->statut === 'annulée' && !empty($reservation->date_annulation))
                                        {
                                            $date_annulation = DateTime::createFromFormat('Y-m-d H:i:s', $reservation->date_annulation);
                                            if ($date_annulation) 
                                            {
                                                $formatted_annulation = $formatter->format($date_annulation);
                                                echo ' le ' . esc_html($formatted_annulation);
                                            }
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>



            <!-- NOTE ADMINISTRATEUR -->
            <?php
                if (isset($_POST['sauvegarder_note_admin']) && current_user_can('manage_options'))
                {
                    $note = sanitize_textarea_field($_POST['note_admin'] ?? '');
                    update_user_meta($user_id, 'note_admin', $note);
                    echo '<div class="notice notice-success"><p>Note administrateur sauvegardée avec succès.</p></div>';
                }
                
            ?>

            <br>
            <h2>Note administrateur</h2>

            <form method="post">
                <textarea name="note_admin" rows="4" style="width: 100%;"><?php echo esc_textarea(get_user_meta($user_id, 'note_admin', true)); ?></textarea>
                <p>
                    <button type="submit" name="sauvegarder_note_admin" class="button button-primary">Enregistrer la note</button>
                </p>
            </form>
        </div>
        <br>
        <a href="?page=wp-reservations-users" class="button">Retour aux utilisateurs</a>
        <?php
    }
?>