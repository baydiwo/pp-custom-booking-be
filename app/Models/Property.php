<?php

namespace App\Models;

class Property
{
    public static $rules = [
        'categoryId'      => 'required|integer',
        'areaId'          => 'required|integer',
        'rateTypeId'      => 'required|integer',
        'adults'          => 'required|integer',
        'children'        => 'required|integer',
        'infants'         => 'required|integer',
        'bookingSourceId' => 'required|integer',
        'arrivalDate'     => 'required|date_format:Y-m-d H:i:s',
        'departureDate'   => 'required|date_format:Y-m-d H:i:s|after:arrivalDate',
    ];
}
