document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('#imagem_cor_borda');
    const text = document.querySelector('[name="personalizacao[cor_borda]"]');
    const border = document.querySelector('[data-borda-imagem]');
    const position = document.querySelector('[data-posicao-imagem]');
    const upload = document.querySelector('[data-imagem-atividade]');
    const image = document.querySelector('[data-preview-imagem]');
    const preview = document.querySelector('[data-preview-atividade]');
    if (!picker || !text || !image) return;

    let objectUrl = null;
    const validColor = value => /^#[0-9a-f]{6}$/i.test(value);
    const update = () => {
        image.style.border = border.checked ? `3px solid ${validColor(text.value) ? text.value : picker.value}` : 'none';
        preview.classList.toggle('flex-row-reverse', position.value === 'direita');
        preview.classList.toggle('justify-content-between', position.value === 'direita');
    };
    picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); update(); });
    text.addEventListener('input', () => { if (validColor(text.value)) picker.value = text.value; update(); });
    border.addEventListener('change', update);
    position.addEventListener('change', update);
    upload.addEventListener('change', () => {
        const file = upload.files[0];
        if (!file) return;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        image.src = objectUrl;
        image.classList.remove('d-none');
        update();
    });
    update();
});
