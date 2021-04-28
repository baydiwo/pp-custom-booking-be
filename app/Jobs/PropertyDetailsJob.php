<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\PropertyDetails;
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
		
		$save = self::requestDetails( $id, $dataToken['token']);
		
		$check = PropertyDetails::where('property_id', $id)
			->where('property_id', env("PROPERTY_ID"))
			->first();

		if($check) {
			PropertyDetails::where('id', $check->id)->firstorfail()->delete();
		}
		$model = new PropertyDetails();
		$model->property_id = $id;
		$model->allow_group_bookings = (isset($save['allowGroupBookings']))? $save['allowGroupBookings']:0;
		$model->children_allowed = (isset($save['childrenAllowed']))? $save['childrenAllowed']:0;
		$model->currency = (isset($save['currency']))? $save['currency']:'';
		$model->currency_symbol = (isset($save['currencySymbol']))? $save['currencySymbol']:'';
		$model->default_arrival_time = $save['defaultArrivalTime'];
		$model->default_depart_time = $save['defaultDepartTime'];
		$model->gateway_id = (isset($save['gatewayId']))? $save['gatewayId']:0;
		$model->latitude = (isset($save['latitude']))? $save['latitude']:'';
		$model->longitude = (isset($save['latitude']))? $save['longitude']:'';
		$model->max_child_age = (isset($save['maxChildAge']))? $save['maxChildAge']:'';
		$model->max_infant_age = (isset($save['maxInfantAge']))? $save['maxInfantAge']:'';
		$model->min_age_required_to_book = (isset($save['minAgeRequiredToBook']))? $save['minAgeRequiredToBook']:'';
		$model->pets_allowed = (isset($save['petsAllowed']))? $save['petsAllowed']:0;
		$model->redirection_url = (isset($save['redirectionURL']))? $save['redirectionURL']:'';
		$model->smoking_allowed = (isset($save['smokingAllowed']))? $save['smokingAllowed']:0;
		$model->max_group_bookings = (isset($save['maxGroupBookings']))? $save['maxGroupBookings']:'';
		$model->google_analytics_code = (isset($save['googleAnalyticsCode']))? $save['googleAnalyticsCode']:'';
		$model->save();

		return [
			'code' => 1,
			'status' => 'success',
			'message' => "Data Has Been Saved"
		];
	}

	public static function requestDetails($id, $dataToken)
	{
		$value = Cache::remember('property_setting_' . $id, 10 * 60, function () use ($id, $dataToken) {
            $endpoint = 'properties/' . $id . '/ibe/settings';
            $response = Http::withHeaders([
                'authToken' => $dataToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }
}