<?php

namespace App\Http\Controllers;

use App\Models\Convidado;
use App\Models\Evento;
use App\Services\ArmazemService;
use App\Services\GiPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConvidadoController
{
    private const TITULACOES = ['Graduado','Especialista','Mestre','Doutor','Pós-doutor','Professor'];

    public function index(Request $request, ArmazemService $armazem): View { return view('convidados.index',['estadoTabela'=>$armazem->recuperar('convidados',$request),'eventosFiltro'=>Evento::withTrashed()->orderBy('nome')->get(['id','nome'])]); }
    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $query=Convidado::query()->with('evento'); $total=(clone $query)->count(); $filtroEvento=max(0,(int)$request->input('filtro_evento',0));if($filtroEvento>0)$query->where('evento_id',$filtroEvento);$busca=trim((string)$request->input('search.value',''));
        if($busca!=='')$query->where(fn($q)=>$q->where('nome','like',"%{$busca}%")->orWhere('sobrenome','like',"%{$busca}%")->orWhere('titulacao','like',"%{$busca}%")->orWhere('local','like',"%{$busca}%")->orWhere('email','like',"%{$busca}%")->orWhereHas('evento',fn($e)=>$e->where('nome','like',"%{$busca}%")));
        $filtrados=(clone $query)->count();$colunas=['id','evento_id','nome','sobrenome','titulacao','local','telefone_whatsapp','email','created_at','updated_at'];$coluna=$colunas[(int)$request->input('order.0.column',0)]??'id';$direcao=$request->input('order.0.dir')==='asc'?'asc':'desc';$inicio=max(0,(int)$request->input('start'));$tamanho=min(100,max(1,(int)$request->input('length',10)));$armazem->salvar('convidados',$request,intdiv($inicio,$tamanho)+1,$busca,$tamanho,['filtro_evento'=>$filtroEvento]);$permissoes=app(GiPermissionService::class);
        $dados=$query->orderBy($coluna,$direcao)->skip($inicio)->take($tamanho)->get()->map(fn(Convidado $convidado)=>['id'=>$convidado->id,'evento'=>e($convidado->evento?->nome??'Sem evento'),'nome'=>e($convidado->nome),'sobrenome'=>e($convidado->sobrenome?:'—'),'titulacao'=>e($convidado->titulacao?:'—'),'local'=>e($convidado->local?:'—'),'telefone_whatsapp'=>e($convidado->telefone_whatsapp?:'—'),'email'=>e($convidado->email?:'—'),'created_at'=>$convidado->created_at?->format('d/m/Y H:i')??'—','updated_at'=>$convidado->updated_at?->format('d/m/Y H:i')??'—','acoes'=>view('convidados.partials.acoes',['convidado'=>$convidado,'permissoes'=>$permissoes])->render()]);
        return response()->json(['draw'=>(int)$request->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$filtrados,'data'=>$dados]);
    }
    public function create(): View { return view('convidados.form',['convidado'=>new Convidado(),'titulacoes'=>self::TITULACOES,'eventos'=>Evento::query()->where('ativo',true)->orderBy('nome')->get()]); }
    public function store(Request $request): RedirectResponse { Convidado::create($this->validar($request)); return redirect()->route('convidados.index')->with('status','Convidado cadastrado com sucesso.'); }
    public function show(Convidado $convidado): View { return view('convidados.show',compact('convidado')); }
    public function edit(Convidado $convidado): View { return view('convidados.form',['convidado'=>$convidado,'titulacoes'=>self::TITULACOES,'eventos'=>Evento::query()->where('ativo',true)->orWhereKey($convidado->evento_id)->orderBy('nome')->get()]); }
    public function update(Request $request,Convidado $convidado): RedirectResponse { $this->convidadoEmEdicao=$convidado; $convidado->update($this->validar($request)); return redirect()->route('convidados.index')->with('status','Convidado atualizado com sucesso.'); }
    public function destroy(Convidado $convidado): JsonResponse { $this->apagarFoto($convidado->foto_nome); $convidado->delete(); return response()->json(['message'=>'Convidado excluído com sucesso.']); }

    public function foto(string $codigo): BinaryFileResponse
    {
        abort_unless((bool) preg_match('/^[a-f0-9]{40}\.jpg$/', $codigo), 404);
        $caminho = storage_path('app/private/convidados/'.$codigo);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function validar(Request $request): array
    {
        $dados=$request->validate(['evento_id'=>['required','integer','exists:eventos,id'],'nome'=>['required','string','max:255'],'sobrenome'=>['nullable','string','max:255'],'titulacao_opcao'=>['nullable','string'],'titulacao_outra'=>['nullable','required_if:titulacao_opcao,outra','string','max:255'],'curriculo'=>['nullable','string'],'descricao'=>['nullable','string'],'local'=>['nullable','string','max:255'],'telefone_whatsapp'=>['nullable','string','max:30'],'email'=>['nullable','email','max:150'],'foto'=>['nullable','image','mimes:jpeg,jpg,png,webp','max:8192'],'redes_sociais'=>['nullable','array'],'redes_sociais.*.rede'=>['required','string','max:30'],'redes_sociais.*.nome'=>['required','string','max:100'],'redes_sociais.*.url'=>['required','url:http,https','max:500']]);
        if ($request->hasFile('foto')) {
            $novo = $this->guardarFoto($request->file('foto'));
            $this->apagarFoto($this->convidadoEmEdicao?->foto_nome);
            $dados['foto_nome'] = $novo;
        }
        unset($dados['foto']);
        $opcao=(string)($dados['titulacao_opcao']??'');$dados['titulacao']=$opcao==='outra'?trim((string)($dados['titulacao_outra']??'')):(in_array($opcao,self::TITULACOES,true)?$opcao:null);unset($dados['titulacao_opcao'],$dados['titulacao_outra']);
        $dados['curriculo']=$this->limparHtml((string)($dados['curriculo']??''))?:null;
        $dados['redes_sociais']=array_values($dados['redes_sociais']??[]);
        return $dados;
    }

    private ?Convidado $convidadoEmEdicao = null;

    private function guardarFoto(UploadedFile $arquivo): string
    {
        $imagem = @imagecreatefromstring((string) file_get_contents($arquivo->getRealPath()));
        abort_unless($imagem !== false, 422, 'Não foi possível processar a foto.');
        $tamanho = 600;
        $saida = imagecreatetruecolor($tamanho, $tamanho);
        $branco = imagecolorallocate($saida, 255, 255, 255);
        imagefill($saida, 0, 0, $branco);
        imagecopyresampled($saida, $imagem, 0, 0, 0, 0, $tamanho, $tamanho, imagesx($imagem), imagesy($imagem));
        imagedestroy($imagem);
        $codigo = bin2hex(random_bytes(20)).'.jpg';
        $pasta = storage_path('app/private/convidados');
        if (! is_dir($pasta)) mkdir($pasta, 0750, true);
        imagejpeg($saida, $pasta.'/'.$codigo, 88);
        imagedestroy($saida);

        return $codigo;
    }

    private function apagarFoto(?string $nome): void
    {
        if ($nome && preg_match('/^[a-f0-9]{40}\.jpg$/', $nome)) @unlink(storage_path('app/private/convidados/'.$nome));
    }
    private function limparHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><blockquote>');

        return trim((string) preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $html));
    }
}
