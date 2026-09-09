<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class PresencaQrService
{
    public function novoCodigo(): string
    {
        do {
            $codigo = 'EVGI-'.strtoupper(bin2hex(random_bytes(16)));
        } while (InscricaoAtividade::query()->where('codigo_qr', $codigo)->exists());

        return $codigo;
    }

    public function habilitado(InscricaoAtividade $inscricao): bool
    {
        return ! empty($inscricao->atividade?->formulario['registrar_presenca_qrcode'])
            && filled($inscricao->codigo_qr);
    }

    /** @return array{codigo: string, imagem: string}|null */
    public function dados(InscricaoAtividade $inscricao, int $tamanho = 260): ?array
    {
        if (! $this->habilitado($inscricao)) return null;

        $resultado = Builder::create()
            ->writer(new PngWriter)
            ->data($inscricao->codigo_qr)
            ->encoding(new Encoding('ISO-8859-1'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size($tamanho)
            ->margin(12)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return ['codigo' => $inscricao->codigo_qr, 'imagem' => $resultado->getDataUri()];
    }

    public function garantirCodigos(Atividade $atividade): void
    {
        if (empty($atividade->formulario['registrar_presenca_qrcode'])) return;

        $atividade->inscricoes()->whereNull('codigo_qr')->select('id')->chunkById(100, function ($inscricoes): void {
            foreach ($inscricoes as $inscricao) $inscricao->update(['codigo_qr' => $this->novoCodigo()]);
        });
    }

    public function definir(InscricaoAtividade $inscricao, bool $presente, int $usuarioGi): void
    {
        $inscricao->update([
            'presente' => $presente,
            'data_presenca' => $presente ? now() : null,
            'presenca_validada_por' => $presente ? $usuarioGi : null,
        ]);
    }
}
