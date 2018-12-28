<?php

namespace Module\Http;

use Module\Http\exception\CurlHttpException;

class CurlHttpClient
{
    /**
     * The URI to request
     *
     * @var string
     */
    protected $full_url;

    /**
     * The scheme to use for the request, i.e. http, https
     *
     * @var string
     */
    protected $scheme;

    /**
     * Buffer for the HTTP request data
     *
     * @var string
     */
    protected $post_fields;

    /**
     * http headers
     *
     * @var array
     */
    protected $headers = [];
    protected $uri_no_func;
    protected $connect_timeout_ms;
    protected $timeout_ms;
    protected $method;
    protected $curl_handle;
    protected $response;
    protected $error_code;
    protected $cookies = [];
    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';

    public function __construct($config = [])
    {
        $http_option = [
            $config['connect_timeout'] = 10000,
            $config['timeout_ms'] = 10000
        ];
        $this->connect_timeout_ms = isset($config['connect_timeout']) ? $config['connect_timeout'] : ($http_option['connect_timeout'] ?? 10);
        $this->timeout_ms = isset($config['timeout_ms']) ? $config['timeout_ms'] : ($http_option['timeout_ms'] ?? 100) ;
    }

    /**
     * Set read timeout
     *
     * @param float $timeout
     */
    public function setTimeoutSecs($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Close the transport.
     */
    public function close()
    {
        $this->request = '';
        $this->response = null;
    }

    public function get($url, $get_arr, $options = [])
    {
        $arr = explode(':', $url);
        $scheme = 'https' === $arr[0] ? 'https' : 'http';
        $this->scheme = $scheme;
        $this->full_url = $url . '?' . http_build_query($get_arr);
        if (!empty($options['time']['connect_timeout_ms'])) {
            $this->connect_timeout_ms = $options['time']['connect_timeout'];
        }
        if (!empty($options['time']['timeout_ms'])) {
            $this->timeout_ms = $options['time']['timeout_ms'];
        }
        if (!empty($options['headers'])) {
            $this->headers = $options['headers'];
        }
        if (!empty($options['cookies'])) {
            $this->cookies = $options['cookies'];
        }
        $this->method = self::METHOD_GET;
        $this->flush();
        return $this->response;
    }

    public function post($url, $post_arr, $options = [])
    {
        $arr = explode(':', $url);
        $scheme = 'https' === $arr[0] ? 'https' : 'http';
        $this->scheme = $scheme;
        $this->full_url = $url;
        $this->post_fields = $post_arr;
        if (!empty($options['time']['connect_timeout_ms'])) {
            $this->connect_timeout_ms = $options['time']['connect_timeout'];
        }
        if (!empty($options['time']['timeout_ms'])) {
            $this->timeout_ms = $options['time']['timeout_ms'];
        }
        if (!empty($options['headers'])) {
            $this->headers = $options['headers'];
        }
        if (!empty($options['cookies'])) {
            $this->cookies = $options['cookies'];
        }
        $this->method = self::METHOD_POST;
        $this->flush();
        return $this->response;
    }

    /**
     * @param string $method
     * @param array $params
     * @param array $cookies
     * @throws CurlHttpException
     */
    public function flush()
    {
        register_shutdown_function([$this, 'closeCurlHandle']);
        $this->curl_handle = curl_init();
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl_handle, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($this->curl_handle, CURLOPT_USERAGENT, 'PHP/TCurlClient');
        if (self::METHOD_POST === $this->method) {
            curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'POST');
        }
        curl_setopt($this->curl_handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl_handle, CURLOPT_MAXREDIRS, 1);
        $cookie_set = false;
        $cookie_string = '';
        if (defined('SERVICE_FORCE_VERSION_KEY') && !empty($GLOBALS['GLOBAL_VERSION_VALUE_NO_PTEFIX'])) {
            $cookie_string .= SERVICE_FORCE_VERSION_KEY . '=' . $GLOBALS['GLOBAL_VERSION_VALUE_NO_PTEFIX'] . ';';
            $cookie_set = true;
        } elseif (!empty($cookies)) {
            $cookie_set = true;
        }
        if (true === $cookie_set) {
            foreach ($this->cookies as $key => $value) {
                $cookie_string .= $key . '=' . $value . ';';
            }
            curl_setopt($this->curl_handle, CURLOPT_COOKIE, $cookie_string);
        }
        // God, PHP really has some esoteric ways of doing simple things.
        $full_url = $this->full_url;
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[] = "$key: $value";
        }
        curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->curl_handle, CURLOPT_TIMEOUT_MS, $this->timeout_ms);
        curl_setopt($this->curl_handle, CURLOPT_CONNECTTIMEOUT_MS, $this->connect_timeout_ms);
        if (self::METHOD_POST === $this->method) {
            curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $this->post_fields);
        }
        curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
        curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYHOST, 0); // 检查证书中是否设置域名（为0也可以，就是连域名存在与否都不验证了）
        $this->post_fields = '';
        curl_setopt($this->curl_handle, CURLOPT_URL, $full_url);
        $this->response = curl_exec($this->curl_handle);
        $this->error_code = curl_errno($this->curl_handle);
        if(200 !== $this->error_code){
            $msg = curl_error($this->curl_handle).' ERROR_CODE:' .$this->error_code;
            error_log($msg, 3, "/path/error.log");
        }
        // Connect failed?
        if (!$this->response) {
            $msg = 'CurlHttpClient: Could not connect to ' . $full_url;
            error_log($msg, 3, "/path/error.log");
            curl_close($this->curl_handle);
            $this->curl_handle = null;
            throw new CurlHttpException($msg, CurlHttpException::NOT_OPEN);
        }
    }

    public function closeCurlHandle()
    {
        try {
            if ($this->curl_handle) {
                curl_close($this->curl_handle);
                $this->curl_handle = null;
            }
        } catch (\Exception $x) {
            error_log('There was an error closing the curl handle: ' . $x->getMessage());
        }
    }

    public function addHeaders($headers)
    {
        $this->headers = array_merge($this->headers, $headers);
    }
}
