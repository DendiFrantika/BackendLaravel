<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Tambahkan ini

class Pasien extends Model
{
    use HasFactory;

    protected $table = 'pasiens';

    protected $fillable = [
        'user_id', 
        'no_pendaftaran', 
        'nama', 
        'no_identitas', 
        'tanggal_lahir', 
        'email', 
        'alamat', 
        'no_telepon', 
        'jenis_kelamin'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}