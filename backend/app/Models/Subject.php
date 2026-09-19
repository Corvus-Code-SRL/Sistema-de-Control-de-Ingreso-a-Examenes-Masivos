<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'materia';
    protected $primaryKey = 'id_materia';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
        'estado',
    ];

    public function groups()
    {
        return $this->hasMany(Group::class, 'id_materia');
    }
}
