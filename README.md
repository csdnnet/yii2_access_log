# yii2_access_log
yii2 framework access log module
v1.0.2
Yii2 框架集成日志上报组件

1、composer安装第三方扩展：composer require cornivy/yii2_access_log:"dev-master"
2、项目配置文件中components新增配置： 'on afterAction' => '\AccessLog\report\PlatformLogReport::assign'

附：如果要进行扩展，可继承	\AccessLog\report\PlatformLogReport 类，复写相应扩展，并修改components中'on afterAction'配置

扩展文件上报，使用\AccessLog\report\PlatformLogReport接口

补充上报接口：通过API上报终端数据，例子就是ReportController.php 有两种接口
1、即时上报统计：actionActionReport，即直接上报数据，通过日志上报组件配合收集上报内容 
2、延时文件统计上报：actionReport，即间接上报数据，终端通过统计一定日志后，再通过API上报数据，通过日志上报组件收集上报内容
整个统计逻辑可以参照ReportController.php


```php

namespace app\controllers;

use Yii;
use yii\web\Controller;

/**
 * Description of ReportController
 *
 * @author cornivy
 */
class ReportController extends CMyController {

    /**
     * 
     * @return type action完整ID，包含module、controller
     */
    public function getActionFullId() {
        return $this->module->id . "/" . $this->id . "/" . $this->action->id;
    }

    /**
     * 页面统计
     *
     * @return string
     */
    public function actionActionReport() {
        return json_encode(['iRet' => 0]);
    }

    /**
     * 文件统计上报接口
     * @return type
     */
    public function actionReport() {
        if (Yii::$app->request->isPost) {
            $report = \AccessLog\report\PlatformLogReport::getInstance();
            $action_id = $this->getActionFullId();
            $msg = $report->getReportData($action_id, '');

            try {
                $file = \yii\web\UploadedFile::getInstanceByName('_UPLOADFILE_');
                if ($file && $file->tempName) {
                    $lines = $this->count_line($file->tempName);
                    if ($file->size < 64 * 1024) {
                        $fp = new \SplFileObject($file->tempName, "rb");
                        for ($i = 0; $i < $lines; ++$i) {
                            $txt = $fp->current();
                            $cont_array = json_decode($txt, TRUE); // current()获取当前行内容 
                            if (isset($cont_array['___t'])) {
                                $msg[5] = $cont_array['___t'];
                            } else {
                                $msg[5] = time();
                            }
                            $msg[8] = ($cont_array && count($cont_array)) ? json_encode($cont_array) : []; //收集_POST
                            $report->report($msg);
                            $fp->next(); // 下一行
                        }
                        return json_encode(['iRet' => 0, 'sMsg' => 'OK']);
                    } else {
                        return json_encode(['iRet' => -1, 'sMsg' => 'file size illegal']);
                    }
                } else {
                    return json_encode(['iRet' => -1, 'sMsg' => 'file error']);
                }
            } catch (\Exception $ex) {
                return json_encode(['iRet' => -99, 'sMsg' => 'parse file error']);
            }
        }
        return json_encode(['iRet' => -99, 'sMsg' => 'upload file method error']);
    }

}



```
