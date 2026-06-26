<?php

namespace App\Modules\Kasbon\Services;

use App\Modules\Kasbon\Models\KasbonRequest;
use App\Modules\Kasbon\Models\KasbonInstallment;
use App\Modules\Payroll\Models\PayrollConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KasbonService
{
    /**
     * Ambil config kasbon dari payroll_configs (dengan fallback default)
     */
    public function getConfig(): array
    {
        return PayrollConfig::getConfig('kasbon');
    }

    /**
     * Buat pengajuan kasbon baru
     */
    public function create(array $data, int $userId): KasbonRequest
    {
        return DB::transaction(function () use ($data, $userId) {
            $kasbon = KasbonRequest::create([
                'employee_id' => $data['employee_id'],
                'amount' => $data['amount'],
                'tenor' => $data['tenor'],
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'remaining_amount' => $data['amount'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return $kasbon;
        });
    }

    /**
     * Update pengajuan kasbon (pending only)
     */
    public function update(KasbonRequest $kasbon, array $data, int $userId): KasbonRequest
    {
        if (!$kasbon->canBeEdited()) {
            throw new \Exception('Hanya pengajuan dengan status pending yang bisa diedit.');
        }

        $kasbon->update([
            'employee_id' => $data['employee_id'],
            'amount' => $data['amount'],
            'tenor' => $data['tenor'],
            'reason' => $data['reason'] ?? $kasbon->reason,
            'remaining_amount' => $data['amount'],
            'updated_by' => $userId,
        ]);

        return $kasbon->fresh();
    }

    /**
     * Setujui kasbon + generate cicilan
     */
    public function approve(KasbonRequest $kasbon, int $userId): KasbonRequest
    {
        if ($kasbon->status !== 'pending') {
            throw new \Exception('Hanya pengajuan pending yang bisa disetujui.');
        }

        return DB::transaction(function () use ($kasbon, $userId) {
            $kasbon->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'remaining_amount' => $kasbon->amount,
            ]);

            // Generate cicilan
            $installmentService = app(KasbonInstallmentService::class);
            $installmentService->generateInstallments($kasbon);

            return $kasbon->fresh();
        });
    }

    /**
     * Tolak pengajuan kasbon
     */
    public function reject(KasbonRequest $kasbon, int $userId, ?string $rejectionNote = null): KasbonRequest
    {
        if ($kasbon->status !== 'pending') {
            throw new \Exception('Hanya pengajuan pending yang bisa ditolak.');
        }

        $kasbon->update([
            'status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'notes' => $rejectionNote ?: 'Ditolak',
            'updated_by' => $userId,
        ]);

        return $kasbon->fresh();
    }

    /**
     * Tandai kasbon sudah dicairkan
     */
    public function disburse(KasbonRequest $kasbon, int $userId): KasbonRequest
    {
        if ($kasbon->status !== 'approved') {
            throw new \Exception('Hanya pengajuan approved yang bisa dicairkan.');
        }

        $kasbon->update([
            'status' => 'disbursed',
            'disbursed_at' => now(),
            'updated_by' => $userId,
        ]);

        return $kasbon->fresh();
    }

    /**
     * Hapus pengajuan kasbon (pending only)
     */
    public function destroy(KasbonRequest $kasbon): void
    {
        if (!$kasbon->canBeEdited()) {
            throw new \Exception('Hanya pengajuan dengan status pending yang bisa dihapus.');
        }

        $kasbon->delete();
    }

    /**
     * Validasi request berdasarkan config
     */
    public function validateAgainstConfig(array $data): array
    {
        $config = $this->getConfig();
        $errors = [];

        // Cek limit
        $limitType = $config['limit_type'] ?? 'salary_multiplier';
        if ($limitType === 'salary_multiplier') {
            $employee = \App\Modules\Employee\Models\Employee::with('salaries')->find($data['employee_id']);
            $gajiPokok = $employee?->salaries?->first()?->gaji_pokok ?? 0;
            $maxAmount = $gajiPokok * ($config['limit_value'] ?? 3);
            if ($maxAmount > 0 && $data['amount'] > $maxAmount) {
                $errors[] = "Nominal kasbon melebihi batas maksimal (Rp " . number_format($maxAmount, 0, ',', '.') . ").";
            }
        } elseif ($limitType === 'fixed') {
            $maxAmount = $config['limit_value'] ?? 0;
            if ($maxAmount > 0 && $data['amount'] > $maxAmount) {
                $errors[] = "Nominal kasbon melebihi batas maksimal (Rp " . number_format($maxAmount, 0, ',', '.') . ").";
            }
        }

        // Cek tenor
        $maxTenor = $config['max_tenor'] ?? 12;
        if ($data['tenor'] > $maxTenor) {
            $errors[] = "Jumlah cicilan maksimal {$maxTenor} bulan.";
        }

        // Cek multi-kasbon
        $allowMulti = $config['allow_multi'] ?? false;
        if (!$allowMulti) {
            $hasOutstanding = KasbonRequest::where('employee_id', $data['employee_id'])
                ->whereIn('status', ['approved', 'disbursed'])
                ->where('remaining_amount', '>', 0)
                ->exists();
            if ($hasOutstanding) {
                $errors[] = "Karyawan masih memiliki kasbon yang belum lunas.";
            }
        }

        return $errors;
    }
}
