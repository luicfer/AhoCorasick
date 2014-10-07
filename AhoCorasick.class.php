<?php

/**
 * @classname: AhoCorasick
 * @description: 用于实现AC多模式匹配的搜索查找算法
 */
class AhoCorasick {
    private $root;// State对象，表示根节点
    private $prepared;// boolean类型，表示搜索词是否装载完成。如果为true，则表示加载完成，并且不能再加载搜索词
    private $arr_keys;// Array对象，存放第一级的搜索词
	/**
	 * @function 构造函数
	 * @param
	 * @return
	 */
    public function AhoCorasick() {
		$this->root = new State(0);
		$this->root->setFail($this->root);// 设置根节点的失效值
		$this->prepared = false;
		$this->arr_keys = array();
    }
	/**
	 * @function 获取根节点对象
	 * @param
	 * @return State
	 */
     public function getRoot() {
		return $this->root;
    }

	/**
	 *@function 添加搜索词
	 *@param string $keywords 要查找的搜索词
	 *@return
	**/
    public function add($keywords=""){
    	// 如果装载标志为true，则禁止再加载搜索词
    	try{
			if ($this->prepared){
				throw new Exception("can't add keywords after prepare() is called.");
			}
    	}catch(Exception $e){
    		echo $e->getMessage();
    		return;
    	}

		// 如果搜索词不是字符串类型，或者内容为空，则返回
		try{
			if(!is_string($keywords) || strlen(trim($keywords))==0){
				throw new Exception("Added keywords is not string type, or content is empty.");
			}
		}catch(Exception $e){
    		echo $e->getMessage();
    		return;
    	}
    	$keywords = trim($keywords);
		// 把搜索词按字符为单位转换成字符数组
		$words = $this->str_split_utf8($keywords);
		// 设置第一层级的搜索字符
		$this->arr_keys = array_unique(array_merge($this->arr_keys, $words));
		// 获取添加完搜索词之后的最后一个State值
		$lastState = $this->root->extendAll($words);
		// 向最后一个State值中添加输出内容
		$lastState->addOutput($keywords);
    }
	/**
	 *@function 加载搜索词add()完成之后调用
	 *@param
	 *@return
	**/
    public function prepare() {
		$this->prepareFailTransitions();
		$this->prepared = true;
    }
	/**
	 *@function 设置字典树中每个State节点的失效值
	 *@param
	 *@return
	**/
	private function prepareFailTransitions() {
		$q = array();// 存放第一层级的所有搜索词
		foreach($this->arr_keys as $value){
			if(is_null($this->root->get($value))){
				// 如果搜索词不存在于第一层级，则添加，并且设置失效值为根节点State对象
				$this->root->put($value, $this->root);
			}else{
				// 设置第一层级的失效值为根节点State对象，并且把搜索词对应的State值添加到$q数组中
				$this->root->get($value)->setFail($this->root);
				array_push($q, $this->root->get($value));
			}
		}
		// 设置所有State节点的失效值
		while(!is_null($q)) {
			// 将数组$q第一个State值移出该数组，并返回移出的State值
			$state = array_shift($q);
			// 如果取出的$state内容为空，则结束循环
			if(is_null($state)){
				break;
			}
			// 获取$state值对应的下一级所有搜索词
			$keys = $state->keys();
			$cnt_keys = count($keys);
			for($i=0; $i<$cnt_keys; $i++) {
				$r = $state;
				$a = $keys[$i];
				$s = $r->get($a);
				array_push($q, $s);
				$r = $r->getFail();
				// 递归查找失效值，直到根节点为止
				while(is_null($r->get($a))){
					$r = $r->getFail();
				}

				$s->setFail($r->get($a));
				$s->setOutputs(array_unique(array_merge($s->getOutputs(), $r->get($a)->getOutputs())));
			}
		}
	}
	/**
	 *@function 查找函数
	 *@param string words 被查找的字符串
	 *@return Searcher
	**/
    public function search($words){
		return new Searcher($this, $this->startSearch($words));
    }
	/**
	 *@function 查找函数
	 *@param string words 被查找的字符串
	 *@return SearchResult
	**/
    public function startSearch($words) {
    	// 加载未完成时，不允许进行搜索查找
        try{
			if (!$this->prepared){
				throw new Exception("Can't start search until prepare().");
			}
    	}catch(Exception $e){
    		echo $e->getMessage();
    		return;
    	}
		// 转换被查找的字符串为字符数组
		$arr_words = $this->str_split_utf8($words);
		// 搜索查找后结果集
		$res = $this->continueSearch(new SearchResult($this->root, $arr_words, 0));
		return $res;
    }
	/**
	 *@function 真正的查找函数
	 *@param SearchResult lastResult SearchResult对象
	 *@return SearchResult or NULL
	**/
    public function continueSearch($lastResult) {
    	// 如果lastResult搜索结果对象为null，则返回
    	if(is_null($lastResult)){
    		return NULL;
    	}

    	$words = $lastResult->words;// 被查找的字符数组
		$state = $lastResult->lastMatchedState;// 开始查找的State值
		$start = $lastResult->lastIndex;// 开始查找的位置
		$len = count($words);
    	for($i=$start; $i<$len; $i++) {
			$word = $words[$i];	// 获取单个字符
			// 如果获取的搜索词不存在，则递归转向失效值进行搜索，直到根节点为止
			while (is_null($state->get($word))){
				$state = $state->getFail();
				if($state===$this->root){
					break;
				}
			}

			if(!is_null($state->get($word))){
				// 获取搜索词对应的State值，如果有输出内容，则输出
				$state = $state->get($word);
				if (count($state->getOutputs())>0){
					return new SearchResult($state, $words, $i+1);
				}
			}
		}
		return NULL;
    }
	/**
	 *@function 字符串转换成字符数组，单位是字符
	 *@param string str 转换的字符串内容
	 *@return Array
	**/
	function str_split_utf8($str){
		$split=1;
		$array = array();
		for($i=0; $i<strlen($str); ){
			$value = ord($str[$i]);
			if($value > 127){
				if($value >= 192 && $value <= 223)
					$split=2;
				else if($value >= 224 && $value <= 239)
					$split=3;
				else if($value >= 240 && $value <= 247)
					$split=4;
			}else{
				$split=1;
			}

			$key = NULL;
			for($j = 0; $j<$split; $j++, $i++ ) {
				$key .= $str[$i];
			}
			array_push( $array, $key );
		}
		return $array;
	}
}

///////////////////////////////////////
/**
 * @classname: SearchResult
 * @description: 搜索结果类，用于存储搜索查找后的结果集
 */
class SearchResult {
    var $lastMatchedState;// State对象，最后匹配的State值
    var $words;// Array对象，被搜索的内容
    var $lastIndex;// int类型，最后出现的位置
	/**
	 * @function 构造函数
	 * @param State state State对象
	 * @param Array words 被查找的字符串
	 * @param int index 查找位置
	 * @return
	 */
    public function SearchResult($state, $words=array(), $index=0) {
		$this->lastMatchedState = $state;
		$this->words = $words;
		$this->lastIndex = $index;
    }
	/**
	 * @function 获取输出的内容
	 * @param
	 * @return Array
	 */
    public function getOutputs() {
		return $this->lastMatchedState->getOutputs();
    }
	/**
	 * @function 获取查找的位置
	 * @param
	 * @return int
	 */
    public function getLastIndex() {
		return $this->lastIndex;
    }
}

////////////////////////////
/**
 * @classname: Searcher
 * @description: 搜索类
 */
class Searcher{
    private $tree;// AhoCorasick对象
    private $currentResult;// SearchResult对象
	/**
	 * @function 构造函数
	 * @param AhoCorasick tree AhoCorasick对象
	 * @param SearchResult result SearchResult对象
	 */
    public function Searcher($tree, $result) {
		$this->tree = $tree;
		$this->currentResult = $result;
    }
	/**
	 * @function hasNext 用于判断是否还有值存在
	 * @param
	 * @param boolean true表示有值  false表示无值
	 */
    public function hasNext() {
		return !is_null($this->currentResult);
    }
	/**
	 * @function next 获取下一个值
	 * @param
	 * @param 如果有值则返回SearchResult对象，否则返回NULL
	 */
    public function next() {
		if (!$this->hasNext()){
		    return NULL;
		}
		$result = $this->currentResult;
		$this->currentResult = $this->tree->continueSearch($this->currentResult);
		return $result;
    }
}

/**
 * @classname: State
 * @description: 状态类，用于表示字典树中的每一个状态节点
 */
class State {
    private $depth;// int类型，表示每一个状态对象的深度，从0开始表示
    private $edgeList;// 类似于列表，用于包含该状态下所包含的下一级所有State对象
    private $fail;// State对象，表示状态对象失效之后要跳转的地方
    private $outputs;// array对象，存放某一状态下可以输出的内容
	/**
	 * @function State 构造函数
	 * @param int depth 状态所处的深度
	 * @return
	 */
    public function State($depth) {
		$this->depth = $depth;
		//$this->edgeList = new SparseEdgeList();
		$this->edgeList = new DenseEdgeList();
		$this->fail = NULL;
		$this->outputs = array();
    }

	/**
	 *@function extend 添加单个搜索词
	 *@param char character 单个搜索词，或者一个字母、数字、或者一个汉字等
	 *@return State
	**/
    public function extend($character) {
		if (!is_null($this->edgeList->get($character))){
		    return $this->edgeList->get($character);
		}

		$nextState = new State($this->depth+1);
		$this->edgeList->put($character, $nextState);
		return $nextState;
    }
	/**
	 *@function extendAll 添加搜索词
	 *@param array contents 搜索词数组
	 *@return State
	**/
    public function extendAll($contents) {
		$state = $this;
		$cnt = count($contents);
		for($i=0; $i<$cnt; $i++) {
			// 如果搜索的关键词存在，则直接返回该 关键词所处的State对象，否则添加该关键词
		    if(!is_null($state->edgeList->get($contents[$i]))){
				$state = $state->edgeList->get($contents[$i]);
		    }else{
				$state = $state->extend($contents[$i]);
			}
		}
		return $state;
    }
	/**
	 * @function 计算搜索词的总长度
	 * @param
	 * @return int
	 */
    public function size() {
		$keys = $this->edgeList->keys();
		$result = 1;
		$length = count($keys);
		for ($i=0; $i<$length; $i++){
		    $result += $this->edgeList->get($keys[$i])->size();
		}
		return $result;
    }
	/**
	 * @function 获取单个关键词所处的State对象
	 * @param char character
	 * @return State
	 */
    public function get($character) {
    	$res = $this->edgeList->get($character);
    	return $res;
    }
	/**
	 * @function 向State对象中添加下一级的搜索词及对应的State值
	 * @param char character
	 * @param State state
	 * @return
	 */
    public function put($character, $state) {
		$this->edgeList->put($character, $state);
    }
	/**
	 * @function 获取State对象下一级的所有关键词
	 * @param
	 * @return Array
	 */
    public function keys() {
		return $this->edgeList->keys();
    }
	/**
	 * @function 获取State对象失效时对应的失效值
	 * @param
	 * @return State
	 */
    public function getFail() {
		return $this->fail;
    }
	/**
	 * @function 设置State对象失效时对应的失效值
	 * @param
	 * @return
	 */
    public function setFail($state) {
		$this->fail = $state;
    }
	/**
	 * @function 向State对象的outputs中添加输出内容
	 * @param
	 * @return
	 */
    public function addOutput($str) {
    	array_push($this->outputs, $str);
    }
	/**
	 * @function 获取State对象的输出内容
	 * @param
	 * @return Array
	 */
    public function getOutputs() {
		return $this->outputs;
    }
 	/**
	 * @function 设置State对象的输出内容
	 * @param
	 * @return
	 */
    public function setOutputs($arr=array()){
    	$this->outputs = $arr;
    }
}

/**
 * @classname: DenseEdgeList
 * @description: 存储State对象下一级对应的所有State内容，以数组形式存储
 */
class DenseEdgeList{
	private $array;// State对象，包含对应的搜索词及State值
	/**
	 * 构造函数
	 */
	public function DenseEdgeList() {
		$this->array = array();
	}
	/**
	 * @function 从链表存储形式的内容转为数组存储形式的内容
	 * @param SparseEdgeList list
	 * @return DenseEdgeList
	 */
	public function fromSparse($list) {
		$keys = $list->keys();
		$newInstance = new DenseEdgeList();
		for($i=0; $i<count($keys); $i++) {
			$newInstance->put($keys[$i], $list->get($keys[$i]));
		}
		return $newInstance;
	}
	/**
	 * @function 获取搜索词对应的State值
	 * @param char word
	 * @return 如果存在则返回对应的State对象，否则返回NULL
	 */
	public function get($word) {
		if(array_key_exists($word, $this->array)){
			return $this->array["$word"];
		}else{
			return NULL;
		}
	}
	/**
	 * @function 添加搜索词及对应的State值到数组中
	 * @param char word 单个搜索词
	 * @param State state 搜索词对应的State对象
	 * @return
	 */
	public function put($word, $state) {
		$this->array["$word"] = $state;
	}
	/**
	 * @function 获取所有的搜索词
	 * @param
	 * @return Array
	 */
	public function keys() {
		return array_keys($this->array);
	}
}

/**
 * @classname: SparseEdgeList
 * @description: 存储State对象下一级对应的所有State内容，以链表形式存储
 */
class SparseEdgeList{
    private $head;// Cons对象
	/**
	 * 构造函数
	 */
    public function SparseEdgeList() {
		$this->head = NULL;
    }
	/**
	 * @function 获取搜索词对应的State值
	 * @param char word
	 * @return 如果存在则返回对应的State对象，否则返回NULL
	 */
    public function get($word) {
		$cons = $this->head;
		while(!is_null($cons)){
		    if ($cons->word === $word){
				return $cons->state;
		    }
		    $cons = $cons->next;
		}
		return NULL;
    }
	/**
	 * @function 添加搜索词及对应的State值到链接中
	 * @param char word 单个搜索词
	 * @param State state 搜索词对应的State对象
	 * @return
	 */
    public function put($word, $state){
		$this->head = new Cons($word, $state, $this->head);
    }
	/**
	 * @function 获取所有的搜索词
	 * @param
	 * @return Array
	 */
    public function keys() {
		$result = array();
		$c = $this->head;
		while(!is_null($c)){
			array_push($result, $c->word);
		    $c = $c->next;
		}
		return $result;
    }

}
/**
 * @classname: Cons
 * @description: 用于SparseEdgeList生成链表时表示的节点对象
 */
class Cons {
	var $word;// 单个搜索词
	var $state;// State对象
	var $next;// Cons对象
	/**
	 * 构造函数
	 */
	public function Cons($word, $state, $next){
	    $this->word = $word;
	    $this->state = $state;
	    $this->next = $next;
	}
}
?>