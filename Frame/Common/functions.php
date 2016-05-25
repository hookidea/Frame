<?php
/**
 * @Name: functions.php
 * @Role:   框架基础函数库
 * @Author: 拓少
 * @Date:   2015-10-15 16:54:05
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-07 12:40:08
 */

defined('FRAME_PATH')||exit('ACC Denied');

function F($name, $value, $path=APP_DATA){
    static $_cache = array();
    $path = $path . $name . '.php';
}

/**
 * 递归转义数组
 * @param  array  $arr 需要转义的数组
 * @return array       转义后的数组
 */
function _addslashes($arr){
    foreach($arr as $k=>$v){
        if (is_string($v)) {
            $arr[$k] = addslashes($v);
        }else if (is_array($v)) {
            $arr[$k] = _addslashes($v);
        }
    }   
    return $arr;
}

function getErrorType($num){
    switch($type){ 
        case E_ERROR: // 1 // 
         return 'E_ERROR'; 
        case E_WARNING: // 2 // 
         return 'E_WARNING'; 
        case E_PARSE: // 4 // 
         return 'E_PARSE'; 
        case E_NOTICE: // 8 // 
         return 'E_NOTICE'; 
        case E_CORE_ERROR: // 16 // 
         return 'E_CORE_ERROR'; 
        case E_CORE_WARNING: // 32 // 
         return 'E_CORE_WARNING'; 
        case E_COMPILE_ERROR: // 64 // 
         return 'E_COMPILE_ERROR'; 
        case E_COMPILE_WARNING: // 128 // 
         return 'E_COMPILE_WARNING'; 
        case E_USER_ERROR: // 256 // 
         return 'E_USER_ERROR'; 
        case E_USER_WARNING: // 512 // 
         return 'E_USER_WARNING'; 
        case E_USER_NOTICE: // 1024 // 
         return 'E_USER_NOTICE'; 
        case E_STRICT: // 2048 // 
         return 'E_STRICT'; 
        case E_RECOVERABLE_ERROR: // 4096 // 
         return 'E_RECOVERABLE_ERROR'; 
        case E_DEPRECATED: // 8192 // 
         return 'E_DEPRECATED'; 
        case E_USER_DEPRECATED: // 16384 // 
         return 'E_USER_DEPRECATED'; 
    } 

}

/**
 * 数组->XML
 * @param  array $arr   数据
 * @param  无    $node  不用理会，只是函数内部需要
 * @return xml          XML
 */
function arr2xml($data,$node=null){
    if ($node!==null) {
        $simxml = $node;
    } else {
        $simxml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><root></root>');
    }
    foreach($data as $k=>$v){
        if (is_array($v)) {
        	if (is_numeric($k)) $k = 'item';
            arr2xml($v,$simxml->addChild($k));
        } else {
        	if (is_numeric($k)) {
        		$simxml->addChild('item',$v);
        	} else {
        		$simxml->addChild($k,$v);
        	}            
        }
    }
    return $simxml->saveXML();
}

/**
 * URL重定向
 * @param string $url 重定向的URL地址
 * @param integer $time 重定向的等待时间（秒）
 * @param string $msg 重定向前的提示信息
 * @return void
 */
function redirect($url, $time=0, $msg='') {
    //多行URL地址支持
    $url        = str_replace(array("\n", "\r"), '', $url);
    if (empty($msg))
        $msg    = "系统将在{$time}秒之后自动跳转到{$url}！";
    if (!headers_sent()) {
        // redirect
        if (0 === $time) {
            header('Location: ' . $url);
        } else {
            header("refresh:{$time};url={$url}");
            echo($msg);
        }
        exit();
    } else {
        $str    = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
        if ($time != 0)
            $str .= $msg;
        exit($str);
    }
}

/**
 * 文件尺寸转换，将字节转为合适的单位大小
 * @param   int $bytes  字节大小
 * @return  string  转换后带单位的大小
 */
function tosize($bytes) {                    //自定义一个文件大小单位转换函数
    if ($bytes >= pow(2,40)) {                   //如果提供的字节数大于等于2的40次方，则条件成立
        $return = round($bytes / pow(1024,4), 2);    //将字节大小转换为同等的T大小
        $suffix = "TB";                              //单位为TB
    } elseif ($bytes >= pow(2,30)) {             //如果提供的字节数大于等于2的30次方，则条件成立
        $return = round($bytes / pow(1024,3), 2);    //将字节大小转换为同等的G大小
        $suffix = "GB";                              //单位为GB
    } elseif ($bytes >= pow(2,20)) {             //如果提供的字节数大于等于2的20次方，则条件成立
        $return = round($bytes / pow(1024,2), 2);    //将字节大小转换为同等的M大小
        $suffix = "MB";                              //单位为MB
    } elseif ($bytes >= pow(2,10)) {             //如果提供的字节数大于等于2的10次方，则条件成立
        $return = round($bytes / pow(1024,1), 2);    //将字节大小转换为同等的K大小
        $suffix = "KB";                              //单位为KB
    } else {                                     //否则提供的字节数小于2的10次方，则条件成立
        $return = $bytes;                            //字节大小单位不变
        $suffix = "Byte";                            //单位为Byte
    }
    return $return ." " . $suffix;                       //返回合适的文件大小和单位
}

/**
 * 递归把对象=>数组
 * @param  object $obj 要转换的对象
 * @return array       把对象转为数组的结果
 */
function obj2arr($obj){
    $arr = (array) $obj;
    foreach($arr as $k=>$v){
        if (is_object($arr[$k]))
            $arr[$k] = obj2arr($arr[$k]);
        if (is_array($arr[$k])) {
            foreach($arr[$k] as $k2=>$v2){
                if (is_object($arr[$k][$k2]) || is_array($arr[$k][$k2]))
                    $arr[$k][$k2] = obj2arr($arr[$k][$k2]);
            }
        }
    }
    return $arr;
}

/**
 * 输出各种类型的数据，调试程序时打印数据使用。
 * @param   mixed   参数：可以是一个或多个任意变量或值
 */
function p(){
    $args = func_get_args();  //获取多个参数
    if (count($args)<1) {
        Debug::addmsg("错误：必须为p()函数提供参数！", 1);
        return;
    }   

    echo '<div style="width:100%;text-align:left"><pre>';
    //多个参数循环输出
    for($i=0, $len=count($args); $i<$len; $i++){
        if (is_array($args[$i])) {  
            print_r($args[$i]);
            echo '<br>';
        }else if (is_string($args[$i])||is_numeric($args[$i])) {
            echo $args[$i].'<br>';
        } else {
            var_dump($args[$i]);
            echo '<br>';
        }
    }
    echo '</pre></div>';    
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @return mixed
 */
function get_client_ip($type = 0) {
    $type       =  $type ? 1 : 0;
    static $ip  =   NULL;
    if ($ip !== NULL) return $ip[$type];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $pos    =   array_search('unknown',$arr);
        if (false !== $pos) unset($arr[$pos]);
        $ip     =   trim($arr[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip     =   $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip     =   $_SERVER['REMOTE_ADDR'];
    }
    $ip = $ip == '::1' ? '127.0.0.1' : $ip; 
    // IP地址合法验证
    $long = sprintf("%u",ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
} 

// 过滤表单中的表达式，不明其原理
function frame_filter(&$value){
    // TODO 其他安全过滤
    
    // 过滤查询特殊字符
    if (preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|LIKE|NOTLIKE|NOTBETWEEN|NOT BETWEEN|BETWEEN|NOTIN|NOT IN|IN)$/i', $value)) {
        $value .= ' ';
    }
}





 ?>