<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Leave\Services\LeaveBalanceService;

class DistributeFirstYearLeaveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:distribute-first-year {--date= : Tanggal spesifik YYYY-MM-DD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mendistribusikan kuota cuti tahunan bagi karyawan yang genap 1 tahun masa kerja';

    /**
     * Execute the console command.
     */
    public function handle(LeaveBalanceService $service)
    {
        $date = $this->option('date');
        $this->info("Menjalankan distribusi kuota cuti 1 tahun masa kerja...");
        
        try {
            $count = $service->distributeFirstYearQuota($date);
            $this->info("Berhasil! {$count} karyawan mendapatkan kuota cuti baru hari ini.");
        } catch (\Exception $e) {
            $this->error("Gagal: " . $e->getMessage());
        }
    }
}
