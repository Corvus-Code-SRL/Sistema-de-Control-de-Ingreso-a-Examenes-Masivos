<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Period extends Model
{
    use HasFactory;

    protected $table = 'periodo';

    protected $primaryKey = 'id_periodo';

    public $timestamps = false;

    protected $fillable = [
        'nombre_periodo',
        'gestion',
    ];
}
