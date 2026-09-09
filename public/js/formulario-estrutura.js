(() => {
    const root = document.getElementById('builderRows');
    if (!root) return;
    const criteriaRoot = document.getElementById('criteriosVagas');
    const esc = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;');
    let sequence = 0;
    let criteriaUids = [];
    const choiceTypes = ['select', 'radio', 'checkbox', 'multiselect'];
    const singleChoiceTypes = ['select', 'radio'];
    const slug = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    function optionHtml(option = {valor: '', texto: ''}) {
        const item = typeof option === 'object' && option !== null ? option : {valor: String(option), texto: String(option)};
        const percentual = item.percentual_vagas === null || item.percentual_vagas === undefined ? '' : `${Number(item.percentual_vagas)}%`;
        return `<div class="row g-2 align-items-end mb-2 option-item"><div class="col-md-4"><label class="form-label small mb-1">Texto exibido<input class="form-control option-text" value="${esc(item.texto)}" required></label></div><div class="col-md-3"><label class="form-label small mb-1">Valor<input class="form-control option-value" value="${esc(item.valor)}" required></label></div><div class="col-md-3 option-percent-column"><label class="form-label small mb-1">% de vaga<input class="form-control option-percent" inputmode="decimal" value="${esc(percentual)}" placeholder="Ex.: 33% ou 3"></label><div class="small text-muted option-quota-number"></div></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger remove-option mb-1" aria-label="Remover item" title="Remover item"><i class="bi bi-trash"></i></button></div></div>`;
    }
    function refresh() {
        const fields = [...root.children];
        document.getElementById('builderEmpty').hidden = fields.length > 0;
        fields.forEach((el, index) => {
            el.querySelector('.field-number').textContent = index + 1;
            el.querySelector('.field-title').textContent = el.querySelector('.f-label').value || 'Novo campo';
            el.querySelector('.move-up').disabled = index === 0;
            el.querySelector('.move-down').disabled = index === fields.length - 1;
            const grid = Number(el.querySelector('.f-grid').value);
            el.className = `col-12 col-lg-${grid} field`;
            const type = el.querySelector('.f-type').value;
            const criterio = el.querySelector('.f-criterion');
            criterio.disabled = !singleChoiceTypes.includes(type);
            if (criterio.disabled) criterio.checked = false;
            for (const [selector, visible] of [['.field-options', choiceTypes.includes(type)], ['.field-upload', type === 'file']]) {
                const section = el.querySelector(selector);
                section.hidden = !visible;
                section.querySelectorAll('input,button').forEach(control => control.disabled = !visible);
            }
            el.querySelector('.criterion-toggle').hidden = !singleChoiceTypes.includes(type);
            el.querySelectorAll('.option-percent').forEach(input => {
                input.disabled = !criterio.checked;
                input.required = criterio.checked;
            });
            el.querySelectorAll('.option-percent-column').forEach(area => area.hidden = !criterio.checked);
            const obrigatorio = el.querySelector('.f-required');
            if (criterio.checked) obrigatorio.checked = true;
            obrigatorio.disabled = criterio.checked;
        });
        renderCriteria();
        updateQuotaNumbers();
    }
    function add(field = {}, focus = false) {
        const id = `builder-field-${++sequence}`;
        const el = document.createElement('div');
        el.dataset.builderId = id;
        el._original = field;
        el.innerHTML = `<section class="border rounded-3 bg-white h-100 shadow-sm overflow-hidden"><div class="p-3 bg-light border-bottom d-flex gap-2 align-items-center"><span class="badge bg-primary field-number"></span><strong class="field-title text-break flex-grow-1"></strong><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-secondary move-up" title="Mover antes" aria-label="Mover campo antes"><i class="bi bi-arrow-up"></i></button><button type="button" class="btn btn-sm btn-outline-secondary move-down" title="Mover depois" aria-label="Mover campo depois"><i class="bi bi-arrow-down"></i></button><button type="button" class="btn btn-sm btn-outline-danger remove-field" title="Remover campo" aria-label="Remover campo"><i class="bi bi-trash"></i></button></div></div><div class="p-3"><div class="row g-3">
        <div class="col-12"><label class="form-label" for="${id}">Título do campo</label><input id="${id}" class="form-control f-label" value="${esc(field.label)}" required placeholder="Ex.: Turno de participação"></div>
        <div class="col-12"><label class="form-label">Nome único<input class="form-control f-name" value="${esc(field.nome)}" placeholder="Ex.: turno" required></label></div>
        <div class="col-12"><label class="form-label">Tipo<select class="form-select f-type">${Object.entries({text:'Texto',date:'Data','datetime-local':'Data e hora',textarea:'Texto longo',file:'Arquivo',select:'Combo',radio:'Radio',checkbox:'Checkbox',multiselect:'Seleção múltipla'}).map(([value,text]) => `<option value="${value}">${text}</option>`).join('')}</select></label></div>
        <div class="col-12"><label class="form-label">Grid — largura do campo<select class="form-select f-grid"><option value="12">12 — 1 campo por linha</option><option value="6">6 — 2 campos por linha</option><option value="4">4 — 3 campos por linha</option></select></label></div>
        <div class="col-12"><label class="form-label">Texto de exemplo<input class="form-control f-placeholder" value="${esc(field.placeholder)}"></label></div>
        <div class="col-12"><label class="form-label">Validação<select class="form-select f-validation"><option value="">Nenhuma</option><option value="cpf">CPF</option><option value="telefone">Telefone</option><option value="email">E-mail</option></select></label></div>
        <div class="col-12"><label class="form-check"><input class="form-check-input f-required" type="checkbox" ${field.obrigatorio ? 'checked' : ''}><span class="form-check-label">Preenchimento obrigatório</span></label></div>
        </div><div class="field-options border-top mt-3 pt-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><h3 class="h6 mb-0">Itens da lista</h3><label class="form-check criterion-toggle"><input class="form-check-input f-criterion" type="checkbox" ${field.criterio_vagas ? 'checked' : ''}><span class="form-check-label fw-semibold">Habilitar % de vaga</span></label></div><p class="small text-muted mt-2">O valor é sugerido pelo texto e pode ser editado. Ao habilitar vagas, informe uma porcentagem ou um número inteiro de vagas.</p><div class="option-items">${(field.opcoes || []).map(optionHtml).join('')}</div><button type="button" class="btn btn-outline-primary btn-sm add-option"><i class="bi bi-plus-lg me-1"></i>Adicionar item da lista</button></div>
        <div class="field-upload border-top mt-3 pt-3"><label class="form-label">Extensões aceitas<input class="form-control f-accept" placeholder="pdf,jpg" value="${esc((field.aceitos || []).join(','))}"></label><label class="form-label">Máximo de arquivos<input class="form-control f-max" type="number" min="1" max="10" value="${Number(field.max_arquivos) || 1}"></label></div></div></section>`;
        el.querySelector('.f-type').value = field.tipo || 'text';
        el.querySelector('.f-grid').value = [12,6,4].includes(Number(field.grid)) ? field.grid : 6;
        el.querySelector('.f-validation').value = field.validacao || '';
        el.querySelectorAll('.option-value').forEach(input => {
            if (input.value && input.value !== slug(input.closest('.option-item').querySelector('.option-text').value)) input.dataset.edited = '1';
        });
        root.append(el);
        refresh();
        if (focus) el.querySelector('.f-label').focus();
    }
    root.addEventListener('input', event => {
        const el = event.target.closest('.field');
        if (event.target.matches('.option-text')) {
            const value = event.target.closest('.option-item').querySelector('.option-value');
            if (!value.dataset.edited) value.value = slug(event.target.value);
        }
        if (event.target.matches('.option-value')) event.target.dataset.edited = '1';
        if (event.target.matches('.f-label')) {
            const name = el.querySelector('.f-name');
            if (!name.dataset.edited && !name.defaultValue) name.value = slug(event.target.value);
        }
        if (event.target.matches('.f-name')) event.target.dataset.edited = '1';
        event.target.setCustomValidity?.('');
        refresh();
    });
    root.addEventListener('change', refresh);
    root.addEventListener('focusout', event => {
        if (!event.target.matches('.option-percent') || event.target.disabled) return;
        const campo = event.target.closest('.field');
        const texto = event.target.value.trim().replace(',', '.');
        const numero = Number.parseFloat(texto.replace('%', ''));
        const base = quotaBase(campo);
        event.target.setCustomValidity('');
        if (!Number.isFinite(numero) || numero < 0) return;
        let percentual = numero;
        if (!texto.includes('%')) {
            if (numero > base) {
                event.target.setCustomValidity(`A cota deste critério comporta no máximo ${base} vagas.`);
                event.target.reportValidity();
                return;
            }
            percentual = base > 0 ? numero / base * 100 : 0;
        }
        if (percentual > 100) {
            event.target.setCustomValidity('O percentual não pode ultrapassar 100%.');
            event.target.reportValidity();
            return;
        }
        event.target.value = `${Math.round(percentual * 10000) / 10000}%`;
        validarSoma(campo);
        updateQuotaNumbers();
    });
    root.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const el = button.closest('.field');
        if (button.matches('.remove-field')) el.remove();
        if (button.matches('.move-up') && el.previousElementSibling) el.previousElementSibling.before(el);
        if (button.matches('.move-down') && el.nextElementSibling) el.nextElementSibling.after(el);
        if (button.matches('.remove-option')) button.closest('.option-item').remove();
        if (button.matches('.add-option')) {
            const list = el.querySelector('.option-items');
            list.insertAdjacentHTML('beforeend', optionHtml());
            list.lastElementChild.querySelector('input').focus();
        }
        refresh();
    });
    window.readBuilderFields = () => [...root.children].map(el => {
        const value = selector => el.querySelector(selector).value;
        return {...el._original, label:value('.f-label'), nome:value('.f-name'), tipo:value('.f-type'), grid:Number(value('.f-grid')), placeholder:value('.f-placeholder'), obrigatorio:el.querySelector('.f-required').checked,
            criterio_vagas:el.querySelector('.f-criterion').checked,
            opcoes:[...el.querySelectorAll('.option-item')].map(item => ({texto:item.querySelector('.option-text').value,valor:item.querySelector('.option-value').value,percentual_vagas:item.querySelector('.option-percent').disabled?null:parsePercent(item.querySelector('.option-percent').value)})),
            aceitos:value('.f-accept').split(',').map(v => v.trim()).filter(Boolean), max_arquivos:Number(value('.f-max')) || 1, validacao:value('.f-validation')};
    });
    document.getElementById('addField').onclick = () => add({grid:12}, true);
    const fields = initial.campos ?? (initial.rows || []).flatMap(row => row.columns.flatMap(col => col.fields));
    fields.forEach(field => add(field));
    criteriaUids = (initial.criterios_vagas || []).map(nome => [...root.children].find(el => el.querySelector('.f-name').value === nome)?.dataset.builderId).filter(Boolean);
    refresh();
    window.readBuilderCriteria = () => criteriaUids.map(uid => [...root.children].find(el => el.dataset.builderId === uid)?.querySelector('.f-name').value).filter(Boolean);
    window.refreshBuilderQuota = refresh;

    function criterionFields() {
        return [...root.children].filter(el => el.querySelector('.f-criterion').checked && !el.querySelector('.f-criterion').disabled);
    }
    function renderCriteria() {
        if (!criteriaRoot) return;
        const campos = criterionFields();
        const ids = campos.map(el => el.dataset.builderId);
        criteriaUids = [...new Set(criteriaUids.filter(uid => ids.includes(uid)))];
        criteriaUids.push(...ids.filter(uid => !criteriaUids.includes(uid)));
        criteriaRoot.innerHTML = criteriaUids.map((uid, indice) => `<div class="col-md-6"><label class="form-label">Selecione o ${indice + 1}º critério<select class="form-select criterion-order" data-index="${indice}" required>${campos.map(el => `<option value="${el.dataset.builderId}" ${el.dataset.builderId === uid ? 'selected' : ''}>${esc(el.querySelector('.f-label').value || el.querySelector('.f-name').value || 'Campo sem título')}</option>`).join('')}</select></label></div>`).join('');
        document.getElementById('criteriosVagasVazio').hidden = criteriaUids.length > 0;
        criteriaRoot.querySelectorAll('select').forEach(select => select.querySelectorAll('option').forEach(option => option.disabled = option.value !== select.value && criteriaUids.includes(option.value)));
    }
    criteriaRoot?.addEventListener('change', event => {
        if (!event.target.matches('.criterion-order')) return;
        criteriaUids[Number(event.target.dataset.index)] = event.target.value;
        renderCriteria();
        updateQuotaNumbers();
    });
    function parsePercent(valor) {
        const numero = Number.parseFloat(String(valor).replace(',', '.').replace('%', ''));
        return Number.isFinite(numero) ? Math.round(numero * 10000) / 10000 : null;
    }
    function quotaBase(campo) {
        let base = Math.max(0, Number(document.getElementById('limite_inscricoes')?.value) || 0);
        const indice = criteriaUids.indexOf(campo.dataset.builderId);
        for (let i = 0; i < indice; i++) {
            const anterior = [...root.children].find(el => el.dataset.builderId === criteriaUids[i]);
            const percentual = parsePercent(anterior?.querySelector('.option-percent')?.value) || 0;
            base = Math.floor(base * percentual / 100);
        }
        return base;
    }
    function updateQuotaNumbers() {
        criterionFields().forEach(campo => {
            const base = quotaBase(campo);
            campo.querySelectorAll('.option-item').forEach(item => {
                const percentual = parsePercent(item.querySelector('.option-percent').value) || 0;
                item.querySelector('.option-quota-number').textContent = `${Math.floor(base * percentual / 100 + 0.000001)} vaga(s) de uma cota de ${base}`;
            });
        });
    }
    function validarSoma(campo) {
        const inputs = [...campo.querySelectorAll('.option-percent:not(:disabled)')];
        inputs.forEach(input => input.setCustomValidity(''));
        if (inputs.reduce((soma, input) => soma + (parsePercent(input.value) || 0), 0) > 100.001) {
            const ultimo = inputs.at(-1);
            ultimo.setCustomValidity('A soma dos percentuais deste critério não pode ultrapassar 100%.');
            ultimo.reportValidity();
        }
    }
})();
