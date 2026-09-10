const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = vm.createContext({ document: { addEventListener() {} } });
vm.runInContext(fs.readFileSync('public/personalizacao-evento.js', 'utf8'), context);
const select = context.coresFormularioEvento;
assert.equal(select([]), null);
assert.equal(select(['#abcdef', '#ABCDEF']), null);
assert.equal(select(['#INVALID', '#112233']), null);
assert.equal(select(['#EEEEEE', '#FFFFFF']).cor_fonte, '#000000');
assert.equal(select(['#102A43', '#176B87']).cor_fonte, '#FFFFFF');
const palette = ['#101010', '#111111', '#EEEEEE'];
let draws = [0, 0.99];
const result = select(palette, () => draws.shift());
assert.equal(result.degrade_inicio, '#101010');
assert.equal(result.degrade_fim, '#EEEEEE');
assert.equal(result.tipo, 'degrade');
assert.deepEqual(palette, ['#101010', '#111111', '#EEEEEE']);
const pairs = new Set();
for (let first = 0; first < 3; first++) {
    for (let second = 0; second < 2; second++) {
        const draws = [(first + 0.5) / 3, (second + 0.5) / 2];
        const pair = select(palette, () => draws.shift());
        assert.notEqual(pair.degrade_inicio, pair.degrade_fim);
        pairs.add(`${pair.degrade_inicio}/${pair.degrade_fim}`);
    }
}
assert.equal(pairs.size, 6);
console.log('OK: todos os pares sorteáveis, cores distintas, contraste e paleta preservada.');
