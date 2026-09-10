document.addEventListener('DOMContentLoaded', () => {
    const tipo = document.getElementById('tipo_atividade');
    if (!tipo) return;
    const atualizar = () => {
        const somenteInscricao = tipo.value === 'somente_inscricao';
        document.querySelectorAll('[data-campo-atividade]').forEach(grupo => {
            grupo.hidden = somenteInscricao;
            grupo.querySelectorAll('input, select').forEach(campo => { campo.disabled = somenteInscricao; });
        });
    };
    tipo.addEventListener('change', atualizar);
    atualizar();
});
