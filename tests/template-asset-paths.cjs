const assert = require('node:assert/strict');
const {validar} = require('../public/template-asset-paths.js');
const arquivos = ['index.html', 'style.css', 'script.js', 'assets/logo.png', 'css/tema.css'];
const html = `<link href="{{ asset('style.css') }}">
<script src="{{ asset('js/ausente.js') }}"></script>
<img src="assets/logo.png"><img src="assets/falta.png">
<a href="https://example.com/fora.pdf">Externo</a><a href="/inscricao/1">Rota</a>
<img src="{{ evento.imagem }}"><a href="#secao">Âncora</a>
<!-- <img src="comentado.png"> -->`;
assert.deepEqual(validar(html, 'index.html', arquivos).map(a => [a.linha,a.caminho]), [[2,'js/ausente.js'],[3,'assets/falta.png']]);
assert.deepEqual(validar(`body {background: url('../assets/logo.png')}
@import "ausente.css";
p {background:url(https://example.com/imagem.png)}
a {background:url(data:image/png;base64,abc)}
/* url(falso.png) */`, 'css/tema.css', arquivos).map(a=>[a.linha,a.caminho]), [[2,'css/ausente.css']]);
assert.equal(validar(`<img src="{{ asset('assets/falta.png') }}">`, 'index.html', arquivos).length,1);
assert.equal(validar(`<img src="../assets/logo.png?v=2#img">`, 'paginas/index.html',arquivos).length,0);
assert.match(validar(`<img src="../../fora.png">`,'index.html',arquivos)[0].mensagem,/fora do template/);
assert.equal(validar(`<img\n src="{{\n asset('falta.png')\n }}">`,'index.html',arquivos)[0].linha,2);
assert.equal(validar(`<img src="assets/logo.png">`, 'index.html', arquivos.filter(a=>a!=='assets/logo.png')).length,1);
console.log('OK: assets, linhas, referências relativas, CSS, comentários, URLs externas, caminhos dinâmicos e arquivos removidos.');
