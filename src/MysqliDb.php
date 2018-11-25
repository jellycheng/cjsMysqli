<?php
namespace CjsMysqli;

use mysqli;

include_once __DIR__ . '/func.php';

class MysqliDb
{
    protected $dbConfig = [
                        'host'=>'localhost',
                        'port'=>'3306',
                        'username'=>'root',
                        'passwd'=>'',
                        'dbname'=>'',
                        'socket'=>'',
                        'charset'=>'utf8',
                        ];
    protected $options = [];

    protected $mysqliLink;
    protected $error = [];
    protected $lastError = [
                            'iserror'=> false,
                            'type'=>'',
                            'errno'=>'',
                            'errmsg'=>'',
                            ];
    protected $isTransaction = false;

    public function __construct()
    {

    }


    public function getDbConfig($key = null)
    {
        if(is_null($key)) {
            return $this->dbConfig;
        }
        return isset($this->dbConfig[$key])?$this->dbConfig[$key]:'';
    }


    public function setDbConfig($dbConfig = [])
    {
        $this->dbConfig = array_merge($this->dbConfig, $dbConfig);
        return $this;
    }

    public function setDbConfig4Key($key, $val)
    {
        $this->dbConfig[$key] = $val;
        return $this;
    }

    protected function setError($type, $errno, $errmsg = '') {
        $this->lastError['iserror'] = true;
        $this->lastError['type'] = $type;
        $this->lastError['errno'] = $errno;
        $this->lastError['errmsg'] = $errmsg;
        $this->error[] = [
                            'type'=>$type,
                            'errno'=>$errno,
                            'errmsg'=>$errmsg,
                        ];
    }

    public function getError() {
        return $this->error;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getMysqliLink($isAutoConnect = true)
    {
        if($isAutoConnect) {
            if($this->mysqliLink) {
                return $this->mysqliLink;
            } else {
                $this->connect();
                //var_export($this->getError());exit;
                return $this->mysqliLink;
            }
        }
        return $this->mysqliLink;
    }


    protected function setMysqliLink($mysqliLink)
    {
        $this->mysqliLink = $mysqliLink;
    }

    //1. 连接
    public function connect() {
        $link = @new mysqli($this->getDbConfig('host'),
                                        $this->getDbConfig('username'),
                                        $this->getDbConfig('passwd'),
                                        $this->getDbConfig('dbname'),
                                   $this->getDbConfig('port')?:3306,
                                        $this->getDbConfig('socket')
                                        );
        if($link->connect_error) {
            $this->setError('connect', $link->connect_errno, $link->connect_error);
            return false;
        }

        $link->set_charset($this->getDbConfig('charset')?:"utf8");

        $this->setMysqliLink($link);
        return true;
    }


    // 关闭
    public function close() {
        if($this->getMysqliLink(false)) {
            $this->getMysqliLink(false)->close();
            $this->setMysqliLink(null);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    //切换db
    public function select_db($dbName) {
        return $this->getMysqliLink()->select_db($dbName);
    }


    //查询 - 非预执行sql直接查询, return false,mysqli_result对象等值
    public function query($sql, $resultmode = MYSQLI_STORE_RESULT) {
        $res = $this->getMysqliLink()->query($sql, $resultmode);
        if($res === false) {
            $this->setError('query', $$this->getMysqliLink(false)->errno, $this->getMysqliLink(false)->error);
        }
        return $res;
    }

    //增 insert
    public function insert($sql, $isReturnLastId = true, $resultmode = MYSQLI_STORE_RESULT) {
        $result = $this->getMysqliLink()->query($sql, $resultmode); //返回 true， false
        if(!$result) {
            $this->setError('insert', $this->getMysqliLink(false)->errno, $this->getMysqliLink(false)->error);
        }
        if($result && $isReturnLastId) {
            $result = $this->getMysqliLink(false)->insert_id;
        }
        return $result;
    }

    //删 delete
    public function delete($sql, $resultmode = MYSQLI_STORE_RESULT) {
        $result = $this->getMysqliLink()->query($sql, $resultmode); //返回 true， false
        if(!$result) {
            $this->setError('delete', $this->getMysqliLink(false)->errno, $this->getMysqliLink(false)->error);
        } else {
            $result = $this->getMysqliLink(false)->affected_rows;
        }
        return $result;
    }

    //改 update
    public function update($sql, $resultmode = MYSQLI_STORE_RESULT) {
        $result = $this->getMysqliLink()->query($sql, $resultmode); //返回 true， false
        if(!$result) {
            $this->setError('update', $this->getMysqliLink(false)->errno, $this->getMysqliLink(false)->error);
        } else {
            $result = $this->getMysqliLink(false)->affected_rows;
        }
        return $result;
    }

    //查 查询一条记录,无记录返回null or []
    public function findOne($sql, $resultmode = MYSQLI_STORE_RESULT) {
        $resObj = $this->getMysqliLink()->query($sql, $resultmode);
        if($resObj===false) {
            $this->setError('findOne', $this->getMysqliLink(false)->errno, $this->getMysqliLink(false)->error);
            return [];
        }
        $result = $resObj->fetch_assoc();
        return $result;
    }

    //查 查询多天记录
    public function findList($sql, $resultmode = MYSQLI_STORE_RESULT) {
        $resObj = $this->getMysqliLink()->query($sql, $resultmode);
        if($resObj===false) {
            $this->setError('findList', $this->getMysqliLink(false)->errno, $this->getMysqliLink(false)->error);
            return [];
        }
        $result = $resObj->fetch_all(MYSQLI_ASSOC);
        return $result;
    }

    //统计数量，count
    public function count($sql, $resultmode = MYSQLI_STORE_RESULT) {
        $resObj = $this->getMysqliLink()->query($sql, $resultmode);
        $result = $resObj->fetch_row();
        return $result[0];
    }

    //开启事务
    public function begin_transaction($flags = 0, $name = null) {
        if(!$this->isTransaction) {
            $this->getMysqliLink()->autocommit(false);//关闭自动提交
            $this->getMysqliLink()->begin_transaction($flags, $name);//对应sql：START TRANSACTION
            $this->isTransaction = true;
            return true;
        } else {
            $this->setError('transaction_begin', 1, "已经开启过事务，重复调用");
            return false;
        }
    }

    //提交事务
    public function commit($flags = null, $name = null){
        if($this->isTransaction) {
            $cRes = $this->getMysqliLink()->commit($flags, $name);
            $this->getMysqliLink()->autocommit(true);
            $this->isTransaction = false;
            if(!$cRes) {
                //提交失败，抛异常，告诉可以回滚
                throw new \Exception("db commit fail", $this->getMysqliLink(false)->errno);
            }
        } else {
            $this->setError('transaction_commit', 1, "非正常提交事务");
        }
    }

    //事务回滚
    public function rollback() {
        if($this->isTransaction) {
            $this->getMysqliLink()->rollback();
            $this->getMysqliLink()->autocommit(true);
            $this->isTransaction = false;
        } else {
            $this->setError('transaction_rollback', 1, "非正常事务回滚");
        }
    }


    //闭包写法，事务处理
    public function transaction($callback, $ext = []) {
        try{
            //$callback && $callback();
        }catch (\Exception $e) {
            //回滚

        }
    }


}