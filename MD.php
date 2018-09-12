
<?php
	Header('Content:text/html;charset=utf-8');
?>
<style>
	body,ol,ul,li,img,p{
		padding:0;
		margin:0;
		border:none;
	}

	li{
		list-style-type: square;
		padding:2px;
	}

	h1,h2,h3,h4,h5,h6{
		margin:10px 0 0 0;
	}

	h1,h2,h3{
		border-bottom:solid 1px #ddd;
	}

	h1{
		padding-bottom:12px;
	}

	h2{
		padding-bottom: 6px;
	}

	h3{
		padding-bottom: 2px;
	}

	body,ul{
		padding:10px 20px;
	}

	blockquote{
		border-left:solid 4px #bbb;
		background-color:#eee;
		min-height:24px;
		line-height:24px;
		padding:0px 10px;
		margin:0;
		color:#333;
		font-size:12px;
		font-style: italic;
		font-family: consolas,楷体;
	}

	table,td,th{
		border:solid 1px #ddd;
		border-collapse: collapse;
		padding:10px;
		font-family: consolas;
		font-size:14px;
	}

	table{
		margin:10px 0;
		text-align:center;
	}

	.table-left{
		text-align:left;
	}

	.table-center{
		text-align: center;
	}

	.table-right{
		text-align: right;
	}

	tr:nth-child(even){
		background-color:#eee;
	}
	
	code,pre{
		border-radius:5px;
		border:solid 1px #eee;
		background-color:#f9f9f9;
		font-family: consolas;
		font-size:14px;
		box-shadow: 1px 2px 3px #ddd;
	}

	code{
		padding:2px 12px;
		display: inline-block;
		margin:10px 0;
	}

	pre{
		padding:10px 12px;
	}
</style>
<?php
	/*
	*	flag规则:
	*	h1-h6	标题
	*	u1,u2 	无序列表
	*	ol 		有序列表
	*	p     	普通文本
	*	code 	代码
	*	table 	文本
	*	check   选择
	*	uncheck	未选择
	*	quote   引用
	*	img 	图片
	*	url 	网址
	*	bold 	加粗
	*	italia  斜体
	*	el    	空行
	*
	*	idel 	闲置
	*/
class MD{

	private $file;			//当前文件
	private $mdGen;			//生成器
	private $flag;			//当前标志
	private $flag_pre;		//上一次的flag
	private $flag_like;		//假定类型

	private $line_count;	//总行数
	private $lines;			//所有行
	private $buf;			//缓存行

	public function __construct($filename){
		$this->file = $filename;			//设置当前文件
		$this->mdGen = $this->content($filename);	//获取生成器
		$this->flag = 'idel';				//闲置
		$this->lines = array();				//所有行状态
		$this->line_count = 0;				//行数归0
	}

	private function content($file){	//获取生成器
		if(file_exists($file)){
			$fh = fopen($file,"r");
			while(!feof($fh)){
				yield fgets($fh);
			}
			fclose($fh);
		}else{
			return null;
		}
	}

	//遍历每一个字符
	private function every($str){
		$l = strlen($str);
		for($i=0;$i<$l;$i++)
			if(ord($str[$i])<0x80)
				yield $str[$i];
			else{
				yield $str[$i].$str[$i+1].$str[$i+2];	//三个字节
				$i+=2;
			}
	}

	//解析
	private function parse(){
		$ctnt = $this->mdGen;	//生成器
		$ret=array();
		if($ctnt != null){
			$ret = array();
			foreach($ctnt as $line){
				if($this->flag != 'code')
					$line=ltrim($line);	//删除左边
				if(strlen($line)){
					$ch = $line[0];	//判断第一个字符
					$type=ctype_punct($ch);//标点符号
					if($this->flag !== 'code') $line=preg_replace('/\s{2,}$/','<br>',$line);	//非代码模式
					$ret[] = $this->parseLine($line,$type);	//解析行
				}else{	//空行
					switch($this->flag){
						case 'ul':
						case 'ol':
							$ret[]="</$this->flag>";
							$this->flag_pre = $this->flag;
							$this->flag = 'idel';
						break;
						case 'table':
							$this->flag = 'idel';
							$ret[]='</table>';
						break;
						default:
							if($this->flag != 'el') $ret[] = '<br>';
							$this->flag = 'el';	//空行
					}
				}
			}
		}
		$this->lines = $ret;
		return $ret;
	}

	//生成blockquote
	private function genQuote($level,$content){
		if($level<=0) return "";
		return str_repeat('<blockquote>',$level).$content.str_repeat('</blockquote>',$level);
	}

	//检查是否处于某个状态,默认检查是否处于代码状态
	private function check($value='code'){
		return ($this->flag == $value);
	}

	/**
	 *	生成一行新的表格列
	 *	$data:需要输入的数据
	 *	$type:生成类型
	 *	$wrap:最快层包裹
	 *	$sep:分隔符
	 */
	private function genRow($data,$type='td',$wrap='tr',$sep='|'){
		$ret='';
		$mat = explode($sep,$data);
		foreach($mat as $elem)
			$ret.="<$type>".$elem."</$type>";
		return "<$wrap>".$ret."</$wrap>";
	}

	/**
	 *	解析行内元素
	 *	bold,italic,code,link,pic
	 */
	private function parseInner($str){
		$str=preg_replace('/\*\*\*([^\*\n\r]+)\*\*\*/','<b><i>\1</i></b>',$str);		//加粗并加斜字体
		$str=preg_replace('/\*\*([^\*\n\r]+)\*\*/','<b>\1</b>',$str);					//加粗字体
		$str=preg_replace('/\*([^\*\n\r]+)\*/','<i>\1</i>',$str);						//斜体
		$str=preg_replace('/```([^`\n\r]+)```/','<code>\1</code>',$str);					//代码
		$str=preg_replace('/`([^`\n\r]+)`/','<code>\1</code>',$str);						//代码
		$str=preg_replace('/!\[([^\]\r\n]+)\]\(([^\)\r\n]+)\)/','<img src="\2" alt="\1" title="\1">',$str);	//图片
		$str=preg_replace('/\[([^\]\r\n]+)\]\(([^\)\r\n]+)\)/','<a href="\2">\1</a>',$str);	//图片
		return $str;
	}

	/**
	 *	按照给定规则解析字符串:
	 *	type:
	 *	true	符号
	 *	false	文字
	 *	返回:
	 *	array('type'=>类型,'parts'=>array(...))
	 */
	private function parseLine($str,$type){
		if(strlen($str) == 0){
		 	return array('type'=>'p','parts'=>[]);	//返回空段
		}

		$valid_str = rtrim($str);	//去除右边的空格

		$str = $valid_str;
		$output = '';		//需要输出的字符串

		if($type == true){	//标点
			$type_str = '';	//类型字符串
			$data_str = '';
			$flag = 0;

			foreach($this->every($str) as $ch){
				if(!ctype_punct($ch)){
					$flag = 1;
				}
				if($flag == 0)
					$type_str .= $ch;
				else
					$data_str .= $ch;
			}

			switch($type_str){
				case '#':
					$wrap = 'h1';
					$this->flag_like = 'h1';
					break;
				case '##':
					$wrap = 'h2';
					$this->flag_like = 'h2';
					break;
				case '###':
					$wrap = 'h3';
					$this->flag_like = 'h3';
					break;
				case '####':
					$wrap = 'h4';
					$this->flag_like = 'h4';
					break;
				case '#####':
					$wrap = 'h5';
					$this->flag_like = 'h5';
					break;
				case '######':
					$wrap = 'h6';
					$this->flag_like = 'h6';
					break;
				case '+':
				case '-':
					$wrap = 'li';
					$this->flag_like = 'ul';
					if($this->flag != 'code'){
						if($this->flag != 'ul'){
							$this->flag_pre = $this->flag;
							$this->flag = 'ul';
							$output.='<ul>';
						}
					}
					break;
				case '```':	//代码模式,不进行干扰
					if($this->flag != 'code'){	//不为code,判断是否为单行代码
						$output.='<pre>';
						if(preg_match('/^\s*```([^`]+)```([^\s]+)\s*$/',$str,$mat)){	//单行代码
							$output.="$mat[1]</pre>$mat[2]";
							$str = '';
							$data_str = '';
						}else{
							$this->flag_pre = $this->flag;
							$this->flag = 'code';	//代码
						}
					}else{
						$output.='</pre>';
						$this->flag_pre = $this->flag;
						$this->flag = 'idel';						//闲置
					}
					break;
				case '[':
					if(preg_match('/^\[\s\]\s*(.+)$/',$str,$mat)){	//选择
						$output.="<label><input type='checkbox' disabled> $mat[1]</label><br>";
						$this->flag_like = 'check';
					}else if(preg_match('/^\[([^\]]+)\]\(([^\)]+)\)([^\s]+)\s*$/',$str,$mat)){	//未选择
						$output.="<a href='$mat[2]'>$mat[1]</a>".$this->parseInner($mat[3]);
						$this->flag_like = 'a';	//网址
					}else if(preg_match('/^\s*(\[\d+\]\:)\s+([^\s]+)\s+([^\r\n]+)\s*$/',$str,$mat)){	//外部资料
						$output.="$mat[1] <a href='$mat[2]'>$mat[3]</a>";
					}else{
						$output.=$str;
					}
					break;
				case '[-]':
					$output.="<label><input type='checkbox' checked disabled>$data_str</label><br>";
					$this->flag_like = 'check';
					break;
				case '![':
					if(preg_match('/^!\[([^\]]+)\]\(([^\)]+)\)([^\s]+)\s*$/',$str,$mat)){
						$output.="<img src='$mat[2]' alt='$mat[1]'>".$this->parseInner($mat[3]);
						$this->flag_like = 'img';
					}else{
						$this->flag_like = 'p';		//没有模式
						$output.="$str";
					}
					break;
				case '*':	//斜体
					if(preg_match('/^\*(.*)\*([^\s]+)\s*$/',$str,$mat)){
						$output.=$this->parseInner($str);
						$this->flag_like = 'i';
					}else if(preg_match('/^\s*\*\s+(.+)\s*$/',$str,$mat)){
						$str=$mat[1];
						$wrap = 'li';
						$this->flag_like = 'ul';
						if($this->flag != 'code'){
							if($this->flag != 'ul'){
								$this->flag_pre = $this->flag;
								$this->flag = 'ul';
								$output.='<ul>';
							}
						}
					}
					break;
				case '**':	//粗体
					if(preg_match('/^\*\*(.*)\*\*/',$str,$mat)){
						$output.=$this->parseInner($str);
						$this->flag_like = 'b';
					}
					break;
				case '<':
					$output.=$str;
					$this->flag_like = 'html';
					break;
				default:
					if(!$this->check()){	//非代码模式
						if(preg_match('/^>+$/',$type_str)){	//引用	
							$level = strlen($type_str);
							$this->flag_like = 'quote';
							$output.=$this->genQuote($level,$data_str);	//引用
						}else if(preg_match('/^([^\|\n\r]+)(?:\|[^\|\n\r]+)+$/',$str)){	//表格确定格式---|---|....
							if($this->flag == 'table'){
								$this->flag_like = 'table';
								$output.='<table><thead>'.$this->genRow($this->buf,'th').'</thead><tbody>';
							}else{	//非表格格式,上一行没有标题
								$this->flag_like = 'p';		//没有格式
								$output.=$this->buf;
								$this->buf = '';
							}
						}else if(preg_match('/^\s*(\-{3,})|(\={3,})$/',$type_str)){	//hr
							$output.='<hr>';
							$this->flag_like = 'hr';
							break;
						}else{
							$this->flag_like = 'p';
							$output.=$str.'<br>';
						}
					}
			}
			$br = '';
			preg_match('/^(.*)(<br\s*\/?>)?$/',$data_str,$mat);
			$data_str = htmlspecialchars($mat[1]);
			if(!$this->check()){	//代码
				if(!empty($wrap)) $output.="<$wrap>$mat[1]</$wrap>".(isset($mat[2])?'<br>':'');
			}else if($type_str!='```'){
				$output.=htmlspecialchars($str).'<br>';
			}
		}else{	//首个非空字符不为符号
			if($this->flag == 'code'){	//代码,直接输出
				$str=str_replace("\t",'    ',$str);
				$output.=htmlspecialchars($str).'<br>';
			}else if(is_numeric($str[0])){	//可能为有序列表,也可能是表格
				if(preg_match('/^\s*\d+\.\s+([^\r\n]+)\s*$/',$str)){	//有序列表
					$this->flag_like = 'ol';
					$output.=$str;
				}else{
					$this->flag_like = 'idel';
					foreach($this->every($str) as $ch){
						$output.=$ch;
					}
				}
				$output.='<br>';
			}else if(preg_match('/^([^\|\n\r]+)(?:\|[^\|\n\r]+)+$/',$str)){	//表格
				$mat = explode('|',$str);

				if($this->flag!='table'){	//进入表格
					$this->buf = $str;
					$this->flag_like = 'table';
					$wrap = '';
				}else{
					$output.=$this->genRow($str);
				}
				//echo $mat[0].'<br>';
				//var_dump($mat);
			}else{	//其他
				$this->flag = 'p';
				$output.=$str;
			}
		}

		if(!$this->check()){	//不为代码,设置flag
			$this->flag_pre = $this->flag;
			$this->flag = $this->flag_like;
			if($this->flag_pre == 'table' && $this->flag != 'table')
				$output='</table>'.$output;
			else if($this->flag_pre == 'ul' && $this->flag != 'ul'){
				$output='</ul>'.$output;
			}
		}

		return $this->parseInner($output);	//输出需要输出的内容
	}

	//获取输出内容
	public function output(){
		$this->parse();
		echo implode('',$this->lines);
	}
}