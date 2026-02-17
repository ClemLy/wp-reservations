<?php
    // Empêche l'accès direct
    defined('ABSPATH') or die('No script kiddies please!');

    function envoyer_mail_reservation($email, $statut, $vehicule, $date_reservation, $horaire, $nom, $prenom, $motif = null)
    {
        global $wpdb;
        $table_vehicules = $wpdb->prefix . 'vehicules';

        
        // Obtenir le nom complet
        $nom_complet = $prenom . ' ' . $nom;

        // Convertir la date au format français
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_reservation);

        if ($date_obj)
        {
            $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
            $date_reservation = $formatter->format($date_obj);
        }

        // Déterminer si c'est la matinée, l'après-midi ou la journée entière
        $periode = '';
        if ($horaire == "half_day_morning" || $horaire == "half_day_morning_weekend")
        {
            $periode = 'Matinée';
        }
        elseif ($horaire == "half_day_afternoon" || $horaire == "half_day_afternoon_weekend")
        {
            $periode = 'Après-midi';
        }
        else
        {
            $periode = 'Journée entière';
        }

        // Récupérer le sujet et le corps du mail en fonction du statut
        switch ($statut)
        {
            case 'validée':
                $email_sujet = $wpdb->get_var($wpdb->prepare("SELECT subject_valid FROM $table_vehicules WHERE nom = %s", $vehicule));
                $email_corps = $wpdb->get_var($wpdb->prepare("SELECT body_valid FROM $table_vehicules WHERE nom = %s", $vehicule));
                break;

            case 'refusée':
                $email_sujet = $wpdb->get_var($wpdb->prepare("SELECT subject_refus FROM $table_vehicules WHERE nom = %s", $vehicule));
                $email_corps = $wpdb->get_var($wpdb->prepare("SELECT body_refus FROM $table_vehicules WHERE nom = %s", $vehicule));
                break;

            case 'J-1':
                $email_sujet = $wpdb->get_var($wpdb->prepare("SELECT subject_rappel FROM $table_vehicules WHERE nom = %s", $vehicule));
                $email_corps = $wpdb->get_var($wpdb->prepare("SELECT body_rappel FROM $table_vehicules WHERE nom = %s", $vehicule));
                break;

            case 'en attente':
                $email_sujet = $wpdb->get_var($wpdb->prepare("SELECT subject_attente FROM $table_vehicules WHERE nom = %s", $vehicule));
                $email_corps = $wpdb->get_var($wpdb->prepare("SELECT body_attente FROM $table_vehicules WHERE nom = %s", $vehicule));
                break;

            case 'capacite':
                $email_sujet = $wpdb->get_var($wpdb->prepare("SELECT subject_capacite FROM $table_vehicules WHERE nom = %s", $vehicule));
                $email_corps = $wpdb->get_var($wpdb->prepare("SELECT body_capacite FROM $table_vehicules WHERE nom = %s", $vehicule));
                break;

            case 'annulée':
                $email_sujet = 'Annulation de votre réservation';
                $email_corps = "Bonjour $nom_complet,\n\n";
                $email_corps .= "Nous vous informons que votre réservation pour \"$vehicule\" prévue pour le $date_reservation dans la période \"$periode\" a été annulée.\n";
                if (!empty($motif))
                {
                    $email_corps .= "\nMotif : $motif\n";
                }
                $email_corps .= "\nSi vous avez des questions, n'hésitez pas à nous contacter.";
                break;

            default:
                return;
        }

        // Vérifier si le sujet et le corps sont bien définis
        if (empty($email_sujet) || empty($email_corps))
        {
            return;
        }

        // ⚠️ S'assurer que $email_corps est bien une chaîne de caractères
        $email_corps = (string) $email_corps;

        // Personnalisation du message avec les détails de la réservation
        $email_corps = str_replace(
            ['{nom}','{date}', '{periode}'],
            [$nom_complet, $date_reservation, $periode],
            $email_corps
        );

        // Envoi de l'email
        wp_mail($email, $email_sujet, $email_corps);
    }
?>