<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Gestão de Eventos</title>
    <style>
        body { font: 16px system-ui; background: #f5f7fa; color: #17243a; padding: 2rem; }
        .card { max-width: 1100px; margin: auto; background: #fff; padding: 2rem; border-radius: 12px; }
        .execution-context { margin: 1rem 0; padding: 1rem 1.25rem; border: 1px solid; border-radius: 8px; }
        .execution-context.inside { color: #155724; background: #d4edda; border-color: #badbcc; }
        .execution-context.outside { color: #664d03; background: #fff3cd; border-color: #ffecb5; }
        .execution-context strong { display: block; margin-bottom: .25rem; }
        pre { background: #17243a; color: #e8eef8; padding: 1.25rem; border-radius: 8px; overflow: auto; }
        .actions { display: flex; gap: .75rem; margin: 1rem 0; }
        button { border: 0; border-radius: 7px; padding: .7rem 1rem; color: #fff; background: #0d6efd; cursor: pointer; }
        .context-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(210px,1fr)); gap: 1rem; margin: 1.5rem 0; }
        .context-card { padding: 1rem; border: 1px solid #dce3ec; border-radius: 10px; background: #f8fafc; }
        .context-card small { display:block; color:#64748b; margin-bottom:.35rem; }
        .permissions { margin: 1.5rem 0; padding: 1.25rem; border: 1px solid #bfdbfe; border-radius: 10px; background:#eff6ff; }
        .permission { display:inline-block; margin:.2rem; padding:.35rem .55rem; border-radius:6px; color:#1e3a8a; background:#dbeafe; font:700 .8rem ui-monospace,monospace; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Gestão de Eventos</h1>
        <p>Sessão criada com segurança pelo GI.</p>
        @if(session('manutencao'))<p class="execution-context inside"><strong>Manutenção</strong>{{ session('manutencao') }}</p>@endif
        @if(data_get($context, 'atualizacao_usuarios.realizada'))<p class="execution-context inside"><strong>Usuários atualizados</strong>O GI informou acréscimo de usuários e enviou {{ data_get($context, 'atualizacao_usuarios.total', 0) }} cadastro(s).</p>@endif
        <div id="executionContext" class="execution-context" role="status"></div>
        <section class="context-grid" aria-label="Contexto do perfil">
            <div class="context-card"><small>Usuário</small><strong>{{ data_get($context, 'usuario.nome', 'Não informado') }}</strong><div>{{ data_get($context, 'usuario.email') }}</div></div>
            <div class="context-card"><small>Sistema</small><strong>{{ data_get($context, 'sistema.nome', 'Não informado') }}</strong><div>ID {{ data_get($context, 'sistema.id', '—') }}</div></div>
            <div class="context-card"><small>Perfil</small><strong>{{ data_get($context, 'perfil.nome', 'Não informado') }}</strong><div>ID {{ data_get($context, 'perfil.id', '—') }}</div></div>
            <div class="context-card"><small>Caminho solicitado</small><strong>{{ data_get($context, 'caminho', '/') }}</strong><div>Emitido em {{ data_get($context, 'emitido_em', '—') }}</div></div>
        </section>
        <section class="permissions"><strong>Permissões entregues para este perfil</strong><div>@forelse((array)data_get($context, 'permissoes', []) as $permission)<code class="permission">{{ $permission }}</code>@empty<span> Nenhuma permissão foi concedida.</span>@endforelse</div></section>
        <div class="actions"><button data-resource="perfis">Carregar perfis</button><button data-resource="usuarios">Carregar usuários</button><button data-resource="grupos">Carregar grupos</button></div>
        <div class="actions" aria-label="Manutenção do Laravel"><form method="POST" action="{{ route('manutencao.executar', 'optimize-clear') }}">@csrf<button type="submit">php artisan optimize:clear</button></form><form method="POST" action="{{ route('manutencao.executar', 'config-cache') }}">@csrf<button type="submit">php artisan config:cache</button></form></div>
        <h2>Contexto JSON recebido</h2><pre>{{ json_encode($context, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre><pre id="result" hidden></pre>
    </main>
    <script>
        const contextBlock = document.getElementById('executionContext');
        const isInsideGi = window.self !== window.top;

        contextBlock.classList.add(isInsideGi ? 'inside' : 'outside');
        contextBlock.innerHTML = isInsideGi
            ? '<strong>Executando dentro do GI</strong>Esta página está sendo exibida no ambiente integrado do sistema GI.'
            : '<strong>Executando fora do GI</strong>Esta página foi aberta diretamente, fora do ambiente integrado do sistema GI.';
        document.querySelectorAll('[data-resource]').forEach(button => button.addEventListener('click', async () => {
            const result = document.getElementById('result');
            result.hidden = false;
            result.textContent = 'Carregando...';
            try {
                const response = await fetch('/gi/' + button.dataset.resource, {headers: {'Accept': 'application/json'}});
                const json = await response.json();
                result.textContent = JSON.stringify(json, null, 2);
            } catch (error) {
                result.textContent = JSON.stringify({message: error.message}, null, 2);
            }
        }));
    </script>
</body>
</html>