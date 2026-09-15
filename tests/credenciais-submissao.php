<?php
// php tests/credenciais-submissao.php — banco SQLite em memória, sem envio real de e-mails.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\SubmissaoPublicaController;
use App\Http\Controllers\SenhaSubmissaoController;
use App\Http\Controllers\SenhaParticipanteController;
use App\Models\{Submissao, Evento, InscricaoSubmissao, CredencialSubmissao, CredencialParticipante, CodigoInscricao, Participante, Atividade};
use App\Services\{GiEmailService, CaptchaInscricaoService, SenhaCompartilhadaService, IdentificacaoParticipanteService};
use Illuminate\Support\Facades\{DB, Schema, Hash};
use Illuminate\Http\Request;
use Illuminate\Session\{Store, ArraySessionHandler};
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
Schema::create('eventos', function ($t) { $t->id(); $t->string('nome'); $t->boolean('ativo'); $t->timestamps(); $t->softDeletes(); });
Schema::create('submissoes', function ($t) { $t->id(); $t->integer('evento_id'); $t->string('titulo'); $t->boolean('ativo'); $t->dateTime('data_inicio'); $t->dateTime('data_fim'); $t->integer('qtde_resumo'); $t->integer('qtde_autores'); $t->timestamps(); });
(require __DIR__.'/../database/migrations/2026_09_15_010000_add_perguntas_visiveis_to_submissoes_table.php')->up();
Schema::create('inscritos_submissao', function ($t) { $t->id(); $t->integer('submissao_id'); $t->string('email'); $t->string('senha'); $t->integer('credencial_versao')->default(1); $t->timestamps(); $t->unique(['submissao_id', 'email']); });
Schema::create('inscritos_submissao_trabalhos', function ($t) { $t->id(); $t->integer('inscrito_submissao_id'); $t->string('titulo_trabalho'); $t->text('conteudo')->nullable(); $t->string('status'); $t->boolean('tem_apoio_financeiro')->default(false); $t->string('apoiador')->nullable(); $t->string('apresentacao')->default('presencial'); $t->boolean('aprovacao_comite_etica')->default(false); $t->string('protocolo_comite_etica')->nullable(); $t->timestamps(); $t->softDeletes(); });
(require __DIR__.'/../database/migrations/2026_09_15_020000_add_palavras_chave_to_submissoes.php')->up();
(require __DIR__.'/../database/migrations/2026_09_15_030000_add_categoria_trabalho_to_submissoes.php')->up();
Schema::create('submissao_autores', function ($t) { $t->id(); $t->integer('inscrito_submissao_trabalho_id'); $t->string('nome'); $t->string('email'); $t->string('afiliacao')->nullable(); $t->boolean('principal'); $t->integer('ordem'); $t->integer('numero')->nullable(); $t->timestamps(); });
Schema::create('credenciais_participante', function ($t) { $t->id(); $t->integer('participante_id'); $t->string('email')->unique(); $t->string('senha'); $t->integer('credencial_versao')->default(1); $t->timestamps(); });
Schema::create('codigos_inscricao', function ($t) { $t->id(); $t->string('email'); $t->integer('participante_id')->nullable(); $t->string('codigo_hash'); $t->string('ip')->nullable(); $t->timestamp('expira_em'); $t->string('redefinicao_token_hash')->nullable(); $t->timestamp('redefinicao_expira_em')->nullable(); $t->timestamp('redefinicao_usado_em')->nullable(); $t->timestamps(); });

function check(bool $ok, string $mensagem): void { if (!$ok) throw new RuntimeException($mensagem); }
function req(array $dados = [], ?Store $sessao = null): Request {
    global $app;
    $r = Request::create('/submissao/1/formulario', 'POST', $dados);
    $r->setLaravelSession($sessao ?? new Store('teste', new ArraySessionHandler(120)));
    $app->instance('request', $r);
    $app['url']->setRequest($r);
    $app['redirect']->setSession($r->session());
    view()->share('errors', new ViewErrorBag);
    return $r;
}
function negado(callable $acao, int $status = 403): void {
    try { $acao(); } catch (HttpException $e) { check($e->getStatusCode() === $status, 'Status incorreto'); return; }
    throw new RuntimeException('Acesso deveria ser negado');
}
$evento = Evento::create(['nome' => 'Evento', 'ativo' => true]);
$sub = Submissao::create(['mostrar_palavras_chave' => false, 'evento_id' => $evento->id, 'titulo' => 'Submissão', 'ativo' => true, 'data_inicio' => now()->subDay(), 'data_fim' => now()->addDay()]);
$sub2 = Submissao::create(['mostrar_palavras_chave' => false, 'evento_id' => $evento->id, 'titulo' => 'Outra submissão', 'ativo' => true, 'data_inicio' => now()->subDay(), 'data_fim' => now()->addDay()]);
$i = $sub->inscricoes()->create(['email' => 'autor@example.test', 'senha' => Hash::make('Antiga123')]);
$i->update(['updated_at' => now()->subDay()]);
$i2 = $sub2->inscricoes()->create(['email' => $i->email, 'senha' => Hash::make('Atual123')]);
$t = $i->trabalhos()->create(['titulo_trabalho' => 'Trabalho compartilhado', 'status' => 'rascunho']);
$t->autores()->create(['nome' => 'Autor Principal', 'email' => $i->email, 'principal' => true, 'ordem' => 1]);
$t->autores()->create(['nome' => 'Outro Autor', 'email' => 'coautor@example.test', 'principal' => false, 'ordem' => 2]);
(require __DIR__.'/../database/migrations/2026_09_14_010000_create_credenciais_submissao.php')->up();
check(CredencialSubmissao::count() === 2, 'Migração deve unificar e-mails e incluir coautor');
check(Hash::check('Atual123', CredencialSubmissao::where('email', $i->email)->first()->senha), 'Migração deve conservar a senha mais recente');
$c = new SubmissaoPublicaController;
$r = req(['email_login' => $i->email, 'senha_login' => 'Atual123']);
$c->entrar($r, $sub);
$c->entrar($r, $sub2);
check($r->session()->has('submissoes_publicas.'.$sub2->id), 'Mesma senha deve entrar em outra submissão');

$mail = new class extends GiEmailService {
    public array $mensagens = [];
    public function enviar(string $email, ?string $nome, string $assunto, string $conteudoHtml, ?string $idExterno = null, array $extras = []): ?int { $this->mensagens[] = [$email, $conteudoHtml]; return 1; }
};
$captcha = new class extends CaptchaInscricaoService { public function validarSubmissao(Request $r, Submissao $s, string $resposta): void {} };
$rco = req(['email_recuperacao' => 'coautor@example.test', 'captcha' => 'ABC123']);
$c->esqueciSenha($rco, $sub, $mail, $captcha);
$html = $mail->mensagens[0][1];
$credencialRecuperada = CredencialSubmissao::where('email', 'coautor@example.test')->firstOrFail();
$expiracaoTemporaria = $credencialRecuperada->temporaria_expira_em;
check(
    $expiracaoTemporaria->betweenIncluded(now()->addHours(47)->addMinutes(59), now()->addHours(48)->addMinute()),
    'Senha temporária de submissão deve valer por 48 horas'
);
check(str_contains($html, 'vale por 48 horas'), 'E-mail de submissão deve informar validade de 48 horas');
check(
    $credencialRecuperada->redefinicao_expira_em->betweenIncluded(now()->addHours(47)->addMinutes(59), now()->addHours(48)->addMinute()),
    'Link de alteração da senha de submissão deve valer por 48 horas'
);
check(str_contains($html, 'link válido por 48 horas e para um único uso'), 'E-mail de submissão deve informar validade e uso único do link');
preg_match('/<strong>([^<]+)<\/strong>/', $html, $m); $temporaria = $m[1];
preg_match('#/senha/submissao/([A-Za-z0-9]{64})#', $html, $m); $token = $m[1];
check(!empty($token), 'E-mail deve incluir link');
$rco = req(['email_login' => 'coautor@example.test', 'senha_login' => $temporaria], $rco->session());
$c->entrar($rco, $sub);
$dadosView = $c->formulario($rco, $sub)->getData();
check($dadosView['trabalhos']->count() === 1, 'Coautor deve ver seu trabalho');
$render = $c->formulario($rco, $sub)->render();
check(str_contains($render, 'somente à leitura') && !str_contains($render, 'id="trabalhoForm"'), 'Coautor deve receber tela de leitura');
check(str_contains($render, $i->email), 'Leitura deve mostrar e-mail do primeiro autor');
$c->exportarDocumento($rco, $sub, $t);
negado(fn () => $c->atualizar($rco, $sub, $t));
negado(fn () => $c->excluir($rco, $sub, $t));
negado(fn () => $c->exportarDocumento($rco, $sub2, $t), 404);
$rsel = req(['trabalho_id' => $t->id], $rco->session()); $c->selecionar($rsel, $sub);

// O coautor pode criar um trabalho próprio; seu acesso anterior continua somente leitura.
$novo = ['email' => 'tentativa@example.test', 'primeiro_autor' => 'Outro Autor', 'primeiro_autor_afiliacao' => 'Universidade', 'titulo_trabalho' => 'Trabalho próprio', 'categoria_trabalho' => 'pesquisa_original', 'tem_apoio_financeiro' => 0, 'apresentacao' => 'presencial', 'aprovacao_comite_etica' => 0];
$rnovo = req($novo, $rco->session()); $c->criar($rnovo, $sub);
$proprio = $sub->trabalhos()->where('titulo_trabalho', 'Trabalho próprio')->firstOrFail();
check($proprio->inscricao->email === 'coautor@example.test', 'Criação deve usar e-mail autenticado');
check($c->formulario($rnovo, $sub)->getData()['trabalhos']->count() === 2, 'Combo deve reunir autoria e coautoria');
$rnovo = req($novo + ['conteudo' => '<p>Resumo editado</p>'], $rnovo->session());
$c->atualizar($rnovo, $sub, $proprio);
check($proprio->fresh()->conteudo === '<p>Resumo editado</p>', 'Primeiro autor deve editar');
// Seleção de trabalho fora da fase de edição oculta a exclusão e bloqueia o DELETE.
$proprio->update(['status' => 'avaliado']);
$rselecionado = req(['trabalho_id' => $proprio->id], $rnovo->session());
$c->selecionar($rselecionado, $sub);
$htmlForaEdicao = $c->formulario($rselecionado, $sub)->render();
check(!str_contains($htmlForaEdicao, 'id="excluirTrabalhoModal"') && !str_contains($htmlForaEdicao, 'data-bs-target="#excluirTrabalhoModal"'), 'Trabalho avaliado não pode apresentar exclusão');
try { $c->excluir($rselecionado, $sub, $proprio); throw new RuntimeException('Trabalho avaliado não pode ser excluído'); } catch (ValidationException $e) {}
$proprio->update(['status' => 'rascunho']);
check(str_contains($c->formulario($rselecionado, $sub)->render(), 'data-bs-target="#excluirTrabalhoModal"'), 'Exclusão deve aparecer para primeiro autor no prazo');
$sub->update(['data_fim' => now()->subMinute()]);
try { $c->atualizar($rnovo, $sub, $proprio); throw new RuntimeException('Prazo deve bloquear edição'); } catch (ValidationException $e) {}
try { $c->excluir($rnovo, $sub, $proprio); throw new RuntimeException('Prazo deve bloquear exclusão'); } catch (ValidationException $e) {}
try { $c->criar($rnovo, $sub); throw new RuntimeException('Prazo deve bloquear novo trabalho'); } catch (ValidationException $e) {}
$htmlEncerrado = $c->formulario($rnovo, $sub)->render();
check(!str_contains($htmlEncerrado, 'excluirTrabalhoModal') && !str_contains($htmlEncerrado, '?novo=1"'), 'Após o prazo, exclusão e novo trabalho não devem aparecer');
check($proprio->fresh() !== null, 'Tentativa de exclusão fora do prazo deve preservar trabalho');
$sub->update(['data_fim' => now()->addDay()]);

$sub->update(['data_inicio' => now()->addHours(2)]);
try { $c->criar($rnovo, $sub); throw new RuntimeException('Período futuro não pode permitir criação'); } catch (ValidationException $e) {}
check(!str_contains($c->formulario($rnovo, $sub)->render(), '?novo=1"'), 'Novo trabalho deve ficar oculto antes da abertura');
$sub->update(['data_inicio' => now()->subDay()]);
$semPeriodo = clone $sub;
$semPeriodo->data_inicio = null;
check(!$semPeriodo->aberta(), 'Cadastro sem início não pode estar aberto');
$semPeriodo->data_inicio = now()->subDay();
$semPeriodo->data_fim = null;
check(!$semPeriodo->aberta(), 'Cadastro sem fim não pode estar aberto');

$ca = CredencialParticipante::create(['participante_id' => 42, 'email' => 'coautor@example.test', 'senha' => Hash::make('Atividade123')]);
$reset = new SenhaSubmissaoController;
check($reset->edit($token)->getData()['valido'], 'Token deve ser válido');
check(str_contains($reset->edit($token)->render(), 'value="1" checked'), 'Sincronização deve vir marcada');
$resultado = $reset->update(req(['senha' => 'NovaSenha123', 'senha_confirmation' => 'NovaSenha123', 'usar_na_atividade' => 1]), $token, new SenhaCompartilhadaService);
check($resultado->getData()['sucesso'], 'Troca por token deve funcionar');
check(Hash::check('NovaSenha123', $ca->fresh()->senha), 'Senha deve sincronizar com atividade');
check(!$reset->edit($token)->getData()['valido'], 'Token não pode ser reutilizado');
check($c->formulario($rnovo, $sub)->getData()['acesso'] === null, 'Troca deve revogar sessões antigas de submissão');

// Recusa token expirado e preserva atividade quando a opção está desmarcada.
$cred = CredencialSubmissao::where('email', 'coautor@example.test')->first();
$token2 = str_repeat('b', 64);
$cred->update(['redefinicao_token_hash' => hash('sha256', $token2), 'redefinicao_expira_em' => now()->subMinute()]);
check(!$reset->edit($token2)->getData()['valido'], 'Token expirado deve ser recusado');
$cred->update(['redefinicao_expira_em' => now()->addMinutes(15)]);
$reset->update(req(['senha' => 'SomenteSub123', 'senha_confirmation' => 'SomenteSub123', 'usar_na_atividade' => 0]), $token2, new SenhaCompartilhadaService);
check(Hash::check('NovaSenha123', $ca->fresh()->senha), 'Opção desmarcada deve preservar atividade');

// Atividade: e-mail contém link e a troca por ele atualiza a credencial global de submissão.
$ident = new class extends IdentificacaoParticipanteService {
    public function __construct() {}
    public function resolverParticipante(string $email): array { $p = new Participante; $p->id = 42; $p->nome = 'Outro Autor'; return ['participante' => $p, 'criado' => false, 'unificados' => 0]; }
};
$cod = CodigoInscricao::create(['email' => $ca->email, 'codigo_hash' => hash('sha256', 'CODE1234'), 'expira_em' => now()->addMinutes(15), 'redefinicao_token_hash' => hash('sha256', str_repeat('c',64)), 'redefinicao_expira_em' => now()->addMinutes(15)]);
$pc = new SenhaParticipanteController;
$pc->update(req(['senha' => 'Ambas1234', 'senha_confirmation' => 'Ambas1234', 'usar_na_submissao' => 1]), str_repeat('c',64), $ident);
check(Hash::check('Ambas1234', $cred->fresh()->senha) && Hash::check('Ambas1234', $ca->fresh()->senha), 'Troca da atividade deve sincronizar submissão');
check($cod->fresh()->redefinicao_usado_em !== null && $cod->fresh()->expirado(), 'Troca deve consumir link e invalidar código temporário');
check(!(new SenhaParticipanteController)->edit(str_repeat('c',64), $ident)->getData()['valido'], 'Link da atividade não deve ser reutilizado');

// Cadastro de um e-mail novo pelo modal e recuperação sem revelar e-mails desconhecidos.
$rn = req(['email_recuperacao' => 'novo@example.test', 'captcha' => 'ABC123', 'cadastrar' => 1]);
$c->esqueciSenha($rn, $sub, $mail, $captcha);
check(CredencialSubmissao::where('email', 'novo@example.test')->exists(), 'Cadastro deve registrar e-mail novo');
$antes = count($mail->mensagens);
$c->esqueciSenha(req(['email_recuperacao' => 'desconhecido@example.test', 'captcha' => 'ABC123']), $sub, $mail, $captcha);
check(count($mail->mensagens) === $antes, 'Recuperação de desconhecido não deve enviar');

// Senha temporária expirada não autentica e falha no envio preserva a senha permanente.
$cred->update(['temporaria_hash' => Hash::make('Expirada123'), 'temporaria_expira_em' => now()->subMinute()]);
try { $c->entrar(req(['email_login' => $cred->email, 'senha_login' => 'Expirada123']), $sub); throw new RuntimeException('Senha temporária expirada foi aceita'); } catch (ValidationException $e) {}
$falhaEmail = new class extends GiEmailService {
    public function enviar(string $email, ?string $nome, string $assunto, string $conteudoHtml, ?string $idExterno = null, array $extras = []): ?int { throw new RuntimeException('Falha simulada'); }
};
$c->esqueciSenha(req(['email_recuperacao' => $i->email, 'captcha' => 'ABC123']), $sub, $falhaEmail, $captcha);
$principal = CredencialSubmissao::where('email', $i->email)->first();
check(Hash::check('Atual123', $principal->senha) && !$principal->temporaria_hash && !$principal->redefinicao_token_hash, 'Falha no envio deve preservar senha e invalidar códigos');

// Remover a coautoria revoga a leitura, mesmo com a sessão ainda aberta.
$rco = req(['email_login' => 'coautor@example.test', 'senha_login' => 'Ambas1234']); $c->entrar($rco, $sub);
$t->autores()->where('principal', false)->delete();
negado(fn () => $c->exportarDocumento($rco, $sub, $t));
negado(fn () => $c->selecionar(req(['trabalho_id' => $t->id], $rco->session()), $sub));

// Verifica o envio da atividade sem acionar serviços externos.
$app->instance(GiEmailService::class, $mail);
$servicoAtividade = $app->make(IdentificacaoParticipanteService::class);
$servicoAtividade->solicitarCodigo(req(), new Atividade(['nome' => 'Atividade teste']), 'coautor@example.test', true);
$ultima = $mail->mensagens[array_key_last($mail->mensagens)][1];
$codigoAtividade = CodigoInscricao::where('email', 'coautor@example.test')->latest('id')->firstOrFail();
$expiracaoAtividade = $codigoAtividade->expira_em;
check(
    $expiracaoAtividade->betweenIncluded(now()->addHours(47)->addMinutes(59), now()->addHours(48)->addMinute()),
    'Senha temporária de atividade deve valer por 48 horas'
);
check(str_contains($ultima, 'vale por 48 horas'), 'E-mail de atividade deve informar validade de 48 horas');
check(
    $codigoAtividade->redefinicao_expira_em->betweenIncluded(now()->addHours(47)->addMinutes(59), now()->addHours(48)->addMinute()),
    'Link de alteração da senha de atividade deve valer por 48 horas'
);
check(str_contains($ultima, 'link válido por 48 horas e para um único uso'), 'E-mail de atividade deve informar validade e uso único do link');
preg_match('#/senha/definir/([A-Za-z0-9]{64})#', $ultima, $m);
check(!empty($m[1]) && $pc->edit($m[1], $ident)->getData()['valido'], 'E-mail de atividade deve incluir link utilizável');
file_put_contents('/tmp/submissao-acesso-teste.html', $c->formulario(req(), $sub)->render());

echo "OK: migração, credenciais globais, coautoria, prazo, cadastro, recuperação, tokens e sincronização opcional.\n";
