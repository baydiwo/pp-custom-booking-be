<?php

namespace App\Http\Controllers;

use App\Jobs\PropertyJob;
use App\Models\Property;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        } elseif (isset($minNight['Message'])) {
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

        if (($this->params['adults'] + $this->params['infants'] + $this->params['children']) > $detailCategory['maxOccupantsPerCategory']) {
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

        // Cache::flush();
        // Queue::pushOn(
        // 'import-talent-queue',new PropertyJob()
        // );

        dispatch(new PropertyJob($this->params['propertyId']));
        return [
            'code' => 1,
            'status' => 'success',
            'data' => [],
            'message' => "Data Has Been Saved in Cache"
        ];
    }

    public function rateByDate($dateFrom, $dateTo)
    {
        $diff = $dateFrom->diffInDays($dateTo);

        if ($diff == 1 || $diff == 2) {
            $rateId = 12;
        }

        if ($diff == 3) {
            $rateId = 2;
        }

        if ($diff == 4) {
            $rateId = 3;
        }

        if ($diff == 5) {
            $rateId = 4;
        }

        if ($diff == 6) {
            $rateId = 5;
        }

        if ($diff >= 7) {
            $rateId = 6;
        }

        return $rateId;
    }

    public function checkAvailability()
    {
        $validator = Validator::make(
            $this->params,
            Property::$rules['check-availability']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $from = Carbon::parse($this->params['dateFrom']);
        $to = Carbon::parse($this->params['dateTo']);
        $diff = $from->diffInDays($to);
        if ($diff > 14) {
            throw new Exception("Different Days Cannot Greater Than 14 Days");
        }

        $getRate = $this->rateByDate($from, $to);


        $result = Property::select('response')
            ->where('property_id', $this->params['propertyId'])
            ->where('area_id', $this->params['areaId'])
            ->where('date_from', '<=', $from)
            ->orderBy('date_from', 'DESC')
            ->first();
        $new = json_decode($result->response);
        $collect = collect($new->categories[0]->rates)->where('rateId', $getRate)->values()->first();
        $dayBreakDown = collect($collect->dayBreakdown)
            ->whereBetween('theDate',[$this->params['dateFrom'], $this->params['dateTo']] )->all();
        $collect->dayBreakdown = $dayBreakDown;
        // $name = "prop1_area_".$this->params['areaId']."_from_".$this->params['dateFrom'].
        // "_to_". $this->params['dateTo'];

        // $redis = Cache::getRedis();
        // $keys = $redis->keys("*{$name}*");
        // // $count = 0;
        // $result = [];

        // foreach ($keys as $key) {
        //     $result[] = $red

        // }      

        // $newResult  = [];
        // foreach ($result as $value) {
        //     $newResult[] = unserialize($value);
        // }

        return [
            'code' => 1,
            'status' => 'success',
            'data' => [
                "categories" => [
                    "categoryId" => $new->categories[0]->categoryId,
                    "name" => $new->categories[0]->name,
                    "rates" => $collect,
                ]
            ]
        ];


        return $result;

        // $api = new ApiController($this->authToken, $this->request);
        // $listProperty = $api->listProperty();

        // $dateInYear = $this->getDateInYear(date("Y")."-01-01", date("Y")."-12-31");
        // $chunck = array_chunk($dateInYear, 14);
        // $push = [];
        // $temp = "";
        // for ($i=0; $i <= count($chunck[0]) ; $i++) { 
        //     for ($j=0; $j < $i; $j++) { 
        //         $push[$i][$j] = $chunck[0][$j];
        //     }
        // }

        // $push2 = [];
        // foreach ($push as $key => $value) {
        //     if($key != 1) {
        //         $push2[$key]['first']= reset($value);
        //         $push2[$key]['last']= end($value);
        //     }
        // }

        // $newArrayValue = array_values($push2);
        // if($listProperty) {
        //     foreach ($listProperty as $keyProp => $valueProp) {
        //         foreach ($newArrayValue as $keyNew => $valueNew) {
        //             $paramMinNight = [
        //                 'categoryIds' => [$this->params['categoryId']],
        //                 'dateFrom'    => $valueNew['first'],
        //                 'dateTo'      => $valueNew['last'],
        //                 'propertyId'  => $valueProp['id'],
        //                 'rateIds'     => [$this->params['rateIds']]
        //             ];    

        //             Cache::remember('min_night_prop'.$valueProp['id']."from {$valueNew['first']} - to {$valueNew['last']}"
        //             , 10 * 60, function () use ($api, $paramMinNight) {
        //                 return $api->availabilityrategrid($paramMinNight);
        //             });
        //         }
        //     }
        // }

        // return "Data Has Been Saved in Cache";
    }

    public function getDateInYear($first, $last, $step = '+1 day', $output_format = 'Y-m-d')
    {
        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {
            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }
        return $dates;
    }

    public function areaByYear()
    {
        $validator = Validator::make(
            $this->params,
            Property::$rules['area-by-year']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $data = Property::select('response')->where("area_id", $this->params['areaId'])->get();
        if(count($data) == 0) {
            throw new Exception(ucwords('Data Not Found'));
        }
        $newResponse = collect($data)->map(function($data){
            return json_decode($data->response);
        })->pluck('categories')->all();
        
        $response = [];
        foreach ($newResponse as $key => $value) {
            $valueCollect = collect($value[0]->rates)->pluck('dayBreakdown')->last();
            foreach ($valueCollect as $keys => $valueDataTemp) {
                $response[$keys][$valueDataTemp->theDate] = $valueDataTemp->availableAreas;
            }
        }

        $return = collect($response)->all();

        $temp = [];
        foreach ($return as $returnkey => $valuereturn) {
            foreach ($valuereturn as $keyreturn => $valueDataReturn) {
                $temp[$keyreturn] = $valueDataReturn;
            }
        }

        $dataReturn = collect($temp)->sort()->all();
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $dataReturn
        ];

    }
}
