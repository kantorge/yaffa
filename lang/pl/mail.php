<?php

return [
    'common' => [
        'na' => 'brak',
        'greeting' => 'Witaj :name,',
        'thanks' => 'Dziękujemy,',
    ],

    'labels' => [
        'source_type' => 'Źródło',
        'submitted' => 'Przesłano',
        'processed' => 'Przetworzono',
        'document_id' => 'ID dokumentu',
        'type' => 'Typ',
        'amount' => 'Kwota',
        'date' => 'Data',
        'reason' => 'Przyczyna',
    ],

    'source_types' => [
        'manual_upload' => 'Ręczne przesłanie',
        'received_email' => 'Otrzymany e-mail',
        'google_drive' => 'Google Drive',
    ],

    'transaction_types' => [
        'withdrawal' => 'Wydatek',
        'deposit' => 'Wpływ',
        'transfer' => 'Transfer',
        'buy' => 'Kupno',
        'sell' => 'Sprzedaż',
        'dividend' => 'Dywidenda',
        'interest' => 'Odsetki',
        'add_shares' => 'Dodanie udziałów',
        'remove_shares' => 'Usunięcie udziałów',
    ],

    'ai_document_processed' => [
        'subject' => 'Dokument przetworzony - gotowy do sprawdzenia',
        'intro' => 'Twój dokument AI jest gotowy do sprawdzenia.',
        'what_happened' => 'Co się stało',
        'extracted_summary' => 'Podsumowanie ekstrakcji',
        'next_action_title' => 'Następny krok',
        'next_action_text' => 'Sprawdź wyekstrahowane wartości, wprowadź poprawki, a następnie zatwierdź transakcję.',
        'button_review_document' => 'Sprawdź dokument',
        'button_open_documents' => 'Otwórz dokumenty AI',
    ],

    'ai_document_processing_failed' => [
        'subject' => 'Przetwarzanie dokumentu nie powiodło się',
        'intro' => 'Nie udało się przetworzyć dokumentu AI.',
        'document_details' => 'Szczegóły dokumentu',
        'next_action_title' => 'Następny krok',
        'next_action_text' => 'Otwórz dokument, w razie potrzeby popraw własny prompt i uruchom ponowne przetwarzanie.',
        'settings_hint' => 'Jeśli problem się powtarza, sprawdź model AI i dane uwierzytelniające dostawcy.',
        'fallback_reason' => 'Wystąpił nieznany błąd podczas przetwarzania.',
        'button_review_reprocess' => 'Sprawdź i przetwórz ponownie',
        'button_open_settings' => 'Otwórz ustawienia AI',
    ],

    'google_drive_import_success' => [
        'subject' => 'Import z Google Drive zakończony',
        'intro' => 'Import z Google Drive zakończył się pomyślnie.',
        'folder' => 'Folder: :folder',
        'imported' => 'Zaimportowane dokumenty: :count',
        'skipped_existing' => 'Pominięte (już zaimportowane): :count',
        'skipped_unsupported' => 'Pominięte (nieobsługiwany typ): :count',
        'skipped_too_large' => 'Pominięte (zbyt duży plik): :count',
        'failed_downloads' => 'Błędy pobierania: :count',
        'button_open_documents' => 'Otwórz dokumenty AI',
    ],

    'google_drive_import_failed' => [
        'subject' => 'Import z Google Drive nie powiódł się',
        'intro' => 'Proces importu z Google Drive zakończył się błędem.',
        'folder' => 'Folder: :folder',
        'error' => 'Błąd: :error',
        'error_count' => 'Liczba kolejnych błędów: :count',
        'button_open_settings' => 'Otwórz ustawienia profilu',
    ],
];
