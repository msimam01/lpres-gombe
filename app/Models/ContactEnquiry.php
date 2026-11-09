<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A contact form submission from the public site, triaged by staff in the
 * admin panel (handled/unhandled) and deletable once processed.
 */
class ContactEnquiry extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'message', 'handled_at'];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function scopeUnhandled($query)
    {
        return $query->whereNull('handled_at')->orderByDesc('id');
    }
}
