document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const picker = document.querySelector('#imagem_cor_borda');
    const text = document.querySelector('[name="personalizacao[cor_borda]"]');
    const border = document.querySelector('[data-borda-imagem]');
    const position = document.querySelector('[data-posicao-imagem]');
    const upload = document.querySelector('[data-imagem-atividade]');
    const imagePreview = document.querySelector('[data-preview-imagem]');
    const preview = document.querySelector('[data-preview-atividade]');
    const removeInput = document.querySelector('[data-remover-imagem-atividade]');
    const removeButton = document.querySelector('[data-remover-imagem]');
    const savedLink = document.querySelector('[data-imagem-salva-link]');
    const uploadStatus = document.querySelector('[data-imagem-status]');
    const modalElement = document.getElementById('recorteImagemAtividade');
    const canvas = document.querySelector('[data-recorte-canvas]');
    const cropStatus = document.querySelector('[data-recorte-status]');
    const applyCrop = document.querySelector('[data-aplicar-recorte]');
    if (!picker || !text || !border || !position || !upload || !imagePreview || !preview || !modalElement || !canvas || !applyCrop) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const context = canvas.getContext('2d');
    let previewUrl = null;
    let sourceUrl = null;
    let sourceImage = null;
    let sourceFile = null;
    let confirmedFile = null;
    let selection = null;
    let dragStart = null;

    const validColor = value => /^#[0-9a-f]{6}$/i.test(value);
    const update = () => {
        imagePreview.style.border = border.checked ? `3px solid ${validColor(text.value) ? text.value : picker.value}` : 'none';
        preview.classList.toggle('flex-row-reverse', position.value === 'direita');
        preview.classList.toggle('justify-content-between', position.value === 'direita');
    };
    const setUploadFile = file => {
        const transfer = new DataTransfer();
        if (file) transfer.items.add(file);
        upload.files = transfer.files;
    };
    const point = event => {
        const bounds = canvas.getBoundingClientRect();
        return {
            x: Math.max(0, Math.min(canvas.width, (event.clientX - bounds.left) * canvas.width / bounds.width)),
            y: Math.max(0, Math.min(canvas.height, (event.clientY - bounds.top) * canvas.height / bounds.height)),
        };
    };
    const draw = () => {
        if (!sourceImage) return;
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(sourceImage, 0, 0, canvas.width, canvas.height);
        if (!selection) return;
        const { x, y, w, h } = selection;
        context.fillStyle = 'rgba(0, 0, 0, .55)';
        context.fillRect(0, 0, canvas.width, y);
        context.fillRect(0, y, x, h);
        context.fillRect(x + w, y, canvas.width - x - w, h);
        context.fillRect(0, y + h, canvas.width, canvas.height - y - h);
        context.lineWidth = 2;
        context.strokeStyle = '#fff';
        context.strokeRect(x, y, w, h);
        context.setLineDash([6, 4]);
        context.strokeStyle = '#000';
        context.strokeRect(x, y, w, h);
        context.setLineDash([]);
    };
    const resetCrop = () => {
        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        sourceUrl = null;
        sourceImage = null;
        sourceFile = null;
        selection = null;
        dragStart = null;
    };

    picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); update(); });
    text.addEventListener('input', () => { if (validColor(text.value)) picker.value = text.value; update(); });
    border.addEventListener('change', update);
    position.addEventListener('change', update);

    upload.addEventListener('change', () => {
        const file = upload.files[0];
        if (!file) return;
        // O arquivo só passa a integrar o formulário depois que o recorte for confirmado.
        setUploadFile(confirmedFile);
        uploadStatus.classList.remove('text-danger');
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
            uploadStatus.textContent = 'Escolha uma imagem JPG, PNG ou WebP de até 8 MB.';
            uploadStatus.classList.add('text-danger');
            return;
        }
        resetCrop();
        sourceFile = file;
        sourceUrl = URL.createObjectURL(file);
        const candidate = new Image();
        candidate.onload = () => {
            if (candidate.width > 12000 || candidate.height > 12000) {
                uploadStatus.textContent = 'A imagem deve ter no máximo 12000 pixels de largura e altura.';
                uploadStatus.classList.add('text-danger');
                resetCrop();
                return;
            }
            sourceImage = candidate;
            const scale = Math.min(1, 1000 / candidate.width, 650 / candidate.height);
            canvas.width = Math.max(1, Math.round(candidate.width * scale));
            canvas.height = Math.max(1, Math.round(candidate.height * scale));
            selection = null;
            applyCrop.disabled = true;
            cropStatus.classList.remove('text-danger');
            cropStatus.textContent = 'Arraste sobre a imagem para selecionar o recorte.';
            draw();
            modal.show();
        };
        candidate.onerror = () => {
            uploadStatus.textContent = 'Não foi possível abrir a imagem. Escolha outro arquivo.';
            uploadStatus.classList.add('text-danger');
            resetCrop();
        };
        candidate.src = sourceUrl;
    });

    canvas.addEventListener('pointerdown', event => {
        if (!sourceImage) return;
        dragStart = point(event);
        selection = null;
        applyCrop.disabled = true;
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', event => {
        if (!dragStart) return;
        const end = point(event);
        selection = {
            x: Math.min(dragStart.x, end.x), y: Math.min(dragStart.y, end.y),
            w: Math.abs(end.x - dragStart.x), h: Math.abs(end.y - dragStart.y),
        };
        applyCrop.disabled = selection.w < 3 || selection.h < 3;
        cropStatus.textContent = applyCrop.disabled ? 'Selecione uma área maior.' : 'Área selecionada. Confirme para usar este recorte.';
        draw();
    });
    const finishDrag = event => {
        if (dragStart && canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
        dragStart = null;
    };
    canvas.addEventListener('pointerup', finishDrag);
    canvas.addEventListener('pointercancel', finishDrag);

    applyCrop.addEventListener('click', () => {
        if (!sourceImage || !sourceFile || !selection || selection.w < 3 || selection.h < 3) return;
        applyCrop.disabled = true;
        const ratioX = sourceImage.width / canvas.width;
        const ratioY = sourceImage.height / canvas.height;
        const cropped = document.createElement('canvas');
        cropped.width = Math.max(1, Math.round(selection.w * ratioX));
        cropped.height = Math.max(1, Math.round(selection.h * ratioY));
        cropped.getContext('2d').drawImage(
            sourceImage,
            Math.round(selection.x * ratioX), Math.round(selection.y * ratioY), cropped.width, cropped.height,
            0, 0, cropped.width, cropped.height,
        );
        const mime = sourceFile.type;
        cropped.toBlob(blob => {
            applyCrop.disabled = false;
            if (!blob || blob.size > 8 * 1024 * 1024) {
                cropStatus.textContent = 'O recorte excedeu 8 MB. Selecione uma área menor.';
                cropStatus.classList.add('text-danger');
                return;
            }
            const extension = mime === 'image/jpeg' ? 'jpg' : mime.split('/')[1];
            confirmedFile = new File([blob], `atividade-recortada.${extension}`, { type: mime });
            setUploadFile(confirmedFile);
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(blob);
            imagePreview.src = previewUrl;
            imagePreview.classList.remove('d-none');
            removeButton?.classList.remove('d-none');
            if (removeInput) removeInput.value = '0';
            uploadStatus.textContent = 'Recorte pronto para upload. Salve a atividade para confirmar.';
            uploadStatus.classList.remove('text-danger');
            update();
            modal.hide();
        }, mime, 0.92);
    });

    removeButton?.addEventListener('click', () => {
        confirmedFile = null;
        setUploadFile(null);
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        imagePreview.removeAttribute('src');
        imagePreview.classList.add('d-none');
        removeButton.classList.add('d-none');
        savedLink?.classList.add('d-none');
        if (removeInput) removeInput.value = '1';
        uploadStatus.textContent = 'A imagem será removida ao salvar a atividade.';
        uploadStatus.classList.remove('text-danger');
    });
    modalElement.addEventListener('hidden.bs.modal', resetCrop);
    update();
});
