<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\Categoria;
use App\Models\Convidado;
use App\Models\Evento;

/**
 * Monta a pagina publica de um evento a partir do HTML de um template importado.
 *
 * O template vem de um ZIP que alguem envia pela tela, entao ele NAO e PHP nem Blade:
 * qualquer um dos dois transformaria a importacao de um arquivo em execucao de codigo
 * arbitrario no servidor. O que existe aqui e uma linguagem pequena e fechada, que so
 * sabe ler o contexto que esta classe monta:
 *
 *   {{ evento.nome }}                          valor, sempre escapado
 *   {{ asset('css/estilo.css') }}              URL de um arquivo do proprio template
 *   {% for atividade in atividades %} … {% endfor %}
 *   {% if atividade.categoria %} … {% else %} … {% endif %}
 *
 * Nao ha chamada de metodo, nao ha expressao: o que nao estiver no contexto nao existe.
 *
 * O contexto e propositalmente feito de arrays de texto ja formatado -- e ele que a
 * pagina publica e, futuramente, o shortcode do WordPress vao consumir igual.
 */
class PaginaEventoRenderer
{
    /** Nomes reservados: uma variavel do evento nao pode encobrir os dados do sistema. */
    public const RESERVADOS = ['evento', 'atividades', 'categorias', 'convidados', 'submissoes', 'eventos', 'dias', 'menu', 'loop', 'programacao_dias', 'hands_on', 'minicursos', 'total_convidados', 'total_programacao'];

    /** Teto de itens por coleção, para um template não derrubar a página pedindo tudo. */
    private const MAX_ITENS = 500;

    public function __construct(private readonly TemplatePaginaService $templates) {}

    /**
     * HTML final da pagina do evento.
     */
    public function renderizar(Evento $evento): string
    {
        $template = $evento->templatePagina;

        if ($template === null) {
            return '';
        }

        return $this->processar(
            $this->templates->html($template),
            $this->contexto($evento),
            fn (string $arquivo): string => $this->templates->urlDoAsset($template, $arquivo),
        );
    }

    /** Prévia de um modelo em construção, usando a mesma linguagem da página publicada. */
    public function renderizarModelo(string $modelo, Evento $evento, callable $asset): string
    {
        return $this->processar($modelo, $this->contexto($evento), $asset);
    }

    /**
     * Dados oferecidos ao template.
     *
     * Tudo ja vem formatado como texto: a linguagem do template nao chama metodo nenhum,
     * entao datas e rotulos precisam chegar prontos.
     *
     * @return array<string, mixed>
     */
    public function contexto(Evento $evento): array
    {
        $template = $evento->templatePagina;
        $atividades = Atividade::query()
            ->with(['categoria', 'convidados', 'sessoes' => fn ($query) => $query->where('ativo', true)->withCount('inscricoes')])
            ->where('evento_id', $evento->id)
            ->where('ativo', true)
            ->orderByRaw('data_inicio is null, data_inicio')->orderBy('nome')
            ->limit(self::MAX_ITENS)->get();

        $dados = [
            'evento' => [
                'id' => $evento->id,
                'nome' => (string) $evento->nome,
                'ativo' => (bool) $evento->ativo,
                'criado_em' => $evento->created_at?->format('d/m/Y') ?? '',
            ],
            'atividades' => $atividades->map(fn (Atividade $atividade) => [
                'id' => $atividade->id,
                'nome' => (string) $atividade->nome,
                'subtitulo' => (string) ($atividade->formulario['subtitulo'] ?? ''),
                'local' => (string) ($atividade->local ?: ($atividade->formulario['local'] ?? '')),
                'local_exibicao' => trim((string) ($atividade->local ?: ($atividade->formulario['local'] ?? ''))) ?: 'Local a definir',
                'vagas' => $this->rotuloVagas($atividade),
                'hora_inicio' => $atividade->data_inicio?->format('H:i') ?? '',
                'hora_fim' => $atividade->data_fim?->format('H:i') ?? '',
                'dia' => $atividade->data_inicio?->format('d/m') ?? '',
                'hands_on' => \Illuminate\Support\Str::slug($atividade->categoria?->nome ?? '') === 'hands-on',
                'minicurso' => in_array(\Illuminate\Support\Str::slug($atividade->categoria?->nome ?? ''), ['minicurso', 'minicursos', 'mini-curso', 'mini-cursos', 'curso', 'cursos'], true),
                'inscricao_geral' => \Illuminate\Support\Str::slug($atividade->categoria?->nome ?? '') === 'inscricao-no-evento',
                'convidados' => $atividade->convidados->map(fn ($convidado) => $this->dadosConvidado($convidado))->all(),
                ...$this->dadosInscricao($atividade),
                'formato' => $atividade->formato,
                'modalidade' => match ($atividade->modalidade) { 'ead' => 'ONLINE', 'presencial' => 'PRESENCIAL', default => '' },
                'mostrar_link_transmissao' => $atividade->modalidade === 'ead' && (bool) $atividade->link_transmissao,
                'tipo_link_transmissao' => (string) ($atividade->tipo_link_transmissao ?? ''),
                'iframe_transmissao' => $atividade->tipo_link_transmissao === 'iframe',
                'url_transmissao' => $atividade->tipo_link_transmissao === 'iframe'
                    ? route('atividades.transmissao', $atividade)
                    : (string) ($atividade->link_transmissao ?? ''),
                'miniatura_transmissao' => $atividade->miniaturaTransmissao() ?? '',
                'data_inicio' => $atividade->data_inicio?->format('d/m/Y H:i') ?? '',
                'data_fim' => $atividade->data_fim?->format('d/m/Y H:i') ?? '',
                'data_inicio_iso' => $atividade->data_inicio?->toIso8601String() ?? '',
                'data_fim_iso' => $atividade->data_fim?->toIso8601String() ?? '',
                'categoria' => (string) ($atividade->categoria?->nome ?? ''),
                'categoria_id' => (int) ($atividade->categoria_id ?? 0),
                'sessoes' => $atividade->sessoes->map(fn ($sessao) => [
                    'id' => $sessao->id,
                    'nome' => $sessao->nome,
                    'data_inicio' => $sessao->data_inicio?->format('d/m/Y H:i') ?? '',
                    'data_fim' => $sessao->data_fim?->format('d/m/Y H:i') ?? '',
                    'limite_vagas' => $sessao->limite_vagas,
                    'vagas_restantes' => $sessao->vagasRestantes(),
                ])->all(),
                // Página pública de inscrição da atividade, para o template linkar direto.
                'url_inscricao' => $atividade->urlPublica(),
                // Pronto para o shortcode do WordPress apontar o formulario da atividade.
                'shortcode' => '[eventosgi_formulario id="'.$atividade->id.'"]',
            ])->all(),
            'categorias' => Categoria::query()->where('ativo', true)->orderBy('nome')
                ->limit(self::MAX_ITENS)->get()
                ->map(fn (Categoria $categoria) => ['id' => $categoria->id, 'nome' => (string) $categoria->nome])->all(),
            'convidados' => Convidado::query()->whereHas('eventos', fn ($query) => $query->where('eventos.id', $evento->id))
                ->orderBy('nome')->limit(self::MAX_ITENS)->get()
                ->map(fn (Convidado $convidado) => $this->dadosConvidado($convidado))->all(),
            'submissoes' => $evento->submissoes()->where('ativo', true)->orderBy('data_inicio')->orderBy('id')
                ->limit(self::MAX_ITENS)->get()
                ->map(fn ($submissao) => [
                    'id' => $submissao->id,
                    'titulo' => (string) $submissao->titulo,
                    'data_inicio' => $submissao->data_inicio?->format('d/m/Y H:i') ?? '',
                    'data_fim' => $submissao->data_fim?->format('d/m/Y H:i') ?? '',
                    'aberta' => $submissao->aberta(),
                    'url' => route('submissoes.publicas.formulario', $submissao).'?pagina_evento='.urlencode(route('eventos.pagina.visualizar', $evento)),
                ])->all(),
            'eventos' => Evento::query()->where('ativo', true)->orderBy('nome')
                ->limit(self::MAX_ITENS)->get()
                ->map(fn (Evento $outro) => ['id' => $outro->id, 'nome' => (string) $outro->nome])->all(),
        ];

        // Recortes prontos do mesmo conjunto de atividades: a linguagem do template nao
        // agrupa nem filtra, entao quem monta o contexto entrega o agrupamento feito.
        $dados['dias'] = $this->porDia($dados['atividades']);
        $dados['menu'] = $this->porCategoria($dados['atividades']);
        $programacao = array_values(array_filter($dados['atividades'], function ($item) use ($evento) {
            if ($item['inscricao_geral'] || ! $item['data_inicio_iso']) return false;
            $data = substr($item['data_inicio_iso'], 0, 10);
            $inicio = $evento->pagina_variaveis['agenda_inicio'] ?? '';
            $fim = $evento->pagina_variaveis['agenda_fim'] ?? '';
            return (! $inicio || $data >= $inicio) && (! $fim || $data <= $fim);
        }));
        $dados['programacao_dias'] = $this->porDia($programacao);
        $dados['hands_on'] = array_values(array_filter($programacao, fn ($item) => $item['hands_on']));
        // Minicursos possuem uma vitrine própria e continuam visíveis mesmo quando a
        // data estiver fora do recorte opcional usado apenas pela grade da programação.
        $dados['minicursos'] = array_values(array_filter($dados['atividades'], fn ($item) => $item['minicurso']));
        $dados['total_convidados'] = count($dados['convidados']);
        $dados['total_programacao'] = count($programacao);

        // Os padrões do manifesto também entram no contexto: assim uma página recém
        // criada já renderiza corretamente antes de o usuário clicar em Salvar.
        foreach ((array) ($template->variaveis ?? []) as $variavel) {
            $nome = (string) ($variavel['nome'] ?? '');
            if ($nome !== '' && ! in_array($nome, self::RESERVADOS, true)) {
                $dados[$nome] = (string) ($variavel['padrao'] ?? '');
            }
        }

        // Variaveis do evento entram por ultimo, mas sem poder sobrescrever os reservados.
        foreach ((array) ($evento->pagina_variaveis ?? []) as $nome => $valor) {
            if (! in_array($nome, self::RESERVADOS, true)) $dados[$nome] = $valor;
        }

        return $dados;
    }

    private function dadosConvidado(Convidado $convidado): array
    {
        preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', (string) $convidado->curriculo, $itens);

        return [
            'id' => $convidado->id,
            'nome' => trim($convidado->nome.' '.(string) $convidado->sobrenome),
            'titulacao' => (string) $convidado->titulacao,
            'descricao' => (string) $convidado->descricao,
            'curriculo' => (string) $convidado->curriculo,
            'curriculo_texto' => trim(html_entity_decode(strip_tags(preg_replace('/<\/(p|li|h[1-6])>/i', ' ', (string) $convidado->curriculo)), ENT_QUOTES, 'UTF-8')),
            'curriculo_itens' => array_values(array_filter(array_map(
                fn (string $item) => trim(html_entity_decode(strip_tags($item), ENT_QUOTES, 'UTF-8')),
                $itens[1] ?? [],
            ))),
            'local' => (string) $convidado->local,
            'email' => (string) $convidado->email,
            'foto' => $convidado->foto_nome ? route('convidados.foto', $convidado->foto_nome) : null,
            'redes_sociais' => array_values((array) ($convidado->redes_sociais ?? [])),
        ];
    }

    private function dadosInscricao(Atividade $atividade): array
    {
        if (! $atividade->formulario) return ['pode_inscrever' => false, 'inscricao_rotulo' => 'Inscrições em breve', 'lista_reserva' => false];
        $estado = app(FormularioInscricaoService::class)->estado($atividade);
        return [
            'pode_inscrever' => $estado['aberto'],
            'lista_reserva' => $estado['lista_reserva'],
            'inscricao_rotulo' => $estado['aberto']
                ? ($estado['lista_reserva'] ? 'Inscrever além do limite' : 'Inscreva-se')
                : match ($estado['motivo']) { 'esgotado' => 'Vagas esgotadas', 'antes' => 'Inscrições em breve', default => 'Inscrições encerradas' },
        ];
    }

    private function rotuloVagas(Atividade $atividade): string
    {
        if ($atividade->comSessoes()) {
            $limites = $atividade->sessoes->pluck('limite_vagas');
            if ($limites->isEmpty() || $limites->contains(null)) return 'Vagas sem limite';
            $total = (int) $limites->sum();
        } elseif (! empty($atividade->formulario['limitar_inscricoes'])) {
            $total = (int) ($atividade->formulario['limite_inscricoes'] ?? 0);
        } else {
            return 'Vagas sem limite';
        }

        return $total.' '.($total === 1 ? 'vaga' : 'vagas');
    }

    /**
     * Atividades agrupadas por dia, na ordem do calendario. As sem data ficam de fora:
     * um calendario nao tem onde encaixa-las.
     *
     * @param  list<array<string, mixed>>  $atividades
     * @return list<array{data: string, data_iso: string, dia_semana: string, total: int, atividades: list<array<string, mixed>>}>
     */
    private function porDia(array $atividades): array
    {
        $semana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        $dias = [];

        foreach ($atividades as $atividade) {
            if ($atividade['data_inicio_iso'] === '') continue;

            $momento = new \DateTimeImmutable($atividade['data_inicio_iso']);
            $chave = $momento->format('Y-m-d');

            $dias[$chave] ??= [
                'data' => $momento->format('d/m/Y'),
                'data_iso' => $chave,
                'dia_semana' => $semana[(int) $momento->format('w')],
                'total' => 0,
                'atividades' => [],
            ];
            $dias[$chave]['atividades'][] = $atividade + ['hora' => $momento->format('H:i')];
            $dias[$chave]['total']++;
        }

        ksort($dias);

        return array_values($dias);
    }

    /**
     * Atividades agrupadas por categoria, para o menu lateral. A ultima entrada reune as
     * que nao tem categoria, quando houver alguma.
     *
     * @param  list<array<string, mixed>>  $atividades
     * @return list<array{id: int, nome: string, total: int, atividades: list<array<string, mixed>>}>
     */
    private function porCategoria(array $atividades): array
    {
        $grupos = [];

        foreach ($atividades as $atividade) {
            $id = (int) $atividade['categoria_id'];
            $grupos[$id] ??= [
                'id' => $id,
                'nome' => $atividade['categoria'] !== '' ? $atividade['categoria'] : 'Sem categoria',
                'total' => 0,
                'atividades' => [],
            ];
            $grupos[$id]['atividades'][] = $atividade;
            $grupos[$id]['total']++;
        }

        uasort($grupos, fn ($a, $b) => $a['id'] === 0 ? 1 : ($b['id'] === 0 ? -1 : strcasecmp($a['nome'], $b['nome'])));

        return array_values($grupos);
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  callable(string): string  $asset
     */
    private function processar(string $modelo, array $contexto, callable $asset): string
    {
        $nos = $this->analisar($this->separar($modelo), $indice, null);

        return $this->desenhar($nos, $contexto, $asset);
    }

    /**
     * Quebra o texto em pedacos: trecho literal, saida de valor e tag de bloco.
     *
     * @return list<array{tipo: string, valor: string}>
     */
    private function separar(string $modelo): array
    {
        $partes = preg_split('/(\{\{.*?\}\}|\{%.*?%\})/s', $modelo, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $pedacos = [];

        foreach ($partes as $parte) {
            if (str_starts_with($parte, '{{')) {
                $pedacos[] = ['tipo' => 'valor', 'valor' => trim(substr($parte, 2, -2))];
            } elseif (str_starts_with($parte, '{%')) {
                $pedacos[] = ['tipo' => 'tag', 'valor' => trim(substr($parte, 2, -2))];
            } else {
                $pedacos[] = ['tipo' => 'texto', 'valor' => $parte];
            }
        }

        return $pedacos;
    }

    /**
     * Monta a arvore de nos, respeitando o aninhamento de for e if.
     *
     * @param  list<array{tipo: string, valor: string}>  $pedacos
     * @return list<array<string, mixed>>
     */
    private function analisar(array $pedacos, ?int &$i, ?string $fecha): array
    {
        $i ??= 0;
        $nos = [];

        while ($i < count($pedacos)) {
            $pedaco = $pedacos[$i];

            if ($pedaco['tipo'] === 'tag') {
                $tag = $pedaco['valor'];

                // Fecha o bloco de quem chamou; quem consome o token e o chamador.
                if (in_array($tag, ['endfor', 'endif', 'else'], true)) {
                    if ($tag === $fecha || ($fecha === 'endif' && $tag === 'else')) return $nos;

                    // Fechamento solto: vira texto, para o template nao sumir silenciosamente.
                    $nos[] = ['tipo' => 'texto', 'valor' => '{% '.$tag.' %}'];
                    $i++;
                    continue;
                }

                if (preg_match('/^for\s+([a-z_][a-z0-9_]*)\s+in\s+([a-z_][a-z0-9_.]*)$/i', $tag, $achado)) {
                    $i++;
                    $corpo = $this->analisar($pedacos, $i, 'endfor');
                    $i++; // consome o endfor
                    $nos[] = ['tipo' => 'for', 'item' => $achado[1], 'lista' => $achado[2], 'corpo' => $corpo];
                    continue;
                }

                if (preg_match('/^if\s+([a-z_][a-z0-9_.]*)$/i', $tag, $achado)) {
                    $i++;
                    $entao = $this->analisar($pedacos, $i, 'endif');
                    $senao = [];
                    if (($pedacos[$i]['valor'] ?? '') === 'else') {
                        $i++;
                        $senao = $this->analisar($pedacos, $i, 'endif');
                    }
                    $i++; // consome o endif
                    $nos[] = ['tipo' => 'if', 'condicao' => $achado[1], 'entao' => $entao, 'senao' => $senao];
                    continue;
                }

                // Tag desconhecida sai como texto: erro de quem escreveu o template fica visível.
                $nos[] = ['tipo' => 'texto', 'valor' => '{% '.$tag.' %}'];
                $i++;
                continue;
            }

            $nos[] = $pedaco;
            $i++;
        }

        return $nos;
    }

    /**
     * @param  list<array<string, mixed>>  $nos
     * @param  array<string, mixed>  $contexto
     * @param  callable(string): string  $asset
     */
    private function desenhar(array $nos, array $contexto, callable $asset): string
    {
        $saida = '';

        foreach ($nos as $no) {
            $saida .= match ($no['tipo']) {
                'texto' => $no['valor'],
                'valor' => $this->valor($no['valor'], $contexto, $asset),
                'for' => $this->laco($no, $contexto, $asset),
                'if' => $this->verdadeiro($this->buscar($no['condicao'], $contexto))
                    ? $this->desenhar($no['entao'], $contexto, $asset)
                    : $this->desenhar($no['senao'], $contexto, $asset),
                default => '',
            };
        }

        return $saida;
    }

    /**
     * @param  array<string, mixed>  $no
     * @param  array<string, mixed>  $contexto
     * @param  callable(string): string  $asset
     */
    private function laco(array $no, array $contexto, callable $asset): string
    {
        $lista = $this->buscar($no['lista'], $contexto);

        if (! is_array($lista)) return '';

        $saida = '';
        $posicao = 0;

        foreach ($lista as $item) {
            $posicao++;
            $saida .= $this->desenhar($no['corpo'], $contexto + [
                $no['item'] => $item,
                'loop' => ['indice' => $posicao, 'primeiro' => $posicao === 1, 'ultimo' => $posicao === count($lista)],
            ], $asset);
        }

        return $saida;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  callable(string): string  $asset
     */
    private function valor(string $expressao, array $contexto, callable $asset): string
    {
        if (preg_match('/^asset\(\s*[\'"]([^\'"]+)[\'"]\s*\)$/', $expressao, $achado)) {
            return e($asset($achado[1]));
        }

        $valor = $this->buscar($expressao, $contexto);

        if (is_array($valor)) return '';
        if (is_bool($valor)) return $valor ? '1' : '';

        return e((string) ($valor ?? ''));
    }

    /**
     * Segue um caminho pontilhado dentro do contexto. Vale so leitura de chave.
     *
     * @param  array<string, mixed>  $contexto
     */
    private function buscar(string $caminho, array $contexto): mixed
    {
        $valor = $contexto;

        foreach (explode('.', $caminho) as $parte) {
            if (! is_array($valor) || ! array_key_exists($parte, $valor)) return null;
            $valor = $valor[$parte];
        }

        return $valor;
    }

    private function verdadeiro(mixed $valor): bool
    {
        return is_array($valor) ? $valor !== [] : ! in_array($valor, [null, false, '', 0, '0'], true);
    }
}
