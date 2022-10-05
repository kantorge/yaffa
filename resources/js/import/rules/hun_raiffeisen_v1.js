// Categorization rule engine functionality
const { Engine, Rule } = require('json-rules-engine')
let engine = new Engine()

// Helper function to find a payee in the global payees array
function findPayee(fact) {
    // Loop all payees and find the one with the same or name as the 'Közlemény/2' field.
    for (let payee of window.payees) {
        let regex = new RegExp(payee.name, 'i');
        if (regex.test(fact)) {
            return payee;
        }
    }

    // Try to loop payee import aliases and find the one with the same or name as the 'Közlemény/2' field.
    for (let payee of window.payees.filter(payee => payee.config.import_alias)) {
        // Payee alias can have multiple values separated by newlines. Loop each item.
        for (let alias of payee.config.import_alias.split('\r\n')) {
            let regex = new RegExp(alias, 'i');
            if (regex.test(fact)) {
                return payee;
            }
        }
    }

    // If no match was found, return the default payee.
    // TODO: This should be a configurable option.
    return window.payees.find(payee => payee.name === 'Egyéb');
}

// Define custom operator for Regex matching
engine.addOperator('matchesRegex', (factValue, jsonValue) => {
    if (!factValue.length) return false

    let regex = new RegExp(jsonValue);

    return regex.test(factValue);
})

// TODO: move common processing rules to common variables

// Rule for card payment
let ruleCardPayment = new Rule({
    conditions: {
        all: [
            {
                fact: 'Típus',
                operator: 'equal',
                value: 'Kártyatranzakció'
            },
            {
                fact: 'Közlemény/3',
                operator: 'matchesRegex',
                value: ' Vásárlás$'
            }
        ]
    },
    event: {
        type: 'Card payment',
        params: {
            processingRules: [
                {
                    transactionField: 'date',
                    customFunction: function (fact, _transaction) {
                        let regex = new RegExp(/\w+ (\d{4})(\d{2})(\d{2}) \w+/);
                        let match = regex.exec(fact['Közlemény/1']);
                        if (match) {
                            return new Date(match[1] + '-' + match[2] + '-' + match[3]);
                        }
                    },
                },
                {
                    transactionField: 'transaction_config_type',
                    customFunction: function (_fact, _transaction) {
                        return 'standard';
                    }
                },
                {
                    transactionField: 'transaction_type_id',
                    customFunction: function (_fact, _transaction) {
                        return 1;
                    }
                },
                {
                    transactionField: 'transaction_type',
                    customFunction: function (_fact, _transaction) {
                        return 'withdrawal';
                    }
                },
                {
                    transactionField: 'transaction_operator',
                    customFunction: function (_fact, _transaction) {
                        return 'minus';
                    }
                },
                {
                    transactionField: 'config.amount_from',
                    customFunction: function (fact, _transaction) {
                        // Amount is in format '-123,45 HUF'. We need the number as a positive number.
                        // TODO: HUF is hardcoded, while it'll be determined by account currency.
                        let regex = new RegExp(/^-((\d+\s)*\d+,\d+) HUF$/);
                        let match = regex.exec(fact['Összeg']);
                        if (match) {
                            return parseFloat(match[1].replace(/\s/g, '').replace(/,/, '.'));
                        }
                    }
                },
                {
                    transactionField: 'config.amount_to',
                    customFunction: function (fact, _transaction) {
                        // Amount is in format '-123,45 HUF'. We need the number as a positive number.
                        // TODO: HUF is hardcoded, while it'll be determined by account currency.
                        let regex = new RegExp(/^-((\d+\s)*\d+,\d+) HUF$/);
                        let match = regex.exec(fact['Összeg']);
                        if (match) {
                            return parseFloat(match[1].replace(/\s/g, '').replace(/,/, '.'));
                        }
                    }
                },
                {
                    transactionField: 'config.account_to',
                    customFunction: function (fact, _transaction) {
                        return findPayee(fact['Közlemény/2']);
                    }
                },
                {
                    transactionField: 'config.account_from',
                    customFunction: function (_fact, _transaction) {
                        // Get ID and name from select2 element
                        return {
                            id: $('#account').val(),
                            name: $('#account option:selected').text(),
                        };
                    }
                },
                {
                    transactionField: 'comment',
                    customFunction: function (fact, transaction) {
                        // If the account_to is the default payee, use the 'Közlemény/2' field as the comment.
                        // TODO: This should be a configurable option.
                        if (transaction.config.account_to.name === 'Egyéb') {
                            return fact['Közlemény/2'];
                        }
                    }
                }
            ]
        }
    }
});
engine.addRule(ruleCardPayment);

// Rule for outgoing wire transfer
let ruleOutgoingWireTransfer = new Rule({
    conditions: {
        all: [
            // Negative amount
            {
                fact: 'Összeg',
                operator: 'matchesRegex',
                value: '^-\\d+'
            },
            // Description
            {
                fact: 'Típus',
                operator: 'in',
                value: [
                    'Elektronikus bankon belüli átutalás',
                    'Elektronikus forint átutalás',
                    'Állandó átutalás',
                    'Csoportos beszedési megbízás',
                    'Forint átutalás',
                ]
            }
        ]
    },
    event: {
        type: 'Outgoing wire transfer',
        params: {
            processingRules: [
                {
                    transactionField: 'date',
                    customFunction: function (fact, _transaction) {
                        let regex = new RegExp(/(\d{4})\.(\d{2})\.(\d{2})\./);
                        let match = regex.exec(fact['Értéknap']);
                        if (match) {
                            return new Date(match[1] + '-' + match[2] + '-' + match[3]);
                        }
                    },
                },
                {
                    transactionField: 'transaction_config_type',
                    customFunction: function (_fact, _transaction) {
                        return 'standard';
                    }
                },
                {
                    transactionField: 'transaction_type_id',
                    customFunction: function (_fact, _transaction) {
                        return 1;
                    }
                },
                {
                    transactionField: 'transaction_type',
                    customFunction: function (_fact, _transaction) {
                        return 'withdrawal';
                    }
                },
                {
                    transactionField: 'transaction_operator',
                    customFunction: function (_fact, _transaction) {
                        return 'minus';
                    }
                },
                {
                    transactionField: 'config.amount_from',
                    customFunction: function (fact, _transaction) {
                        // Amount is in format '-123,45 HUF'. We need the number as a positive number.
                        // TODO: HUF is hardcoded, while it'll be determined by account currency.
                        let regex = new RegExp(/^-((\d+\s)*\d+,\d+) HUF$/);
                        let match = regex.exec(fact['Összeg']);
                        if (match) {
                            return parseFloat(match[1].replace(/\s/g, '').replace(/,/, '.'));
                        }
                    }
                },
                {
                    transactionField: 'config.amount_to',
                    customFunction: function (fact, _transaction) {
                        // Amount is in format '-123,45 HUF'. We need the number as a positive number.
                        // TODO: HUF is hardcoded, while it'll be determined by account currency.
                        let regex = new RegExp(/^-((\d+\s)*\d+,\d+) HUF$/);
                        let match = regex.exec(fact['Összeg']);
                        if (match) {
                            return parseFloat(match[1].replace(/\s/g, '').replace(/,/, '.'));
                        }
                    }
                },
                {
                    transactionField: 'config.account_to',
                    customFunction: function (fact, _transaction) {
                        return findPayee(fact['Közlemény/2']);
                    }
                },
                {
                    transactionField: 'config.account_from',
                    customFunction: function (_fact, _transaction) {
                        // Get ID and name from select2 element
                        return {
                            id: $('#account').val(),
                            name: $('#account option:selected').text(),
                        };
                    }
                },
                {
                    transactionField: 'comment',
                    customFunction: function (fact, transaction) {
                        // If the account_to is the default payee, use the 'Közlemény/2' field as the comment.
                        // TODO: This should be a configurable option.
                        if (transaction.config.account_to.name === 'Egyéb') {
                            return fact['Közlemény/2'];
                        }
                    }
                }
            ]
        }
    }
})
engine.addRule(ruleOutgoingWireTransfer);

// Rule for incoming wire transfer
engine.addRule({
    conditions: {
        all: [
            // Positive  amount
            {
                fact: 'Összeg',
                operator: 'matchesRegex',
                value: '^\\d+'
            },
            // Description
            {
                fact: 'Típus',
                operator: 'in',
                value: [
                    'Forint átutalás',
                    'Deviza átutalás',
                    'Csoportos átutalás jóváírása',
                ]
            }
        ]
    },
    event: {
        type: 'Incoming wire transfer',
        params: {
            processingRules: [
                {
                    transactionField: 'date',
                    customFunction: function (fact, _transaction) {
                        let regex = new RegExp(/(\d{4})\.(\d{2})\.(\d{2})\./);
                        let match = regex.exec(fact['Értéknap']);
                        if (match) {
                            return new Date(match[1] + '-' + match[2] + '-' + match[3]);
                        }
                    },
                },
                {
                    transactionField: 'transaction_config_type',
                    customFunction: function (_fact, _transaction) {
                        return 'standard';
                    }
                },
                {
                    transactionField: 'transaction_type_id',
                    customFunction: function (_fact, _transaction) {
                        return 2;
                    }
                },
                {
                    transactionField: 'transaction_type',
                    customFunction: function (_fact, _transaction) {
                        return 'deposit';
                    }
                },
                {
                    transactionField: 'transaction_operator',
                    customFunction: function (_fact, _transaction) {
                        return 'plus';
                    }
                },
                {
                    transactionField: 'config.amount_from',
                    customFunction: function (fact, _transaction) {
                        // Amount is in format '123,45 HUF'. We need the number as a positive number.
                        // TODO: HUF is hardcoded, while it'll be determined by account currency.
                        let regex = new RegExp(/^((\d+\s)*\d+,\d+) HUF$/);
                        let match = regex.exec(fact['Összeg']);
                        if (match) {
                            return parseFloat(match[1].replace(/\s/g, '').replace(/,/, '.'));
                        }
                    }
                },
                {
                    transactionField: 'config.amount_to',
                    customFunction: function (fact, _transaction) {
                        // Amount is in format '123,45 HUF'. We need the number as a positive number.
                        // TODO: HUF is hardcoded, while it'll be determined by account currency.
                        let regex = new RegExp(/^((\d+\s)*\d+,\d+) HUF$/);
                        let match = regex.exec(fact['Összeg']);
                        if (match) {
                            return parseFloat(match[1].replace(/\s/g, '').replace(/,/, '.'));
                        }
                    }
                },
                {
                    transactionField: 'config.account_from',
                    customFunction: function (fact, _transaction) {
                        return findPayee(fact['Közlemény/2']);
                    }
                },
                {
                    transactionField: 'config.account_to',
                    customFunction: function (_fact, _transaction) {
                        // Get ID and name from select2 element
                        return {
                            id: $('#account').val(),
                            name: $('#account option:selected').text(),
                        };
                    }
                },
                {
                    transactionField: 'comment',
                    customFunction: function (fact, transaction) {
                        // If the account_to is the default payee, use the 'Közlemény/2' field as the comment.
                        // TODO: This should be a configurable option.
                        if (transaction.config.account_to.name === 'Egyéb') {
                            return fact['Közlemény/2'];
                        }
                    }
                }
            ]
        }
    }
})

// Rule transfer for transfer between accounts
let ruleCashWithdrawal = new Rule({
    conditions: {
        any: [
            {
                all: [
                    {
                        fact: 'Típus',
                        operator: 'equal',
                        value: 'Elektronik. saját számlás átvezetés'
                    },
                    {
                        fact: 'Közlemény/3',
                        operator: 'in',
                        value: [
                            'Hitelkártya feltöltés',
                            'Pay off credit card',
                        ]
                    }
                ]
            },
            {
                all: [
                    {
                        fact: 'Típus',
                        operator: 'equal',
                        value: 'Kártyatranzakció'
                    },
                    {
                        fact: 'Közlemény/3',
                        operator: 'matchesRegex',
                        value: ' Kp felvét$'
                    },
                ]
            }
        ]
    },
    event: {
        type: 'Cash withdrawal',
        params: {
            processingRules: [
                {
                    transactionField: 'date',
                    customFunction: function (fact, _transaction) {
                        let regex = new RegExp(/(\d{4})\.(\d{2})\.(\d{2})\./);
                        let match = regex.exec(fact['Értéknap']);
                        if (match) {
                            return new Date(match[1] + '-' + match[2] + '-' + match[3]);
                        }
                    },
                },
                {
                    transactionField: 'transaction_config_type',
                    customFunction: function (_fact, _transaction) {
                        return 'standard';
                    }
                },
                {
                    transactionField: 'transaction_type_id',
                    customFunction: function (_fact, _transaction) {
                        return 3;
                    }
                },
                {
                    transactionField: 'transaction_type',
                    customFunction: function (_fact, _transaction) {
                        return 'transfer';
                    }
                },
                {
                    transactionField: 'transaction_operator',
                    customFunction: function (_fact, _transaction) {
                        return '';
                    }
                },
                {
                    transactionField: 'config.amount_from',
                    customFunction: function (fact, _transaction) {
                        // Amount is in format '-123,45 HUF'. We need the number as a positive number.
                        // TODO: HUF is hardcoded, while it'll be determined by account currency.
                        let regex = new RegExp(/^-((\d+\s)*\d+,\d+) HUF$/);
                        let match = regex.exec(fact['Összeg']);
                        if (match) {
                            return parseFloat(match[1].replace(/\s/g, '').replace(/,/, '.'));
                        }
                    }
                },
                {
                    transactionField: 'config.amount_to',
                    customFunction: function (fact, _transaction) {
                        // Amount is in format '-123,45 HUF'. We need the number as a positive number.
                        // TODO: HUF is hardcoded, while it'll be determined by account currency.
                        let regex = new RegExp(/^-((\d+\s)*\d+,\d+) HUF$/);
                        let match = regex.exec(fact['Összeg']);
                        if (match) {
                            return parseFloat(match[1].replace(/\s/g, '').replace(/,/, '.'));
                        }
                    }
                },
                {
                    transactionField: 'config.account_to',
                    customFunction: function (_fact, _transaction) {
                        return undefined;
                    }
                },
                {
                    transactionField: 'config.account_from',
                    customFunction: function (_fact, _transaction) {
                        // Get ID and name from select2 element
                        return {
                            id: $('#account').val(),
                            name: $('#account option:selected').text(),
                        };
                    }
                },
                {
                    transactionField: 'comment',
                    customFunction: function (fact, _transaction) {
                        return fact['Közlemény/3'];
                    }
                }
            ]
        }
    }
});
engine.addRule(ruleCashWithdrawal);


module.exports = engine;
