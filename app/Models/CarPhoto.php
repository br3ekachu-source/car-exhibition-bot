<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CarPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_id',
        'path'
    ];

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

// app/Models/CarPhoto.php
    public function getUrlAttribute()
    {
        return asset('storage/'.$this->path);
    }
}