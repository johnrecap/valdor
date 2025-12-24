<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $table = "addresses";
    protected $fillable = [
        'label',
        'user_id',
        'governorate',
        'city',
        'street',
        'building_number',
        'apartment',
        'phone',
        'address',
        'latitude',
        'longitude'
    ];
    protected $casts = [
        'id'              => 'integer',
        'label'           => 'string',
        'user_id'         => 'integer',
        'governorate'     => 'string',
        'city'            => 'string',
        'street'          => 'string',
        'building_number' => 'string',
        'apartment'       => 'string',
        'phone'           => 'string',
        'address'         => 'string',
        'latitude'        => 'string',
        'longitude'       => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
