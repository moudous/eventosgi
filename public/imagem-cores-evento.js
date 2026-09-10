(() => {
    'use strict';
    const get = id => document.getElementById(id);
    if (!get('imagem-cores-evento')) return;
    const input = get('imagem_evento'), canvas = get('evento-previa'), ctx = canvas.getContext('2d');
    const status = get('evento-imagem-status'), list = get('evento-cores');
    let source = null, original = null, selection = null, start = null, version = 0;
    const notify = message => { status.textContent = message; };
    function colors(values) {
        list.replaceChildren();
        (Array.isArray(values) ? values : []).forEach(addColor);
        renumber();
    }
    function renumber() {
        [...list.children].forEach((row, i) => {
            row.querySelector('label').textContent = `${i + 1}ª Cor`;
            row.querySelector('label').htmlFor = `evento-cor-${i}`;
            row.querySelector('input[type=text]').id = `evento-cor-${i}`;
            row.querySelector('input[type=color]').setAttribute('aria-label', `Selecionar ${i + 1}ª cor`);
            row.querySelector('button').setAttribute('aria-label', `Remover ${i + 1}ª cor`);
        });
        get('evento-adicionar-cor').disabled = list.children.length >= 32;
    }
    function addColor(value = '#000000') {
        if (list.children.length >= 32) return;
        const row = document.createElement('div');
        row.className = 'd-flex flex-wrap gap-1 align-content-start';
        row.innerHTML = '<label class="w-100 mb-0"></label><input type="color" class="form-control form-control-color p-1" style="width:2rem"><input type="text" name="cores[]" class="form-control px-1 font-monospace" style="min-width:0;width:4.5rem;flex:1" pattern="#[0-9A-Fa-f]{6}" maxlength="7" required placeholder="#000000" title="Informe # seguido de seis dígitos hexadecimais"><button type="button" class="btn btn-outline-danger btn-sm">Remover</button>';
        const picker = row.querySelector('input[type=color]'), text = row.querySelector('input[type=text]');
        text.value = String(value).toUpperCase();
        if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value;
        picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); });
        text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
        text.addEventListener('change', () => { text.value = text.value.toUpperCase(); });
        row.querySelector('button').onclick = () => { row.remove(); renumber(); };
        list.append(row);
        renumber();
    }
    function draw() {
        if (!source) return;
        const scale = Math.min(1, 900 / source.width, 600 / source.height);
        canvas.width = Math.round(source.width * scale);
        canvas.height = Math.round(source.height * scale);
        canvas.hidden = false;
        ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
        if (selection) {
            ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 2;
            ctx.strokeRect(selection.x, selection.y, selection.w, selection.h);
            ctx.setLineDash([5, 5]); ctx.strokeStyle = '#000000';
            ctx.strokeRect(selection.x, selection.y, selection.w, selection.h); ctx.setLineDash([]);
        }
        get('evento-extrair').disabled = false;
        get('evento-recortar').disabled = !selection || selection.w < 2 || selection.h < 2;
    }
    async function load(url) {
        const img = new Image(); img.src = url; await img.decode();
        if (img.width > 12000 || img.height > 12000) throw new Error('Use uma imagem com até 12000 pixels de largura e altura.');
        return img;
    }
    input.onchange = async () => {
        const token = ++version, file = input.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
            input.value = ''; notify('Selecione JPG, PNG ou WebP com até 8 MB.'); return;
        }
        const url = URL.createObjectURL(file);
        try {
            const img = await load(url);
            if (token !== version) return;
            original = { image: img, file }; source = img; selection = null; draw();
            get('evento-restaurar').disabled = false; notify('Imagem carregada. Você pode recortar ou extrair as cores.');
        } catch (error) { input.value = ''; notify(error.message || 'Não foi possível abrir a imagem.'); }
        finally { URL.revokeObjectURL(url); }
    };
    const point = event => {
        const rect = canvas.getBoundingClientRect();
        return { x: Math.max(0, Math.min(canvas.width, (event.clientX - rect.left) * canvas.width / rect.width)), y: Math.max(0, Math.min(canvas.height, (event.clientY - rect.top) * canvas.height / rect.height)) };
    };
    canvas.onpointerdown = event => { start = point(event); selection = null; canvas.setPointerCapture(event.pointerId); };
    canvas.onpointermove = event => {
        if (!start) return;
        const end = point(event);
        selection = { x: Math.min(start.x, end.x), y: Math.min(start.y, end.y), w: Math.abs(end.x - start.x), h: Math.abs(end.y - start.y) }; draw();
    };
    canvas.onpointerup = canvas.onpointercancel = () => { start = null; };
    function setFile(file) { const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; }
    get('evento-recortar').onclick = async () => {
        if (!selection || selection.w < 2 || selection.h < 2) return;
        const token = ++version, crop = document.createElement('canvas'), ratio = source.width / canvas.width;
        crop.width = Math.max(1, Math.round(selection.w * ratio)); crop.height = Math.max(1, Math.round(selection.h * ratio));
        crop.getContext('2d').drawImage(source, selection.x * ratio, selection.y * ratio, selection.w * ratio, selection.h * ratio, 0, 0, crop.width, crop.height);
        get('evento-recortar').disabled = true;
        const blob = await new Promise(resolve => crop.toBlob(resolve, 'image/png'));
        if (token !== version) return;
        if (!blob || blob.size > 8 * 1024 * 1024) { notify('O recorte excedeu 8 MB. Selecione uma área menor.'); draw(); return; }
        setFile(new File([blob], 'evento.png', { type: 'image/png' })); source = crop; selection = null; draw();
        notify('Recorte aplicado. Clique em Extrair cores para usar a imagem recortada.');
    };
    get('evento-restaurar').onclick = () => {
        ++version; source = original.image; selection = null;
        if (original.file) setFile(original.file); else input.value = '';
        draw(); notify('Imagem original restaurada.');
    };
    get('evento-extrair').onclick = () => {
        if (!source) return;
        const sample = document.createElement('canvas'), scale = Math.min(1, 400 / Math.max(source.width, source.height));
        sample.width = Math.max(1, Math.round(source.width * scale)); sample.height = Math.max(1, Math.round(source.height * scale));
        const context = sample.getContext('2d', { willReadFrequently: true });
        context.drawImage(source, 0, 0, sample.width, sample.height);
        const data = context.getImageData(0, 0, sample.width, sample.height).data, buckets = new Map();
        for (let i = 0; i < data.length; i += 4) {
            if (data[i + 3] < 128) continue;
            const key = (data[i] >> 4) * 256 + (data[i + 1] >> 4) * 16 + (data[i + 2] >> 4);
            const b = buckets.get(key) || { count: 0, r: 0, g: 0, b: 0 };
            b.count++; b.r += data[i]; b.g += data[i + 1]; b.b += data[i + 2]; buckets.set(key, b);
        }
        const palette = [...buckets.values()].sort((a, b) => b.count - a.count).slice(0, Number(get('evento-quantidade').value)).map(b => '#' + [b.r, b.g, b.b].map(v => Math.round(v / b.count).toString(16).padStart(2, '0')).join('').toUpperCase());
        if (!palette.length) { notify('A imagem não contém pixels visíveis para extrair cores.'); return; }
        colors(palette); notify(`${palette.length} cores extraídas em ordem de ocorrência. A paleta anterior foi substituída.`);
    };
    get('evento-adicionar-cor').onclick = () => addColor();
    colors(window.eventoImagemCores.cores);
    if (window.eventoImagemCores.imagem) {
        const token = version;
        load(window.eventoImagemCores.imagem).then(img => {
            if (token !== version) return;
            original = { image: img, file: null }; source = img; draw(); get('evento-restaurar').disabled = false;
        }).catch(() => notify('Não foi possível carregar a imagem salva. Selecione uma nova imagem.'));
    }
})();
