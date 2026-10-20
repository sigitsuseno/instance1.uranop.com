<?php

namespace App\Modules\Supervisor\Attendance\Exceptions;

use RuntimeException;

/**
 * Berkas scanlog tidak bisa dipakai: rusak, bukan Excel, atau formatnya tidak
 * dikenali. Ini kesalahan sisi pemakai (422), bukan kegagalan server (500).
 */
class AttendanceScanFileException extends RuntimeException {}
