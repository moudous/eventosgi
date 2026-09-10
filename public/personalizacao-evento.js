// Sorteia duas cores distintas da paleta e ajusta o contraste da fonte.
function coresFormularioEvento(paleta, random = Math.random) {
    const cores = [...new Set(paleta.filter(cor => /^#[0-9a-f]{6}$/i.test(cor)).map(cor => cor.toUpperCase()))];
    if (cores.length < 2) return null;
    const rgb = cor => [1, 3, 5].map(inicio => parseInt(cor.slice(inicio, inicio + 2), 16));
    const primeira = Math.floor(random() * cores.length);
    const restantes = cores.filter((_, indice) => indice !== primeira);
    const par = [cores[primeira], restantes[Math.floor(random() * restantes.length)]];
    const luminancia = canais => canais.map(canal => {
        const valor = canal / 255;
        return valor <= 0.04045 ? valor / 12.92 : ((valor + 0.055) / 1.055) ** 2.4;
    }).reduce((soma, valor, k) => soma + valor * [0.2126, 0.7152, 0.0722][k], 0);
    const inicio = rgb(par[0]), fim = rgb(par[1]);
    const luz = Array.from({ length: 21 }, (_, i) => luminancia(inicio.map((canal, k) => canal + (fim[k] - canal) * i / 20)));
    const contrasteEscuro = Math.min(...luz.map(valor => (valor + 0.05) / 0.05));
    const contrasteClaro = Math.min(...luz.map(valor => 1.05 / (valor + 0.05)));
    return { tipo: 'degrade', degrade_inicio: par[0], degrade_fim: par[1], cor_solida: par[0], cor_fonte: contrasteEscuro >= contrasteClaro ? '#000000' : '#FFFFFF' };
}

document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('recorteFundo');
    if (!modalElement) return;
    const modal = new bootstrap.Modal(modalElement);
    const canvas = document.getElementById('recorteCanvas');
    const zoom = document.getElementById('recorteZoom');
    const x = document.getElementById('recorteX');
    const y = document.getElementById('recorteY');
    const confirm = document.getElementById('aplicarRecorte');
    let current = null, image = null, sourceUrl = null;
    document.querySelectorAll('.color-pareada').forEach(group => {
        const picker = group.querySelector('[data-color-picker]');
        const text = group.querySelector('[data-color-text]');
        const valid = value => /^#[0-9a-f]{6}$/i.test(value);
        picker.addEventListener('input', () => {
            text.value = picker.value.toUpperCase();
            text.dispatchEvent(new Event('input', { bubbles: true }));
        });
        text.addEventListener('input', () => { if (valid(text.value)) picker.value = text.value; });
    });
    const renderCrop = () => {
        if (!image) return;
        const scale = Math.max(canvas.width / image.width, canvas.height / image.height) * Number(zoom.value);
        const width = canvas.width / scale, height = canvas.height / scale;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(image, (image.width - width) * Number(x.value), (image.height - height) * Number(y.value), width, height, 0, 0, canvas.width, canvas.height);
    };
    [zoom, x, y].forEach(input => input.addEventListener('input', renderCrop));
    document.querySelectorAll('.personalizacao-editor').forEach(editor => {
        const upload = editor.querySelector('[data-upload]');
        const error = editor.querySelector('[data-erro]');
        const preview = editor.querySelector('[data-preview]');
        editor.updatePreview = () => {
            const value = key => editor.querySelector(`[data-campo="${key}"]`).value;
            preview.style.color = value('cor_fonte');
            preview.style.background = value('tipo') === 'degrade'
                ? `linear-gradient(135deg, ${value('degrade_inicio')}, ${value('degrade_fim')})`
                : value('tipo') === 'imagem' && editor.dataset.imagem
                    ? `url("${editor.dataset.imagem}") center / cover no-repeat` : value('cor_solida');
        };
        editor.querySelectorAll('[data-campo]').forEach(input => input.addEventListener('input', editor.updatePreview));
        editor.updatePreview();
        const mensagemCores = editor.querySelector('[data-cores-status]');
        const aplicarCores = valores => {
            Object.keys(valores).filter(campo => ['tipo', 'degrade_inicio', 'degrade_fim', 'cor_solida', 'cor_fonte'].includes(campo)).forEach(campo => {
                const input = editor.querySelector(`[data-campo="${campo}"]`);
                input.value = valores[campo];
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        };
        editor.querySelector('[data-inverter-degrade]').addEventListener('click', () => {
            const inicio = editor.querySelector('[data-campo="degrade_inicio"]').value;
            const fim = editor.querySelector('[data-campo="degrade_fim"]').value;
            aplicarCores({ degrade_inicio: fim, degrade_fim: inicio });
            mensagemCores.textContent = 'Cores inicial e final invertidas.';
        });
        editor.querySelector('[data-cores-padrao]').addEventListener('click', event => {
            aplicarCores(JSON.parse(event.currentTarget.dataset.padrao));
            mensagemCores.textContent = 'Cores padrão aplicadas. Você pode continuar editando as cores.';
        });
        editor.querySelector('[data-cores-evento]').addEventListener('click', () => {
            const paleta = [...document.querySelectorAll('#evento-cores input[type="text"]')].map(input => input.value);
            if (paleta.some(cor => !/^#[0-9a-f]{6}$/i.test(cor))) {
                mensagemCores.textContent = 'Corrija os códigos hexadecimais da paleta do evento antes de aplicar.';
                return;
            }
            const valores = coresFormularioEvento(paleta);
            if (!valores) {
                mensagemCores.textContent = 'Adicione ou extraia pelo menos duas cores diferentes na paleta do evento.';
                return;
            }
            aplicarCores(valores);
            mensagemCores.textContent = 'Par de cores do evento sorteado e aplicado ao degradê e fonte ajustada para contraste. Você pode editar todos os valores.';
        });
        upload.addEventListener('change', () => {
            const file = upload.files[0];
            if (!file) return;
            // Preserve o último recorte confirmado se a nova seleção for cancelada.
            const previous = new DataTransfer();
            if (editor.croppedFile) previous.items.add(editor.croppedFile);
            upload.files = previous.files;
            error.textContent = '';
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
                error.textContent = 'Escolha uma imagem JPG, PNG ou WebP de até 8 MB.';
                return;
            }
            current = editor;
            sourceUrl = URL.createObjectURL(file);
            image = new Image();
            image.onload = () => {
                if (image.width > 12000 || image.height > 12000) {
                    error.textContent = 'A imagem deve ter no máximo 12000 pixels de largura e altura.';
                    URL.revokeObjectURL(sourceUrl);
                    return;
                }
                zoom.value = 1; x.value = y.value = 0.5;
                renderCrop(); modal.show();
            };
            image.onerror = () => {
                error.textContent = 'Não foi possível abrir a imagem. Escolha outro arquivo.';
                URL.revokeObjectURL(sourceUrl);
            };
            image.src = sourceUrl;
        });
    });
    confirm.addEventListener('click', () => {
        if (!current || !image) return;
        confirm.disabled = true;
        const editor = current;
        canvas.toBlob(blob => {
            confirm.disabled = false;
            if (!blob) return;
            const file = new File([blob], 'fundo.png', { type: 'image/png' });
            const transfer = new DataTransfer(); transfer.items.add(file);
            editor.querySelector('[data-upload]').files = transfer.files;
            editor.croppedFile = file;
            if (editor.dataset.imagem.startsWith('blob:')) URL.revokeObjectURL(editor.dataset.imagem);
            editor.dataset.imagem = URL.createObjectURL(blob);
            editor.updatePreview(); modal.hide();
        }, 'image/png');
    });
    modalElement.addEventListener('hidden.bs.modal', () => {
        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        image = null; current = null;
    });
});
