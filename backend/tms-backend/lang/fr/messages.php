<?php

return [

    'validation_failed' => 'Les données fournies sont invalides.',

    'auth' => [
        'login_success' => 'Connexion réussie.',
        'logout_success' => 'Déconnexion réussie.',
        'credentials_invalid' => 'Ces identifiants ne correspondent à aucun compte connu.',
        'password_reset_link_sent' => 'Si ce compte existe, un lien de réinitialisation du mot de passe a été envoyé.',
        'password_reset_success' => 'Mot de passe réinitialisé avec succès.',
        'email_already_verified' => 'Adresse e-mail déjà vérifiée.',
        'verification_link_sent' => 'Lien de vérification envoyé.',
        'email_verified_success' => 'Adresse e-mail vérifiée avec succès.',
        'invalid_verification_link' => 'Ce lien de vérification est invalide ou a expiré.',
    ],

    'tickets' => [
        'created' => 'Ticket créé.',
        'updated' => 'Ticket mis à jour.',
        'deleted' => 'Ticket supprimé.',
        'status_updated' => 'Statut du ticket mis à jour.',
        'assigned' => 'Ticket assigné.',
        'pulled' => 'Ticket qui vous a été assigné.',
        'comment_added' => 'Commentaire ajouté.',
        'read_only_resolved' => 'Les tickets résolus sont en lecture seule ; changez le statut pour les rouvrir avant de les modifier.',
        'read_only_closed' => 'Les tickets fermés ne peuvent plus être modifiés.',
        'already_assigned' => 'Ce ticket a déjà été pris en charge par un autre agent.',
        'invalid_transition' => 'Impossible de faire passer un ticket de « :from » à « :to ».',
        'not_qualified' => "Vous n'êtes pas qualifié pour prendre ce type de ticket. Demandez à un administrateur de vous attribuer cette compétence.",
    ],

    'ticket_types' => [
        'created' => 'Type de ticket créé.',
        'updated' => 'Type de ticket mis à jour.',
        'deleted' => 'Type de ticket supprimé.',
    ],

    'tenants' => [
        'created' => 'Entité créée.',
        'updated' => 'Entité mise à jour.',
        'deleted' => 'Entité suspendue.',
    ],

    'users' => [
        'created' => 'Utilisateur créé.',
        'updated' => 'Utilisateur mis à jour.',
        'deleted' => 'Utilisateur désactivé.',
        'roles_updated' => 'Rôles mis à jour.',
        'skills_updated' => 'Compétences mises à jour.',
        'only_super_admin_grants_super_admin' => 'Seul un Super Admin peut accorder le rôle Super Admin.',
        'super_admin_role_requires_no_tenant' => "Le rôle Super Admin ne peut être accordé qu'à un compte de niveau plateforme, sans espace de travail.",
        'skills_must_match_tenant' => "Un ou plusieurs types de tickets n'appartiennent pas à l'espace de travail de cet utilisateur.",
    ],

    'attachments' => [
        'uploaded' => 'Fichier téléversé.',
        'deleted' => 'Pièce jointe supprimée.',
        'infected' => 'Ce fichier a été rejeté car il semble contenir un logiciel malveillant.',
        'scan_unavailable' => "Le fichier n'a pas pu être analysé pour le moment. Veuillez réessayer sous peu.",
    ],

    'notifications' => [
        'all_marked_read' => 'Toutes les notifications ont été marquées comme lues.',
    ],

    'http' => [
        'unauthenticated' => 'Non authentifié.',
        'forbidden' => "Cette action n'est pas autorisée.",
        'not_found' => 'La ressource demandée est introuvable.',
        'rate_limited' => 'Trop de requêtes.',
        'http_error' => "Une erreur s'est produite.",
        'server_error' => 'Erreur du serveur.',
    ],

];
