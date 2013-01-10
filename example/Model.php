<?php

class Model {
    const PDO_EXPIRES=10;
    private $pdo=NULL;
    private $absoute_expires=0;
    public function say_hello($str, $encode = FALSE) {
        return $encode ? md5 ( $str ) : $str;
    }

    public function get_from_database($sql) {
        $pdo=$this->load_pdo();
        $stmt=$pdo->prepare($sql);
        $stmt->execute();
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    private function load_pdo(){
        if(NULL===$this->pdo||$this->absoute_expires<time()){
            $this->pdo=$this->get_pdo();
            $this->absoute_expires=time()+self::PDO_EXPIRES;
        }
        return $this->pdo;
    }

    protected function get_pdo() {
        $dsn = 'mysql:dbname=blog;host=192.168.187.16;port=3306';
        $username = 'zhxia';
        $password = 'admin';
        $options = array (PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'' );
        try {
            $pdo = new PDO ( $dsn, $username, $password, $options );
        } catch ( Exception $ex ) {
            die ( 'Connection failed:' . $ex->getMessage () );
        }
        return $pdo;
    }
}

?>