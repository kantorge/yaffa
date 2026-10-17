// Run with: node --test resources/js/shared/lib/tom-select/queryString.test.js
// This folder's package.json ({"type":"module"}) lets plain `node --test` load the ESM files,
// same as resources/js/investments/lib.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toQueryString } from './queryString.js';

test('undefined is omitted, so the backend sees the key as absent', () => {
  assert.equal(
    toQueryString({ q: undefined, withInactive: true }),
    'withInactive=true',
  );
});

test('null is sent as an empty value', () => {
  assert.equal(toQueryString({ q: 'x', payee: null }), 'q=x&payee=');
});

test("'*' and '' terms are passed through", () => {
  assert.equal(toQueryString({ q: '*' }), 'q=*');
  assert.equal(toQueryString({ q: '' }), 'q=');
});

test('keys and values are URI-encoded', () => {
  assert.equal(toQueryString({ 'a b': 'c&d=é' }), 'a%20b=c%26d%3D%C3%A9');
});
