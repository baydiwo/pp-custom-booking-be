<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function getUser()
    {
        $query = User::get();
        foreach ($query as $q) {
            echo "<li>{$q->name}</li>";
        }
    }

    public function index()
    {
        $query = Cache::remember("user_all", 10 * 60, function () {
            return User::all();
        });

        foreach ($query as $q) {
            echo "<li>{$q->name}</li>";
        }
    }

    public function test()
    {
        $data = [
            'data' => [],
            "code" => "200",
            "messsage" => "Testing Only"
        ];

        return json_encode($data);
    }
}
