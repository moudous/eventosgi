<?php

namespace App\Http\Controllers;

use App\Models\Convidado;
use App\Services\ArmazemService;
use App\Services\GiPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConvidadoController
{
    private const TITULACOES = ['Graduado','Especialista','Mestre','Doutor','Pós-doutor','Professor'];

    public function index(Request $request, ArmazemService $armazem): View { return view('convidados.index',['estadoTabela'=>$armazem->recuperar('convidados',$request)]); }
    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $query=Convidado::query(); $total=(clone $query)->count(); $busca=trim((string)$request->input('search.value',''));
        if($busca!=='')$query->where(fn($q)=>$q->where('nome','like',"%{$busca}%")->orWhere('sobrenome','like',"%{$busca}%")->orWhere('titulacao','like',"%{$busca}%")->orWhere('local','like',"%{$busca}%")->orWhere('email','like',"%{$busca}%"));
        $filtrados=(clone $query)->count();$colunas=['id','nome','sobrenome','titulacao','local','telefone_whatsapp','email','created_at','updated_at'];$coluna=$colunas[(int)$request->input('order.0.column',0)]??'id';$direcao=$request->input('order.0.dir')==='asc'?'asc':'desc';$inicio=max(0,(int)$request->input('start'));$tamanho=min(100,max(1,(int)$request->input('length',10)));$armazem->salvar('convidados',$request,intdiv($inicio,$tamanho)+1,$busca,$tamanho);$permissoes=app(GiPermissionService::class);
        $dados=$query->orderBy($coluna,$direcao)->skip($inicio)->take($tamanho)->get()->map(fn(Convidado $convidado)=>['id'=>$convidado->id,'nome'=>e($convidado->nome),'sobrenome'=>e($convidado->sobrenome?:'—'),'titulacao'=>e($convidado->titulacao?:'—'),'local'=>e($convidado->local?:'—'),'telefone_whatsapp'=>e($convidado->telefone_whatsapp?:'—'),'email'=>e($convidado->email?:'—'),'created_at'=>$convidado->created_at?->format('d/m/Y H:i')??'—','updated_at'=>$convidado->updated_at?->format('d/m/Y H:i')??'—','acoes'=>view('convidados.partials.acoes',['convidado'=>$convidado,'permissoes'=>$permissoes])->render()]);
        return response()->json(['draw'=>(int)$request->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$filtrados,'data'=>$dados]);
    }
    public function create(): View { return view('convidados.form',['convidado'=>new Convidado(),'titulacoes'=>self::TITULACOES]); }
    public function store(Request $request): RedirectResponse { Convidado::create($this->validar($request)); return redirect()->route('convidados.index')->with('status','Convidado cadastrado com sucesso.'); }
    public function show(Convidado $convidado): View { return view('convidados.show',compact('convidado')); }
    public function edit(Convidado $convidado): View { return view('convidados.form',['convidado'=>$convidado,'titulacoes'=>self::TITULACOES]); }
    public function update(Request $request,Convidado $convidado): RedirectResponse { $convidado->update($this->validar($request)); return redirect()->route('convidados.index')->with('status','Convidado atualizado com sucesso.'); }
    public function destroy(Convidado $convidado): JsonResponse { $convidado->delete(); return response()->json(['message'=>'Convidado excluído com sucesso.']); }

    private function validar(Request $request): array
    {
        $dados=$request->validate(['nome'=>['required','string','max:255'],'sobrenome'=>['nullable','string','max:255'],'titulacao_opcao'=>['nullable','string'],'titulacao_outra'=>['nullable','required_if:titulacao_opcao,outra','string','max:255'],'curriculo'=>['nullable','string'],'descricao'=>['nullable','string'],'local'=>['nullable','string','max:255'],'telefone_whatsapp'=>['nullable','string','max:30'],'email'=>['nullable','email','max:150'],'redes_sociais'=>['nullable','array'],'redes_sociais.*.rede'=>['required','string','max:30'],'redes_sociais.*.nome'=>['required','string','max:100'],'redes_sociais.*.url'=>['required','url:http,https','max:500']]);
        $opcao=(string)($dados['titulacao_opcao']??'');$dados['titulacao']=$opcao==='outra'?trim((string)($dados['titulacao_outra']??'')):(in_array($opcao,self::TITULACOES,true)?$opcao:null);unset($dados['titulacao_opcao'],$dados['titulacao_outra']);
        $dados['curriculo']=$this->limparHtml((string)($dados['curriculo']??''))?:null;
        $dados['redes_sociais']=array_values($dados['redes_sociais']??[]);
        return $dados;
    }
    private function limparHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><blockquote>');

        return trim((string) preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $html));
    }
}
