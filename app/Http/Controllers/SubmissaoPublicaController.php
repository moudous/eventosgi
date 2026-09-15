<?php

namespace App\Http\Controllers;

use App\Models\CredencialSubmissao;
use App\Services\SenhaCompartilhadaService;
use App\Models\InscricaoSubmissaoTrabalho;
use App\Models\Submissao;
use App\Services\GiEmailService;
use App\Services\CaptchaInscricaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SubmissaoPublicaController
{
    public const HORAS_VALIDADE_SENHA_TEMPORARIA = 48;

    private const SESSAO_OCIOSA_SEGUNDOS = 30 * 60;
    private const SESSAO_TOTAL_SEGUNDOS = 8 * 60 * 60;

    public function formulario(Request $request, Submissao $submissao): View
    {
        $submissao->load('evento');
        $submissao->atualizarStatusDoPrazo();
        $acesso = $this->acessoValido($request, $submissao);
        $trabalhos = collect();
        $inscricao = null;
        $trabalho = null;

        if ($acesso) {
            $inscricao = $submissao->inscricoes()->where('email', $acesso['email'])->first();
            $trabalhos = $this->trabalhosAcessiveis($submissao, $acesso['email'])->with(['autores', 'inscricao'])->get();
            $trabalho = $trabalhos->firstWhere('id', (int) ($acesso['trabalho_atual'] ?? 0)) ?? $trabalhos->first();
        }

        $novo = $acesso && $submissao->aberta() && ($request->boolean('novo') || $trabalhos->isEmpty());

        return view('submissoes.publico', compact(
            'submissao', 'acesso', 'trabalhos', 'inscricao', 'trabalho', 'novo'
        ));
    }

    public function criar(Request $request, Submissao $submissao): RedirectResponse
    {
        $this->exigirAberta($submissao);
        $acesso = $this->acessoValido($request, $submissao);
        abort_unless($acesso, 403);
        [$dados, $autores] = $this->validarTrabalho($request, $submissao, $acesso['email']);
        $this->limitarCriacao($request, $submissao, $acesso['email']);

        [$inscricao, $trabalho] = DB::transaction(function () use ($dados, $autores, $submissao, $acesso): array {
            $inscricao = $submissao->inscricoes()->firstOrCreate(['email' => $acesso['email']], [
                // Campo legado obrigatório; a autenticação usa apenas credenciais_submissao.
                'senha' => Hash::make(Str::random(64)),
            ]);
            $trabalho = $inscricao->trabalhos()->create([
                'titulo_trabalho' => $dados['titulo_trabalho'],
                'conteudo' => $dados['conteudo'],
                'categoria_trabalho' => $dados['categoria_trabalho'],
                'palavras_chave' => $dados['palavras_chave'],
                'tem_apoio_financeiro' => $dados['tem_apoio_financeiro'],
                'apoiador' => $dados['apoiador'],
                'apresentacao' => $dados['apresentacao'],
                'aprovacao_comite_etica' => $dados['aprovacao_comite_etica'],
                'protocolo_comite_etica' => $dados['protocolo_comite_etica'],
                'status' => 'rascunho',
            ]);
            $this->gravarAutores($trabalho, $autores);

            return [$inscricao, $trabalho];
        });

        $this->iniciarAcesso($request, $submissao, CredencialSubmissao::findOrFail($acesso['credencial_id']), $trabalho->id);

        return redirect()->route('submissoes.publicas.formulario', $submissao)
            ->with('status', 'Trabalho salvo. Você poderá alterá-lo enquanto o período de submissão estiver aberto.');
    }

    public function entrar(Request $request, Submissao $submissao): RedirectResponse
    {
        abort_unless($submissao->ativo && (bool) $submissao->evento?->ativo, 404);
        $dados = $request->validate([
            'email_login' => ['required', 'email', 'max:150'],
            'senha_login' => ['required', 'string', 'max:200'],
        ], ['email_login.required' => 'Informe o e-mail.', 'senha_login.required' => 'Informe a senha.']);
        $email = mb_strtolower(trim($dados['email_login']));
        $this->limitarLogin($request, $submissao, $email);

        $credencial = CredencialSubmissao::where('email', $email)->first();
        $permanente = $credencial?->senha && Hash::check($dados['senha_login'], $credencial->senha);
        $temporaria = $credencial?->temporaria_expira_em?->isFuture()
            && $credencial->temporaria_hash && Hash::check($dados['senha_login'], $credencial->temporaria_hash);
        if (! $permanente && ! $temporaria) {
            throw ValidationException::withMessages(['email_login' => 'E-mail ou senha inválidos.']);
        }

        $this->limparLimitesLogin($request, $submissao, $email);
        $this->iniciarAcesso($request, $submissao, $credencial, null);

        return redirect()->route('submissoes.publicas.formulario', $submissao);
    }

    public function selecionar(Request $request, Submissao $submissao): RedirectResponse
    {
        $dados = $request->validate(['trabalho_id' => ['required', 'integer']]);
        $acesso = $this->acessoValido($request, $submissao);
        abort_unless($acesso, 403);
        abort_unless($this->trabalhosAcessiveis($submissao, $acesso['email'])->whereKey($dados['trabalho_id'])->exists(), 403);
        $acesso['trabalho_atual'] = (int) $dados['trabalho_id'];
        $acesso['ultimo_acesso'] = now()->timestamp;
        $request->session()->put($this->chaveSessao($submissao), $acesso);

        return redirect()->route('submissoes.publicas.formulario', $submissao);
    }

    public function atualizar(Request $request, Submissao $submissao, InscricaoSubmissaoTrabalho $trabalho): RedirectResponse
    {
        $this->exigirAcesso($request, $submissao, $trabalho, true);
        $this->exigirEditavel($submissao, $trabalho);
        [$dados, $autores] = $this->validarTrabalho($request, $submissao, $trabalho->inscricao->email, $trabalho);

        DB::transaction(function () use ($dados, $autores, $trabalho): void {
            $trabalho->update([
                'titulo_trabalho' => $dados['titulo_trabalho'],
                'conteudo' => $dados['conteudo'],
                'categoria_trabalho' => $dados['categoria_trabalho'],
                'palavras_chave' => $dados['palavras_chave'],
                'tem_apoio_financeiro' => $dados['tem_apoio_financeiro'],
                'apoiador' => $dados['apoiador'],
                'apresentacao' => $dados['apresentacao'],
                'aprovacao_comite_etica' => $dados['aprovacao_comite_etica'],
                'protocolo_comite_etica' => $dados['protocolo_comite_etica'],
            ]);
            $trabalho->autores()->delete();
            $this->gravarAutores($trabalho, $autores);
        });

        return redirect()->route('submissoes.publicas.formulario', $submissao)->with('status', 'Alterações salvas com sucesso.');
    }

    public function exportarDocumento(
        Request $request,
        Submissao $submissao,
        InscricaoSubmissaoTrabalho $trabalho,
    ): Response {
        $this->exigirAcesso($request, $submissao, $trabalho);
        $trabalho->loadMissing(['autores', 'inscricao']);

        $nomes = $trabalho->autores->map(function ($autor): string {
            $numero = (int) ($autor->numero ?: $autor->ordem);

            return e($autor->nome).'<sup>'.$numero.'</sup>';
        })->implode('; ');
        $afiliacoes = $trabalho->autores->map(function ($autor): string {
            $numero = (int) ($autor->numero ?: $autor->ordem);

            return '<div><sup>'.$numero.'</sup> '.nl2br(e($autor->afiliacao ?: 'Filiação não informada')).'</div>';
        })->implode('');
        $apoio = $trabalho->tem_apoio_financeiro ? ($trabalho->apoiador ?: 'Sim') : 'Não';
        $etica = $trabalho->aprovacao_comite_etica
            ? 'Sim. Protocolo: '.($trabalho->protocolo_comite_etica ?: 'Não informado')
            : 'Não';
        $resumo = $trabalho->conteudo ?: '<p>Sem resumo informado.</p>';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            .'body{font-family:Arial,sans-serif;font-size:12pt;line-height:1.5;color:#000}'
            .'h1{text-align:center;font-size:16pt;margin-bottom:18pt}.autores{text-align:center}'
            .'.afiliacoes{margin:12pt 0 24pt}.resumo-titulo{font-weight:bold}.espaco{height:36pt}'
            .'hr{border:0;border-top:1px solid #000;margin:12pt 0}.dados p{margin:4pt 0}'
            .'</style></head><body>'
            .'<h1>'.e($trabalho->titulo_trabalho).'</h1>'
            .'<div class="autores">'.$nomes.'</div><div class="afiliacoes">'.$afiliacoes.'</div>'
            .($submissao->mostrar_categoria_trabalho && $trabalho->categoriaTrabalhoRotulo() ? '<p><strong>Categoria do trabalho:</strong> '.e($trabalho->categoriaTrabalhoRotulo()).'</p>' : '')
            .($submissao->mostrar_palavras_chave && $trabalho->palavras_chave ? '<p><strong>Palavras Chave:</strong> '.e($trabalho->palavras_chave).'</p>' : '')
            .'<p class="resumo-titulo">RESUMO:</p><div>'.$resumo.'</div>'
            .'<div class="espaco">&nbsp;</div><hr><div class="dados">'
            .'<p><em>E-mail do primeiro autor: '.e($trabalho->inscricao->email).'</em></p>'
            .'<p><em>Apoio Financeiro (se houver): '.e($apoio).'</em></p>'
            .'<p><em>Aprovação do Comitê de Ética: '.e($etica).'</em></p>'
            .'<p>Apresentação: '.e(ucfirst($trabalho->apresentacao ?? 'presencial')).'</p>'
            .'</div></body></html>';
        $arquivo = (Str::slug($trabalho->titulo_trabalho) ?: 'resumo').'.doc';

        return response("\xEF\xBB\xBF".$html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$arquivo.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function excluir(Request $request, Submissao $submissao, InscricaoSubmissaoTrabalho $trabalho): RedirectResponse
    {
        $this->exigirAcesso($request, $submissao, $trabalho, true);
        $this->exigirEditavel($submissao, $trabalho);
        $titulo = $trabalho->titulo_trabalho;
        $inscricao = $trabalho->inscricao;
        $trabalho->delete();

        $acesso = $this->acessoValido($request, $submissao);
        if ($acesso) {
            $acesso['trabalho_atual'] = $inscricao->trabalhos()->value('id');
            $acesso['ultimo_acesso'] = now()->timestamp;
            $request->session()->put($this->chaveSessao($submissao), $acesso);
        }

        return redirect()->route('submissoes.publicas.formulario', $submissao)
            ->with('status', "O trabalho \"{$titulo}\" foi apagado.");
    }

    public function alterarSenha(Request $request, Submissao $submissao): RedirectResponse
    {
        $acesso = $this->acessoValido($request, $submissao);
        abort_unless($acesso, 403);
        $dados = $request->validate([
            'senha_atual' => ['required', 'string'],
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'usar_na_atividade' => ['required', 'boolean'],
        ]);
        $this->limitarLogin($request, $submissao, $acesso['email']);
        DB::transaction(function () use ($acesso, $dados): void {
            $credencial = CredencialSubmissao::whereKey($acesso['credencial_id'])->lockForUpdate()->firstOrFail();
            $valida = ($credencial->senha && Hash::check($dados['senha_atual'], $credencial->senha))
                || ($credencial->temporaria_expira_em?->isFuture() && $credencial->temporaria_hash
                    && Hash::check($dados['senha_atual'], $credencial->temporaria_hash));
            if (! $valida) throw ValidationException::withMessages(['senha_atual' => 'A senha atual está incorreta.']);
            $servico = app(SenhaCompartilhadaService::class);
            $hash = Hash::make($dados['senha']);
            $servico->atualizarSubmissao($credencial->email, $hash);
            if ($dados['usar_na_atividade']) $servico->atualizarAtividade($credencial->email, $hash);
        });
        $this->limparLimitesLogin($request, $submissao, $acesso['email']);
        $this->iniciarAcesso($request, $submissao, CredencialSubmissao::findOrFail($acesso['credencial_id']), $acesso['trabalho_atual']);
        return redirect()->route('submissoes.publicas.formulario', $submissao)->with('status', 'Senha alterada com sucesso.');
    }

    public function esqueciSenha(Request $request, Submissao $submissao, GiEmailService $emailService, CaptchaInscricaoService $captcha): RedirectResponse
    {
        abort_unless($submissao->ativo && (bool) $submissao->evento?->ativo, 404);
        $dados = $request->validate([
            'email_recuperacao' => ['required', 'email', 'max:150'],
            'cadastrar' => ['sometimes', 'boolean'],
            'captcha' => ['required', 'string', 'size:6'],
            'website' => ['nullable', 'max:0'],
        ], ['email_recuperacao.required' => 'Informe o e-mail.', 'captcha.required' => 'Digite o texto exibido na imagem.', 'captcha.size' => 'Digite os 6 caracteres exibidos na imagem.', 'website.max' => 'Não foi possível processar a solicitação.']);
        $captcha->validarSubmissao($request, $submissao, $dados['captcha']);
        $email = mb_strtolower(trim($dados['email_recuperacao']));
        $this->limitarRecuperacao($request, $submissao, $email);
        $credencial = CredencialSubmissao::where('email', $email)->first();
        $trabalhos = $this->trabalhosAcessiveis($submissao, $email)->with('autores')->get();
        if (! $credencial && ($trabalhos->isNotEmpty() || ($request->boolean('cadastrar') && $submissao->aberta()))) {
            $credencial = CredencialSubmissao::firstOrCreate(['email' => $email]);
        }
        if (! $credencial) return back()->with('recuperacao', 'Se o e-mail estiver cadastrado, uma senha temporária será enviada.');

        $senhaTemporaria = strtoupper(bin2hex(random_bytes(5))).random_int(1000, 9999);
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);
        $credencial->update([
            'temporaria_hash' => Hash::make($senhaTemporaria),
            'temporaria_expira_em' => now()->addHours(self::HORAS_VALIDADE_SENHA_TEMPORARIA),
            'redefinicao_token_hash' => $tokenHash,
            'redefinicao_expira_em' => now()->addMinutes(60),
        ]);
        $url = route('submissoes.publicas.formulario', $submissao);
        $urlSenha = route('senha-submissao.editar', ['token' => $token]);
        $titulos = $trabalhos->pluck('titulo_trabalho')->map(fn ($titulo) => '<li>'.e($titulo).'</li>')->implode('');
        $conteudo = '<p>Olá.</p><p>Sua senha temporária de submissão é: <strong>'.e($senhaTemporaria).'</strong></p>'
            .'<p>Ela vale por '.self::HORAS_VALIDADE_SENHA_TEMPORARIA.' horas e pode ser utilizada nas outras submissões.</p>'
            .($titulos ? '<p>Trabalhos vinculados:</p><ul>'.$titulos.'</ul>' : '')
            .'<p><a href="'.e($url).'">Acessar a área de submissão</a></p>'
            .'<p><a href="'.e($urlSenha).'">Alterar senha de submissão</a> (link válido por 60 minutos e para um único uso).</p>'
            .'<p>Se você não fez este pedido, ignore esta mensagem.</p>';
        try {
            $emailService->enviar($email, null, 'Senha temporária — '.$submissao->titulo, $conteudo, 'submissao-senha-'.$submissao->id.'-'.Str::random(16));
        } catch (Throwable $erro) {
            CredencialSubmissao::whereKey($credencial->id)->where('redefinicao_token_hash', $tokenHash)->update([
                'temporaria_hash' => null, 'temporaria_expira_em' => null,
                'redefinicao_token_hash' => null, 'redefinicao_expira_em' => null,
            ]);
            Log::warning('Falha ao enviar recuperação de senha de submissão.', ['submissao' => $submissao->id, 'erro' => $erro->getMessage()]);
            return back()->withErrors(['email_recuperacao' => 'Não foi possível enviar a nova senha agora. Tente novamente mais tarde.'])->withInput($request->only('email_recuperacao', 'cadastrar'));
        }

        $request->session()->forget($this->chaveSessao($submissao));

        return back()->with('recuperacao', 'Se o e-mail estiver cadastrado, uma senha temporária será enviada.');
    }

    public function sair(Request $request, Submissao $submissao): RedirectResponse
    {
        $request->session()->forget($this->chaveSessao($submissao));

        return redirect()->route('submissoes.publicas.formulario', $submissao);
    }

    /** @return array{0: array<string, mixed>, 1: list<array{nome:string,email:string,afiliacao:string}>} */
    private function validarTrabalho(
        Request $request,
        Submissao $submissao,
        ?string $emailPrimeiroAutor = null,
        ?InscricaoSubmissaoTrabalho $trabalho = null,
    ): array
    {
        $nomeCompleto = function (string $atributo, mixed $valor, \Closure $falhar): void {
            if (! $this->nomeCompleto((string) $valor)) $falhar('Informe nome e sobrenome.');
        };
        $regras = [
            'titulo_trabalho' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'primeiro_autor' => ['required', 'string', 'max:255', $nomeCompleto],
            'primeiro_autor_afiliacao' => ['required', 'string', 'max:1000'],
            'outros_autores' => ['nullable', 'array', 'max:'.max(0, (int) $submissao->qtde_autores - 1)],
            'outros_autores.*.nome' => ['required', 'string', 'max:255', $nomeCompleto],
            'outros_autores.*.email' => ['required', 'email', 'max:150', 'distinct:ignore_case'],
            'outros_autores.*.afiliacao' => ['required', 'string', 'max:1000'],
            'categoria_trabalho' => $submissao->mostrar_categoria_trabalho ? ['required', 'in:'.implode(',', array_keys(InscricaoSubmissaoTrabalho::CATEGORIAS_TRABALHO))] : ['exclude'],
            'palavras_chave' => $submissao->mostrar_palavras_chave ? ['required', 'string', 'max:20000'] : ['exclude'],
            'conteudo' => ['nullable', 'string', 'max:1000000'],
            'tem_apoio_financeiro' => ['required', 'boolean'],
            'apoiador' => ['nullable', 'required_if:tem_apoio_financeiro,1', 'string', 'max:1000'],
            'apresentacao' => ['required', 'in:online,presencial'],
            'aprovacao_comite_etica' => ['required', 'boolean'],
            'protocolo_comite_etica' => ['nullable', 'required_if:aprovacao_comite_etica,1', 'string', 'max:500'],
        ];

        $camposOcultos = [];
        if (! $submissao->mostrar_categoria_trabalho) {
            $camposOcultos['categoria_trabalho'] = $trabalho?->categoria_trabalho;
        }
        if (! $submissao->mostrar_palavras_chave) {
            $camposOcultos['palavras_chave'] = $trabalho?->palavras_chave;
        }
        if (! $submissao->mostrar_apresentacao) {
            $camposOcultos['apresentacao'] = $trabalho?->apresentacao ?? 'presencial';
        }
        if (! $submissao->mostrar_apoio_financeiro) {
            $camposOcultos['tem_apoio_financeiro'] = $trabalho?->tem_apoio_financeiro ?? false;
            $camposOcultos['apoiador'] = $trabalho?->apoiador;
        }
        if (! $submissao->mostrar_aprovacao_comite_etica) {
            $camposOcultos['aprovacao_comite_etica'] = $trabalho?->aprovacao_comite_etica ?? false;
            $camposOcultos['protocolo_comite_etica'] = $trabalho?->protocolo_comite_etica;
        }
        foreach ($camposOcultos as $campo => $valor) {
            $regras[$campo] = ['exclude'];
        }

        $dados = $request->validate($regras, [
            'categoria_trabalho.required' => 'Selecione a categoria do trabalho.',
            'categoria_trabalho.in' => 'Selecione uma categoria do trabalho válida.',
            'palavras_chave.required' => 'Informe as palavras-chave do resumo, separadas por vírgulas.',
            'titulo_trabalho.required' => 'Informe o título do trabalho.',
            'titulo_trabalho.max' => 'O título do trabalho deve ter no máximo 120 caracteres.',
            'primeiro_autor.required' => 'Informe o primeiro autor.',
            'primeiro_autor_afiliacao.required' => 'Informe a filiação do primeiro autor.',
            'outros_autores.max' => 'A quantidade máxima de autores permitida para esta submissão foi ultrapassada.',
            'outros_autores.*.nome.required' => 'Informe o nome completo de cada autor.',
            'outros_autores.*.email.required' => 'Informe o e-mail de cada autor.',
            'outros_autores.*.email.email' => 'Informe um e-mail válido para cada autor.',
            'outros_autores.*.email.distinct' => 'Não repita o e-mail de um autor.',
            'outros_autores.*.afiliacao.required' => 'Informe a filiação de cada autor.',
            'tem_apoio_financeiro.required' => 'Informe se o trabalho possui apoio financeiro.',
            'apoiador.required_if' => 'Informe o apoiador financeiro.',
            'apresentacao.required' => 'Selecione a forma de apresentação.',
            'aprovacao_comite_etica.required' => 'Informe se o trabalho possui aprovação do Comitê de Ética.',
            'protocolo_comite_etica.required_if' => 'Informe o protocolo do Comitê de Ética.',
        ]);
        $dados = array_replace($dados, $camposOcultos);
        if ($submissao->mostrar_palavras_chave) {
            $palavras = array_values(array_filter(array_map('trim', explode(',', $dados['palavras_chave'])), fn (string $palavra): bool => $palavra !== ''));
            if (count($palavras) < $submissao->min_palavras_chave || count($palavras) > $submissao->max_palavras_chave) {
                throw ValidationException::withMessages(['palavras_chave' => "Informe de {$submissao->min_palavras_chave} a {$submissao->max_palavras_chave} palavras-chave ou expressões, separadas por vírgulas."]);
            }
            $dados['palavras_chave'] = implode(', ', $palavras);
        }
        $emailPrimeiroAutor = mb_strtolower(trim($emailPrimeiroAutor ?: $dados['email']));
        $dados['email'] = $emailPrimeiroAutor;
        $autores = [[
            'nome' => $this->normalizarNome($dados['primeiro_autor']),
            'email' => $emailPrimeiroAutor,
            'afiliacao' => trim($dados['primeiro_autor_afiliacao']),
        ]];
        foreach ((array) ($dados['outros_autores'] ?? []) as $autor) {
            $autores[] = [
                'nome' => $this->normalizarNome((string) $autor['nome']),
                'email' => mb_strtolower(trim((string) $autor['email'])),
                'afiliacao' => trim((string) $autor['afiliacao']),
            ];
        }
        if (collect($autores)->pluck('email')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['outros_autores' => 'Cada autor deve possuir um e-mail diferente.']);
        }
        $dados['titulo_trabalho'] = trim($dados['titulo_trabalho']);
        $dados['tem_apoio_financeiro'] = (bool) $dados['tem_apoio_financeiro'];
        $dados['apoiador'] = $dados['tem_apoio_financeiro'] ? trim((string) $dados['apoiador']) : null;
        $dados['aprovacao_comite_etica'] = (bool) $dados['aprovacao_comite_etica'];
        $dados['protocolo_comite_etica'] = $dados['aprovacao_comite_etica']
            ? trim((string) $dados['protocolo_comite_etica'])
            : null;
        $dados['conteudo'] = $this->limparHtml((string) ($dados['conteudo'] ?? '')) ?: null;
        $limiteResumo = max(1, (int) $submissao->qtde_resumo);
        if ($this->contarCaracteresResumo((string) $dados['conteudo']) > $limiteResumo) {
            throw ValidationException::withMessages([
                'conteudo' => "O resumo deve ter no máximo {$limiteResumo} caracteres.",
            ]);
        }
        return [$dados, $autores];
    }

    private function contarCaracteresResumo(string $html): int
    {
        $html = preg_replace(
            '/<(p|div|li|h[1-6]|blockquote)\b[^>]*>\s*<br\s*\/?>\s*<\/\1>/i',
            "\n",
            $html,
        );
        $texto = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $texto = preg_replace('/<\/(p|div|li|h[1-6]|blockquote|tr)>/i', "\n", (string) $texto);
        $texto = html_entity_decode(strip_tags((string) $texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/\r?\n$/', '', $texto, 1);

        return mb_strlen((string) $texto);
    }

    /** @param list<array{nome:string,email:string,afiliacao:string}> $autores */
    private function gravarAutores(InscricaoSubmissaoTrabalho $trabalho, array $autores): void
    {
        foreach ($autores as $indice => $autor) {
            $numero = $indice + 1;
            CredencialSubmissao::firstOrCreate(['email' => $autor['email']]);
            $trabalho->autores()->create([
                'nome' => $autor['nome'],
                'email' => $autor['email'],
                'afiliacao' => $autor['afiliacao'],
                'principal' => $indice === 0,
                'ordem' => $numero,
                'numero' => $numero,
            ]);
        }
    }

    private function exigirAberta(Submissao $submissao): void
    {
        $submissao->loadMissing('evento');
        if (! $submissao->aberta()) {
            throw ValidationException::withMessages(['titulo_trabalho' => 'O período de submissão não está aberto.']);
        }
    }

    private function exigirEditavel(Submissao $submissao, InscricaoSubmissaoTrabalho $trabalho): void
    {
        $this->exigirAberta($submissao);
        if ($trabalho->status !== 'rascunho') {
            throw ValidationException::withMessages(['titulo_trabalho' => 'Este trabalho não pode mais ser alterado.']);
        }
    }

    private function trabalhosAcessiveis(Submissao $submissao, string $email): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $submissao->trabalhos()->where(function ($query) use ($email): void {
            $query->whereHas('inscricao', fn ($q) => $q->where('email', $email))
                ->orWhereHas('autores', fn ($q) => $q->where('email', $email));
        });
    }

    private function exigirAcesso(Request $request, Submissao $submissao, InscricaoSubmissaoTrabalho $trabalho, bool $editar = false): void
    {
        abort_unless($trabalho->inscricao->submissao_id === $submissao->id, 404);
        $acesso = $this->acessoValido($request, $submissao);
        abort_unless($acesso, 403);
        abort_unless($editar
            ? $trabalho->inscricao->email === $acesso['email']
            : $this->trabalhosAcessiveis($submissao, $acesso['email'])->whereKey($trabalho->id)->exists(), 403);
    }

    private function iniciarAcesso(Request $request, Submissao $submissao, CredencialSubmissao $credencial, ?int $trabalhoAtual): void
    {
        $agora = now()->timestamp;
        $request->session()->regenerate();
        $request->session()->put($this->chaveSessao($submissao), [
            'inicio' => $agora, 'ultimo_acesso' => $agora,
            'credencial_id' => $credencial->id, 'email' => $credencial->email,
            'versao' => $credencial->credencial_versao, 'trabalho_atual' => $trabalhoAtual,
        ]);
    }

    private function acessoValido(Request $request, Submissao $submissao): ?array
    {
        $chave = $this->chaveSessao($submissao);
        $acesso = $request->session()->get($chave);
        $agora = now()->timestamp;
        if (! is_array($acesso) || ! isset($acesso['inicio'], $acesso['ultimo_acesso'], $acesso['credencial_id'], $acesso['versao'], $acesso['email'])
            || $agora - (int) $acesso['inicio'] > self::SESSAO_TOTAL_SEGUNDOS
            || $agora - (int) $acesso['ultimo_acesso'] > self::SESSAO_OCIOSA_SEGUNDOS
            || ! $submissao->ativo || ! $submissao->evento?->ativo
            || ! CredencialSubmissao::whereKey($acesso['credencial_id'])->where('email', $acesso['email'])->where('credencial_versao', $acesso['versao'])->exists()) {
            $request->session()->forget($chave);
            return null;
        }
        $acesso['ultimo_acesso'] = $agora;
        $request->session()->put($chave, $acesso);
        return $acesso;
    }

    private function limitarLogin(Request $request, Submissao $submissao, string $email): void
    {
        foreach ($this->chavesLogin($request, $submissao, $email) as $chave) {
            if (RateLimiter::tooManyAttempts($chave, 8)) {
                throw ValidationException::withMessages(['email_login' => 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.']);
            }
            RateLimiter::hit($chave, 15 * 60);
        }
    }

    private function limparLimitesLogin(Request $request, Submissao $submissao, string $email): void
    {
        foreach ($this->chavesLogin($request, $submissao, $email) as $chave) RateLimiter::clear($chave);
    }

    /** @return list<string> */
    private function chavesLogin(Request $request, Submissao $submissao, string $email): array
    {
        $base = 'submissao-login:';
        return [$base.'email:'.sha1($email), $base.'sessao:'.sha1($request->session()->getId()), $base.'ip:'.sha1((string) $request->ip())];
    }

    private function limitarRecuperacao(Request $request, Submissao $submissao, string $email): void
    {
        $base = 'submissao-recuperacao:';
        $sessao = sha1($request->session()->getId());
        $ip = sha1((string) $request->ip());
        $regras = [
            [$base.'email:'.sha1($email).':intervalo', 1, 5 * 60],
            [$base.'email:'.sha1($email).':dia', 3, 86400],
            [$base.'sessao:'.$sessao.':hora', 5, 3600],
            [$base.'sessao:'.$sessao.':dia', 10, 86400],
            [$base.'ip:'.$ip.':hora', 10, 3600],
            [$base.'ip:'.$ip.':dia', 25, 86400],
            [$base.'global:hora', 100, 3600],
        ];
        foreach ($regras as [$chave, $maximo]) {
            if (RateLimiter::tooManyAttempts($chave, $maximo)) {
                $espera = max(1, RateLimiter::availableIn($chave));
                throw ValidationException::withMessages(['email_recuperacao' => "Limite de solicitações atingido. Tente novamente em {$espera} segundos."]);
            }
        }
        foreach ($regras as [$chave, , $duracao]) RateLimiter::hit($chave, $duracao);
    }

    private function limitarCriacao(Request $request, Submissao $submissao, string $email): void
    {
        $base = 'submissao-criacao:'.$submissao->id.':';
        $regras = [
            [$base.'email:'.sha1($email).':hora', 5, 3600],
            [$base.'sessao:'.sha1($request->session()->getId()).':hora', 10, 3600],
            [$base.'ip:'.sha1((string) $request->ip()).':hora', 20, 3600],
            [$base.'global:hora', 200, 3600],
        ];

        foreach ($regras as [$chave, $maximo]) {
            if (RateLimiter::tooManyAttempts($chave, $maximo)) {
                throw ValidationException::withMessages([
                    'email' => 'Limite de novos trabalhos atingido. Aguarde antes de tentar novamente.',
                ]);
            }
        }
        foreach ($regras as [$chave, , $duracao]) RateLimiter::hit($chave, $duracao);
    }

    private function chaveSessao(Submissao $submissao): string
    {
        return 'submissoes_publicas.'.$submissao->id;
    }

    private function nomeCompleto(string $nome): bool
    {
        return count(preg_split('/\s+/u', trim($nome), -1, PREG_SPLIT_NO_EMPTY) ?: []) >= 2;
    }

    private function normalizarNome(string $nome): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $nome));
    }

    private function limparHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><blockquote><table><thead><tbody><tr><th><td>');

        return trim((string) preg_replace_callback('/<([a-z][a-z0-9]*)\b([^>]*)>/i', function (array $partes): string {
            preg_match('/\bclass=["\'][^"\']*\b(ql-align-(?:center|right|justify))\b[^"\']*["\']/i', $partes[2], $classe);

            return '<'.strtolower($partes[1]).(isset($classe[1]) ? ' class="'.strtolower($classe[1]).'"' : '').'>';
        }, $html));
    }
}
