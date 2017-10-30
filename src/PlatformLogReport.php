<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AccessLog\report;

use YII;
use yii\base\Event;

defined('GANDROID_ID') or define('GANDROID_ID', 1); //  android
defined('GIOS_ID') or define('GIOS_ID', 2); //  ios
defined('GOTHER_ID') or define('GOTHER_ID', 3); //pc
defined('GBROWSE_ID') or define('GBROWSE_ID', 4); //浏览器
defined('GROUTER_ID') or define('GROUTER_ID', 5); //路由

defined('WOMAN_ID') or define('WOMAN_ID', 1); //woman
defined('MAN_ID') or define('MAN_ID', 2); //man
defined('SEXUNUSUAL_ID') or define('SEXUNUSUAL_ID', 0); //unknown

class PlatformLogReport extends Event {

    private static $instance = null;

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new PlatformLogReport();
        }
        return self::$instance;
    }

    /**
     * 记录访问日志入口
     * @param type $actionEvent
     */
    public static function assign($actionEvent) {
        $action = $actionEvent->action;
        $controller = $action->controller;
        $module = $controller->module;
        $action_id = (isset($module->id) ? $module->id : 'default') . "/" . $controller->id . "/" . $action->id;
        $result = $actionEvent->result;
        $report = self::getInstance();
        $report->reportPrepare($action_id, $result);
    }

    /**
     * 获取终端类型
     * @return type
     */
    public function getDeviceType() {
        $origin = Yii::$app->request->getCookies()->getValue('origin', -1);
        $origin = ($origin > 0) ? $origin : Yii::$app->request->getQueryParam('origin', -1); //CUtil::getRequestParam('default', 'origin', -1);
        $origin = ($origin > 0) ? $origin : Yii::$app->request->getBodyParam('origin', -1);
        if ($origin < 0) {
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
                if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
                    $origin = GIOS_ID;
                } elseif (strpos($agent, 'android')) {
                    $origin = GANDROID_ID;
                } else {
                    $origin = GBROWSE_ID;
                }
            } else {
                $origin = GOTHER_ID;
            }
        }
        return $origin;
    }

    /**
     * 特殊状态码：供页面使用
     */
    const SPECIAL_STATUS_FOR_PAGE = -99999;

    /**
     * 获取用户IP，此函数不能再iis下工作，但是效率比较高
     * @return string user IP address
     */
    public function getUserHostAddressNoIIS() {
        $ip = '';
        switch (true) {
            case isset($_SERVER["HTTP_X_FORWARDED_FOR"]):
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
                break;
            case isset($_SERVER["HTTP_CLIENT_IP"]):
                $ip = $_SERVER["HTTP_CLIENT_IP"];
                break;
            default:
                $ip = $_SERVER["REMOTE_ADDR"] ? $_SERVER["REMOTE_ADDR"] : '127.0.0.1';
        }
        if (strpos($ip, ', ') > 0) {
            $ips = explode(', ', $ip);
            $ip = $ips[0];
        }
        return $ip;
    }

    public function getRequestType() {
        return \Yii::$app->request->method;
    }

    public function getReportCookies() {
        return Yii::$app->request->getCookies()->toArray();
    }

    /**
     * 
     * @return type 获取get参数
     */
    public function getReportGets() {
        $getsvalue = Yii::$app->request->get();
        $getsvalue['r_host'] = Yii::$app->request->hostInfo;
        $getsvalue['u_ip'] = $this->getUserHostAddressNoIIS();

        if ($getsvalue && count($getsvalue)) {
            unset($getsvalue['sign']);
            unset($getsvalue['sessionid']);
            unset($getsvalue['r']);
            unset($getsvalue['_csrf']);
        }
        return $getsvalue;
    }

    /**
     * 
     * @return type 获取post参数
     */
    public function getReportPosts() {
        $request = Yii::$app->request;
        $posts = $request->post();
        $postsvalue = [];
        if ($posts && count($posts)) {
            $postsvalue = $posts;
            unset($postsvalue['sessionid']);
            unset($postsvalue['sign']);
            unset($postsvalue['_csrf']);
        }
        return $postsvalue;
    }

    /**
     * 汇总log数据
     * @param type $action_id
     * @param type $result
     * @return type
     */
    public function getReportData($action_id, $result) {
        $status = self::SPECIAL_STATUS_FOR_PAGE;
        $report_result = '';
        try {
            $r = json_decode($result, true);
            if ($r && isset($r['iRet'])) {
                $status = $r['iRet'];
                $report_result = $result;
            } elseif (strlen($result) < 50) {
                $report_result = $result;
            }
        } catch (\Exception $ex) {
            //do nothing
        }

        $actionlog = [date("Y-m-d H:i:s")];
        $cookies = $this->getReportCookies();                                     //收集cookie
        $actionlog[] = (isset($cookies['userid'])) ? $cookies['userid'] : 0;    //访问Userid
        $actionlog[] = $action_id;                                              //action id
        $actionlog[] = $status;                                                 //返回码
        $actionlog[] = $this->getDeviceType();                                                 //终端类型
        $actionlog[] = time();
        $actionlog[] = microtime(TRUE) - YII_BEGIN_TIME;
        $actionlog[] = $this->getRequestType();
        $actionlog[] = json_encode($this->getReportPosts());
        $actionlog[] = json_encode($this->getReportGets());
        $actionlog[] = json_encode($cookies);
        $actionlog[] = is_array($report_result) ? json_encode($report_result) : $report_result;
        return $actionlog;
    }

    public function beforeReport($action_id, $result) {
        return true;
    }

    public function afterReport($actionlog) {
        return true;
    }

    public function reportPrepare($action_id, $result) {
        try {
            $this->beforeReport($action_id, $result);
            $actionlog = $this->getReportData($action_id, $result);
            $this->report($actionlog);
            $this->afterReport($actionlog);
        } catch (\Exception $exc) {
            Yii::error($exc->getMessage(), "log_report");
        }
    }

    /**
     * 记录访问日志
     * @return type log
     */
    public function report($actionlog) {
        if (!is_array($actionlog)) {
            throw new \Exception("action log must be a array!");
        }
        $logmsg = implode(chr(1), $actionlog);
        $file = $this->getPlatformlogFile();
        file_put_contents($file, $logmsg . "\n", FILE_APPEND);
        $this->afterReport($actionlog);
    }

    /**
     * 获得log记录文件
     * @param type $file_first_name
     * @return string
     */
    private function getPlatformlogFile($file_first_name = 'access') {
        $root = Yii::$app->getRuntimePath() . DIRECTORY_SEPARATOR . Yii::$app->id;
        if (!file_exists($root)) {
            mkdir($root, 0777, true);
        }
        $file = $root . "/{$file_first_name}.log." . date("Ymd");
        return $file;
    }

    /**
     * 获取服务器IP
     * @return type
     */
    private function getServerIp() {
        $ss = exec('/sbin/ifconfig eth0 | sed -n \'s/^ *.*addr:\\([0-9.]\\{7,\\}\\) .*$/\\1/p\'', $arr);
        $ret = $arr && is_array($arr) ? $arr[0] : '';
        return $ret;
    }

}
