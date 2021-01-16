<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

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

    public function detail($id)
    {
        $api = new ApiController();
        $detailProperty = $api->detailProperty($id, $this->authToken);
        if (count($detailProperty) == 0) {
            throw new Exception(ucwords('Detail Property Not Found'));
        }

        $detailSetting = $api->detailPropertySetting($id, $this->authToken);
        if (isset($detailSetting['Message'])) {
            throw new Exception(ucwords($detailSetting['Message']));
        }

        $data = $detailProperty[0];
        $data['setting'] = $detailSetting;

        return [
            'code' => 1,
            'status' => 'success',
            'data' => $data
        ];
    }
}
