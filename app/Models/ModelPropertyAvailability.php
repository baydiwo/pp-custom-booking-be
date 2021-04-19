<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelPropertyAvailability extends Model
{
    protected $table = 'property_availability';
    public $timestamps = false;
    
    public static $rules = [
        'check-availability-area' => [
            'propertyId' => 'required|integer',
            'areaId'     => 'required|integer'
        ],
        'check-availability-date' => [
            'dateFrom'   => 'required|date_format:Y-m-d',
            'dateTo'     => 'required|date_format:Y-m-d|after:dateFrom',
            'propertyId' => 'required|integer'
        ]
    ];
}
