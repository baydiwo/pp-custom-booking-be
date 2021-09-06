<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\PropertyController;
use App\Models\ModelPropertyAvailability;
use App\Models\Property;
use Carbon\Carbon;
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

class PropertyAvailabilityJob implements ShouldQueue
{

    use InteractsWithQueue, Queueable, SerializesModels;
    protected $propertyId;
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
    public $tries = 2;
    public $timeout = 0;
    public function __construct($propertyId)
    {
        $this->propertyId  = $propertyId;
    }

    public function handle()
    {
		// ModelPropertyJob::truncate();
        $nextYear = Carbon::now()->addYear(2)->format('Y-m-d');
		$cDate = Carbon::now()->format('Y-m-d');
		
        $request       = new Request();
        $token         = new ApiController(NULL, $request);
        $dataToken     = $token->authToken();
        $api           = new ApiController($dataToken['token'], $request);
        $listAreasData = $api->listArea(env("PROPERTY_ID"));
        $listCategory  = collect($listAreasData)->where('inactive', false)->pluck('id');
		
		foreach($listCategory as $areaId)
		{
			$save = self::requestConcurrent(
				[$areaId],
				$cDate, 
				$nextYear,
				$dataToken['token']
			);
			
			$check = ModelPropertyAvailability::where('area_id', $areaId)
				->where('property_id', env("PROPERTY_ID"))
				->first();

			if($check) {
				ModelPropertyAvailability::where('id', $check->id)->firstorfail()->delete();
			}
			$model = new ModelPropertyAvailability();
			$model->property_id = env("PROPERTY_ID");
			$model->area_id = $areaId;
			$model->response = $save;
			$model->save();
			sleep(1);
		}

		return [
			'code' => 1,
			'status' => 'success',
			'message' => "Data Has Been Saved"
		];
	}

	public static function requestConcurrent($listArea, $from, $to, $dataToken)
	{
		$concurrent = 10;
		$client = new Client([
			'http_errors'     => false,
			'headers' => [
				'User-Agent' => 'Test/1.0',
				'authToken' => $dataToken,
			],
			"content-type" => 'application/json'
		]);
		$responses = collect();
		$endpoint = 'reservations/search?limit=1000&modelType=full&offset=0';
		$requests = function ($total) use ($dataToken, $listArea, $to, $from, $endpoint) {
			$uris = env('BASE_URL_RMS') . $endpoint;
			$paramMinNight = [
				"arriveFrom" => $from,
				"departTo" => $to,
				"listOfStatus" => [
								"confirmed",
								"maintenance",
								"unconfirmed",
								"pencil",
								"arrived",
								"departed",
								"owner"
								],
				"areaIds" => $listArea
			];

			yield new Psr7Request('POST', $uris, [
				'headers' => [
					'authToken' => $dataToken,
				],
				"content-type" => 'application/json'
			], json_encode($paramMinNight));
        };
        // wait on all of the requests to complete. Throws a ConnectException if any
        $pool = new Pool($client, $requests($concurrent), [
            'concurrency' => $concurrent,
            'fulfilled' => function ($response, $index) use ($responses) {
                $body = $response->getBody();

                // $content = $response->getReasonPhrase();
                $content = $body->getContents();
                $responses[$index] = $content;
            },
            'rejected' => function (ConnectException $reason, $index) use ($responses) {
                $body = $reason->getMessage();

                $responses[$index] = $body;
            },
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();
        // Force the pool of requests to complete.
        $promise->wait();

        return json_encode($responses);
    }
}