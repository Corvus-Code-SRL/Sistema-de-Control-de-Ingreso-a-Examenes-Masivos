<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TipoExamen extends Model {
    protected $table = "tipo_examen";
    protected $primaryKey = "id_tipo_examen";
    public $timestamps =false;


    protected $casts = [
        'nombre'=> 'string',
        'categoria' => 'string'
    ];

    protected $fillable = [
        'nombre',
        'categoria'
    ];

    // public functions examenes(){
    //     return $this->hasMany(Examen::class, 'id_tipo_examen');
    // }
}
