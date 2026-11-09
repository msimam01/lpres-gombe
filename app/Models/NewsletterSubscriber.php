<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A verified homepage newsletter subscription. The subscribe endpoint is the
 * only writer; the admin panel can list and remove subscribers.
 */
class NewsletterSubscriber extends Model
{
    protected $fillable = ['email'];
}
