import assert from 'node:assert/strict';
import test from 'node:test';

import {
    backwardDelete,
    formatForInput,
    maskInput,
    parseToIso,
} from '../../resources/js/thai-date.js';

test('parseToIso accepts supported Thai date formats', () => {
    assert.equal(parseToIso('23/06/2569'), '2026-06-23');
    assert.equal(parseToIso('23/06/2026'), '2026-06-23');
    assert.equal(parseToIso('23-06-2569'), '2026-06-23');
    assert.equal(parseToIso('23062569'), '2026-06-23');
    assert.equal(parseToIso('2026-06-23'), '2026-06-23');
});

test('parseToIso rejects invalid dates and unsupported years', () => {
    assert.equal(parseToIso('31/02/2569'), null);
    assert.equal(parseToIso('23/06/2200'), null);
    assert.equal(parseToIso(''), null);
});

test('formatForInput displays Buddhist years and preserves invalid input', () => {
    assert.equal(formatForInput('2026-06-23'), '23/06/2569');
    assert.equal(formatForInput('23-06-2026'), '23/06/2569');
    assert.equal(formatForInput('not-a-date'), 'not-a-date');
});

test('maskInput inserts separators while typing', () => {
    assert.equal(maskInput('1'), '1');
    assert.equal(maskInput('12'), '12/');
    assert.equal(maskInput('1206'), '12/06/');
    assert.equal(maskInput('12062569'), '12/06/2569');
    assert.equal(maskInput('2026-06-23'), '23/06/2569');
});

test('backwardDelete removes the preceding digit at an automatic separator', () => {
    assert.deepEqual(backwardDelete('12/', 3), {
        value: '1',
        selectionStart: 1,
        selectionEnd: 1,
    });
    assert.deepEqual(backwardDelete('12/06/2569', 3), {
        value: '1/06/2569',
        selectionStart: 1,
        selectionEnd: 1,
    });
    assert.deepEqual(backwardDelete('12/06/2569', 6), {
        value: '12/0/2569',
        selectionStart: 4,
        selectionEnd: 4,
    });
});

test('backwardDelete leaves selections and ordinary positions to the browser', () => {
    assert.equal(backwardDelete('12/06/2569', 3, 4), null);
    assert.equal(backwardDelete('12/06/2569', 2), null);
    assert.equal(backwardDelete('1', 1), null);
});
