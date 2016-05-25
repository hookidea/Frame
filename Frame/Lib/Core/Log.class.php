<?php
/**
 * @Name: Log.class.php
 * @Role:   日志类
 * @Author: 拓少
 * @Date:   2015-10-15 16:54:05
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-07 12:46:35
 */

defined('FRAME_PATH')||exit('ACC Denied'); 

class Log
{
    const LOG = 'curr.log';
    static private $path = '';
    static public $logs = array();
    static public $type = array('1'=>'E_ERROR', '2'=>'E_WARNING', '4'=>'E_PARSE', '8'=>'E_NOTICE', '16'=>'E_CORE_ERROR', '32'=>'E_CORE_WARNING', '64'=>'E_COMPILE_ERROR', '128'=>'E_COMPILE_WARNING', '256'=>'E_USER_ERROR', '512'=>'E_USER_WARNING', '1024'=>'E_USER_NOTICE', '2048'=>'E_STRICT', '4096'=>'E_RECOVERABLE_ERROR', '8192'=>'E_DEPRECATED', '16384'=>'E_USER_DEPRECATED');

    /**
     * 保存信息到属性
     * @param  string $log  要写入的内容
     * @param  string $flag 内容的类型
     */
    public static function record($log, $type){
        if (!C('LOG_RECORD')) return;
        $type = self::$type[$type];
        if (!in_array($type, C('LOG_LEVEL'))) return;  //不在配置的要记录的日志级别中
        self::$logs[] = $type . '：' . $log;
    }

    /**
     * 把信息写入日志文件
     */
    public static function save(){
        if (!C('LOG_RECORD')) return;
        $line = self::line();
        $log = '[ '.date('Y-m-d H:i:s').' ] ' . get_client_ip() . ' ' . $_SERVER['REQUEST_URI'] . $line;
        if (isset(self::$logs) && ($len=count(self::$logs)) > 0)
            for($i=0; $i<$len; $i++)
                $log .= self::$logs[$i] . $line;
        self::write($log);
    }

    /**
     * 直接写入日志
     * @param  string $log 要写入的日志内容
     */
    public static function write($log){
        if (!C('LOG_RECORD')) return;
        //处理日志目录
        self::mk_dir();
        //判断是否应该备份
        self::isBak();
        //判断当前操作系统的换行符
        $log .= self::line();
        $log_type = C('LOG_TYPE');
        if (3 == $log_type) {
            error_log($log, 3, self::$path);
        } elseif (1 == $log_type) {
            error_log($log, 3, self::$path, C('LOG_DEST'), C('LOG_EXTRA'));
        } elseif (0 == $log_type || 4 == $log_type) {
            error_log($log, $log_type);
        }
        // $fh = fopen(self::$path, 'ab');
        // fwrite($fh, $log);
        // fclose($fh);
    }

    /**
     * 备份日志文件
     * @return boolean 是否成功备份
     */
    protected static function bak(){
        //获取备份名
        $bak = self::bakName();
        //重命名日志文件，即备份
        return rename(self::$path,$bak);
    }

    /**
     * 判断是否应该根据日期创建当日的目录，并且计算curr.log的路径位置
     * @return void
     */
    protected static function mk_dir(){
        //计算当天的日志目录
        $dir = APP_RUNTIME  . 'Logs/' . date('Ym/d');
        //保存curr.log日志文件路径
        self::$path = $dir . '/' . self::LOG;
        //判断当天的日志目录是否已存在
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    /**
     * 根据当前操作系统类型，生成对应换行符
     * @return string 当前系统下对应的换行符
     */
    protected static function line(){
        if (stripos(PHP_OS,'win') !== false) {
            //不能使用单引号
            return "\r\n";
        } elseif (stripos(PHP_OS,'linux') !== false) {
            return "\n";
        } elseif (stripos(PHP_OS,'mac') !== false) {
            return "\r";
        }
    }

    /**
     * 生成备份文件名
     * @return string 文件名
     */
    protected static function bakName(){
        $bak = ROOT . 'data/log/' . date('Ym/d/') . date('YmdHi') . rand(10000,99999) . '.bak';
        //防止生成的新备份文件名  已存在
        if (file_exists($bak)) {
            //再次生成新的备份文件名
            self::bakName();
        }
        return $bak;
    }

    /**
     * 判断日志文件是否需要备份
     */
    protected static function isBak(){
        if (!file_exists(self::$path)) {
            touch(self::$path);
            return;
        }
        //如果日志文件 < 1M 则无需备份
        if (filesize(self::$path) < C('LOG_FILE_SIZE')) {
            return;
        }
        //执行到此，说明文件已 > 1M，执行备份
        return self::bak();
    }
}





 ?>