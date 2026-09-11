(() => {
    const origemScript = document.currentScript.src;
    const base = new URL('vendor/dashboard-export/', origemScript);
    const cache = new Map();
    const ler = nome => {
        if (!cache.has(nome)) cache.set(nome, fetch(new URL(nome, base)).then(r => {
            if (!r.ok) throw new Error('Não foi possível carregar os recursos de exportação.');
            return r.text();
        }).catch(erro => { cache.delete(nome); throw erro; }));
        return cache.get(nome);
    };
    const estilo = `body{background:#f4f6f9;color:#212529;font-family:Arial,sans-serif;padding:24px}.exportacao{max-width:1400px;margin:auto}.card{background:#fff;border:1px solid #dee2e6;border-radius:12px;overflow:hidden}.card-header{padding:18px 24px;background:#fff;border-bottom:1px solid #dee2e6}.card-body{padding:24px}.dashboard-icone{width:48px;height:48px;display:grid;place-items:center;border-radius:14px;background:#e7efff}.dashboard-grafico{position:relative;height:340px}.dashboard-legenda-cor{width:12px;height:12px;border-radius:3px;display:inline-block}.exportacao-info{font-size:13px;color:#586174;margin-bottom:16px}.exportacao-filtros{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px}.exportacao-filtros span{padding:8px 12px;border:1px solid #dee2e6;border-radius:6px}canvas{max-width:100%}td{overflow-wrap:anywhere}@media print{body{padding:0;background:#fff}}`;
    function nomeArquivo() {
        const partes = Object.fromEntries(new Intl.DateTimeFormat('pt-BR', {timeZone:'America/Sao_Paulo',day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date()).map(p=>[p.type,p.value]));
        return `Dashboard - Eventos GI - ${partes.day}-${partes.month}-${partes.year}-${partes.hour}h${partes.minute}`;
    }
    function baixar(blob, nome) {
        const url = URL.createObjectURL(blob), link = document.createElement('a');
        link.href = url; link.download = nome; link.click();
        setTimeout(() => URL.revokeObjectURL(url), 30000);
    }
    function preparar(card) {
        const clone = card.cloneNode(true);
        clone.querySelectorAll('[data-export-actions],.select2-container').forEach(el=>el.remove());
        clone.querySelectorAll('form').forEach(form => {
            const resumo = document.createElement('div'); resumo.className = 'exportacao-filtros';
            form.querySelectorAll('select').forEach(select => {
                const original = card.querySelector(`#${select.id}`);
                const label = form.querySelector(`label[for="${select.id}"]`);
                const item = document.createElement('span');
                item.textContent = `${label?.textContent.trim() || 'Filtro'}: ${original?.selectedOptions[0]?.textContent.trim() || 'Nenhum'}`;
                resumo.append(item);
            });
            form.replaceWith(resumo);
        });
        clone.querySelectorAll('[style]').forEach(el => {
            if (el.style.overflow || el.style.overflowY || el.style.maxHeight) {
                el.style.maxHeight='none'; el.style.overflow='visible'; el.style.overflowY='visible';
            }
        });
        // Ícones decorativos não dependem de fontes externas no arquivo exportado.
        clone.querySelectorAll('i.bi').forEach(el => {el.textContent='●';el.className='';});
        const graficos = [];
        card.querySelectorAll('canvas').forEach(canvas => {
            const chart = window.Chart?.getChart(canvas);
            if (!chart) return;
            const alvo = clone.querySelector(`#${canvas.id}`);
            alvo.parentElement.style.height = `${Math.max(canvas.clientHeight, 180)}px`;
            graficos.push({id:canvas.id,type:chart.config.type,data:JSON.parse(JSON.stringify(chart.data)),
                horizontal:chart.config.options.indexAxis === 'y',
                eixoX:chart.config.options.scales?.x?.title?.text || '',
                eixoY:chart.config.options.scales?.y?.title?.text || ''});
        });
        return {clone,graficos};
    }
    const jsonSeguro = valor => JSON.stringify(valor).replace(/</g,'\\u003c').replace(/\u2028/g,'\\u2028').replace(/\u2029/g,'\\u2029');
    async function html(card, nome) {
        const {clone,graficos} = preparar(card);
        const [css,chartJs] = await Promise.all([ler('bootstrap.min.css'), graficos.length ? ler('chart.umd.min.js') : Promise.resolve('')]);
        const titulo = document.createElement('div'); titulo.textContent = `${nome} · ${card.querySelector('h2').textContent}`;
        const inicializar = `const graficos=${jsonSeguro(graficos)};graficos.forEach(g=>{const pie=g.type==='pie';new Chart(document.getElementById(g.id),{type:g.type,data:g.data,options:{responsive:true,maintainAspectRatio:false,indexAxis:g.horizontal?'y':'x',plugins:{legend:{display:pie,position:'bottom'},tooltip:{callbacks:{label:c=>{const soma=g.data.datasets[0].data.reduce((a,b)=>a+(Number(b)||0),0);return c.raw+' inscrições'+(pie&&soma?' ('+(c.raw*100/soma).toFixed(1)+'%)':'')}}}},...(pie?{}:{scales:{x:{beginAtZero:g.horizontal,title:{display:!!g.eixoX,text:g.eixoX},ticks:g.horizontal?{precision:0}:{maxTicksLimit:8}},y:{beginAtZero:!g.horizontal,title:{display:!!g.eixoY,text:g.eixoY},ticks:g.horizontal?{autoSkip:false}:{precision:0}}}})}})});`;
        const conteudo = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${titulo.innerHTML}</title><style>${css}\n${estilo}</style></head><body><main class="exportacao"><p class="exportacao-info">${titulo.innerHTML}</p>${clone.outerHTML}</main><script>${chartJs.replace(/<\/script/gi,'<\\/script')}<\/script><script>${inicializar}<\/script></body></html>`;
        baixar(new Blob([conteudo],{type:'text/html;charset=utf-8'}),nome+'.html');
    }
    async function imagem(card, nome, pdf) {
        const {clone} = preparar(card);
        card.querySelectorAll('canvas').forEach(canvas => {
            const alvo = clone.querySelector(`#${canvas.id}`);
            const img = document.createElement('img'); img.src=canvas.toDataURL('image/png');
            img.style.width='100%'; img.style.height='100%'; img.style.objectFit='contain';
            alvo.replaceWith(img);
        });
        const area = document.createElement('div');
        area.style.cssText='position:fixed;left:-20000px;top:0;width:1280px;padding:24px;background:#f4f6f9;';
        const info=document.createElement('p'); info.textContent=nome; info.className='small text-muted';
        area.append(info,clone); document.body.append(area);
        try {
            await Promise.all(Array.from(area.querySelectorAll('img'), img=>img.decode()));
            const canvas = await window.html2canvas(area,{scale:1.5,backgroundColor:'#f4f6f9',logging:false,windowWidth:1440});
            if (!pdf) {
                const blob = await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
                if (!blob) throw new Error('Não foi possível gerar a imagem.');
                baixar(blob,nome+'.png'); return;
            }
            const doc = new window.jspdf.jsPDF({orientation:'portrait',unit:'mm',format:'a4'});
            const largura = 190, altura = 277;
            const alturaPagina = Math.floor(canvas.width * altura / largura);
            for (let y=0;y<canvas.height;y+=alturaPagina) {
                const fatia=document.createElement('canvas'); fatia.width=canvas.width; fatia.height=Math.min(alturaPagina,canvas.height-y);
                fatia.getContext('2d').drawImage(canvas,0,y,canvas.width,fatia.height,0,0,canvas.width,fatia.height);
                if (y>0) doc.addPage();
                doc.addImage(fatia.toDataURL('image/png'),'PNG',10,10,largura,fatia.height*largura/canvas.width);
            }
            doc.save(nome+'.pdf');
        } finally { area.remove(); }
    }
    document.addEventListener('click', async event => {
        const botao=event.target.closest('[data-dashboard-export]');
        if (!botao) return;
        const card=botao.closest('.card'), botoes=card.querySelectorAll('[data-dashboard-export]');
        botoes.forEach(b=>b.disabled=true);
        card.setAttribute('aria-busy','true');
        try {
            const nome=nomeArquivo(), tipo=botao.dataset.dashboardExport;
            if(tipo==='html') await html(card,nome); else await imagem(card,nome,tipo==='pdf');
        } catch(erro) { alert(erro.message || 'Falha ao exportar o card. Tente novamente.'); }
        finally {botoes.forEach(b=>b.disabled=false);card.removeAttribute('aria-busy');}
    });
})();
