# yii2_access_log
yii2 framework access log module
Yii2 框架集成日志上报组件

1、composer安装第三方扩展：composer require cornivy/yii2_access_log:"dev-master"
2、项目配置文件中components新增配置： 'on afterAction' => '\AccessLog\report\PlatformLogReport::assign'

附：如果要进行扩展，可继承	\AccessLog\report\PlatformLogReport 类，复写相应扩展，并修改components中'on afterAction'配置

扩展文件上报，使用\AccessLog\report\PlatformLogReport接口

补充上报接口：通过API上报终端数据，例子就是ReportController.php 有两种接口
1、即时上报统计：actionActionReport，即直接上报数据，通过日志上报组件配合收集上报内容 
2、延时文件统计上报：actionReport，即间接上报数据，终端通过统计一定日志后，再通过API上报数据，通过日志上报组件收集上报内容
整个统计逻辑可以参照ReportController.php

