<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    protected $table = 'accion';
    protected $primaryKey = 'id_accion';
    public $timestamps = false;

    protected $fillable = ['operacion', 'tipo_operacion', 'descripcion'];
}
