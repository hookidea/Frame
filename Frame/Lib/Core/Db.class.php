<?php
/**
 * @Name: Db.class.php
 * @Role:   数据库中间层，负责调度不同数据库
 * @Author: 拓少
 * @Date:   2015-10-15 16:54:05
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-07 12:46:35
 */

class Db
{

	public static function getIns(){
		$db_type = strtolower(C('DB_TYPE'));
		if ('pdo' == $db_type) {
			return DbPdo::getIns();
		} else {
			return DbMysql::getIns();
		}
	}
}