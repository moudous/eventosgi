<?php

// Execute: php database/seeds/dashboard-demonstracao.php
// Reexecutar completa este lote sem duplicar os registros já criados.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Atividade, Categoria, Evento, InscricaoAtividade, Participante, Usuario};
use App\Services\{DistribuicaoVagasService, DispositivoVisitanteService, FormularioInscricaoService};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

$resultado = DB::transaction(function () {
    $criador = Usuario::query()->orderBy('id')->value('id');
    if (! $criador) throw new RuntimeException('Nenhum usuário disponível para vincular as atividades.');
    foreach (['Palestra', 'Minicurso', 'Mesa redonda', 'Oficina', 'Hands on', 'Seminário', 'Simpósio', 'Workshop', 'Painel', 'Exposição'] as $nome) {
        $existe = Categoria::all()->contains(fn ($categoria) => Str::slug($categoria->nome) === Str::slug($nome));
        if (! $existe) Categoria::create(['nome'=>$nome, 'ativo'=>true]);
    }
    $categorias = Categoria::orderBy('nome')->get();
    $temas = ['Odontologia Integrada', 'Saúde e Tecnologia', 'Inovação Clínica', 'Pesquisa Científica', 'Cuidados Infantis', 'Empreendedorismo', 'Estética e Reabilitação', 'Saúde Coletiva', 'Práticas Digitais', 'Formação Profissional'];
    $eventos = [];
    foreach ($temas as $indice => $tema) {
        $eventos[] = Evento::firstOrCreate(['nome'=>sprintf('[Demo] %02d — %s', $indice + 1, $tema)], ['ativo'=>true]);
    }
    $nomes = ['Ana Silva', 'Bruno Santos', 'Carla Oliveira', 'Daniel Souza', 'Elisa Costa', 'Felipe Lima', 'Gabriela Rocha', 'Henrique Alves', 'Isabela Pereira', 'João Martins', 'Karen Ribeiro', 'Lucas Barbosa', 'Mariana Ferreira', 'Nicolas Gomes', 'Olivia Dias', 'Pedro Carvalho', 'Rafaela Mendes', 'Samuel Araujo', 'Tatiana Teixeira', 'Victor Fernandes', 'Amanda Melo', 'Bernardo Pinto', 'Camila Castro', 'Diego Nunes', 'Eduarda Freitas', 'Fernando Reis', 'Giovana Lopes', 'Hugo Moreira'];
    $participantes = [];
    foreach ($nomes as $indice => $nome) {
        // Mesma conexão para manter os cadastros no banco de certificados na transação do lote.
        $modelo = new Participante();
        $modelo->setConnection(config('database.default'));
        $modelo->setTable(config('database.connections.cert.database').'.participantes');
        $participantes[] = $modelo->newQuery()->firstOrCreate(['email'=>sprintf('seed.dashboard.demo.%02d@example.invalid', $indice + 1)], [
            'nome'=>$nome.' (Demo)', 'instituicao_ensino'=>'Instituição de demonstração', 'sexo'=>$indice % 2 ? 'M' : 'F', 'ativo'=>true,
        ]);
    }
    $agentes = [
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0',
        'Mozilla/5.0 (X11; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0',
        'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15',
    ];
    $combo = fn ($nome, $label, $opcoes) => ['nome'=>$nome, 'label'=>$label, 'tipo'=>'select', 'grid'=>4, 'obrigatorio'=>true, 'criterio_vagas'=>false,
        'opcoes'=>array_map(fn ($texto) => ['valor'=>Str::slug($texto), 'texto'=>$texto], $opcoes)];
    $campos = [
        $combo('perfil', 'Perfil do participante', ['Estudante', 'Profissional', 'Docente']),
        $combo('turno', 'Turno de preferência', ['Manhã', 'Tarde', 'Noite']),
        $combo('instituicao', 'Instituição', ['FCO', 'Outra instituição', 'Sem vínculo institucional']),
        $combo('interesse', 'Principal interesse', ['Atualização', 'Prática clínica', 'Pesquisa', 'Networking']),
    ];
    $atividadesIds = [];
    $criadas = 0;
    $agora = CarbonImmutable::now()->startOfMinute();
    $quantidadeAtividades = max(30, $categorias->count());
    for ($n = 0; $n < $quantidadeAtividades; $n++) {
        $evento = $eventos[$n % 10];
        $categoria = $categorias[$n % $categorias->count()];
        $inicio = match ($n % 4) { 0=>$agora->subHours(6), 1=>$agora->subDays(3), 2=>$agora->subDays(20), default=>$agora->subMonths(4) };
        $fim = $n % 2 ? $agora->addDays(3) : $agora->subMinutes(5);
        $atividade = Atividade::firstOrCreate(['evento_id'=>$evento->id, 'nome'=>sprintf('[Demo] %02d — %s: %s', $n + 1, $categoria->nome, $temas[$n % 10])], [
            'tipo'=>'atividade_evento', 'categoria_id'=>$categoria->id, 'ativo'=>true, 'criado_por'=>$criador,
            'modalidade'=>$n % 2 ? 'ead' : 'presencial', 'data_inicio'=>$agora->addDays(7), 'data_fim'=>$agora->addDays(7)->addHours(3),
            'formulario'=>[
                'titulo'=>'Inscrição — '.$categoria->nome, 'subtitulo'=>'Formulário de demonstração com dados fictícios.',
                'abertura'=>$inicio->format('Y-m-d\TH:i'), 'fechamento'=>$fim->format('Y-m-d\TH:i'),
                'limitar_inscricoes'=>true, 'limite_inscricoes'=>200, 'mostrar_vagas_restantes'=>true,
                'registrar_presenca_qrcode'=>false, 'campos'=>$campos, 'rows'=>[], 'grupos'=>[], 'fieldsets'=>[], 'criterios_vagas'=>[],
                'editor'=>['exibir'=>false, 'conteudo'=>''], 'mensagem_sucesso'=>'Inscrição de demonstração realizada.',
            ],
        ]);
        $atividadesIds[] = $atividade->id;
        $inicio = CarbonImmutable::parse($atividade->formulario['abertura']);
        $limite = min(CarbonImmutable::parse($atividade->formulario['fechamento']), $agora);
        $duracao = max(1, $limite->getTimestamp() - $inicio->getTimestamp());
        $quantidade = 12 + ($n % 5) * 4;
        for ($i = 0; $i < $quantidade; $i++) {
            $participante = $participantes[$i];
            if ($atividade->inscricoes()->where('participante_email', $participante->email)->exists()) continue;
            $resposta = [];
            foreach ($campos as $k => $campo) $resposta[$campo['nome']] = $campo['opcoes'][($i + $n + $k) % count($campo['opcoes'])]['valor'];
            Validator::make($resposta, app(FormularioInscricaoService::class)->regras($atividade))->validate();
            $request = Request::create('/demo', 'POST', [], [], [], [
                'HTTP_USER_AGENT'=>$agentes[($i + $n) % count($agentes)], 'REMOTE_ADDR'=>'192.0.2.'.($i + 1),
                'HTTP_ACCEPT_LANGUAGE'=>['pt-BR,pt;q=0.9','en-US,en;q=0.9','es;q=0.9'][$i % 3],
                'HTTP_REFERER'=>['https://demo.example.invalid/eventos','https://pesquisa.example.invalid','https://social.example.invalid'][$i % 3],
            ]);
            $fracao = match ($i % 4) { 0, 1=>0.25 + ($i % 5) * 0.006, 2=>0.72 + ($i % 3) * 0.01, default=>($i + 1) / ($quantidade + 1) };
            $data = $inicio->addSeconds((int) ($duracao * $fracao));
            $inscricao = new InscricaoAtividade([
                'atividade_id'=>$atividade->id, 'participante_id'=>$participante->id, 'participante_email'=>$participante->email,
                'resposta'=>$resposta, ...app(DispositivoVisitanteService::class)->capturar($request),
            ]);
            $inscricao->forceFill(['created_at'=>$data, 'updated_at'=>$data])->save();
            $criadas++;
        }
        app(DistribuicaoVagasService::class)->recalcular($atividade);
    }
    $cobertura = InscricaoAtividade::query()->join('atividades', 'atividades.id', '=', 'inscricoes_atividade.atividade_id')
        ->whereIn('atividades.id', $atividadesIds)->selectRaw('atividades.categoria_id, COUNT(*) as total')->groupBy('atividades.categoria_id')->pluck('total', 'categoria_id');
    if ($cobertura->count() !== $categorias->count()) throw new RuntimeException('Existem categorias sem inscrições no seed.');
    return ['eventos'=>count($eventos), 'atividades'=>count($atividadesIds), 'formularios'=>count($atividadesIds),
        'participantes'=>count($participantes), 'inscricoes_criadas'=>$criadas,
        'inscricoes_no_lote'=>InscricaoAtividade::whereIn('atividade_id', $atividadesIds)->count(),
        'categorias'=>$categorias->map(fn ($categoria) => ['nome'=>$categoria->nome, 'inscricoes'=>$cobertura[$categoria->id] ?? 0])->all()];
});
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
