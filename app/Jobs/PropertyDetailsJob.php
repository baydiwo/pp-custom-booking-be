<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\PropertyDetails;
use App\Models\PropertyAreaDetails;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;


class PropertyDetailsJob implements ShouldQueue
{

    use InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    /**
     * Execute the job.
     *
     * @return void
     */
    public $tries = 3;
    public $timeout = 0;
    public function __construct()
    {
    }

    public function handle()
    {
        $request       = new Request();
        $token         = new ApiController(NULL, $request);
        $dataToken     = $token->authToken();
		$id = env("PROPERTY_ID");
		
		$save = self::requestDetails($id, $dataToken['token']);
		$savePropertyDetails = self::requestPropertyDetails($id, $dataToken['token']);
		if(is_countable($savePropertyDetails) && count($savePropertyDetails) > 0)
			$propertyDetails = $savePropertyDetails[0];
		else
			$propertyDetails = array();
		
		$model = PropertyDetails::where('property_id', $id)
								->first();

		if(!$model) {
			$model = new PropertyDetails();
		}
		$model->property_id 			= $id;
		$model->allow_group_bookings 	= (isset($save['allowGroupBookings']))? $save['allowGroupBookings']:0;
		$model->children_allowed 		= (isset($save['childrenAllowed']))? $save['childrenAllowed']:0;
		$model->currency 				= (isset($save['currency']))? $save['currency']:'';
		$model->currency_symbol 		= (isset($save['currencySymbol']))? $save['currencySymbol']:'';
		$model->default_arrival_time 	= $save['defaultArrivalTime'];
		$model->default_depart_time 	= $save['defaultDepartTime'];
		$model->gateway_id 				= (isset($save['gatewayId']))? $save['gatewayId']:0;
		$model->latitude 				= (isset($save['latitude']))? $save['latitude']:'';
		$model->longitude 				= (isset($save['latitude']))? $save['longitude']:'';
		$model->max_child_age 			= (isset($save['maxChildAge']))? $save['maxChildAge']:'';
		$model->max_infant_age 			= (isset($save['maxInfantAge']))? $save['maxInfantAge']:'';
		$model->min_age_required_to_book = (isset($save['minAgeRequiredToBook']))? $save['minAgeRequiredToBook']:'';
		$model->pets_allowed 			= (isset($save['petsAllowed']))? $save['petsAllowed']:0;
		$model->redirection_url 		= (isset($save['redirectionURL']))? $save['redirectionURL']:'';
		$model->smoking_allowed 		= (isset($save['smokingAllowed']))? $save['smokingAllowed']:0;
		$model->max_group_bookings 		= (isset($save['maxGroupBookings']))? $save['maxGroupBookings']:'';
		$model->google_analytics_code 	= (isset($save['googleAnalyticsCode']))? $save['googleAnalyticsCode']:'';
		$model->address_line1 			= (isset($propertyDetails['addressLine1']))? $propertyDetails['addressLine1']:'';
		$model->address_line2 			= (isset($propertyDetails['addressLine2']))? $propertyDetails['addressLine2']:'';
		$model->address_line3			= (isset($propertyDetails['addressLine3']))? $propertyDetails['addressLine3']:'';
		$model->address_line4 			= (isset($propertyDetails['addressLine4']))? $propertyDetails['addressLine4']:'';
		$model->city 					= (isset($propertyDetails['city']))? $propertyDetails['city']:'';
		$model->country_id 				= (isset($propertyDetails['countryId']))? $propertyDetails['countryId']:'';
		$model->email 					= (isset($propertyDetails['email']))? $propertyDetails['email']:'';
		$model->mobile 					= (isset($propertyDetails['mobile']))? $propertyDetails['mobile']:'';
		$model->phone 					= (isset($propertyDetails['phone']))? $propertyDetails['phone']:'';
		$model->post_code 				= (isset($propertyDetails['postCode']))? $propertyDetails['postCode']:'';
		$model->state 					= (isset($propertyDetails['state']))? $propertyDetails['state']:'';
		$model->name 					= (isset($propertyDetails['name']))? $propertyDetails['name']:'';
		$model->save();
		
        $api           = new ApiController($dataToken['token'], $request);
        $listAreasData = $api->listArea(env("PROPERTY_ID"));
        $listCategory  = collect($listAreasData)->where('inactive', false)->pluck('id');
		
		foreach($listCategory as $areaId)
		{
			$saveAreaDetails = self::requestAreaDetails(
				$areaId,
				$dataToken['token']
			);
			
			$saveData = PropertyAreaDetails::where('property_id', $id)
										->where('area_id', $areaId)
										->first();
			
			$saveAreaConfigDetails = self::requestAreaConfigDetails(
				$areaId,
				$dataToken['token']
			);
			
			$saveCategoryDetails = self::requestCategoryDetails(
				$saveAreaDetails['categoryId'],
				$dataToken['token']
			);
			
			$saveCategoryImage = self::requestCategoryImage(
				$saveAreaDetails['categoryId'],
				$dataToken['token']
			);
			
			$saveAreaAttribute = self::requestAreaAttribute(
				$areaId,
				$dataToken['token']
			);
			
			$bathrooms = $bedrooms = 0;
			$bed_list = ['1' => '165', '2' => '166', '3' => '167', '4' => '168', '5' => '169', '6' => '170', '8' => '177'];
			$bath_list = ['1' => '171', '2' => '172', '3' => '173', '4' => '174', '5' => '175', '6' => '176', '7' => '178'];
			foreach($saveAreaAttribute as $areaAttr)
			{
				if(in_array($areaAttr['id'], $bath_list))
					$bathrooms = array_search($areaAttr['id'], $bath_list);
					
				if(in_array($areaAttr['id'], $bed_list))
					$bedrooms = array_search($areaAttr['id'], $bed_list);
			}
			
			$petsAllowed = (isset($saveAreaConfigDetails['petsAllowed']) && $saveAreaConfigDetails['petsAllowed'] == 'True') ? 1 : 0;
			if(!$saveData){
				$saveData = new PropertyAreaDetails();
			}
			$saveData->category_id 		= $saveAreaDetails['categoryId'];
			$saveData->name 			= $saveAreaDetails['name'];
			$saveData->address_line1 	= $saveAreaDetails['addressLine1'];
			$saveData->address_line2 	= $saveAreaDetails['addressLine2'];
			$saveData->address_line3 	= $saveAreaDetails['addressLine3'];
			$saveData->town 			= $saveAreaDetails['town'];
			$saveData->state 			= $saveAreaDetails['state'];
			$saveData->post_code 		= $saveAreaDetails['postCode'];
			$saveData->external_ref 	= $saveAreaDetails['externalRef'];
			$saveData->clean_status 	= $saveAreaDetails['cleanStatus'];
			$saveData->description 		= $saveAreaDetails['description'];
			$saveData->extension 		= $saveAreaDetails['extension'];
			$saveData->guest_description = $saveAreaDetails['guestDescription'];
			$saveData->max_occupants 	= $saveCategoryDetails['maxOccupantsPerCategory'];
			$saveData->total_rooms 		= $saveCategoryDetails['numberOfAreas'];
			$saveData->long_description = $saveCategoryDetails['longDescription'];
			$saveData->image_link 		= (isset($saveCategoryImage[0]['url']) && $saveCategoryImage[0]['url'] != '') ? $saveCategoryImage[0]['url'] : '';
			$saveData->pets_allowed 	= $petsAllowed;
			$saveData->total_bedrooms 	= $bedrooms;//$saveAreaConfigDetails['numberOfBedrooms'];
			$saveData->total_baths 		= $bathrooms;//$saveAreaConfigDetails['numberOfFullBaths'];
			$saveData->created_date 	= $saveAreaDetails['createdDate'];
			$saveData->property_id 		= $saveAreaDetails['propertyId'];
			$saveData->bond 			= $saveAreaDetails['keyNumber2'];
			$saveData->area_id			= $areaId;
			$saveData->created_date		= date('Y-m-d H:i:s');
			$ss = $saveData;
			$saveData->save();
			sleep(1); 
		}

		return [
			'code' => 1,
			'status' => 'success',
			'message' => "Data Has Been Saved"
		];
	}

	public static function requestDetails($id, $dataToken)
	{
		$value = Cache::remember('property_details_' . $id, 10 * 60, function () use ($id, $dataToken) {
            $endpoint = 'properties/' . $id . '/ibe/settings';
            $response = Http::withHeaders([
                'authToken' => $dataToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

	public static function requestPropertyDetails($id, $dataToken)
	{
        $value = Cache::remember('property_' . $id, 10 * 60, function () use ($id, $dataToken) {
            $endpoint = 'properties/' . $id . '?modelType=full';
            $response = Http::withHeaders([
                'authToken' => $dataToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

	public static function requestAreaDetails($id, $dataToken)
	{
		$value = Cache::remember('area_details_' . $id, 10 * 60, function () use ($id, $dataToken) {
            $endpoint = 'areas/' . $id . '?modelType=full';
            $response = Http::withHeaders([
                'authToken' => $dataToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

	public static function requestAreaConfigDetails($id, $dataToken)
	{
		$value = Cache::remember('category_areas_' . $id, 10 * 60, function () use ($id, $dataToken) {
            $endpoint = 'areas/' . $id . '/configuration';
            $response = Http::withHeaders([
                'authToken' => $dataToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

	public static function requestCategoryDetails($id, $dataToken)
	{
        $value = Cache::remember('category_' . $id, 10 * 60, function () use ($id, $dataToken) {
            $endpoint = 'categories/' . $id.'?modelType=full';
            $response = Http::withHeaders([
                'authToken' => $dataToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

	public static function requestCategoryImage($id, $dataToken)
	{
        $value = Cache::remember('category_images_' . $id, 10 * 60, function () use ($id, $dataToken) {
            $endpoint = 'categories/' . $id .'/images';
            $response = Http::withHeaders([
                'authToken' => $dataToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }
	
	public static function requestAreaAttribute($id, $dataToken)
	{
        $value = Cache::remember('area_attributes' . $id, 10 * 60, function () use ($id, $dataToken) {
            $endpoint = 'areas/'.$id.'/attributes';
            $response = Http::withHeaders([
                'authToken' => $dataToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }
}