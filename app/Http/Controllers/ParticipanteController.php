<?php

namespace App\Http\Controllers;

use App\Models\Participante;
use App\Models\Usuario;
use App\Services\ArmazemService;
use App\Services\GiPermissionService;
use App\Services\ParticipanteCertificadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParticipanteController
{
    public function index(Request $request, ArmazemService $armazem): View
    {
        return view('participantes.index', ['estadoTabela' => $armazem->recuperar('participantes', $request)]);
    }

    public function dados(Request $request, ArmazemService $armazem, ParticipanteCertificadoService $certificados): JsonResponse
    {
        $query = Participante::query(); $total = (clone $query)->count();
        $busca = trim((string) $request->input('search.value', ''));
        if ($busca !== '') $query->where(fn ($q) => $q->where('nome','like',"%{$busca}%")->orWhere('email','like',"%{$busca}%")->orWhere('email2','like',"%{$busca}%")->orWhere('email_institucional','like',"%{$busca}%")->orWhere('instituicao_ensino','like',"%{$busca}%"));
        $filtrados=(clone $query)->count(); $colunas=['id','nome','email','email2','email_institucional','instituicao_ensino','cpf',null,'ativo','criado_em','atualizado_em'];
        $coluna=$colunas[(int)$request->input('order.0.column',0)]??'id'; $coluna??='id'; $direcao=$request->input('order.0.dir')==='asc'?'asc':'desc';
        $inicio=max(0,(int)$request->input('start')); $tamanho=min(100,max(1,(int)$request->input('length',10))); $armazem->salvar('participantes',$request,intdiv($inicio,$tamanho)+1,$busca,$tamanho);
        $permissoes=app(GiPermissionService::class);
        $pagina=$query->orderBy($coluna,$direcao)->skip($inicio)->take($tamanho)->get();
        // Duas consultas agrupadas para a pagina inteira, em vez de duas por linha.
        $contagem=$certificados->contarPorParticipante($pagina->pluck('id')->map(fn($id)=>(int)$id)->all());
        $dados=$pagina->map(fn(Participante $participante)=>[
            'id'=>$participante->id,'nome'=>e($participante->nome),'email'=>e($participante->email?:'—'),'email2'=>e($participante->email2?:'—'),'email_institucional'=>e($participante->email_institucional?:'—'),'instituicao_ensino'=>e($participante->instituicao_ensino?:'—'),'cpf'=>e($participante->cpf?:'—'),
            'ativo'=>view('eventos.partials.status',['evento'=>$participante])->render(),'criado_em'=>$participante->criado_em?->format('d/m/Y H:i')??'—','atualizado_em'=>$participante->atualizado_em?->format('d/m/Y H:i')??'—',
            'certificados'=>view('participantes.partials.certificados',['contagem'=>$contagem[(int)$participante->id]])->render(),
            'acoes'=>view('participantes.partials.acoes',['participante'=>$participante,'permissoes'=>$permissoes,'certificados'=>$contagem[(int)$participante->id]])->render(),
        ]);
        return response()->json(['draw'=>(int)$request->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$filtrados,'data'=>$dados]);
    }

    public function create(): View { return view('participantes.form',['participante'=>new Participante()]); }
    public function store(Request $request): RedirectResponse { $dados=$this->validar($request); $dados['criado_por']=$request->session()->get('gi_context.usuario.id'); Participante::create($dados); return redirect()->route('participantes.index')->with('status','Participante cadastrado com sucesso.'); }
    public function show(int $id,string $nome,ParticipanteCertificadoService $certificados): View
    {
        $participante=$this->encontrar($id,$nome);

        return view('participantes.show',[
            'participante'=>$participante,
            'certificados'=>$certificados->contarPorParticipante([(int) $participante->id])[(int) $participante->id],
            // Usuarios ficam na conexao padrao e participantes na conexao cert, entao a
            // ligacao e feita aqui, com uma consulta, em vez de por relacionamento.
            'criador'=>$participante->criado_por ? Usuario::query()->find($participante->criado_por) : null,
        ]);
    }
    public function edit(int $id,string $nome): View { return view('participantes.form',['participante'=>$this->encontrar($id,$nome)]); }
    public function update(Request $request,int $id,string $nome): RedirectResponse { $this->encontrar($id,$nome)->update($this->validar($request)); return redirect()->route('participantes.index')->with('status','Participante atualizado com sucesso.'); }
    public function destroy(int $id,string $nome,ParticipanteCertificadoService $certificados): JsonResponse
    {
        $participante=$this->encontrar($id,$nome);

        // O botao some na listagem, mas a recusa mora aqui: esconder nao e impedir.
        if ($certificados->possuiCertificados((int) $participante->id)) {
            return response()->json(['message'=>'Este participante tem certificados emitidos e não pode ser excluído.'],409);
        }

        $participante->delete();

        return response()->json(['message'=>'Participante excluído com sucesso.']);
    }
    private function encontrar(int $id,string $nome): Participante { return Participante::query()->where('id',$id)->where('nome',$nome)->firstOrFail(); }
    private function validar(Request $request): array { return $request->validate(['nome'=>['required','string','max:100'],'email'=>['nullable','email','max:150'],'email2'=>['nullable','email','max:150'],'email_institucional'=>['nullable','email','max:150'],'instituicao_ensino'=>['nullable','string','max:80'],'cpf'=>['nullable','digits:11'],'sexo'=>['nullable','in:M,F'],'grupo'=>['nullable','string','max:1'],'ativo'=>['required','boolean']]); }
}
