<?php
/**
 * @Name: Dbmysql.class.php
 * @Role:   mysqli面向过程化版数据库操作类
 * @Author: 拓少
 * @Date:   2015-10-15 16:54:05
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-07 12:46:35
 */

defined('FRAME_PATH')||exit('ACC Denied');

class DbMysql
{
    private static $ins = null;
    private $conn = null;  //保存数据库操作句柄

    //构造函数
    final protected function __construct(){
        $this->conn = $this->connect(C('DB_HOST'), C('DB_USER'), C('DB_PWD'), C('DB_NAME'));
        if (mysqli_connect_error()) trace(mysqli_connect_error(), 1);
        $this->select_char('UTF8');
    }

    /**
     * 获取一个单例的Mysql类
     * @return object Mysql类
     */
    public static function getIns(){
        if (self::$ins instanceof self)
            return self::$ins;
        self::$ins = new self();
        return self::$ins;
    }

    /**
     * 获取多行数据
     * @param  result      select语句得到资源句柄
     * @return array       查询到的数据
     */
    public function getAll($rs){
        $arr = array();
        while($row = mysqli_fetch_assoc($rs))
            $arr[] = $row;
        return $arr;
    }

    /**
     * 获取一行数据
     * @param  result      select语句得到资源句柄
     * @return array       一维数组
     */
    public function getRow($rs){
        return mysqli_fetch_assoc($rs);
    }

    /**
     * 获取某一个值
     * @param  result      select语句得到资源句柄
     * @return num/string  一个值
     */
    public function getOne($rs){
        $row = mysqli_fetch_row($rs);
        return $row[0];
    }
    
    /**
     * 连接数据库
     * @param  string $h  服务器地址
     * @param  string $u  要登陆的用户
     * @param  string $p  用户密码
     * @param  string $db 要连接的库名
     * @return result     连接资源
     */
    public function connect($h, $u, $p, $db){
        return mysqli_connect($h, $u, $p, $db);
    }
    
    /**
     * 获取上一次操作而新增行的"自增值""
     * @return num  上一次操作而新增行的"自增值""
     */
    public function insert_id(){
        return mysqli_insert_id($this->conn);
    }

    /**
     * 获取上一次操作影响的行数
     * @return num  上一次操作影响的行数
     */
    public function affected_rows(){
        return mysqli_affected_rows($this->conn);
    }

    /**
     * 往数据库发送SQL语句
     * @param  string $sql SQL语句
     * @return        [description]
     */
    public function query($sql){
        $rs = mysqli_query($this->conn, $sql);
        trace($sql, 0);
        if (is_object($rs)) {
            $field_num = $rs->field_count;   //查到的列数
            $line_num = $rs->num_rows;       //查到的行数
            if ($field_num >= 1 && $line_num > 1) return $this->getAll($rs); 
            if ($field_num >= 1 && $line_num === 1) return $this->getRow($rs);
            if ($field_num === 1 && $line_num === 1) return $this->getOne($rs);
        } else {
            return $rs;
        }
    }

    /**
     * 执行SQL语句
     * @param   $sql  需要执行的sql语句
     */
    public function execute($sql){
        trace($sql, 0);
        return mysqli_query($sql);
    }

    /**
     * 选择字符集
     * @param  string $char 字符集类型
     */
    public function select_char($char){
        $sql = 'set names '. $char;
        $this->query($sql);
    }

    /**
     * 查询
     * @param  string  $table   必填，操作的表名
     * @param  array  $options  必填
     * @param  string  $fields  必填，查询的字段，格式：title,name,age
     * @param  boolean $flag    选填，0:All,1:Row,2:One
     * @return string/array           返回查到的数据
     */
    public function select($options){
        if (!is_array($options) || !isset($options['table'])) return false;
        $fields = !isset($options['field']) ? '*' : $options['field'];
        $sql = 'select ' . $fields . ' from ' . $options['table'] . $this->parseSql($options);
        return $this->query($sql);
    }
    
    
    /**
     * 插入数据
     * @param  string $table  必填，操作的表名
     * @param  array  $data   必填，数据，格式：$data = array('age'=>'18','gender'=>'男');
     * @return num            影响的行数
     */
    public function insert($data,$options){
        if (empty($data) || !is_array($options) || !isset($options['table'])) return false;
        $sql = 'insert into ' . $options['table'] . ' (' . implode(',', array_keys($data)) . ') values (\'' . implode('\',\'', array_values($data)) . '\')';
        $this->query($sql);
        return $this->insert_id();
    }

    /**
     * 更新、修改数据
     * @param  array  $data   必填，数据，格式：$data = array('age'=>'18','gender'=>'男');
     * @param  string $where  必填，条件数组，包含表名，where条件(为了数据库安全，如果没有where，则退出)
     * @return num            影响的行数
     */
    public function update($data,$options){
        if (empty($data) || !is_array($options) ||empty($options['table']) || empty($options['where'])) return false;
        $str = '';
        foreach($data as $k=>$v){
            if (stripos($k,'exp_') !== false) {//如果是自增自减来到
                $sql = 'update ' . $options['table'] . ' set ' . substr($k,4) . '=' . substr($k,4) . $v . $this->parseSql($options);
                $this->query($sql);
                return $this->affected_rows();
            }
            $str .= $k . '=\'' . $v . '\',';
        }
        $sql = 'update ' . $options['table'] . ' set ' . substr($str,0,-1) . $this->parseWhere($options);
        $this->query($sql);
        return $this->affected_rows();
    }

    /**
     * 删除数据
     * @param  string $options 条件，包括表明
     * @return num             影响的行数
     */
    public function delete($options){
        if (!is_array($options) || !isset($options['table']) || !isset($options['where'])) return false;
        $sql = 'delete from ' . $options['table'] . $this->parseSql($options);
        $this->query($sql);
        return $this->affected_rows();
    }

    //自动组装 options 表达式
    public function parseSql($options){
        $sql = '';
        foreach($options as $k=>$v){
            if (in_array($k,array('group', 'limit', 'order', 'having'))) {
                $method = 'parse' . $k;
                $sql .= $this->$method($options);
            }
        }
        return $sql;
    }

    private function parseWhere($options){
        return isset($options['where']) ? ' where ' . $options['where'] : '';
    }
    private function parseGroup($options){
        return isset($options['group']) ? ' group by ' . $options['group'] : '';
    }
    private function parseLimit($options){
        return isset($options['limit']) ? ' limit ' . $options['limit'] : '';
    }
    private function parseOrder($options){
        return isset($options['order']) ? ' order by ' . $options['order'] : '';
    }
    private function parseHaving($options){
        return isset($options['having']) ? ' having ' . $options['having'] : '';
    }

    //开启事物
    public function startTrans(){
        $this->query('start transaction');
    }

    //提交
    public function commit(){
        $this->query('commit');
    }

    //回滚
    public function rollback(){
        $this->query('rollback');
    }


    //防止类被复制，破坏单例
    final protected function __clone(){
    }
}

