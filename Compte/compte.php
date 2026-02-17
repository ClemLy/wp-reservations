<?php
    // Sécurité pour éviter un accès direct
    defined('ABSPATH') or die('No script kiddies please!');

    // --- Formulaire d'inscription ---
    function afficher_formulaire_inscription()
    {
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

        // Messages d'erreurs
        if (isset($_GET['message']))
        {
            $message = '';

            if ($_GET['message'] == 'error-password')
            {
                $message = "Les mots de passe ne correspondent pas.";
                $title = "Erreur";
                $icon = "error";
            }
            elseif ($_GET['message'] == 'error-email')
            {
                $message = "Cette adresse email est déjà utilisée.";
                $title = "Erreur";
                $icon = "error";
            }

            if ($message)
            {
                echo "<script>
                        Swal.fire({
                            icon: '$icon',
                            title: '$title',
                            text: '$message',
                            confirmButtonColor: '#d33'
                        });
                    </script>";
            }
        }
    ?>
        <form class="form-plugin-reservation" method="POST" action="">
            <input type="hidden" name="wp-reservations_nonce" value="<?php echo wp_create_nonce('wp-reservations_inscription'); ?>">

            <label for="nom">Nom *</label>
            <input type="text" name="nom" required>

            <label for="prenom">Prénom *</label>
            <input type="text" name="prenom" required>

            <label for="email">Adresse Email *</label>
            <input type="email" name="email" required>

            <label for="telephone">Numéro de téléphone</label>
            <input type="tel" name="telephone" placeholder="Ex : 06 12 34 56 78">

            <label for="password">Mot de passe *</label>
            <input type="password" name="password" required>

            <label for="password_confirm">Confirmer le mot de passe *</label>
            <input type="password" name="password_confirm" required>

            <p class="note">(*) Champs obligatoires</p>

            <button type="submit" name="inscription">S'inscrire</button>
        </form>

        <p>Déjà un compte ? <a href="<?php echo home_url('/connexion'); ?>">Connectez-vous ici !</a></p>
    <?php
    }




    // --- Formulaire de connexion ---
    function afficher_formulaire_connexion()
    {
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

        // Messages d'erreurs
        if (isset($_GET['message']))
        {
            $message = '';
            
            switch ($_GET['message'])
            {
                case 'activation':
                    $message = "Un lien d'activation a été envoyé à votre adresse mail.";
                    $title = "Activation envoyée";
                    $icon = "info";
                    break;

                case 'activated':
                    $message = "Votre compte a été activé avec succès. Vous pouvez maintenant vous connecter.";
                    $title = "Compte activé";
                    $icon = "success";
                    break;

                case 'non-active':
                    $message = "Votre compte n'est pas encore activé. Veuillez vérifier vos emails.";
                    $title = "Compte non activé";
                    $icon = "warning";
                    break;

                case 'error':
                    $message = "Identifiants incorrects.";
                    $title = "Erreur";
                    $icon = "error";
                    break;
            }

            if ($message)
            {
                $message_escaped = esc_js($message);
                $title_escaped   = esc_js($title);
                $icon_escaped    = esc_js($icon);
                
                echo "<script>
                        Swal.fire({
                            icon: '$icon_escaped',
                            title: '$title_escaped',
                            text: '$message_escaped',
                            confirmButtonColor: '#3085d6'
                        });
                    </script>";
            }
        }
    ?>
        <form class="form-plugin-reservation" method="POST" action="">
            <input type="hidden" name="wp-reservations_nonce" value="<?php echo wp_create_nonce('wp-reservations_connexion'); ?>">

            <label for="email">Adresse Email</label>
            <input type="email" name="email" required>

            <label for="password">Mot de passe</label>
            <input type="password" name="password" required>

            <button type="submit" name="connexion">Se connecter</button>
        </form>

        <p>Pas de compte ? <a href="<?php echo home_url('/inscription'); ?>">Inscrivez-vous ici !</a></p>
    <?php
    }




    // --- Traitement des formulaires ---
    function traitement_formulaires_compte()
    {
        // Traitement de l'inscription
        if (isset($_POST['inscription']))
        {
            $nom              = sanitize_text_field($_POST['nom']);
            $prenom           = sanitize_text_field($_POST['prenom']);
            $email            = sanitize_email($_POST['email']);
            $telephone        = isset($_POST['telephone']) ? sanitize_text_field($_POST['telephone']) : '';
            $password         = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];


            // Vérifications
            if ($password !== $password_confirm)
            {
                wp_redirect(home_url('/inscription?message=error-password'));
                exit;
            }

            if (email_exists($email))
            {
                wp_redirect(home_url('/inscription?message=error-email'));
                exit;
            }

            // Création du compte
            $user_id = wp_create_user($email, $password, $email);

            if (!is_wp_error($user_id))
            {
                wp_update_user(array(
                    'ID' => $user_id,
                    'first_name' => $prenom,
                    'last_name' => $nom,
                ));

                // Stockage du numéro de téléphone si renseigné
                if (!empty($telephone))
                {
                    update_user_meta($user_id, 'telephone', $telephone);
                }

                // Déterminer le rôle en fonction du domaine de l'email
                $role = (strpos($email, '@sidel.com') !== false) ? 'salarie' : 'externe';

                $user = new WP_User($user_id);
                $user->set_role($role);

                // Envoyer le mail d'activation
                $activation_key = wp_generate_password(20, false);
                update_user_meta($user_id, 'activation_key', $activation_key);

                $activation_link = home_url("/activer-compte?key=$activation_key&user=$user_id");
                $subject = "Activation de votre compte";


                // Corps du message avec du HTML et des styles
                $body = "
                <html>
                    <head>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                color: #333;
                                margin: 0;
                                padding: 0;
                            }
                            .activation-message {
                                padding: 20px;
                                border: 1px solid #ccc;
                                background-color: #f9f9f9;
                                margin: 20px auto;
                                max-width: 600px;
                                text-align: center;
                            }
                            .activation-message h2 {
                                color: #0073aa;
                            }
                            .activation-message p {
                                margin: 10px 0;
                                font-size: 16px;
                            }
                            .activation-message a {
                                color: #ffffff;
                                background-color: #0073aa;
                                padding: 10px 20px;
                                text-decoration: none;
                                border-radius: 5px;
                                font-weight: bold;
                                margin-top: 10px;
                            }
                            .activation-message a:hover {
                                background-color: #005f8d;
                            }
                        </style>
                    </head>
                    <body>
                        <div class='activation-message'>
                            <h2>Activation de votre compte</h2>
                            <p>Merci de vous être inscrit !</p>
                            <p>Cliquez sur le lien ci-dessous pour activer votre compte :</p>
                            <p><a href='$activation_link'>Activer mon compte</a></p>
                        </div>
                    </body>
                </html>
                ";

                // En-têtes de l'e-mail pour spécifier qu'il est en HTML et définir l'expéditeur
                $headers = array(
                    'Content-Type: text/html; charset=UTF-8',
                );



                wp_mail($email, $subject, $body, $headers);

                // Redirection vers la connexion avec message
                wp_redirect(home_url('/connexion?message=activation'));
                exit;
            }
            else
            {
                wp_die('Erreur lors de la création du compte.');
            }
        }

        // Traitement de la connexion
        if (isset($_POST['connexion']))
        {
            $creds = array(
                'user_login'    => $_POST['email'],
                'user_password' => $_POST['password'],
            );
            $user = wp_signon($creds, false);

            if (is_wp_error($user))
            {
                wp_redirect(home_url('/connexion?message=error'));
                exit;
            }
            else
            {
                // Vérification si le compte est activé
                $activation_key = get_user_meta($user->ID, 'activation_key', true);

                if (!empty($activation_key))
                {
                    // Redirection avec message d'erreur si le compte n'est pas activé
                    wp_redirect(home_url('/connexion?message=non-active'));
                    exit;
                }


                $redirect_url = isset($_GET['redirect_to']) && !empty($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/');


                wp_redirect($redirect_url);
                exit;
            }
        }
    }
    add_action('init', 'traitement_formulaires_compte');






    // --- Activation du compte ---
    function activer_utilisateur()
    {
        if (isset($_GET['key']) && isset($_GET['user']))
        {
            $user_id = intval($_GET['user']);
            $key = sanitize_text_field($_GET['key']);
            
            $saved_key = get_user_meta($user_id, 'activation_key', true);
            
            if ($key === $saved_key)
            {
                delete_user_meta($user_id, 'activation_key');
                
                wp_redirect(home_url('/connexion?message=activated'));
                exit;
            }
            else
            {
                wp_die('Clé d\'activation invalide.');
            }
        }
    }
    add_action('init', 'activer_utilisateur');
?>