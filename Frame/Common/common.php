<?php
/**
 * @Name: common.php
 * @Role:   框架核心函数库
 * @Author: 拓少
 * @Date:   2015-10-17 11:47:36
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-16 20:08:28
 */

defined('FRAME_PATH')||exit('ACC Denied');

/**
 * 输出用户的操作错误（如：操作不存在、、、）
 * @param string/array  $error  错误信息
 */
function halt($error) {
    $e = array();
    if (APP_DEBUG) { // 调试模式下输出错误信息
        if (!is_array($error)) { // 不是数组
            $trace = debug_backtrace();  // 产生一条回溯跟踪(backtrace)
            $e['message'] = $error;
            $e['file'] = $trace[0]['file'];   // 最新的错误项数越小，相反，最旧的错误项数越大
            $e['line'] = $trace[0]['line'];
            ob_start();
            debug_print_backtrace(); // 打印一条回溯
            $e['trace'] = ob_get_clean();
        }else { // 是数组
            $e = $error;
        }
    } else {
        // 否则定向到错误页面
        $error_page = C('ERROR_PAGE');
        if (!empty($error_page)) {
            redirect($error_page);
        }else {
            if (C('SHOW_ERROR_MSG'))
                $e['message'] = is_array($error) ? $error['message'] : $error;
            else
                $e['message'] = C('ERROR_MESSAGE');
        }
    }
    // 包含异常页面模板
    include C('TMPL_EXCEPTION_FILE');
    exit;
}

/**
 * 输出trace页面调试消息
 * @param  string  $msg  要添加调试的信息
 * @param  string  $type 信息类型，0：SQL语句，1：错误信息，2：运行信息
 * @param  boolean $view 是否在添加的同时显示调试页面（一般用于输出致命错误时）
 */
function trace($msg='', $type='', $view=false){
	static $sqls = array();  // 保存 SQL信息
	static $infos = array(); // 保存错误信息
	static $runs = array();  // 保存运行信息，如：详细运行时间
	if (!empty($msg)) {      // 有参数，说明是添加调试信息
		if (defined("APP_DEBUG") && APP_DEBUG==1) {
			switch ($type) {
				case 0:   // SQL语句
					$sqls[] = $msg;
					break;
				case 1:   // 错误信息
					$infos[] = $msg;
					break;
				case 2:   // 运行信息
					$runs[] = $msg;
					break;
			}
		}	
		if (!$view) return;  // 是否同时显示，不显示，则return
	}
	// 来到这里，说明是输出调试信息
	$config = C(array('SHOW_PAGE_TRACE', 'SHOW_USE_MEM', 'SHOW_LOAD_FILE', 'SHOW_DB_TIMES', 'SHOW_ADV_TIME', 'SHOW_FUN_TIMES', 'SHOW_RUN_TIME', 'SHOW_DEBUG'), true);

	echo '<div style="float:left;clear:both;text-align:left;font-size:11px;color:#888;width:95%;margin:10px;padding:10px;background:#F5F5F5;border:1px dotted #778855;z-index:100">';
	echo '<div style="float:left;width:100%;"><span style="float:left;width:200px;"><b>运行信息</b>';
	if ($config['SHOW_RUN_TIME']) echo '( <font color="red">'. G('appStart', 'appEnd') .' </font>秒):</span>';
	echo '<span onclick="this.parentNode.parentNode.style.display=\'none\'" style="cursor:pointer;width:25px;background:#500;border:1px solid #555;color:white;position:absolute;right:60px;";>关闭</span></div><br>';
	echo '<ul style="margin:0px;padding:0 10px 0 10px;list-style:none">';

	if ($config['SHOW_USE_MEM']){ // 是否显示内存占用
		echo '<br>［内存占用］<li>&nbsp;&nbsp;&nbsp;&nbsp;' . tosize(memory_get_usage()) . '</li>';
    }
	if ($config['SHOW_LOAD_FILE']) { // 是否显示文件包含
		$files = get_included_files(); // 获取所有被包含的文件
		if (count($files) > 0) {
			echo '<br>［自动包含］';
			for ($i=0, $len=count($files); $i<$len; $i++) {
				echo '<li>&nbsp;&nbsp;&nbsp;&nbsp;'.$files[$i].'</li>';
            }
		}
	}
	if ($config['SHOW_DEBUG']) { // 是否显示错误信息
		if (count($infos) > 0 ) {
			echo '<br>［错误信息］';
			foreach ($infos as $info) {
				echo '<li>&nbsp;&nbsp;&nbsp;&nbsp;<font color="red">' . $info . '</font></li>';
            }
		}
	}
	if ($config['SHOW_DB_TIMES']) { // 是否显示数据库查询
		if (count($sqls) > 0) {
			echo '<br>［SQL语句］';
			foreach ($sqls as $sql) {
				echo '<li>&nbsp;&nbsp;&nbsp;&nbsp;' . $sql . '</li>';
            }
		}
	}
	if ($config['SHOW_ADV_TIME']) { // 是否显示详细时间
		if (count($runs) > 0) {
			echo '<br>［运行时间］';
			foreach ($runs as $run) {
				echo '<li>&nbsp;&nbsp;&nbsp;&nbsp;' . $run . '</li>';
            }
		}
	}
	if ($config['SHOW_FUN_TIMES']) { // 是否显示函数调用
		$funcs = get_defined_functions();
		$str = '<br>［函数调用］<br>&nbsp;&nbsp;&nbsp;&nbsp;';
		foreach ($funcs['user'] as $row) {
			$str .= $row . ', ';
        }
		echo substr($str, 0, -1);
	}
	echo '</ul>';
	echo '</div>';	
}

/**
 * 实例化一个Model
 * @param string $name 要实例化的Model名
 */
function D($name){
	$name = $name . C('DEFAULT_M_LAYER');
	if (!class_exists($name)) {
        $name = 'Model';
    }
	return new $name();
}


/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 * @param string $name 字符串
 * @param integer $type 转换类型
 * @return string
 */
function parse_name($name, $type=0) {
    if ($type) {
        return ucfirst(preg_replace("/_([a-zA-Z])/e", "strtoupper('\\1')", $name));
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

/**
 * 获取模版文件     格式：分组/模块/操作
 * @param  string $name  模版资源地址
 * @return string
 */
function T($template='') {
    $arr = explode('/', $template);
    $suffix = C('TMPL_TEMPLATE_SUFFIX');
    switch (count($arr)) {
        case 3:
            $path = APP_TPL . $arr[0] . '/' . ucwords($arr[1]) . '/' . ucwords($arr[2]) . $suffix;
            break;
        case 2:
            $path = APP_TPL . GROUP_NAME . '/' . ucwords($arr[0]) . '/' . ucwords($arr[1]) . $suffix;
            break;
        case 1:
            $path = APP_TPL . GROUP_NAME . '/' . ACTION_NAME . '/' . ucwords($arr[0]) . $suffix;
            break;
        default:
            exit('输入有误！');
            break;
    }
    return $path;
}

/**
 * 获取输入参数，支持过滤和默认值
 * 使用方法:
 * <code>
 *     I('id', 0); 获取id参数 自动判断get或者post
 *     I('post.name', '', 'htmlspecialchars'); 获取$_POST['name']
 *     I('get.name'); 获取$_GET
 * </code> 
 * @param  string $name    变量的名称 支持指定类型
 * @param  mixed  $default 不存在的时候默认值
 * @param  mixed  $filter  参数过滤方法
 * @return mixed
 */
function I($name, $default='', $filter=null) {
    $arr = explode('.', $name);
    if (count($arr) == 2) {
        switch (strtolower($arr[0])) {
            case 'get': $input = $_GET; break;
            case 'post': $input = $_POST; break;
            case 'request': $input = $_REQUEST; break;
            case 'session': $input = $_SESSION; break;
            case 'cookie': $input = $_COOKIE; break;
            case 'env': $input = $_ENV; break;
            case 'server': $input = $_SERVER; break;
            case 'globals': $input = $GLOBALS; break;
            default: return NULL;
        }
        if (!isset($input[$arr[1]])) return $default;
        $value = $input[$arr[1]];
    } elseif (count($arr) == 1) {
        if (isset($_GET[$name])) {
            $value = $_GET[$name];
        } elseif (isset($_POST[$name])) {
            $value = $_POST[$name];
        } else {
            $value = $default;
        }
    } else {
        exit('I函数参数输入有误！');
    }
    if (!is_null($filter)) $value = $filter($value);
    return $value;
}

/**
 * 调用模块（Action）的方法
 * @param  string $method 模块名action/方法名
 * @param  array  $args   要被传入回调函数作为参数的数组，这个数组得是索引数组
 * @param  string $layer  控制层名称/自定义控制器路径
 * @return                返回回调函数的结果。如果出错的话就返回FALSE
 */
function R($method, $args=array(), $layer='') {
	$tmp = explode('/', $method);
	$action = A($tmp[0], $layer);
	return call_user_func_array(array($action, $tmp[1]), $args);
}

// 读取、设置 配置项
function C($name='', $value='') {
	// 保存所有的配置
	static $_config = array();
	if (is_string($name)) {
		// 获取配置值
		if (empty($value)) {
			$name = strtoupper($name);
			if (array_key_exists($name, $_config)) {
				return $_config[$name];
			} else { // 如果不存在的配置项
				return null;
			}
			
		}
		// 增加单个配置
		$_config[strtolower($name)] = $value;
	}
	// 批量设置
	if (is_array($name) && empty($value)) {
		$_config = array_merge($_config, array_change_key_case($name, CASE_UPPER));
	}
	if (is_array($name) && true===$value) { // 提供一个要获取的项的集合的索引数组，获取配置值
		$return = array();
		for ($i=0, $len=count($name); $i<$len; $i++) {
			$tmp = strtoupper($name[$i]);
			$return[$tmp] = $_config[$tmp];
		}
		return $return;
	}
}

/**
 * 实例化一个Model对象
 * @param  string $name  模块器名称(无需后戳)
 * @param  string $layer 模块层名称/自定义模块路径
 * @return object        Model对象
 */
function M($name='', $layer='') {
	$model_layer = C('DEFAULT_M_LAYER');
	if (empty($layer)) {
		$name = ucwords($name) . $model_layer;
	} else { // 控制器分层
		if (is_file($layer)) { // 如果$layer是一个路径，则认为是自定义模块路径
			include($layer);
			return new $name;
		}
		$layer = ucwords($layer);
		$name = ucwords($name) . $layer;
        $file = APP_LIB . $layer . '/' . GROUP_NAME . '/' . $name . '.class.php';
        if (!file_exists($file)) {
            $file = APP_LIB . $layer . '/' . $name . '.class.php';
        }
		include($file);
	}
	return new $name();
}

/**
 * 用以作类的自动加载函数
 * @param  string $class 类名
 */
function autoloadfile($class) { // DEFAULT_M_LAYER//DEFAULT_C_LAYER
	$model_layer = C('DEFAULT_M_LAYER');
	$action_layer = C('DEFAULT_C_LAYER');
	$m_len = strlen($model_layer);
	$a_len = strlen($action_layer);
	if (substr($class, -$m_len) == $model_layer && strlen($class) > $m_len) {
		$path = APP_LIB . $model_layer . '/' . GROUP_NAME . '/' . $class . '.class.php';
        if (!file_exists($path))  $path = APP_LIB . $model_layer . '/' . $class . '.class.php';
	} elseif (substr($class, -$a_len) == $action_layer && strlen($class) > $a_len) {
        $path = APP_LIB . $action_layer . '/' . GROUP_NAME . '/' . $class . '.class.php';
        if (!file_exists($path))  $path = APP_LIB . $action_layer . '/' . $class . '.class.php';
	} else {
		$path = $class . '.class.php';
	}
    include($path);
}

/**
 * URL生成，该功能支持ThinkPHP中的该功能所有方法
 * @param string        $param 	 '模块/操作/参数1/值1...?参数' 
 * @param string/array  $arr     '参数1=值1&参数2=值2'  或 array('参1'=>'值1','参2'=>'值2')
 */
function U($param='', $arr=null){
	if (empty($param)) { // 如果都为空，则取当前的模块名和操作名
		return __APP__ . '/' . substr(MODULE_NAME,0,-6) . '/' . ACTION_NAME; 
	}
	$get = array(); // 保存所有的参数 
	$tmp = explode('?', $param);
	if (count($tmp) > 1) { // 如果第1参数中有通过"?"的方法设置参数
		$tmp2 = explode('&', $tmp[1]);
		for ($i=0,$len=count($tmp2); $i<$len; $i++) {
			$tmp3 = explode('=', $tmp2[$i]);
			if (count($tmp3) > 1) {
				$get[$tmp3[0]] = $tmp3[1];
			}
		}
	}
	$tmp4 = explode('/', $tmp[0]);
	if (count($tmp4) == 2) {
		$module = $tmp4[0];
		$action = $tmp4[1];
	} elseif (count($tmp4) == 1) {
		$module = MODULE_NAME;
		$action = $tmp4[0];
	}
	if (is_array($arr)) { // 如果有参数作为数组传递过来
		$get = array_merge($get, $arr);
	}
	if (is_string($arr) && !empty($arr)) { // 如果有参数作为字符串传递过来
		$tmp5 = explode('&', $arr);
		for ($m=0, $len=count($tmp5); $m<$len; $m++) {
			$tmp6 = explode('=', $tmp5[$m]);
			$get[$tmp6[0]] = $tmp6[1];
		}
	}
	switch(C('URL_MODEL')){
		case 0: // 普通模式 index.php?m=Blog&a=read&id=1
			if (empty($get)) {
                return __APP__ . '?' . C('VAR_MODULE') . '=' . $module . '&' . C('VAR_ACTION') . '=' . $action;
            }
			$query = http_build_query($get); // 编译url中的query
			return  __APP__ . '?' . C('VAR_MODULE') . '=' . $module . '&' . C('VAR_ACTION') . '=' . $action . '&' . $query;
			break;
		case 1: // pathinfo模式
			if (empty($get)) {
                return __APP__ . '/' . $module . '/' . $action;
            }
			$query = str_replace('=', '/', str_replace('&', '/', http_build_query($get))); // 替换"=","&" 为 '/'
			return __APP__ . '/' . $module . '/' . $action . '/' . $query;
			break;
		case 2:
			if (empty($get)) {
                return dirname(__APP__) . '/' . $module . '/' . $action;
            }
			$query = str_replace('=', '/', str_replace('&', '/', http_build_query($get))); // 替换"=","&" 为 '/'
			if (C('URL_HTML_SUFFIX')) {
                $query .= '.' . C('URL_HTML_SUFFIX');
            }
			return dirname(__APP__) . '/' . $module . '/' . $action . $query;
			break;
	}
}

/**
 * 实例化一个Action
 * @param string $name  控制器名称(无需后戳)
 * @param string $layer 控制层名称/自定义控制器路径
 * @return              控制器对象              
 * 
 */
function A($name, $layer=''){
	$action_layer = C('DEFAULT_C_LAYER');
	if (empty($layer)) {
		$name = ucwords($name) . $action_layer;
	} else {//控制器分层
		if (is_file($layer)) {//如果$layer是一个路径，则认为是自定义控制器路径
			include($layer);
			return new $name;
		}
		$layer = ucwords($layer);
		$name = ucwords($name) . $layer;
		$file = APP_LIB . $layer . '/' . $name . '.class.php';
		include($file);
	}
	return new $name();
}

/**
 * 处理标签扩展
 * @param string $tag 标签名称
 */
function tag($tag) {
    if (APP_DEBUG||C('LOG_RECORD')||C('SHOW_PAGE_TRACE')) {
    	$arr = array_change_key_case(explode('_', $tag), CASE_LOWER);
    	if ('begin' == $arr[1]) {
    		G($arr[0].'Start');
	        trace('[ '.$tag.' ] --START--', 2);
    	} elseif ('end' == $arr[1]) {
    		G($arr[0].'End');
        	trace('[ '.$tag.' ] --END-- [ RunTime:'.G($arr[0].'Start', $arr[0].'End', 6).'s ]', 2);
    	}
    }
}

/**
 * 记录和统计时间（微秒）和内存使用情况
 * 使用方法:
 * <code>
 * G('begin'); // 记录开始标记位
 * // ... 区间运行代码
 * G('end'); // 记录结束标签位
 * echo G('begin','end',6); // 统计区间运行时间 精确到小数后6位
 * echo G('begin','end','m'); // 统计区间内存使用情况
 * 如果end标记位没有定义，则会自动以当前作为标记位
 * 其中统计内存使用需要 MEMORY_LIMIT_ON 常量为true才有效
 * </code>
 * @param string $start 开始标签
 * @param string $end 结束标签
 * @param integer|string $dec 小数位或者m 
 * @return mixed
 */
function G($start='', $end='', $dec=4) { // G($tag.'Start',$tag.'End',6)
    static $_info = array();
    static $_mem = array();
    if (empty($start)) { // 获取所有运行时间统计
        return $_info; 
    }
    if (is_float($end)) { // 记录时间(手动传递时间)
        $_info[$start]  =   $end;
    } elseif (!empty($end)) { // 统计时间和内存使用
        if (!isset($_info[$end])) {
            $_info[$end] = microtime(TRUE);
        }
        if (C('MEMORY_LIMIT_ON') && $dec=='m') {
            if (!isset($_mem[$end])) {
                $_mem[$end] = memory_get_usage();
            }
            return number_format(($_mem[$end]-$_mem[$start])/1024);          
        } else {
            return number_format(($_info[$end]-$_info[$start]), $dec);
        }       
            
    } else { // 记录时间和内存使用
        $_info[$start] = microtime(TRUE);
        if (C('MEMORY_LIMIT_ON')) {
            $_mem[$start] = memory_get_usage();
        }
    }
}


 ?>