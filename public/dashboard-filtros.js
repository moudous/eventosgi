document.addEventListener('DOMContentLoaded', () => {
    const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
    if (window.jQuery?.fn.select2) {
        document.querySelectorAll('.dashboard-evento-pesquisa').forEach(select => {
            const eventos = Array.from(select.options, option => ({id: option.value, text: option.text}));
            window.jQuery(select).select2({
                theme: 'bootstrap-5',
                width: '100%',
                minimumResultsForSearch: 0,
                language: {noResults: () => 'Nenhum evento encontrado', searching: () => 'Pesquisando…'},
                ajax: {
                    transport: (params, sucesso) => {
                        const termo = normalizar((params.data.term || '').trim());
                        const todos = eventos.filter(evento => evento.id === '0');
                        const resultados = eventos.filter(evento => evento.id !== '0' && normalizar(evento.text).includes(termo)).slice(0, 10);
                        sucesso({results: [...todos, ...resultados]});
                        return {abort() {}};
                    },
                    processResults: dados => dados,
                },
            });
        });
        window.jQuery(document).on('select2:open', () => {
            const pesquisa = document.querySelector('.select2-container--open .select2-search__field');
            if (pesquisa) {
                pesquisa.placeholder = 'Pesquisar eventos';
                pesquisa.focus();
            }
        });
    }
    const voltarAoCard = () => {
        const card = document.getElementById(window.location.hash.slice(1));
        if (card?.classList.contains('dashboard-card-filtro')) card.scrollIntoView({block: 'start'});
    };
    // Reposiciona depois que os gráficos e demais recursos definirem a altura dos cards.
    window.addEventListener('load', () => requestAnimationFrame(voltarAoCard), {once: true});
});
