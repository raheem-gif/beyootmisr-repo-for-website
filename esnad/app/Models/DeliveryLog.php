<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryLog extends Model
{
    protected $fillable = ['channel', 'recipient', 'template', 'status', 'provider_message_id', 'error'];
}
