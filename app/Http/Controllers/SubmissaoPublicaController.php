<?php

namespace App\Http\Controllers;

use App\Models\InscricaoSubmissao;
use App\Models\InscricaoSubmissaoTrabalho;
use App\Models\Submissao;
use App\Services\GiEmailService;
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
            $inscricao = InscricaoSubmissao::query()->with('trabalhos.autores')
                ->where('submissao_id', $submissao->id)->find($acesso['inscricao_id']);

            if (! $inscricao || $inscricao->credencial_versao !== (int) $acesso['versao']) {
                $request->session()->forget($this->chaveSessao($submissao));
                $acesso = null;
                $inscricao = null;
            } else {
                $trabalhos = $inscricao->trabalhos->values();
                if ($acesso['trabalho_atual']) {
                    $trabalho = $trabalhos->firstWhere('id', (int) $acesso['trabalho_atual']);
                } elseif ($trabalhos->isNotEmpty()) {
                    $trabalho = $trabalhos->first();
                    $acesso['trabalho_atual'] = $trabalho->id;
                    $request->session()->put($this->chaveSessao($submissao), $acesso);
                }
            }
        }

        $primeiroCadastro = ! $acesso && $request->boolean('cadastro') && $submissao->aberta();
        $novo = $submissao->aberta() && ($primeiroCadastro || ((bool) $acesso && $request->boolean('novo')));

        return view('submissoes.publico', compact(
            'submissao', 'acesso', 'trabalhos', 'inscricao', 'trabalho', 'novo', 'primeiroCadastro'
        ));
    }

    public function criar(Request $request, Submissao $submissao): RedirectResponse
    {
        $this->exigirAberta($submissao);
        $acesso = $this->acessoValido($request, $submissao);
        $primeiroCadastro = ! $acesso;
        $inscricao = null;

        if (! $primeiroCadastro) {
            $inscricao = $submissao->inscricoes()->find($acesso['inscricao_id']);
            abort_unless($inscricao && $inscricao->credencial_versao === (int) $acesso['versao'], 403);
        }

        [$dados, $autores] = $this->validarTrabalho(
            $request,
            $submissao,
            $primeiroCadastro,
            $inscricao?->email,
        );

        if ($primeiroCadastro) {
            $email = $dados['email'];
            if ($submissao->inscricoes()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Este e-mail já possui cadastro. Entre com sua senha para adicionar outro trabalho.']);
            }
        } else {
            $email = $inscricao->email;
        }

        $this->limitarCriacao($request, $submissao, $email);

        [$inscricao, $trabalho] = DB::transaction(function () use ($dados, $autores, $submissao, $primeiroCadastro, $inscricao): array {
            if ($primeiroCadastro) {
                $inscricao = $submissao->inscricoes()->create([
                    'email' => $dados['email'],
                    'senha' => Hash::make($dados['senha']),
                ]);
            }
            $trabalho = $inscricao->trabalhos()->create([
                'titulo_trabalho' => $dados['titulo_trabalho'],
                'conteudo' => $dados['conteudo'],
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

        $this->iniciarAcesso($request, $submissao, $inscricao, $trabalho->id);

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

        $inscricao = $submissao->inscricoes()->withCount('trabalhos')->where('email', $email)->first();

        if (! $inscricao || ! Hash::check($dados['senha_login'], $inscricao->senha)) {
            throw ValidationException::withMessages(['email_login' => 'E-mail ou senha inválidos.']);
        }

        $this->limparLimitesLogin($request, $submissao, $email);
        $trabalhoAtual = $inscricao->trabalhos_count === 1 ? $inscricao->trabalhos()->value('id') : null;
        $this->iniciarAcesso($request, $submissao, $inscricao, $trabalhoAtual);

        return redirect()->route('submissoes.publicas.formulario', $submissao);
    }

    public function selecionar(Request $request, Submissao $submissao): RedirectResponse
    {
        $dados = $request->validate(['trabalho_id' => ['required', 'integer']]);
        $acesso = $this->acessoValido($request, $submissao);
        abort_unless($acesso, 403);
        $inscricao = $submissao->inscricoes()->find($acesso['inscricao_id']);
        abort_unless($inscricao && $inscricao->trabalhos()->whereKey($dados['trabalho_id'])->exists(), 403);
        $acesso['trabalho_atual'] = (int) $dados['trabalho_id'];
        $acesso['ultimo_acesso'] = now()->timestamp;
        $request->session()->put($this->chaveSessao($submissao), $acesso);

        return redirect()->route('submissoes.publicas.formulario', $submissao);
    }

    public function atualizar(Request $request, Submissao $submissao, InscricaoSubmissaoTrabalho $trabalho): RedirectResponse
    {
        $this->exigirAcesso($request, $submissao, $trabalho);
        $this->exigirEditavel($submissao, $trabalho);
        [$dados, $autores] = $this->validarTrabalho($request, $submissao, false, $trabalho->inscricao->email);

        DB::transaction(function () use ($dados, $autores, $trabalho): void {
            $trabalho->update([
                'titulo_trabalho' => $dados['titulo_trabalho'],
                'conteudo' => $dados['conteudo'],
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

            return '<div><sup>'.$numero.'</sup> '.nl2br(e($autor->afiliacao ?: 'Afiliação não informada')).'</div>';
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
        $this->exigirAcesso($request, $submissao, $trabalho);
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

    public function alterarSenha(Request $request, Submissao $submissao, InscricaoSubmissaoTrabalho $trabalho): RedirectResponse
    {
        $acesso = $this->acessoValido($request, $submissao);
        abort_unless($acesso, 403);
        $inscricao = $trabalho->inscricao;
        abort_unless($inscricao->submissao_id === $submissao->id
            && $inscricao->id === (int) $acesso['inscricao_id']
            && $inscricao->credencial_versao === (int) $acesso['versao'], 403);
        $dados = $request->validate([
            'senha_atual' => ['required', 'string'],
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['senha.confirmed' => 'A confirmação da nova senha não confere.']);

        if (! Hash::check($dados['senha_atual'], $inscricao->senha)) {
            throw ValidationException::withMessages(['senha_atual' => 'A senha atual está incorreta.']);
        }

        $inscricao->update(['senha' => Hash::make($dados['senha']), 'credencial_versao' => $inscricao->credencial_versao + 1]);
        $inscricao->refresh();
        $acesso['versao'] = $inscricao->credencial_versao;
        $acesso['trabalho_atual'] = $trabalho->id;
        $acesso['ultimo_acesso'] = now()->timestamp;
        $request->session()->regenerate();
        $request->session()->put($this->chaveSessao($submissao), $acesso);

        return redirect()->route('submissoes.publicas.formulario', $submissao)->with('status', 'Senha alterada com sucesso.');
    }

    public function esqueciSenha(Request $request, Submissao $submissao, GiEmailService $emailService): RedirectResponse
    {
        abort_unless($submissao->ativo && (bool) $submissao->evento?->ativo, 404);
        $dados = $request->validate([
            'email_recuperacao' => ['required', 'email', 'max:150'],
            'website' => ['nullable', 'max:0'],
        ], ['email_recuperacao.required' => 'Informe o e-mail.', 'website.max' => 'Não foi possível processar a solicitação.']);
        $email = mb_strtolower(trim($dados['email_recuperacao']));
        $this->limitarRecuperacao($request, $submissao, $email);
        $inscricao = $submissao->inscricoes()->with('trabalhos.autores')->where('email', $email)->first();

        // A mesma resposta evita revelar a terceiros se o endereço possui trabalhos.
        if (! $inscricao) {
            return back()->with('recuperacao', 'Se o e-mail estiver cadastrado, uma nova senha será enviada.');
        }

        $senhaTemporaria = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4)).random_int(1000, 9999);
        $nome = $inscricao->trabalhos->first()?->autores?->firstWhere('principal', true)?->nome;
        $url = route('submissoes.publicas.formulario', $submissao);
        $titulos = $inscricao->trabalhos->pluck('titulo_trabalho')->map(fn ($titulo) => '<li>'.e($titulo).'</li>')->implode('');
        $conteudo = '<p>Olá'.($nome ? ', '.e($nome) : '').'.</p>'
            .'<p>Uma nova senha foi solicitada para os seus trabalhos em <strong>'.e($submissao->titulo).'</strong>.</p>'
            .'<p>Sua senha temporária é: <strong style="font-size:18px">'.e($senhaTemporaria).'</strong></p>'
            .'<p>Trabalhos vinculados:</p><ul>'.$titulos.'</ul>'
            .'<p><a href="'.e($url).'">Acessar a área de submissão</a></p>'
            .'<p>Altere essa senha depois de entrar. Se você não fez este pedido, avise a comissão responsável.</p>';

        try {
            $emailService->enviar($email, $nome, 'Nova senha — '.$submissao->titulo, $conteudo, 'submissao-senha-'.$submissao->id.'-'.bin2hex(random_bytes(8)));
        } catch (Throwable $erro) {
            Log::warning('Falha ao enviar recuperação de senha de submissão.', ['submissao' => $submissao->id, 'erro' => $erro->getMessage()]);

            return back()->withErrors(['email_recuperacao' => 'Não foi possível enviar a nova senha agora. Tente novamente mais tarde.']);
        }

        $inscricao->update([
            'senha' => Hash::make($senhaTemporaria),
            'credencial_versao' => $inscricao->credencial_versao + 1,
        ]);

        $request->session()->forget($this->chaveSessao($submissao));

        return back()->with('recuperacao', 'Se o e-mail estiver cadastrado, uma nova senha será enviada.');
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
        bool $novo,
        ?string $emailPrimeiroAutor = null,
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
            'conteudo' => ['nullable', 'string', 'max:1000000'],
            'tem_apoio_financeiro' => ['required', 'boolean'],
            'apoiador' => ['nullable', 'required_if:tem_apoio_financeiro,1', 'string', 'max:1000'],
            'apresentacao' => ['required', 'in:online,presencial'],
            'aprovacao_comite_etica' => ['required', 'boolean'],
            'protocolo_comite_etica' => ['nullable', 'required_if:aprovacao_comite_etica,1', 'string', 'max:500'],
        ];
        if ($novo) {
            $regras['senha'] = ['required', 'confirmed', Password::min(8)->letters()->numbers()];
            $regras['website_trabalho'] = ['nullable', 'max:0'];
        }

        $dados = $request->validate($regras, [
            'titulo_trabalho.required' => 'Informe o título do trabalho.',
            'titulo_trabalho.max' => 'O título do trabalho deve ter no máximo 120 caracteres.',
            'primeiro_autor.required' => 'Informe o primeiro autor.',
            'primeiro_autor_afiliacao.required' => 'Informe a afiliação do primeiro autor.',
            'outros_autores.max' => 'A quantidade máxima de autores permitida para esta submissão foi ultrapassada.',
            'outros_autores.*.nome.required' => 'Informe o nome completo de cada autor.',
            'outros_autores.*.email.required' => 'Informe o e-mail de cada autor.',
            'outros_autores.*.email.email' => 'Informe um e-mail válido para cada autor.',
            'outros_autores.*.email.distinct' => 'Não repita o e-mail de um autor.',
            'outros_autores.*.afiliacao.required' => 'Informe a afiliação de cada autor.',
            'tem_apoio_financeiro.required' => 'Informe se o trabalho possui apoio financeiro.',
            'apoiador.required_if' => 'Informe o apoiador financeiro.',
            'apresentacao.required' => 'Selecione a forma de apresentação.',
            'aprovacao_comite_etica.required' => 'Informe se o trabalho possui aprovação do Comitê de Ética.',
            'protocolo_comite_etica.required_if' => 'Informe o protocolo do Comitê de Ética.',
            'senha.confirmed' => 'A confirmação da senha não confere.',
        ]);
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

    private function exigirAcesso(Request $request, Submissao $submissao, InscricaoSubmissaoTrabalho $trabalho): void
    {
        $inscricao = $trabalho->inscricao;
        abort_unless($inscricao->submissao_id === $submissao->id, 404);
        $acesso = $this->acessoValido($request, $submissao);
        abort_unless($acesso
            && (int) $acesso['inscricao_id'] === $inscricao->id
            && (int) $acesso['versao'] === $inscricao->credencial_versao, 403);
    }

    private function iniciarAcesso(Request $request, Submissao $submissao, InscricaoSubmissao $inscricao, ?int $trabalhoAtual): void
    {
        $agora = now()->timestamp;
        $request->session()->regenerate();
        $request->session()->put($this->chaveSessao($submissao), [
            'inicio' => $agora,
            'ultimo_acesso' => $agora,
            'inscricao_id' => $inscricao->id,
            'versao' => $inscricao->credencial_versao,
            'trabalho_atual' => $trabalhoAtual,
        ]);
    }

    private function acessoValido(Request $request, Submissao $submissao): ?array
    {
        $chave = $this->chaveSessao($submissao);
        $acesso = $request->session()->get($chave);
        $agora = now()->timestamp;
        if (! is_array($acesso) || ! isset($acesso['inicio'], $acesso['ultimo_acesso'], $acesso['inscricao_id'], $acesso['versao'])
            || $agora - (int) $acesso['inicio'] > self::SESSAO_TOTAL_SEGUNDOS
            || $agora - (int) $acesso['ultimo_acesso'] > self::SESSAO_OCIOSA_SEGUNDOS) {
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
        $base = 'submissao-login:'.$submissao->id.':';
        return [$base.'email:'.sha1($email), $base.'sessao:'.sha1($request->session()->getId()), $base.'ip:'.sha1((string) $request->ip())];
    }

    private function limitarRecuperacao(Request $request, Submissao $submissao, string $email): void
    {
        $base = 'submissao-recuperacao:'.$submissao->id.':';
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
