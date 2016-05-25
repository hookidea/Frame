<?php
/**
 * @Name: Action.class.php
 * @Role:  Action基类
 * @Author: xunzeo
 * @Date:   2015-10-15 16:57:57
 * @Last Modified by:   hookidea
 * @Last Modified time: 2016-04-24 23:25:01
 */

defined('FRAME_PATH')||exit('ACC Denied');

class Action
{
	// 保存模板类
	private $tpl = null;

	public function __construct()
    {
		$this->tpl = new Tpl();
	}

	/**
	 * 显示模板
	 * @param  $template        模板名称，可选，默认显示与操作名称一致的模板
	 */
	public function display($template=ACTION_NAME, $char='', $type='')
    {
		$this->tpl->display($template, $char, $type);
	}

	/**
	 * 显示与操作名称一致的模板
	 */
	public function show($char='', $type='')
    {
		$this->tpl->display(ACTION_NAME, $char, $type);
	}

	/**
	 * 赋值到模板变量
	 * @param  string $key   变量名
	 * @param  mixed  $value 值
	 */
	public function assign($key, $value)
    {
		$this->tpl->assign($key, $value);
	}

	/**
	 * 转换数据格式，方便Ajax方式返回数据到客户端
	 * @param  array   $data    数据
	 * @param  num     $status  状态码
	 * @param  string  $info    信息说明
	 * @param  string  $type    要把数据转换成什么格式：JSON,XML
	 * @return                  转化后的数据：XML，JSON，直接输出
	 */
	public function ajaxReturn($data, $status=1, $info='', $type='')
    {
		$result  =  array();
        $result['status']  =  $status;
        $result['info'] =  $info;
        $result['data'] = $data;
		$type = empty($type) ? C('DEFAULT_AJAX_RETURN') : $type;
		if (strtoupper($type) === 'JSON') {
			// 返回JSON数据格式到客户端 包含状态信息
            header("Content-Type:text/html; charset=utf-8");
            exit(json_encode($result));
		}
		if (strtoupper($type) === 'XML') {
			// 返回xml格式数据
            header("Content-Type:text/xml; charset=utf-8");
            exit(arr2xml($result));
		}
	}

	public function redirect($param='', $arr=null, $waitSecond=2, $message='页面跳转中...')
    {
		$url = U($param, $arr);
		redirect($url, $waitSecond, $message);
	}
	
	/**
	 * 成功跳转
	 * @param  string  $message    提示信息
	 * @param  string  $jumpUrl    可选，跳转到。。默认返回上一页
	 * @param  integer $waitSecond 等待秒数，默认2秒
	 */
	public function success($message='操作成功！', $jumpUrl='', $waitSecond=2)
    {
		$this->dispatchJump($message, 1, $jumpUrl, $waitSecond);
	}
	
	/**
	 * 错误跳转
	 * @param  string  $message    提示信息
	 * @param  string  $jumpUrl    可选，跳转到。。默认返回上一页
	 * @param  integer $waitSecond 等待秒数，默认2秒
	 */
	public function error($message='操作失败！', $jumpUrl='',$waitSecond=2)
    {
		$this->dispatchJump($message, 0, $jumpUrl, $waitSecond);
	}

	/**
	 * 成功、错误跳转的核心函数
	 * @param  string  $message    提示信息
	 * @param  integer $status     状态码
	 * @param  string  $jumpUrl    可选，跳转到。。默认返回上一页
	 * @param  integer $waitSecond 等待秒数，默认2秒
	 */
	public function dispatchJump($message, $status=1, $jumpUrl='', $waitSecond=2)
    {
		if (empty($jumpUrl))
			$jumpUrl = 'javascript:history.back();';  // 返回上一页
		else 
			$jumpUrl = U($jumpUrl);      // 跳到指定页面
		include(C('TMPL_ACTION_SUCCESS'));
	}

	/**
	 * 魔术方法
	 * @param  string $method 调用的方法名
	 * @param  array  $attr   传递给方法的参数数组
	 * @return mixed          根据调用方法不同，返回值不同
	 */
	public function __call($method, $attr)
    {
		$filter = isset($attr[1]) ? $attr[1] : C('DEFAULT_FILTER'); // 获取过滤函数
		if ($filter === false) $filter = null;      // 如果第二个参数为false，则不过滤
		$value = isset($attr[2]) ? $attr[2] : null; // 是否传有默认值，没有则为null 
		switch (strtolower($method)) {
			case 'isget':
			case 'ispost':
			case 'ishead':
			case 'isput': return strtolower($_SERVER['REQUEST_METHOD']) == strtolower(substr($method, 2));
			case '_get': return isset($_GET[$attr[0]]) ? $filter($_GET[$attr[0]]) : $value;
			case '_post': return isset($_POST[$attr[0]]) ? $filter($_POST[$attr[0]]) : $value;
			case '_request': return isset($_REQUEST[$attr[0]]) ? $filter($_REQUEST[$attr[0]]) : $value;
			case '_session': return isset($_SESSION[$attr[0]]) ? $filter($_SESSION[$attr[0]]) : $value;
			case '_cookie': return isset($_COOKIE[$attr[0]]) ? $filter($_COOKIE[$attr[0]]) : $value;
			case '_server': return isset($_SERVER[$attr[0]]) ? $filter($_SERVER[$attr[0]]) : $value;
			case '_globals': return isset($GLOBALS[$attr[0]]) ? $filter($GLOBALS[$attr[0]]) : $value;
			case '_param':
				if (is_numeric($attr[0])) {
					return $_GET[C('VAR_URL_PARAMS')][$attr[0]];
				}
				$name = '_' . strtolower($_SERVER['REQUEST_METHOD']);
			 	return $this->$name($attr[0], $filter, $value);
				break;
		}
	}



	
}



