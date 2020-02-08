#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php

if ($argc !== 5) {
    echo 'badparam';
    exit();
}

$cf = new updateCFDDNS($argv);
$cf->makeUpdateDNS();

/**
 * DDNS auto updater for Synology NAS
 * Base on Cloudflare API v4
 * Supports multidomains and sundomains 
 */
class updateCFDDNS
{
    const API_URL = 'https://api.cloudflare.com';
    var $account, $apiKey, $hostList, $ip;

    function __construct($argv)
    {
        if (count($argv) != 5) {
            $this->badParam();
        }

        $this->account = (string) $argv[1];
        $this->apiKey = (string) $argv[2]; // CF Global API Key
        $hostname = (string) $argv[3]; //example: example.com---sundomain.example1.com---example2.com
        $this->ip = (string) $argv[4];

        $this->validateIpV4($this->ip);

        $arHost = explode('---', $hostname);
        if (empty($arHost)) {
            $this->badParam('empty host list');
        }

        foreach ($arHost as $value) {
            $arParam = $this->getHostnameFromStr($value);
            if ($arParam['success']) {
                $this->hostList[$arParam['fullname']] = [
                    'hostname' => $arParam['hostname'],
                    'fullname' => $arParam['fullname'],
                    'zoneId' => '',
                    'recordId' => '',
                    'proxied' => true,
                ];
            }
        }

        $this->setZones();
        foreach ($this->hostList as $arHost) {
            $this->setRecord($arHost['fullname'], $arHost['zoneId']);
        }
    }
    /**
     * Update CF DNS records  
     */
    function makeUpdateDNS()
    {
        $url = "https://api.cloudflare.com/client/v4/zones/${zoneID}/dns_records/$recordID";
        foreach ($this->hostList as $arHost) {
            $post = [
                'type' => 'A',
                'name' => $arHost['fullname'],
                'content' => $this->ip,
                'ttl' => 1,
                'proxied' => $arHost['proxied'],
            ];

            $json = $this->callApiCFPOST("client/v4/zones/" . $arHost['zoneId'] . "/dns_records/" . $arHost['recordId'], $post);
            if (!$json['success']) {
                echo 'Update Record failed';
                exit();
            }
        }
        printf("good");
    }

    function badParam($msg = '')
    {
        echo (strlen($msg) > 0) ? $msg : 'badparam';
        exit();
    }

    function validateIpV4($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->badParam('invalid ip-address, only ipv4');
        }
        return true;
    }
    /**
     * Get hostname (without subdomain) and fullname (with subdomain if exist)
     * @return ['success' => true/false, 'hostname' => '', 'fullname' => '']
     */
    function getHostnameFromStr($str)
    {
        $ar = explode('.', $str);
        $res = ['success' => true, 'hostname' => '', 'fullname' => ''];
        if (count($ar) == 1) {
            $res['success'] = false;
        }
        if (count($ar) > 1) {
            $res['hostname'] = $ar[count($ar) - 2] . '.' . $ar[count($ar) - 1];
            $res['fullname'] = $str;
        }
        return $res;
    }

    /**
     * Set ZoneID for each hosts
     */
    function setZones()
    {
        $json = $this->callCFApiGET('client/v4/zones');
        if (!$json['success']) {
            $this->badParam('getZone unsuccessful response');
        }
        $res = [];
        foreach ($json['result'] as $ar) {
            $res[$ar['name']] = $ar['id'];
        }

        foreach ($this->hostList as $arHost) {
            if ($res[$arHost['hostname']]) {
                $this->hostList[$arHost['fullname']]['zoneId'] = $res[$arHost['hostname']];
            }
        }
    }

    /**
     * Set Records for each hosts
     */
    function setRecord($fullname, $zoneId)
    {
        if (empty($fullname)) {
            return false;
        }

        if (empty($zoneId)) {
            unset($this->hostList[$fullname]);
            return false;
        }

        $json = $this->callCFApiGET("client/v4/zones/${zoneId}/dns_records?type=A&name=${fullname}");
        if (!$json['success']) {
            $this->badParam('unsuccessful response for getRecord host: ' . $fullname);
        }
        $this->hostList[$fullname]['recordId'] = $json['result']['0']['id'];
        $this->hostList[$fullname]['proxied'] = $json['result']['0']['proxied'];
    }

    function callCFApiGET($path)
    {
        $req = curl_init();
        $options = array(
            CURLOPT_URL => self::API_URL . '/' . $path,
            CURLOPT_HTTPGET => true,
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array("X-Auth-Email: $this->account", "X-Auth-Key: $this->apiKey", "Content-Type: application/json")
        );

        curl_setopt_array($req, $options);
        $res = curl_exec($req);
        curl_close($req);
        return json_decode($res, true);
    }

    function callApiCFPOST($path, $data)
    {
        $req = curl_init();
        $options = array(
            CURLOPT_URL => self::API_URL . '/' . $path,
            CURLOPT_HTTPGET => false,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array("X-Auth-Email: $this->account", "X-Auth-Key: $this->apiKey", "Content-Type: application/json"),
            CURLOPT_POST => false,
            CURLOPT_POSTFIELDS => json_encode($data)
        );

        curl_setopt_array($req, $options);
        $res = curl_exec($req);
        curl_close($req);
        return json_decode($res, true);
    }
}
?>