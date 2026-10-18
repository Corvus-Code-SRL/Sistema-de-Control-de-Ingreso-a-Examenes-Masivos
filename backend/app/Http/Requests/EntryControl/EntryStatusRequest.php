<?php

namespace App\Http\Requests\EntryControl;

use Illuminate\Foundation\Http\FormRequest;

class EntryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['desde' => ['nullable', 'string', 'max:64']];
    }
}
