const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/views/sales/create.blade.php', 'utf8');
const start = source.indexOf("        document.addEventListener('input', function (event) {\n            if (!event.target.matches('.sale-row .unit-price')");
assert.ok(start > 0);
const batchCode = source.slice(start, source.indexOf('        function addLine()', start));
const validationCode = source.slice(source.indexOf('        function validateRowPricing('), source.indexOf('        function calculateTotals()'));
let inputHandler;
let saleType = 'retail';
let recalculations = 0;
const context = vm.createContext({
    document: { addEventListener: (_, fn) => { inputHandler = fn; }, getElementById: () => ({value: saleType}) },
    isProformaDocument: false,
    calculateTotals: () => recalculations++,
    currentRowPriceFloor: (row, selected = row.batch.options[row.batch.selectedIndex]) => Math.max(Number(selected.dataset.purchasePrice), Number(selected.dataset[saleType + 'Price'])),
    currentRowPurchasePrice: row => Number(row.batch.options[row.batch.selectedIndex].dataset.purchasePrice),
    currentRowPriceFloorLabel: () => 'selling price',
});
vm.runInContext(batchCode + validationCode, context);
function makeRow() {
    const nodes = {};
    for (const name of ['expiry-box', 'available-box', 'reserved-box', 'free-stock-box', 'purchase-price-box', 'unit-price', 'quantity', 'discount-amount', 'product-select']) {
        nodes['.' + name] = {value: '0', classList: {add() {}, remove() {}}};
    }
    nodes['.product-select'].value = '1';
    nodes['.quantity'].value = '1';
    const row = {dataset: {}, querySelector: key => nodes[key], classList: {add() {}, remove() {}}};
    row.batch = {value: 'A', options: [], selectedIndex: 0, closest: () => row};
    nodes['.batch-select'] = row.batch;
    nodes['.unit-price'].closest = () => row;
    nodes['.unit-price'].matches = () => true;
    return row;
}
function select(row, cost, retail, wholesale = retail) {
    row.batch.options = [{value: 'batch', dataset: {purchasePrice: cost, retailPrice: retail, wholesalePrice: wholesale}}];
    context.applyBatchSelection(row.batch);
}
function manual(row, value) {
    row.querySelector('.unit-price').value = String(value);
    inputHandler({target: row.querySelector('.unit-price')});
}
const row = makeRow();
select(row, 800, 1000);
assert.equal(row.querySelector('.unit-price').value, '1000.00');
select(row, 850, 1100);
assert.equal(row.querySelector('.unit-price').value, '1100.00');
manual(row, 1200);
select(row, 900, 1050);
assert.equal(row.querySelector('.unit-price').value, '1200');
select(row, 1300, 1400);
assert.equal(row.querySelector('.unit-price').value, '1200');
assert.equal(row.querySelector('.unit-price').min, '1400.00');
assert.equal(context.validateRowPricing(row).belowPurchaseCost, true);
manual(row, 1500);
row.querySelector('.discount-amount').value = '250';
assert.equal(context.validateRowPricing(row).belowPurchaseCost, true);
row.querySelector('.discount-amount').value = '200';
assert.equal(context.validateRowPricing(row).belowPurchaseCost, false);
saleType = 'wholesale';
select(row, 900, 1200, 1100);
assert.equal(row.querySelector('.unit-price').value, '1100.00');
manual(row, 1250);
select(row, 950, 1300, 1150);
assert.equal(row.querySelector('.unit-price').value, '1250');
const other = makeRow();
select(other, 500, 800, 700);
assert.equal(other.querySelector('.unit-price').value, '700.00');
row.querySelector('.product-select').value = '2';
select(row, 500, 800, 700);
assert.equal(row.querySelector('.unit-price').value, '700.00');
assert.ok(recalculations >= 8);
console.log('PASS: manual prices, automatic prices, higher batch cost, discounts, sale type, product changes and row isolation');
