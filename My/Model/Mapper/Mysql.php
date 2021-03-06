<?php

namespace Bgw\Core;


class My_Model_Mapper_Mysql extends My_Model_Mapper
{

    /**
     * id of a client database
     * 
     * @var int
     */
    protected $clientId = null;

    /**
     * table name mapper is mapping to
     * 
     * @var string
     */
    protected $_tablename = null;

    /**
     * table's primary key field name
     * 
     * @var string
     */
    protected $_primary_key_field = 'id';

    /**
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_connection;

    /**
     *
     * @var Zend_Db_Select
     * @var unknown_type
     */
    protected $_select = null;

    public function __construct($table = '')
    {
        $this->_tablename = $table;
        parent::__construct();
    }

    /**
     *
     * @return Zend_Db_Adapter_Abstract
     */
    protected function _db($config)
    {
        if (empty($config)) {
            throw new Exception('no mysql db config');
        }
        
        $dbFactory = new My_Model_Factory_Db_Mysql();
        
        return $dbFactory->setHost($config->host)
            ->setPort($config->port)
            ->setUsername($config->username)
            ->setPassword($config->password)
            ->setDbname($config->dbname)
            ->getConnection();
    }

    /**
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function _dbGeneral()
    {
        return $this->_db($this->_dbConfiguration->mysql->general);
    }

    /**
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function _dbClient()
    {
        return $this->_db($this->_dbConfiguration->mysql->client);
    }

    public function _dbClientRequest()
    {
        return $this->_db($this->_dbConfiguration->mysql->recoveryRequest->client);
    }

    /**
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function _dbClientData($clientId)
    {
        if (empty($clientId)) {
            throw new Exception('no client id');
        }
        
        $client = 'client' . $clientId;
        
        return $this->_db($this->_dbConfiguration->mysql->clientData->$client);
    }

    /**
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function _dbClientPd($clientId)
    {
        if (empty($clientId)) {
            throw new Exception('no client id');
        }
        
        $client = 'client' . $clientId;
        
        return $this->_db($this->_dbConfiguration->mysql->clientPd->$client);
    }

    public function getIdentity()
    {
        return new My_Model_Mapper_Mysql_IdentityObject();
    }

    public function getNumRows()
    {
        if (! $this->_select)
            return 0;
        $paginator = Zend_Paginator::factory($this->_select, 'DbSelect');
        return $paginator->getTotalItemCount();
    }

    public function getLastSelect()
    {
        return $this->_select;
    }

    public function insert($obj)
    {
        if ($obj instanceof My_Model_Domain) {
            if ($obj->getClientIdUnsetFromData()) {
                $obj->unsetField($obj->getClientIdKey());
            }
            $data = $obj->getData();
        } elseif (is_array($obj)) {
            $data = $obj;
        } else {
            throw new Exception("Unsupported datatype used in insert", - 1001);
        }
        
        return $this->_connection->insert($this->_tablename, $data);
    }
    
    public function insertOrUpdate($obj){
        if ($obj instanceof My_Model_Domain) {
            if ($obj->getClientIdUnsetFromData()) {
                $obj->unsetField($obj->getClientIdKey());
            }
            $data = $obj->getData();
        } elseif (is_array($obj)) {
            $data = $obj;
        } else {
            throw new Exception("Unsupported datatype used in insert", - 1001);
        }
    
    
        // extract and quote col names from the array keys
        $cols = array();
        $vals = array();
        $i = 0;
        foreach ($data as $col => $val) {
            $cols[] = $this->_connection->quoteIdentifier($col, true);
            if ($val instanceof Zend_Db_Expr) {
                $vals[] = $val->__toString();
                unset($data[$col]);
            } else {
                if ($this->_connection->supportsParameters('positional')) {
                    $vals[] = '?';
                } else {
                    if ($this->_connection->supportsParameters('named')) {
                        unset($data[$col]);
                        $data[':col'.$i] = $val;
                        $vals[] = ':col'.$i;
                        $i++;
                    } else {
                        /** @see Zend_Db_Adapter_Exception */
                        require_once 'Zend/Db/Adapter/Exception.php';
                        throw new Zend_Db_Adapter_Exception(get_class($this->_connection) ." doesn't support positional or named binding");
                    }
                }
            }
        }
    
        // build the statement
        $sql = "INSERT INTO "
            . $this->_connection->quoteIdentifier($this->_tablename, true)
            . ' (' . implode(', ', $cols) . ') '
                . 'VALUES (' . implode(', ', $vals) . ')';

                $duplicate=" ON DUPLICATE KEY UPDATE ";
                    foreach ($cols as $index => $col) {
                       $duplicate .=  $col . " = " .$vals[$index] ."," ;
                    }
                    $duplicate = rtrim($duplicate, ",");
                    $sql.=$duplicate;
                    // execute the statement and return the number of affected rows
                    if ($this->_connection->supportsParameters('positional')) {
                        $data = array_values($data);
                    }
                    //because we have two 
                    $data = array_merge($data, $data);
                    $stmt = $this->_connection->query($sql, $data);
                    $result = $stmt->rowCount();
                    return $result;
    
    }
    
    public function lastInsertId()
    {
        return $this->_connection->lastInsertId();
    }

    public function startTransaction()
    {
        return $this->_connection->beginTransaction();
    }

    public function select(My_Model_Mapper_IdentityObject $identity)
    {
        $select = $this->_connection->select();
        $select->from($this->_tablename);
        $select->where($this->_getSelection()
            ->where($identity))
            ->limit($this->_getSelection()
            ->limit($identity), $this->_getSelection()
            ->offset($identity))
            ->order($this->_getSelection()
            ->orderBy($identity));
        $this->_select = $select;
        // echo $this->_select, "\n\n";
        // die;
        return $this;
    }

    /**
     *
     * @param My_Model_Domain|array $obj            
     * @throws Exception
     * @return number
     */
    public function update($obj)
    {
        if ($obj instanceof My_Model_Domain) {
            if ($obj->getClientIdUnsetFromData()) {
                $obj->unsetField($obj->getClientIdKey());
            }
            $bind = $obj->getData();
        } elseif (is_array($obj)) {
            $bind = $obj;
        } else {
            throw new Exception("Unsupported datatype used in insert/update", - 1001);
        }
        
        $db = $this->_connection;
        
        return $this->_connection->update($this->_tablename, $bind, $db->quoteInto("$this->_primary_key_field = (?)", $obj->{"get" . $this->_primary_key_field}()));
    }

    public function updateAll(My_Model_Mapper_IdentityObject $identity, array $data)
    {
        if (empty($data)) {
            throw new Exception("Data can not be empty");
        }
        return $this->_connection->update($this->_tablename, $data, $this->_getSelection()
            ->where($identity));
    }

    public function query($sql, $bind = array())
    {
        $this->_connection->query($sql, $bind);
    }

    public function rollback()
    {
        $this->_connection->rollback();
    }

    public function commit()
    {
        $this->_connection->commit();
    }

    public function delete($obj)
    {
        $db = $this->_connection;
        return $this->_connection->delete($this->_tablename, $db->quoteInto("$this->_primary_key_field = (?)", $obj->{"get" . $this->_primary_key_field}()));
    }

    public function deleteAll(My_Model_Mapper_IdentityObject $identity)
    {
        return $this->_connection->delete($this->_tablename, $this->_getSelection()
            ->where($identity));
    }

    /**
     *
     * @return My_Model_Mapper_Mysql_SelectionFactory
     */
    protected function _getSelection()
    {
        return new My_Model_Mapper_Mysql_SelectionFactory();
    }

    protected function _selectAll(My_Model_Mapper_IdentityObject $identity)
    {
        $this->select($identity);
        $data = $this->_connection->fetchAll($this->_select);
        return $data;
    }

    protected function _selectOne(My_Model_Mapper_IdentityObject $identity)
    {
        $this->select($identity);
        $data = $this->_connection->fetchRow($this->_select);
        return $data;
    }
}
