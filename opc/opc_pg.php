<?php
  /*
   function wich returns the databasename/current user and so on
    seq_list seq_get seq_set seq_next
    sql-explode -> restrict to select insert update delete
    update auch mit ident und array (platzhalter für eigenen namen)
    converterfunktionen utf8_de/encode fest integrieren
       bis jetzt transform in read_*, fehlend load_*, write_*, save_*
    write_row mit update/insert verhinderung bezüglich ident

    class to access only one table
    read-only class
    load/save hierarchische Daten (nested array)
   */

require_once('opc_err.php');

class pgdb {
  var $structure = array();

  /** current database name */
  var $dbname = '';

  /** current database name */
  var $dbuser = '';

  /** encoding used by the database */
  var $dbenc = '';
  
  /** encoding used from php */
  protected $phpenc = 'UTF8';
  var $db = null;

  var $encodings = array(0=>'SQL_ASCII',
			 6=>'UTF8',
			 8=>'LATIN1');

  var $err = NULL;


  var $yes = array('yes','ja','oui','y','j','+','on', '1','I',);
  var $no = array('no','nein','non','n',    '-','off','0','O');

  var $transform = array();

  /* uselimit
   there are two ways to select a row:
    uselimit = TRUE: add limit and offset to the sql statement
    uselimit = FLASE: read the full result and select by php array index
   */
  var $uselimit = FALSE;

  

  function __construct($connection=null,$name=NULL){
    $this->init_err();
    $this->connect($connection,$name);
  }


  /**   function __get
   * @access private
   * @param $key name of the asked variable
   * @return aksed value or error 103 is triggered
   */
  function __get($key){
    $tmp = NULL;
    if($this->___get($key,$tmp)) return $tmp;
    return NULL;
  }

  /** subfunction of magic method __get to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return bool: returns TRUE if key was accepted (result is saved in $res) otherwise FALSE
   */
  protected function ___get($key,&$res){
    switch($key){
    case 'phpenc': $res = $this->$key; return TRUE;
    }
    return FALSE;
  }

  /**   function __set
   * @access private
   * @param $key name of the asked variable
   * @param mixed $value new value
   * @return aksed value or error 103 is triggered
   */
  function __set($key,$value){
    $tmp = NULL;
    $tmp = $this->___set($key,$value);
    if($tmp>0) return $tmp;
    return 0;
  }

  /** subfunction of magic method __set to simplfy overloading
   * @param string $key name of the asked element
   * @param mixed &$res place to save the result
   * @return int err-code (0=success)
   */
  protected function ___set($key,$value){
    switch($key){
    case 'phpenc':
      if(preg_match('#utf-?8#i',$value)) $this->phpenc = 'UTF8';
      else if(preg_match('#iso-?8859#i',$value)) $this->phpenc = 'LATIN1';
      else if(preg_match('#ascii#i',$value)) $this->phpenc = 'SQL_ASCII';
      else return 105;
      $this->sql_execute("SET client_encoding to '$this->phpenc'");
      return 0;
    }
    return 104;
  }


  function dbname(){ return is_null($this->db)?NULL:pg_dbname($this->db);}

  function init_err(){
    $msgs = array(2=>'Invalid connection',
		  3=>'SQL Syntax error',
		  4=>'Unknown table/view',
		  5=>'Unknown field',
		  6=>'No read access',
		  7=>'No write access',
		  10=>'Unknown or non unique connection',
		  );
    $this->err = new opc_err($msgs);
  }

  /** from php to pg */
  function decode($txt){
    if($this->dbenc===$this->phpenc) return $txt;
    return $this->_code($txt,$this->phpenc . '-' . $this->dbenc);
  }

  /** from pg to php */
  function encode($txt){
    if($this->dbenc===$this->phpenc) return $txt;
    return $this->_code($txt,$this->dbenc . '-' . $this->phpenc);
  }

  /** from php to pg */
  protected function _code($txt,$way){
    switch($way){
    case 'UTF8-SQL_ASCII': return utf8_decode($txt);
    case 'UTF8-LATIN1': return utf8_decode($txt);
    case 'LATIN1-UTF8': return utf8_encode($txt);
    case 'LATIN1-SQL_ASCII': return $txt;
    case 'SQL_ASCII-UTF8': return utf8_encode($txt);
    case 'SQL_ASCII-LATIN1': return $txt;
    }
    return $txt;
  }

  /*
   connection is
     + a valid db-connection
       "host=sheep port=5432 dbname=mary user=lamb password=foo"
     + filename with
       + a single db-connection (see above)
       + a connection per line style "name: host=sheep port=54....
         the second argument is the 'name' part.
       
   */

  function connect($connection=NULL,$name=NULL){
    $this->db = NULL;
    $this->dbname = NULL;
    $this->dbuser = NULL;

    // already a opc_instance
    if(is_object($connection) and ($connection instanceof opc_pg)){
      $this->phpenc = $connection->phpenc;
      return $this->int_settings($connection->db);
    }

    // connection is already a resource
    if(is_resource($connection)){
      if(get_resource_type($connection)!='pgsql link') return FALSE;
      return $this->int_settings($connection);
    } else if(!is_string($connection)) return(FALSE);

    // read connection from a file (singel string or name:connection)
    if(!preg_match('/dbname *=/i',$connection)){ // connection is a file name
      if(!file_exists($connection)) return(FALSE);
      $connection = file($connection);
      if(!is_null($name)){
	$connection = preg_grep('/^' . $name . ':/',$connection);
	if(!is_array($connection) or count($connection)!=1) return(FALSE); 
	$connection = preg_replace('/^.*:/','',array_shift($connection));
      } else $connection = array_shift($connection);
    }
    // connect to the db
    $db = pg_connect($connection);
    if($db===FALSE) return FALSE;
    return $this->int_settings($db);
  }

  protected function int_settings($db){
    $this->db = $db;
    $this->dbname = pg_dbname($this->db);
    $this->dbuser = $this->read_field('SELECT current_user');
    $this->dbenc = (int)$this->read_field("SELECT encoding FROM pg_database WHERE datname='$this->dbname'");
    if(array_key_exists($this->dbenc,$this->encodings)) $this->dbenc = $this->encodings[$this->dbenc];
    return TRUE;
  }
  
  function is_connected() { return(!is_null($this->db));}

  /*
    simulates a cmd like "\i dump.sql" in psql
    force: false: stop after the first faild command; true -> try all
    COPY Section: use only FROM stdin
    Comments: are not well handeld (there should be no ; at the end of a comment)
  */
  function dump_read($file,$force=false){
    if(!is_array($file)) $file = file($file);
    if(!is_array($file)) return(null);
    $mode = true; $csql = ''; $fn = count($file);
    for($ci=0;$ci<$fn;$ci++){
      if($mode){ 
	$csql .= $file[$ci];
	if(preg_match('/^ *COPY/i',$file[$ci])){
	  while(!preg_match('/; *$/',$file[$ci]) and $ci<$fn) $csql .= $file[++$ci];
	  if(pg_query($this->db,$csql)===false and !$force) return(false); 
	  $mode = false; 
	  $csql = array();
	} else if(preg_match('/; *$/',$file[$ci])){
	  if(pg_query($this->db,$csql)===false and !$force) return(false); 
	  $csql = '';
	}
      } else {
	$csql[] = $file[$ci];
	if($file[$ci]=="\\.\n") {
	  foreach($csql as $cl) if(pg_put_line($this->db,$cl)===false and !$force) return(false);
	  if(pg_end_copy($this->db)===false and !$force) return(false);
	  $mode = true;
	  $csql = '';
	}
      }
    }
    if(preg_match('/; *$/',$csql))
      if(pg_query($this->db,$csql)===false and !$force) 
	return(false); 
    return(true);
  }

  // reads the table settings
  function settings($name=null,$fullrow=true){
    $sql = 'SELECT * FROM pg_settings';
    if($fullrow) $qa = $this->read_array($sql,'name');
    else         $qa = $this->read_column($sql,'setting','name');
    if(is_null($name))   return($qa);
    if(!is_array($name)) return($qa[$name]);
    $res = array();
    foreach($name as $cn) $res[$cn] = $qa[$cn];
    return($res);
  }

  function encoding($encoding=null){
    if(is_null($encoding)) return(pg_client_encoding($this->db));
    $pat = '/^(SQL_ASCII|EUC_JP|EUC_CN|EUC_KR|EUC_TW|UNICODE|MULE_INTERNAL'
      . '|LATIN[1-9]|KOI8|WIN|ALT|SJIS|BIG5|WIN1250)$/';
    $encoding = strtoupper($encoding);
    if(!preg_match($pat,$encoding)) return(false);
    return(pg_set_client_encoding($this->db,$encoding));
  }

  /** to be extended */
  function tabstructure_read($tabname){
    if(is_null($this->db)) return(null);
    $sql = 'SELECT * FROM pg_tables WHERE schemaname=\'public\''
      . ' AND tablename=\'' . $tabname . '\'';
    
    $tab = array('name'=>$tabname);
    $this->structure[$tabname] = $tab;
    return($tab);
  }

  function sqls_execute($sqls){
    if(is_string($sqls)) return $this->sql_execute($sqls);
    if(!is_array($sqls)) return FALSE;
    if(count($sqls)==0)  return TRUE;
    if(count($sqls)==1)  return $this->sql_execute(array_shift($sqls));
    $sql = 'BEGIN; ' . implode('; ',$sqls) . '; COMMIT';
    return pg_query($this->db,$sql);
  }

  function sql_execute($sql){
    if(is_null($this->db)) return(null);
    if(preg_match('/DELETE FROM \d/',$sql)) {qk();qq($sql);}
    return pg_query($this->db,$sql);
  }

  // returns a string ready to use inside a sql statement
  //if table is null field is the datatyp directly
  function mask($value=null,$field='character varying',$tabname=null){
    if(is_null($value)) return('NULL');		       
    if(is_string($tabname)){
      $field = $this->field_type($tabname,strtolower($field));
    }
    $pos = strpos($field,'(');
    if($pos){
      $prec = explode(',',substr($field,$pos+1,-1));
      for($ci=0;$ci<count($prec);$ci++) $prec[$ci] = (int)$prec[$ci];
      $field = substr($field,0,$pos);
    } else $prec = array();
    $field = strtolower($field);
    switch($field){
    case 'bigint': case 'integer': case 'smallint':
    case 'real': case 'double precision':
    case 'decimal': case 'numeric': 
    case 'serial': case 'bigserial':
    case 'id': case 'double': case 'int': case 'float':
      $res = strval($value);
      break;
    case 'boolean':
      if(is_bool($value))         $res = $value?'t':'f';
      else if(is_numeric($value)) $res = $value?'t':'f';
      else if(is_string($value)){
	$value = strtolower($value);
	if(!(array_search($value,$this->yes)===FALSE)) $res = 't';
	else if(!(array_search($value,$this->no)===FALSE)) $res = 'f';
	else $res = empty($value)?'t':'f';
      } else $res = empty($value)?'t':'f';
      $res = '\'' . $res . '\'';
      break;
    case 'character': case 'character varying': case 'text': case 'string':
      $res = '\'' . pg_escape_string($value) . '\'';
      break;
    case 'time': case 'time without time zone': case 'time with time zone':
      if(is_numeric($value)){
	$res = '\'' . date('H:i:s',$value) . '\'';
      } else {
	$res = '\'' . pg_escape_string($value) . '\'';
      }
      break;
    case 'timestamp': case 'timestamp without time zone': case 'timestamp with time zone':
    case 'date':
      if(is_numeric($value)){
	$res = '\'' . date('Y-m-d H:i:s',$value) . '\'';
      } else {
	$res = '\'' . pg_escape_string($value) . '\'';
      }
      break;
    case 'money':
      if(is_numeric($value)) $res = '\'$ ' . $value . '\'';
      else                   $res = $value;
      break;
    case 'bytea':
      $res = '\'' . pg_escape_bytea($value) . '\'';
      break;
    default:
      if(is_string($value)) $res = '\'' . pg_escape_string($value) . '\'';
      else return($value);
    }
    return($res);
  }

  /* create sql-parts based on a named array
     typ: where; update; insert; select
     typ select allows also a array of field names directly (with numeric keys)
   */
  function sql_make($data,$typ='where',$tabname=null,$mask=TRUE){
    if(!is_array($data)) return $data;
    if(count($data)==0) return NULL;
    $ak = array_keys($data);

    switch($typ){
    case 'where':
      foreach($ak as $ck) {
	if(is_numeric($ck)){
	  // do nothing
	} else if(substr($ck,0,1)!=':'){
	  if(is_null($data[$ck])) $data[$ck] = $ck . ' IS NULL';
	  else $data[$ck] = $ck . '=' . ($mask?$this->mask($data[$ck],$ck,$tabname):$data[$ck]);
	}
      }
      $res = implode(' AND ',$data);
      break;

    case 'update':
      foreach($ak as $ck) $data[$ck] = $ck . '=' . ($mask?$this->mask($data[$ck],$ck,$tabname):$data[$ck]);
      $res = implode(', ',$data);
      break;

    case 'insert':
      if($mask) foreach($ak as $ck) $data[$ck] = $this->mask($data[$ck],$ck,$tabname);
      $res = '(' . implode(', ',$ak) . ') VALUES(' . implode(', ',$data) . ')';
      break;

    case 'select':
      if($this->is_namedarray($data)) $res = implode(', ',$ak); else $res = implode(', ',$data);
      break;

    case 'order':
      if(is_numeric($ak[0])){
	$res = implode(', ',$data);
      } else {
	$res = array();
	foreach($data as $ck=>$cv) $res[] = $ck . ($cv?'':' DESC');
	$res = implode(', ',$res);
      }
      break;
    }
    return($res);
  }

  /* make a insert or update
     ident 
       + named array(field:value) if this array defines one or more existing rows
         -> update this line and return the number of affected rows
	 -> otherwise insert the new data and return the new values of the fields of index
       + array (with numerical keys)
         -> insert the new data and return the new values of the fields of index
       + index name -> insert data and returns the new values used in the index
       + null -> insert data and returns ...
          - if an unique index exists the new value of the fields of this index
	    (if more than one index exist the first orderd by indexname is used
          - otherwise the negativ of the number of inserted rows
     if ident defines one or more rows update is used and the
         return is the number of changed rows
     if not insert is used and the result is
       - if ident is defined -> the new values in this fields
       - if ident is empty but a unique index exsist the new values of this fields
       - otherwise the number of insertet rows (but negativ to separate from update)
   */

  function write_row($tabname,$data,$ident=array(),$transform=array()){
    if(is_array($ident) and count($ident)>0){
      if($this->is_namedarray($ident)){
	$wk = $this->sql_make($ident,'where',$tabname);
	$sql = 'SELECT count(*) FROM ' . $tabname . ' WHERE ' . $wk;
	$ins = $this->read_field($sql) == 0;
	if($ins) $data = array_merge($ident,$data); // if ident includes new data too!
      } else {
	$ins = true;
      }
    } else if(is_string($ident)){
      $ins = true;
      $ident = $this->table_unique($tabname,$ident);      
    } else {
      $ins = true;
      $ident = $this->table_unique($tabname,$ident);
    }
    $data = $this->transform_row($data,$tabname,array_merge($this->transform,$transform),FALSE);
    if($ins) {
      $sql = 'INSERT INTO ' . $tabname . $this->sql_make($data,'insert',$tabname);
      if(is_array($ident)){
	$this->sql_execute('BEGIN');
	$qe = $this->sql_execute($sql);
	$sql = 'SELECT ' . $this->sql_make($ident,'select',$tabname) . ' FROM ' . $tabname
	  . ' WHERE ' . $this->sql_make($data,'where',$tabname);
	$res = $this->read_array($sql);
	$this->sql_execute('COMMIT');
	if($qe===FALSE) return(FALSE);
	if(is_array($res)) $res = $res[count($res)-1]; 
	else $res = -pg_affected_rows($qe);
      } else {
	$qe = $this->sql_execute($sql);
	if($qe===FALSE) return(FALSE);
	$res = -pg_affected_rows($qe);
      }
    } else {
      $sql = 'UPDATE ' . $tabname . ' SET ' . $this->sql_make($data,'update',$tabname) . ' WHERE ' . $wk;
      $qe = $this->sql_execute($sql);
      if($qe===FALSE) return(FALSE);
      $res = pg_affected_rows($qe);
    }
    return($res);
  }


  function sql_insert($tabname,$data,$transform=array()){
    $data = $this->transform_row($data,$tabname,array_merge($this->transform,$transform),FALSE);
    return 'INSERT INTO ' . $tabname . $this->sql_make($data,'insert',$tabname);
  }

  function sql_update($tabname,$data,$ident=array(),$transform=array()){
    $wk = $this->sql_make($ident,'where',$tabname);
    $data = $this->transform_row($data,$tabname,array_merge($this->transform,$transform),FALSE);
    return 'UPDATE ' . $tabname . ' SET ' . $this->sql_make($data,'update',$tabname) . ' WHERE ' . $wk;
  }

  function sql_remove($tabname,$ident=array()){
    $sql = 'DELETE FROM ' . $tabname;
    if(count($ident)>0) $sql .= ' WHERE ' . $this->sql_make($ident,'where',$tabname);
    return $sql;
  }

  function insert($tabname,$data,$transform=array()){
    $qe = $this->sql_execute($this->sql_insert($tabname,$data,$transform));
    if($qe===FALSE) return(FALSE);
    return pg_affected_rows($qe);
  }

  function remove($tabname,$ident=array()){
    $qe = $this->sql_execute($this->sql_remove($tabname,$ident));
    return $qe===FALSE?NULL:pg_affected_rows($qe);
  }

  function count($tabname,$ident=array()){
    $sql = 'SELECT count(*) FROM ' . $tabname;
    if(count($ident)>0) $sql .= ' WHERE ' . $this->sql_make($ident,'where',$tabname);
    return $this->read_field($sql);
  }

  // namefield: if not null it definies which field (numerical or name) gives the rows a name
  // transpose will return an array of columns instead an array of fields
  function read_array($sql,$namefield=null,$transform=array()){
    if(is_null($this->db)) return(null);
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE) return(NULL);
    $qa = pg_fetch_all($qe);
    if(!is_array($qa) or count($qa)==0) return(NULL);
    if(is_null($namefield)) {
      $res = $qa;
    } else {
      if(is_numeric($namefield)) $namefield = pg_field_name($qe,$namefield);
      $res = array();
      foreach($qa as $qi) $res[$qi[$namefield]] = $qi;
    }
    return($this->transform_array($res,NULL,array_merge($this->transform,$transform),TRUE));
  }

  // namefield: if not null it definies which field (numerical or name) gives the items a name
  function read_column($sql,$field=0,$namefield=null,$transform=array()){
    if(is_null($this->db)) return(null);
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE) opt::r(NULL,"SQL Error: '$sql'");
    $qa = pg_fetch_all($qe);
    if(!is_array($qa) or count($qa)==0) return(NULL);
    if(is_numeric($field)) $field = pg_field_name($qe,$field);
    $res = array();
    $trans = array_merge($this->transform,$transform);
    if(empty($trans)){
      if(is_null($namefield)) {
	foreach($qa as $qi) $res[] = $qi[$field];
      } else {
	if(is_numeric($namefield)) $namefield = pg_field_name($qe,$namefield);
	foreach($qa as $qi) $res[$qi[$namefield]] = $qi[$field];
      }
    } else {
      if(is_null($namefield)) {
	foreach($qa as $qi){
	  $qi = $this->transform_row($qi,NULL,$trans,TRUE);
	  $res[] = $qi[$field];
	}
      } else {
	if(is_numeric($namefield)) $namefield = pg_field_name($qe,$namefield);
	foreach($qa as $qi) {
	  $qi = $this->transform_row($qi,NULL,$trans,TRUE);
	  $res[$qi[$namefield]] = $qi[$field];
	}
      }
    }
    return($res);
  }

  // reads a single row
  function read_row($sql,$row=0,$named=TRUE,$transform=array()){
    if(is_null($this->db)) return(NULL);
    if($this->uselimit){
      $sql .= ' LIMIT 1 OFFSET 0';
      $row = 0;
    } 
    $qe = pg_query($this->db,$sql);
    //if($qe===FALSE) qk();
    if($qe===FALSE) return(NULL);
    if($row>=pg_num_rows($qe)) return(NULL);
    $qa = pg_fetch_array($qe,$row,PGSQL_ASSOC);
    if(!is_array($qa)) return(NULL);
    $res = $this->transform_row($qa,NULL,array_merge($this->transform,$transform),TRUE);
    return($named?$res:array_values($res));

  }

  //single field defined by name/colposition and row
  function read_field($sql,$field=0,$row=0,$transform=array()){
    if(is_null($this->db)) return(NULL);
    if($this->uselimit){
      $sql .= ' LIMIT 1 OFFSET 0';
      $row = 0;
    } 
    $qe = pg_query($this->db,$sql);
    //if($qe===FALSE) qk();
    if($qe===FALSE) return $this->err->ret($sql,3,NULL);
    if($row>=pg_num_rows($qe)) return(NULL);
    $qa = pg_fetch_array($qe,$row,PGSQL_ASSOC);
    if(is_numeric($field)) {
      $ak = array_keys($qa);
      $field = $ak[$field];
    } else $field = strtolower($field); 
    $res = array($field=>$qa[$field]);
    $transform = is_array($transform)?array_merge($this->transform,$transform):$this->transform;
    $res = $this->transform_row($res,NULL,$transform,TRUE);
    return($res[$field]);
  }

  //single field defined by name/colposition and row
  function read_fielddef($sql,$default=null,$field=0,$row=0){
    if(is_null($this->db)) return(NULL);
    if($this->uselimit){
      $sql .= ' LIMIT 1 OFFSET 0';
      $row = 0;
    } 
    $qe = pg_query($this->db,$sql);
    if($row>=pg_num_rows($qe)) return($default);
    if(is_numeric($field)){
      if($row>=pg_num_fields($qe)) return($default);
      $qa = pg_fetch_array($qe,$row,PGSQL_NUM);
      return($qa[strtolower($field)]);
    } else {
      $field = strtolower($field);
      $qa = pg_fetch_array($qe,$row,PGSQL_ASSOC);
      if(array_key_exists($field,$qa)) return($qa[$field]);
      else return($default);
    }
    

  }

  function read_count($sql,$row=0,$named=TRUE){
    if(is_null($this->db)) return(NULL);
    if($this->uselimit){
      $sql .= ' LIMIT 1 OFFSET 0';
      $row = 0;
    } 
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE) return(NULL);
    return(pg_num_rows($qe));
  }

  /* dynamical arguments
   string or array of strings (with numerical keys) -> asked fields (def: all)
   array with text-keys -> criteria (field=>value, combination by and)
   boolean: transpose   
   a second string (or field-array already given) -> order statement
   */
  function load_array($tabname/* ...*/){
    $args = func_get_args();
    list($sql,$oarg) = call_user_func_array(array($this,'_load_prepare'),$args);
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE) return(NULL);
    $qa = pg_fetch_all($qe);
    if(!is_array($qa)) return(NULL);
    if($oarg['transpose']) $qa = $this->transpose($qa);
    return($qa);
  }

  function load_row($tabname/*...*/){
    $args = func_get_args();
    list($sql,$oarg) = call_user_func_array(array($this,'_load_prepare'),$args);
    if($this->uselimit){$sql .= ' LIMIT 1 OFFSET 0';$oarg['row'] = 0;}
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE) return(NULL);
    if($oarg['row']>=pg_num_rows($qe)) return(NULL);
    $qa = pg_fetch_array($qe,$oarg['row'],PGSQL_ASSOC);
    return($qa);
  }    
  

  function load_column($tabname/*...*/){
    $args = func_get_args();
    list($sql,$oarg) = call_user_func_array(array($this,'_load_prepare'),$args);
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE) qk();
    if($qe===FALSE) return(NULL);
    $qa = pg_fetch_all($qe);
    if(!is_array($qa)) return(NULL);
    $nr = count($qa);
    if($nr==0) return(array());
    if(count($qa[0])==1){
      for($ci=0;$ci<$nr;$ci++) $qa[$ci] = array_shift($qa[$ci]);
      $res = $qa;
    } else {
      $res = array();
      for($ci=0;$ci<$nr;$ci++){
	$cr = array_values($qa[$ci]);
	$res[$cr[0]] = $cr[1];
      }
    }
    return($res);
  }

  function load_field(/* ... */){
    $args = func_get_args();
    list($sql,$oarg) = call_user_func_array(array($this,'_load_prepare'),$args);
    if($this->uselimit){$sql .= ' LIMIT 1 OFFSET 0';$oarg['row'] = 0;}
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE)
      if(function_exists('trg_ret')) trg_ret("SQL Error: '$sql'",NULL); //array('cls'=>__CLASS__),NULL);
      else return NULL;
    if($oarg['row']>=pg_num_rows($qe)) return(NULL);
    $qa = pg_fetch_array($qe,$oarg['row'],PGSQL_ASSOC);
    return(array_shift($qa));
  }

  function load_count(/* ... */){
    $args = func_get_args();
    list($sql,$oarg) = call_user_func_array(array($this,'_load_prepare'),$args);
    if($this->uselimit){$sql .= ' LIMIT 1 OFFSET 0';$oarg['row'] = 0;}
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE) return(NULL);
    return(pg_num_rows($qe));
  }


  /* common preparation for load_* commands
   returns an array with 0:sql-statement 1: array of additional arguments
   first element: has to be the tablename!
   first string / first array with numeric keys -> asked fields
   first array with string keys -> where clause (field=>value) AND
   first boolean: -> additional arg, name: transpose (default: FALSE)
   first numeric: -> additional arg, name: row (default: 0)
   second string (or first if fieldlist given) -> order statement
   other will given the second argument
   */
  function _load_prepare(){
    $nargs = func_num_args();
    $args = func_get_args();
    if($nargs<1) retunr(NULL);
    $tabname = $args[0];
    if(!$this->table_exists($tabname,2)) return(NULL);

    $fld = array();
    $crit = array();
    $ord = '';
    $oarg = array('row'=>0,'transpose'=>FALSE);
    $flds = $this->field_name($tabname);

    for($ci=1;$ci<$nargs;$ci++){
      $ca =$args[$ci];
      if(is_string($ca)) {
	if(count($fld)>0) $ord = ' ORDER BY ' . $ca; else $fld = array($ca);
      } else if(is_bool($ca)){
	$oarg['transpose'] = $ca;
      } else if(is_numeric($ca)){
	$oarg['row'] = $ca;
      } else if(is_array($ca) and count($ca)>0){
	$ak = array_keys($ca);
	$ck = array_shift($ak);
	if(is_string($ck)) $crit = $ca; else $fld = $ca;
      }
    }

    if(count($fld)>0){
      $ak = array_keys($fld);
      foreach($ak as $ck)
	if(preg_match('/^[_a-z][_a-z[0-9]*$/i',$fld[$ck]))
	  if(!in_array($fld[$ck],$flds))
	    return(NULL);
    } else $fld = $flds;

    $sql = 'SELECT ' . implode(', ',$fld) . ' FROM ' . $tabname;
    if(count($crit)>0) $sql .= ' WHERE ' . $this->sql_make($crit,'where',$tabname);
    $sql .= $ord;
    return(array($sql,$oarg));
  }

  function _array2fields($tabname,$flds){
    $flds = array_intersect($flds,$this->field_name($tabname));
    if(count($flds)==0) $sel = 'count(*)'; else $sel = implode(', ',$flds);
    return($sel);
  }

  function tabstructure_get($tabname){
    if(!array_keys_exists($tabname,$this->structure)) return(NULL);
    return($this->structure[$tabname]);
  }

  // name of the nth field of a table
  function field_name($tabname,$column=null){
    $sql = 'SELECT attname FROM pg_attribute WHERE attrelid = \'' . $tabname . '\'::regclass';
    $res = $this->read_column($sql . ' AND attnum>0 ORDER BY attnum');
    // dropped columns may be includded here! therfore the next two line
    $res = array_values(preg_grep('/pg[.]dropped/',$res,PREG_GREP_INVERT));
    if(is_null($column)) return($res);
    return($res[$column-1]);
  }

  // pos of the field of a table
  function field_pos($tabname,$column=0){
    $sql = 'SELECT attnum FROM pg_attribute WHERE attrelid = \'' . $tabname . '\'::regclass'
         . ' AND attname=\'' . $column . '\'';
    return($this->read_field($sql));
  }

  // returns the type of a field column is numeric or string
  function field_type($tabname,$column=null){
    $sql = 'SELECT pg_catalog.format_type(atttypid,atttypmod), attname FROM pg_attribute WHERE attrelid = \''
      . $tabname . '\'::regclass';
    if(is_null($column))     return($this->read_column($sql . ' AND attnum>0 ORDER BY attnum',0,1));
    if(is_numeric($column))  return($this->read_field( $sql . ' AND attnum=' . $column));
    return($this->read_field( $sql . ' AND attname=\'' . $column . '\''));
  }

  /* returns the indexes of a table
     result is a nested array (name=string, unique=bool, metod=string, fields=array of stings, where=string)
     name
       null -> no restriction
       string -> name of the index
       true/false -> return only (non) unique index
  */
  function table_index($tabname,$name=null){
    $sql = 'SELECT indexdef, indexname from pg_indexes WHERE tablename=\'' . $tabname . '\'';
    if(is_string($name))
      $sql .=  ' AND indexname=\'' . $name . '\'';
    else if($name===true)
      $sql .= ' AND indexdef LIKE \'CREATE UNIQUE INDEX %\'';
    else if($name===false)
      $sql .= ' AND indexdef NOT LIKE \'CREATE UNIQUE INDEX %\'';
    $re = $this->read_column($sql . ' ORDER BY indexname',0,1);
    if(!is_array($re)) return(null);
    $pat = '/(CREATE +)([^ ]*)( *INDEX +[^ ]+ +ON +[^ ]+)( +USING +[^ ]+)? +[(](.*)[)]'
      . '( +TABLESPACE [^ ]+)?( +WHERE .*)?.*$/';
    while(list($ak,$av)=each($re)){
      $tv = preg_replace($pat,'$2;$4;$5;$6;$7',$av);
      $tv = explode(';',$tv);
      $re[$ak] = array('name'=>$ak,
		       'unique'=>$tv[0]=='UNIQUE',
		       'method'=>trim(substr($tv[1],strpos($tv[1],'USING')+6)),
		       'fields'=>explode(', ',$tv[2]),
		       'tablespace'=>trim(substr($tv[3],strpos($tv[3],'TABLESPACE')+10)),
		       'condition'=>trim(substr($tv[4],strpos($tv[4],'WHERE')+6)));
    }
    if(!is_string($name)) return($re); else return(array_shift($re));
  }

  function table_unique($tabname,$restrict=true){
    $idt = $this->table_index($tabname,$restrict);
    if(!is_array($idt)) return(null);
    if(!is_string($restrict)) $idt = array_shift($idt);
    return($idt['fields']);
  }

  // gets the defaults of a table, field: null, fieldname or position
  function table_default($tabname,$field=null){
    if(is_null($field)){
      $sql = 'SELECT attname, adsrc FROM pg_attrdef JOIN pg_attribute'
	. ' ON pg_attrdef.adrelid=pg_attribute.attrelid AND pg_attrdef.adnum=pg_attribute.attnum'
	. ' WHERE pg_attribute.attrelid=\'' . $tabname . '\'::regclass AND attnum>0 ORDER BY attnum';
      return($this->read_column($sql,1,0));
    } else {
      if(is_string($field)) $field = $this->field_pos($tabname,$field);
      $sql = 'SELECT adsrc FROM pg_attrdef WHERE adrelid=\'' . $tabname . '\'::regclass AND adnum=' . $field;
      return($this->read_field($sql));
    }
  }

  // list of public tables
  // 0: tables, 1: views, 2: both
  function table_list($mode=0){
    switch($mode){
    case 1:
      $sql = 'SELECT viewname as name FROM pg_views WHERE schemaname=\'public\''
	. ' ORDER BY viewname';
      break;
    case 2:
      $sql = 'SELECT tablename as name FROM pg_tables WHERE schemaname=\'public\''
	. ' UNION SELECT viewname FROM pg_views WHERE schemaname=\'public\''
	. ' ORDER BY name';
      break;
    default:
      $sql = 'SELECT tablename as name FROM pg_tables WHERE schemaname=\'public\''
	. ' ORDER BY tablename';
    }
    return($this->read_column($sql));
  }
  
  function table_exists($tabname,$mode=0){
    switch($mode){
    case 1:
      $sql = 'SELECT count(*) FROM pg_views WHERE schemaname=\'public\''
	. ' AND viewname=\'' . $tabname . '\'';
      break;
    case 2:
      $sql = 'SELECT count(*) FROM ('
	. 'SELECT tablename as n FROM pg_tables WHERE schemaname=\'public\''
	. ' UNION SELECT viewname FROM pg_views WHERE schemaname=\'public\'' 
	. ') s WHERE n=\'' . $tabname . '\'';
      break;
    default:
      $sql = 'SELECT count(*) FROM pg_tables WHERE schemaname=\'public\''
	. ' AND tablename=\'' . $tabname . '\'';
    }

    if(is_null($this->db)) return(NULL);
    $qe = pg_query($this->db,$sql);
    if($qe===FALSE) return(NULL);
    $qa = pg_fetch_array($qe,0,PGSQL_NUM);
    return($qa[0]==1);
    
  }
  // gets the constraints of a table
  function table_constraint($tabname,$name=null,$typ=null){
    $sql = 'select *, confrelid::regclass from pg_constraint where conrelid=\'' . $tabname
      . '\'::regclass';
    if(!is_null($typ)) $sql .= ' AND contype=\'' . $typ . '\'';
    if(!is_null($name)) $sql .=  ' AND conname=\'' . $name . '\'';
    $sql .= ' ORDER BY conname';
    $re = $this->read_array($sql,'conname');
    while(list($ak,$av)=each($re)){
      $ri = array();
      while(list($bk,$bv)=each($av)) $ri[substr($bk,3)] = $bv; 
      unset($ri['relid']);
      unset($ri['namespace']);
      if(preg_match('/^{[0-9]+(, *[0-9]+)*}$/',$ri['key'])){
	$rx = array();
	foreach(explode(',',substr($ri['key'],1,-1)) as $cf)
	  $rx[] = $this->field_name($tabname,(int)$cf);
	$ri['key'] = $rx;
      }
      if(preg_match('/^{[0-9]+(, *[0-9]+)*}$/',$ri['fkey'])){
	$rx = array();
	foreach(explode(',',substr($ri['fkey'],1,-1)) as $cf)
	  $rx[] = $this->field_name($ri['frelid'],(int)$cf);
	$ri['fkey'] = $rx;
      }
      $re[$ak] = $ri;
    }
    if(is_null($name)) return($re); else return(array_shift($re));
  }

  // list of public views
  function view_list(){
    $sql = 'SELECT viewname FROM pg_views WHERE schemaname=\'public\' ORDER BY viewname';
    return($this->read_column($sql));
  }

  // ermittelt die rechte auf relationen
  function grants($relnames=null,$user=null){
    if(is_null($relnames)) $rels = array_merge($this->table_list(),$this->view_list());
    else if(!is_array($relnames)) $rels = array($relnames);
    else $rels = $relnames;
    $sql = 'SELECT relname, relacl FROM pg_class WHERE relname in (\''
      . implode("','",$rels) . '\')';
    $sql .= ' ORDER BY relname';
    $qa = $this->read_column($sql,'relacl','relname');
    $rights = array('r'=>'SELECT','w'=>'UPDATE','a'=>'INSERT','d'=>'DELETE','R'=>'RULE',
		    'x'=>'REFERENCES','t'=>'TRIGGER','X'=>'EXECUTE','U'=>'USAGE',
		    'C'=>'CREATE','T'=>'TEMPORARY');
    while(list($ak,$av)=each($qa)){
      if(is_null($av)) continue;
      $rx = array();
      $tv = explode(',',preg_replace('/^.*{ *(.*) *}.*$/','$1',$av));
      foreach($tv as $ct){
	$cn = substr($ct,0,strpos($ct,'='));
	if(!is_null($user) and $cn!=$user) continue;
	$ct = preg_replace('/.*=(.*)\/.*/','$1',$ct);
	$ry = array();
	reset($rights);
	while(list($bk,$bv)=each($rights)) 
	  $ry[$bv] = !(strpos($ct,$bk)===false);
	$rx[empty($cn)?'PUBLIC':$cn] = $ry;
      }
      if(is_null($user))
	$qa[$ak] = $rx;
      else 
	$qa[$ak] = isset($rx[$user])?$rx[$user]:NULL;
    }
    if(!is_null($relnames) and !is_array($relnames)) return($qa[$relnames]); else return($qa);
  }

  function seq_list(){
    
  }

  // ======================================================================
  // various aux functions
  // ======================================================================


  // returns true if none of the keys of $arr are numerical
  function is_namedarray($arr){
    $bk = array_keys($arr);
    array_walk($bk,create_function('&$elem','$elem = is_numeric($elem)?1:0;'));
    return(array_sum($bk)==0);
  }

  function transpose($res){
    $re = array();
    $ak = array_keys($res);
    $bk = array_keys($res[$ak[0]]);
    foreach($bk as $dk) $re[$dk] = array();
    foreach($ak as $ck) foreach($bk as $dk) $re[$dk][$ck] = $res[$ck][$dk];
    return($re);

  }
  // transform function for read_* statements
  function transform_array($data,$table,$trans,$read=TRUE){
    if(empty($trans)) return($data);
    $res = array();
    foreach($data as $key=>$val)
      $res[$key] = $this->transform_row($data[$key],$table,$trans,$read);
    if(def($trans,'transpose')==TRUE) $res = $this->transpose($res);
    return($res);
  }
  
  function transform_row($data,$table,$trans,$read=TRUE){
    return $data; // done with set client_encoding to 'xxx'
    if(empty($trans)) return($data);
    if(!is_array($data)) return($data);
    $res = array();
    foreach($data as $key=>$val){
      if(is_string($val) and isset($trans['utf8'])){
	if($trans['utf8']===TRUE)
	  $val = $read?utf8_decode($val):utf8_encode($val);
	else if($trans['utf8']===FALSE)
	  $val = !$read?utf8_decode($val):utf8_encode($val);
      }
      $res[$key] = $val;
    }
    return($res);
  }

  public function str2time($date){
    if(strlen($date)<10)
      return mktime(0,0,0,
		    (int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4));
    return mktime((int)substr($date,11,2),(int)substr($date,14,2),(int)substr($date,17,4),
		  (int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4));
    
  }


  /**
   * add['page'] array of to integers: 0: n'th page (1,2 ...); 1: page-size
   */
  public function build_sql($table,$fields,$add=array()){
    $fields = $this->sql_make($fields,'select',$table);
    
    $sql = 'SELECT ' . $fields . ' FROM ' . $table;

    if(isset($add['where']) and (is_string($add['where']) or (is_array($add['where']) and count($add['where']))))
      $sql .= ' WHERE ' . $this->sql_make($add['where'],'where',$table);
       
    if(isset($add['order'])) 
      $sql .= ' ORDER BY ' . $this->sql_make($add['order'],'order');

    if(isset($add['page']))
      $sql .= ' LIMIT ' . $add['page'][1] . ' OFFSET ' . ($add['page'][1]*($add['page'][0]-1));
    
    return $sql;
  }

}



class opc_pg_value {
  var $table = NULL;
  var $idfield = NULL;
  var $valfield = NULL;

  var $_idfield = TRUE;
  var $_numid = TRUE;

  var $err = NULL;


  /* optional in this order: tablename, idfield, valuefield */
  function opc_pg_value(&$db /* ... */){ 
    $al = func_get_args();
    $msgs = array(2=>'not a valid postgres-db resource',
		  3=>'unknown table',
		  4=>'unknown id field',
		  5=>'unkown value field',
		  6=>'SQL Error',
		  7=>'no value found',
		  8=>'multiple values found');
    $this->err = new opc_err($msgs);
    if(!is_resource($db)) return($this->err->set(2));
    if(get_resource_type($db)!='pgsql link') return($this->err->set(2));
    $this->db = $db;
    array_shift($al);
    if(count($al)>0) call_user_func_array(array(&$this,'set_table'),$al);
  }

  function set_table($table /* [idfield [valfield]]*/){
    $al = func_get_args();
    $sql = 'SELECT * FROM ' . $table;
    if(@pg_query($this->db,$sql)===FALSE) return($this->err->ret(3));
    $this->table = $al[0];
    if(count($al)==1) return($this->err->ret(0));
    array_shift($al);
    return(call_user_func_array(array(&$this,'set_idField'),$al));
  }

  function set_idField($field /* [valfield]*/){
    $al = func_get_args();
    if(is_null($this->table))  return($this->err->ret(3));
    $sql = 'SELECT ' . $field . ' FROM ' . $this->table;
    if(@pg_query($this->db,$sql)===FALSE) return($this->err->ret(4));
    $this->idfield = $field;
    if(count($al)==1) return($this->err->ret(0));
    array_shift($al);
    return(call_user_func_array(array(&$this,'set_valField'),$al));
  }

  function set_valField($field /* [valfield]*/){
    if(is_null($this->table))  return($this->err->ret(3));
    if(is_null($this->idfield))  return($this->err->ret(4));
    $sql = 'SELECT ' . $field . ' FROM ' . $this->table;
    if(@pg_query($this->db,$sql)===FALSE) return($this->err->ret(5));
    $this->valfield = $field;
    return($this->err->ret(0));
  }
  

  function get($id /* [valuefield [idfield [table]]*/){
    $al = func_get_args();
    if(is_null($this->db)) return($this->err->ret(2));
    switch(count($al)){
    case 1: array_push($al,$this->valfield,$this->idfield,$this->table); break;
    case 2: array_push($al,$this->idfield,$this->table); break;
    case 3: array_push($al,$this->table); break;
    }
    if($al[2]!==$this->_idfield){
      $sql = 'SELECT pg_catalog.format_type(atttypid,atttypmod), attname FROM pg_attribute'
	. ' WHERE attrelid = \'' . $al[3] . '\'::regclass AND attname=\'' . $al[2] . '\'';
      $qe = pg_query($this->db,$sql);
      $typ = pg_fetch_result($qe,0);
      $num = ';bigint;integer;smallint;real;double precision;decimal;numeric;'
	. 'serial;bigserial;id;double;int;float;';
      $this->_idfield = $al[2];
      $this->_numid = $numid;
    } 
    
    $sql = "SELECT $al[1] FROM $al[3] WHERE $al[2]=";
    if($this->_numid) $sql .= $al[0]; else $sql .= "'$al[0]'";
    if(FALSE === $qe = pg_query($this->db,$sql)) return($this->err->ret($sql,6,FALSE));
    switch(pg_num_rows($qe)){
    case 0: return($this->err->ret(7,FALSE));
    case 1: $this->err->set(0); break;
    default:
      return($this->err->ret(8,FALSE));
    }
    if($al[1]=='*') return(pg_fetch_assoc($qe));
    else return(pg_fetch_result($qe,0));
  }


}

// alias
class opc_pg extends pgdb {}
?>