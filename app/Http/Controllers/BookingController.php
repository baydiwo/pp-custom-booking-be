<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Constant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BookingController
{
    private $authToken;
    private $request;
    private $params;

    public function __construct(Request $request)
    {
        // $this->authToken = Redis::get('authToken');
        $this->authToken = env('AUTH_TOKEN'); // please update
        $this->request = $request;
        $this->params  = $request->all();
    }

    public function create()
    {
        $api = new ApiController();
        $validator = Validator::make(
            $this->params,
            [
                'arrivalDate'     => 'required|date_format:Y-m-d H:i:s',
                'departureDate'   => 'required|date_format:Y-m-d H:i:s',
                'surname'         => 'required',
                'given'           => 'required',
                // 'bookingSourceId' => 'required|integer',
                'adults'          => 'required|integer',
                'areaId'          => 'required|integer',
                'categoryId'      => 'required|integer',
                'children'        => 'required|integer',
                'infants'         => 'required|integer',
                'rateTypeId'      => 'required|integer',
                'state'           => 'required',
                'town'            => 'required',
                'address'         => 'required',
                'countryId'       => 'required|integer',
                'nights'          => 'required|integer',
                'email'           => 'required|email',
                'phone'           => 'required',
                'postCode'        => 'required',

            ]
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        //create guest
        $paramCreateGuest = [
            'addressLine1' => $this->params['address'],
            'postCode'     => $this->params['postCode'],
            'state'        => $this->params['state'],
            'town'         => $this->params['town'],
            'countryId'    => $this->params['countryId'],
            'email'        => $this->params['email'],
            'guestGiven'   => $this->params['given'],
            'guestSurname' => $this->params['surname'],
            'mobile'       => $this->params['phone'],
        ];
        $createGuest = $api->createGuest($this->authToken, $paramCreateGuest);
        if(isset($createGuest['Message'])) {
            throw new Exception(ucwords($createGuest['Message']));
        }

        $this->params['guestId'] = $createGuest['id'];
        $this->params['bookingSourceId'] = 200;

        $endpoint = 'reservations';

        $response = Http::withHeaders([
            'authtoken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $this->params);

        if(isset($response['Message'])) {
            throw new Exception(ucwords($response['Message']));
        }
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $response->json()
        ];
    }
}
