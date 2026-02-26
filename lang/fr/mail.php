<?php

return [
    'common' => [
        'na' => 'N/D',
        'thanks' => 'Merci,',
    ],

    'labels' => [
        'source_type' => 'Source',
        'submitted' => 'Soumis le',
        'processed' => 'Traité le',
        'document_id' => 'ID du document',
        'type' => 'Type',
        'amount' => 'Montant',
        'date' => 'Date',
        'reason' => 'Raison',
    ],

    'source_types' => [
        'manual_upload' => 'Téléversement manuel',
        'received_email' => 'E-mail reçu',
        'google_drive' => 'Google Drive',
    ],

    'transaction_types' => [
        'withdrawal' => 'Retrait',
        'deposit' => 'Dépôt',
        'transfer' => 'Transfert',
        'buy' => 'Achat',
        'sell' => 'Vente',
        'dividend' => 'Dividende',
        'interest' => 'Intérêt',
        'add_shares' => 'Ajouter des actions',
        'remove_shares' => 'Retirer des actions',
    ],

    'ai_document_processed' => [
        'subject' => 'Document traité - prêt pour révision',
        'greeting' => 'Bonjour :name,',
        'intro' => 'Votre document IA est prêt pour révision.',
        'what_happened' => 'Ce qui s\'est passé',
        'extracted_summary' => 'Résumé extrait',
        'next_action_title' => 'Action suivante',
        'next_action_text' => 'Vérifiez les valeurs extraites, ajustez si nécessaire, puis finalisez la transaction.',
        'button_review_document' => 'Réviser le document',
        'button_open_documents' => 'Ouvrir les documents IA',
    ],

    'ai_document_processing_failed' => [
        'subject' => 'Échec du traitement du document',
        'greeting' => 'Bonjour :name,',
        'intro' => 'Votre document IA n\'a pas pu être traité.',
        'document_details' => 'Détails du document',
        'next_action_title' => 'Action suivante',
        'next_action_text' => 'Ouvrez le document, mettez à jour l\'instruction personnalisée si nécessaire, puis relancez le traitement.',
        'settings_hint' => 'Si le problème persiste, vérifiez le modèle IA et les identifiants du fournisseur.',
        'fallback_reason' => 'Une erreur inconnue est survenue pendant le traitement.',
        'button_review_reprocess' => 'Réviser et retraiter',
        'button_open_settings' => 'Ouvrir les paramètres IA',
    ],
];
