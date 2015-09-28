<?php

/**
 * VKAPI class for vk.com social network
 *
 * @package server API methods
 * @link http://vk.com/developers.php
 * @autor Oleg Illarionov
 * @version 1.0
 */
class vkapi {

    var $api_secret;
    var $app_id;
    var $api_url;
    private $params;

    function vkapi($app_id, $api_secret, $api_url = 'api.vk.com/api.php') {
        $this->app_id = $app_id;
        $this->api_secret = $api_secret;
        if (!strstr($api_url, 'http://'))
            $api_url = 'http://' . $api_url;

        $this->api_url = "https://api.vk.com/method/%m%"; //$api_url;//api.vk.com/api.php
    }

    function api($method, $params = false) {
        if (!$params)
            $params = array();

        $this->params = $params;
        $params['api_id'] = $this->app_id;
        if (!$params['v'])
            $params['v'] = '5.36';
        $params['method'] = $method;
        $params['timestamp'] = time();
        $params['format'] = 'json';
        $params['random'] = rand(0, 10000);
        ksort($params);
        $sig = '';

        $url = str_replace("%m%", $method, $this->api_url);

        foreach ($params as $k => $v) {
            $sig .= $k . '=' . $v;
        }
        $sig .= $this->api_secret;
        $params['sig'] = md5($sig);
        $query = $url . '?' . $this->params($params);
        if (strlen($query) > 500) {
            $res = $this->curl_post($query);
        } else {
            $res = file_get_contents($query);
        }
        //dd($query, $res);
        return json_decode($res, true);
    }

    public function curl_post($url) {
        if (!function_exists('curl_init'))
            return false;

        $param = parse_url($url);

        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, $param['scheme'] . '://' . $param['host'] . $param['path']);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $param['query']);
            $out = curl_exec($curl);
            curl_close($curl);

            return $out;
        }

        return false;
    }

    function getParams() {
        return $this->params($this->params);
    }

    function params($params) {
        $pice = array();
        foreach ($params as $k => $v) {
            $pice[] = $k . '=' . urlencode($v);
        }
        return implode('&', $pice);
    }

}
