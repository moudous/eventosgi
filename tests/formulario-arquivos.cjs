// NODE_PATH=/tmp/eventos-builder-tests/node_modules node tests/formulario-arquivos.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

const dom = new JSDOM(`
    <form>
        <div data-anexos-campo data-nome="documentos[]" data-maximo="2" data-accept=".jpg,.pdf" data-obrigatorio="1">
            <div data-anexos-lista></div>
            <button type="button" data-adicionar-arquivo>Adicionar arquivo</button>
            <div data-anexos-limite></div>
            <div data-anexos-erro></div>
        </div>
    </form>
`, { runScripts: 'outside-only' });
const { window } = dom;
const { document } = window;
let proximoArquivo;
let numeroUrl = 0;
window.URL.createObjectURL = () => `blob:teste-${++numeroUrl}`;
window.URL.revokeObjectURL = () => {};
window.HTMLInputElement.prototype.click = function () {
    if (this.type !== 'file' || !proximoArquivo) return;
    Object.defineProperty(this, 'files', { configurable: true, value: [proximoArquivo] });
    proximoArquivo = null;
    this.dispatchEvent(new window.Event('change'));
};
window.eval(fs.readFileSync('public/formulario-arquivos.js', 'utf8'));

const campo = document.querySelector('[data-anexos-campo]');
const botao = campo.querySelector('[data-adicionar-arquivo]');
window.inicializarCampoDeArquivos(campo);

proximoArquivo = new window.File(['imagem'], 'foto.jpg', { type: 'image/jpeg' });
botao.click();
assert.equal(campo.querySelectorAll('[data-anexo-item]').length, 1);
assert.equal(campo.querySelector('img')?.alt, 'Miniatura de foto.jpg');
assert.equal(botao.hidden, false);
document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
assert(campo.classList.contains('is-invalid'));

proximoArquivo = new window.File(['pdf'], 'trabalho.pdf', { type: 'application/pdf' });
botao.click();
assert(campo.querySelector('.bi-file-earmark-pdf-fill'));
assert.equal(botao.hidden, true);
assert.equal(campo.querySelectorAll('input[name="documentos[]"]').length, 2);
assert.equal(campo.classList.contains('is-invalid'), false);

campo.querySelector('[data-anexo-item] button').click();
assert.equal(campo.querySelectorAll('[data-anexo-item]').length, 1);
assert.equal(botao.hidden, false);

campo.querySelector('[data-anexo-item] button').click();
document.querySelector('form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
assert(campo.classList.contains('is-invalid'));
console.log('OK: miniatura, ícone, limite, remoção, reexibição do botão e campo obrigatório.');
