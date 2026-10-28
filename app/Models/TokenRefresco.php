<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class TokenRefresco extends Model
{
    protected $table = 'tokens_refresco';

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'usado_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
