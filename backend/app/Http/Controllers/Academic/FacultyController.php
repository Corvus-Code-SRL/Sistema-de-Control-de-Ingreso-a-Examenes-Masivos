<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ListFacultiesRequest;
use App\Http\Resources\Academic\FacultyResource;
use App\Models\Faculty;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FacultyController extends Controller
{
    public function index(ListFacultiesRequest $request): AnonymousResourceCollection
    {
        return FacultyResource::collection(
            Faculty::query()->where('estado', 'ACTIVO')->orderBy('nombre')->get()
        );
    }
}
