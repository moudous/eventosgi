// NODE_PATH=/tmp/eventos-builder-tests/node_modules node tests/distribuicao-vagas-ui.cjs
const {JSDOM} = require('jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');

const html = '<input id="limite_inscricoes" value="12"><div id="builderRows"></div><div id="builderEmpty"></div><button id="addField"></button><div id="criteriosVagas"></div><div id="criteriosVagasVazio"></div>';
const dom = new JSDOM(html, {runScripts:'outside-only'});
const w = dom.window, d = w.document;
w.eval(`window.initial={criterios_vagas:['periodo','sexo'],campos:[
 {nome:'periodo',label:'Período',tipo:'select',criterio_vagas:true,opcoes:[{valor:'manha',texto:'Manhã',percentual_vagas:25},{valor:'tarde',texto:'Tarde',percentual_vagas:25},{valor:'noite',texto:'Noite',percentual_vagas:25}]},
 {nome:'sexo',label:'Sexo',tipo:'select',criterio_vagas:true,opcoes:[{valor:'F',texto:'Feminino',percentual_vagas:66.6667},{valor:'M',texto:'Masculino',percentual_vagas:33.3333}]},
 {nome:'oficinas',label:'Inscrever na oficina',tipo:'checkbox',criterio_vagas:true,obrigatorio:false,percentual_vagas:25,opcoes:[]}
]}`);
w.eval(fs.readFileSync('public/js/formulario-estrutura.js','utf8'));

const criterios = [...d.querySelectorAll('.criterion-order')];
assert.equal(criterios.length, 2);
assert(criterios.every(select => select.required));
assert.equal(w.readBuilderCriteria().join(','), 'periodo,sexo');
assert(criterios[1].querySelector(`option[value="${criterios[0].value}"]`).disabled);
assert.match(d.querySelector('.field .option-quota-number').textContent, /^3 vaga/);
assert.match(d.querySelectorAll('.field')[1].querySelector('.option-quota-number').textContent, /^2 vaga/);
const campoCheckbox = d.querySelectorAll('.field')[2];
assert.equal(campoCheckbox.querySelector('.f-criterion').checked, true);
assert.equal(campoCheckbox.querySelector('.f-required').checked, false);
assert.equal(campoCheckbox.querySelector('.f-required').disabled, false);
assert.equal(campoCheckbox.querySelector('.f-field-percent').required, true);
assert.equal(campoCheckbox.querySelector('.f-field-percent').value, '25%');
assert.match(campoCheckbox.querySelector('.field-quota-number').textContent, /^3 vaga/);
assert.equal(w.readBuilderCriteria().includes('oficinas'), false);

const percentual = d.querySelector('.option-percent');
percentual.value = '3';
percentual.dispatchEvent(new w.Event('focusout', {bubbles:true}));
assert.equal(percentual.value, '25%');
percentual.value = '13';
percentual.dispatchEvent(new w.Event('focusout', {bubbles:true}));
assert(percentual.validationMessage.includes('máximo 12'));

const campoPeriodo = d.querySelector('.field');
campoPeriodo.querySelector('.f-criterion').click();
campoPeriodo.querySelector('.f-criterion').dispatchEvent(new w.Event('change', {bubbles:true}));
assert.equal(d.querySelectorAll('.criterion-order').length, 1);
assert.equal(w.readBuilderCriteria()[0], 'sexo');

console.log('OK: percentuais, conversão, cotas hierárquicas e reservas independentes de checkbox.');
