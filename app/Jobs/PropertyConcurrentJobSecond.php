<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\PropertyController;
use App\Models\ModelPropertyJob;
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

class PropertyConcurrentJobSecond implements ShouldQueue
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
    public $tries = 3;
    public $timeout = 0;
    public function __construct($propertyId)
    {
        $this->propertyId  = $propertyId;
    }

    public function handle()
    {
		// ModelPropertyJob::truncate();
        $nextYear = Carbon::now()->addMonths(8)->format('Y-m-d');
		$lastEndDate = Carbon::now()->addMonths(4)->format('Y-m-d');
		$cDate = Carbon::createFromFormat('Y-m-d', $lastEndDate)->addDays(1)->format('Y-m-d');
        $dateInYear = $this->getDateInYear($cDate, $nextYear);
		$allGroupDate  = [];
        $thisDay = "";
        foreach ($dateInYear as $valueDate) {
            //if($valueDate != "2021-12-31") {
                $thisDay = Carbon::parse($valueDate);
                $groupDate = [];
                for ($i=1; $i <= 7; $i++) {
                    $thisDay->addDays($i);
                    array_push($groupDate, $thisDay);
                    $thisDay = Carbon::parse($valueDate);
                }

                $allGroupDate[$valueDate] =  $groupDate;
            //}
        }

        $request       = new Request();
        $token         = new ApiController(NULL, $request);
        $dataToken     = $token->authToken();
        $api           = new ApiController($dataToken['token'], $request);
        $listAreasData = $api->listArea($this->propertyId);
        $listCategory  = collect($listAreasData)->where('inactive', false)->pluck('categoryId');
        $listRatesData = collect($api->listRates());

        $name = 'Night Direct';
        $filtered = $listRatesData->filter(function ($item) use ($name) {
            return false !== stripos($item['name'], $name);
        })->all();

        $listRates = collect($filtered)->pluck('id');


        foreach ($allGroupDate as $keyallGroupDate => $valueallGroupDate) {
			$save = self::requestConcurrent(
				$listCategory,
				$listRates,
				$valueallGroupDate,
				$keyallGroupDate,
				$dataToken['token']
			);
			
			$check = ModelPropertyJob::where('date_from', $keyallGroupDate)
				->where('property_id', env("PROPERTY_ID"))
				->first();

			if($check) {
				ModelPropertyJob::where('id', $check->id)->firstorfail()->delete();
			}
			$model = new ModelPropertyJob();
			$model->property_id = env("PROPERTY_ID");
			$model->date_from = $keyallGroupDate;
			$model->response = $save;
			$model->save();
			sleep(3);
		}

		return [
			'code' => 1,
			'status' => 'success',
			'message' => "Data Has Been Saved"
		];
	}

	public static function requestConcurrent($listCategory, $listArea, $to, $from, $dataToken)
	{
		$concurrent = 12;
		$client = new Client([
			'http_errors'     => false,
			'headers' => [
				'User-Agent' => 'Test/1.0',
				'authToken' => $dataToken,
			],
			"content-type" => 'application/json'
		]);
		$responses = collect();
		$endpoint = 'availabilityRateGrid';
		$requests = function ($total) use ($dataToken, $listCategory, $listArea, $endpoint, $to, $from) {
			$uris = env('BASE_URL_RMS') . $endpoint;
            foreach ($to as $key => $value) {
                $paramMinNight = [
                    'categoryIds' => $listCategory,
                    'dateTo'      => Carbon::parse($value)->format('Y-m-d'),
                    'dateFrom'    => $from,
                    'propertyId'  => 1,
                    'rateIds'     => $listArea
                ];

                yield new Psr7Request('POST', $uris, [
                    'headers' => [
                        'authToken' => $dataToken,
                    ],
                    "content-type" => 'application/json'
                ], json_encode($paramMinNight));
            }
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
    private function getDateInYear($first, $last, $step = '+1 day', $output_format = 'Y-m-d')
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

    public function rateByDate($dateFrom, $dateTo, $listRates)
    {
        $listRates = collect($listRates);
        $from = Carbon::parse($dateFrom);
        $diff = $from->diffInDays($dateTo);

        if ($diff == 1 || $diff == 2) {
            $rateId = $listRates->where('id', 12)->first();
        }

        if ($diff == 3) {
            $rateId = $listRates->where('id', 2)->first();
        }

        if ($diff == 4) {
            $rateId = $listRates->where('id', 3)->first();
        }

        if ($diff == 5) {
            $rateId = $listRates->where('id', 4)->first();
        }

        if ($diff == 6) {
            $rateId = $listRates->where('id', 5)->first();
        }

        if ($diff >= 7) {
            $rateId = $listRates->where('id', 6)->first();
        }

        return $rateId['id'];
    }
}
