<?php

namespace FastElephant\Printer;

use GuzzleHttp\Client as HttpClient;

class SftClient
{
    /**
     * 请求值
     * @var array
     */
    protected $request = [];

    /**
     * 返回值
     * @var array
     */
    protected $response = [];

    /**
     * 映射编号
     * @var
     */
    protected $bizOpenid;

    /**
     * @param string $bizOpenid
     */
    public function __construct(string $bizOpenid = '')
    {
        $this->bizOpenid = $bizOpenid;
    }

    /**
     * @return array
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * @param string $path
     * @param array $param
     * @return array
     */
    public function call(string $path, array $param = []): array
    {
        $apiUrl = config('sft.url') . $path;

        $param['biz_code'] = config('sft.biz_code');
        $param['version'] = config('sft.version');
        $param['biz_openid'] = $this->bizOpenid;
        $param['timestamp'] = time();
        $param['sign'] = $this->makeSign(config('sft.biz_code'), config('sft.secret'), $param, $param['timestamp']);

        $client = new HttpClient(['verify' => false, 'timeout' => config('sft.timeout')]);

        $this->request = $param;

        $startTime = $this->millisecond();

        try {
            $strResponse = $client->post($apiUrl, ['json' => $this->request])->getBody()->getContents();
        } catch (\Exception $e) {
            $strResponse = $e->getMessage();
            return ['code' => 550, 'msg' => $strResponse];
        } finally {
            $expendTime = intval($this->millisecond() - $startTime);
            $this->monitorProcess($path, json_encode($this->request, JSON_UNESCAPED_UNICODE), $strResponse, $expendTime);
        }

        if (!$strResponse) {
            return ['code' => 555, 'msg' => '响应值为空', 'request_id' => ''];
        }

        $arrResponse = json_decode($strResponse, true);
        if (!$arrResponse) {
            return ['code' => 555, 'msg' => '响应值格式错误', 'request_id' => ''];
        }

        $this->response = $arrResponse;
        if ($arrResponse['code'] != 0) {
            return ['code' => $arrResponse['code'], 'msg' => $arrResponse['msg'], 'request_id' => $arrResponse['request_id']];
        }

        return ['code' => 0, 'result' => $arrResponse['result'], 'request_id' => $arrResponse['request_id']];
    }

    /**
     * 监控请求过程（交给子类实现）
     * @param $path
     * @param $strRequest
     * @param $strResponse
     * @param $expendTime
     */
    public function monitorProcess($path, $strRequest, $strResponse, $expendTime)
    {
    }

    /**
     * 获取当前时间毫秒时间戳
     * @return float
     */
    protected function millisecond()
    {
        list($mSec, $sec) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($mSec) + floatval($sec)) * 1000);
    }

    /**
     * 签名
     * @param $code
     * @param $secret
     * @param $param
     * @param $time
     * @return string
     */
    protected function makeSign($code, $secret, $param, $time): string
    {
        $tmpArr = array(
            "biz_code" => $code,
            "timestamp" => $time,
        );

        foreach ($param as $k => $v) {
            $tmpArr[$k] = $v;
        }

        ksort($tmpArr);

        $str = $secret;

        foreach ($tmpArr as $k => $v) {
            if ($v === false) {
                $v = 'false';
            }
            if ($v === true) {
                $v = 'true';
            }
            if (empty($v) && $v != 0) {
                continue;
            }
            $str .= $k . $v;
        }

        $signature = sha1($str);
        return strtolower($signature);
    }
}
