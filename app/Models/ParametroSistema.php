<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[Table(name: 'parametros_sistema', key: 'clave', keyType: 'string', incrementing: false)]
class ParametroSistema extends Model
{
    /**
     * Valor convertido a su tipo de dato declarado.
     */
    public function valorTipado(): int|float|bool|string
    {
        return match ($this->tipo_dato) {
            'entero' => (int) $this->valor,
            'decimal' => (float) $this->valor,
            'booleano' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            default => (string) $this->valor,
        };
    }
}
