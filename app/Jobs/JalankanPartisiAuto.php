<?php

namespace App\Jobs;

use App\Models\SesiPartisi;
use App\Services\Partisi\PartisiRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Menjalankan partisi auto di background untuk sesi besar (>BATAS_SYNC SubSLS).
 * Status dilacak di kolom sesi_partisi.job_status (antri→proses→selesai/gagal).
 */
class JalankanPartisiAuto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 280;

    public function __construct(public int $sesiId) {}

    public function handle(PartisiRunner $runner): void
    {
        $sesi = SesiPartisi::find($this->sesiId);
        if (! $sesi) {
            return;
        }

        $sesi->update(['job_status' => 'proses', 'job_error' => null]);
        $runner->run($sesi);
        $sesi->update(['job_status' => 'selesai', 'job_error' => null]);
    }

    public function failed(\Throwable $e): void
    {
        SesiPartisi::where('id', $this->sesiId)->update([
            'job_status' => 'gagal',
            'job_error' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }
}
