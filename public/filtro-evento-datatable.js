window.iniciarFiltroEventoDataTable = function (select, aoAlterar) {
    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
    const eventos = Array.from(select.options, option => ({id: option.value, text: option.text}));
    const $select = window.jQuery(select);
    $select.on('change', aoAlterar);
    if (! window.jQuery.fn.select2) return;
    $select.select2({
        theme: 'bootstrap-5', width: '100%', minimumResultsForSearch: 0,
        language: {noResults: () => 'Nenhum evento encontrado', searching: () => 'Pesquisando…'},
        ajax: {
            transport: (params, sucesso) => {
                const termo = normalizar((params.data.term || '').trim());
                const resultados = eventos.filter(evento => evento.id !== '0' && normalizar(evento.text).includes(termo));
                sucesso({results: [...eventos.filter(evento => evento.id === '0'), ...resultados.slice(0, 15)]});
                return {abort() {}};
            },
            processResults: dados => dados,
        },
    });
    $select.on('select2:open', () => {
        const campo = document.querySelector('.select2-container--open .select2-search__field');
        if (campo) { campo.placeholder = 'Pesquisar eventos'; campo.focus(); }
    });
};
