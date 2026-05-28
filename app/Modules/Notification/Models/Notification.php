<?php

namespace App\Modules\Notification\Models;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    // Kita extend model bawaan Laravel agar sesuai dengan struktur modular kita
    // Jika ke depan butuh custom scope atau mutator, bisa ditambahkan di sini.
}
