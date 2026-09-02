<?php

namespace app\controllers;

class HomeController extends Controller
{
    public function index()
    {
        echo $this->view('home', [
            'app_name' => env('APP_NAME', 'PHP MVC'),
            'year' => date('Y'),
        ]);
    }
}
