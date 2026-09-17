/* Validação local: usa a lista atual de arquivos, sem requisitar URLs externas. */
(function (root) {
    function validar(codigo, arquivo, arquivos) {
        const existentes = new Set(arquivos);
        const avisos = [], vistos = new Set();
        const tipo = arquivo.split('.').pop().toLowerCase();
        const pasta = arquivo.includes('/') ? arquivo.slice(0, arquivo.lastIndexOf('/') + 1) : '';
        // Mantém os offsets e as linhas ao desconsiderar comentários.
        const texto = codigo.replace(/<!--[\s\S]*?-->|\/\*[\s\S]*?\*\//g, trecho => trecho.replace(/[^\r\n]/g, ' '));
        function conferir(referencia, indice, raiz) {
            let caminho = referencia.trim();
            if (!caminho || /^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i.test(caminho) || /\{\{|\{%|\$\{/.test(caminho)) return;
            // URLs absolutas do site podem ser rotas da aplicação, fora do template.
            if (!raiz && caminho.startsWith('/')) return;
            caminho = caminho.split(/[?#]/)[0];
            try { caminho = decodeURIComponent(caminho); } catch (_) { /* O nome inválido será sinalizado. */ }
            caminho = caminho.replace(/\\/g, '/');
            const partes = (raiz ? caminho.replace(/^\/+/, '') : pasta + caminho).split('/');
            const resolvido = []; let fora = false;
            for (const parte of partes) {
                if (parte === '..') { if (!resolvido.length) fora = true; else resolvido.pop(); }
                else if (parte && parte !== '.') resolvido.push(parte);
            }
            const destino = resolvido.join('/');
            if (!fora && existentes.has(destino)) return;
            const linha = codigo.slice(0, indice).split('\n').length;
            const chave = linha + ':' + referencia;
            if (vistos.has(chave)) return;
            vistos.add(chave);
            avisos.push({linha, referencia, caminho: destino, mensagem: fora ? `Caminho fora do template: ${referencia}` : `Arquivo não encontrado: ${referencia} (destino: /${destino})`});
        }
        for (const match of texto.matchAll(/\{\{\s*asset\(\s*(['"])([^'"]+)\1\s*\)\s*\}\}/g)) {
            conferir(match[2], match.index, true);
        }
        if (['html', 'svg'].includes(tipo)) {
            for (const tag of texto.matchAll(/<[a-z][^>]*>/gi)) {
                for (const attr of tag[0].matchAll(/\b(?:src|href|poster|data-src)\s*=\s*(['"])([\s\S]*?)\1/gi)) {
                    conferir(attr[2], tag.index + attr.index, false);
                }
            }
        }
        if (['css', 'html', 'svg'].includes(tipo)) {
            for (const match of texto.matchAll(/\burl\(\s*(?:'([^']*)'|"([^"]*)"|([^\s)'";]+))\s*\)/gi)) {
                conferir(match[1] ?? match[2] ?? match[3], match.index, false);
            }
            for (const match of texto.matchAll(/@import\s+(['"])([^'"]+)\1/gi)) conferir(match[2], match.index, false);
        }
        return avisos.sort((a, b) => a.linha - b.linha);
    }
    root.TemplateAssetPaths = {validar};
    if (typeof module !== 'undefined' && module.exports) module.exports = {validar};
})(globalThis);
