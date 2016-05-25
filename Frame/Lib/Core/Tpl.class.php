<?php
/**
 * @Name: Tpl.class.php
 * @Role:   模板类
 * @Author: 拓少
 * @Date:   2015-11-11 17:01:33
 * @Last Modified by:   xunzeo
 * @Last Modified time: 2016-03-07 12:46:35
 */

defined('FRAME_PATH')||exit('ACC Denied');

class Tpl
{
    private $templates_dir = './temp';  //模板路径
    private $complie_dir = './comp';    //编译后存放路径

    private $config = array();
    private $ldelimit = '<\{';  //=> '<{' 如{}|()^$等特殊字符必须转义（转义一次即可，不用双重转义）
    private $rdelimit = '\}>';  //=> '}>' 如{}|()^$等特殊字符必须转义（转义一次即可，不用双重转义）
    
    private $ys = array('+', '-', '/', '*', '%');// 所有支持的运算符
    private $_tpl_var = array();//存放模板变量
    private $flag = true; //默认为true，开启解析运算符，则解析变量名函数此时不会解析函数名、默认值
    private $ys_run = false; //true:此次解析已经运行过parseYs，用来保证解析每一个<{}>都只运行一次 parseYs
    private $literal = array();
    private $nocache = array();
    private $curr_temp = '';
    private $num = array('volist'=>0, 'for'=>0, 'foreach'=>0);

    //必须注意顺序，键长的放在前面，不然都让他替换了，如：eq
    private $comparison = array('neq'=>'!=','nheq'=>'!==','heq'=>'===','eq'=>'==','notequal'=>'!=','equal'=>'==','egt'=>'>=','gt'=>'>','elt'=>'<=','lt'=>'<');

    public function __construct(){
        $this->config['cache_path']         =   C('CACHE_PATH');
        $this->config['template_suffix']    =   C('TMPL_TEMPLATE_SUFFIX');
        $this->config['cache_suffix']       =   C('TMPL_CACHFILE_SUFFIX');
        $this->config['tmpl_cache']         =   C('TMPL_CACHE_ON');
        $this->config['cache_time']         =   C('TMPL_CACHE_TIME');
        // $this->config['taglib_begin']       =   $this->stripPreg(C('TAGLIB_BEGIN'));
        // $this->config['taglib_end']         =   $this->stripPreg(C('TAGLIB_END'));
        $this->config['tmpl_begin']         =   $this->stripPreg(C('TMPL_L_DELIM'));
        $this->config['tmpl_end']           =   $this->stripPreg(C('TMPL_R_DELIM'));
        $this->config['default_tmpl']       =   C('TEMPLATE_NAME');

        $this->config['layout_path']       =   C('LAYOUT_PATH');
        $this->config['layout_name']       =   C('LAYOUT_NAME');
        $this->config['layout_item']        =   C('LAYOUT_ITEM');  //标识：<{__CONTENT__}>
        $this->config['layout_suffix']        =   C('LAYOUT_SUFFIX');
        
        $this->config['html_cache_on']      =   C('HTML_CACHE_ON');
        $this->config['html_cache_time']    =   C('HTML_CACHE_TIME');
        $this->config['html_file_suffix']   =   C('HTML_FILE_SUFFIX');
    }

    /**
     * 设置模板路径
     * @param string $dir 目录路径
     */
    public function setTemplates_dir($dir){
        $this->templates_dir = $dir;
    }

    /**
     * 设置编译后的文件存放路径
     * @param string $dir 目录路径
     */
    public function setComplie_dir($dir){
        $this->complie_dir = $dir;
    }

    /**
     * 赋值到模板变量
     * @param  strign $key   键
     * @param  mixed  $value 值，支持对象、数组（默认最大支持四维数组），标量，bool...
     */
    public  function assign($key,$value){
        $this->_tpl_var[$key] = $value;
    }

    /**
     * 编译并显示模板
     * @param  strign $template 模版文件名
     */
    public function display($template, $char='', $type=''){
        //当前请求的URL部分，不包括请求字符串
        $this->_tpl_var['url'] = $_SERVER['REQUEST_URI'];
        $this->_tpl_var['root'] = ROOT;
        //当前使用的模板名
        $this->curr_temp = $template;
        $compFile = $this->complie($template, $char, $type);
        
    }

    /**
     * 编译模板，但不显示
     * @param  string $template 模板名
     * @return string           编译后的模板的存放地址
     */
    public function complie($template='', $char='', $type=''){
        $tempFile = I($template, false);
        if (!is_file($tempFile)) exit('模板'.$tempFile . '文件不存在');

        //注意：编译缓存和静态缓存只能够选择开启其中一项
        $compFile = $this->config['cache_path'] . md5($template) . $this->config['cache_suffix'];
        //没有开启静态缓存，但开启了编译缓存，且编译文件未过期，为0则永不过期
        if (!C('HTML_CACHE_ON') && $this->config['tmpl_cache'] && file_exists($compFile) && ($this->config['cache_time'] === 0 | time() < ($this->config['cache_time'] + filemtime($compFile)))) {
            include($compFile);
        }
        if (C('HTML_CACHE_ON')) {//开启了静态缓存
            $htmlFile = $this->config['cache_path'] . md5($template) . $this->config['html_file_suffix'];  //静态缓存保存路径
            //静态缓存文件存在，且不过期，为0则永不过期
            if (file_exists($htmlFile) && ($this->config['html_cache_time'] === 0 | time() < ($this->config['html_cache_time'] + filemtime($htmlFile)))) {
                include($htmlFile);
            } 
        }
        //获取模板内容
        $source = file_get_contents($tempFile);

        $source .= '<?php defined("FRAME_PATH")||exit("ACC Denied"); ?>'; //添加访问控制

        $source = $this->parseBlock($source);
        $source = $this->parseNote($source);
        $source = $this->parseLiteral($source);    
        $source = $this->parsePhp($source);
        $source = $this->parseLayout($source);

        //要放在block，layout后，因为可能文档还不完整
        $source = $this->parseCharType($source, $char, $type);

        $source = $this->parseSwitch($source);

        $source = $this->parseTag($source);  //闭合标签
        $source = $this->parseTagS($source); //非闭合标签
        $source = $this->parse($source);     //解析模板变量

        $source = $this->parseContentReplace($source); //解析内容替换，替换一些常用的模板常量

        //还原literal标签原有内容，必须最后运行
        $source = $this->restoreLiteral($source);
        // $source = $this->parseNoCache($source);
        
        // $source = $this->restoreNoCache(ob_get_contents()); //还原局部不缓存内容
        // 
        file_put_contents($compFile, $source);

        ob_start();
        include($compFile);

        if (C('HTML_CACHE_ON')) { //开启静态缓存，且缓存过期了，不然前面就会return
            file_put_contents($htmlFile, ob_get_clean());   //$htmlFile在上面定义了
        }        
        
    }

    /**
     * 删除缓存文件
     * @param  string $template 要删除的模板名
     * @param  string $prefix   模板前缀
     * @return boolean           true：成功，false：失败
     */
    public function delCache($template, $prefix=''){
        if ($this->config['html_cache_on'])
            $filepath = $this->config['cache_path'] . $prefix . md5($template) . $this->config['html_file_suffix'];
        else
            $filepath = $this->config['cache_path'] . $prefix . md5($template) . $this->config['cache_suffix'];
        return unlink($filepath) ? true : false;
    }

    /**
     * 模板内容替换
     * @access protected
     * @param string $content 模板内容
     * @return string
     */
    protected function parseContentReplace($content) {
        // 系统默认的特殊变量替换
        $replace =  array(
            //以下路径都是相对于网站根目录的相对路径，可用于js, css, img
            '__TMPL__'      =>  __TMPL__,  // 项目模板目录
            '__ROOT__'      =>  __ROOT__,       // 当前网站地址（不含域名）
            '__APP__'       =>  __APP__,        // 当前项目地址（不含域名）
            '__SELF__'      =>  __SELF__,       // 当前页面地址（不含域名）
            '__ACTION__'    =>  __ACTION__,     // 当前操作地址（不含域名）
            '__URL__'       =>  __URL__,        // 会替换成当前模块的URL地址（不含域名）
            '__PUBLIC__'    =>  __TMPL__ . 'Public', // 站点公共目录（不含域名）
        );
        // 允许用户自定义模板的字符串替换
        if (is_array(C('TMPL_PARSE_STRING')) )
            $replace =  array_merge($replace, C('TMPL_PARSE_STRING'));
        return str_replace(array_keys($replace), array_values($replace), $content);
    }

    /**
     * 解析<meta http-equiv="Content-Type" content="text/html;charset=gbk">
     * @param  string $content 待解析的内容
     * @param  string $char    要设置的字符集，如：UTF-8, GBK
     * @param  string $type    要设置的MIME格式的类型，如：text/html, text/xml
     * @return string          解析后的内容
     */
    private function parseCharType($content, $char, $type){
        $preg = '/<meta\s*http-equiv="Content-Type" content="(.*?);charset=(.*?)">/i';
        if (preg_match($preg, $content, $matches)) {
            if (empty($char))
                $char = C('DEFAULT_CHARSET');
            if (empty($type))
                $type = C('TMPL_CONTENT_TYPE');
            return str_replace($matches[0], '<meta http-equiv="Content-Type" content="'.$type.';charset='.$char.'">', $content);
        } else {
            if (empty($char))
                $char = C('DEFAULT_CHARSET');
            if (empty($type))
                $type = C('TMPL_CONTENT_TYPE');
            return str_ireplace('<head>', '<head><meta http-equiv="Content-Type" content="'.$type.';charset='.$char.'">', $content);
        }
    }

    /**
     * 解析的外层，负责区分运行函数和模板变量，进行对应的解析，并把结果返回
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function parse($content){
        $preg = '/' . $this->ldelimit . '(.*?)?' . $this->rdelimit . '/i';
        return preg_replace_callback($preg, function($arr){
            $this->flag = true;   //每一次匹配<{}>里面的内容，都要开启解析运算符
            $this->ys_run = false; //保证每一次解析<{}>都只运行一次parseYs
            //三元运算符
            if (($pos = strpos($arr[1], '?')) !== false && strpos(substr($arr[1], $pos), ':') !== false)
                return $this->parseSanYuan($arr[1]);
            if (strpos($arr[1], ':') === 0 || strpos($arr[1], '~') === 0)//解析运行函数
                return $this->parseRunFunc($arr[1]);//是否输出由其内部处理
            return '<?php echo ' . $this->parseCore($arr[1]) . '; ?>';
        }, $content);
    }

    /**
     * 负责调度函数解析模板变量，注：此函数不处理"运行函数"
     * @param  string $str     待解析的内容
     * @return string          解析后的内容，可以直接echo输出的内容
     */
    private function parseCore($str){
        if (strpos($str, '$') === false) return $str;
        if ((($tmp = strpbrk($str, '+-/*|')) === false && strpos($str, ':') !== false) || (strpos(substr($str, 0, strpos($str, $tmp)), ':') !== false)) {//解析对象
            return $this->parseObject($str);
        } elseif (stripos($str, '$tpl.const.') !== false) {//解析常量
            return $this->parseConst($str);
        } elseif (stripos($str, '$tpl.') === 0) {//解析超全局数组
            return $this->parseGlobals($str);//<{$tll.d+$tp:get[3]-$t++|md5}>
        } elseif ((($tmp = strpbrk($str, '+-/*|')) === false && strpbrk($str, '.[') !== false) || (strpbrk(substr($str, 0, strpos($str, $tmp)), '.[') !== false)) {//解析数组 
            return $this->parseArray($str);
        } else {//解析变量
            return $this->parseVar($str);
        }
    }

    /**
     * 负责转义与正则冲突的字符，如：{ } /...防止用户自定义的模板标签定界符与正则冲突
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function stripPreg($str) {
        return str_replace(
            array('{','}','(',')','|','[',']','-','+','*','.','^','?'),
            array('\{','\}','\(','\)','\|','\[','\]','\-','\+','\*','\.','\^','\?'),
            $str);        
    }

    /**
     * 解析模板布局layout
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function parseLayout($content){//<layout name="new_layout" />
        if (!C('LAYOUT_ON')) return $content;
        $preg = '/<layout\s*name=([\'\"])(.*?)\1\/>/i';
        if (preg_match($preg, $content, $matches)) {
            $content = str_replace($matches[0], '', $content);  //去除标签
            $layout_name = $this->parseAttr('name', $matches[0]);  //获取使用的布局模板名
            $layout_file = $this->config['layout_path'] . $layout_name . $this->config['layout_suffix'];  //布局模板文件路径
            if (!file_exists($layout_file)) exit('布局文件不存在');
            $layout_com = file_get_contents($layout_file);   //获取布局模板内容
            $l_tab = $this->config['tmpl_begin'];
            $r_tab = $this->config['tmpl_end'];
            $tmpl_content = str_replace(C('TMPL_L_DELIM') . '__CONTENT__' . C('TMPL_R_DELIM'), $content, $layout_com);          //结合当前模板和布局模板内容
            $tmpl_content = $this->parse($tmpl_content);
            return $tmpl_content;
        } else {
            $layout_file = $this->config['layout_path'] . $this->config['layout_name'] . $this->config['layout_suffix'];  //布局模板文件路径
            if (!file_exists($layout_file)) exit('布局文件不存在');
            $layout_com = file_get_contents($layout_file);
            $tmpl_content = str_replace(C('TMPL_L_DELIM') . '__CONTENT__' . C('TMPL_R_DELIM'), $content, $layout_com);
            $tmpl_content = $this->parse($tmpl_content);
            return $tmpl_content;
        }
    }

    /**
     * 解析block标签
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function parseBlock($content){//<block name="left"></block>  <extend name="base" />
        $preg1 = '/<extend\s*name=([\"\'])(.*?)\1\s*\/>/i'; 
        if (preg_match($preg1, $content, $tmp) === 0) return $content; //匹配extend及其name属性值
        $file = $tmp[2];
        $path = I($file, false);
        $base_com = file_get_contents($path);//基础模板的内容

        $this->blocks = array();    //用来保存子模板的内容，数组的键是block的name属性值
        $preg2 = '/<block\s*name=([\"\'])(.*?)\1\s*>(.*?)<\/block>/is';
        preg_match_all($preg2, $content, $matches);//匹配所有的block标签
        //重组匹配的内容，让数组的键是block的name属性值
        for($i=0, $len=count($matches[2]); $i<$len; $i++){  
            $this->blocks[$matches[2][$i]] = $matches[3][$i];
        }
        //替换基础模板的block为子模板中对应的block的内容
        $preg3 = '/<block\s*name=([\"\'])(.*?)\1\s*>(.*?)<\/block>/is';
        $com = preg_replace_callback($preg3, function($args){
            return $this->blocks[$args[2]];
        }, $base_com);
        return $com;
    }

    /**
     * 解析注释
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function parseNote($content){
        $preg = array(
            '/'.$this->config['tmpl_begin'].'\/\/.*?'.$this->config['tmpl_end'].'/i',
            '/'.$this->config['tmpl_begin'].'\/\*.*\*\/?'.$this->config['tmpl_end'].'/is'
            );
        return preg_replace($preg, '', $content);
    }

    /**
     * 解析局部不缓存
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function parseNoCache($content){
        $preg = '/<nocache>(.*?)<\/nocache>/is';
        return preg_replace_callback($preg, function($args){
            $i = count($this->nocache);
            $this->nocache[$i] = $args[1];
            return "<!--###nocache{$i}###-->";
        }, $content);
        
    }

    /**
     * 还原局部不缓存内容，在实现静态化时调用
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function restoreNoCache($content){
        $preg = '/<!--###nocache(\d+)###-->/ie';
        return preg_replace($preg, "\$this->parse(\$this->nocache['\\1'])", $content);  //必须使用e模式修饰符
    }

    /**
     * 解析php标签
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function parsePhp($content){
        $preg = '/<php>(.*?)<\/php>/i';
        return preg_replace($preg, '<?php \1 ?>', $content);
    }

    /**
     * 替换literal标签，并保存其内容
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function parseLiteral($content){//<!--###literal{$i}###-->
        $preg = '/<literal>(.*?)<\/literal>/is';
        return preg_replace_callback($preg, function($args){
            $i = count($this->literal);
            $this->literal[$i] = $args[1];
            return "<!--###literal{$i}###-->";
        }, $content);

    }

    /**
     * 还原literal标签内容
     * @param  string $content 待解析的内容
     * @return string          解析后的内容
     */
    private function restoreLiteral($content){
        $preg  = '/<!--###literal(\d+)###-->/i';
        return preg_replace_callback($preg, function($args){
            $com = $this->literal[$args[1]];
            unset($this->literal[$args[1]]);
            return $com;
        }, $content);
    }


    /**
     * 负责把提供的变量名传递给提供的函数作参数，且参数的所在位置也可以自定义，使用：占位符（###）
     * @param  string $arr  匹配的字符串的函数部分，如：<{$tp->obj+9|md5|cha1}>，则是：|md5|cha1
     * @param  string $name 变量名，调用对应解析的函数得到的变量名
     * @return string       返回如：cha1(md5($this->_tpl_var['tp']->obj[3]+9))
     */
    private function parseHandle($han, $name){
        $arr = explode('|', substr($han, 1));
        $handles = C('TMPL_DENY_FUNC_LIST');//获取禁用函数列表
        $handles = explode(',', $handles);
        for($i=0, $len=count($arr); $i<$len; $i++){//解析一个<{}>，解析每一个|
            $str = trim($arr[$i]);  //|前后可以有任意个空格
            if (stripos($str, '=') !== false) {//找到了=，说明有传参
                $abb = explode('=', $str);//分割date="y-m-d",###，$abb[0]是函数名
                if (in_array($abb[0], $handles)) continue;//如果函数名存在于函数禁用列表中，则跳过！
                if (stripos($abb[1], '###') !== false) {
                    $str2 = str_replace('###', $name , $abb[1]);
                    $name = $abb[0] . '(' . $str2 . ')';
                } else {
                    $name = $abb[0] . '(' . $name . ', ' . $abb[1] . ')';  //作为第一个参数
                }
            } else {
                $name = $str . '(' . $name . ')';
            }
        }
        return $name;
    }

    /**
     * 解析超全局数组：$_GET,$_POST,$_SERVER...（默认最多支持三维数组），不能与对象一起用，如：$tpl.get[b]:c
     * @param  string $content 要替换的内容
     * @return string          替换后的内容
     */
    private function parseGlobals($content){
        //  [.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?   解析一维数组
        $preg = '/\s*\$tpl\.(get|post|cookie|session|request|server)[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?\s*((\+|-|\*|\/|%|\+|-)\s*(?:[^\s|]*))?\s*(?:(\|\s*(default=)\s*[\'\"](.*?)?[\'\"])|(\|.*))?\s*/i';
        $return = preg_replace_callback($preg, function($arr){
            $ys = '';  //默认为空，如果有运算符，解析后自动赋予给这个变量
            if (!$this->ys_run) {//保证只运行一次 parseYs
                if (($tmp = $this->parseYs($arr)) === false) {//这个<{}>没有运算符，如：<{$tpl.get}>
                    $arr = array_diff($arr,array(null,''));
                    $arr = array_merge($arr);
                    $num = count($arr);   //仅仅是解析操作符的模板变量名来到，所以不需要-1
                } else {//这个<{}>有运算符，解析完毕，取得解析结果
                    $arr = $tmp['arr'];//包含最左边的模板变量名，函数/default；如：$tpl.get[7]|md5
                    $ys = $tmp['ys'];//$tpl.get[7]+$tpl.get[3]+$tpl.get[5]|md5中的"+$tpl.get[3]+$tpl.get[5]"解析结果
                }
            }
            if ($this->flag) {//单纯为了解析变量名
                $arr = array_diff($arr,array(null,''));
                $arr = array_merge($arr);
                $num = count($arr);  //default/函数的占位，所以变量名要少一个，运算符的项已经在解析运算符时被删除
            } else {
                //解析运算符完毕，此时是最后一次解析，包括解析最左边的变量名（在最左边变量名右边的都提供给parseYs解析了）、default、函数
                //此时已经得到了parseYs解析结果，包括$arr,$ys，也包括这个<{}>没有运算符$str=''这个可能性
                $len = count($arr);
                //计算在数组$arr中，属于模板变量名的
                if (strpos($arr[$len-1],'|') === false)//没有default/函数
                    $num = $len;
                else
                    $num = $len-1;
            }
            $one = strtoupper($arr[1]);
            $str = '$_' . $one;
            for($i=2; $i<$num; $i++){
                if (strpos($arr[$i], '|') !== false) break;
                if (is_numeric($arr[$i]))
                    $str .= '[' . $arr[$i] . ']';
                else
                    $str .= '[\'' . $arr[$i] . '\']';
            }
            if ($this->flag === false && !in_array('default=', $arr) && (strpos($arr[0], '|') !== false)) {
                $str = $str . $ys;
                return $this->parseHandle($arr[count($arr)-1], $str);
            } else {
                if ($this->flag === false && (($pos = array_search('default=', $arr)) !== false)) {//有默认值
                    eval('$abb = isset(' . $str . ');');
                    if ($abb)   //有默认值，变量存在
                        return $str . $ys;
                    else    //有默认值，变量不存在，使用默认值
                        return '\'' . $arr[$pos+1] . '\'';
                } else {//没有默认值
                    return $str . $ys;
                }
            }
        }, $content, -1, $num);
        if ($num !== 0) 
            return $return;
        else
            return false;//说明没有匹配
    }

    /**
     * 解析常量，包括用户常量和PHP自身的常量
     * @param  string $content 要替换的内容，<{$tpl.const.APP}>
     * @return string          替换后的内容
     */
    private function parseConst($content){
        $preg = '/\s*\$tpl\.const\.([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*((\+|-|\*|\/|%|\+|-)\s*(?:[^\s|]*))?\s*(?:(\|\s*(default=)\s*[\'\"](.*?)?[\'\"])|(\|.*))?\s*/i';
        $return = preg_replace_callback($preg, function($arr){
            $ys = '';  //默认为空，如果有运算符，解析后自动赋予给这个变量
            if (!$this->ys_run) {//保证只运行一次 parseYs
                if (($tmp = $this->parseYs($arr)) === false) {//这个<{}>没有运算符，如：<{$tpl.get}>
                    $arr = array_diff($arr,array(null,''));
                    $arr = array_merge($arr);
                    $num = count($arr);   //仅仅是解析操作符的模板变量名来到，所以不需要-1
                } else {//这个<{}>有运算符，解析完毕，取得解析结果
                    $arr = $tmp['arr'];//包含最左边的模板变量名，函数/default；如：$tpl.get[7]|md5
                    $ys = $tmp['ys'];//$tpl.get[7]+$tpl.get[3]+$tpl.get[5]|md5中的"+$tpl.get[3]+$tpl.get[5]"解析结果
                }
            }
            if ($this->flag) {//单纯为了解析变量名
                $arr = array_diff($arr,array(null,''));
                $arr = array_merge($arr);
                //是单纯为了解析变量名，所以 $ys=''
                $num = count($arr);  //default/函数的占位，所以变量名要少一个，运算符的项已经在解析运算符时被删除
            } else {
                //解析运算符完毕，此时是最后一次解析，包括解析最左边的变量名（在最左边变量名右边的都提供给parseYs解析了）、default、函数
                //此时已经得到了parseYs解析结果，包括$arr,$ys，也包括这个<{}>没有运算符$str=''这个可能性
                $len = count($arr);
                //计算在数组$arr中，属于模板变量名的
                if (strpos($arr[$len-1],'|') === false)//没有default/函数
                    $num = $len;
                else
                    $num = $len-1;
            }
            $str = $arr[1] . $ys;
            if ($this->flag === false && !in_array('default=', $arr) && strpos($arr[0], '|') !== false) {
                return $this->parseHandle($arr[count($arr)-1], $str);
            } else {
                if ($this->flag === false && (($pos = array_search('default=', $arr)) !== false)) {//有默认值
                    if (defined($arr[1]))//常量存在
                        return $str;
                    else//常量不存在，使用默认值
                        return '\'' . $arr[$pos+1] . '\'';
                } else {//没有默认值
                    return $str;
                }
            }
        }, $content, -1, $num);
        if ($num !== 0) 
            return $return;
        else
            return false;//说明没有匹配
    }

    /**
     * 解析普通变量
     * @param  string $content 要替换的内容，<{$a}>
     * @return string          替换后的内容
     */
    private function parseVar($content){
        $preg = '/\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*((\+|-|\*|\/|%|\+|-)\s*(?:[^\s|]*))?\s*(?:(\|\s*(default=)\s*[\'\"](.*?)?[\'\"])|(\|.*))?\s*/i';
        return preg_replace_callback($preg, function($arr){
            $ys = '';  //默认为空，如果有运算符，解析后自动赋予给这个变量
            if (!$this->ys_run) {//保证只运行一次 parseYs
                if (($tmp = $this->parseYs($arr)) === false) {//这个<{}>没有运算符，如：<{$tpl.get}>
                    $arr = array_diff($arr,array(null,''));
                    $arr = array_merge($arr);
                } else {//这个<{}>有运算符，解析完毕，取得解析结果
                    $arr = $tmp['arr'];//包含最左边的模板变量名，函数/default；如：$tpl.get[7]|md5
                    $ys = $tmp['ys'];//$tpl.get[7]+$tpl.get[3]+$tpl.get[5]|md5中的"+$tpl.get[3]+$tpl.get[5]"解析结果
                }
            }
            if ($this->flag) {//单纯为了解析变量名
                $arr = array_diff($arr,array(null,''));
                $arr = array_merge($arr);
            }

            $str = '$this->_tpl_var[\'' . $arr[1] . '\']' . $ys;
            if ($this->flag === false && !in_array('default=', $arr) && strpos($arr[0], '|') !== false) {
                return $this->parseHandle($arr[count($arr)-1], $str);
            } else {
                if ($this->flag === false && (($pos = array_search('default=', $arr)) !== false)) {//有默认值
                    if (isset($this->_tpl_var[$arr[1]]))//变量存在
                        return $str;
                    else//变量不存在，使用默认值
                        return '\'' . $arr[$pos+1] . '\'';
                } else {//没有默认值
                    return $str;
                }
            }
        }, $content);
    }

    /**
     * 解析数组（多维数组|索引数组）最多三维数组，可自行增加，不能与对象一起用，如：$a[b]:c
     * @param  string $content 要替换的内容，<{$tl.cdonst[5]|default="9"}>
     * @return string          替换后的内容
     */
    private function parseArray($content){
        //  |  优先级最低
        //[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?   //匹配一维,每增加一次，则数组可以解析多一维
        $preg = '/\s*\$(?!tpl\.)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)[.\[]{1}([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?\s*((\+|-|\*|\/|%|\+|-)\s*(?:[^\s|]*))?\s*(?:(\|\s*(default=)\s*[\'\"](.*?)?[\'\"])|(\|.*))?\s*/i';
        $return = preg_replace_callback($preg, function($arr){
            $ys = '';  //默认为空，如果有运算符，解析后自动赋予给这个变量
            if (!$this->ys_run) {//保证只运行一次 parseYs
                if (($tmp = $this->parseYs($arr)) === false) {//这个<{}>没有运算符，如：<{$tpl.get}>
                    $arr = array_diff($arr,array(null,''));
                    $arr = array_merge($arr);
                } else {//这个<{}>有运算符，解析完毕，取得解析结果
                    $arr = $tmp['arr'];//包含最左边的模板变量名，函数/default；如：$tpl.get[7]|md5
                    $ys = $tmp['ys'];//$tpl.get[7]+$tpl.get[3]+$tpl.get[5]|md5中的"+$tpl.get[3]+$tpl.get[5]"解析结果
                }
            }
            if ($this->flag) {//单纯为了解析变量名
                $arr = array_diff($arr,array(null,''));
                $arr = array_merge($arr);
            }
            $arr = array_diff($arr,array(null,''));
            $arr = array_merge($arr);
            $str = '$this->_tpl_var';
            for($i=1, $len=count($arr); $i<$len; $i++){
                if (strpos($arr[$i], '|') !== false) break;
                if (is_numeric($arr[$i]))
                    $str .= '[' . $arr[$i] . ']';
                else
                    $str .= '[\'' . $arr[$i] . '\']';
            }
            if ($this->flag === false && !in_array('default=', $arr) && (strpos($arr[0], '|') !== false)) {
                $str .= $ys;
                return $this->parseHandle($arr[count($arr)-1], $str);
            } else {
                if ($this->flag === false && (($pos = array_search('default=', $arr)) !== false)) {//有默认值
                    eval('$abb = isset(' . $str . ');');
                    if ($abb)
                        return $str . $ys;
                    else
                        return '\'' . $arr[$pos+1] . '\'';
                } else {
                    return $str . $ys;
                }
            }
        }, $content, -1, $num);
        if ($num !== 0) 
            return $return;
        else
            return false;//说明没有匹配
    }

    /**
     * 解析对象（最多支持二维对象，同时也支持到和三维数组合用）
     * @param  string $content 要替换的内容
     * @return string          替换后的内容
     */
    private function parseObject($content){
        $preg = '/\s*\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*):([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(?::([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))?[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?[.\[]?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|\d*)?\]?\s*((\+|-|\*|\/|%|\+|-)\s*(?:[^\s|]*))?\s*(?:(\|\s*(default=)\s*[\'\"](.*?)?[\'\"])|(\|.*))?\s*/i';
        $return = preg_replace_callback($preg, function($arr){
            $ys = '';  //默认为空，如果有运算符，解析后自动赋予给这个变量
            if (!$this->ys_run) {//保证只运行一次 parseYs
                if (($tmp = $this->parseYs($arr)) === false) {//这个<{}>没有运算符，如：<{$tpl.get}>
                    $arr = array_diff($arr,array(null,''));
                    $arr = array_merge($arr);
                } else {//这个<{}>有运算符，解析完毕，取得解析结果
                    $arr = $tmp['arr'];//包含最左边的模板变量名，函数/default；如：$tpl.get[7]|md5
                    $ys = $tmp['ys'];//如：$tpl.get[7]+$tpl.get[3]+$tpl.get[5]|md5中的"+$tpl.get[3]+$tpl.get[5]"解析结果
                }
            }
            if ($this->flag) {//单纯为了解析变量名
                $arr = array_diff($arr,array(null,''));
                $arr = array_merge($arr);
            }
            $num = substr_count($arr[0], ':');//:出现的次数
            $start = 2 + $num;  //计算循环数组部分的起点位置
            $num = 1 + $num;    //计算循环对象部分的结束位置
            $str = '$this->_tpl_var[\''. $arr[1] .'\']';
            $flag = false;  //标志是否出现 |default |函数，为true存在
            for($j=2; $j<=$num; $j++)   //计算对象部分
                $str .= '->' . $arr[$j];
            for($i=$start; $i<count($arr); $i++){//计算数组部分
                if (strpos($arr[$i], '|') !== false) break;
                if (is_numeric($arr[$i]))
                    $str .= '[' . $arr[$i] . ']';
                else
                    $str .= '[\'' . $arr[$i] . '\']';
            }
            $str .= $ys;
            if ($this->flag === false && !in_array('default=', $arr) && (strpos($arr[0], '|') !== false)) {
                return $this->parseHandle($arr[count($arr)-1], $str);
            } else {
                if ($this->flag === false && (($pos = array_search('default=', $arr)) !== false)) {//有默认值
                    if (isset($this->_tpl_var[$arr[1]]->$arr[2]))//变量存在
                        return $str;
                    else//变量不存在，使用默认值
                        return '\'' . $arr[$pos+1] . '\'';
                } else {//没有默认值
                    return $str;
                }
            }
        }, $content, -1, $num);
        if ($num !== 0) 
            return $return;
        else
            return false;//说明没有匹配
    }

    /**
     * 提供一个数组，然后搜索在该数组中”运算符“的位置，如果不存在，返回false
     * @param  array $arr  要搜索的数组
     * @return mixed       为int，为运算符的位置，为false，说明没有找到运算符
     */
    private function parseYs($arr){
        $this->ys_run = true;//保证每一次解析<{}>都只运行一个parseYs
        $this->flag = true;//开启解析运算符进程
        $abb = array_intersect($arr, $this->ys);  //计算数组的交集
        $ys = implode('', $this->ys);  //字符串型的所支持运算符，如："+-*/++--"
        if (!empty($abb)) {//找到运算符
            $pos = key($abb);      //key() 返回数组中当前单元的键名。
            $str = $arr[$pos-1];   //得到所有的操作符   -$t+$n+$p   ++  --
            unset($arr[$pos]);
            unset($arr[$pos-1]);
            $wan = '';  //用来保存结果
            $quit = true;   //为false停止循环
            do{
                $dan_fu = substr($str, 0, 1);   //操作符：+, -, *, /, %.... 
                $shu = substr($str, 1);    //操作数，可能是多个操作数：$t+$n+$p，$p , +
                $tmp = strpbrk($shu, $ys); //剩余待解析的字符串中，以下一个操作符开始的字符串+$n+$p,false,+,-
                if ($tmp !== false) {//还有操作符待解析
                    $dan_shu = substr($shu, 0, strpos($shu, $tmp));   //单个操作数
                    if ($dan_shu === '') {//来到这里说明是++,--，则只匹配一次$a++$b没有意义
                        $name = $dan_fu;    //+,-
                        $quit = false;      //++,--只能放在最后，而不能<{$a++$b}>，没有意义
                        $this->flag = false; 
                    } else {
                        $name = $this->parseCore($dan_shu);
                        $str = $tmp;
                    }
                    $wan .= $dan_fu . $name;
                } else {//已经没有操作符解析，这是最后一次循环，注意：++,--这两种情况是不会来到这里的
                    $quit = false;
                    //来到这里，$shu就是最后的操作数
                    if (is_numeric($shu))//操作数是数字
                        $name = $shu;
                    else//操作数是模板变量名
                        $name = $this->parseCore($shu);
                    $this->flag = false;
                    $wan .= $dan_fu . $name;
                }
            }while($quit);
            // 数组除空值，且重新索引
            $arr = array_diff($arr,array(null,''));
            $arr = array_merge($arr);
            return array('arr'=>$arr, 'ys'=>$wan);
        } else {//没有找到运算符，关闭解析运算符进程，退出函数
            $this->flag = false;
            return false;
        }
    }

    /**
     * 解析执行函数，有返回值的，没有返回值的
     * @param  string $content 要替换的内容，<{:U('User/insert')}>
     * @return string          替换后的内容
     */
    private function parseRunFunc($content){
        $preg = '/([:~])([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\((.*)?\)/i';
        return preg_replace_callback($preg, function($arr){
            $return = '<?php ';
            if ($arr[1] === ':')
                $return .= 'echo ' . $arr[2] . '(' . $arr[3] . '); ?>';
            elseif($arr[1] === '~')
                $return .= $arr[2] . '(' . $arr[3] . '); ?>';
            return $return;
        }, $content);
    }

    /**
     * 解析三元运算符
     * @param  string $content 要替换的内容
     * @return string          替换后的内容
     */
    private function parseSanYuan($content){
        $preg = '/^\s*(.*?)\s*\?\s*(.*?)\s*:\s*(.*?)\s*$/i';
        return preg_replace_callback($preg, function($args){
            $one = $this->parseCondition($args[1]);
            $two = strpos($args[2], '$') !== false ? $this->parseCore($args[2]) : $args[2];
            $three = strpos($args[3], '$') !== false ? $this->parseCore($args[3]) : $args[3];
            return "<?php echo {$one}?{$two}:{$three};?>";
        }, $content);
    }




    /**
     * 获取一个XML节点字符串中的属性：如：<load href="$tp.css" /> 中的 href 属性
     * @param  string $attr    属性名
     * @param  string $str     节点字符串：<load href="$tp.css" />
     * @return string          属性值，如果不存在，则为NULL
     */
    private function parseAttr($str){
        $sxml = simplexml_load_string($str);
        $attrs = $sxml->attributes();
        $arr = array();
        foreach($attrs as $k=>$v){
            $arr[$k] = (string) $v;
        }
        return $arr;
    }

    /**
     * 解析switch标签
     * @param  string $content 待解析内容
     * @return string          解析后内容
     */
    private function parseSwitch($content){
        $preg = '/<switch\s*name=([\'\"])(.*?)\1>(.*)<\/switch>/is';
        return preg_replace_callback($preg, function($args){
            if (preg_match('/<default\s*\/>(.*)<\/switch>/is', $args[0], $matches) !== 0)//有默认值
                $default = $matches[1];//如果没有默认值，则不会有变量$default

            $name = $this->parseCore('$' . $args[2]);//要判断的模板变量名
            $xml = simplexml_load_string($args[0]);
            $child = $this->getXmlChild('case', $xml);//得到所有的case、其属性、其值构成的数组

            $str = "<?php switch({$name}){";            
            for($i=0, $len=count($child); $i<$len; $i++){
                $value = $child[$i]['attr']['value'];
                $vars_name = array();//用来保存所有解析后的case的判断值
                if (strpos($value, '|') !== false) { //多个变量
                    $vars = explode('|', $value);
                    for($j=0, $len_j=count($vars); $j<$len_j; $j++){
                        if (strpos($value, '$') !== false) //模板变量
                            $vars_name[] = $this->parseCore($vars[$j]);
                        else //普通值
                            $vars_name[] = '"'. $vars[$j] . '"';
                    }
                } else {//单个变量
                    if (strpos($value, '$') !== false) 
                        $vars_name[] = $this->parseCore($value); //模板变量
                    else //普通值
                        $vars_name[] = '"' . $value . '"';
                }
                if (isset($child[$i]['attr']['break']) && $child[$i]['attr']['break'] == 0) {//存在break且值为0
                    for($x=0, $len_x=count($vars_name); $x<$len_x; $x++)
                        $str .= "case {$vars_name[$x]}: echo \"{$child[$i]['value']}\";break;";
                } else {//不存在break
                    for($x=0, $len_x=count($vars_name); $x<$len_x; $x++)
                        $str .= "case {$vars_name[$x]}: echo \"{$child[$i]['value']}\";";
                }
            }
            if (isset($default))//在有默认值的情况下添加default
                $str .= "default: echo \"{$default}\";";
            $str .= '} ?>';
            return $str;
        }, $content);
    }

    /**
     * 解析闭合标签
     * @param  string $content 待解析内容
     * @return string          解析后内容
     */
    private function parseTag($content){
        $preg = '/<(elseif|else|include|load).*\/>/i';
        return preg_replace_callback($preg, function($args){
            $attr = $this->parseAttr($args[0]);
            switch(strtolower($args[1])){
                case 'else':
                    return '<?php } else { ?>';
                case 'elseif':
                    $condition = $this->parseCondition($attr['condition']);
                    return "<?php } elseif ({$condition}) { ?>";
                case 'include':
                    $file = $attr['file'];
                    $str = '<?php ';
                    $arr = explode(',', $file);
                    for($i=0, $len=count($arr); $i<$len; $i++){
                        if (strpos($arr[$i], '$') !== false) {
                            $file = $this->parseCore($arr[$i]);
                        } else {//[模块名:方法名][方法名][路径]
                            $file = I($arr[$i], false);
                        }
                        $str .= 'include "' . $file . '";';
                    }
                    return $str . '?>';
                case 'load'://只支持js,css
                    $href = $attr['href'];
                    $ext = substr($href, strrpos($href, '.')+1);
                    $file = implode('/', array_map(array(__CLASS__, 'parseCore'), explode('/', $href)));
                    if ($ext === 'js')
                        return '<script src="' . $file . '"></script>';
                    if ($ext === 'css')
                        return '<link rel="stylesheet" href="' . $file . '">';
            }
        }, $content);
    }

    /**
     * 解析非闭合标签
     * @param  string $content 待解析内容
     * @return string          解析后内容
     */
    private function parseTagS($content){//开始标签|结束标签
        //注意标签名位置：如果标签名neq包含另外一个标签名eq的全部，则把neq放在前面，eq放在其后，如：neq|eq
        //不然eq也会匹配标签neq
        $preg = '/(?:<(notdefined|defined|notempty|empty|notpresent|present|if|compare|neq|notequal|equal|eq|egt|elt|gt|lt|nheq|heq|range|notin|in|notbetween|between|foreach|for|volist).*?>)|(?:<(\/(?:notdefined|defined|notempty|empty|notpresent|present|if|compare|neq|notequal|equal|eq|egt|elt|gt|lt|nheq|heq|range|notin|in|notbetween|between|foreach|for|volist))>)/i';
        return preg_replace_callback($preg, function($args){
            if (isset($args[2])) {
                $type = strtolower($args[2]);
            } else {
                $xml = $args[0] . '</' . $args[1] . '>';
                $attr = $this->parseAttr($xml);
                if ('range' == strtolower($args[1])) 
                    $type = strtolower($attr['type']);
                else 
                    $type = strtolower($args[1]);
            }
            switch($type){
                case 'notdefined':
                    return "<?php if (!defined('{$attr['name']}')) { ?>";
                case 'defined':
                    return "<?php if (defined('{$attr['name']}')) { ?>";
                case 'notempty':
                    $name = $this->parseCore('$' . $attr['name']);
                    return "<?php if (!empty({$name})) { ?>";
                case 'empty':
                    $name = $this->parseCore('$' . $attr['name']);
                    return "<?php if (empty({$name})) { ?>";    
                case 'notpresent'://判断模板变量是否存在
                    $name = $this->parseCore('$' . $attr['name']);
                    return "<?php if (!isset({$name})) { ?>";
                case 'present'://判断模板变量是否存在
                    $name = $this->parseCore('$' . $attr['name']);
                    return "<?php if (isset({$name})) { ?>";
                case 'if':
                    $condition = $this->parseCondition($attr['condition']);
                    return "<?php if ({$condition}) { ?>";
                case 'compare':
                case 'eq':
                case 'equal':
                case 'notequal':
                case 'neq':
                case 'gt':
                case 'lt':
                case 'egt':
                case 'elt':
                case 'heq':
                case 'nheq':
                    return $this->parseCompare($type, $attr['name'], $attr['value']);

                case 'in':
                case 'notin':
                    $handle = $type == 'in' ? 'in_array' : '!in_array';
                    $name = $this->parseCore('$' . $attr['name']);
                    if (strpos($attr['value'], ',') !== false)
                        return "<?php if ({$handle}({$name}, explode(',','{$attr['value']}'))) { ?>";
                    if (strpos($attr['value'], '$') !== false) {
                        $val = $this->parseCore($attr['value']);
                        return "<?php if (({$handle}({$val})&&in_array({$name},{$val}))||{$name}=={$val}) { ?>";
                    }

                case 'between':
                case 'notbetween':
                    $name = $this->parseCore('$' . $attr['name']);
                    if (strpos($attr['value'], ',') !== false)
                        $vals = explode(',', $attr['value']);
                    if (strpos($vals[0], '$') !== false) $vals[0] = $this->parseCore($vals[0]);
                    if (strpos($vals[1], '$') !== false) $vals[1] = $this->parseCore($vals[1]);
                    if ('between' == $type)
                        return "<?php if (({$vals[0]}>{$vals[1]}&&{$vals[0]}>{$name}&&{$vals[1]}<{$name})||{$vals[0]}<{$vals[1]}&&{$vals[1]}>{$name}&&{$vals[0]}<{$name}) { ?>";
                    if ('notbetween' == $type)
                        return "<?php if (!(({$vals[0]}>{$vals[1]}&&{$vals[0]}>{$name}&&{$vals[1]}<{$name})||({$vals[0]}<{$vals[1]}&&{$vals[1]}>{$name}&&{$vals[0]}<{$name}))) { ?>";

                case 'foreach':
                    $name = $this->parseCore('$' . $attr['name']);
                    $item = $this->parseCore('$' . $attr['item']);
                    $key = isset($attr['key']) ? $attr['key'] : false;//键
                    if ($key) $key = $this->parseCore('$' . $key);
                    if ($key)
                        return '<?php foreach(' . $name . ' as ' . $key . '=>' . $item . '){ ?>';
                    else
                        return '<?php foreach(' . $name . ' as ' . $item . '){ ?>'; 
                case 'for':
                    $this->num['for']++;
                    $start = $attr['start'];
                    if (!is_numeric($start)) $start = $this->parseCore($start);
                    $end = $attr['end'];
                    if (!is_numeric($end)) $end = $this->parseCore($end);
                    $name = isset($attr['name']) ? $attr['name'] : 'i' . $this->num['for'];
                    $name = $this->parseCore('$' . $name);
                    $step = isset($attr['step']) ? $attr['step'] : 1;
                    if (!is_numeric($step)) $step = $this->parseCore($step);
                    $comparison = isset($attr['comparison']) ? $this->comparison[$attr['comparison']] : '<';
                    return '<?php for('.$name.'='.$start.'; '.$name . $comparison . $end. '; ' . $name . '+=' . $step . '){ ?>';
                case 'volist':
                    $this->num['volist']++;
                    $num = $this->num['volist'];
                    $level = 'iii' . $num;
                    $name = $attr['name'];
                    $str = '<?php ';
                    // 允许使用函数设定数据集 <volist name=":fun('arg')" id="vo">{$vo.name}</volist>
                    if (strpos($name,':') === 0) {
                        $str = '$_result='.substr($name,1).';';
                        $name   = '$_result';
                    } else {
                        $name = $this->parseCore('$' . $name);  //解析模板变量名
                    }
                    $id = $attr['id']; //循环变量
                    $offset = isset($attr['offset']) ? $attr['offset'] : 0; //偏移量
                    $length = isset($attr['length']) ? $attr['length'] : 0; //长度
                    $key = isset($attr['key']) ? $attr['key'] : 'i' . $num; //键
                    $modname = isset($attr['modname']) ? $attr['modname'] : 'mod' . $num; //取模结果
                    $empty = isset($attr['empty']) ? $attr['empty'] : ''; //如果数据为空显示的字符串

                    $this->_tpl_var['mod'.$num.$num] = isset($attr['mod']) ? $attr['mod'] : 2;

                    //在没有运行下端代码时，很多变量还不存在的！所以算不了长度，所以必须把源代码编译进去，而不是把结果
                    
                    $str .= '$data_len'.$num.' = count('.$name.');';
                    $str .= 'if ('.$length.'==0) {$length'.$num.' = $data_len'.$num.';} else {$length'.$num.'='.$length.';}';
                    $str .= '$num'.$num.' = '.$offset.' + $length'.$num.';';
                    $str .= 'if ($num'.$num.' > $data_len'.$num.') {$num'.$num.' = $data_len'.$num.';}';
                    $str .=  $name.'=array_slice('.$name.', '.$offset.', $length'.$num.');';
                    $str .= '$this->_tpl_var[\'data_key'.$num.'\'] = array_keys('.$name.');';
                    $str .= '$this->_tpl_var[\'data_val'.$num.'\'] = array_values('.$name.');';
                    $str .= 'if (empty('.$name.')){echo "'.$empty.'";} else {for($'.$level.'=' . $offset . '; $'.$level.'<$num'.$num.'; $'.$level.'+=1) {$this->_tpl_var[\'' . $id .'\'] = $this->_tpl_var[\'data_val'.$num.'\'][$'.$level.'];$this->_tpl_var[\'' .  $key. '\'] = $this->_tpl_var[\'data_key'.$num.'\'][$'.$level.'];$this->_tpl_var[\''.$modname.'\'] = $this->_tpl_var[\'data_key'.$num.'\'][$'.$level.'] % $this->_tpl_var[\'mod'.$num.$num.'\']; ?>';
                    return $str;

                case '/defined':
                case '/notdefined':
                case '/empty':
                case '/notempty':
                case '/present':
                case '/notpresent':
                case '/if':
                case '/compare':
                case '/eq':
                case '/equal':
                case '/notequal':
                case '/neq':
                case '/gt':
                case '/lt':
                case '/egt':
                case '/elt':
                case '/heq':
                case '/nheq':
                case '/range':
                case '/in':
                case '/notin':
                case '/between':
                case '/notbetween':
                case '/foreach':
                case '/for':
                    return '<?php } ?>';
                case '/volist':
                    return '<?php }} ?>';

            }
        }, $content);
    }

    /**
     * 获取一个SimpleXML对象，指定子节点的值和属性
     * @param  stirng $name 要获取的子节点的名字
     * @param  object $xml  SimpleXML对象
     * @return array        返回一个数组，包含子节点的值，和其属性的数组
     */
    public function getXmlChild($name, $xml){
        $child = array();
        foreach($xml->$name as $v){
            $tmp = array();
            $tmp['value'] = (string) $v;
            $tmp['name'] = $name;
            foreach($v->attributes() as $x=>$y){
                $tmp['attr'][$x] = (string) $y;
            }
            $child[] = $tmp;
        }   
        return $child;
    }

    /**
     * 解析条件表达式
     * @param  string $condition 要解析的条件表达式
     * @return string            解析后的条件表达式
     */
    private function parseCondition($condition){
        $condition = str_ireplace(array_keys($this->comparison), array_values($this->comparison), $condition);
        $preg = '/\$.*(?:[\)\s])|(?:\$.*$)/i';
        return preg_replace_callback($preg, function($args){
            return $this->parseCore($args[0]);
        }, $condition);
    }

    /**
     * 负责组装返回 比较标签 应该被替换的值
     * @param  string $type  比较类型
     * @param  string $name  模板变量名
     * @param  string $value 要比较的值
     * @return string        解析后应该被替换的值
     */
    private function parseCompare($type, $name, $value){
        $name = $this->parseCore('$' . $name);
        $fu = $this->comparison[strtolower($type)];
        if (strpos($value, '$') !== false) $value = $this->parseCore($value);
        return "<?php if ({$name}{$fu}{$value}) { ?>";
    }    
}  