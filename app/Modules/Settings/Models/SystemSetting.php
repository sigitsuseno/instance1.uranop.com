<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Shared\Traits\HasAuditLog;
use Carbon\Carbon;

class SystemSetting extends Model
{
    use HasAuditLog;
    // use SoftDeletes; // uncomment if table has softDeletes

    protected $guarded = ['id'];

    /**
     * Mengambil nilai setting berdasarkan key (untuk backward compatibility key-value)
     */
    public static function getSetting($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Mengambil jumlah hari kerja berdasarkan konfigurasi instance ini
     * (membaca dari kolom working_day_type dan fixed_working_day)
     *
     * @param int|null $month
     * @param int|null $year
     * @return int
     */
    public function working_day($month = null, $year = null)
    {
        $type = $this->working_day_type ?? 'fixed';

        if ($type === 'fixed') {
            return (int) ($this->fixed_working_day ?? 21);
        }

        $month = $month ?: (int) date('m');
        $year = $year ?: (int) date('Y');

        // Jika menggunakan kalender, hitung manual hari kerja (Senin-Jumat)
        if ($type === 'calendar') {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $workingDays = 0;
            
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = Carbon::createFromDate($year, $month, $i);
                // isWeekday() = Senin s/d Jumat
                if ($date->isWeekday()) {
                    $workingDays++;
                }
            }
            return $workingDays;
        }

        return 21;
    }
}
