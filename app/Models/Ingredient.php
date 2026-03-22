<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'tags'];

    protected $casts = [
        'tags' => 'array',
    ];

    public function plats()
    {
        return $this->belongsToMany(Plat::class);
    }
}
