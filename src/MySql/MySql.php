<?php

namespace NRich\MySql;

use NRich\MySql\Helper\Escape;
use NRich\MySql\Helper\Implode;
use NRich\MySql\Helper\IsNotNULL;
use NRich\MySql\Helper\IsNULL;
use NRich\MySql\Helper\NoEscape;
use NRich\MySql\Helper\NoQuote;

/**
 * Class MySql
 *
 * @package Njee
 */
class MySql {

    /**
     * @var \mysqli
     */
    protected $_connection;


    /**
     * @var \mysqli_result
     */
    protected $_result;

    /**
     * sets the logging process to true or false
     * @var bool
     */
    protected $_log   = false;

    /**
     *
     * @var string
     */
    protected $_renderedQuery;

    /**
     * @var array|string
     */
    protected $_queryParams;

    /**
     * @var bool
     */
    protected $_debug = false;


    /**
     * MySql constructor.
     * @param $host
     * @param $user
     * @param $password
     * @param $database
     * @param $port
     */
    public function __construct($host, $user, $password, $database, $port)
    {
        $this->_connect($host, $user, $password, $database, $port);
    }

    /**
     * @param $string
     * @return NoQuote
     */
    public static function noQuote($string)
    {
        return new NoQuote($string);
    }

    /**
     * @param $string
     * @return NoEscape
     */
    public static function noEscape($string)
    {
        return new NoEscape($string);
    }

    /**
     * @param $string
     * @return NoEscape
     */
    public static function raw($string)
    {
        return new NoEscape($string);
    }

    /**
     * @param $string
     * @return NoQuote
     */
    public static function null($string = null)
    {
        if($string==''||$string==null) {
            return new NoQuote('NULL');
        }
        return $string;
    }

    public static function dateNull($string = null)
    {
        if($string == '' || $string == null || $string == '0000-00-00' || $string == '0000-00-00 00:00:00') {
            return new NoQuote("NULL");
        }
        return $string;
    }

    public static function toDateTime($string = null)
    {
        $string = self::dateNull($string);
        if($string instanceof NoQuote) return $string;
        return date('Y-m-d H:i:s', strtotime($string));
    }

    public static function toDate($string = null)
    {
        $string = self::dateNull($string);
        if($string instanceof NoQuote) return $string;
        return date('Y-m-d', strtotime($string));
    }

    /**
     * @param bool $empty
     * @param bool $datetimeZero
     * @return IsNULL
     */
    public static function isNULL($empty = false, $datetimeZero = false)
    {
        return new IsNULL($empty, $datetimeZero);
    }

    /**
     * @param bool $empty
     * @param bool $datetimeZero
     * @return IsNotNULL
     */
    public static function isNotNULL($empty = false, $datetimeZero = false)
    {
        return new IsNotNULL($empty, $datetimeZero);
    }

    /**
     * Returns a string for your query
     * e.g.  LIMIT 0, 30
     * @param int $page
     * @param int $itemsPerPage
     * @return string
     */
    public static function paginate($page = 1, $itemsPerPage = 30)
    {
        if($page==null) $page = 1;
        if($itemsPerPage==null) $itemsPerPage = 30;
        return self::noQuote(" LIMIT ".((intval($page)-1)*intval($itemsPerPage)).','.intval($itemsPerPage));
    }

    /**
     * @param $delimiter
     * @param array $array
     * @param null $defaultFirst
     * @return Implode
     */
    public static function implode($delimiter, array $array = [], $defaultFirst = null)
    {
        return new Implode($delimiter, array_merge( ($defaultFirst !== null ? [$defaultFirst] : []), $array));
    }

    /**
     * executes an rendered query to mysql db
     * strings like 'NULL' etc will only accepted on key-based params array (case sensitive)
     * example:
     *  ->query("SOME SQL SHIT WHERE name = :name", array('id' => 'NULL')
     * ---------------------------------
     * OnDebug: Query was rendered but not executed! No changes at the database!
     *          Returns an object with all information about this query!
     *
     * @param       $sql
     * @param array $params
     * @return $this
     * @throws \ErrorException
     */
    public function query($sql, array $params = null)
    {
        $originSql = $sql;

        if($params !== null) {

            $defEscapeParams = array();

            krsort($params);

            if(in_array(substr($sql, 0, 6), ['UPDATE', 'INSERT'])) {
                if(preg_match('/SET[ ]{0,5}\?/', $sql)) {
                    $sqlParams = [];
                    foreach ($params as $key => $param) {
                        $key = $this->_connection->real_escape_string($key);
                        $sqlParams[] = "`$key` = :$key";
                    }
                    $sql = preg_replace('/SET[ ]{0,5}\?/', "SET ".implode(',', $sqlParams), $sql);
                }
            }

            foreach ($params as $key => $param) {
                if($param instanceof Implode) {
                    $newKeys = [];
                    foreach ($param->array as $data) {
                        $newKey = $this->getUniqueKey($params);
                        $params[$newKey] = $data;
                        $newKeys[] = ':'.$newKey;
                    }

                    $sql = str_replace(':'.$key, implode($param->delimiter, $newKeys), $sql);

                }
            }


            foreach ($params as $key => $param) {

                if(is_array($param)) continue;

                if(is_int($key)) {
                    $defEscapeParams[] = $this->_connection->real_escape_string($param);
                    continue;
                }

                if($param instanceof Escape) {

                    if($param instanceof IsNULL) {

                        if($param->empty && $param->datetimeZero) {
                            $sql = preg_replace('/[ ]{1,5}([a-zA-Z._-]{1,60})[ ]{0,5}=[ ]{0,5}:'.$key.'/', " ($1 = '' OR $1 = '0000-00-00 00:00:00' OR $1 IS NULL)", $sql);
                            continue;
                        }

                        if($param->empty) {
                            $sql = preg_replace('/[ ]{1,5}([a-zA-Z._-]{1,60})[ ]{0,5}=[ ]{0,5}:'.$key.'/', " ($1 = '' OR $1 IS NULL)", $sql);
                            continue;
                        }

                        if($param->datetimeZero) {
                            $sql = preg_replace('/[ ]{1,5}([a-zA-Z._-]{1,60})[ ]{0,5}=[ ]{0,5}:'.$key.'/', " ($1 = '0000-00-00 00:00:00' OR $1 IS NULL)", $sql);
                            continue;
                        }

                        $sql = preg_replace('/[ ]{1,5}([a-zA-Z._-]{1,60})[ ]{0,5}=[ ]{0,5}:'.$key.'/', ' $1 IS NULL', $sql);
                        continue;

                    }

                    if($param instanceof IsNotNULL) {

                        if($param->empty && $param->datetimeZero) {
                            $sql = preg_replace('/[ ]{1,5}([a-zA-Z._-]{1,60})[ ]{0,5}=[ ]{0,5}:'.$key.'/', " ($1 != '' AND $1 != '0000-00-00 00:00:00' AND $1 IS NOT NULL)", $sql);
                            continue;
                        }

                        if($param->empty) {
                            $sql = preg_replace('/[ ]{1,5}([a-zA-Z._-]{1,60})[ ]{0,5}=[ ]{0,5}:'.$key.'/', " ($1 != '' AND $1 IS NOT NULL)", $sql);
                            continue;
                        }

                        if($param->datetimeZero) {
                            $sql = preg_replace('/[ ]{1,5}([a-zA-Z._-]{1,60})[ ]{0,5}=[ ]{0,5}:'.$key.'/', " ($1 != '0000-00-00 00:00:00' AND $1 IS NOT NULL)", $sql);
                            continue;
                        }

                        $sql = preg_replace('/[ ]{1,5}([a-zA-Z._-]{1,60})[ ]{0,5}=[ ]{0,5}:'.$key.'/', ' $1 IS NOT NULL', $sql);
                        continue;

                    }

                    if($param instanceof NoQuote) {
                        $sql = str_replace(':'.$key, ($this->_real_escape_exception($param->string)?$param->string:$this->_connection->real_escape_string($param->string)), $sql);
                        continue;
                    }

                    if($param instanceof NoEscape) {
                        $sql = str_replace(':'.$key,$param->string, $sql);
                        continue;
                    }


                } else {

                    $sql = str_replace(':'.$key, ($this->_real_escape_exception($param)?$param:"'".$this->_connection->real_escape_string($param)."'"), $sql);

                }

            }

            if(count($defEscapeParams)) {
                $sql = vsprintf(str_replace("?", "%d", $sql), $defEscapeParams);
            }

        }

        $this->_renderedQuery   = $sql;

        $this->_queryParams     = $params;

        if($this->_debug===true) {
            print_r((object) [
                'message' => "The Query (".substr($sql, 0,10)."...) was not executed, cause debug-mode is activated!",
                'originalQuery' => $originSql,
                'renderedQuery' => $sql,
                'params' => $params,
            ]);
            exit;
        }

        $result = $this->_connection->query($sql);

        if(!$result) {
            throw new \ErrorException(
                'JSON::'.json_encode(array(
                    'errorMessage'  => $this->_connection->error,
                    'originQuery'   => $originSql,
                    'renderedQuery' => $sql,
                    'params'        => $params
                ))
            );
        }

        $this->_result   = $result;

        return $this;
    }


    /**
     * @param $table
     * @param $column
     * @return array
     */
    public function getEnumOptions($table, $column)
    {
        $this->query("SHOW COLUMNS FROM `:table` WHERE Field = :column", array(
            'table' => self::noQuote($table),
            'column' => $column,
        ));

        if($this->count() == 0) return array();

        $type = $this->fetchOne()->Type;

        preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);

        return explode("','", $matches[1]);
    }

    /**
     * Returns an object of your query.
     * By setting emptyObj = true or(and) advObj = true you got an
     * pre-filled object like your select query ordered.
     * The advObj function is for joins, you can also use emptyObj only.
     * But if there is an duplicate column-name it will be overwriten by the
     * last one! If you want to play save - use both! (true, true)
     * ---------------------------------
     * OnDebug: fills each column with complete sql-column-attributes
     *
     *
     * @param bool $asArray
     * @return object|\stdClass|array
     * @throws \ErrorException
     */
    public function fetchOne($asArray = false)
    {
        if($this->_debug===true&&!$this->_result) {
            throw new \ErrorException('fetchOne (in Debug-Mode) works only if the debug() function is executed after the query!');
        }
        if($asArray) {
            return $this->_result->fetch_assoc();
        }
        return $this->_result->fetch_object();
    }

    /**
     * Returns an array of rows, each row is an array.
     * By setting emptyObj = true or(and) advObj = true you got an
     * array of pre-filled object-rows like your select query ordered.
     * The advObj function is for joins, you can also use emptyObj only.
     * But if there is an duplicate column-name it will be overwriten by the
     * last one! If you want to play save - use both! (true, true)
     * ---------------------------------
     * OnDebug: fills each column with complete sql-column-attributes
     *
     * @param bool $asArray
     * @return array
     * @throws \ErrorException
     */
    public function fetchAll($asArray = false)
    {
        if($this->_debug===true&&!$this->_result) {
            throw new \ErrorException('fetchAll (in Debug-Mode) works only if the debug() function is executed after the query!');
        }
        $result = array();
        while($row = $this->_result->fetch_assoc()) {
//            foreach ($row as &$val) {
//                if (is_numeric($val))
//                    $val = $val + 0;
//            }
            if(!$asArray) {
                $result[]   = (object) $row;
            } else {
                $result[]   = (object) $row;
            }
        }
        return $result;
    }

    /**
     * see num_rows()
     * @return int
     */
    public function count()
    {
        return $this->num_rows();
    }

    /**
     * returns the number of rows of the result
     * @return int
     */
    public function num_rows()
    {
        return $this->_result->num_rows;
    }

    /**
     * returns the last INSERT - id
     * @return int
     */
    public function lastInsertId()
    {
        return $this->_connection->insert_id;
    }

    /**
     * returns the current connection
     * @return \mysqli
     */
    public function connection()
    {
        return $this->_connection;
    }

    /**
     * debug the given query
     * @param bool $params
     * @return string|array
     */
    public function debugRenderedQuery($params = false)
    {
        if($params) {
            return array(
                'query'     => $this->_renderedQuery,
                'params'    => $this->_queryParams
            );
        }
        return $this->_renderedQuery;
    }

    /**
     * connect to the mysql database
     * @param $host
     * @param $user
     * @param $password
     * @param $database
     * @param $port
     * @throws \ErrorException
     */
    private function _connect($host, $user, $password, $database, $port)
    {
        $this->_connection   = new \mysqli(
            $host,
            $user,
            $password,
            $database,
            $port
        );
        if($this->_connection->connect_errno > 0) {
            throw new \ErrorException("database connection failed!");
        }

        mysqli_set_charset($this->_connection, 'utf8');

    }

    /**
     * exceptions for MySQL::real_escape_string
     * for example the NULL value
     * @param $string
     * @return bool
     */
    private function _real_escape_exception($string)
    {
        if($string == 'NULL') {
            return true;
        }
        return false;
    }

    /**
     * destroys the current mysql connection
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * close the mysql connection
     */
    public function close()
    {
        if($this->_connection && @$this->_connection->ping()) {
            $this->_connection->close();
        }
    }

    /**
     * enanbles the debug mode:
     * HEADUP! The debug mode is different on each function!
     */
    public function debug()
    {
        $this->_debug   = true;
    }

    /**
     * @return array|object|\stdClass
     */
    public function fetchOneOrEmptyObject()
    {
        if($this->count()) {
            return $this->fetchOne();
        } else {
            $obj    = new \stdClass();
            foreach ($this->_result->fetch_fields() as $field) {
                $obj->{$field->name}  = null;
            }
            return $obj;
        }
    }

    /**
     * @param $array
     * @return string
     */
    public function getUniqueKey($array)
    {
        $key = 'UK'.substr(md5(time().rand().'2893DDD'),0,6);
        if(array_key_exists($key, $array)) return $this->getUniqueKey($array);
        return $key;
    }
}