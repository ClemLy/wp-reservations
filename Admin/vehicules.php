<?php
    // Fonction pour afficher la page de gestion des véhicules
    function afficher_vehicules()
    {
        // Vérifie si l'utilisateur a les permissions pour gérer les véhicules
        if (!current_user_can('manage_options'))
        {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'vehicules';

        // Récupérer tous les véhicules
        $vehicules = $wpdb->get_results("SELECT * FROM $table_name");

        ?>
        <div class="wrap">
            <h1>Gestion des Véhicules</h1>

            <h2>Liste des Véhicules</h2>
            <table class="widefat fixed">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Shortcode</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        // Affichage des véhicules
                        if ($vehicules)
                        {
                            foreach ($vehicules as $vehicule)
                            {
                                echo '<tr>';
                                echo '<td>' . esc_html($vehicule->id) . '</td>';
                                echo '<td>' . esc_html($vehicule->nom) . '</td>';
                                echo '<td>[vehicule nom="'. esc_html($vehicule->nom) .'"]</td>';
                                echo '<td>';
                                    // Lien pour modifier
                                    echo '<a href="' . esc_url(admin_url('admin.php?page=gestion_vehicule&id=' . $vehicule->id)) . '">Modifier</a> | ';
                                    // Lien pour supprimer
                                    echo '<a href="'. esc_url(admin_url('admin.php?action=supprimer_vehicule&id=' . $vehicule->id . '&nonce=' . wp_create_nonce('supprimer_vehicule_' . $vehicule->id))). '" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer ce véhicule ?\')">Supprimer</a>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        }
                        else
                        {
                            echo '<tr><td colspan="3">Aucun véhicule trouvé.</td></tr>';
                        }
                    ?>
                </tbody>
            </table>

            <br />
            <a href="<?php echo esc_url(admin_url('admin.php?page=gestion_vehicule')); ?>" class="button button-primary">Créer un Nouveau Véhicule</a>
        </div>
        <?php
    }




    /* ----- GERER UN VEHICULE ----- */

    // Fonction pour afficher le formulaire de création ou de modification du véhicule
    function afficher_formulaire_vehicule()
    {
        // Vérifie si l'utilisateur a les permissions pour gérer les véhicules
        if (!current_user_can('manage_options'))
        {
            return;
        }

        // Vérifier si un ID de véhicule est passé pour la modification
        $id_vehicule = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Récupérer les informations du véhicule si un ID est passé
        $vehicule = null;
        if ($id_vehicule > 0)
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'vehicules';
            $vehicule = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id_vehicule");

            // Si le véhicule n'existe pas, retourner
            if (!$vehicule)
            {
                echo '<div class="error"><p>Véhicule non trouvé.</p></div>';
                return;
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo $id_vehicule > 0 ? 'Modifier le Véhicule' : 'Ajouter un Nouveau Véhicule'; ?></h1>

            <?php
                // Vérification de l'envoi du formulaire
                if (isset($_POST['submit']))
                {
                    // Traitement du formulaire d'ajout ou de modification du véhicule
                    if ($id_vehicule > 0)
                    {
                        modifier_vehicule($id_vehicule);
                    }
                    else
                    {
                        ajouter_vehicule();
                    }
                }
            ?>

            <!-- Formulaire d'ajout ou de modification d'un véhicule -->
            <form method="post" action="">
                <!-- INFORMATIONS GENERALES -->
                <div class="form-group">
                    <label for="nom_vehicule">Nom du Véhicule</label>
                    <input type="text" id="nom_vehicule" name="nom_vehicule" value="<?php echo esc_attr($vehicule->nom ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description_vehicule">Description</label>
                    <textarea id="description_vehicule" name="description_vehicule" required><?php echo esc_textarea(stripslashes($vehicule->description ?? '')); ?></textarea>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="validation_capacite" name="validation_capacite" value="1" <?php checked($vehicule->validation_capacite ?? '', '1'); ?>>
                    <label for="validation_capacite">Ce véhicule nécessite une validation de prise en main</label>
                </div>

                <div class="form-group">
                    <label for="statut_vehicule">Statut</label>
                    <select name="statut_vehicule" id="statut_vehicule" required>
                        <option value="disponible" <?php selected($vehicule->statut ?? '', 'disponible'); ?>>Disponible</option>
                        <option value="indisponible" <?php selected($vehicule->statut ?? '', 'indisponible'); ?>>Indisponible</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="image_vehicule">Image</label>
                    <input type="text" id="image_vehicule" name="image_vehicule" value="<?php echo esc_attr($vehicule->image ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email_admin">Pour info : Email de l'administrateur du véhicule</label>
                    <input type="email" id="email_admin" name="email_admin" value="<?php echo esc_attr($vehicule->email_admin ?? ''); ?>">
                </div>

                
                <div class="form-group">
                <!--    <label for="email_reservation">Email de Réservation</label>-->
                    <input type="hidden" id="email_reservation" name="email_reservation" value="<?php echo esc_attr($vehicule->email_reservation ?? ''); ?>">
                </div>

                <hr>

                <!-- INFORMATIONS RESERVATION -->
                <div class="form-group points-group">
                    <div>
                        <label for="points_halfday">Points Demie-journée</label>
                        <input type="number" id="points_halfday" name="points_halfday" value="<?php echo esc_attr($vehicule->points_halfday ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="points_fullday">Points Journée Complète</label>
                        <input type="number" id="points_fullday" name="points_fullday" value="<?php echo esc_attr($vehicule->points_fullday ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="points_halfweek">Points Demie-journée (Week-end)</label>
                        <input type="number" id="points_halfweek" name="points_halfweek" value="<?php echo esc_attr($vehicule->points_halfweek ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="points_fullweek">Points Journée Complète (Week-end)</label>
                        <input type="number" id="points_fullweek" name="points_fullweek" value="<?php echo esc_attr($vehicule->points_fullweek ?? ''); ?>" required>
                    </div>
                </div>

                <div class="schedule-group">
                    <div class="schedule-column">
                        <h3>Journée complète</h3>
                        <label for="start_time_full_day">Début</label>
                        <input type="time" id="start_time_full_day" name="start_time_full_day" value="<?php echo esc_attr($vehicule->start_time_full_day ?? ''); ?>">
                        <label for="end_time_full_day">Fin</label>
                        <input type="time" id="end_time_full_day" name="end_time_full_day" value="<?php echo esc_attr($vehicule->end_time_full_day ?? ''); ?>">
                    </div>

                    <div class="schedule-column">
                        <h3>Matin</h3>
                        <label for="start_time_half_day">Début</label>
                        <input type="time" id="start_time_half_day" name="start_time_half_day" value="<?php echo esc_attr($vehicule->start_time_half_day ?? ''); ?>">
                        <label for="end_time_half_day">Fin</label>
                        <input type="time" id="end_time_half_day" name="end_time_half_day" value="<?php echo esc_attr($vehicule->end_time_half_day ?? ''); ?>">
                    </div>

                    <div class="schedule-column">
                        <h3>Après-midi</h3>
                        <label for="start_time_half_day2">Début</label>
                        <input type="time" id="start_time_half_day2" name="start_time_half_day2" value="<?php echo esc_attr($vehicule->start_time_half_day2 ?? ''); ?>">
                        <label for="end_time_half_day2">Fin</label>
                        <input type="time" id="end_time_half_day2" name="end_time_half_day2" value="<?php echo esc_attr($vehicule->end_time_half_day2 ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="jours_indisponibilites">Jours d'Indisponibilités</label>

                    <div id="jours-indisponibilites-container">
                        <div class="jours-indisponibilites-header">
                            <span>Date début</span>
                            <span>Date fin</span>
                        </div>

                        <?php 
                        // Récupération des jours d'indisponibilités sous forme de tableau
                        $jours_indisponibilites = !empty($vehicule->jours_indisponibilites) ? explode(',', $vehicule->jours_indisponibilites) : [];
                        
                        // On regroupe les dates en intervalles (début - fin)
                        $intervals = [];
                        $prev_date = null;

                        foreach ($jours_indisponibilites as $date) {
                            $date = trim($date);
                            if ($prev_date === null || (strtotime($date) != strtotime(end($intervals)['fin']) + 86400)) {
                                $intervals[] = ['debut' => $date, 'fin' => $date];
                            } else {
                                $intervals[count($intervals) - 1]['fin'] = $date;
                            }
                            $prev_date = $date;
                        }

                        // Affichage des intervalles dans le formulaire
                        foreach ($intervals as $interval) :
                        ?>
                            <div class="jours-indisponibilites-item">
                                <input type="date" name="jours_indisponibilites_debut[]" value="<?php echo esc_attr($interval['debut']); ?>">
                                <input type="date" name="jours_indisponibilites_fin[]" value="<?php echo esc_attr($interval['fin']); ?>">
                                <button type="button" class="remove-interval">❌</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add-interval">➕ Ajouter une période</button>
                </div><br>

                <div class="form-group">
                    <label for="jours_feries">Jours Fériés</label>
                    <div id="jours-feries-container">
                        <?php 
                            // Jours fériés par défaut
                            $jours_feries_defaut = ['2025-05-01', '2025-05-08', '2025-07-14', '2025-08-15', '2025-12-31', '2026-01-01']; // Jour de l'an, Fête du Travail, Armistice, 14 juillet, Assomption, Réveillon

                            // Récupérer les jours fériés existants
                            $jours_feries_existants = !empty($vehicule->jours_feries) ? explode(',', $vehicule->jours_feries) : [];
                            
                            // Fusionner et supprimer les doublons
                            $jours_feries = array_unique(array_merge($jours_feries_defaut, $jours_feries_existants));

                            
                            // Afficher chaque date sous forme de champ input
                            foreach ($jours_feries as $date) :
                        ?>
                            <div class="jours-feries-item">
                                <input type="date" name="jours_feries[]" value="<?php echo esc_attr(trim($date)); ?>">
                                <button type="button" class="remove-date">❌</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add-date">➕ Ajouter une date</button>
                </div><br>

                <div class="form-group">
                    <label>Permissions par jour</label>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Jour</th>
                                <th>Salarié</th>
                                <th>Retraité</th>
                                <th>Externe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                            $roles = ['salarie', 'retraite', 'externe'];

                            // Récupérer les permissions existantes depuis la base de données (si elles existent)
                            $permissions = !empty($vehicule->permissions_reservation) ? json_decode($vehicule->permissions_reservation, true) : [];

                            foreach ($jours as $jour) :
                            ?>
                                <tr>
                                    <td><strong><?php echo $jour; ?></strong></td>
                                    <?php foreach ($roles as $role) :
                                        // Vérifie si la case doit être cochée par défaut
                                        $checked = isset($permissions[$jour][$role]) ? 'checked' : '';
                                    ?>
                                        <td>
                                            <input type="checkbox" name="permissions_reservation[<?php echo $jour; ?>][<?php echo $role; ?>]" value="1" <?php echo $checked; ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div><br>

                <div class="form-group">
                    <label>Réservation par créneaux</label>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Matinée</th>
                                <th>Après-Midi</th>
                                <th>Journée Entière</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                            $periodes = ['morning', 'afternoon', 'fullday'];

                            // Récupérer les permissions existantes depuis la base de données (si elles existent)
                            $permissions = !empty($vehicule->permissions_creneaux) ? json_decode($vehicule->permissions_creneaux, true) : [];

                            foreach ($jours as $jour) :
                            ?>
                                <tr>
                                    <td><strong><?php echo $jour; ?></strong></td>
                                    <?php foreach ($periodes as $periode) :
                                        // Vérifie si la case doit être cochée par défaut
                                        $checked = isset($permissions[$jour][$periode]) ? 'checked' : '';
                                    ?>
                                        <td>
                                            <input type="checkbox" name="permissions_creneaux[<?php echo $jour; ?>][<?php echo $periode; ?>]" value="1" <?php echo $checked; ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-group">
                    <label for="message_creneaux">Message affiché pour les personnes essayant de réserver sur un créneau non réservable (Tableau au dessus)</label>
                    <input type="text" id="message_creneaux" name="message_creneaux" value="<?php echo esc_attr(stripslashes($vehicule->message_creneaux ?? '')); ?>" required>
                </div><br><br>


                <div class="form-group">
                    <label for="validation_auto_time">Délai (en heures) avant la clôture de validation de réservation automatique</label>
                    <input type="number" id="validation_auto_time" name="validation_auto_time" value="<?php echo esc_attr($vehicule->validation_auto_time ?? ''); ?>" required>
                </div>

                <hr>

                <!-- EMAILS TRANSACTIONNELS -->

                <h4>Légende des variables disponibles dans le message</h4>
                <ul>
                    <li><strong>{nom}</strong> : Remplacé par le nom complet du client (prénom + nom)</li>
                    <li><strong>{date}</strong> : Remplacé par la date de la réservation, au format 1 janvier 2025</li>
                    <li><strong>{periode}</strong> : Remplacé par la période de la réservation (Matinée, Après-midi, ou Journée entière)</li>
                </ul>

                <h3>Message Validation Réservation</h3>

                <div class="form-group">
                    <label for="subject_valid">Objet</label>
                    <input type="text" id="subject_valid" name="subject_valid" value="<?php echo esc_attr(stripslashes($vehicule->subject_valid ?? '')); ?>" required><br>
                </div>

                <div class="form-group">
                    <label for="body_valid">Corps du Mail</label>
                    <textarea id="body_valid" name="body_valid" required><?php echo esc_textarea(stripslashes($vehicule->body_valid ?? '')); ?></textarea><br>
                </div>


                <h3>Message Refus Réservation</h3>

                <div class="form-group">
                    <label for="subject_refus">Objet</label>
                    <input type="text" id="subject_refus" name="subject_refus" value="<?php echo esc_attr(stripslashes($vehicule->subject_refus ?? '')); ?>" required><br>
                </div>

                <div class="form-group">
                    <label for="body_refus">Corps du Mail</label>
                    <textarea id="body_refus" name="body_refus" required><?php echo esc_textarea(stripslashes($vehicule->body_refus ?? '')); ?></textarea><br>
                </div>


                <h3>Message Rappel J-1 Réservation</h3>

                <div class="form-group">
                    <label for="subject_rappel">Objet</label>
                    <input type="text" id="subject_rappel" name="subject_rappel" value="<?php echo esc_attr(stripslashes($vehicule->subject_rappel ?? '')); ?>" required><br>
                </div>

                <div class="form-group">
                    <label for="body_rappel">Corps du Mail</label>
                    <textarea id="body_rappel" name="body_rappel" required><?php echo esc_textarea(stripslashes($vehicule->body_rappel ?? '')); ?></textarea><br>
                </div>


                <h3>Message Attente Réservation</h3>

                <div class="form-group">
                    <label for="subject_attente">Objet</label>
                    <input type="text" id="subject_attente" name="subject_attente" value="<?php echo esc_attr(stripslashes($vehicule->subject_attente ?? '')); ?>" required><br>
                </div>
                
                <div class="form-group">
                    <label for="body_attente">Corps du Mail</label>
                    <textarea id="body_attente" name="body_attente" required><?php echo esc_textarea(stripslashes($vehicule->body_attente ?? '')); ?></textarea><br>
                </div>

                <h3>Message Validation Capacité</h3>

                <div class="form-group">
                    <label for="subject_capacite">Objet</label>
                    <input type="text" id="subject_capacite" name="subject_capacite" value="<?php echo esc_attr(stripslashes($vehicule->subject_capacite ?? '')); ?>" required><br>
                </div>

                <div class="form-group">
                    <label for="body_capacite">Corps du Mail</label>
                    <textarea id="body_capacite" name="body_capacite" required><?php echo esc_textarea(stripslashes($vehicule->body_capacite ?? '')); ?></textarea><br>
                </div>


                <!-- Bouton de soumission -->
                <input type="submit" name="submit" id="submit" class="button-primary" value="<?php echo $id_vehicule > 0 ? 'Modifier le Véhicule' : 'Ajouter le Véhicule'; ?>">

            </form>
            <br>
            <a href="?page=wp-reservations-vehicules" class="button">Retour aux véhicules</a>
        </div>

        <script>
            // Jours Indisponibilités
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('jours-indisponibilites-container');
                const addButton = document.getElementById('add-interval');

                // Ajouter un nouvel intervalle
                addButton.addEventListener('click', function() {
                    const newInterval = document.createElement('div');
                    newInterval.classList.add('jours-indisponibilites-item');
                    newInterval.innerHTML = `
                        <input type="date" name="jours_indisponibilites_debut[]" value="">
                        <input type="date" name="jours_indisponibilites_fin[]" value="">
                        <button type="button" class="remove-interval">❌</button>
                    `;
                    container.appendChild(newInterval);
                });

                // Suppression d'un intervalle
                container.addEventListener('click', function(event) {
                    if (event.target.classList.contains('remove-interval')) {
                        event.target.parentElement.remove();
                    }
                });
            });

            // Jours Fériés
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('jours-feries-container');
                const addButton = document.getElementById('add-date');

                // Ajouter une nouvelle date
                addButton.addEventListener('click', function() {
                    const newDateInput = document.createElement('div');
                    newDateInput.classList.add('jours-feries-item');
                    newDateInput.innerHTML = `
                        <input type="date" name="jours_feries[]" value="">
                        <button type="button" class="remove-date">❌</button>
                    `;
                    container.appendChild(newDateInput);
                });

                // Suppression d'une date
                container.addEventListener('click', function(event) {
                    if (event.target.classList.contains('remove-date'))
                    {
                        event.target.parentElement.remove();
                    }
                });
            });
        </script>

        <style>
            .form-group {
                margin-bottom: 10px;
                display: flex;
                flex-direction: column;
                width: 50%;
            }

            .form-group textarea {
                height: 100px;
            }

            .points-group {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                flex-direction: row;
            }

            .points-group div {
                flex: 1;
                min-width: 50px;
                text-align: center;
            }

            .points-group input {
                width: 80px;
                text-align: center;
                padding: 5px;
            }

            .checkbox-group {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .checkbox-group label {
                margin: 0;
            }

            .schedule-group {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                width: 100%;
                margin-bottom: 20px;
            }

            .schedule-column {
                padding: 15px;
                text-align: center;
            }

            .schedule-column input {
                width: 100%;
                padding: 5px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }

            .jours-indisponibilites-header {
                display: flex;
                gap: 15px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .jours-indisponibilites-header span {
                width: 45%;
                text-align: center;
            }

            .jours-indisponibilites-item {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 5px;
            }

            .jours-indisponibilites-item input {
                width: 45%;
            }


            .jours-feries-item {
                display: flex;
                align-items: center;
                margin-bottom: 5px;
            }

            .jours-feries-item input {
                margin-right: 10px;
            }
        </style>
        <?php
    }


    

    // Fonction pour ajouter un véhicule dans la base de données
    function ajouter_vehicule()
    {
        global $wpdb;

        // Vérification et nettoyage des données
        $nom_vehicule           = sanitize_text_field($_POST['nom_vehicule']);
        $description_vehicule   = sanitize_textarea_field($_POST['description_vehicule']);
        $validation_capacite    = isset($_POST['validation_capacite']) ? 1 : 0;
        $image_vehicule         = sanitize_text_field($_POST['image_vehicule']);
        $email_admin            = sanitize_email($_POST['email_admin']);
        $email_reservation      = sanitize_email($_POST['email_reservation']);
        $points_halfday         = intval($_POST['points_halfday']);
        $points_fullday         = intval($_POST['points_fullday']);
        $points_halfweek        = intval($_POST['points_halfweek']);
        $points_fullweek        = intval($_POST['points_fullweek']);
        $start_time_full_day    = !empty($_POST['start_time_full_day']) ? $_POST['start_time_full_day'] : null;
        $end_time_full_day      = !empty($_POST['end_time_full_day']) ? $_POST['end_time_full_day'] : null;
        $start_time_half_day    = !empty($_POST['start_time_half_day']) ? $_POST['start_time_half_day'] : null;
        $end_time_half_day      = !empty($_POST['end_time_half_day'])? $_POST['end_time_half_day'] : null;
        $start_time_half_day2   = !empty($_POST['start_time_half_day2']) ? $_POST['start_time_half_day2'] : null;
        $end_time_half_day2     = !empty($_POST['end_time_half_day2']) ? $_POST['end_time_half_day2'] : null;
        $statut_vehicule        = sanitize_text_field($_POST['statut_vehicule']);

        // Vérifier si des jours d'indisponibilités sont sélectionnés
        if (!empty($_POST['jours_indisponibilites_debut']) && !empty($_POST['jours_indisponibilites_fin']))
        {
            $jours_indisponibilites = [];

            for ($i = 0; $i < count($_POST['jours_indisponibilites_debut']); $i++)
            {
                $debut = sanitize_text_field($_POST['jours_indisponibilites_debut'][$i]);
                $fin = sanitize_text_field($_POST['jours_indisponibilites_fin'][$i]);

                // Générer toutes les dates entre la date de début et la date de fin
                $start_date = new DateTime($debut);
                $end_date   = new DateTime($fin);
                while ($start_date <= $end_date)
                {
                    $jours_indisponibilites[] = $start_date->format('Y-m-d');
                    $start_date->modify('+1 day');
                }
            }

            // Convertir en chaîne séparée par des virgules pour stocker dans la base de données
            $jours_indisponibilites_str = implode(',', $jours_indisponibilites);
        }
        else
        {
            $jours_indisponibilites_str = '';
        }


        // Vérifier si des jours fériés sont sélectionnés
        if (!empty($_POST['jours_feries']) && is_array($_POST['jours_feries']))
        {
            // Nettoyer chaque date et la stocker dans un tableau
            $jours_feries_array = array_map('sanitize_text_field', $_POST['jours_feries']);
            
            // Convertir le tableau en une chaîne de texte séparée par des virgules
            $jours_feries = implode(',', $jours_feries_array);
        }
        else
        {
            $jours_feries = ''; // Aucune date sélectionnée
        }

        // Vérifier si des permissions ont été envoyées
        if (!empty($_POST['permissions_reservation']) && is_array($_POST['permissions_reservation']))
        {
            $permissions = json_encode($_POST['permissions_reservation']); // Conversion en JSON
        }
        else
        {
            $permissions = json_encode([]); // Valeur par défaut
        }

        // Vérifier si des permissions de créneaux ont été envoyées
        if (!empty($_POST['permissions_creneaux']) && is_array($_POST['permissions_creneaux']))
        {
            $permissions_creneaux = json_encode($_POST['permissions_creneaux']); // Conversion en JSON
        }
        else
        {
            $permissions_creneaux = json_encode([]); // Valeur par défaut
        }

        $message_creneaux       = sanitize_text_field($_POST['message_creneaux']);
        $validation_auto_time   = intval($_POST['validation_auto_time']);

        // Emails transactionnels
        $subject_valid          = sanitize_text_field($_POST['subject_valid']);
        $body_valid             = sanitize_textarea_field($_POST['body_valid']);
        $subject_refus          = sanitize_text_field($_POST['subject_refus']);
        $body_refus             = sanitize_textarea_field($_POST['body_refus']);
        $subject_rappel         = sanitize_text_field($_POST['subject_rappel']);
        $body_rappel            = sanitize_textarea_field($_POST['body_rappel']);
        $subject_attente        = sanitize_text_field($_POST['subject_attente']);
        $body_attente           = sanitize_textarea_field($_POST['body_attente']);
        $subject_capacite       = sanitize_text_field($_POST['subject_capacite']);
        $body_capacite          = sanitize_textarea_field($_POST['body_capacite']);

        // Insertion des données dans la table des véhicules
        $table_name = $wpdb->prefix . 'vehicules';

        $wpdb->insert(
            $table_name,
            array(
                'nom'                    => $nom_vehicule,
                'description'            => $description_vehicule,
                'validation_capacite'    => $validation_capacite,
                'image'                  => $image_vehicule,
                'email_admin'            => $email_admin,
                'email_reservation'      => $email_reservation,
                'points_halfday'         => $points_halfday,
                'points_fullday'         => $points_fullday,
                'points_halfweek'        => $points_halfweek,
                'points_fullweek'        => $points_fullweek,
                'start_time_full_day'    => $start_time_full_day,
                'end_time_full_day'      => $end_time_full_day,
                'start_time_half_day'    => $start_time_half_day,
                'end_time_half_day'      => $end_time_half_day,
                'start_time_half_day2'   => $start_time_half_day2,
                'end_time_half_day2'     => $end_time_half_day2,
                'statut'                 => $statut_vehicule,
                'jours_indisponibilites' => $jours_indisponibilites_str,
                'jours_feries'           => $jours_feries,
                'permissions_reservation' => $permissions,
                'permissions_creneaux'   => $permissions_creneaux,
                'message_creneaux'       => $message_creneaux,
                'validation_auto_time'   => $validation_auto_time,

                // Emails transactionnels
                'subject_valid'          => $subject_valid,
                'body_valid'             => $body_valid,
                'subject_refus'          => $subject_refus,
                'body_refus'             => $body_refus,
                'subject_rappel'         => $subject_rappel,
                'body_rappel'            => $body_rappel,
                'subject_attente'        => $subject_attente,
                'body_attente'           => $body_attente,
                'subject_capacite'       => $subject_capacite,
                'body_capacite'          => $body_capacite
            )
        );

        // Message de succès
        echo '<div class="updated"><p>Le véhicule a été ajouté avec succès !</p></div>';
    }


    // Fonction pour modifier un véhicule dans la base de données
    function modifier_vehicule($id_vehicule)
    {
        global $wpdb;

        // Vérification et nettoyage des données
        $nom_vehicule           = sanitize_text_field($_POST['nom_vehicule']);
        $description_vehicule   = sanitize_textarea_field($_POST['description_vehicule']);
        $validation_capacite    = isset($_POST['validation_capacite']) ? 1 : 0;
        $image_vehicule         = sanitize_text_field($_POST['image_vehicule']);
        $email_admin            = sanitize_email($_POST['email_admin']);
        $email_reservation      = sanitize_email($_POST['email_reservation']);
        $points_halfday         = intval($_POST['points_halfday']);
        $points_fullday         = intval($_POST['points_fullday']);
        $points_halfweek        = intval($_POST['points_halfweek']);
        $points_fullweek        = intval($_POST['points_fullweek']);
        $start_time_full_day    = !empty($_POST['start_time_full_day']) ? $_POST['start_time_full_day'] : null;
        $end_time_full_day      = !empty($_POST['end_time_full_day']) ? $_POST['end_time_full_day'] : null;
        $start_time_half_day    = !empty($_POST['start_time_half_day']) ? $_POST['start_time_half_day'] : null;
        $end_time_half_day      = !empty($_POST['end_time_half_day'])? $_POST['end_time_half_day'] : null;
        $start_time_half_day2   = !empty($_POST['start_time_half_day2']) ? $_POST['start_time_half_day2'] : null;
        $end_time_half_day2     = !empty($_POST['end_time_half_day2']) ? $_POST['end_time_half_day2'] : null;
        $statut_vehicule        = sanitize_text_field($_POST['statut_vehicule']);

        // Vérifier si des jours d'indisponibilités sont sélectionnés
        if (!empty($_POST['jours_indisponibilites_debut']) && !empty($_POST['jours_indisponibilites_fin']))
        {
            $jours_indisponibilites = [];

            for ($i = 0; $i < count($_POST['jours_indisponibilites_debut']); $i++)
            {
                $debut = sanitize_text_field($_POST['jours_indisponibilites_debut'][$i]);
                $fin = sanitize_text_field($_POST['jours_indisponibilites_fin'][$i]);

                // Générer toutes les dates entre la date de début et la date de fin
                $start_date = new DateTime($debut);
                $end_date   = new DateTime($fin);
                while ($start_date <= $end_date)
                {
                    $jours_indisponibilites[] = $start_date->format('Y-m-d');
                    $start_date->modify('+1 day');
                }
            }

            // Convertir en chaîne séparée par des virgules pour stocker dans la base de données
            $jours_indisponibilites_str = implode(',', $jours_indisponibilites);
        }
        else
        {
            $jours_indisponibilites_str = '';
        }

        // Vérifier si des jours fériés sont sélectionnés
        if (!empty($_POST['jours_feries']) && is_array($_POST['jours_feries']))
        {
            // Nettoyer chaque date et la stocker dans un tableau
            $jours_feries_array = array_map('sanitize_text_field', $_POST['jours_feries']);
            
            // Convertir le tableau en une chaîne de texte séparée par des virgules
            $jours_feries = implode(',', $jours_feries_array);
        }
        else
        {
            $jours_feries = ''; // Aucune date sélectionnée
        }

        // Vérifier si des permissions ont été envoyées
        if (!empty($_POST['permissions_reservation']) && is_array($_POST['permissions_reservation']))
        {
            $permissions = json_encode($_POST['permissions_reservation']); // Conversion en JSON
        }
        else
        {
            $permissions = json_encode([]); // Valeur par défaut
        }

        // Vérifier si des permissions de créneaux ont été envoyées
        if (!empty($_POST['permissions_creneaux']) && is_array($_POST['permissions_creneaux']))
        {
            $permissions_creneaux = json_encode($_POST['permissions_creneaux']); // Conversion en JSON
        }
        else
        {
            $permissions_creneaux = json_encode([]); // Valeur par défaut
        }

        $message_creneaux       = sanitize_text_field($_POST['message_creneaux']);
        $validation_auto_time   = intval($_POST['validation_auto_time']);

        // Emails transactionnels
        $subject_valid          = sanitize_text_field($_POST['subject_valid']);
        $body_valid             = sanitize_textarea_field($_POST['body_valid']);
        $subject_refus          = sanitize_text_field($_POST['subject_refus']);
        $body_refus             = sanitize_textarea_field($_POST['body_refus']);
        $subject_rappel         = sanitize_text_field($_POST['subject_rappel']);
        $body_rappel            = sanitize_textarea_field($_POST['body_rappel']);
        $subject_attente        = sanitize_text_field($_POST['subject_attente']);
        $body_attente           = sanitize_textarea_field($_POST['body_attente']);
        $subject_capacite       = sanitize_text_field($_POST['subject_capacite']);
        $body_capacite          = sanitize_textarea_field($_POST['body_capacite']);

        // Mise à jour du véhicule
        $table_name = $wpdb->prefix . 'vehicules';

        $wpdb->update(
            $table_name,
            array(
                'nom'                    => $nom_vehicule,
                'description'            => $description_vehicule,
                'validation_capacite'    => $validation_capacite,
                'image'                  => $image_vehicule,
                'email_admin'            => $email_admin,
                'email_reservation'      => $email_reservation,
                'points_halfday'         => $points_halfday,
                'points_fullday'         => $points_fullday,
                'points_halfweek'        => $points_halfweek,
                'points_fullweek'        => $points_fullweek,
                'start_time_full_day'    => $start_time_full_day,
                'end_time_full_day'      => $end_time_full_day,
                'start_time_half_day'    => $start_time_half_day,
                'end_time_half_day'      => $end_time_half_day,
                'start_time_half_day2'   => $start_time_half_day2,
                'end_time_half_day2'     => $end_time_half_day2,
                'statut'                 => $statut_vehicule,
                'jours_indisponibilites' => $jours_indisponibilites_str,
                'jours_feries'           => $jours_feries,
                'permissions_reservation' => $permissions,
                'permissions_creneaux'   => $permissions_creneaux,
                'message_creneaux'       => $message_creneaux,
                'validation_auto_time'   => $validation_auto_time,

                // Emails transactionnels
                'subject_valid'          => $subject_valid,
                'body_valid'             => $body_valid,
                'subject_refus'          => $subject_refus,
                'body_refus'             => $body_refus,
                'subject_rappel'         => $subject_rappel,
                'body_rappel'            => $body_rappel,
                'subject_attente'        => $subject_attente,
                'body_attente'           => $body_attente,
                'subject_capacite'       => $subject_capacite,
                'body_capacite'          => $body_capacite
            ),
            array('id' => $id_vehicule)
        );

        // Message de succès
        echo '<div class="updated"><p>Le véhicule a été modifié avec succès !</p></div>';
    }

    // Fonction pour supprimer le véhicule
    function supprimer_vehicule()
    {
        // Vérifier si l'utilisateur a les permissions nécessaires
        if (!current_user_can('manage_options'))
        {
            return;
        }

        // Vérifier si l'ID est passé et si le nonce est valide
        if (isset($_GET['id'], $_GET['nonce']) && is_numeric($_GET['id']))
        {
            $id_vehicule = intval($_GET['id']);
            $nonce = $_GET['nonce'];

            // Vérifier le nonce pour la sécurité
            if (!wp_verify_nonce($nonce, 'supprimer_vehicule_' . $id_vehicule))
            {
                die('Nonce invalide');
            }

            // Supprimer le véhicule de la base de données
            global $wpdb;
            $table_name = $wpdb->prefix . 'vehicules';
            $wpdb->delete($table_name, array('id' => $id_vehicule));

            // Rediriger vers la page de gestion des véhicules après suppression
            wp_redirect(admin_url('admin.php?page=wp-reservations-vehicules'));
            exit;
        }
    }

    // Hook pour capturer l'action de suppression
    add_action('admin_init', 'supprimer_vehicule');

?>