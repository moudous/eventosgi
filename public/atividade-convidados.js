document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('convidado-selecao');
    if (!select) return;
    const list = document.getElementById('atividade-convidados');
    const status = document.getElementById('convidados-status');
    const add = document.getElementById('adicionar-convidado');
    const avatar = option => {
        const fallback = document.createElement('span');
        fallback.className = 'convidado-avatar';
        fallback.setAttribute('aria-hidden', 'true');
        fallback.textContent = Array.from((option.dataset.nome || option.textContent).trim())[0]?.toLocaleUpperCase('pt-BR') || '?';
        const colors = ['#175CD3', '#6941C6', '#027A48', '#B54708', '#C11574', '#0E7090'];
        fallback.style.backgroundColor = colors[Number(option.value) % colors.length];
        if (!option.dataset.foto) return fallback;
        const photo = document.createElement('img');
        photo.className = 'convidado-avatar'; photo.alt = ''; photo.src = option.dataset.foto;
        photo.addEventListener('error', () => photo.replaceWith(fallback), { once: true });
        return photo;
    };
    const identity = option => {
        const wrapper = document.createElement('span');
        wrapper.className = 'convidado-identidade';
        const name = document.createElement('span'); name.textContent = option.textContent;
        wrapper.append(avatar(option), name);
        return wrapper;
    };
    const template = item => item.id && item.element ? $(identity(item.element)) : item.text;
    $(select).select2({
        theme: 'bootstrap-5', width: '100%', placeholder: 'Selecione um convidado...',
        templateResult: template, templateSelection: template,
        language: { noResults: () => 'Nenhum convidado encontrado' }
    });
    const refresh = () => {
        const ids = [...list.children].map(row => row.dataset.id);
        [...select.options].forEach(option => { option.disabled = ids.includes(option.value); });
        if (ids.includes(select.value)) select.value = '';
        [...list.children].forEach((row, index) => {
            row.querySelector('[data-up]').disabled = index === 0;
            row.querySelector('[data-down]').disabled = index === list.children.length - 1;
        });
        document.getElementById('convidados-vazio').hidden = ids.length > 0;
        add.disabled = !select.value;
        $(select).trigger('change.select2');
    };
    const insert = id => {
        const option = [...select.options].find(option => option.value === String(id));
        if (!option || !option.value || [...list.children].some(row => row.dataset.id === option.value)) return;
        const row = document.createElement('li');
        row.className = 'list-group-item d-flex align-items-center gap-2 flex-wrap'; row.dataset.id = option.value;
        row.innerHTML = '<span class="flex-grow-1"></span><input type="hidden" name="convidados[]"><button type="button" data-up class="btn btn-sm btn-outline-secondary" title="Mover para cima" aria-label="Mover para cima"><i class="bi bi-arrow-up" aria-hidden="true"></i></button><button type="button" data-down class="btn btn-sm btn-outline-secondary" title="Mover para baixo" aria-label="Mover para baixo"><i class="bi bi-arrow-down" aria-hidden="true"></i></button><button type="button" data-remove class="btn btn-sm btn-outline-danger">Remover</button>';
        row.querySelector('span').append(identity(option));
        row.querySelector('input').value = option.value;
        row.querySelector('[data-up]').onclick = () => { if (row.previousElementSibling) list.insertBefore(row, row.previousElementSibling); refresh(); status.textContent = 'Ordem alterada. Salve as alterações.'; };
        row.querySelector('[data-down]').onclick = () => { if (row.nextElementSibling) list.insertBefore(row.nextElementSibling, row); refresh(); status.textContent = 'Ordem alterada. Salve as alterações.'; };
        row.querySelector('[data-remove]').onclick = () => { row.remove(); refresh(); status.textContent = 'Convidado removido da seleção. Salve as alterações.'; };
        list.append(row); refresh();
    };
    $(select).on('change', refresh);
    add.onclick = () => { insert(select.value); status.textContent = 'Convidado adicionado à seleção. Salve as alterações.'; };
    const initial = window.atividadeConvidadosSelecionados;
    (Array.isArray(initial) ? initial : []).forEach(insert);
    refresh();
});
