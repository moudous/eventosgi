<?php

namespace App\Console\Commands;

use App\Models\PixCobranca;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CorrigirHorariosPix extends Command
{
    protected $signature = 'pix:corrigir-horarios';
    protected $description = 'Converte para o fuso da aplicação os horários PIX anteriormente gravados como UTC';

    public function handle(): int
    {
        $corrigidas = 0;
        $ignoradas = 0;

        PixCobranca::query()
            ->whereNotNull('pago_em')
            ->orderBy('id')
            ->eachById(function (PixCobranca $cobranca) use (&$corrigidas, &$ignoradas): void {
                $horarioSicoob = data_get($cobranca->resposta_api, 'pix.0.horario');
                if (! is_string($horarioSicoob) || trim($horarioSicoob) === '') {
                    $ignoradas++;
                    return;
                }

                try {
                    $horarioLocal = Carbon::parse($horarioSicoob)->setTimezone(config('app.timezone'));
                } catch (Throwable) {
                    $ignoradas++;
                    return;
                }

                if ($cobranca->pago_em?->format('Y-m-d H:i:s') === $horarioLocal->format('Y-m-d H:i:s')) return;

                $cobranca->update(['pago_em' => $horarioLocal]);
                $corrigidas++;
            }, 100);

        $this->info("Horários PIX corrigidos: {$corrigidas}. Sem horário original: {$ignoradas}.");

        return self::SUCCESS;
    }
}
