const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const partial = fs.readFileSync('resources/views/sales/_saved_price_validation.blade.php', 'utf8');
const matcher = partial.slice(partial.indexOf('function savedInvoicePriceMatches'));
for (const file of ['edit', 'edit_approved']) {
    const source = fs.readFileSync(`resources/views/sales/${file}.blade.php`, 'utf8');
    const floor = source.slice(source.indexOf('        function currentRowPriceFloor('), source.indexOf('        function currentRowPriceFloorLabel('));
    let saleType = 'wholesale';
    let cost = 450;
    const nodes = {'.product-select': {value: '1'}, '.unit-price': {value: '500'}, '.batch-select': {value: '2', selectedIndex: 0, options: [{value: '2'}]}};
    const row = {querySelector: key => nodes[key]};
    const rows = [row];
    const ctx = vm.createContext({
        savedInvoicePrices: [{product: '1', batch: '2', price: 500}], savedInvoiceSaleType: 'wholesale',
        document: {getElementById: () => ({value: saleType}), querySelectorAll: () => rows},
        isProformaDocument: false,
        currentSellingPriceForOption: () => 600,
        currentRowPurchasePrice: () => cost,
    });
    vm.runInContext(matcher + floor, ctx);
    assert.equal(ctx.currentRowPriceFloor(row), 500, file + ': saved price accepted');
    nodes['.unit-price'].value = '520';
    assert.equal(ctx.currentRowPriceFloor(row), 600, file + ': changed price checked');
    nodes['.unit-price'].value = '500';
    cost = 550;
    assert.equal(ctx.currentRowPriceFloor(row), 550, file + ': cost still enforced');
    cost = 450;
    saleType = 'retail';
    assert.equal(ctx.currentRowPriceFloor(row), 600);
    saleType = 'wholesale';
    nodes['.batch-select'].value = '3';
    assert.equal(ctx.currentRowPriceFloor(row), 600);
    nodes['.batch-select'].value = '2';
    nodes['.product-select'].value = '9';
    assert.equal(ctx.currentRowPriceFloor(row), 600);
    nodes['.product-select'].value = '1';
    const duplicate = {querySelector: key => nodes[key]};
    rows.push(duplicate);
    assert.equal(ctx.currentRowPriceFloor(row), 500);
    assert.equal(ctx.currentRowPriceFloor(duplicate), 600, file + ': cannot reuse exception for extra line');
    assert.ok(source.includes('unitPriceInput.min = minimumAllowedPrice.toFixed(2)'));
}
console.log('PASS: pending/approved saved-price floors, changed prices, cost increases, sale types, changed products/batches and duplicate lines');
