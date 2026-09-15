document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('excluirBiblioteca')?.addEventListener('show.bs.modal', event => {
        document.getElementById('formExcluirBiblioteca').action = event.relatedTarget.dataset.excluirUrl;
        document.getElementById('nomeExcluirBiblioteca').textContent = event.relatedTarget.dataset.excluirNome;
        document.getElementById('confirmarExclusaoBiblioteca').checked = false;
    });
    document.querySelectorAll('[data-gerar-formato]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.enviando) { event.preventDefault(); return; }
            form.dataset.enviando = '1';
            const botao = form.querySelector('.dropdown-toggle');
            botao.textContent = 'Gerando…';
            botao.disabled = true;
        });
    });
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
    const pasteArea = document.getElementById('areaColarImagem');
    const pastePreview = document.getElementById('previewImagemColada');
    const pasteIcon = document.getElementById('iconeColarImagem');
    const pasteStatus = document.getElementById('statusColarImagem');
    const savePasted = document.getElementById('salvarImagemColada');
    let pastedFile = null;
    let pastedPreviewUrl = null;
    const allowedExtensions = new Set((pasteArea?.dataset.extensoes || '').split(',').filter(Boolean));
    const mimeExtensions = {
        'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif', 'image/webp': 'webp', 'image/svg+xml': 'svg',
        'application/pdf': 'pdf', 'application/msword': 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
        'application/vnd.oasis.opendocument.text': 'odt', 'application/rtf': 'rtf', 'text/rtf': 'rtf', 'text/plain': 'txt', 'text/csv': 'csv',
        'application/vnd.ms-excel': 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
        'application/vnd.oasis.opendocument.spreadsheet': 'ods', 'application/vnd.ms-powerpoint': 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
        'application/vnd.oasis.opendocument.presentation': 'odp'
    };
    const extensionOf = file => {
        const fromName = file?.name?.match(/\.([a-z0-9]+)$/i)?.[1]?.toLowerCase();
        return allowedExtensions.has(fromName) ? fromName : (mimeExtensions[file?.type] || '');
    };
    const iconFor = extension => {
        if (extension === 'pdf') return 'bi-file-earmark-pdf text-danger';
        if (['doc', 'docx', 'odt', 'rtf'].includes(extension)) return 'bi-file-earmark-word text-primary';
        if (['xls', 'xlsx', 'ods', 'csv'].includes(extension)) return 'bi-file-earmark-spreadsheet text-success';
        if (['ppt', 'pptx', 'odp'].includes(extension)) return 'bi-file-earmark-slides text-warning';
        return 'bi-file-earmark-text text-secondary';
    };
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

    const showClipboardError = message => {
        if (!clipboardError) return;
        clipboardError.textContent = message;
        clipboardError.classList.remove('d-none');
    };
    const receivePastedFile = file => {
        clipboardError?.classList.add('d-none');
        const extension = extensionOf(file);
        if (!file || !extension) {
            showClipboardError('O conteúdo colado não contém um arquivo compatível. Cole uma imagem, PDF, documento, apresentação ou planilha.');
            return;
        }
        if (file.size > 25 * 1024 * 1024) {
            showClipboardError('O arquivo colado deve ter no máximo 25 MB.');
            return;
        }
        const originalName = file.name && file.name !== 'image.png' ? file.name : `arquivo-colado-${Date.now()}.${extension}`;
        pastedFile = new File([file], originalName, { type: file.type || 'application/octet-stream' });
        if (pastedPreviewUrl) URL.revokeObjectURL(pastedPreviewUrl);
        pastedPreviewUrl = null;
        if (isImage(pastedFile)) {
            pastedPreviewUrl = URL.createObjectURL(pastedFile);
            pastePreview.src = pastedPreviewUrl;
            pastePreview.classList.remove('d-none');
            pasteIcon?.classList.add('d-none');
        } else {
            pastePreview.removeAttribute('src');
            pastePreview.classList.add('d-none');
            pasteIcon.className = `bi ${iconFor(extension)} fs-2`;
            pasteIcon.classList.remove('d-none');
        }
        pasteStatus.textContent = `${pastedFile.name} — ${(pastedFile.size / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} KB`;
        savePasted.disabled = false;
    };
    pasteArea?.addEventListener('paste', event => {
        event.preventDefault();
        const file = Array.from(event.clipboardData?.files || [])[0]
            || Array.from(event.clipboardData?.items || []).find(item => item.kind === 'file')?.getAsFile();
        receivePastedFile(file);
    });
    pasteArea?.addEventListener('click', () => pasteArea.focus());
    document.getElementById('colarImagem')?.addEventListener('click', () => {
        pasteArea?.focus();
        pasteArea?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    savePasted?.addEventListener('click', () => {
        if (!pastedFile) return;
        try {
            const transfer = new DataTransfer();
            transfer.items.add(pastedFile);
            uploadInput.files = transfer.files;
        } catch (error) {
            showClipboardError('Seu navegador não permitiu preparar o arquivo colado para envio.');
            return;
        }
        uploadName.value = pastedFile.name;
        if (uploadCategory) uploadCategory.value = '';
        updateUpload();
        bootstrap.Modal.getOrCreateInstance(uploadModalElement).show();
    });
    document.getElementById('abrirUploadBiblioteca')?.addEventListener('click', () => {
        if (uploadInput) uploadInput.value = '';
        updateUpload();
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
