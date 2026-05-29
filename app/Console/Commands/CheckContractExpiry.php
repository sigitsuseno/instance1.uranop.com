<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Employee\Models\EmployeeContract;
use App\Notifications\ContractExpiringNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class CheckContractExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:check-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for contracts expiring in 15 days and send web push notifications to Admin/HR';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetDate = Carbon::today()->addDays(15)->format('Y-m-d');

        $expiringContracts = EmployeeContract::with('employee')
            ->where('is_latest', true)
            ->where('end_date', $targetDate)
            ->get();

        if ($expiringContracts->isEmpty()) {
            $this->info("No contracts expiring exactly in 15 days ({$targetDate}).");
            return 0;
        }

        $this->info("Found {$expiringContracts->count()} contracts expiring in 15 days.");

        // We assume HR managers/Admins should receive the notifications.
        // E.g. Users with role 'superadmin' or 'hrmanager'
        $hrUsers = User::role(['superadmin', 'hrmanager'])->get();

        if ($hrUsers->isEmpty()) {
            $this->warn("No users found with role 'superadmin' or 'hrmanager' to notify.");
            return 0;
        }

        foreach ($expiringContracts as $contract) {
            if ($contract->employee) {
                Notification::send($hrUsers, new ContractExpiringNotification($contract->employee, 15));
            }
        }

        $this->info("Notifications sent successfully.");
        return 0;
    }
}
