<?php
/**
 * @Name: DbPdo.class.php
 * @Role:   PDO数据库操作类
 * @Author: 拓少
 * @Date:   2015-11-03 11:28:45
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-07 12:46:35
 */

class DbPdo
{
	//保存连接句柄
	private static $ins = null;   //保存实例化本类的对象
	private $link = null;  //保存数据库操作句柄

    //构造函数
	final protected function __construct(){
		$this->connect();
	}

    /**
     * 获得一个MyPDO对象实例（单例的）
     * @return object   MyPDO对象
     */
	public static function getIns(){
		if (self::$ins instanceof self)
			return self::$ins;
		self::$ins = new self;
		return self::$ins;
	}

    //连接数据库
    private function connect(){
        $dsn = C('PDO_DSN');
        $dsn = empty($dsn) && file_exists(C('PDO_DSN_FILE')) ? file_get_contents(C('PDO_DSN_FILE')) : $dsn;
        if (empty($dsn))
            trace('数据库连接失败，PDO的DSN没有提供', 1);
        try{
            $this->link = new PDO($dsn, C('DB_USER'), C('DB_PWD'));
        }catch(PDOException $e){
            trace($e->getMessage(), 1, true);
            exit;
        }
    }

    /**
     * 执行select语句
     * @param  string $options 要执行的select语句
     * @return mixed           操作结果
     */
    public function select($options){
    	if (!isset($options['table'])) return false;
        $fields = !isset($options['field']) ? '*' : $options['field'];
		$where = $this->parseWhere($options);//是个数组
		$sql = "SELECT {$fields} FROM {$options['table']} {$where['parse']}" . $this->parseSql($options);
        $sql = str_replace(array('   ', '  '), ' ', $sql);
        trace($sql, 0);
		$stmt = $this->link->prepare($sql); 
        if (!$stmt->execute($where['values'])) trace($stmt->errorInfo()[2], 1);
        if (0 == $stmt->rowCount()) return false;
        if (1 == $stmt->columnCount()) {     //查询一个值
            return $stmt->fetch()[0];
        } else {
            if (1 == $stmt->rowCount()) {  
                return $stmt->fetch(2);    //查询一行
            } else {
                return $stmt->fetchAll(2); //查询多行
            }
        }
	}

    /**
     * 删除数据
     * @param  string $table   必填，操作的表名
     * @param  string $where   必填，删除条件
     * @return num             影响的行数
     */
    public function delete($options){
        if (!is_array($options) || !isset($options['table']) || !isset($options['where']) || empty($options['where'])) return false;
        $where = $this->parseWhere($options);
        $sql = 'DELETE FROM '.$options['table'].' '.$where['parse'];
        trace($sql, 0);
        $stmt = $this->link->prepare($sql); 
        if (!$stmt->execute($where['values'])) trace($stmt->errorInfo()[2], 1);
        return $stmt->rowCount();
    }

    /**
     * 插入数据
     * @param  array  $data     要插入的数据
     * @param  array  $options 条件（里面包含表明即可）
     * @return integer         返回最后自增长的主键值
     */
    public function insert($data, $options){
        if (empty($data) || !is_array($options) || !isset($options['table']) ) return false;
        $keys = array_map(function($key){return ':d_'.$key;}, array_keys($data));
        $values = array_combine($keys, $data);
        $sql = "INSERT INTO {$options['table']} ";
        $sql .= '(' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', $keys) . ')';
        trace($sql, 0);
        $stmt = $this->link->prepare($sql); 
        if (!$stmt->execute($values)) trace($stmt->errorInfo()[2], 1);
        return $this->link->lastInsertId();
    }

    /**
     * 更新、修改数据
     * @param  array  $data   必填，数据，格式：$data = array('age'=>'18','gender'=>'男');
     * @param  string $where  必填，条件数组，包含表名，where条件(为了数据库安全，如果没有where，则退出)
     * @return integer        影响的行数
     */
    public function update($data, $options){
        if (empty($data) || !is_array($options) || !isset($options['table']) || !isset($options['where']) || empty($options['where'])) return false;
        $where = $this->parseWhere($options);
        $keys = array_map(function($key){return ':d_'.$key;}, array_keys($data));
        $values = array_combine($keys, $data);
        $values = array_merge($values, $where['values']);//防止这种："id=3&name=3"
        $sql = "UPDATE {$options['table']} SET ";
        foreach($data as $k=>$v)
        	$sql .= "$k=:d_$k,";
        $sql = substr($sql, 0, -1) . ' ' . $where['parse'];
        $sql = str_replace(array('   ', '  '), ' ', $sql);
        $stmt = $this->link->prepare($sql); 
        trace($sql, 0);
		if (!$stmt->execute($values)) trace($stmt->errorInfo()[2], 1);
        return $stmt->rowCount();
    }

    /**
     * 执行一条查询语句
     * @param  string $sql 需要执行的查询语句
     * @return mixed       执行结果    
     */
    public function query($sql){
        trace($sql, 0);
        $stmt = $this->link->prepare($sql); 
        if (!$stmt->execute()) trace($stmt->errorInfo()[2], 1);
        if (0 == $stmt->rowCount()) return false;
        if (1 == $stmt->columnCount()) {     //查询一个值
            return $stmt->fetch()[0];
        } else {
            if (1 == $stmt->rowCount()) {  
                return $stmt->fetch(2);    //查询一行
            } else {
                return $stmt->fetchAll(2); //查询多行
            }
        }
    }

    /**
     * 执行一条增、改、删语句
     * @param  string  $sql 要执行的增、改、删语句
     * @return              语句执行影响的行数
     */
    public function execute($sql){
        trace($sql, 0);
        return $this->link->exec($sql);
    }


    //启动一个事物
    public function startTrans(){
        $this->link->beginTransaction();
    }

    //提交一个事物
    public function commit(){
        $this->link->commit();
    }

    //回滚一个事物
    public function rollback(){
        $this->link->rollBack();
    }

    //自动组装 options 表达式
    public function parseSql($options){
        $sql = '';
        foreach($options as $k=>$v){
            if (in_array($k,array('group','limit','order','having'))) {
                $method = 'parse' . $k;
                $sql .= $this->$method($options);
            }
        }
        return $sql;
    }

    //解析where
    public function parseWhere($options){
        if (!isset($options['where'])) return;
        $where = $options['where'];
        if (is_string($where)) return array('parse'=>"WHERE {$where}", 'values'=>array());       
        $keys = array();
        $values = array();
        $logic = isset($where['_logic']) ? ' ' . strtoupper($where['_logic']) . ' ' : ' AND ';
        if (isset($where['_logic'])) unset($where['_logic']);
        $num = 0; //用来保存，当前循环到第几个字段了，从1开始计数
        $where_len = count($where); //字段的个数
        $parse = 'WHERE ';
        foreach($where as $k=>$v){
            if ('_string' == strtolower($k)) {
                if ($where_len >= 2)
                    $parse .= $logic . '('.$v.')';
                else
                    $parse .= $v;
                continue;
            }
            if (is_array($v[0]) && is_array($v[1])) {//区间查询$map['id'] = array(array('gt',1),array('lt',10)) ;
                if (is_string($v[count($v)-1]) && in_array(strtolower($v[count($v)-1]), array('xor', 'or', 'and'))) {
                    $lo = $v[count($v)-1];
                    $len_j = count($v)-1;
                } else {
                    $lo = 'AND';
                    $len_j = count($v);
                }
                $str = '';
                for($j=0; $j<$len_j; $j++){
                    $str .= '(';
                    $type = strtolower(trim($v[$j][0]));
                    if ($type == 'like') {
                        if (is_array($v[$j][1])) {
                            $str .= " {$k} LIKE";
                            for($x=0, $len_x=count($v[$j][1]); $x<$len_x; $x++){//:w_{$k}{$i} {$v[2]}
                                $values[":w_{$k}{$j}{$x}"] = $v[$j][1][$x];
                                $str .= " :w_{$k}{$j}{$x} {$v[$j][2]}";
                            }
                            $str = rtrim($str, $v[$j][2]);
                        }
                        if (is_string($v[$j][1]) || is_numeric($v[$j][1])) {
                            $str .= " {$k} LIKE :w_{$k}{$j}";
                            $values[":w_{$k}{$j}"] = $v[$j][1];
                        }
                    } elseif ($type == 'not like') {
                        if (is_array($v[$j][1])) {
                            $str .= " {$k} NOT LIKE";
                            for($x=0, $len_x=count($v[$j][1]); $x<$len_x; $x++){//:w_{$k}{$i} {$v[2]}
                                $values[":w_{$k}{$j}{$x}"] = $v[$j][1][$x];
                                $str .= " :w_{$k}{$j}{$x} {$v[$j][2]}";
                            }
                            $str = rtrim($str, $v[$j][2]);
                        }
                        if (is_string($v[$j][1]) || is_numeric($v[$j][1])) {
                            $str .= " {$k} NOT LIKE :w_{$k}{$j}";
                            $values[":w_{$k}{$j}"] = $v[$j][1];
                        }
                    } elseif ($type == 'between') {
                        if (is_string($v[$j][1])) $v[$j][1] = explode(',', $v[$j][1]);
                        $str .= " {$k} BETWEEN :w_{$k}{$j}0 and :w_{$k}{$j}1";
                        $values[":w_{$k}{$j}0"] = $v[$j][1][0];
                        $values[":w_{$k}{$j}1"] = $v[$j][1][1];
                    } elseif ($type == 'not between') {
                        if (is_string($v[$j][1])) $v[$j][1] = explode(',', $v[$j][1]);
                        $str .= " {$k} NOT BETWEEN :w_{$k}{$j}0 and :w_{$k}{$j}1";
                        $values[":w_{$k}{$j}0"] = $v[$j][1][0];
                        $values[":w_{$k}{$j}1"] = $v[$j][1][1];
                    } elseif ($type == 'not in') {
                        if (is_string($v[$j][1])) $v[$j][1] = explode(',', $v[$j][1]);
                        $str .= " {$k} NOT IN (";
                        for($s=0, $len=count($v[$j][1]); $s<$len; $s++){
                            $str .= ":w_{$k}{$j}{$s},";
                            $values[":w_{$k}{$j}{$s}"] = $v[$j][1][$s];
                        }
                        $str = rtrim($str, ',') . ')';
                    } elseif ($type == 'in') {
                        if (is_string($v[$j][1])) $v[$j][1] = explode(',', $v[$j][1]);
                        $str .= " {$k} IN (";
                        for($s=0, $len=count($v[$j][1]); $s<$len; $s++){
                            $str .= ":w_{$k}{$j}{$s},";
                            $values[":w_{$k}{$j}{$s}"] = $v[$j][1][$s];
                        }
                        $str = rtrim($str, ',') . ')';
                    } elseif ($type == 'exp') {
                        $str .= " {$k} {$v[$j][1]}";
                    } else {
                        $str .= "{$k}{$v[$j][0]}:w_{$k}{$j}";
                        $values[":w_{$k}{$j}"] = $v[$j][1];
                    }
                    $str .= ')'. $lo;
                }
                $str = rtrim($str, $lo);
                $parse .= $str;
            } else {
                $num++;
                if ($num>1 && $num <= $where_len) $parse .= $logic; //第一个和最后一个不添加逻辑符号
                if (is_string($v) || is_numeric($v)) {
                    $parse .= " {$k}=:w_{$k}";
                    $values[':w_'.$k] = $v;
                } elseif (is_array($v)) {
                    $v[0] = strtolower(trim($v[0]));
                    if ($v[0] == 'like') {
                        if (is_array($v[1])) {
                            $parse .= " {$k} LIKE";
                            for($i=0, $len=count($v[1]); $i<$len; $i++){
                                $values[":w_{$k}{$i}"] = $v[1][$i];
                                $parse .= " :w_{$k}{$i} {$v[2]}";
                            }
                            $parse = rtrim($parse, $v[2]);
                        }
                        if (is_string($v[1])) {
                            $parse .= " {$k} LIKE :w_{$k}";
                            $values[":w_{$k}"] = $v[1];
                        }
                    } elseif ($v[0] == 'not like') {
                        if (is_array($v[1])) {
                            $parse .= " {$k} NOT LIKE";
                            for($i=0, $len=count($v[1]); $i<$len; $i++){
                                $values[":w_{$k}{$i}"] = $v[1][$i];
                                $parse .= " :w_{$k}{$i} {$v[2]}";
                            }
                            $parse = rtrim($parse, $v[2]);
                        }
                        if (is_string($v[1])) {
                            $parse .= " {$k} NOT LIKE :w_{$k}";
                            $values[":w_{$k}"] = $v[1];
                        }
                    } elseif ($v[0] == 'between') {
                        if (is_string($v[1])) $v[1] = explode(',', $v[1]);
                        $parse .= " {$k} BETWEEN :w_{$k}0 and :w_{$k}1";
                        $values[":w_{$k}0"] = $v[1][0];
                        $values[":w_{$k}1"] = $v[1][1];
                    } elseif ($v[0] == 'not between') {
                        if (is_string($v[1])) $v[1] = explode(',', $v[1]);
                        $parse .= " {$k} NOT BETWEEN :w_{$k}0 and :w_{$k}1";
                        $values[":w_{$k}0"] = $v[1][0];
                        $values[":w_{$k}1"] = $v[1][1];
                    } elseif ($v[0] == 'not in') {
                        if (is_string($v[1])) $v[1] = explode(',', $v[1]);
                        $parse .= " {$k} NOT IN (";
                        for($i=0, $len=count($v[1]); $i<$len; $i++){
                            $parse .= ":w_{$k}{$i},";
                            $values[":w_{$k}{$i}"] = $v[1][$i];
                        }
                        $parse = rtrim($parse, ',') . ')';
                    } elseif ($v[0] == 'in') {
                        if (is_string($v[1])) $v[1] = explode(',', $v[1]);
                        $parse .= " {$k} IN (";
                        for($i=0, $len=count($v[1]); $i<$len; $i++){
                            $parse .= ":w_{$k}{$i},";
                            $values[":w_{$k}{$i}"] = $v[1][$i];
                        }
                        $parse = rtrim($parse, ',') . ')';
                    } elseif ($v[0] == 'exp') {
                        $parse .= " {$k} {$v[1]}"; 
                    } else {
                        $parse .= " {$k}{$v[0]}:w_{$k}";
                        $values[":w_{$k}"] = $v[1];
                    }
                }
            }
        }
        return array('values'=>$values, 'parse'=>$parse);
    }

    //解析group
    private function parseGroup($options){
        return isset($options['group']) ? ' group by ' . $options['group'] : '';
    }

    //解析limit
    private function parseLimit($options){
        return isset($options['limit']) ? ' limit ' . $options['limit'] : '';
    }

    //解析order
    private function parseOrder($options){
        return isset($options['order']) ? ' order by ' . $options['order'] : '';
    }

    //解析having
    private function parseHaving($options){
        return isset($options['having']) ? ' having ' . $options['having'] : '';
    }

    public function close(){
        
    }

	final protected function __clone(){}
}

