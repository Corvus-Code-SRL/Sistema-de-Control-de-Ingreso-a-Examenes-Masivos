<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    protected $table = 'periodo';

    protected $primaryKey = 'id_periodo';

    public $timestamps = false;

    protected $fillable = [
        'nombre_periodo',
        'gestion',
    ];
}
