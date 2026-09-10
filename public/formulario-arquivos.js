function inicializarCampoDeArquivos(campo) {
    const lista = campo.querySelector('[data-anexos-lista]');
    const adicionar = campo.querySelector('[data-adicionar-arquivo]');
    const limite = campo.querySelector('[data-anexos-limite]');
    const maximo = Number(campo.dataset.maximo) || 1;
    const obrigatorio = campo.dataset.obrigatorio === '1';
    const urls = new Set();

    const iconeDoArquivo = arquivo => {
        const extensao = arquivo.name.split('.').pop()?.toLowerCase() || '';
        if (arquivo.type === 'application/pdf' || extensao === 'pdf') return ['bi-file-earmark-pdf-fill', 'text-danger'];
        if (['doc', 'docx', 'odt', 'rtf'].includes(extensao)) return ['bi-file-earmark-word-fill', 'text-primary'];
        if (['xls', 'xlsx', 'ods', 'csv'].includes(extensao)) return ['bi-file-earmark-excel-fill', 'text-success'];
        if (['ppt', 'pptx', 'odp'].includes(extensao)) return ['bi-file-earmark-ppt-fill', 'text-warning'];
        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(extensao)) return ['bi-file-earmark-zip-fill', 'text-secondary'];
        if (arquivo.type.startsWith('audio/')) return ['bi-file-earmark-music-fill', 'text-info'];
        if (arquivo.type.startsWith('video/')) return ['bi-file-earmark-play-fill', 'text-primary'];
        if (['txt', 'md'].includes(extensao) || arquivo.type.startsWith('text/')) return ['bi-file-earmark-text-fill', 'text-secondary'];
        return ['bi-file-earmark-fill', 'text-secondary'];
    };

    const quantidade = () => lista.querySelectorAll('[data-anexo-item]').length;
    const atualizar = () => {
        const total = quantidade();
        adicionar.hidden = total >= maximo;
        limite.textContent = total >= maximo
            ? `Limite de ${maximo} ${maximo === 1 ? 'arquivo atingido' : 'arquivos atingido'}.`
            : `${total} de ${maximo} ${maximo === 1 ? 'arquivo selecionado' : 'arquivos selecionados'}.`;
        campo.classList.toggle('is-invalid', obrigatorio && total !== maximo && campo.dataset.validado === '1');
    };

    const remover = item => {
        const url = item.dataset.previewUrl;
        if (url) {
            URL.revokeObjectURL(url);
            urls.delete(url);
        }
        item.remove();
        atualizar();
    };

    const incluir = (arquivo, input) => {
        const item = document.createElement('div');
        item.className = 'col-12';
        item.dataset.anexoItem = '';
        item.innerHTML = '<div class="anexo-preview border rounded-3 p-2 d-flex align-items-center gap-3"><div data-visual></div><div class="anexo-nome flex-grow-1"><strong class="d-block"></strong><span class="small text-muted"></span></div><button type="button" class="btn btn-sm btn-outline-danger" aria-label="Remover arquivo"><i class="bi bi-trash" aria-hidden="true"></i></button></div>';
        input.hidden = true;
        item.append(input);
        item.querySelector('strong').textContent = arquivo.name;
        item.querySelector('.small').textContent = arquivo.size < 1048576
            ? `${Math.max(1, Math.round(arquivo.size / 1024))} KB`
            : `${(arquivo.size / 1048576).toFixed(1)} MB`;

        const visual = item.querySelector('[data-visual]');
        const extensao = arquivo.name.split('.').pop()?.toLowerCase() || '';
        if (arquivo.type.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'heic'].includes(extensao)) {
            const url = URL.createObjectURL(arquivo);
            urls.add(url);
            item.dataset.previewUrl = url;
            const imagem = document.createElement('img');
            imagem.className = 'anexo-miniatura';
            imagem.src = url;
            imagem.alt = `Miniatura de ${arquivo.name}`;
            visual.append(imagem);
        } else {
            const [icone, cor] = iconeDoArquivo(arquivo);
            const visualIcone = document.createElement('span');
            visualIcone.className = `anexo-icone ${cor}`;
            visualIcone.setAttribute('aria-hidden', 'true');
            visualIcone.innerHTML = `<i class="bi ${icone}"></i>`;
            visual.append(visualIcone);
        }

        item.querySelector('button').addEventListener('click', () => remover(item));
        lista.append(item);
        campo.dataset.validado = '1';
        atualizar();
    };

    adicionar.addEventListener('click', () => {
        if (quantidade() >= maximo) return;
        const input = document.createElement('input');
        input.type = 'file';
        input.name = campo.dataset.nome;
        input.accept = campo.dataset.accept || '';
        input.addEventListener('change', () => {
            const arquivo = input.files?.[0];
            if (arquivo) incluir(arquivo, input);
        }, { once: true });
        input.click();
    });

    campo.closest('form')?.addEventListener('submit', evento => {
        campo.dataset.validado = '1';
        atualizar();
        if (obrigatorio && quantidade() !== maximo) {
            evento.preventDefault();
            adicionar.focus();
        }
    });
    window.addEventListener('pagehide', () => urls.forEach(url => URL.revokeObjectURL(url)), { once: true });
    atualizar();
}
