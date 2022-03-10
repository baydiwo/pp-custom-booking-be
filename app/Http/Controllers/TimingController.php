<?php

namespace App\Http\Controllers;

use App\Models\SessionDetails;
use App\Models\ModelTiming;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\Types\This;

class TimingController extends Controller
{
    private $authToken;
    private $request;
    private $params;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function index()
    {
      return view('timing.index');
    }

    public function getTiming(Request $request)
    {
		$booking_id = $request->booking_id;
		$timing_data = ModelTiming::select('pencil_created','expiry_date','unconfirmed_status','reservation_update','guest_token','txn_receipt','confirmed_status')->where('booking_id',$booking_id)->orderBy('id', 'ASC')->first();
		$data['booking_id'] = $request->booking_id;
		if($timing_data)
		{
			$data['pencil_created'] = $timing_data['pencil_created'];
			$data['expiry_date'] = $timing_data['expiry_date'];
			$data['unconfirmed_status'] = $timing_data['unconfirmed_status'];
			$data['reservation_update'] = $timing_data['reservation_update'];
			$data['guest_token'] = $timing_data['guest_token'];
			$data['txn_receipt'] = $timing_data['txn_receipt'];
			$data['confirmed_status'] = $timing_data['confirmed_status'];
		}
		else
		{
			$data['pencil_created'] = '';
			$data['expiry_date'] = '';
			$data['unconfirmed_status'] = '';
			$data['reservation_update'] = '';
			$data['guest_token'] = '';
			$data['txn_receipt'] = '';
			$data['confirmed_status'] = '';
		}
		return $data;
    }
}