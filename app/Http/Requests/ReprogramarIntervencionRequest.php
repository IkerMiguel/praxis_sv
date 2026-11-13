<?php

namespace App\Http\Requests;

class ReprogramarIntervencionRequest extends ProgramarIntervencionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + ['motivo' => ['required', 'string', 'min:10', 'max:500']];
    }
}
