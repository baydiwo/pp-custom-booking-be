<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\Types\This;

class PropertyController
{
    private $authToken;
    private $request;
    private $params;

    public function __construct(Request $request)
    {
        $this->authToken = Cache::get('authToken')['token'];
        $this->request = $request;
        $this->params  = $request->all();
    }

    public function list()
    {
        $api = new ApiController($this->authToken, $this->request);
        $data = $api->listProperty();
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $data
        ];
    }
    public function detail($id)
    {
        $api = new ApiController($this->authToken, $this->request);
        $validator = Validator::make(
            $this->params,
            Property::$rules['detail']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $detailProperty = $api->detailProperty($id);

        if ((count($detailProperty) == 0) || isset($detailProperty['Message'])) {
            throw new Exception(ucwords('Detail Property Not Found'));
        }
        
        $paramMinNight = [
            'categoryIds' => [$this->params['categoryId']],
            'dateFrom'    => $this->params['arrivalDate'],
            'dateTo'      => $this->params['departureDate'],
            'propertyId'  => $id,
            'rateIds'     => [$this->params['rateTypeId']]
        ];

        $minNight = $api->availabilityrategrid($paramMinNight);
        if (!$minNight) {
            throw new Exception(ucwords('Minimum Night Not Found'));
        } elseif(isset($minNight['Message'])) {
            throw new Exception(ucwords($minNight['Message']));
        }
        if (empty($minNight['categories'][0]['rates'])) {
            throw new Exception(ucwords('Rate Not Found'));
        }

        $detailSetting = $api->detailPropertySetting($id);
        if (isset($detailSetting['Message'])) {
            throw new Exception(ucwords($detailSetting['Message']));
        }

        $detailCategory = $api->detailCategory($id);
        if (isset($detailCategory['Message'])) {
            throw new Exception(ucwords($detailCategory['Message']));
        }

        if(($this->params['adults'] + $this->params['infants'] + $this->params['children']) > $detailCategory['maxOccupantsPerCategory']) {
            throw new Exception(ucwords('Occupants over limit'));
        }

        $areaConfiguration = $api->areaConfiguration($id);
        if (isset($areaConfiguration['Message'])) {
            throw new Exception(ucwords($areaConfiguration['Message']));
        }

        $paramsRateQuote = [
            'adults'        => $this->params['adults'],
            'areaId'        => $this->params['areaId'],
            'arrivalDate'   => $this->params['arrivalDate'],
            'categoryId'    => $this->params['categoryId'],
            'children'      => $this->params['children'],
            'departureDate' => $this->params['departureDate'],
            'infants'       => $this->params['infants'],
            'propertyId'    => $id,
            'rateTypeId'    => $this->params['rateTypeId'],
        ];

        $rateQuote = $api->rateQuote($paramsRateQuote);

        if (isset($rateQuote['Message'])) {
            throw new Exception(ucwords($rateQuote['Message']));
        }
        $to   = Carbon::createFromFormat('Y-m-d H:s:i', $this->params['arrivalDate']);
        $from = Carbon::createFromFormat('Y-m-d H:s:i', $this->params['departureDate']);
        $data['propertyId']      = $id;
        $data['propertyName']    = $detailProperty[0]['name'];
        $data['petAllowed']      = $detailSetting['petsAllowed'];
        $data['maxOccupants']    = $detailCategory['maxOccupantsPerCategory'];
        $data['totalGuests']     = $this->params['adults'] . ' adults, ' . $this->params['children'] . ' children, ' . $this->params['infants'] . ' infants';
        $data['totalRooms']      = $detailCategory['numberOfAreas'];
        $data['totalBedrooms']   = $areaConfiguration['numberOfBedrooms'];
        $data['totalBaths']      = $areaConfiguration['numberOfFullBaths'];
        $data['nights']          = $to->diffInDays($from);
        $data['accomodation']    = collect($rateQuote['rateBreakdown'])->sum('totalRate');
        $data['petFee']          = $detailSetting['petsAllowed'] == false ? 0 : 150;
        $data['totalAmount']     = $data['accomodation'] + $data['petFee'];
        $data['dueToday']        = $rateQuote['firstNightRate'];

        return [
            'code' => 1,
            'status' => 'success',
            'data' => $data
        ];
    }

    public function availabilityGrid()
    {
        $validator = Validator::make(
            $this->params,
            Property::$rules['availability-grid']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
        
        $this->checkAvailability();

    }

    public function checkAvailability()
    {
        $api = new ApiController($this->authToken, $this->request);
        $listProperty = $api->listProperty();

        $paramMinNight = [
            'categoryIds' => [$this->params['categoryId']],
            'dateFrom'    => $this->params['dateFrom'],
            'dateTo'      => $this->params['dateTo'],
            'propertyId'  => $this->params['propertyId'],
            'rateIds'     => [$this->params['rateIds']]
        ];

        $minNight = $api->availabilityrategrid($paramMinNight);
        die(json_encode($minNight));

        if($listProperty) {
            die(json_encode($listProperty));
            foreach ($listProperty as $key => $value) {
            }
        }
        die(json_encode($listProperty));

    }
}
