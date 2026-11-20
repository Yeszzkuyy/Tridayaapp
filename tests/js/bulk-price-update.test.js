import assert from 'node:assert/strict';
import test from 'node:test';

import { bulkPriceUpdate } from '../../resources/js/bulk-price-update.js';

function row({
    entered = 112000,
    previousNet = 100000,
    supplier = '1',
    originalSupplier = '1',
    validUntil = '2026-12-01',
    originalValidUntil = '2026-12-01',
    includesTax = true,
} = {}) {
    const fields = {
        '[data-bulk-price]': { value: String(entered), required: false },
        '[data-bulk-supplier] input[type="hidden"]': { value: supplier },
        '[data-bulk-valid-until]': { value: validUntil },
        '[data-bulk-includes-tax]': { checked: includesTax },
        '[data-bulk-supplier] [data-glide-mount]': { dataset: {} },
    };

    return {
        dataset: {
            previousNetCost: String(previousNet),
            originalSupplier,
            originalValidUntil,
        },
        fields,
        querySelector(selector) {
            return fields[selector] || null;
        },
        querySelectorAll() {
            return [];
        },
    };
}

test('VAT-inclusive entered cost is compared using its net amount', () => {
    const component = bulkPriceUpdate({ vatRate: 12 });
    const product = row({ entered: 224000, previousNet: 200000 });

    assert.equal(component.newNetCost(product), 200000);
    assert.equal(component.difference(product), 'No change');
});

test('a current VAT-rate change is shown as a price change before saving', () => {
    const component = bulkPriceUpdate({ vatRate: 13 });
    const product = row({ entered: 112000, originalEntered: 112000, previousNet: 100000 });

    assert.equal(component.isChanged(product), true);
    assert.notEqual(component.difference(product), 'No change');
});

test('price difference shows increase/decrease against previous net cost', () => {
    const component = bulkPriceUpdate({
        vatRate: 12,
        labels: { noChange: 'No change' },
    });
    const decreased = row({ entered: 112000, previousNet: 120000 });
    const increased = row({ entered: 224000, previousNet: 180000 });

    assert.equal(component.difference(decreased), '↓ Rp 20.000 (16.7%)');
    assert.equal(component.difference(increased), '↑ Rp 20.000 (11.1%)');
});

test('a product counts once when net price, supplier, or validity changes', () => {
    const component = bulkPriceUpdate({ vatRate: 12 });
    const changedPrice = row({
        entered: 150000,
        previousNet: 100000,
        includesTax: false,
    });
    const changedSupplier = row({ supplier: '2', originalSupplier: '1' });
    const changedDate = row({ validUntil: '2027-01-01' });
    const sameNetWithVat = row({ entered: 112000, previousNet: 100000, includesTax: true });

    assert.equal(component.isChanged(changedPrice), true);
    assert.equal(component.isChanged(changedSupplier), true);
    assert.equal(component.isChanged(changedDate), true);
    assert.equal(component.isChanged(sameNetWithVat), false);
    assert.equal(component.isChanged(row()), false);
});

test('only changed product fields are included in the submitted form data', () => {
    const component = bulkPriceUpdate({ vatRate: 12 });
    const changed = row({ entered: 150000, previousNet: 100000, includesTax: false });
    changed.dataset.productName = 'Changed';
    changed.querySelectorAll = () => [
        { name: 'items[1][product_id]' },
        { name: 'items[1][price]' },
        { name: 'items[1][supplier_id]' },
    ];

    const unchanged = row();
    unchanged.dataset.productName = 'Unchanged';
    unchanged.querySelectorAll = () => [
        { name: 'items[2][product_id]' },
        { name: 'items[2][price]' },
        { name: 'items[2][supplier_id]' },
    ];

    component.$root = {
        querySelectorAll: () => [changed, unchanged],
    };
    const values = new Map([
        ['items[1][product_id]', '1'],
        ['items[1][price]', '150000'],
        ['items[1][supplier_id]', '1'],
        ['items[2][product_id]', '2'],
        ['items[2][price]', '112000'],
        ['items[2][supplier_id]', '1'],
    ]);
    const formData = {
        delete(name) {
            values.delete(name);
        },
    };

    component.stripUnchanged(formData);

    assert.deepEqual([...values.keys()], [
        'items[1][product_id]',
        'items[1][price]',
        'items[1][supplier_id]',
    ]);
});
