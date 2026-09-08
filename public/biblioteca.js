document.addEventListener('DOMContentLoaded', () => {
    const typeFilter = document.getElementById('tipo');
    const categoryFilter = document.getElementById('categoria');
    if (typeFilter && categoryFilter) {
        const toggleFilter = () => { categoryFilter.disabled = typeFilter.value !== 'imagem'; };
        typeFilter.addEventListener('change', toggleFilter);
        toggleFilter();
    }

    const uploadModalElement = document.getElementById('uploadBiblioteca');
    const uploadInput = document.getElementById('arquivoUpload');
    const uploadName = document.getElementById('nomeUpload');
    const uploadCategoryGroup = document.querySelector('[data-categoria-upload]');
    const uploadCategory = document.getElementById('categoriaUpload');
    const clipboardError = document.getElementById('erroClipboard');
    const isImage = file => file && (file.type.startsWith('image/') || /\.svg$/i.test(file.name));
    const updateUpload = () => {
        if (!uploadInput || !uploadInput.files[0]) return;
        const image = isImage(uploadInput.files[0]);
        uploadCategoryGroup?.classList.toggle('d-none', !image);
        if (uploadCategory) {
            uploadCategory.disabled = !image;
            uploadCategory.required = image;
        }
    };
    uploadInput?.addEventListener('change', () => {
        if (uploadInput.files[0]) uploadName.value = uploadInput.files[0].name;
        updateUpload();
    });

    document.getElementById('colarImagem')?.addEventListener('click', async () => {
        clipboardError?.classList.add('d-none');
        try {
            if (!navigator.clipboard?.read) throw new Error('Seu navegador não permite ler imagens copiadas nesta página.');
            const items = await navigator.clipboard.read();
            let blob = null;
            for (const item of items) {
                const mime = item.types.find(type => type.startsWith('image/'));
                if (mime) { blob = await item.getType(mime); break; }
            }
            if (!blob) throw new Error('A área de transferência não contém uma imagem.');
            const extension = blob.type.split('/')[1]?.replace('jpeg', 'jpg') || 'png';
            const file = new File([blob], `imagem-colada.${extension}`, { type: blob.type });
            const transfer = new DataTransfer();
            transfer.items.add(file);
            uploadInput.files = transfer.files;
            uploadName.value = file.name;
            if (uploadCategory && !uploadCategory.value) uploadCategory.value = 'imagem_decorativa';
            updateUpload();
            bootstrap.Modal.getOrCreateInstance(uploadModalElement).show();
        } catch (error) {
            if (clipboardError) {
                clipboardError.textContent = error.message || 'Não foi possível colar a imagem copiada.';
                clipboardError.classList.remove('d-none');
            }
        }
    });

    let selected = null;
    const cropButton = document.getElementById('recortarSelecionada');
    document.querySelectorAll('[data-selecionar]').forEach(button => {
        button.addEventListener('click', () => {
            const card = button.closest('[data-biblioteca-item]');
            const wasSelected = selected === card;
            document.querySelectorAll('[data-biblioteca-item].selecionado').forEach(item => {
                item.classList.remove('selecionado');
                const control = item.querySelector('[data-selecionar]');
                control?.setAttribute('aria-pressed', 'false');
                if (control) control.innerHTML = '<i class="bi bi-check2-square me-1"></i>Selecionar';
            });
            selected = wasSelected ? null : card;
            if (selected) {
                selected.classList.add('selecionado');
                button.setAttribute('aria-pressed', 'true');
                button.innerHTML = '<i class="bi bi-check-square-fill me-1"></i>Selecionada';
            }
            if (cropButton) cropButton.disabled = !selected;
        });
    });

    const cropModalElement = document.getElementById('cropBiblioteca');
    const canvas = document.getElementById('cropBibliotecaCanvas');
    const ratioSelect = document.getElementById('cropProporcao');
    const zoom = document.getElementById('cropZoom');
    const positionX = document.getElementById('cropX');
    const positionY = document.getElementById('cropY');
    const cropName = document.getElementById('cropNome');
    const cropTags = document.getElementById('cropTags');
    const cropCategory = document.getElementById('cropCategoria');
    const cropFile = document.getElementById('cropArquivo');
    let sourceImage = null;

    const resizeCanvas = () => {
        if (!sourceImage || !canvas) return;
        const ratio = ratioSelect.value === 'original'
            ? sourceImage.naturalWidth / sourceImage.naturalHeight
            : Number(ratioSelect.value);
        if (ratio >= 1) {
            canvas.width = 1200;
            canvas.height = Math.max(1, Math.round(1200 / ratio));
        } else {
            canvas.height = 1200;
            canvas.width = Math.max(1, Math.round(1200 * ratio));
        }
        drawCrop();
    };
    const drawCrop = () => {
        if (!sourceImage || !canvas) return;
        const scale = Math.max(canvas.width / sourceImage.naturalWidth, canvas.height / sourceImage.naturalHeight) * Number(zoom.value);
        const sourceWidth = canvas.width / scale;
        const sourceHeight = canvas.height / scale;
        const sourceX = (sourceImage.naturalWidth - sourceWidth) * Number(positionX.value);
        const sourceY = (sourceImage.naturalHeight - sourceHeight) * Number(positionY.value);
        const context = canvas.getContext('2d');
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(sourceImage, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
    };
    [zoom, positionX, positionY].forEach(control => control?.addEventListener('input', drawCrop));
    ratioSelect?.addEventListener('change', resizeCanvas);

    cropButton?.addEventListener('click', () => {
        if (!selected) return;
        sourceImage = new Image();
        sourceImage.onload = () => {
            zoom.value = 1;
            positionX.value = positionY.value = 0.5;
            ratioSelect.value = 'original';
            const oldName = selected.dataset.nome || 'imagem';
            cropName.value = `${oldName.replace(/\.[^.]+$/, '')} copia.png`;
            cropTags.value = selected.dataset.tags || '';
            cropCategory.value = selected.dataset.categoria || 'imagem_decorativa';
            resizeCanvas();
            bootstrap.Modal.getOrCreateInstance(cropModalElement).show();
        };
        sourceImage.onerror = () => {
            if (clipboardError) {
                clipboardError.textContent = 'Não foi possível abrir a imagem selecionada para recorte.';
                clipboardError.classList.remove('d-none');
            }
        };
        sourceImage.src = selected.dataset.url;
    });

    document.getElementById('salvarRecorte')?.addEventListener('click', event => {
        if (!sourceImage || !canvas || !cropName.value.trim()) return;
        const button = event.currentTarget;
        button.disabled = true;
        canvas.toBlob(blob => {
            button.disabled = false;
            if (!blob) return;
            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], 'recorte.png', { type: 'image/png' }));
            cropFile.files = transfer.files;
            document.getElementById('formCropBiblioteca').requestSubmit();
        }, 'image/png', 0.92);
    });
});
