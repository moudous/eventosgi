<?php

namespace App\Services;

use App\Models\Atividade;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\File;

class ConteudoEditorFormularioService
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'h1', 'h2', 'h3', 'ol', 'ul', 'li', 'blockquote', 'a', 'img', 'span', 'sub', 'sup', 'pre', 'code', 'table', 'tbody', 'tr', 'td'];

    public function sanitizar(?string $html): string
    {
        if (trim((string) $html) === '') return '';

        $documento = new DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="UTF-8"><div id="editor-raiz">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        $raiz = $documento->getElementById('editor-raiz');
        if (! $raiz) return '';

        $this->limparFilhos($raiz);

        return collect(iterator_to_array($raiz->childNodes))
            ->map(fn (DOMNode $no) => $documento->saveHTML($no))->implode('');
    }

    public function sincronizarImagens(Atividade $atividade, string $html): void
    {
        $pasta = $this->pasta($atividade);
        if (! is_dir($pasta)) return;

        preg_match_all('/\/editor\/imagens\/([a-f0-9-]+\.(?:jpg|jpeg|png|gif|webp))/i', $html, $encontradas);
        $usadas = array_flip($encontradas[1] ?? []);
        foreach (File::files($pasta) as $arquivo) {
            if (! isset($usadas[$arquivo->getFilename()])) File::delete($arquivo->getPathname());
        }
    }

    public function pasta(Atividade $atividade): string
    {
        return storage_path('app/public/formularios/editor/'.$atividade->hash_publica);
    }

    private function limparFilhos(DOMNode $pai): void
    {
        foreach (iterator_to_array($pai->childNodes) as $filho) {
            if (! $filho instanceof DOMElement) continue;
            $tag = strtolower($filho->tagName);
            if (! in_array($tag, self::TAGS, true)) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $pai->removeChild($filho);
                    continue;
                }
                while ($filho->firstChild) $pai->insertBefore($filho->firstChild, $filho);
                $pai->removeChild($filho);
                continue;
            }
            $this->limparAtributos($filho);
            $this->limparFilhos($filho);
        }
    }

    private function limparAtributos(DOMElement $elemento): void
    {
        foreach (iterator_to_array($elemento->attributes) as $atributo) {
            $nome = strtolower($atributo->name);
            $valor = trim($atributo->value);
            $manter = match ($nome) {
                'class' => $this->classeSegura($valor),
                'style' => $this->estiloSeguro($valor),
                'href' => $elemento->tagName === 'a' && $this->urlSegura($valor),
                'src' => $elemento->tagName === 'img' && $this->urlSegura($valor),
                'alt', 'title' => in_array($elemento->tagName, ['a', 'img'], true),
                'target' => $elemento->tagName === 'a' && in_array($valor, ['_blank', '_self'], true),
                'data-list' => $elemento->tagName === 'li' && in_array($valor, ['ordered', 'bullet', 'checked', 'unchecked'], true),
                'data-row' => $elemento->tagName === 'td' && preg_match('/^row-[a-z0-9]{4}$/', $valor) === 1,
                'width', 'height' => $elemento->tagName === 'img' && preg_match('/^\d{1,4}$/', $valor) === 1,
                default => false,
            };
            if (! $manter) $elemento->removeAttribute($atributo->name);
            elseif ($nome === 'src') $elemento->setAttribute($atributo->name, $this->normalizarUrlImagem($valor));
        }
        if ($elemento->tagName === 'a' && $elemento->getAttribute('target') === '_blank') {
            $elemento->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function classeSegura(string $classes): bool
    {
        if ($classes === '') return false;
        foreach (preg_split('/\s+/', $classes) ?: [] as $classe) {
            if (! preg_match('/^(?:ql-ui|ql-(?:align-(?:center|right|justify)|indent-[1-8]|font-(?:serif|monospace)|size-(?:small|large|huge)|direction-rtl|syntax))$/', $classe)) return false;
        }
        return true;
    }

    private function estiloSeguro(string $estilo): bool
    {
        if ($estilo === '' || preg_match('/(?:url|expression|javascript|data):?/i', $estilo)) return false;
        foreach (array_filter(array_map('trim', explode(';', $estilo))) as $declaracao) {
            if (! preg_match('/^(?:color|background-color|text-align)\s*:\s*[#(),.%\sa-z0-9-]+$/i', $declaracao)) return false;
        }
        return true;
    }

    private function urlSegura(string $url): bool
    {
        return preg_match('#^(?:https?://|/)[^\s<>]+$#i', $url) === 1;
    }

    private function normalizarUrlImagem(string $url): string
    {
        return (string) preg_replace(
            '~(/formularios/[a-f0-9]{64}/editor/imagens/[a-f0-9-]{36}\.(?:jpg|jpeg|png|gif|webp))(?=[?#]|$)~i',
            '$1/visualizar',
            $url,
        );
    }
}
