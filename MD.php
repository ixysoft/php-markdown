
<?php
	Header('Content:text/html;charset=utf-8');
?>
<style>
	body,ul,li,img,p{
		padding:0;
		margin:0;
		border:none;
	}

	h1,h2,h3,h4,h5,h6{
		margin:10px 0;
	}

	body,ul{
		padding:0px 20px;
	}

	blockquote{
		border-left:solid 4px #bbb;
		background-color:#eee;
		min-height:24px;
		line-height:24px;
		padding:0px 10px;
		margin:0;
		font-style: italic;
		font-family: consolas,楷体;
	}

	table,td,th{
		border:solid 1px #ddd;
		border-collapse: collapse;
		padding:5px;
	}

	tr:nth-child(even),th{
		background-color:#eee;
	}

	pre{
		padding:12px;
		border:solid 1px #bbb;
		background-color:#eee;
		font-family: consolas;
		font-size:14px;
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
	*
	*	idel 	闲置
	*/
class MD{

	private $file;			//当前文件
	private $mdGen;			//生成器
	private $flag;			//当前标志
	private $line_count;	//总行数
	private $lines;			//所有行

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
		if($ctnt != null){
			$ret = array();
			foreach($ctnt as $line){
				if($this->flag != 'code')
					$line=ltrim($line);	//删除左边
				if(strlen($line)){
					$ch = $line[0];	//判断第一个字符
					$type=ctype_punct($ch);//标点符号
					$this->lines[] = $this->parseLine($line,$type);	//解析行
				}else{
					switch($this->flag){
						case 'ul':
						case 'ol':
							echo "</$this->flag>";
							$this->flag = 'idel';
						break;
					}
				}
			}
		}
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
		if(strlen($str) - strlen($valid_str) >= 2){
			$br = '<br>';
		}else{
			$br = '';
		}
		$str = $valid_str;

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

			if($this->flag == 'ul' && !in_array($type_str,['+','-']))
				echo '</ul>';
			switch($type_str){
				case '#':
					$wrap = 'h1';
					break;
				case '##':
					$wrap = 'h2';
					break;
				case '###':
					$wrap = 'h3';
					break;
				case '####':
					$wrap = 'h4';
					break;
				case '#####':
					$wrap = 'h5';
					break;
				case '######':
					$wrap = 'h6';
					break;
				case '---':
					echo '<hr>';
					continue;
					break;
				case '+':
				case '-':
					$wrap = 'li';
					if($this->flag != 'code'){
						if($this->flag != 'ul'){
							$this->flag = 'ul';
							echo '<ul>';
						}
					}
					break;
				case '```':
					if($this->flag != 'code'){
						echo '<pre>';
						$this->flag = 'code';	//代码
					}else{
						echo '</pre>';
						$this->flag = 'idel';						//闲置
					}
					break;
				case '[':
					if(preg_match('/^\[\s\]\s*(.+)$/',$str,$mat)){
						echo "<label><input type='checkbox' disabled> $mat[1]</label><br>";
					}else if(preg_match('/\[([^\]]+)\]\(([^\)]+)\)/',$str,$mat)){
						echo "<a href='$mat[2]'>$mat[1]</a>".$br;
					}
					break;
				case '[-]':
					echo "<label><input type='checkbox' checked disabled>$data_str</label><br>";
					break;
				case '![':
					$this->flag = 'pic';
					if(preg_match('/^!\[([^\]]+)\]\(([^\)]+)\)$/',$str,$mat)){
						echo "<img src='$mat[2]' alt='$mat[1]'>".$br;
					}else{
						echo "$str$br";
					}
					continue;
				case '*':
					if(preg_match('/^\*(.*)\*$/',$str,$mat)){
						$wrap = 'b';
						$data_str = $mat[1];
					}
					break;
				case '**':
					if(preg_match('/^\*\*(.*)\*\*/',$str,$mat)){
						$wrap = 'i';
						$data_str = $mat[1];
					}
					break;
				case '<':
					echo $str;
					break;
				default:
					if(!$this->check()){
						if(preg_match('/^>+$/',$type_str)){
							$level = strlen($type_str);
							echo $this->genQuote($level,$data_str);
						}else{
							echo $str.'<br>';
						}
					}
			}
			$data_str = htmlspecialchars($data_str);
			if(!$this->check()){	//代码
				if(!empty($wrap))echo "<$wrap>$data_str</$wrap>".$br;
			}else if($type_str!='```'){
				echo htmlspecialchars($str).'<br>';
			}
		}else{
			if($this->flag == 'code'){
				$str=str_replace("\t",'    ',$str);
				echo htmlspecialchars($str).'<br>';
			}else if(is_numeric($str[0])){	//可能为有序列表,也可能是表格
				foreach($this->every($str) as $ch){
					echo $ch;
				}
				echo '<br>';
			}else if(preg_match('/^([^\|]+)(?!|([^\|]+)){1,}$/',$str,$mat)){
				var_dump($mat);
			}else{
				echo $str.'<br>';
			}
		}
	}

	//获取输出内容
	public function output(){
		$this->parse();
	}
}