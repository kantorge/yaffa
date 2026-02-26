<?php

return [
    'common' => [
        'na' => 'N/A',
        'thanks' => 'Köszönettel,',
    ],

    'labels' => [
        'source_type' => 'Forrás',
        'submitted' => 'Beküldve',
        'processed' => 'Feldolgozva',
        'document_id' => 'Dokumentum azonosító',
        'type' => 'Típus',
        'amount' => 'Összeg',
        'date' => 'Dátum',
        'reason' => 'Ok',
    ],

    'source_types' => [
        'manual_upload' => 'Kézi feltöltés',
        'received_email' => 'Beérkezett e-mail',
        'google_drive' => 'Google Drive',
    ],

    'transaction_types' => [
        'withdrawal' => 'Kiadás',
        'deposit' => 'Bevétel',
        'transfer' => 'Átvezetés',
        'buy' => 'Vétel',
        'sell' => 'Eladás',
        'dividend' => 'Osztalék',
        'interest' => 'Kamat',
        'add_shares' => 'Részvény hozzáadása',
        'remove_shares' => 'Részvény eltávolítása',
    ],

    'ai_document_processed' => [
        'subject' => 'Dokumentum feldolgozva - ellenőrzésre kész',
        'greeting' => 'Kedves :name,',
        'intro' => 'Az AI dokumentum feldolgozása elkészült, ellenőrzésre kész.',
        'what_happened' => 'Mi történt',
        'extracted_summary' => 'Kinyert összefoglaló',
        'next_action_title' => 'Következő lépés',
        'next_action_text' => 'Ellenőrizd a kinyert adatokat, szükség esetén módosítsd, majd véglegesítsd a tranzakciót.',
        'button_review_document' => 'Dokumentum ellenőrzése',
        'button_open_documents' => 'AI dokumentumok megnyitása',
    ],

    'ai_document_processing_failed' => [
        'subject' => 'A dokumentum feldolgozása sikertelen',
        'greeting' => 'Kedves :name,',
        'intro' => 'Az AI dokumentum feldolgozása nem sikerült.',
        'document_details' => 'Dokumentum részletei',
        'next_action_title' => 'Következő lépés',
        'next_action_text' => 'Nyisd meg a dokumentumot, szükség esetén módosítsd az egyéni promptot, majd indítsd újra a feldolgozást.',
        'settings_hint' => 'Ha a hiba továbbra is fennáll, ellenőrizd az AI modell és a szolgáltatói hitelesítő adatok beállítását.',
        'fallback_reason' => 'Ismeretlen hiba történt a feldolgozás során.',
        'button_review_reprocess' => 'Ellenőrzés és újrafeldolgozás',
        'button_open_settings' => 'AI beállítások megnyitása',
    ],
];
