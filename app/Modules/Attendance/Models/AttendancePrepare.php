<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendancePrepare extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_prepares';

    protected $fillable = [
        'employee_id',
        'date',
        'periode_start',
        'periode_end',
        'check_in',
        'check_out',
        'schedule_in',
        'schedule_out',
        'lm',
        'lm_count',
        'overtime',
        'overtime_count',
        'late_minutes',
        'status',
        'review_status',
        'is_locked',
        'locked_at',
        'locked_by',
        'notes',
    ];

    protected $casts = [
        'date'          => 'date:Y-m-d',
        'check_in'      => 'datetime',
        'check_out'     => 'datetime',
        'schedule_in'   => 'datetime:H:i',
        'schedule_out'  => 'datetime:H:i',
        'lm'            => 'integer',
        'lm_count'      => 'integer',
        'overtime'      => 'integer',
        'overtime_count' => 'integer',
        'late_minutes'  => 'integer',
        'is_locked'     => 'boolean',
        'locked_at'     => 'datetime',
        'periode_start' => 'date:Y-m-d',
        'periode_end'   => 'date:Y-m-d',
    ];

    /**
     * Auto-include accessors in JSON serialization.
     */
    protected $appends = ['status_label', 'status_badge_class', 'review_status_label', 'review_status_badge_class'];

    /**
     * Static cache untuk leave types — hindari N+1 query di accessor.
     * @var array<string, array{name: string, color_hex: string|null}>|null
     */
    protected static ?array $leaveTypeCache = null;

    // ─── Status Constants ────────────────────────────────────────────

    public const STATUS_HADIR      = 'hadir';
    public const STATUS_ABSENT     = 'absent';
    // STATUS_TERLAMBAT dihapus — status terlambat tidak dipakai.
    // Status cuti/izin/sakit sekarang pakai kode LeaveType langsung (ct, cm, skt, itm, dll).
    public const STATUS_LIBUR      = 'libur';
    public const STATUS_OFF        = 'off';

    // ─── Review Status Constants ──────────────────────────────────────

    public const REVIEW_CEK        = 'cek';
    public const REVIEW_PERHATIAN  = 'perhatian';
    public const REVIEW_LENGKAP    = 'lengkap';
    public const REVIEW_CSF        = 'csf';    // Consecutive Staff Flag

    // ─── Relations ────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    public function scopeReviewCek($query)
    {
        return $query->where('review_status', self::REVIEW_CEK);
    }

    public function scopeReviewLengkap($query)
    {
        return $query->where('review_status', self::REVIEW_LENGKAP);
    }

    // ─── Accessors ────────────────────────────────────────────────────

    public function getCheckInTimeAttribute(): ?string
    {
        return $this->check_in?->format('H:i');
    }

    public function getCheckOutTimeAttribute(): ?string
    {
        return $this->check_out?->format('H:i');
    }

    /**
     * Label untuk status absensi.
     * Untuk status leave type (ct, cm, skt, dll), resolve dari tabel LeaveType.
     */
    public function getStatusLabelAttribute(): string
    {
        // Cek apakah ini kode leave type
        $leaveName = static::getLeaveTypeName($this->status);
        if ($leaveName !== null) {
            return $leaveName;
        }

        return match ($this->status) {
            self::STATUS_HADIR  => 'Hadir',
            self::STATUS_ABSENT => 'Absen',
            self::STATUS_LIBUR  => 'Libur',
            self::STATUS_OFF    => 'Off',
            default             => ucfirst($this->status ?? ''),
        };
    }

    /**
     * Warna badge untuk status absensi.
     * Untuk leave type, pakai color_hex dari LeaveType.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        // Cek apakah ini kode leave type
        $leaveInfo = static::getLeaveTypeInfo($this->status);
        if ($leaveInfo !== null) {
            return 'bg-blue-100 text-blue-800';
        }

        return match ($this->status) {
            self::STATUS_HADIR  => 'bg-green-100 text-green-800',
            self::STATUS_ABSENT => 'bg-red-100 text-red-800',
            self::STATUS_LIBUR  => 'bg-gray-100 text-gray-800',
            self::STATUS_OFF    => 'bg-gray-200 text-gray-500',
            default             => 'bg-gray-100 text-gray-800',
        };
    }

    // ─── Leave Type Lookup (static cache) ─────────────────────────

    /**
     * Load semua leave types ke static cache.
     */
    public static function loadLeaveTypeCache(): void
    {
        if (static::$leaveTypeCache === null) {
            static::$leaveTypeCache = LeaveType::where('is_active', true)
                ->pluck('name', 'code')
                ->map(fn($name, $code) => ['name' => $name, 'code' => $code])
                ->toArray();
        }
    }

    /**
     * Dapatkan nama leave type dari kode status (case-insensitive).
     * Return null jika bukan kode leave type.
     */
    public static function getLeaveTypeName(string $status): ?string
    {
        static::loadLeaveTypeCache();
        $code = strtoupper($status);
        return static::$leaveTypeCache[$code]['name'] ?? null;
    }

    /**
     * Dapatkan info lengkap leave type dari kode status.
     */
    protected static function getLeaveTypeInfo(string $status): ?array
    {
        static::loadLeaveTypeCache();
        $code = strtoupper($status);
        return static::$leaveTypeCache[$code] ?? null;
    }

    /**
     * Label untuk review status.
     */
    public function getReviewStatusLabelAttribute(): string
    {
        return match ($this->review_status) {
            self::REVIEW_CEK       => 'Cek',
            self::REVIEW_PERHATIAN => 'Perhatian',
            self::REVIEW_LENGKAP   => 'Lengkap',
            self::REVIEW_CSF       => 'CSF',
            default                => ucfirst($this->review_status ?? ''),
        };
    }

    /**
     * Warna badge untuk review status.
     */
    public function getReviewStatusBadgeClassAttribute(): string
    {
        return match ($this->review_status) {
            self::REVIEW_CEK       => 'bg-yellow-100 text-yellow-800',
            self::REVIEW_PERHATIAN => 'bg-orange-100 text-orange-800',
            self::REVIEW_LENGKAP   => 'bg-green-100 text-green-800',
            self::REVIEW_CSF       => 'bg-blue-100 text-blue-800',
            default                => 'bg-gray-100 text-gray-800',
        };
    }

    // ─── Status Checkers ──────────────────────────────────────────────

    /**
     * Apakah record ini punya data check_in dan check_out lengkap?
     */
    public function hasCompleteTimes(): bool
    {
        return !is_null($this->check_in) && !is_null($this->check_out);
    }

    /**
     * Apakah record ini missing check_in atau check_out?
     */
    public function isIncomplete(): bool
    {
        return is_null($this->check_in) || is_null($this->check_out);
    }

    /**
     * Apakah status termasuk "hari libur/off" (bukan hari kerja)?
     */
    public function isOffDay(): bool
    {
        return in_array($this->status, [self::STATUS_LIBUR, self::STATUS_OFF]);
    }

    /**
     * Apakah status termasuk "tidak hadir karena alasan valid"?
     * Sekarang leave type disimpan sebagai kode langsung (ct, skt, itm, dll),
     * jadi kita cek apakah status adalah kode LeaveType yang valid.
     */
    public function isExcused(): bool
    {
        if (in_array($this->status, [self::STATUS_LIBUR, self::STATUS_OFF])) {
            return true;
        }
        // Cek apakah status adalah kode leave type (pakai static cache)
        return static::getLeaveTypeName($this->status) !== null;
    }

    // ─── Lock Methods ─────────────────────────────────────────────────

    /**
     * Kunci record — hanya superadmin & hrmanager.
     */
    public function lock(int $userId): void
    {
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $userId,
        ]);
    }

    /**
     * Buka kunci record — hanya superadmin & hrmanager.
     */
    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }
}
