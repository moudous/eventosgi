<?php

namespace App\Http\Controllers;

use App\Models\FaixaIpLiberada;
use App\Models\PixConfiguracao;
use App\Services\FaixaIpService;
use App\Services\GiPermissionService;
use App\Services\LimiteEnvioCodigoService;
use App\Services\PluginWordpressService;
use App\Services\SicoobPixService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tela de configuracao da aplicacao: redes liberadas dos limites de envio de codigo e
 * tudo o que diz respeito ao plugin do WordPress (download e instrucoes de uso).
 */
class ConfiguracaoController
{
    public function index(PluginWordpressService $plugin, GiPermissionService $permissoes): View
    {
        return view('configuracao.index', [
            // A tela abre com configuracao.visualizar; cada acao aparece so para quem a
            // tem. Sem isto, quem so pode ver encontraria botoes que respondem 403.
            'permissoes' => $permissoes,
            'faixas' => FaixaIpLiberada::query()->with('criador:id,nome')->orderBy('faixa')->get(),
            'versaoPlugin' => $plugin->versao(),
            'pix' => PixConfiguracao::atual(),
            'limites' => [
                'enderecos_por_faixa' => LimiteEnvioCodigoService::MAX_ENDERECOS_POR_FAIXA,
                'envios_por_faixa' => LimiteEnvioCodigoService::MAX_POR_FAIXA,
                'enderecos_por_sessao' => LimiteEnvioCodigoService::MAX_ENDERECOS_POR_SESSAO,
                'por_email' => LimiteEnvioCodigoService::MAX_POR_EMAIL,
                'por_atividade' => LimiteEnvioCodigoService::MAX_POR_ATIVIDADE,
            ],
        ]);
    }

    public function salvarPix(Request $request): RedirectResponse
    {
        $atual = PixConfiguracao::atual();
        $ambiente = (string) $request->input('ambiente');
        $urlSicoob = function (string $atributo, mixed $valor, \Closure $falhar): void {
            $host = mb_strtolower((string) parse_url((string) $valor, PHP_URL_HOST));
            if (! str_ends_with($host, '.sicoob.com.br') && ! str_ends_with($host, '.sisbr.com.br')) {
                $falhar('A :attribute deve apontar para um domínio oficial do Sicoob.');
            }
        };
        $dados = $request->validate([
            'ativo' => ['nullable', 'boolean'],
            'ambiente' => ['required', Rule::in(['sandbox', 'producao'])],
            'client_id' => [$atual ? 'nullable' : 'required', 'string', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'sandbox_token' => [Rule::requiredIf($ambiente === 'sandbox' && ! $atual?->sandbox_token), 'nullable', 'string', 'max:5000'],
            'chave_pix' => [$atual ? 'nullable' : 'required', 'string', 'max:500'],
            'certificado_pem' => [Rule::requiredIf($ambiente === 'producao' && ! $atual?->certificado_pem), 'nullable', 'string', 'max:30000'],
            'chave_privada_pem' => [Rule::requiredIf($ambiente === 'producao' && ! $atual?->chave_privada_pem), 'nullable', 'string', 'max:30000'],
            'senha_chave' => ['nullable', 'string', 'max:500'],
            'token_url' => [Rule::requiredIf($ambiente === 'producao'), 'nullable', 'url:http,https', 'max:500', $urlSicoob],
            'api_url' => [Rule::requiredIf($ambiente === 'producao'), 'nullable', 'url:http,https', 'max:500', $urlSicoob],
        ], [], [
            'client_id' => 'Client ID', 'client_secret' => 'Client Secret', 'sandbox_token' => 'Access Token do Sandbox', 'chave_pix' => 'chave PIX', 'certificado_pem' => 'certificado PEM',
            'chave_privada_pem' => 'chave privada PEM', 'senha_chave' => 'senha da chave',
            'token_url' => 'URL OAuth2', 'api_url' => 'URL da API',
        ]);

        foreach (['client_id', 'client_secret', 'sandbox_token', 'chave_pix', 'certificado_pem', 'chave_privada_pem', 'senha_chave'] as $segredo) {
            if ($atual && blank($dados[$segredo] ?? null)) unset($dados[$segredo]);
        }
        if ($ambiente === 'sandbox') {
            $dados['api_url'] = PixConfiguracao::SANDBOX_API_URL;
            $dados['token_url'] = null;
        }
        $dados['ativo'] = $request->boolean('ativo');
        ($atual ?? new PixConfiguracao)->fill($dados)->save();

        return back()->with('status', 'Configuração da API PIX salva com segurança.');
    }

    public function testarPix(SicoobPixService $pix): RedirectResponse
    {
        try {
            $pix->testarConexao();
            return back()->with('status', 'Conexão com a API PIX do Sicoob realizada com sucesso.');
        } catch (\Throwable $erro) {
            report($erro);
            return back()->withErrors(['pix' => $erro->getMessage()]);
        }
    }

    public function guardarFaixa(Request $request, FaixaIpService $servico): RedirectResponse
    {
        $dados = $request->validate([
            'faixa' => ['required', 'string', 'max:60'],
            'descricao' => ['required', 'string', 'max:150'],
        ], [], ['faixa' => 'faixa', 'descricao' => 'descrição']);

        // Normalizada antes de gravar: assim "200.130.15.37/24" e "200.130.15.0/24" nao
        // viram dois registros da mesma rede, e o unico do banco funciona de verdade.
        $faixa = $servico->normalizar($dados['faixa']);

        if ($faixa === null) {
            return back()->withInput()->withErrors([
                'faixa' => 'Informe um endereço (203.0.113.7) ou uma faixa em notação CIDR (200.130.15.0/24, 2001:db8::/32).',
            ]);
        }

        if (FaixaIpLiberada::query()->where('faixa', $faixa)->exists()) {
            return back()->withInput()->withErrors(['faixa' => "A faixa {$faixa} já está cadastrada."]);
        }

        FaixaIpLiberada::create([
            'faixa' => $faixa,
            'descricao' => $dados['descricao'],
            'ativo' => true,
            'criado_por' => $request->session()->get('gi_context.usuario.id'),
        ]);
        $servico->esquecerCache();

        return back()->with('status', "Faixa {$faixa} liberada.");
    }

    public function alternarFaixa(Request $request, FaixaIpLiberada $faixa, FaixaIpService $servico): RedirectResponse
    {
        $dados = $request->validate(['ativo' => ['required', Rule::in(['0', '1'])]]);

        $faixa->update(['ativo' => $dados['ativo'] === '1']);
        $servico->esquecerCache();

        return back()->with('status', "Faixa {$faixa->faixa} ".($faixa->ativo ? 'ativada' : 'desativada').'.');
    }

    public function removerFaixa(FaixaIpLiberada $faixa, FaixaIpService $servico): RedirectResponse
    {
        $identificacao = $faixa->faixa;
        $faixa->delete();
        $servico->esquecerCache();

        return back()->with('status', "Faixa {$identificacao} removida.");
    }

    public function baixarPlugin(PluginWordpressService $plugin)
    {
        return $plugin->download();
    }
}
