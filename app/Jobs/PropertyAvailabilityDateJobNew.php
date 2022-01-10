<?php
namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\PropertyAreaDetails;
use App\Models\AvailabilityDate;
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

class PropertyAvailabilityDateJobNew implements ShouldQueue
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
    public $tries = 1;
    public $timeout = 0;
    public function __construct($propertyId)
    {
        $this->propertyId  = $propertyId;
    }

    public function handle()
    {
        $nxtYear = Carbon::now()->addYear()->format('Y-m-d');
		$nextYear = Carbon::parse($nxtYear)->endOfMonth()->format('Y-m-d');
		$cDate = Carbon::now()->format('Y-m-01');
		$startDate = Carbon::createFromFormat('Y-m-d', $cDate)->addDays(-1)->format('Y-m-d');
        $dateInYear = $this->getDateInYear($startDate, $nextYear);
		$allGroupDate  = [];
        $thisDay = "";
		$idate = 0;

        $request       = new Request();
        $token         = new ApiController(NULL, $request);
        $dataToken     = $token->authToken();
        $api           = new ApiController($dataToken['token'], $request);

        $name = 'Night Direct';
		
		$save = self::requestConcurrent(
			$nextYear,
			$startDate,
			$dataToken['token']
		);

		$new = json_decode($save, true);
		$tempRate = [];
		$tempVal = [];
		$resultData = [];

		if(is_countable($new) && count($new) > 0)
		{
			foreach ($new as $valueDates)
			{
				$valueDates = json_decode($valueDates,true);
				foreach ($valueDates as $value)
				{
					foreach ($value as $data)
					{
						foreach($data['availability'] as $avail)
						{
							$model = AvailabilityDate::where('category_id', $data['id'])
														->where('date_from', $avail['theDate'])
														->first();
					
							if(!$model) {
								$model = new AvailabilityDate();
							}
							$model->category_id 	= $data['id'];
							$model->available_area 	= (int)$avail['count'];
							$model->date_from 		= $avail['theDate'];
							$model->created_date	= date('Y-m-d H:i:s');
							$model->save();
						}
					}
				}
			}
		}
		sleep(1);

		return [
			'code' => 1,
			'status' => 'success',
			'message' => "Data Has Been Saved"
		];
	}

	public static function requestConcurrent($to, $from, $dataToken)
	{
		$concurrent = 5;
		$client = new Client([
			'http_errors'     => false,
			'headers' => [
				'User-Agent' => 'Test/1.0',
				'authToken' => $dataToken,
			],
			"content-type" => 'application/json'
		]);
		$responses = collect();
		$endpoint = 'availabilityGrid';
		$requests = function ($total) use ($dataToken, $endpoint, $to, $from) {
			$uris = env('BASE_URL_RMS') . $endpoint;
                $paramMinNight = [					
					"propertyId" => 1,
					"dateFrom" => $from,
					"dateTo" => $to,
					"roomStatistics" => "ignore",
					"availabilityFilter" => "house"
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
}
