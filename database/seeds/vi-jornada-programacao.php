<?php

// Execute: php database/seeds/vi-jornada-programacao.php
// A carga é idempotente e preserva dados que não tenham sido criados por ela.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Atividade, Categoria, Convidado, Evento, InscricaoAtividade, Submissao, Usuario};
use App\Services\DistribuicaoVagasService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$origem = '/var/www/palestrantes';
$pastaFotos = storage_path('app/private/convidados');
if (! is_dir($pastaFotos)) mkdir($pastaFotos, 0750, true);

$pessoas = [
    ['alex_olivaldo.png', 'Alex', 'Olivaldo Pereira'],
    ['barbara_souza.png', 'Bárbara', 'Souza Almeida'],
    ['bruna_lorena.png', 'Bruna Lorena', 'Martins'],
    ['carla_cristina.png', 'Carla Cristina', 'Gonçalves da Costa', 2],
    ['copia/barbara_souza.jpeg', 'Bárbara', 'Souza Lima'],
    ['copia/gabriella_neri.jpeg', 'Gabriella', 'Neri Campos'],
    ['copia/gabrielle.jpeg', 'Gabrielle', 'Rodrigues'],
    ['daiane_oliveira.png', 'Daiane', 'Oliveira Santos'],
    ['daniele_duraes.png', 'Daniele', 'Durães'],
    ['danielle_lima.png', 'Danielle', 'Lima Ferreira'],
    ['edmilson_martins.png', 'Edmilson', 'Martins Júnior'],
    ['eduardo_henrique.png', 'Eduardo Henrique', 'Alves'],
    ['fernanda_oliveira.png', 'Fernanda', 'Oliveira Costa'],
    ['francisca_daniele.png', 'Francisca Daniele', 'Moura'],
    ['gabriella_neri.png', 'Gabriella', 'Neri'],
    ['karina_silveira.png', 'Karina', 'Silveira de Castro Namorato', 1],
    ['lucas_augusto.png', 'Lucas Augusto', 'Ribeiro'],
    ['paulo_henrique.png', 'Paulo Henrique', 'Neves Santos', 3],
    ['raphael_costa.png', 'Raphael', 'Costa Menezes'],
    ['victoria_abdo.png', 'Victoria', 'Abdo Ferreira'],
];

$titulacoes = [
    'Doutor(a) em Ciências Odontológicas',
    'Mestre(a) em Clínica Odontológica',
    'Especialista em Harmonização Orofacial',
    'Especialista em Implantodontia e Reabilitação Oral',
    'Professor(a) e pesquisador(a) em Saúde Bucal',
];

$salvarFoto = function (string $relativo) use ($origem, $pastaFotos): string {
    $arquivo = $origem.'/'.$relativo;
    if (! is_file($arquivo)) throw new RuntimeException("Imagem não encontrada: {$arquivo}");
    $imagem = @imagecreatefromstring((string) file_get_contents($arquivo));
    if ($imagem === false) throw new RuntimeException("Não foi possível processar: {$arquivo}");
    $saida = imagecreatetruecolor(600, 600);
    $branco = imagecolorallocate($saida, 255, 255, 255);
    imagefill($saida, 0, 0, $branco);
    imagecopyresampled($saida, $imagem, 0, 0, 0, 0, 600, 600, imagesx($imagem), imagesy($imagem));
    imagedestroy($imagem);
    $nome = sha1('vi-jornada:'.$relativo).'.jpg';
    imagejpeg($saida, $pastaFotos.'/'.$nome, 88);
    imagedestroy($saida);
    return $nome;
};

$resultado = DB::transaction(function () use ($pessoas, $titulacoes, $salvarFoto) {
    $evento = Evento::findOrFail(1);
    $criador = Usuario::query()->orderBy('id')->value('id');
    if (! $criador) throw new RuntimeException('Nenhum usuário disponível para criar as atividades.');

    $variaveis = $evento->pagina_variaveis ?? [];
    $evento->update(['pagina_variaveis' => array_replace($variaveis, [
        'periodo' => '22 e 23 de outubro de 2026',
        'subtitulo' => 'Ciência, inovação e prática clínica em dois dias de encontros para transformar a Odontologia.',
        'local' => 'FCO · Unidades I e II · Presencial e online',
        'agenda_inicio' => '2026-10-22',
        'agenda_fim' => '2026-10-23',
        'faq_online' => 'As palestras do dia 22 serão transmitidas online. O acesso será enviado aos participantes inscritos.',
        'faq_inscricoes' => 'Cada atividade possui inscrição própria. Confira o horário e use o botão exibido no card.',
        'faq_hands_on' => 'Os hands-on são presenciais, têm dois professores por turma e vagas limitadas.',
        'faq_reserva' => 'Inscrever além do limite registra o participante na lista de reserva e não garante uma vaga.',
        'faq_certificados' => 'Os certificados serão disponibilizados após a conferência das inscrições e da presença.',
    ])]);

    Submissao::query()->whereKey(1)->where('evento_id', $evento->id)->update([
        'ativo' => true,
        'data_inicio' => '2026-09-01 08:00:00',
        'data_fim' => '2026-10-18 23:59:00',
    ]);

    $convidados = [];
    foreach ($pessoas as $indice => $pessoa) {
        [$relativo, $nome, $sobrenome] = $pessoa;
        $existente = $pessoa[3] ?? null;
        $marcador = 'jornada.imagem.'.substr(sha1($relativo), 0, 16).'@example.invalid';
        $convidado = isset($existente)
            ? Convidado::findOrFail($existente)
            : Convidado::firstOrNew(['email' => $marcador]);
        $social = Str::slug($nome.'-'.$sobrenome);
        $convidado->fill([
            'evento_id' => $evento->id,
            'nome' => $nome,
            'sobrenome' => $sobrenome,
            'titulacao' => $titulacoes[$indice % count($titulacoes)],
            'descricao' => 'Convidado(a) da VI Jornada Odontológica, com atuação em ensino, pesquisa e prática clínica baseada em evidências.',
            'curriculo' => '<ol><li>Formação em Odontologia e experiência clínica na área de atuação.</li><li>Atuação acadêmica em ensino, pesquisa e extensão.</li><li>Participação em projetos científicos e eventos de atualização profissional.</li></ol>',
            'local' => 'Brasil',
            'email' => $marcador,
            'foto_nome' => $salvarFoto($relativo),
            'redes_sociais' => [
                ['rede' => 'instagram', 'nome' => 'Instagram', 'url' => 'https://www.instagram.com/'.$social],
                ['rede' => 'linkedin', 'nome' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/'.$social],
            ],
        ])->save();
        $convidado->eventos()->syncWithoutDetaching([$evento->id]);
        $convidados[] = $convidado;
    }

    $categorias = Categoria::query()->get()->keyBy(fn ($categoria) => Str::slug($categoria->nome));
    $categoria = fn (string $nome) => $categorias->get(Str::slug($nome))?->id
        ?? throw new RuntimeException("Categoria ausente: {$nome}");

    $programacao = [
        ['Odontologia digital: do escaneamento ao planejamento clínico', 'Palestra', 'ead', '2026-10-22 08:00', '2026-10-22 09:00', 'Transmissão online', 0],
        ['Diagnóstico precoce de lesões bucais', 'Palestra', 'ead', '2026-10-22 09:15', '2026-10-22 10:15', 'Transmissão online', 1],
        ['Estética responsável e previsibilidade clínica', 'Palestra', 'ead', '2026-10-22 10:30', '2026-10-22 11:30', 'Transmissão online', 2],
        ['Comunicação com o paciente na prática odontológica', 'Palestra', 'ead', '2026-10-22 11:45', '2026-10-22 12:45', 'Transmissão online', 3],
        ['Atualizações em periodontia contemporânea', 'Palestra', 'ead', '2026-10-22 14:00', '2026-10-22 15:00', 'Transmissão online', 4],
        ['Endodontia minimamente invasiva', 'Palestra', 'ead', '2026-10-22 15:15', '2026-10-22 16:15', 'Transmissão online', 5],
        ['Odontopediatria e acolhimento infantil', 'Palestra', 'ead', '2026-10-22 16:30', '2026-10-22 17:30', 'Transmissão online', 6],
        ['Planejamento restaurador integrado', 'Palestra', 'ead', '2026-10-22 17:45', '2026-10-22 18:45', 'Transmissão online', 7],
        ['Urgências odontológicas: decisão segura', 'Palestra', 'ead', '2026-10-22 19:00', '2026-10-22 20:00', 'Transmissão online', 8],
        ['Carreira, liderança e gestão de consultório', 'Palestra', 'ead', '2026-10-22 20:10', '2026-10-22 21:10', 'Transmissão online', 9],
        ['Inteligência artificial aplicada à Odontologia', 'Palestra', 'ead', '2026-10-22 21:20', '2026-10-22 22:20', 'Transmissão online', 10],
        ['Pesquisa clínica e prática baseada em evidências', 'Palestra', 'ead', '2026-10-22 22:30', '2026-10-22 23:30', 'Transmissão online', 11],
        ['Reabilitação oral: integração entre função e estética', 'Palestra', 'presencial', '2026-10-23 08:00', '2026-10-23 09:00', 'Auditório da Unidade I', 12],
        ['Cirurgia guiada e segurança no planejamento', 'Palestra', 'presencial', '2026-10-23 10:00', '2026-10-23 11:00', 'Auditório da Unidade II', 13],
        ['Harmonização orofacial com naturalidade', 'Palestra', 'presencial', '2026-10-23 14:00', '2026-10-23 15:00', 'Auditório da Unidade I', 14],
        ['O futuro da formação e da profissão odontológica', 'Palestra', 'presencial', '2026-10-23 19:00', '2026-10-23 20:00', 'Auditório da Unidade II', 15],
        ['Hands-on: isolamento absoluto e excelência restauradora', 'Hands On', 'presencial', '2026-10-23 09:15', '2026-10-23 11:15', 'Laboratório da Unidade I', [14, 15], 'hands_closed'],
        ['Hands-on: suturas e técnicas cirúrgicas fundamentais', 'Hands On', 'presencial', '2026-10-23 14:15', '2026-10-23 16:15', 'Laboratório da Unidade II', [16, 17], 'hands_reserve'],
        ['Hands-on: fotografia odontológica com smartphone', 'Hands On', 'presencial', '2026-10-23 19:15', '2026-10-23 21:15', 'Clínica da Unidade I', [18, 19], 'hands_open'],
        ['Minicurso: documentação clínica e fotografia', 'Minicurso', 'presencial', '2026-10-23 10:30', '2026-10-23 12:30', 'Sala 101 · Unidade I', 0, 'mini_closed'],
        ['Minicurso: prescrição medicamentosa segura', 'Minicurso', 'presencial', '2026-10-23 15:30', '2026-10-23 17:30', 'Sala 202 · Unidade II', 5],
        ['Minicurso: marketing ético para profissionais da saúde', 'Minicurso', 'presencial', '2026-10-23 20:15', '2026-10-23 22:15', 'Sala 103 · Unidade I', 10],
    ];

    $atividades = [];
    $todasAtividades = [];
    foreach ($programacao as $item) {
        [$nome, $tipo, $modalidade, $inicio, $fim, $local, $ministrantes] = $item;
        $estado = $item[7] ?? 'open';
        $limite = match ($estado) { 'hands_closed', 'hands_reserve', 'mini_closed' => 10, 'hands_open' => 15, default => 60 };
        $listaReserva = $estado === 'hands_reserve';
        $formulario = [
            'titulo' => 'Inscrição — '.$nome,
            'subtitulo' => $tipo === 'Hands On' ? 'Atividade prática presencial orientada por dois professores.' : 'Atividade da programação oficial da VI Jornada Odontológica.',
            'local' => $local,
            'abertura' => '2026-09-01T08:00',
            'fechamento' => '2026-10-23T23:59',
            'limitar_inscricoes' => true,
            'limite_inscricoes' => $limite,
            'mostrar_vagas_restantes' => true,
            'apos_encerrar_vagas' => $listaReserva ? 'lista_reserva' : 'encerrar',
            'lista_reserva_sem_limite' => false,
            'limite_lista_reserva' => $listaReserva ? 10 : null,
            'campos' => [], 'rows' => [], 'grupos' => [], 'fieldsets' => [], 'criterios_vagas' => [],
            'editor' => ['exibir' => false, 'conteudo' => ''],
            'mensagem_vagas_esgotadas' => Atividade::MENSAGEM_VAGAS_ESGOTADAS,
            'mensagem_ja_inscrito' => Atividade::MENSAGEM_JA_INSCRITO,
            'mensagem_identificacao' => Atividade::MENSAGEM_IDENTIFICACAO,
            'mensagem_sucesso' => 'Inscrição realizada com sucesso!',
        ];
        $atividade = Atividade::updateOrCreate(['evento_id' => $evento->id, 'nome' => $nome], [
            'tipo' => 'atividade_evento', 'formato' => 'simples', 'categoria_id' => $categoria($tipo),
            'ativo' => true, 'criado_por' => $criador, 'modalidade' => $modalidade,
            'data_inicio' => $inicio, 'data_fim' => $fim, 'formulario' => $formulario,
        ]);
        $ids = array_map(fn ($indice) => $convidados[$indice]->id, (array) $ministrantes);
        $atividade->convidados()->sync(collect($ids)->mapWithKeys(fn ($id, $ordem) => [$id => ['ordem' => $ordem + 1]])->all());
        $atividades[$estado] ??= $atividade;
        $todasAtividades[] = $atividade;
    }

    foreach ($atividades as $atividade) {
        $atividade->inscricoes()->where('participante_email', 'like', 'jornada.demo.%@example.invalid')->delete();
    }
    $inscrever = function (Atividade $atividade, int $regulares, int $reservas = 0): void {
        for ($i = 1; $i <= $regulares + $reservas; $i++) {
            InscricaoAtividade::create([
                'atividade_id' => $atividade->id,
                'participante_email' => sprintf('jornada.demo.%d.%02d@example.invalid', $atividade->id, $i),
                'lista_reserva' => $i > $regulares,
                'resposta' => ['origem' => 'carga demonstrativa da VI Jornada'],
                'ip' => '192.0.2.'.($i + 10),
                'user_agent' => 'EventosGI Demo',
            ]);
        }
    };
    $inscrever($atividades['hands_closed'], 10);
    $inscrever($atividades['hands_reserve'], 10, 3);
    $inscrever($atividades['hands_open'], 4);
    $inscrever($atividades['mini_closed'], 10);

    foreach ($todasAtividades as $atividade) app(DistribuicaoVagasService::class)->recalcular($atividade->refresh());

    return ['convidados_com_imagem' => count($pessoas), 'atividades' => count($programacao)];
});

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
