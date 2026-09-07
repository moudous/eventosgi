<?php

namespace App\Http\Controllers;

use App\Models\FaixaIpLiberada;
use App\Services\FaixaIpService;
use App\Services\GiPermissionService;
use App\Services\LimiteEnvioCodigoService;
use App\Services\PluginWordpressService;
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
            'limites' => [
                'enderecos_por_faixa' => LimiteEnvioCodigoService::MAX_ENDERECOS_POR_FAIXA,
                'envios_por_faixa' => LimiteEnvioCodigoService::MAX_POR_FAIXA,
                'enderecos_por_sessao' => LimiteEnvioCodigoService::MAX_ENDERECOS_POR_SESSAO,
                'por_email' => LimiteEnvioCodigoService::MAX_POR_EMAIL,
                'por_atividade' => LimiteEnvioCodigoService::MAX_POR_ATIVIDADE,
            ],
        ]);
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
