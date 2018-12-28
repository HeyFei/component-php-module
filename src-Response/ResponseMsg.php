<?php

namespace Module\Response;

class ResponseMsg
{
    public $is_support_jsonp = false;

    public $header_list = [];

    private static $default_header_list = [];

    public function __construct()
    {
        if ('cli' !== php_sapi_name()){
            $this->header_list = self::$default_header_list;
            $this->putCrossHeader();
        }
    }

    public function putCrossHeader()
    {
        $allow_list = ['http://www.google.com', 'http://www.yahoo.com'];
        $origin = $_SERVER['Origin'];
        if (!in_array($origin, $allow_list)) {
            $origin = implode(',', $allow_list);
        }
        $this->header('Access-Control-Allow-Origin', $origin);
        $this->header('Access-Control-Allow-Methods', 'POST,GET,OPTIONS');
        $this->header('Access-Control-Allow-Credentials', 'true');
        $this->header('Access-Control-Allow-Headers', 'Authorization,Content-Type,Content-Length');
    }

    public static function setDefaultHeader($default_header_list)
    {
        foreach ($default_header_list as $key => $header) {
            self::$default_header_list[$key] = $header;
        }
    }

    public static function getDefaultHeader()
    {
        return self::$default_header_list;
    }

    public function arrSuccess($data = BaseConstant::OK, $code = 200)
    {
        return [BaseConstant::ERROR => false, BaseConstant::MESSAGE => $data, BaseConstant::CODE => $code];
    }

    public function arrFail($data, $code = -1)
    {
        return [BaseConstant::ERROR => true, BaseConstant::MESSAGE => $data, BaseConstant::CODE => $code];
    }

    /**
     * 失败返回接口
     * @param string $msg
     * @param int $code
     * @return string
     */
    public function jsonError($msg = '', $code = -1)
    {
        if (empty($msg)) {
            $msg = 'unknown error';
        }
        $view = [
            BaseConstant::CODE => $code,
            BaseConstant::MESSAGE => $msg,
        ];
        $json = json_encode($view);
        return $this->dumpJsonData($json);
    }

    /**
     * 成功返回接口
     * @param string $msg
     * @param int $code
     * @return string
     */
    public function jsonSuccess($data = '', $code = 200)
    {
        $view = [
            BaseConstant::CODE => $code,
            BaseConstant::MESSAGE => BaseConstant::OK,
            BaseConstant::DATA => $data
        ];
        $json = json_encode($view);
        return $this->dumpJsonData($json);
    }

    /**
     * 直接处理接口数据
     * @param $ret
     */
    public function dealRet($ret)
    {
        if (true === $ret[BaseConstant::ERROR]) {
            $this->jsonError($ret[BaseConstant::MESSAGE] ? : 'unknown error');
        } else {
            $this->jsonSuccess($ret[BaseConstant::MESSAGE] ? : BaseConstant::OK);
        }
    }

    /**
     * 根据是否为JSONP做特殊处理输出
     * @param $json
     * @return string
     */
    public function dumpJsonData($json)
    {
        $callback = '';
        if (true === $this->is_support_jsonp) {
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/javascript');
            }
            $callback_key = 'jsonpcallback';
            $callback = $_GET[$callback_key];
            if ($callback) {
                $callback = htmlspecialchars($callback_key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
                $json = $callback . '(' . $json . ')';
            }
        }
        if (!$callback && !$this->isDebug()) {
            $this->header('Content-type', 'application/json');
        }
        return $json;
    }

    /**
     * @param $json_str
     * @param string $callback_key
     * @return string
     */
    public function printByJson($json_str, $callback_key = '')
    {
        $callback = '';
        if ($callback_key) {
            $callback = $_GET[$callback_key] ?? '';
        }
        if ($callback) {
            $callback = htmlspecialchars($callback_key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/javascript');
            }
            return $callback . '(' . $json_str . ')';
        } else {
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/json');
            }
            return $json_str;
        }
    }

    /**
     * @param $arr
     * @param string $callback_key
     * @return string
     */
    public function printByArr($arr, $callback_key = '')
    {
        $callback = '';
        if ($callback_key) {
            $callback = $_GET[$callback_key] ?? '';
        }
        if ($callback) {
            $callback = htmlspecialchars($callback_key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/javascript');
            }
            return $callback . '(' . json_encode($arr) . ')';
        } else {
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/json');
            }
            return json_encode($arr);
        }
    }

    public function printOldFail($code, $code_msg, $detail_code, $detail_msg, $callback_key = '')
    {
        $this->putCrossHeader();
        $callback = '';
        if ($callback_key) {
            $callback = $_GET[$callback_key] ?? '';
        }
        $arr = ['code' => $code, 'error' => $code_msg, 'ecode' => $detail_code, 'message' => $detail_msg, 'data' => []];
        if ($callback) {
            $callback = htmlspecialchars($callback_key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/javascript');
            }
            return $callback . '(' . json_encode($arr) . ')';
        } else {
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/json');
            }
            return json_encode($arr);
        }
    }

    /**
     * @param $success_data
     * @param string $callback_key
     * @return string
     */
    public function printOldSuccess($success_data, $callback_key = '')
    {
        $this->putCrossHeader();
        $callback = '';
        if ($callback_key) {
            $callback = $_GET[$callback_key] ?? '';
        }
        $arr = ['code' => 200, 'ecode' => 200, 'error' => 'OK', 'message' => 'OK', 'data' => $success_data];
        if ($callback) {
            $callback = htmlspecialchars($callback_key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/javascript');
            }
            return $callback . '(' . json_encode($arr) . ')';
        } else {
            if (!$this->isDebug()) {
                $this->header('Content-type', 'application/json');
            }
            return json_encode($arr);
        }
    }

    /**
     * 解决xdebug cookie设置不了的问题
     */
    private function isDebug()
    {
        if (defined('SERVICE_ENV') && (SERVICE_ENV === 'test' || SERVICE_ENV === 'local') && isset($_GET['debug'])) {
            return true;
        }
        return false;
    }

    public function header($key, $value)
    {
        $this->header_list[$key] = $value;
    }

    public function getHeaders()
    {
        return $this->header_list;
    }
}
