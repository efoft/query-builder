<?php
namespace QueryBuilder;
/**
 * @package   SQLQueryBuilder
 * @author    efoft
 *
 * Builds SQL query with :tokens from chained calls.
 *
 * Example:
 * $q = new SQLQueryBuilder();
 * $q->from('table1')->select()->where(array('id'=>13))->getQuery();
 * output:
 * SELECT * FROM table1 WHERE id=:id;
 * $values = $q->getBindings();
 */
class SQLQueryBuilder
{
  /**
   * @var   string
   *
   * Defines how field names are quoted in queries according to different SQL standards.
   * Accepted values are: 'none','sql','mysql','mssql', 'sqlite'
   *
   * There are four ways of quoting keywords:
   * 'keyword'		A keyword in single quotes is a string literal.
   * "keyword"		A keyword in double-quotes is an identifier.
   * [keyword]		A keyword enclosed in square brackets is an identifier. This is not standard SQL. 
   *              This quoting mechanism is used by MS Access and SQL Server and is included in SQLite for compatibility.
   * `keyword`		A keyword enclosed in grave accents (ASCII code 96) is an identifier. This is not standard SQL.
   *              This quoting mechanism is used by MySQL and is included in SQLite for compatibility.
   */
  private $quoting;
  
  /**
   * @var   boolean
   *
   * Sets debug mode.
   */
  private $debug = false;
  
  /**
   * @var   array
   * 
   * Keeps all query data. The structure is initialized with newQuery();
   */
  private $stmt = array();

  /**
   * @var   array
   *
   * Keeps all :token values.
   */
  private $bindings = array();
  
  /**
   * @param   string    $quoting    
   * @param   boolean   $debug
   * @throws  InvalidArgumentException
   */
  public function __construct($quoting = 'none')
  {
    $allowed_quoting_types = array('none','sql','mysql','mssql','sqlite');
    if ( ! is_string($quoting) || ! in_array($quoting, $allowed_quoting_types) )
      throw new \InvalidArgumentException(sprintf('Quoting type "%s" is not supported, allowed are: %s', $quoting, implode(', ', $allowed_quoting_types)));
    $this->quoting = $quoting;
    $this->newQuery();
  }

  /*
   * @param   boolean  $debug
   */
  public function setDebug($debug = false)
  {
    $this->debug = (bool)$debug;
  }
  
  /**
   * Initializes the structure of the variable containing the query params.
   * Must be called first for each new query when reusing the class instance.
   * Can be chained with other calls.
   *
   * @return  $this
   */
  public function newQuery()
  {
    $this->stmt = array(
      'tables'  => array(),
      'action'  => NULL,
      'cols'    => array(),
      'distinct'=> NULL,
      'order'   => array(),
      'group'   => array(),
      'join'    => array(),
      'where'   => array(),
      'limit'   => NULL
    );
    $this->bindings = array();
    return $this;
  }

  /**
   * @return  $string   Built SQL query with :tokens.
   * @throws  InvalidArgumentException
   */
  public function getQuery()
  {
    switch($this->stmt['action'])
    {
      case 'SELECT':
        $expr  = $this->stmt['distinct'] . $this->getSelectCols() . ' FROM ' . implode(',', $this->stmt['tables']);
        $expr .= $this->getJoin() . $this->getWhere() . $this->getOrderBy() . $this->getGroupBy() . $this->getLimit();
        break;
      case 'INSERT':
        $expr = 'INTO ' . $this->stmt['tables'][0] . $this->getInsertCols() . ' VALUES ' . $this->getInsertValues();
        break;
      case 'UPDATE':
        $expr = $this->stmt['tables'][0] . $this->getJoin() . ' SET ' . $this->getUpdateSet() . $this->getWhere();
        break;
      case 'DELETE':
        // in case of DELETE... INNER JOIN it's required to specify what table we delete records from
        $expr = ( $join = $this->getJoin() ) ? $this->stmt['tables'][0] : '';
        $expr .= ' FROM ' . $this->stmt['tables'][0] . $join . $this->getWhere();
        break;
      default:
        throw new \InvalidArgumentException(sprintf('Action "%s" is not supported', $this->action));
    }
    return $this->stmt['action'] . ' ' . $expr . ';';
  }
  
  /**
   * Specifies the table(-s) on which the query is supposed to be run.
   * Accepts several args at once or can be called multiple times for appending table names. It is only useful for SELECT queries.
   * Can be chained with other calls.
   *
   * @param   string|array    can be multiple args - see format at buildArrayOfArgs()
   * @return  $this
   */
  public function table()
  {
    $this->stmt['tables'] = array_unique(array_merge($this->stmt['tables'], $this->buildArrayOfArgs(func_get_args())));
    return $this;
  }
  
  /**
   * Alias for table()
   */
  public function from()
  {
    return call_user_func_array(array($this, 'table'), func_get_args());
  }
  
  /**
   * Specifies the number of database table columns to run select on.
   * Accepts several args at once or can be called multiple times for appending field names.
   * Can be chained with other calls.
   *
   * @param   string|array    can be multiple args - see format at buildArrayOfArgs()
   * @return  $this
   */
  public function select()
  {
    $this->stmt['action'] = 'SELECT';
    if ( func_num_args() !== 0 )
      $this->stmt['cols'] = array_unique(array_merge($this->stmt['cols'], $this->buildArrayOfArgs(func_get_args()))); 

    return $this;
  }
  
  /**
   * If called the word 'DISTINCT' is added to a SELECT query.
   *
   * @return  $this
   */
  public function distinct()
  {
    $this->stmt['distinct'] = 'DISTINCT ';
    return $this;
  }
  
  /**
   * Specifies the field to order by.
   * Accepts several args at once or can be called multiple times for appending field names.
   * Can be chained with other calls.
   *
   * @param   string|array    can be multiple args - see format at buildArrayOfArgs()
   * @return  $this
   */
  public function order()
  {
    $this->stmt['order'] = array_unique(array_merge($this->stmt['order'], $this->buildArrayOfArgs(func_get_args())));
    return $this;
  }

  /**
   * Specifies the field to group by.
   * Accepts several args at once or can be called multiple times for appending field names.
   * Can be chained with other calls.
   *
   * @param   string|array    can be multiple args - see format at buildArrayOfArgs()
   * @return  $this
   */
  public function group()
  {
    $this->stmt['group'] = array_unique(array_merge($this->stmt['group'], $this->buildArrayOfArgs(func_get_args())));
    return $this;
  }
  
  /**
   * Specifies the limit for select.
   * Can be chained with other calls.
   *
   * @param   integer
   * @return  $this
   */
  public function limit($limit)
  {
    $this->stmt['limit'] = $limit;
    return $this;
  }
  
  /**
   * Adds 'JOIN <table> ON ...' to SELECT query.
   * Can be called multiple times per query.
   *
   * @param   string    $jointblname    table name that is joined
   * @param   string    $maintlbfld     joining key on main table (no need to specify "table." before it)
   * @param   string    $jointblfld     joining key on joined table
   * @param   string    $type           (optional) type of JOIN
   * @return  $this
   */
  public function join($jointblname, $maintlbfld, $jointblfld, $type = 'LEFT')
  {
    $this->stmt['join'][] = array(
            'table' => $jointblname,
            'f1'    => $maintlbfld,
            'f2'    => $jointblfld,
            'type'  => $type
    );
    return $this;
  }
  
  /**
   * Specifies columns and their values to be inserted into a database.
   * Can be chained with other calls.
   *
   * @param   array   $data   associative array like ('col1'=>value1, 'col2'=>value2 ...)
   * @return  $this
   */
  public function insert($data)
  {
    $this->stmt['action'] = 'INSERT';
    $this->stmt['cols'] = array_merge($this->stmt['cols'], $data);
    return $this;
  }

  /**
   * Specifies columns and their values to be updated into a database.
   * Can be chained with other calls.
   *
   * @param   array   $data   associative array like ('col1'=>value1, 'col2'=>value2 ...)
   * @return  $this
   */
  public function update($data)
  {
    $this->stmt['action'] = 'UPDATE';
    $this->stmt['cols'] = array_merge($this->stmt['cols'], $data);
    return $this;
  }

  /**
   * Forms DELETE FROM table ... statement, no args. Conditions what to delete must be set with where()
   * Can be chained with other calls.
   *
   * @return  $this
   */
  public function delete()
  {
    $this->stmt['action'] = 'DELETE';
    return $this;
  }
  
  /**
   * Specifies WHERE conditions via array. The format is similar to MongoDB find() syntax, but so far limited
   * with $or and $and operators.
   * Can be chained with other calls.
   *
   * @param   array
   * @return  $this
   */
  public function where($where)
  {
    $this->stmt['where'] = array_merge($this->stmt['where'], $where);
    return $this;
  }
  
  /**
   * Helper function that builds a single array combining all the items passed as:
   *  'item1','item2'...
   *  'item1, item2...'
   *   array('item1','item2'...)
   *   and mixes of the above.
   *   
   * @param   array   $args
   * @return  array
   */
  private function buildArrayOfArgs($args)
  {
    $result = array();
    
    foreach($args as $arg)
      if ( is_array($arg) )
        $result = array_merge($result, $arg);
      else if ( is_string($arg) )
        if (false !== strpos($arg, ','))
          $result = array_merge($result, array_map('trim', explode(',',$arg)));
        else
          $result[] = $arg;
      else
        $this->debug && trigger_error(sprintf('Argument "%s" of type %s is skipped.', $arg, gettype($arg)), E_USER_WARNING);
        
    return $result;
  }
  
  private function getSelectCols()
  {
    if ( ! $cols = $this->stmt['cols'] )
      return '*';
    else
    {
      foreach($cols as $k=>&$col)
        if ( ! is_int($k) )
          $col = $this->sanitize($k) . ' AS ' . trim($col);
        else
          $col = $this->sanitize($col);
    }
    
    return implode(',', $cols);
  }
  
  private function getOrderBy()
  {
    if ( ! $this->stmt['order'] ) return '';
    
    $result =  ' ORDER BY ';
    foreach($this->stmt['order'] as $col=>$dest)
    {
      if ( is_int($col) ) ($col = $dest) && ( $dest = NULL);
      $result .= trim($this->sanitize($col) . ' ' . strtoupper($dest)) . ',';
    }
    return substr($result,0,-1);
  }
  
  private function getGroupBy()
  {
    return ( $this->stmt['group'] ) ? ' GROUP BY ' . implode(',', $this->stmt['group']) : '';
  }
  
  private function getLimit()
  {
    return ( $this->stmt['limit'] ) ? ' LIMIT ' . (int)$this->stmt['limit'] : '';
  }
  
  private function getJoin()
  {
    $expr = '';
    foreach($this->stmt['join'] as $j)
    {
      $expr .= ' ' . strtoupper($j['type']) . ' JOIN ' . $j['table'] . ' ON ' . $this->sanitize($this->stmt['tables'][0] . '.' . $j['f1']);
      $expr .= '=' . $this->sanitize($j['table'] . '.' . $j['f2']);
    }
    
    return $expr;
  }
  
  private function getInsertCols()
  {
    $expr = '';
    foreach(array_keys($this->stmt['cols']) as $col)
      $expr .= $this->sanitize($col) . ',';
    return '(' . substr($expr, 0, -1) . ')';
  }
  
  private function getInsertValues()
  {
    $expr = '';
    foreach($this->stmt['cols'] as $col=>$data)
      $expr .= ':' . $this->bind($col,$data) . ',';
    return '(' . substr($expr, 0, -1) . ')';
  }
  
  private function getUpdateSet()
  {
    $set = '';
    foreach ($this->stmt['cols'] as $col=>$data)
      $set .= $this->sanitize($col) . '=:' . $this->bind($col,$data) . ',';

    return substr($set, 0, -1);
  }
  
  private function getWhere()
  {
    $result = $this->buildConditions($this->stmt['where']);
    if ( substr($result,0,1) === '(' ) $result = substr($result,1,strlen($result)-2);
    return ( $result ) ? ' WHERE ' . $result : '';
  }
  
  private function buildExpression($k, $v)
  { 
    if ( preg_match('%/.*/i?%', $v) )
    {
      if ( $case_insensitive = (substr($v,-1,1) === 'i') ) $v = strtolower($v);
      $v = preg_replace(array('%^/%','%/$%','%/i$%'),'',$v); // remove regexp delimeters
      $v = str_replace(array('.*'),'%',$v); // replace regexp substitution with SQL substitutor
  
      $expr = ( $case_insensitive ? 'LOWER(' . $this->sanitize($k) . ')' : $this->sanitize($k) ) . ' LIKE ';
    }
    else
      $expr = $this->sanitize($k) . '=';
    
    return $expr . ':' . $this->bind($k,$v);
  }
  
  private function buildConditions($where, $joiner = ' AND ')
  {
    $conditions = array();
    
    foreach($where as $k=>$v)
    {
      if ( $k === '$or')
        ($result = $this->buildConditions($v, ' OR ')) && $conditions[] = $result;
      elseif ( $k === '$and' || is_numeric($k))
        ($result = $this->buildConditions($v, ' AND ')) && $conditions[] = $result;
      else // key is column
        $conditions[] = $this->buildExpression($k,$v);
    }
    
    if ( count($conditions) > 1 )
      return '(' . implode($joiner, $conditions) . ')';
    elseif ( count($conditions) == 1 )
      return $conditions[0];
  }
  
  /**
   * Encloses the passed field name with quotes according to the quoting type selected at class construct.
   * For fully qualtified field name (mytable.id) it separately quotes each part.
   * '*' is omitted if found.
   *
   * @param   string
   * @return  string
   */
  private function sanitize($name)
  {
    switch ( strtolower($this->quoting) )
    {
      case 'none':
        $ldelim = $rdelim = '';
        break;
      case 'mysql':
        $ldelim = $rdelim = '`';
        break;
      case 'sql':
      case 'sqlite':
        $ldelim = $rdelim = '"';
        break;
      case 'mssql':
        $ldelim = '[';
        $rdelim = ']';
        break;
    }
    
    // If the argument is an SQL-function like SUM(), COUNT() we need to extract field name from it
    if ( false !== strpos($name, '(') )
    {
      // $matches[0]=>original string, [1]=>first substitution(SQL-function), [2]=>SQL-function arg, [3]=>')'
      preg_match('/^(.+\()(.+)(\))$/', $name, $matches);
      $field = $matches[2];
    }
    else
      $field = $name;

    $valueArr = explode('.', $field, 2);
    foreach ($valueArr as $key => $subValue)
      $valueArr[$key] = trim($subValue) == '*' ? $subValue : $ldelim . trim($subValue) . $rdelim;

    $result = implode('.', $valueArr);
    if ( isset($matches) )
      return $matches[1] . $result . $matches[3];

    return $result;
  }
  
  private function bind($k, $v)
  {
    $tag = isset($this->bindings[$k]) ? $k . rand() : $k;
    $tag = str_replace('.', '_',$tag);
    $this->bindings[$tag] = $v;
    return $tag;
  }
  
  public function getBindings()
  {
    return $this->bindings;
  }
}
?>
