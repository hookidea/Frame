<?php
/**
 * @Name: runtime.php
 * @Role:   框架初始化、配置环境
 * @Author: 拓少
 * @Date:   2015-10-15 18:44:32
 * @Last Modified by:   hookidea
 * @Last Modified time: 2016-04-24 23:19:29
 */

defined('FRAME_PATH')||exit('ACC Denied');

define('IS_CGI', substr(PHP_SAPI, 0,3)=='cgi' ? 1 : 0 );
define('IS_WIN', strstr(PHP_OS, 'WIN') ? 1 : 0 );
define('IS_CLI', PHP_SAPI=='cli'? 1 : 0);

defined('APP_DEBUG') or define(APP_DEBUG, false);

// 框架Common目录
defined('COMMON_PATH') or define('COMMON_PATH', FRAME_PATH . 'Common/');
// 框架Conf目录
defined('CONF_PATH') or define('CONF_PATH', FRAME_PATH . 'Conf/');
// 应用的Lib目录
defined('LIB_PATH') or define('LIB_PATH', FRAME_PATH . 'Lib/');
// 框架Core目录
defined('CORE_PATH') or define('CORE_PATH', LIB_PATH . 'Core/');
// 框架Tool目录
defined('TOOL_PATH') or define('TOOL_PATH', LIB_PATH . 'Tool/');
// 框架Tool目录
defined('DRIVER_PATH') or define('DRIVER_PATH', LIB_PATH . 'Driver/');
// 框架Tpl目录
defined('TPL_PATH') or define('TPL_PATH', FRAME_PATH . 'Tpl/');

// 判断是否提供应用名称
defined('APP_NAME') or exit('<font color="red">错误：你没有定义应用名称，应用启动失败！</font>');

// 计算应用路径
define('APP_PATH', __ROOT__ . APP_NAME . '/');
// 应用的Lib目录
defined('APP_LIB') or define('APP_LIB', APP_PATH . 'Lib/');
// 应用的Conf目录
defined('APP_CONF') or define('APP_CONF', APP_PATH . 'Conf/');
// 应用的Runtime目录
defined('APP_RUNTIME') or define('APP_RUNTIME', APP_PATH . 'Runtime/');
// 应用的Data目录
defined('APP_DATA') or define('APP_DATA', APP_RUNTIME . 'Data/');
// 应用的Model目录
defined('APP_MODEL') or define('APP_MODEL', APP_LIB . 'Model/');
// 应用的Action目录
defined('APP_MODULE') or define('APP_MODULE', APP_LIB . 'Action/');
// 应用的Tpl目录
defined('APP_TPL') or define('APP_TPL', APP_PATH . 'Tpl/');


// 加载运行必需文件
function load_runtime_file(){
	$list = array(
		COMMON_PATH . 'common.php',
		COMMON_PATH . 'functions.php'
		);
	for($i=0, $len=count($list); $i<$len; $i++)
		include $list[$i];
}

// 加载配置
function load_conf(){
	C(include CONF_PATH . 'convention.php');   	// 读取配置
	if (file_exists(APP_CONF . 'config.php')) C(include APP_CONF . 'config.php');
}

load_runtime_file(); // 加载运行必需文件
load_conf();         // 加载框架核心 + 共有配置，当前分组的配置在路由中加载
spl_autoload_register('autoloadfile');

// 设置包含目录（类所在的全部目录）,  PATH_SEPARATOR 分隔符号 Linux(:) Windows(;)
$include_path = get_include_path();              // 原基目录
$include_path .= PATH_SEPARATOR.CORE_PATH;       // 框架中基类所在的目录
$include_path .= PATH_SEPARATOR.TOOL_PATH;       // 框架中扩展类的目录
$include_path .= PATH_SEPARATOR.DRIVER_PATH.'Db/';       // 框架中Db驱动
$include_path .= PATH_SEPARATOR.DRIVER_PATH.'Session/';       // 框架中Session驱动
// 设置include包含文件所在的所有目录	
set_include_path($include_path);

// //判断是否需要转义$_GET\$_POST\$_COOKIE
// if (false === get_magic_quotes_gpc()) {
// 	$GET = _addslashes($_GET);
// 	$POST = _addslashes($_POST);
// 	$COOKIE = _addslashes($_COOKIE);
// }

// 部署目录
function mk_dir(){
	$files = array(
		APP_PATH,
		APP_CONF,
		APP_LIB,
		APP_MODULE,
		APP_MODEL,
		APP_TPL,
		APP_RUNTIME
		);
	for($i=0, $len=count($files); $i<$len; $i++) {
		mkdir($files[$i], 0777, true);
	}
	copy(TPL_PATH . 'default_index.tpl', APP_MODULE . 'IndexAction.class.php');
}

if (!file_exists(APP_RUNTIME))  mk_dir(); // 判断是否需要部署目录

Frame::start();

 ?>