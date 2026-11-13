<?php

namespace App\Http\Requests;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class GuardarUsuarioRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuarioId = $this->route('id');
        $creando = $this->isMethod('post');

        return [
            'nombre' => [$creando ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$creando ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuarioId)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'roles' => [$creando ? 'required' : 'prohibited', 'array', 'min:1'],
            'roles.*' => ['string', Rule::enum(Rol::class)],
            'especialidades' => ['sometimes', 'array'],
            'especialidades.*' => ['string', 'exists:especialidades,codigo'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
