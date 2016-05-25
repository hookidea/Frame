<?php
/**
 * @Name: convention.php
 * @Role:   框架默认的配置选项
 * @Author: 拓少
 * @Date:   2015-10-15 16:54:05
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-16 17:10:01
 * 
 * 书写规则：如果是boolean类型的，就不能使用引号包裹，如不能：'false',"false"
 * 
 */

defined('FRAME_PATH')||exit('ACC Denied');

return array(
    'APP_GROUP_LIST'        => array('Admin', 'Home'), // 所有分组
    'DEFAULT_GROUP'         => 'Home',                 // 默认分组

	'DEFAULT_MODULE'        => 'Index',
	'DEFAULT_ACTIOM'        => 'index',
    'DB_TYPE'               => 'PDO',
    // DSN，仅在 DB_TYPE = "PDO" 时有效，也可通过文件引入 
    'PDO_DSN'               => "mysql:dbname=test;host=localhost;charset=utf8",  
    'PDO_DSN_FILE'          => '',  // DSN文件的路径，仅在 DB_TYPE = "PDO" 时有效，如uri:file://D:/dsn.txt
    'PDO_DRIVER_OPTIONS'    => array(),  // 连接选项的键=>值数组 
    
    'DB_HOST'               => 'localhost',       // 服务器地址
    'DB_NAME'               => 'test',            // 数据库名
    'DB_USER'               => 'root',            // 用户名
    'DB_PWD'                => 'root',            // 数据库密码
    'DB_FIELDS_CACHE'       => true,              // 是否开启字段缓存
    "DB_PREFIX"             => "",                // 表的前缀，如果不为空，则认为启用表前缀功能
    'DEFAULT_AJAX_RETURN'   => 'JSON',            // AJAX默认返回的格式
    'VAR_MODULE'            => 'm',               // 默认模块获取变量
    'VAR_ACTION'            => 'a',               // 默认操作获取变量

    /* URL设置 */
    'URL_ROUTER_ON'         => false,             // 开启路由
    // 定义路由规则，详细配置请看ThinkPHP手册，所有功能都以支持、
    'URL_ROUTE_RULES'       => array('/^blog\/(\d+)\/(\d+)$/' => 'http://www.baidu.com'),
    // URL访问模式,可选参数0、1、2、3,代表以下四种模式：
    // 0 (普通模式); 1 (PATHINFO 模式); 2 (REWRITE  模式); 3 (兼容模式, 暂不支持)  默认为 PATHINFO 模式
    'URL_MODEL'             => 1,       
    'URL_HTML_SUFFIX'       => '',                // URL伪静态后缀设置，如：html

    'TMPL_ACTION_ERROR'     => FRAME_PATH . 'Tpl/dispatch_jump.tpl', // 默认错误跳转对应的模板文件
    'TMPL_ACTION_SUCCESS'   => FRAME_PATH . 'Tpl/dispatch_jump.tpl', // 默认成功跳转对应的模板文件



    'VAR_URL_PARAMS'        => '_URL_',          // PATHINFO URL参数变量
    'URL_PARAMS_BIND'       =>  true,            // URL变量绑定到Action方法参数
    'DEFAULT_M_LAYER'       =>  'Model',         // 默认的模型层名称
    'DEFAULT_C_LAYER'       =>  'Action',        // 默认的控制器层名称
    'DEFAULT_CHARSET'       => 'utf-8',          // 默认输出编码
    // 默认参数过滤方法 用于 $this->_get('变量名');$this->_post('变量名')...
    'DEFAULT_FILTER'        => 'htmlspecialchars', 
    'DEFAULT_TIMEZONE'      => 'PRC',            // 时区

     /* 数据缓存设置 */
    'MEMCACHE_HOST'         => '127.0.0.1',   // memcached所在的主机地址
    'MEMCACHE_PORT'         => 11211,         // 端口号
    'MEMCACHE_COMPRESSED'   => true,          // 是否启用压缩
    'MEMCACHE_PERSISTANT'   => false,         // 是否开启长连接
    'DATA_CACHE_TIME'       => 0,             // 数据缓存有效期 0表示永久缓存


    'DATA_CACHE_COMPRESS'   => false,         // 数据缓存是否压缩缓存
    'DATA_CACHE_CHECK'      => false,         // 数据缓存是否校验缓存
    'DATA_CACHE_PREFIX'     => '',            // 缓存前缀
    'DATA_CACHE_TYPE'       => 'File',        // 数据缓存类型，支持：File|Memcache
    // 'DATA_CACHE_PATH'       => TEMP_PATH,  // 缓存路径设置 (仅对File方式缓存有效)
    'DATA_CACHE_SUBDIR'     => false,         // 使用子目录缓存 (自动根据缓存标识的哈希创建子目录)
    'DATA_PATH_LEVEL'       => 1,             // 子目录缓存级别

    'TMPL_EXCEPTION_FILE'   => FRAME_PATH . 'Tpl/error.tpl',
    'TMPL_L_DELIM'          => '<{',
    'TMPL_R_DELIM'          => '}>',
    'CACHE_PATH'            => APP_RUNTIME . 'Cache/',
    'TMPL_TEMPLATE_SUFFIX'  => '.html',       // 模板文件后缀
    'TMPL_CACHFILE_SUFFIX'  => '.php',        // 模板缓存文件的后缀
    'TMPL_CACHE_ON'         => false,         // 是否开启模板编译缓存
    

    'LAYOUT_ON'             => false,         // 是否开启模板布局 
    'LAYOUT_NAME'           => 'layout',      // 默认模板布局名
    'LAYOUT_PATH'           => APP_TPL,       // 模板布局文件所在目录
    'LAYOUT_SUFFIX'         => '.html',       // 模板布局文件后缀
    'LAYOUT_ITEM'           => '<{__CONTENT__}>',  // 布局模板的内容替换标识

    'TMPL_CONTENT_TYPE'     => 'text/html',   // 默认模板输出类型
    'TMPL_CACHE_TIME'       => 0,             // 模板缓存有效期（秒） 0为永久
    'TMPL_DENY_FUNC_LIST'   => 'echo,exit',   // 模板引擎禁用函数

    //编译缓存和静态缓存只能同时开启一种
    'HTML_CACHE_ON'         => false,         // 是否开启静态缓存 
    'HTML_CACHE_TIME'       => 0,             // 静态缓存有效期（秒） 0为永久 
    'HTML_FILE_SUFFIX'      => '.html',       // 静态缓存后缀



    /* 错误设置 */
    'ERROR_MESSAGE'         => '页面错误！请稍后再试～',  // 错误显示信息，部署模式下有效
    'ERROR_PAGE'            => '',       // 错误定向页面，部署模式下
    'SHOW_ERROR_MSG'        => false,    // 部署默认下是否显示错误发生的原因（不是指PHP等语法错误，而是那些未知错误）
    'MEMORY_LIMIT_ON'       => false,    // 系统内存统计支持

    'REQUEST_VARS_FILTER'   => false,    // 是否开启全局安全过滤表达式关键字
    'VAR_FILTERS'           =>  '',      // 全局系统变量的默认过滤方法，多个用逗号分割
    'OUTPUT_ENCODE'         => false,    // 页面压缩输出

    /* 日志设置 */
    'LOG_RECORD'            => true,     // 是否记录日志信息
    'LOG_FILE_SIZE'         => 1024*1024,     // 日志文件大小限制（字节）
    'LOG_TYPE'              => 3,        // 日志记录类型 0 系统 1 邮件 3 文件 4 SAPI 默认为文件方式
    'LOG_DEST'              => '',       // 日志记录目标
    // 允许记录的日志级别
    'LOG_LEVEL'             => array('E_ERROR', 'E_WARNING', 'E_USER_ERROR', 'E_USER_WARNING', 'E_USER_NOTICE', ),
    'LOG_EXTRA'             => '',       // 日志记录额外信息
    


    /* SESSION设置 */
    'SESSION_AUTO_START'    => true,     // 是否自动开启Session
    'SESSION_TYPE'          => 'file',   // file：PHP默认的, memcache：使用memcache驱动, db：使用数据库驱动
    'SESSION_PREFIX'        => '',       // session 前缀 


    /* ShowRuntime行为配置 */
    'SHOW_PAGE_TRACE'       => true,     // 是否显示页面调试窗口
    'SHOW_DEBUG'            => true,     // 是否显示错误信息
    'SHOW_RUN_TIME'         => true,     // 是否显示运行时间 
    'SHOW_ADV_TIME'         => false,    // 是否显示详细的运行时间
    'SHOW_DB_TIMES'         => true,     // 是否显示数据库查询和写入次数
    'SHOW_USE_MEM'          => true,     // 是否显示内存开销
    'SHOW_LOAD_FILE'        => false,    // 是否显示加载文件
    'SHOW_FUN_TIMES'        => false,    // 是否显示函数调用
);






 ?>
 
