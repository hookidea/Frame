<?php
/**
 * @Name: Frame.class.php
 * @Role:   框架核心文件
 * @Author: xunzeo
 * @Date:   2015-10-17 14:08:36
 * @Last Modified by:   hookidea
 * @Last Modified time: 2016-04-24 23:19:36
 */

defined('FRAME_PATH')||exit('ACC Denied');

class Frame
{
	/**
	 * 启动框架
	 */
	static public function start()
    {
		error_reporting(1);// 屏蔽PHP自带的错误提示，然后接管处理
		
		// 在错误退出前调用的方法
		register_shutdown_function(array(__CLASS__, 'fatalError'));
		// 接管PHP错误处理机制
		set_error_handler(array(__CLASS__, 'appError'), E_ALL);
		
		App::run(); // 启动应用
	
		if (APP_DEBUG && C('SHOW_PAGE_TRACE')) trace();  // 开启显示trace
	}

	/**
	 * 不会导致脚本停止运行的错误
	 * @param  number $errno   错误代码
	 * @param  string $errstr  错误信息
	 * @param  string $errfile 错误文件
	 * @param  number $errline 错误行数
	 */
	static public function appError($errno, $errstr, $errfile, $errline)
    {
		$type = array('1'=>'E_ERROR', '2'=>'E_WARNING', '4'=>'E_PARSE', '8'=>'E_NOTICE', '16'=>'E_CORE_ERROR', '32'=>'E_CORE_WARNING', '64'=>'E_COMPILE_ERROR', '128'=>'E_COMPILE_WARNING', '256'=>'E_USER_ERROR', '512'=>'E_USER_WARNING', '1024'=>'E_USER_NOTICE', '2048'=>'E_STRICT', '4096'=>'E_RECOVERABLE_ERROR', '8192'=>'E_DEPRECATED', '16384'=>'E_USER_DEPRECATED');
		if ($errno == E_NOTICE || $errno == E_USER_NOTICE) {
			$color="#000088";
		} else {
			$color="red";
		}
		$mess = "<font color=\"{$color}\"><b>{$type[$errno]}</b>&nbsp;[在文件 {$errfile} 中,第 $errline 行]：{$errstr}</font>";
  		trace($mess, 1);
	}

	/**
	 * 能够导致脚本停止运行的致命错误
	 */
	static public function fatalError()
    {
		if ($e = error_get_last()) {
			$type = array('1'=>'E_ERROR', '2'=>'E_WARNING', '4'=>'E_PARSE', '8'=>'E_NOTICE', '16'=>'E_CORE_ERROR', '32'=>'E_CORE_WARNING', '64'=>'E_COMPILE_ERROR', '128'=>'E_COMPILE_WARNING', '256'=>'E_USER_ERROR', '512'=>'E_USER_WARNING', '1024'=>'E_USER_NOTICE', '2048'=>'E_STRICT', '4096'=>'E_RECOVERABLE_ERROR', '8192'=>'E_DEPRECATED', '16384'=>'E_USER_DEPRECATED');
			if (C('LOG_RECORD')) {  // 记录错误日志
	    		Log::record($e['message'], $e['type']);
	    		Log::save();
	    	}
	    	if (C('SHOW_PAGE_TRACE')) {
		   		$mess = "<font color=\"red\"><b>{$type[$e['type']]}</b>&nbsp;[在文件 {$e['file']} 中,第 {$e['line']} 行]：{$e['message']}</font>";
		   		trace($mess, 1, true);
	    	}
            exit;
        }
	}
}

















 ?>