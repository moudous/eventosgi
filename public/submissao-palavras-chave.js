document.addEventListener('DOMContentLoaded', () => {
    const campo = document.getElementById('palavras_chave');
    if (!campo) return;
    const badges = document.getElementById('badgesPalavrasChave');
    const contador = document.getElementById('contadorPalavrasChave');
    const minimo = Number(campo.dataset.min);
    const maximo = Number(campo.dataset.max);
    const atualizar = () => {
        const palavras = campo.value.split(',').map(palavra => palavra.trim()).filter(Boolean);
        badges.replaceChildren();
        palavras.forEach(palavra => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-light text-dark border border-secondary-subtle text-wrap text-start';
            badge.textContent = palavra;
            badges.append(badge);
        });
        contador.textContent = `${palavras.length} de ${minimo} a ${maximo} palavras-chave ou expressões`;
        const valido = palavras.length >= minimo && palavras.length <= maximo;
        contador.classList.toggle('text-danger', !valido);
        campo.setCustomValidity(valido ? '' : `Informe de ${minimo} a ${maximo} palavras-chave ou expressões, separadas por vírgulas.`);
    };
    campo.addEventListener('input', atualizar);
    atualizar();
});
