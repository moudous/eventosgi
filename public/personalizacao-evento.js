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
