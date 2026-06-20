<?php

namespace App\Services\Import;

class SystemFileImportProfileRegistry
{
    /**
     * @return list<array<string, mixed>>
     */
    public function profiles(): array
    {
        return [
            [
                'key' => 'hun_raiffeisen_v1',
                'type' => 'system',
                'file_type' => 'csv',
                'name' => 'Raiffeisen Hungary v1',
                'delimiter' => ';',
                'has_header_row' => true,
                'date_format' => 'Y.m.d.',
                'decimal_separator' => ',',
                'thousand_separator' => ' ',
                'sign_handling' => 'as_is',
                'mapping_json' => [
                    'Értéknap' => 'value_date',
                    'Összeg' => 'amount',
                    'Típus' => 'entry_type',
                    'Közlemény/1' => 'notice_1',
                    'Közlemény/2' => 'notice_2',
                    'Közlemény/3' => 'notice_3',
                ],
                'options_json' => [
                    'metadata' => [
                        'country' => 'HU',
                        'institution' => 'Raiffeisen',
                        'language' => 'hu',
                    ],
                    'parser_settings' => [
                        'trim_strings' => true,
                        'skip_empty_rows' => true,
                        'date_parsing_formats' => ['Y.m.d.', 'Ymd'],
                    ],
                    'matching_rules' => [
                        [
                            'name' => 'Card payment entries',
                            'conditions' => [
                                'all' => [
                                    ['fact' => 'entry_type', 'operator' => 'equal', 'value' => 'Kártyatranzakció'],
                                    ['fact' => 'notice_3', 'operator' => 'matches_regex', 'value' => 'Vásárlás$'],
                                ],
                            ],
                            'actions' => [
                                ['type' => 'set', 'target' => 'config_type', 'value' => 'standard'],
                                ['type' => 'set', 'target' => 'transaction_type', 'value' => 'withdrawal'],
                                ['type' => 'map_transform', 'target' => 'date', 'source' => 'notice_1', 'transform' => 'extract_date_regex', 'args' => ['pattern' => '(\d{4})(\d{2})(\d{2})']],
                                ['type' => 'map_transform', 'target' => 'amount', 'source' => 'amount', 'transform' => 'parse_localized_amount', 'args' => ['absolute_value' => true]],
                                ['type' => 'map_transform', 'target' => 'payee', 'source' => 'notice_2', 'transform' => 'normalize_whitespace'],
                                ['type' => 'copy', 'target' => 'memo', 'source' => 'notice_2'],
                                ['type' => 'apply_transform', 'target' => 'config.account_from_id', 'transform' => 'selected_account_context'],
                                ['type' => 'map_transform', 'target' => 'config.account_to_id', 'source' => 'notice_2', 'transform' => 'resolve_payee_by_name_or_alias', 'args' => ['fallback_payee_name' => 'Egyéb']],
                                ['type' => 'copy', 'target' => 'config.amount_from', 'source' => 'amount'],
                                ['type' => 'copy', 'target' => 'config.amount_to', 'source' => 'amount'],
                            ],
                        ],
                        [
                            'name' => 'Outgoing transfer-like entries',
                            'conditions' => [
                                'all' => [
                                    ['fact' => 'amount', 'operator' => 'starts_with', 'value' => '-'],
                                    ['fact' => 'entry_type', 'operator' => 'in', 'value' => [
                                        'Elektronikus bankon belüli átutalás',
                                        'Elektronikus forint átutalás',
                                        'Állandó átutalás',
                                        'Csoportos beszedési megbízás',
                                        'Forint átutalás',
                                    ]],
                                ],
                            ],
                            'actions' => [
                                ['type' => 'set', 'target' => 'config_type', 'value' => 'standard'],
                                ['type' => 'set', 'target' => 'transaction_type', 'value' => 'withdrawal'],
                                ['type' => 'map_transform', 'target' => 'date', 'source' => 'value_date', 'transform' => 'parse_date', 'args' => ['format' => 'Y.m.d.']],
                                ['type' => 'map_transform', 'target' => 'amount', 'source' => 'amount', 'transform' => 'parse_localized_amount', 'args' => ['absolute_value' => true]],
                                ['type' => 'map_transform', 'target' => 'payee', 'source' => 'notice_2', 'transform' => 'normalize_whitespace'],
                                ['type' => 'copy', 'target' => 'memo', 'source' => 'notice_3'],
                                ['type' => 'apply_transform', 'target' => 'config.account_from_id', 'transform' => 'selected_account_context'],
                                ['type' => 'map_transform', 'target' => 'config.account_to_id', 'source' => 'notice_2', 'transform' => 'resolve_payee_by_name_or_alias', 'args' => ['fallback_payee_name' => 'Egyéb']],
                                ['type' => 'copy', 'target' => 'config.amount_from', 'source' => 'amount'],
                                ['type' => 'copy', 'target' => 'config.amount_to', 'source' => 'amount'],
                            ],
                        ],
                        [
                            'name' => 'Incoming transfer-like entries',
                            'conditions' => [
                                'all' => [
                                    ['fact' => 'amount', 'operator' => 'amount_sign_is', 'value' => 'positive'],
                                    ['fact' => 'entry_type', 'operator' => 'in', 'value' => [
                                        'Forint átutalás',
                                        'Deviza átutalás',
                                        'Csoportos átutalás jóváírása',
                                    ]],
                                ],
                            ],
                            'actions' => [
                                ['type' => 'set', 'target' => 'config_type', 'value' => 'standard'],
                                ['type' => 'set', 'target' => 'transaction_type', 'value' => 'deposit'],
                                ['type' => 'map_transform', 'target' => 'date', 'source' => 'value_date', 'transform' => 'parse_date', 'args' => ['format' => 'Y.m.d.']],
                                ['type' => 'map_transform', 'target' => 'amount', 'source' => 'amount', 'transform' => 'parse_localized_amount', 'args' => ['absolute_value' => true]],
                                ['type' => 'map_transform', 'target' => 'payee', 'source' => 'notice_2', 'transform' => 'normalize_whitespace'],
                                ['type' => 'copy', 'target' => 'memo', 'source' => 'notice_3'],
                                ['type' => 'apply_transform', 'target' => 'config.account_to_id', 'transform' => 'selected_account_context'],
                                ['type' => 'map_transform', 'target' => 'config.account_from_id', 'source' => 'notice_2', 'transform' => 'resolve_payee_by_name_or_alias', 'args' => ['fallback_payee_name' => 'Egyéb']],
                                ['type' => 'copy', 'target' => 'config.amount_from', 'source' => 'amount'],
                                ['type' => 'copy', 'target' => 'config.amount_to', 'source' => 'amount'],
                            ],
                        ],
                        [
                            'name' => 'Cash withdrawal and internal transfer entries',
                            'conditions' => [
                                'any' => [
                                    [
                                        'all' => [
                                            ['fact' => 'entry_type', 'operator' => 'equal', 'value' => 'Elektronik. saját számlás átvezetés'],
                                            ['fact' => 'notice_3', 'operator' => 'in', 'value' => [
                                                'Hitelkártya feltöltés',
                                                'Pay off credit card',
                                            ]],
                                        ],
                                    ],
                                    [
                                        'all' => [
                                            ['fact' => 'entry_type', 'operator' => 'equal', 'value' => 'Kártyatranzakció'],
                                            ['fact' => 'notice_3', 'operator' => 'matches_regex', 'value' => 'Kp felvét$'],
                                        ],
                                    ],
                                ],
                            ],
                            'actions' => [
                                ['type' => 'set', 'target' => 'config_type', 'value' => 'standard'],
                                ['type' => 'set', 'target' => 'transaction_type', 'value' => 'transfer'],
                                ['type' => 'map_transform', 'target' => 'date', 'source' => 'value_date', 'transform' => 'parse_date', 'args' => ['format' => 'Y.m.d.']],
                                ['type' => 'map_transform', 'target' => 'amount', 'source' => 'amount', 'transform' => 'parse_localized_amount', 'args' => ['absolute_value' => true]],
                                ['type' => 'apply_transform', 'target' => 'config.account_from_id', 'transform' => 'selected_account_context'],
                                ['type' => 'copy', 'target' => 'memo', 'source' => 'notice_3'],
                                ['type' => 'copy', 'target' => 'config.amount_from', 'source' => 'amount'],
                                ['type' => 'copy', 'target' => 'config.amount_to', 'source' => 'amount'],
                            ],
                        ],
                    ],
                    'defaults' => [
                        'config_type' => 'standard',
                    ],
                    'warnings' => [
                        'unmatched_row' => 'No matching system rule was found for this row.',
                    ],
                ],
                'active' => true,
            ],
            [
                'key' => 'qif_swap_p_m_v1',
                'type' => 'system',
                'file_type' => 'qif',
                'name' => 'QIF – Swapped P/M fields (P=type, M=payee)',
                'delimiter' => null,
                'has_header_row' => false,
                'date_format' => null,
                'decimal_separator' => null,
                'thousand_separator' => null,
                'sign_handling' => null,
                'mapping_json' => null,
                'options_json' => [
                    'field_map' => [
                        'payee' => 'M',
                        'comment' => 'P',
                    ],
                    'amount_sign' => 'normal',
                ],
                'active' => true,
            ],
        ];
    }
}
