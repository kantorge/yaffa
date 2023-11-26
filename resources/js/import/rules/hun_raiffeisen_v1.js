// Categorization rule engine functionality
const { Engine } = require('json-rules-engine')
let engine = new Engine()

/**
 * Helper function to find a payee in the global payees array.
 *
 * @param {string} fact
 * @param {string} fallbackPayeeName
 * @returns {Object}
 */
function findPayee(fact, fallbackPayeeName) {
    // Loop all payees and find the one with the same or name as the provided string.
    for (let payee of window.payees) {
        let regex = new RegExp(payee.name, 'i');
        if (regex.test(fact)) {
            return payee;
        }
    }

    // Try to loop payee import aliases and find the one with the same or name as the provided string.
    for (let payee of window.payees.filter(payee => payee.alias)) {
        // Payee alias can have multiple values separated by newlines. Loop each item.
        for (let alias of payee.alias.split('\r\n')) {
            let regex = new RegExp(alias, 'i');
            if (regex.test(fact)) {
                return payee;
            }
        }
    }

    // If no match was found, return the default payee.
    return window.payees.find(payee => payee.name === fallbackPayeeName);
}

/**
 * Define custom operator for Regex matching
 *
 * @param {string} factValue Value to be tested
 * @param {string} regexString Regex pattern
 * @returns {boolean}
 */
const operatorMatchRegex = function (factValue, regexString) {
    if (!factValue.length) return false;

    let regex = new RegExp(regexString);

    return regex.test(factValue);
};
engine.addOperator('matchesRegex', operatorMatchRegex)

// Rule for card payment
engine.addRule({
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
                    customValue: 'standard',
                },
                {
                    transactionField: 'transaction_type_id',
                    customValue: 1,
                },
                {
                    transactionField: 'transaction_type.name',
                    customValue: 'withdrawal',
                },
                {
                    transactionField: 'transaction_type.amount_operator',
                    customValue: 'minus',
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
                        return findPayee(fact['Közlemény/2'], 'Egyéb');
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
                        if (transaction.config.account_to?.name === 'Egyéb') {
                            return fact['Közlemény/2'];
                        }
                    }
                }
            ]
        }
    }
});

// Rule for outgoing wire transfer
engine.addRule({
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
                    customValue: 'standard',
                },
                {
                    transactionField: 'transaction_type_id',
                    customValue: 1,
                },
                {
                    transactionField: 'transaction_type.name',
                    customValue: 'withdrawal',
                },
                {
                    transactionField: 'transaction_type.amount_operator',
                    customValue: 'minus',
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
                        return findPayee(fact['Közlemény/2'], 'Egyéb');
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
                        if (transaction.config.account_to?.name === 'Egyéb') {
                            return fact['Közlemény/2'];
                        }
                    }
                }
            ]
        }
    }
});

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
                    customValue: 'standard',
                },
                {
                    transactionField: 'transaction_type_id',
                    customValue: 2,
                },
                {
                    transactionField: 'transaction_type.name',
                    customValue: 'deposit',
                },
                {
                    transactionField: 'transaction_type.amount_operator',
                    customValue: 'plus',
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
                        return findPayee(fact['Közlemény/2'], 'Egyéb');
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
                        if (transaction.config.account_to?.name === 'Egyéb') {
                            return fact['Közlemény/2'];
                        }
                    }
                }
            ]
        }
    }
})

// Rule transfer for transfer between accounts
engine.addRule({
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
                    customValue: 'standard',
                },
                {
                    transactionField: 'transaction_type_id',
                    customValue: 3,
                },
                {
                    transactionField: 'transaction_type.name',
                    customValue: 'transfer',
                },
                {
                    transactionField: 'transaction_type.amount_operator',
                    customValue: '',
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
                    customValue: undefined,
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

module.exports = engine;
