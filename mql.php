<?php
class MQL{

	private $con;
	private $select;
	private $from;
	private $where;
	private $db;
	private $limit;
	private $count;
	private $joinKeys;

	public $result;

	function __construct($mongocon, $db)
	{
		$this->con = $mongocon;
		$this->db = $this->con->selectDB($db);
		$this->select = array();
		$this->from = array();
		$this->where = array();
		$this->result = array();
		$this->limit = 0;
		$this->count = false;
		$this->joinKeys = array();
	}

	function __destruct()
	{
		$this->cleanVals();
		$this->con = null;
	}

	public function Query($stmnt)
	{
		$syntax = $this->checkSyntax($stmnt);
		if(count($syntax) > 0)
		{	
			$this->result = $syntax;
		}
		else
		{
			$this->getCount($stmnt);
			$this->getLimit($stmnt);
			$this->getSelect($stmnt);
			$this->getFrom($stmnt);
			$this->getWhere($stmnt);
			$this->execQuery();
		}

	}

	private function checkSyntax($stmnt)
	{	
		$sel_pos = strpos($stmnt, "select");
		$from_pos = strpos($stmnt, "from");
		$where_pos = strpos($stmnt, "where");
		$join_pos = strpos($stmnt, "join");
		$count_pos = strpos($stmnt, "count");

		if( $sel_pos != 0 )
		{
			return array("ERROR"=>"SELECT keyword must be first on query statement");
		}
		if($from_pos == false )
		{
			return array("ERROR"=>"FROM keyword must be present on query statement");
		}

		if($from_pos > $where_pos && $where_pos != false)
		{
			return array("ERROR"=>"FROM keyword must be present before WHERE keyword on query statement");
		}

		if(($join_pos > $where_pos || $join_pos < $from_pos) && $where_pos != false && $join_pos != false)
		{
			return array("ERROR"=>"JOIN keyword must be present before WHERE keyword and after FROM keyword on query statement");
		}

		if(($count_pos > $from_pos || $count_pos > $where_pos) && $count_pos != false)
		{
			return array("ERROR"=>"COUNT keyword must be present before FROM keyword and before WHERE keyword on query statement");
		}
	}

	private function getCount($stmnt)
	{
		if(strpos($stmnt, "count")!=false)
		{
			$this->count = true;
		}
	}

	private function getLimit($stmnt)
	{
		$limit_pos = strpos($stmnt, "limit");
		$this->limit = intval(str_replace(" ","",substr($stmnt, $limit_pos + 5)));

	}

	private function getSelect($stmnt)
	{
		$sel_pos = strpos($stmnt,  "from");
		$sel_str =  str_replace(" ", "", substr($stmnt, 6, ($sel_pos - 6)));
		$sel_fields = explode(',', $sel_str);

		if(!in_array("*", $sel_fields))
		{
			foreach ($sel_fields as $key => $value) {
				$this->select[$value] = true;
			}
		}

	}

	private function getFrom($stmnt)
	{
		$from_pos = strpos($stmnt,  "from");
		$where_pos = strpos($stmnt, "where");

		if($where_pos == false)
		{
			$from_str = str_replace(" ", "",  substr($stmnt, $from_pos + 4));
		
		}
		else
		{
			$from_str =  str_replace(" ", "", substr($stmnt, ($from_pos + 4), ($where_pos - $from_pos) - 5  ));
		}
		$this->from = explode(',', $from_str);
	}

	private function getWhere($stmnt)
	{
		//NEEDED: AND OR LOGIC
		$where_pos = strpos($stmnt, "where");

		if($where_pos == true)
		{
			$where_str = str_replace(" ", "", substr($stmnt,($where_pos + 5)));
			$where_fields = explode(",", $where_str);

			foreach ($where_fields as $field) {
				$this->buildWhere($field);	
			}
		}
	}

	private function buildWhere($field)
	{
		if(strpos($field, ">=") != false)
		{
			$field_array = explode(">=", $field);
			$field_array[1] = $this->castField($field_array[1]);
			$this->where[$field_array[0]] = array('$gte'=>$field_array[1]);
		}
		else
		{
			if(strpos($field, "<=") != false)
			{
				$field_array = explode("<=", $field);
				$field_array[1] = $this->castField($field_array[1]);
				$this->where[$field_array[0]] = array('$lte'=>$field_array[1]);
			}
			else
			{
				if(strpos($field, "<") != false)
				{
					$field_array = explode("<", $field);
					$field_array[1] = $this->castField($field_array[1]);
					$this->where[$field_array[0]] = array('$lt'=>$field_array[1]);
				}
				else
				{
					if(strpos($field, ">") != false)
					{
						$field_array = explode(">", $field);
						$field_array[1] = $this->castField($field_array[1]);
						$this->where[$field_array[0]] = array('$gt'=>$field_array[1]);
					}
					else
					{
						$field_array = explode("=", $field);
						$field_array[1] = $this->castField($field_array[1]);
						$this->where[$field_array[0]] = $field_array[1];
					}
				}
			}
		}
	}

	private function castField($field_val)
	{
		if(is_numeric($field_val))
		{
			if(is_int($field_val))
			{
				$field_val=intval($field_val);
			}
			else
			{
				$field_val=floatval($field_val);
			}
		}
		else
		{
			$field_val=str_replace("'","",$field_val);
		}

		return $field_val;
	}

	private function execQuery()
	{ 	
		$this->result=array();
		if(count($this->from) == 1)
		{
			$collection = new MongoCollection($this->db, $this->from[0]);

			if(count($this->where) == 0)
			{
				if($this->count)
				{
					$cursor = $collection->count(array(), $this->select);
				}
				else
				{
					$cursor = $collection->find(array(), $this->select)->limit($this->limit);
				}
			}
			else
			{
				if($this->count)
				{	
					$cursor = $collection->count($this->where, $this->select);
				}
				else
				{
					$cursor = $collection->find($this->where, $this->select)->limit($this->limit);
				}
			}

			if($this->count)
			{
				$this->result[0]=$cursor;
			}
			else
			{
				foreach ($cursor as $doc) {
					$this->result[] = $doc;
				}
			}
		}
		else
		{
			if(count($this->from)==2)
			{
				$this->joinTables($this->from);
			}
			else
			{
				$this->result = array("ERROR"=>"Currently only 2 table joint is supported");
			}
			
		}
		$this->cleanVals();
			
	} 

	private function joinTables($from)
	{ 
		$count = false;
		if($this->count)
		{
			$this->select=array();
			$this->count = false;
			$count = true;
		}

		$select = $this->select;
		$this->getJoinKeys($from);
		$this->from = array($from[0]);
		$this->execQuery();
		$res1 = $this->result;
		$this->result = array();
		
		$from[1] =substr($from[1], 0, strpos($from[1], "join"));
		
		$this->from = array($from[1]);
		$this->select= $select;
		$this->execQuery();
		$res2 = $this->result;

		if($count)
		{
			$this->count=true;
		}
		$this->joinResult($res1, $res2);
		$this->joinKeys=array();
	}

	private function getJoinKeys($from)
	{
		$join_str = substr($from[count($from)-1], strpos($from[count($from)-1], "joinon")+6);

		$this->joinKeys = explode("=",$join_str);

	}

	private function joinResult($res1, $res2)
	{	
		$result = array();
		foreach ($res1 as $key => $value) {
			foreach ($res2 as $key2 => $value2) {
				if($value2[$this->joinKeys[1]] == $value[$this->joinKeys[0]] )
				{
					$result[] = array_merge($value, $value2);
				}
			}
		}
		if($this->count)
		{
			$this->result=array(count($result));
		}
		else
		{
			$this->result= $result;
		}
	}
		
	private function cleanVals()
	{
		$this->select = array();
		$this->from = array();
		$this->where = array();
		$this->limit = 0;
		$this->count = false;	
		
	}
}

?>