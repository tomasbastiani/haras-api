<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendCustomMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ajustá si querés chequear rol admin
        return true;
    }

    public function rules(): array
    {
        return [
            'emails'  => ['required', 'array', 'min:1'],
            'emails.*'=> ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required'  => 'Debe enviar al menos un destinatario.',
            'emails.array'     => 'El campo emails debe ser un array.',
            'emails.*.email'   => 'Uno o más emails tienen un formato inválido.',
            'subject.required' => 'El asunto es obligatorio.',
            'body.required'    => 'El cuerpo del mensaje es obligatorio.',
        ];
    }
}
