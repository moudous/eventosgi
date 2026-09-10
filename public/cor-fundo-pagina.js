document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-alterar-fundo-pagina]').forEach(toggle => {
        const scope = toggle.closest('.card, fieldset') || document;
        const container = scope.querySelector('[data-cor-fundo-pagina-container]');
        if (!container) return;

        const update = () => { container.hidden = !toggle.checked; };
        toggle.addEventListener('change', update);
        update();

        const picker = container.querySelector('[data-fundo-color-picker]');
        const text = container.querySelector('[data-fundo-color-text]');
        if (!picker || !text) return;
        const valid = value => /^#[0-9a-f]{6}$/i.test(value);
        picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); });
        text.addEventListener('input', () => { if (valid(text.value)) picker.value = text.value; });
    });
});
