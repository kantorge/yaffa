<?php

return [
    "ai_document_processed" => [
        "button_open_documents" => "Ouvrir les documents IA",
        "button_review_document" => "Réviser le document",
        "extracted_summary" => "Résumé extrait",
        "intro" => "Votre document IA est prêt pour révision.",
        "next_action_text" => "Vérifiez les valeurs extraites, ajustez si nécessaire, puis finalisez la transaction.",
        "next_action_title" => "Action suivante",
        "subject" => "Document traité - prêt pour révision",
        "what_happened" => "Ce qui s'est passé"
    ],
    "ai_document_processing_failed" => [
        "button_open_settings" => "Ouvrir les paramètres IA",
        "button_review_reprocess" => "Réviser et retraiter",
        "document_details" => "Détails du document",
        "fallback_reason" => "Une erreur inconnue est survenue pendant le traitement.",
        "intro" => "Votre document IA n'a pas pu être traité.",
        "next_action_text" => "Ouvrez le document, mettez à jour l'instruction personnalisée si nécessaire, puis relancez le traitement.",
        "next_action_title" => "Action suivante",
        "settings_hint" => "Si le problème persiste, vérifiez le modèle IA et les identifiants du fournisseur.",
        "subject" => "Échec du traitement du document"
    ],
    "common" => [
        "greeting" => "Bonjour :name,",
        "na" => "N/D",
        "thanks" => "Merci,"
    ],
    "google_drive_import_failed" => [
        "button_open_settings" => "Ouvrir les paramètres du profil",
        "error" => "Erreur : :error",
        "error_count" => "Erreurs consécutives : :count",
        "folder" => "Dossier : :folder",
        "intro" => "Le processus d'import Google Drive a échoué.",
        "subject" => "Échec de l'import Google Drive"
    ],
    "google_drive_import_success" => [
        "button_open_documents" => "Ouvrir les documents IA",
        "failed_downloads" => "Échecs de téléchargement : :count",
        "folder" => "Dossier : :folder",
        "imported" => "Documents importés : :count",
        "intro" => "Votre import Google Drive est terminé avec succès.",
        "skipped_existing" => "Ignorés (déjà importés) : :count",
        "skipped_too_large" => "Ignorés (trop volumineux) : :count",
        "skipped_unsupported" => "Ignorés (type non pris en charge) : :count",
        "subject" => "Import Google Drive terminé"
    ],
    "labels" => [
        "amount" => "Montant",
        "date" => "Date",
        "document_id" => "ID du document",
        "processed" => "Traité le",
        "reason" => "Raison",
        "source_type" => "Source",
        "submitted" => "Soumis le",
        "type" => "Type"
    ],
    "source_types" => [
        "google_drive" => "Google Drive",
        "manual_upload" => "Téléversement manuel",
        "received_email" => "E-mail reçu"
    ],
    "transaction_types" => [
        "add_shares" => "Ajouter des actions",
        "buy" => "Achat",
        "deposit" => "Dépôt",
        "dividend" => "Dividende",
        "interest" => "Intérêt",
        "remove_shares" => "Retirer des actions",
        "sell" => "Vente",
        "transfer" => "Transfert",
        "withdrawal" => "Retrait"
    ]
];
