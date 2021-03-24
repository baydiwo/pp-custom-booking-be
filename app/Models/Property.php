<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $table = 'property';
    public $timestamps = false;
    
    public static $rules = [
        'detail' => [
            'categoryId'      => 'required|integer',
            'areaId'          => 'required|integer',
            'rateTypeId'      => 'required|integer',
            'adults'          => 'required|integer',
            'children'        => 'required|integer',
            'infants'         => 'required|integer',
            'bookingSourceId' => 'required|integer',
            'arrivalDate'     => 'required|date_format:Y-m-d H:i:s',
            'departureDate'   => 'required|date_format:Y-m-d H:i:s|after:arrivalDate',
        ],
        'availability-grid' => [
            // 'categoryId' => 'required|integer',
            // 'dateFrom'   => 'required|date_format:Y-m-d H:i:s',
            // 'dateTo'     => 'required|date_format:Y-m-d H:i:s|after:dateFrom',
            'propertyId' => 'required|integer',
            // 'rateIds'    => 'required|integer',
        ],
        'area-by-year' => [
            'areaId' => 'required|integer',
            'year'   => 'required|date_format:Y',
        ],
        'check-availability' => [
            'dateFrom'   => 'required|date_format:Y-m-d',
            'dateTo'     => 'required|date_format:Y-m-d|after:dateFrom',
            'propertyId' => 'required|integer',
            'areaId'     => 'required|integer',
        ]
    ];
}
