<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use SoftDeletes;

    protected $fillable = ['nome', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
        'deleted_at' => 'datetime',
    ];
}
