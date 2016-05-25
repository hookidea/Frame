<?php
/**
 * @Name: frame.php
 * @Role:   框架入口文件
 * @Author: 拓少
 * @Date:   2015-10-15 16:54:05
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-07 22:17:48
 */

header('content-type:text/html;charset=UTF-8');

// 初始化目录位置

// 入口文件所在绝对路径
define('__APP__', $_SERVER['SCRIPT_NAME']);

// 入口文件所在目录
define('__ROOT__', str_replace('\\', '/', dirname(dirname(__FILE__))).'/');


//框架根目录
define('FRAME_PATH', str_replace('\\', '/', dirname(__FILE__)).'/');

//准备启动应用
include(FRAME_PATH . 'Common/runtime.php');


?>