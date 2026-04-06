<?php

return [
    "ai_document_processed" => [
        "button_open_documents" => "AI dokumentumok megnyitása",
        "button_review_document" => "Dokumentum ellenőrzése",
        "extracted_summary" => "Kinyert összefoglaló",
        "intro" => "Az AI dokumentum feldolgozása elkészült, ellenőrzésre kész.",
        "next_action_text" => "Ellenőrizd a kinyert adatokat, szükség esetén módosítsd, majd véglegesítsd a tranzakciót.",
        "next_action_title" => "Következő lépés",
        "subject" => "Dokumentum feldolgozva - ellenőrzésre kész",
        "what_happened" => "Mi történt"
    ],
    "ai_document_processing_failed" => [
        "button_open_settings" => "AI beállítások megnyitása",
        "button_review_reprocess" => "Ellenőrzés és újrafeldolgozás",
        "document_details" => "Dokumentum részletei",
        "fallback_reason" => "Ismeretlen hiba történt a feldolgozás során.",
        "intro" => "Az AI dokumentum feldolgozása nem sikerült.",
        "next_action_text" => "Nyisd meg a dokumentumot, szükség esetén módosítsd az egyéni promptot, majd indítsd újra a feldolgozást.",
        "next_action_title" => "Következő lépés",
        "settings_hint" => "Ha a hiba továbbra is fennáll, ellenőrizd az AI modell és a szolgáltatói hitelesítő adatok beállítását.",
        "subject" => "A dokumentum feldolgozása sikertelen"
    ],
    "common" => [
        "greeting" => "Kedves :name,",
        "na" => "N/A",
        "thanks" => "Köszönettel,"
    ],
    "google_drive_import_failed" => [
        "button_open_settings" => "Profilbeállítások megnyitása",
        "error" => "Hiba: :error",
        "error_count" => "Egymást követő hibák száma: :count",
        "folder" => "Mappa: :folder",
        "intro" => "A Google Drive importálási folyamat sikertelen volt.",
        "subject" => "Google Drive import sikertelen"
    ],
    "google_drive_import_success" => [
        "button_open_documents" => "AI dokumentumok megnyitása",
        "failed_downloads" => "Letöltési hibák: :count",
        "folder" => "Mappa: :folder",
        "imported" => "Importált dokumentumok: :count",
        "intro" => "A Google Drive import sikeresen befejeződött.",
        "skipped_existing" => "Kihagyva (már importálva): :count",
        "skipped_too_large" => "Kihagyva (túl nagy fájl): :count",
        "skipped_unsupported" => "Kihagyva (nem támogatott típus): :count",
        "subject" => "Google Drive import befejezve"
    ],
    "labels" => [
        "amount" => "Összeg",
        "date" => "Dátum",
        "document_id" => "Dokumentum azonosító",
        "processed" => "Feldolgozva",
        "reason" => "Ok",
        "source_type" => "Forrás",
        "submitted" => "Beküldve",
        "type" => "Típus"
    ],
    "source_types" => [
        "google_drive" => "Google Drive",
        "manual_upload" => "Kézi feltöltés",
        "received_email" => "Beérkezett e-mail"
    ],
    "transaction_types" => [
        "add_shares" => "Részvény hozzáadása",
        "buy" => "Vétel",
        "deposit" => "Bevétel",
        "dividend" => "Osztalék",
        "interest" => "Kamat",
        "remove_shares" => "Részvény eltávolítása",
        "sell" => "Eladás",
        "transfer" => "Átvezetés",
        "withdrawal" => "Kiadás"
    ]
];
