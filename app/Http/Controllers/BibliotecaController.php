<?php

namespace App\Http\Controllers;

use App\Models\ArquivoBiblioteca;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BibliotecaController
{
    private const EXTENSOES = 'jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,odt,rtf,txt,csv,xls,xlsx,ods,ppt,pptx,odp';

    public function index(Request $request): View
    {
        $busca = trim((string) $request->query('busca', ''));
        $tipo = (string) $request->query('tipo', '');
        $categoria = (string) $request->query('categoria', '');

        $arquivos = ArquivoBiblioteca::query()
            ->when($busca !== '', fn ($query) => $query->where(fn ($filtro) => $filtro
                ->where('nome', 'like', "%{$busca}%")
                ->orWhere('tags', 'like', "%{$busca}%")))
            ->when(isset(ArquivoBiblioteca::TIPOS[$tipo]), fn ($query) => $query->where('tipo', $tipo))
            ->when($tipo === 'imagem' && isset(ArquivoBiblioteca::CATEGORIAS[$categoria]),
                fn ($query) => $query->where('categoria', $categoria))
            ->latest('id')->paginate(24)->withQueryString();

        return view('biblioteca.index', compact('arquivos', 'busca', 'tipo', 'categoria'));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request, false);
        $this->guardar($request->file('arquivo'), $dados, $request);

        return back()->with('status', 'Arquivo enviado para a biblioteca.');
    }

    public function recortar(Request $request): RedirectResponse
    {
        $dados = $this->validar($request, true);
        $this->guardar($request->file('arquivo'), $dados, $request);

        return redirect()->route('biblioteca.index')->with('status', 'Recorte salvo como uma nova imagem.');
    }

    public function abrir(string $arquivo): BinaryFileResponse
    {
        $registro = ArquivoBiblioteca::query()->where('arquivo', $arquivo)->firstOrFail();
        $caminho = storage_path('app/public/biblioteca/'.$registro->arquivo);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Content-Type' => $registro->mime,
            'Content-Disposition' => "inline; filename*=UTF-8''".rawurlencode($registro->nome),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
        ]);
    }

    /** @return array{nome:string,tags:?array,categoria:?string} */
    private function validar(Request $request, bool $somenteImagem): array
    {
        $extensoes = $somenteImagem ? 'jpg,jpeg,png,gif,webp' : self::EXTENSOES;
        $dados = $request->validate([
            'arquivo' => ['required', 'file', 'max:25600', 'mimes:'.$extensoes],
            'nome' => ['required', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
            'categoria' => ['nullable', 'in:'.implode(',', array_keys(ArquivoBiblioteca::CATEGORIAS))],
        ], [
            'arquivo.required' => 'Selecione um arquivo.',
            'arquivo.mimes' => 'Este formato de arquivo não é aceito pela biblioteca.',
            'arquivo.max' => 'O arquivo deve ter no máximo 25 MB.',
            'nome.required' => 'Informe o nome do arquivo.',
        ]);

        $tags = $this->tags((string) ($dados['tags'] ?? ''));
        if (count($tags) > 10) {
            throw ValidationException::withMessages(['tags' => 'Informe no máximo 10 tags separadas por vírgula.']);
        }
        $dados['tags'] = $tags ?: null;

        return $dados;
    }

    /** @param array{nome:string,tags:?array,categoria:?string} $dados */
    private function guardar(UploadedFile $upload, array $dados, Request $request): ArquivoBiblioteca
    {
        $extensao = mb_strtolower($upload->getClientOriginalExtension());
        $tipo = $this->tipo($extensao);
        if ($tipo === 'imagem' && empty($dados['categoria'])) {
            throw ValidationException::withMessages(['categoria' => 'Selecione a categoria da imagem.']);
        }

        $nomeArmazenado = Str::uuid().'.'.$extensao;
        $pasta = storage_path('app/public/biblioteca');
        File::ensureDirectoryExists($pasta);
        $upload->move($pasta, $nomeArmazenado);
        $caminho = $pasta.'/'.$nomeArmazenado;
        [$largura, $altura] = $tipo === 'imagem' ? $this->dimensoes($caminho, $extensao) : [null, null];

        try {
            return ArquivoBiblioteca::create([
                'nome' => trim($dados['nome']),
                'arquivo' => $nomeArmazenado,
                'mime' => $this->mime($extensao, $caminho),
                'formato' => $extensao,
                'tipo' => $tipo,
                'tamanho' => File::size($caminho),
                'largura' => $largura,
                'altura' => $altura,
                'tags' => $dados['tags'],
                'categoria' => $tipo === 'imagem' ? $dados['categoria'] : null,
                'enviado_por' => $request->session()->get('gi_context.usuario.id'),
            ]);
        } catch (\Throwable $erro) {
            File::delete($caminho);
            throw $erro;
        }
    }

    /** @return list<string> */
    private function tags(string $texto): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $tag) => mb_substr(trim($tag), 0, 50),
            explode(',', $texto),
        ))));
    }

    private function tipo(string $extensao): string
    {
        return match ($extensao) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => 'imagem',
            'pdf' => 'pdf',
            'doc', 'docx', 'odt', 'rtf' => 'documento',
            'csv', 'xls', 'xlsx', 'ods' => 'planilha',
            'ppt', 'pptx', 'odp' => 'apresentacao',
            default => 'texto',
        };
    }

    private function mime(string $extensao, string $caminho): string
    {
        return match ($extensao) {
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            default => File::mimeType($caminho) ?: 'application/octet-stream',
        };
    }

    /** @return array{0:?int,1:?int} */
    private function dimensoes(string $caminho, string $extensao): array
    {
        if ($extensao !== 'svg') {
            $dados = @getimagesize($caminho);
            return $dados ? [(int) $dados[0], (int) $dados[1]] : [null, null];
        }

        $svg = File::get($caminho);
        if (preg_match('/<svg\b[^>]*>/i', $svg, $tag)
            && preg_match('/\bwidth=["\']([0-9.]+)(?:px)?["\']/i', $tag[0], $largura)
            && preg_match('/\bheight=["\']([0-9.]+)(?:px)?["\']/i', $tag[0], $altura)) {
            return [(int) round((float) $largura[1]), (int) round((float) $altura[1])];
        }
        if (preg_match('/<svg\b[^>]*\bviewBox=["\']\s*[-0-9.]+\s+[-0-9.]+\s+([0-9.]+)\s+([0-9.]+)\s*["\']/i', $svg, $m)) {
            return [(int) round((float) $m[1]), (int) round((float) $m[2])];
        }

        return [null, null];
    }
}
