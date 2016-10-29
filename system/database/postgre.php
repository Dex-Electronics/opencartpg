<?php
final class Postgre {
	private $link;
	
	public function __construct($hostname, $username, $password, $database) {
		if (!$this->link = pg_connect('host=' . $hostname . ' user=' . $username . ' password='	. $password . ' dbname=' . $database)) {
      		trigger_error('Error: Could not make a database link using ' . $username . '@' . $hostname);
    	}

    	//if (!mysql_select_db($database, $this->link)) {
      		//trigger_error('Error: Could not connect to database ' . $database);
    	//}
		
		pg_query($this->link, "SET CLIENT_ENCODING TO 'UTF8'");
  	}
		
  	public function query($sql) {

		// PostgreSQL-style quotes
		$sql = strtr($sql, '`', '"');

		$sql = preg_replace('/^\s*REPLACE\s*(INTO.*)$/', 'INSERT $1', $sql);

		// Transform MySQL's INSERT...SET to normal INSERT...VALUES
		if(preg_match('/^ *INSERT INTO[ a-zA-Z0-9_"-]*SET/', $sql)) {
		  $chars = preg_split('/(INSERT INTO[ a-zA-Z0-9_"-]*SET)/', $sql, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
			$insert = preg_replace('/SET$/', '', $chars[0]);
			$values = $chars[1];
			preg_match_all("/([^=,]*)=((?:'[^']*'|[^=,])*)/s", $values, $chars);
			$sql = $insert . ' (' . implode(', ', $chars[1]) . ') VALUES (' . implode(', ', $chars[2]) . ')';
		}

		$sql = preg_replace('/^ *(SELECT.*LIMIT) *(\d+),(\d+) *$/', '$1 $3 OFFSET $2', $sql);
		$sql = preg_replace("/'0000-00-00'/", "'-infinity'", $sql);

		$resource = pg_query($this->link, $sql);

		if ($resource) {
			if (is_resource($resource)) {
				$i = 0;
    	
				$data = array();
		
				while ($result = pg_fetch_assoc($resource)) {
					$data[$i] = $result;
    	
					$i++;
				}
				
				pg_free_result($resource);
				
				$query = new stdClass();
				$query->row = isset($data[0]) ? $data[0] : array();
				$query->rows = $data;
				$query->num_rows = $i;
				
				unset($data);

				return $query;	
    		} else {
				return true;
			}
		} else {
			trigger_error('Error: ' . pg_result_error($this->link) . '<br />' . $sql);
			exit();
    	}
  }
	
	public function escape($value) {
		return pg_escape_string($this->link, $value);
	}
	
  	public function countAffected() {
    	return pg_affected_rows($this->link);
  	}

  	public function getLastId() {
		$query = $this->query("SELECT LASTVAL() AS `id`");
		
    	return $query->row['id'];
  	}	
	
	public function __destruct() {
		pg_close($this->link);
	}
}
?>
