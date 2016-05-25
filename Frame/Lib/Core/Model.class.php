<?php
/**
 * @Name: Model.class.php
 * @Role:   Model类
 * @Author: xunzeo
 * @Date:   2016-03-07 11:09:18
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-07 12:46:35
 */

defined('FRAME_PATH')||exit('ACC Denied');

class Model
{
	// 保存Mysql单例对象
	private $db = null;
	// 保存表的所有字段，(必填)主键：_pk，(选填)自增：_autoinc
	private $_fields = array();//如果不设置，则自动查询数据库得到
	// 保存操作数据库的条件，包括主键字段，sql的where，表名
	public $options = array();
	// 保存数据
	private $data = array();
	// 保存错误提示信息 
	private $error = array(); 
	// 验证规则 
	public $_validate = array();
	// 字段映射 $_map = array('name' =>'username','mail'  =>'email');
	public $_map = array();
	// 自动填充规则
	public $_auto = array();
	// 实际数据表名（包含表前缀）（为了给个别的表）
    protected $trueTableName = '';
    // 库名
    protected $dbName = '';
    // 只读字段，只要不为空，那么就会自动检查、过滤
    public $readonlyField = array();


    /**
     * 构造函数
     */
	public function __construct(){
		if (strtolower(C('DB_TYPE')) == 'pdo'){
			$this->db = Db::getIns();
		} else {
			$this->db = Db::getIns();//实例化Mysql对象（单例）
		}
	}

	/**
	 * 一般在表单提交后运行此函数，自动验证，自动字段过滤表单非法字段
	 * @param  number  $type  必选，此时的操作类型(1,2,3)，1:insert,2:update,3:所有情况
	 * @param  array   $data  可选，要过滤的数据，一般不用传，自动过滤$_POST的数据
	 * @return array          返回处理后的数据
	 */
	public function create($type, $data=null){
		$data = !empty($data) ? $data : $_POST;  //默认自动过滤的是$_POST
        if (empty($data) || !is_array($data)) {
            return false;
        }
        //字段映射
        $data = $this->parseFieldsMap($data);
		//自动过滤
		$data = $this->_facade($data, $this->getTableName());
		//自动验证
		if (!$this->autoValidation($data)) return false; //验证失败
		//自动填充
		$data = $this->autoOperation($data, $type);
		//把过滤后的数据保存到数据对象
		$this->data = $data;  
		return $data;
	}
	/**
	 * 自动填充
	 * @param  array  $data 表单提交的数据
	 * @param  string $type 此时操作的类型（1,2,3），1:insert,2:update,3:所有情况
	 * @return array        填充后的表单数据
	 */
	public function autoOperation($data, $type){
		if (empty($this->_auto)) return $data;
		$_auto = $this->_auto;
		foreach($_auto as $v){
			$v[2] = isset($v[2]) ? $v[2] : 1;
			$v[3] = isset($v[3]) ? $v[3] : 'string';
			if ($type === 3){ // 所有情况
				$data = $this->autoOperationTmp($data, $v[0], $v[1], $v[3]);
				continue;
			}
			if ($type === 1 || $type === 2){ // 指定操作类型，insert(1)/update(2)
				if ($type === $v[2]){
					$data = $this->autoOperationTmp($data, $v[0], $v[1], $v[3]);
					continue;
				}
			}
		}
		return $data;
	}
	/**
	 * 为自动填充提供支持
	 * @param  array $data   要填充的数据
	 * @param  string $name  字段名
	 * @param  strnig $value 填充规则
	 * @param  string        附加规则
	 * @return array         填充后的数据
	 */
	public function autoOperationTmp($data, $name, $value, $type){
		if ($type === 'function'){
			$data[$name] = $value($data[$name]);
		}
		if ($type === 'callback'){//表示填充的内容是一个当前模型的方法
			$data[$name] = $this->$value($data[$name]);
		}
		if ($type === 'field'){
			$data[$name] = $data[$value];
		}
		if ($type === 'string'){
			$data[$name] = $value;
		}
		return $data;
	}

	/**
	 * 处理字段映射
	 * @param  array  $data 要处理的数据
	 * @return array        处理后的数据
	 */
	public function parseFieldsMap($data){
		//$_map = array('name' =>'username','mail'  =>'email');
		if (!empty($this->_map)){
			foreach($this->_map as $k=>$v){
				$tmp = $data[$k];
				unset($data[$k]);
				$data[$v] = $tmp;
			}
		}
		return $data;
	}

	/**
	 * 获取验证失败的提示信息
	 * @return array 一维数组
	 */
	public function getErr(){
		return $this->error;
	}


	/**
	 * 自动验证
	 * @param  array  $data 必填，要验证的数据
	 * @return bool 		true:成功，false:失败，可通过$this->getErr()获取失败的详细信息      
	 */
	public function autoValidation($data=null){
		if (empty($this->_validate)) return true;//如果没有验证规则，则认为是验证通过，直接退出

		// POST中存在vcode，则验证验证码正确与否
		if (isset($_POST['vcode']) && (strtolower($_POST['vcode']) !== strtolower($_SESSION['vcode']))){
			$this->error[] = '验证码错误';
			return false;
		}

		// 支持的内置规则名
		$_rules = array('require', 'email', 'tell', 'phone', 'code', 'currency', 'number', 'double', 'english', 'url', 'integer');
		foreach($this->_validate as $v){
			if (in_array($v[1], $_rules)){ // 附加规则对此类无效
				// $validate按顺序代表： 0字段，1规则，2提示信息，3验证条件，4附加规则， 5验证时间
				if (!$this->autoValidationTmp($data, $v[0], $v[1], $v[3])) $this->error[] = $v[2];
				continue;
			}
			if ($v[4] === 'function'){ // 使用参数附加规则$v[1]提供的函数验证
				if (!$v[1]($data[$v[0]])) $this->error[] = $v[2];
				continue;
			}
			if ($v[4] === 'length'){ // 可固定长度 '8'，也可表示范围 '3,8'
				$arr = explode(',', $v[1]);
				if (count($arr) === 1){ // 固定长度 '8'
					if (mb_strlen($data[$v[0]], 'UTF8') != $arr[0]) $this->error[] = $v[2];
				}
				if (count($arr) === 2){ // 表示范围 '3,8'
					$len = mb_strlen($data[$v[0]], 'UTF8');
					if ($arr[0] <= $len && $len <= $arr[1]) $this->error[] = $v[2];
				}
				continue;
			}
			if ($v[4] === 'equal '){ // 是否等于某值
				if ($data[$v[0]] !== $v[1]) $this->error[] = $v[2];
				continue;
			}
			if ($v[4] === 'in'){ // 验证是否在某个范围内，定义的验证规则必须是一个数组，如：array(1,2,3)，值必须是1/2/3
				if (!in_array($data[$v[0]], $v[1])) $this->error[] = $v[2];
				continue;
			}
			if ($v[4] === 'regex'){ // 定义的验证规则是一个正则表达式
				if (!$this->autoValidationTmp($data, $v[0], $v[1], $v[3])) $this->error[] = $v[2];
				continue;
			}
			if ($v[4] === 'callback'){ // 定义的验证规则是当前模型类的一个方法
				if (!$this->$v[1]($data[$v[0]])) $this->error[] = $v[2];
				continue;
			}
			if ($v[4] === 'between'){ // 验证范围，定义的验证规则表示范围，可以使用字符串或者数组，例如1,31或者array(1,31)
				if (is_string($v[1])){
					$arr = explode(',', $v[1]);
					if ($arr[0] > $data[$v[0]] && $arr[1] < $data[$v[0]]) $this->error[] = $v[2];
				}
				if (is_array($v[1])){
					if ($v[1][0] > $data[$v[0]] && $v[1][1] < $data[$v[0]]) $this->error[] = $v[2];
				}
				continue;
			}
			if ($v[4] === 'unique'){
				$arr = $this->where($v[0] . '="' . $data[$v[0]] . '"')->select();
				if (count($arr) != 0) $this->error[] = $v[2];//count()参数不是数组，返回1，参数是NULL，返回0
				continue;
			}
			if ($v[4] === 'expire'){ // 验证时间是否在某个范围，验证规则必须是字符串，(要验证的时间必须是时间戳！)范围的时间可以是：'2012-1-15,2013-1-15'，同时支持时间戳
				$arr = explode(',', $v[1]);
				if (strlen($arr[0]) == 10){ //时间戳
					if ($arr[0] > $str && $str > $arr[1]) $this->error[] = $v[2];
				} else {//不是时间戳
					$arr[0] = strtotime($arr[0]);
					$arr[1] = strtotime($arr[1]);
					if ($arr[0] > $data[$v[0]] && $data[$v[0]] > $arr[1]) $this->error[] = $v[2];
				}
				continue;
			}
			if ($v[4] === 'confirm'){ // 验证表单中的两个字段是否相同，定义的验证规则是一个字段名
				if ($data[$v[0]] !== $data[$v[1]]) $this->error[] = $v[2];
				continue;
			}

		}
		//只要$this->error不为空，则验证不通过
		if (!empty($this->error)) return false;
		return true;
	}

	/**
	 * 为 autoValidation 函数提供部分支持
	 * @param  array   $data  要验证数据
	 * @param  string  $field 字段名
	 * @param  string  $rule  内置的规则名、正则表达式
	 * @param  number  $flag  验证条件
	 * @return bool           true：验证通过, false：验证失败
	 */
	private function autoValidationTmp($data, $field, $rule, $flag){
		if ($flag === 0){//POST有该字段，就验证
			if (isset($data[$field])){//如果有该字段
				if ($this->regex($data[$field], $rule)) return true;
				return false;
			}
			return true;
		}
		if ($flag === 1){ // 必须验证
			if (!isset($data[$field]) || !$this->regex($data[$field], $rule)) return false;
			return true;
		}
		if ($flag === 2){ // 非空验证
			if (isset($data[$field]) && !empty($data[$field])){ //POST中存在，且不为空
				if ($this->regex($data[$field], $rule)) return true;
				return false;
			}
			return true;
		}
	}

	/**
	 * 正则匹配
	 * @param  string $value 要匹配的值
	 * @param  string $rule  内置规则名、正则表达式
	 * @return bool          true：匹配，false：不匹配
	 */
	private function regex($value, $rule){//首先判断$rule是内置规则名，如果在内置规则找不到，则认为 $rule 是正则表达式
		$preg = array(
			'require' => '/.+/is',             //必填
			'email' => '/(^[a-zA-Z][a-zA-Z]{4,9}|^[1-9][0-9]{4,9})(?:\.[a-zA-Z]{2,5})?@([\w]{2,5})((?:\.[\w]{2,4}){1}(?:\.[\w]{2})?)$/is',                    //邮箱
			'tell' => '/^(?:\()?(\d{3,4})(?:\))?(?:\-)?(\d{7})$/is',
			'phone' => '/^13\d{9}$/is',        //手机
			'code' => '/^5\d{5}$/is',          //邮编
			'currency' => '/^\d+(\.\d+)?$/is', //货币
			'number' => '/^\d+$/is',           //数字
			'double' => '/^[+-]?\d+(\.\d+)?$/is',
			'english' => '/^\w+$/is',          //英文
			'integer' => '/^[+-]?\d+$/is',      //实数（正负）
			'url' => '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/'
		);
		if (isset($preg[strtolower($rule)])) $rule = $preg[strtolower($rule)];
		return preg_match($rule, $value) === 1;
	}

	/**
	 * 添加数据，使用创建数据对象
	 * @param string $data    数据，不提供，则取$this->data
	 * @param array  $options 表达式，不提供，则取$this->options
	 * @param num             数据库 INSERT 操作产生的 ID
	 */
	public function add($data='', $options=array()){
		if (empty($data)){
			if (empty($this->data)) return false;
			$data = $this->data;
			$this->data = array();//清空
		}
		if (!empty($this->readonlyField)) $data = $this->_checkReadonlyField($data);//检查只读字段
		$options['type'] = 'insert';//操作类型
		$options = $this->_parseOptions($options); //分析 $options ，里面会自动判断、获取表名
		if (!$options) return false;  //如果没有通过两种方法设置表名，直接 return 
		$data = $this->_facade($data, $options['table']);//自动过滤
		if (empty($data)) return false;//如果过滤后的数组为空，则直接 return 
		return $this->db->insert($data, $options);
	}
	/**
	 * 更新数据
	 * @param  string $data    要更新的数据
	 * @param  array  $options 表达式
	 * @return [type]          修改影响的行数
	 */
	public function save($data='', $options=array()){ // update连贯操作最后一步
		if (empty($data)){
			if (empty($this->data)) return false;
			$data = $this->data;
			$this->data = array(); // 清空
		}
		if (!empty($this->readonlyField)) $data = $this->_checkReadonlyField($data);//检查只读字段
		$options['type'] = 'update'; // 操作类型
		$options = $this->_parseOptions($options); //分析 $options ，里面会自动判断、获取表名
		if (!$options) return false;  // 如果没有通过两种方法设置表名，直接 return 
		$data = $this->_facade($data, $options['table']);//自动过滤
		if (empty($data)) return false;// 如果过滤后的数组为空，则直接 return 
		return $this->db->update($data, $options);
	}
	
	/**
	 * 删除一行或多行数据
	 * @param  string $key       可选，如果有，则认为删除主键的值等于$key的行
	 *                     		      如果没有，则作为连贯操作的最后一步
	 * @param  array  $options   只是内部实现需要，一般不用给其传参
	 * @return num               返回删除的行数
	 */
	public function delete($key='', $options=array()){
		$options['type'] = 'select'; // 操作类型
		$options = $this->_parseOptions($options);  // 分析 $options ，里面会自动判断、获取表名
		if (!$options) return false;  // 如果没有通过两种方法设置表名，直接 return
		if (!empty($key)){ // delete主键的值为$key的行，可以'3' , '2,5'
			$pk = $this->getPk($options['table']);
			$arr = explode(',', $key);
			$str = '';
			foreach($arr as $v){
				$str .= $pk . '="' . $v . '" or ';
			}
			$str = substr($str, 0, -3);
			$options['where'] = $str;
		} else { // 作为连贯操作的最后一步
			if (!isset($options['where'])) return false; // 如果where条件为空，则退出
		}
		$options['type'] = 'delete'; // 操作类型
		$options = $this->_parseOptions($options); // 分析 $options ，里面会自动判断、获取表名
		return $this->db->delete($options);
	}

	/**
	 * 多行查询	
	 * @return array 数组
	 */
	public function select($options=array()){ // select连贯操作最后一步
		$options['type'] = 'select'; // 操作类型
		$options = $this->_parseOptions($options); // 分析 $options ，里面会自动判断、获取表名
		if (!$options) return false;  // 如果没有通过两种方法设置表名，直接 return
		return $this->db->select($options);
	} 

	/**
	 * 查询一行数据
	 * @param  array/string/num  array：array('id'=>4)；string/num，则表示主键
	 * @return array              一维数组
	 */
	public function find($value=''){
		$this->limit(1);
		$options['type'] = 'select';//操作类型
		$options = $this->_parseOptions($options); //分析 $options ，里面会自动判断、获取表名
		if (!$options) return false;  //如果没有通过两种方法设置表名，直接 return
		$pk = isset($options['_pk'])? $options['_pk'] : $this->getPk($options['table']);
		if (is_array($value)){
			$str = '';
			foreach($value as $k=>$v){
				$str .= $k . '="' . $v . '" ';
			}
			$options['where'] = $str;
		} elseif (is_numeric($value)){
			$options['where'] = $pk . '="' . $value . '"';
		}
		return $this->db->select($options);
	}

	/**
	 * 定位查询
	 * @param  integer $position 位置
	 * @return array/false            
	 */
	public function getN($position=0){
		if ($position >= 0) { // 正序
			$this->options['limit'] = $position.',1';
			return $this->select();
		} else { // 逆序
			$list = $this->select();
			return $list ? $list[count($list)-abs($position)] : false;
		}
	}

	/**
	 * 查询某个字段的值(这个函数并不能够确保真的返回一个值，只能您通过where条件来控制)
	 * @param  [type] $field 必填，要查询的字段。必须通过参数的形式传递，连贯操作赋值的无效
	 * @return string        如果sql语句查到的不止是一个值，则返回空字符串，否则返回查询到的值
	 */
	public function getField($field){
		if (is_string($field)) {
			$this->options['field'] = $field;	
			return $this->select();
		}
		return false;
	}

	/**
	 * 设置一个、多个字段的值
	 * @param string/array   $v1  必填
	 * @param string         $v2  如$v1为数组，则$v2可选，否则必填
	 * @return num           返回操作影响的行数，如为-1，操作不成功
	 */
	public function setField($v1='', $v2=''){
		if (!is_array($v1) && (empty($v1) || empty($v2))) return false;
		$data = array();
		if (is_array($v1)){
			foreach($v1 as $k=>$v){
				$data[$k] = $v;
			}
		} else {//认为参数1为字段名，参数2为字段要设置的值
			$data[$v1] = $v2; //更新数据对象
		}
		return $this->save($data);
	}

	//增加数据（操作 $this->data 属性）
	public function __set($name, $key){
		$this->data[$name] = $key;
	 	return $this;
	}

	//查询数据（操作 $this->data 属性）
	public function __get($name){
		return $this->data[$name];
	}

	/**
	 * 魔术方法，主要是修改$this->options，给 select 查询做准备
	 * @param  string $name 
	 * @param  array  $args PHP自动把调用时传的参数组合成一个数组，作为$args传入该函数
	 */
	public function __call($name, $args){
		$name = strtolower($name);
		if (in_array($name, array('count', 'avg', 'max', 'min', 'sum'))) {
			if ($name === 'count' && empty($args)) {
				$this->options['field'] = 'count(*)';
			} else {
				$this->options['field'] = $name . '(' . $args[0] . ')';
			}
			return $this->select();
		} elseif($name == 'field') {
			if (empty($args)) { // 如果不设置，则默认取所有字段
				$this->options['field'] = '*';
			} elseif (is_array($args[0])){ // 参数是数组
				$str = '';
				foreach($args[0] as $k=>$v){
					if (!is_numeric($k)) {
						$str .= $k . ' as ' . $v . ',';
					} else {
						$str .= $v . ',';
					}
				}
				$this->options['field'] = substr($str, 0, -1);
			} else { // 参数是字符串
				$this->options['field'] = $args[0];
			}
			return $this;
		} elseif (in_array($name, array('table', 'order', 'having', 'group', 'limit'))) {
			if (empty($args)) return false; // 没有提供参数
			if ($name === 'table') {
				$pos = strpos($args[0], '.');

				if ($pos === false) { // 提供的表名里没有库名
					if (isset($args[1])) { // 如果传入前缀
						$args[0] = $args[1] . $args[0];
					} elseif (C('DB_PREFIX')){ // 如果没有传入前缀，但配置了前缀
						$args[0] = C('DB_PREFIX') . $args[0];//开启了表前缀
					}
					if (!empty($this->dbName)) {
						$args[0] = $this->dbName . '.' . $args[0];
					}
				} else { // 提供的表名里有库名
					$db = substr($args[0], 0, $pos);
					$table = substr($args[0], $pos+1);
					if (isset($args[1])) { // 如果传入前缀
						$table = $args[1] . $table;
					} elseif (C('DB_PREFIX')) { // 如果没有传入前缀，但配置了前缀
						$table = C('DB_PREFIX') . $table; // 开启了表前缀
					}
					$args[0] = $db . '.' . $table;
				}

				$this->options['table'] = $args[0];
			} elseif ($name === 'limit') {
				if (is_array($args[0])){
					$this->options['limit'] = implode(',', $args[0]);
				} else {
					$this->options['limit'] = $args[0];
				}
			} else {
				$this->options[$name] = $args[0];
			}
			return $this;
		} elseif (strtolower(substr($name, 0, 3)) == 'top') { // 实现top动态查询
			$list = $this->select();
			return $list ? array_slice($list, 0, substr($name, 3)) : false;
		}
	}

	/**
	 * 处理 where 条件
	 * @param  mixed $where  where条件，可以是数组、对象、字符串、数字
	 */
	public function where($where){
		$pk = $this->getPk($this->options['table']); // 获取主键

		if (is_object($where)) {
			$arr = get_object_vars($where);
			if (!empty($this->options['where']))
				$this->options['where'] = array_merge(get_object_vars($where), $this->options['where']);
			else
				$this->options['where'] = get_object_vars($where);	
		}

		if (is_numeric($where)) // 4
			$this->options['where'][$pk][] = $where;

		if (is_string($where)) {
			if (strpos($where, ',') !== false) {  // "3,4,5"
				$this->options['where'][$pk] = array('in', $where);
			} else {
				$this->options['where']['_string'] = $where;  // 字符串,则是使用“age > 20 AND sex=’男’”，此时不能使用预处理方式处理语句，不安全
			}
		}

		if (is_array($where)) {
			if (isset($where[0])) { // array(3,4,5)
				$this->options['where'][$pk] = array('in', $where);
			} else { // array(“sex”=>”男”) // array(“uid”=>array(1,2,3), “sex”=>”男")
				foreach($where as $k=>$v){
					
					if (isset($v['_multi']) && $v['_multi'] === true && strpos($k, '&') !== false) {
						$tmp = explode('&', $k);
						for($i=0, $len=count($tmp); $i<$len; $i++)
							$this->options['where'][$tmp[$i]] = $v[$i];
						$this->options['where']['_logic'] = 'AND';
					} elseif (strpos($k, '|') !== false) {
						$tmp = explode('|', $k);
						for($i=0, $len=count($tmp); $i<$len; $i++)
							$this->options['where'][$tmp[$i]] = $v;
						$this->options['where']['_logic'] = 'OR';
					} else {
						$this->options['where'][$k] = $v;
					}
				}
			}
		}
		return $this;
	}

	/**
	 * 自增一个字段
	 * @param  string  $field 必填，字段名
	 * @param  integer $step  可选，步长，默认为 1
	 * @return number         操作影响的行数
	 */
	public function setInc($field, $step=1){
		if (!is_numeric($step)) return false;
		$step = '+' . $step;
		return $this->setField('exp_' . $field, $step);
	}

	/**
	 * 自减一个字段
	 * @param  string  $field 必填，字段名
	 * @param  integer $step  可选，步长，默认为 1
	 * @return number         操作影响的行数
	 */
	public function setDec($field, $step=1){
		if (!is_numeric($step)) return false;
		$step = '-' . $step;
		return $this->setField('exp_' . $field, $step);
	}

	/**
	 * 用于更新和写入数据的sql操作
	 * @param  string  $sql SQL语句
	 * @return integer      操作影响的行数
	 */
	public function execute($sql)
	{
		return $this->db->execute($sql);
	}
	
	/**
	 * 执行SQL查询操作
	 * @param  string   $sql     SQL语句
	 * @return array/false
	 */
	public function query($sql)
	{
		return $this->db->query($sql);
	}

	/**
	 * 开启事物
	 */
	public function startTrans()
	{
		$this->db->startTrans();
	}
	
	/**
	 * 提交事物
	 */
	public function commit()
	{
		$this->db->commit();
	}
	
	/**
	 * 回滚事物
	 */
	public function rollback()
	{
		$this->db->rollback();
	}

	/**
	 * 获取表的字段信息，可能通过file缓存得到、查表得到
	 */
	public function fieldsGet($table)
	{ 
		// 来到这里，已经说明了此时 $this->_fields 没有值
		if (C('DB_FIELDS_CACHE')) { // 开启了字段缓存
			$path = $this->_getFlushPath($table);
			if (file_exists($path)) { // 如果已经有了缓存，那么直接读取缓存
				$this->_fields = eval(file_get_contents($path));
			} else {
				$this->_fields = $this->_parseFields($table); // 通过查询数据库，得到字段
				$this->_flush($table); // 把字段缓存到文件
			}
		} else { // 没有开启字段缓存
			$this->_fields = $this->_parseFields($table);
		}
	}	

	/**
	 * 获取主键
	 * @param  string $table 表名
	 * @return string 主键
	 */
	public function getPk($table)
	{
		if (!isset($this->_fields['_pk'])) { // 如果$_fields还没有字段信息，则到数据库库自动分析得到
			$this->fieldsGet($table);
			return $this->_fields['_pk'];
		} else { // 如果$_fields已经存在字段信息
			return $this->_fields['_pk'];
		}
	}

	/**
	 * 获取表名
	 * @return string 表名
	 */
	public function getTableName()
	{
		if (!isset($this->options['table'])) {
			if ($this->trueTableName) return $this->trueTableName; // 如果通过属性设置了表名
			return false; // 如果不提供表名(通过$this->trueTabName,$this->table两种方法设置)
		}
		return $this->options['table'];
	}

	/**
	 * 批量执行SQL语句，使用事物来保证同时成功、同时失败
	 * @param  array  $sql  要批量执行的SQL语句构成的数组
	 * @return bool         true：执行成功，false：执行失败
	 */
	public function patchQuery($sql)
	{
		if (!is_array($sql)) return false;
		$this->startTrans();
		try {
			for($i=0, $len=count($sql); $i<$len; $i++){
				$rs = $this->execute($sql[$i]);
				if ($rs === false) {//执行不成功，则自动回滚
					$this->rollback();
					return false;
				}
				$this->commit();
			}
		} catch (Exception $e) {
			$this->rollback();
		}
		return true;
	}


	/**
	 * 自动过滤非字段数据
	 * @param  array  $data  必填，要过滤的数组
	 * @param  string $table 必填，表名，以哪个表来过滤
	 * @return array         过滤后的数组
	 */
	public function _facade($data,$table){//能进入到这里，已经说明了表名已经定义
		if (empty($this->_fields)) {//如果没有自定义表的字段，那么自动获取
			$this->fieldsGet($table);
		}
		foreach($data as $k=>$v){
			if (stripos($k,'exp_') !== false) {//如果是自增自减来到
				if (!in_array(substr($k,4),$this->_fields)) unset($data[$k]);//自增的字段不存在与表的字段中
				continue;
			}
			if (!in_array($k,$this->_fields)) {
				unset($data[$k]); //删除表中没有的字段
			}
		}
		return $data;
	}
	
	/**
	 * 负责把表的字段信息缓存到文件
	 * @param  string $table  必填，表名
	 * @param  array  $fields 选填，要缓存的字段
	 * @return bool   是否写入缓存成功
	 */
	public function _flush($table, $fields=array())
	{
		$fields = array_merge($fields, $this->_fields);
		$path = $this->_getFlushPath($table);

		if (!file_exists(dirname($path))) mkdir(dirname($path), 0777, true);
		if (!file_exists($path)) touch($path);
		return file_put_contents($path, 'return ' . var_export($fields, true) . ';');
	}

	/**
	 * 通过查询数据库，分析获得表的所有字段
	 * @param  string $table 必填，表名
	 * @return array         字段信息，如果表有主键和自增，也会一起返回
	 */
	public function _parseFields($table)
	{
		if (!isset($table)) return false; // 不指定表名，退出

		$sql = 'desc ' . $table;
		$arr = $this->db->query($sql);
		$fields = array();
		if (isset($arr[0])) { // 两个字段以上的表
			foreach($arr as $v){
				$fields[] = $v['Field'];
				if ($v['Key'] == 'PRI') { // 寻找表的主键
					$fields['_pk'] = $v['Field'];
				}
				if ($v['Extra'] == 'auto_increment') {
					$fields['_autoinc'] = $v['Field'];
				}
			}
		} else { // 只有一个字段的表
			$fields[] = $arr['Field'];
			if ($arr['Key'] == 'PRI') { // 寻找表的主键
				$fields['_pk'] = $arr['Field'];
			}
			if ($arr['Extra'] == 'auto_increment') {
				$fields['_autoinc'] = $arr['Field'];
			}
		}
		return $fields;
	}

	/**
	 * 获取字段缓存的存储路径
	 * @return string 字段缓存的存储路径
	 */
	private function _getFlushPath($table)
	{
		if ($pos = strpos($table, '.') !== false) {
			$db_table = $table;
		} elseif (!empty($this->dbName)) {
			$db_table = $this->dbName . '.' . $table;
		} else {
			$db_table = C('DB_NAME') . '.' . $table;
		}
		// /home/stu/Code/frame/App/Runtime/Data/_fields/App.test.t.php
		return APP_RUNTIME . 'Data/_fields/' . APP_NAME . '.' . $db_table . '.php';
	}

	/**
	 * 分析Options属性
	 * @param  array $options
	 * @return array 处理后的$options
	 */
	public function _parseOptions($options=array())
	{
		if (is_array($options))
			$options = array_merge($this->options, $options);

		$this->options = array(); // 清空，避免影响下一次操作
		if (!isset($options['type'])) return false; // 如果没有说明操作类型
		$options['type'] = strtolower($options['type']);

		if (!isset($options['table'])) {  // 处理表名
			$name = $this->getTableName();
			if (!($name)) return false;  // 没有通过两种方式任意一种来设置表名
			$options['table'] = $name;
		}

		// 检测$options，防止误操作
		if ($options['type'] === 'delete' || $options['type'] === 'update' ) {
			// where ==> Array ( [_string] => id=1 )
			// 此处待加强：防止错误的where导致数据库数据误删
			if (!isset($options['where']) || empty($options['where']) || is_numeric($options['where'])) return false;
			// delete,update 操作不需要 group,order,having,limit
			if (isset($options['group'])) unset($options['group']);
			if (isset($options['order'])) unset($options['order']);
			if (isset($options['having'])) unset($options['having']);
			if (isset($options['limit'])) unset($options['limit']);
		}
		return $options;
	}

	/**
	 * 实现只读字段 readonlyField
	 * @param  array 所有的表单数据
	 * @return array 删除只读字段之后的表单数据
	 */
	public function _checkReadonlyField($data)
	{
		$keys = array_keys($data);     // 获取字段的所有键
		$arr = $this->readonlyField;   // 只读字段
		for($i=0, $len=count($arr); $i<$len; $i++){
			if (in_array($arr[$i],$keys)) unset($data[$arr[$i]]);
		}
		return $data;
	}

}






