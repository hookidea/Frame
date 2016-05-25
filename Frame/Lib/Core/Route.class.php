<?php
/**
 * @Name: Route.class.php
 * @Role: 路由解析类 
 * @Author: xunzeo
 * @Date:   2015-10-15 16:54:05
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-16 17:42:51
 */

defined('FRAME_PATH')||exit('ACC Denied');

class Route
{
	/**
	 * 启动路由解析
	 */
	static public function run()
    {
		$groups = C('APP_GROUP_LIST_');
		$pathinfo = isset($_SERVER['PATH_INFO']) ? strtolower(substr($_SERVER['PATH_INFO'], 1)) : '';
		$default_module = C('DEFAULT_MODULE');
		$default_action = C('DEFAULT_ACTIOM');
		$var_module = C('VAR_MODULE');
		$var_action = C('VAR_ACTION');
		$model_layer = C('DEFAULT_C_LAYER');

		if (isset($_GET[$var_module]) || isset($_GET[$var_action])) { // 使用 m=User&a=add
			$module = isset($_GET[$var_module]) ? $_GET[$var_module] . $model_layer : $default_module . $model_layer;
			$action = isset($_GET[$var_action]) ? $_GET[$var_action] : $default_action;
			// unset($_GET[$var_module]);
			// unset($_GET[$var_action]);

		}elseif (!empty($pathinfo)) {
			$arr = explode('/', $pathinfo);
			$_GET[C('VAR_URL_PARAMS')] = $arr; // 把info保存到$_GET[C('VAR_URL_PARAMS')]，以便访问

			if (isset($arr[0]) && in_array($arr[0], array_map('strtolower', C('APP_GROUP_LIST')))) {
				define('GROUP_NAME', ucwords($arr[0]));
				unset($arr[0]);  // *
				$arr = array_merge($arr);
			} else {
				define('GROUP_NAME', strtolower(C('DEFAULT_GROUP')));
			}

			// 加载当前分组的配置
			$conf_file = APP_CONF . GROUP_NAME . '/' . 'config.php';
			if (file_exists($conf_file)) C(include $conf_file);

			if (C('URL_ROUTER_ON')) {      // 如果开启路由模式
				self::checkUrlMatch($arr); // 开启解析路由进程
			}

			$module = !empty($arr[0]) ? ucwords($arr[0]) . $model_layer : $default_module . $model_layer;
			$action = !empty($arr[1]) ? $arr[1] : $default_action;

			if (($len = count($arr)) > 2) { // 把在“模块和方法”后面的都传入到$_GET
				for($i=2; $i<$len; $i+=2){
					$_GET[$arr[$i]] = $arr[$i+1];
				}
			}
		} else { // 没有传
			$module = $default_module . $model_layer;
			$action = $default_action;
		}
		// 检测
		self::check($module, $action); // 如果通不过检测，直接在函数内部退出
		// 定义常量（不能在上面定义，因为URL中的模块/方法不一定就是有效的，比如最后可能是"Index/index"）
		define('MODULE_NAME', $module); // 当前模块(Action)
		define('ACTION_NAME', $action); // 当前方法

	}

	/**
	 * 判断模块Action的文件是否存在，类、类的方法是否已经声明
	 * @param  string $module 模块名
	 * @param  string $action 方法名
	 * @return true           true:检测成功；检测不成功，直接exit
	 */
	static public function check($module, $action)
    {
		// 判断模块文件是否存在
		if (!file_exists(APP_LIB . C('DEFAULT_C_LAYER') . '/' . GROUP_NAME . '/' . $module . '.class.php')) {
			if (!file_exists(APP_LIB . C('DEFAULT_C_LAYER') . '/'  . $module . '.class.php')) {
				halt('错误模块：模块 <span style="color: red; font-weight: bold;">' . $module . '</span> 的文件不存在');
			}
			
		}
		// 在模块存在的情况下，类已经定义
		if (class_exists($module)) {
			if (method_exists($module, $action)) {
				return true;
			} else {
				halt('错误操作：操作  <span style="color: red; font-weight: bold;">' . $action . '</span> 不存在');
			}
		} else { // 在模块存在的情况下，类没有定义
			halt('错误模块：模块  <span style="color: red; font-weight: bold;">' . $module . '</span> 未定义');
		}
	}

	/**
	 * 负责调度对应的路由处理函数进行重定向
	 * @return false/无      如果为false，说明不匹配，如果匹配通过，直接重定向
	 */
	static public function checkUrlMatch($pathinfo)
    {
		$route_rules = C('URL_ROUTE_RULES'); // 获取规则+路由
		foreach($route_rules as $k=>$v){
			if (!preg_match('/^\/.*\/$/', $k)) { // 普通路由模式
				return self::parseRule($k, $v, $pathinfo);
			} else { // 正则路由模式
				return self::parseRegex($k, $v, $pathinfo);
			}
		}
	}

	/**
	 * 普通路由匹配
	 * @param  string  $rule      普通匹配规则
	 * @param  string  $route     重定向地址
	 * @param  string  $pathinfo  
	 * @return false/无       如果为false，说明不匹配，如果匹配通过，直接重定向
	 * 注意：在 $route 中不能够有 ":" 号（正则匹配的可以）
	 */
	static public function parseRule($rule, $route, $pathinfo)
	{
		$get = array();
		$rule_arr = explode('/', strtolower($rule));
		for ($i=0, $len=count($rule_arr); $i<$len; $i++) {
			if (strpos($rule_arr[$i], ':') !== false) {
				if (($pos1 = strpos($rule_arr[$i], '\\')) !== false) {
					if (!is_numeric($pathinfo[$i])) {
						return false;
					}
					$var = substr($rule_arr[$i], 1, $pos1 - strlen($rule_arr[$i]));
				} else {
					$var = substr($rule_arr[$i], 1);
				}

				if (($pos2 = strpos($var, '^')) !== false) {
					$qian = substr($var, 0, $pos2);
					$hou = substr($var, $pos2+1);
					$tmp_arr = explode('|', $hou);
					$flag = false;
					for ($x=0, $len_x=count($tmp_arr); $x<$len_x; $x++) {
						if( $pathinfo[$i] === $qian . $tmp_arr[$x] ) {
							$flag = true;
						}
					}
					if (!$flag) {
						return false;
					}
				} else {
					$get[$var] = $pathinfo[$i];
				}
				
			} else {
				if ($rule_arr[$i] !== $pathinfo[$i]) {
					return false;
				}
			}
		}
		$url = self::_parseRoute($route, $get);
		header("location: {$url}");
	}

	/**
	 * 正则路由匹配
	 * @param  string  $rule  正则匹配规则
	 * @param  string  $route 重定向地址
	 * @return false/无       如果为false，说明不匹配，如果匹配通过，直接重定向
	 * 注意：在定义路由规则时，规则里的分组个数 = 动态变量名的个数（必须）
	 */
	static public function parseRegex($rule, $route, $pathinfo)
    {
    	if (preg_match($rule, trim($_SERVER['PATH_INFO'], '/'), $matches)) {
    		array_shift($matches);
    		$url = self::_parseRoute($route, $matches);
    		header("location: {$url}");
    	}
	}

	/**
	 * 解析URL路由中 路由规则部分
	 * @param  string $route 一条路由
	 * @param  array  $get   URL地址中的参数集合
	 * @return string        URL
	 */
	static private function _parseRoute($route, $get)
	{
		if (is_array($route)) {
			$route = $route[0] . '/' . $route[1];
			$route = trim($route, '/');
			$route = str_replace(array('&', '='), '/', $route);
		} elseif (is_string($route) && (strtolower(substr(ltrim($route), 0, 4)) == 'http')) { // 外部URL
			return $route;
		} else {
			$tmp = explode('?', trim($route, '/'));
			if (isset($tmp[1]) && !empty($tmp[1])) {
				$route = $tmp[0] . '/' . str_replace(array('&', '='), '/', $tmp[1]);
			}
		}
		$route_arr = explode('/', $route);
		$values = array_values($get);
		for ($y=0, $len_y=count($route_arr); $y<$len_y; $y++) {
			if (strpos($route_arr[$y], ':') !== false) {
				$key = substr($route_arr[$y], 1)-1;
				$route = str_replace($route_arr[$y], $values[$key], $route);
				unset($get[array_keys($get)[$key]]);
			}
		}
		$url = __APP__ . '/' . $route;
		return $url;
	}


}






