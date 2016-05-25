<?php
/**
 * @Name: App.class.php
 * @Role:   应用类
 * @Author: 拓少
 * @Date:   2015-10-15 16:54:05
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-16 12:24:33
 */

defined('FRAME_PATH')||exit('ACC Denied');

class App
{
	// 启动应用前的初始化
	static public function init()
    {
		// 页面压缩输出支持
        if (C('OUTPUT_ENCODE')) {
        	// 获取是否已默认在配置文件中开启缓存压缩
            $zlib = ini_get('zlib.output_compression');
            if (empty($zlib)) ob_start('ob_gzhandler'); // 如果没有在配置文件开启默认压缩，则手动开启
        }

        if (C('SESSION_AUTO_START')) {  // 自动开启session
        	$s_type = strtolower(C('SESSION_TYPE'));
        	if ($s_type === 'db')
        		DBSession::init(Db::getIns());
        	elseif ($s_type === 'memcache') {
        		$mem = new CacheMemcached;
        		MemSession::init($mem->getMem());
        	} else {
        		session_start();
        	}
        }

		// 设置默认时区
		date_default_timezone_set(C('DEFAULT_TIMEZONE'));
		tag('route_begin');
		// URL解析
		Route::run();
		tag('route_end');

		// 定义当前请求的系统常量
        define('NOW_TIME',      $_SERVER['REQUEST_TIME']);
        define('REQUEST_METHOD',$_SERVER['REQUEST_METHOD']);
        define('IS_GET',        REQUEST_METHOD =='GET' ? true : false);
        define('IS_POST',       REQUEST_METHOD =='POST' ? true : false);
        define('IS_PUT',        REQUEST_METHOD =='PUT' ? true : false);
        define('IS_DELETE',     REQUEST_METHOD =='DELETE' ? true : false);
        define('IS_AJAX',       ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || !empty($_POST[C('VAR_AJAX_SUBMIT')]) || !empty($_GET[C('VAR_AJAX_SUBMIT')])) ? true : false);

		// 有__*__的都是相对于网站文档根目录的路径，其他的都是绝对路径
		// define('__ROOT__', dirname($_SERVER['SCRIPT_NAME']) . '/');  // 网站根目录地址
		// define('__APP__', $_SERVER['SCRIPT_NAME']);   // 当前项目（入口文件）地址
		define('__ACTION__', __APP__ . substr(MODULE_NAME, 0, -6) . '/' . ACTION_NAME);
		define('__URL__', __APP__ . substr(MODULE_NAME, 0, -6));  // 当前模块的URL地址 
		define('__SELF__', $_SERVER['REQUEST_URI']); // 当前URL地址
        // 当前的PATH_INFO字符串
        define('__INFO__', isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] . '/' : ''); 
		define('__TMPL__', __ROOT__ . APP_NAME . '/Tpl/'); // 当前的PATH_INFO字符串

		// 系统变量安全过滤
        if (C('VAR_FILTERS')) {
            $filters    =   explode(',', C('VAR_FILTERS'));
            foreach($filters as $filter){
                // 全局参数过滤
                array_walk_recursive($_POST, $filter);
                array_walk_recursive($_GET, $filter);
            }
        }

        if (C('REQUEST_VARS_FILTER')) {
			// 全局安全过滤表达式(在符合要求的值后面添加一个空格)
			array_walk_recursive($_GET,		'frame_filter');
			array_walk_recursive($_POST,	'frame_filter');
			array_walk_recursive($_REQUEST,	'frame_filter');
		}

        return;

	}

	// 执行应用
	static public function exec()
    {
		$module  = MODULE_NAME; // 当前模块名
		$action = ACTION_NAME;  // 当前操作名
		$_module = new $module();
		$method = new ReflectionMethod($module, $action);
		try {
			if ($method->ispublic()) {
				$class = new ReflectionClass($module);
				// 前置操作
				$_before = '_before_' . $action;
				if ($class->hasMethod($_before)) {
					$before = $class->getMethod($_after);
					if ($before->ispublic()) {
						$before->invokeArgs($module);
					}
				}
				if (C('URL_PARAMS_BIND') && $method->getNumberOfParameters() > 0) {
                // 开启了绑定，且方法参数个数 > 0
					switch($_SERVER['REQUEST_METHOD']) { // 获取请求方法，post,get,put
                        case 'POST':
                            $vars    =  array_merge($_GET,$_POST);
                            break;
                        case 'PUT':
                            parse_str(file_get_contents('php://input'), $vars);
                            break;
                        default:
                            $vars  =  $_GET;
                    }
					$params = $method->getParameters();
					foreach($params as $param){ // getParameters()方法获得的参数列表本身就是有序的
						$name = $param->getName(); // 获取参数的名字
						if (isset($vars[$name])) { // 判断是否给参数绑定了值
							$arr[] = $vars[$name];
						} elseif ($param->isDefaultValueAvailable()) { // 判断参数是否有默认值
							$arr[] = $param->getDefaultValue(); // 获取参数的默认值
						}
						// 下面应该还有一种情况：如果开启了绑定传参，结果没有给参数赋值，且参数还没有默认值
					}
					$method->invokeArgs($_module,$arr); // 最终实现的参数方式还是索引数组传参，而不是关联数组；
					// 如果在$vars中有找到与参数同名的，则把值赋予给这个参数；如果找不到，则去找默认的参数值，然后把这个值赋予给这个参数；如果此时函数没有默认值，而又没有赋予其值，那就报错了
				} else {//普通不带参数运行软件
					$_module->$action();
				}
				// 后置操作
				$_after = '_after_' . $action;
				if ($class->hasMethod($_after)) {
					$after = $class->getMethod($_after);
					if ($after->ispublic()) {
						$after->invokeArgs($module);
					}
				}
			} else {
				halt('操作方法不是Public，不能调用！');
			}
		} catch (ReflectionException $e) {
			$method = new ReflectionMethod($module, '__call');
			$method->invokeArgs($module, array($action,''));
		}
		return;
	}

	//启动应用
	static public function run()
    {
		tag('app_init');
		self::init();
		tag('app_begin');
		self::exec();
		tag('app_end');
	}
}







