<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class config {
    public static $baseurl = "https://buter.io/ai/";
    public static $scheme = "https";
    public static $host = "buter.io";
    public static $path = "/ai/";
    public static $datpath = "/home/www-data/buter.io/www/ai/appdat/";
    public static $pubpath = "/home/www-data/buter.io/www/ai/apppub/";
    public static $apikey = '';
    public static $openaikey = '';
    public static $elevenlabskey = '';
    public static $elevenlabsvoice = '';
 }
