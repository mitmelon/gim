<?php

namespace Manomite\Database;

include __DIR__ . '/../../autoload.php';

class DB
{
    public $conn;
    private $path;
    public function __construct($dbpath, $useEncryption = false)
    {
        $path = SYSTEM_DIR.'/db/'.$dbpath;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $dbpath = $path;
        $backup = $path.'/backup';
        $keyPath = $path.'/dbkeys';
        if($useEncryption){
            $useEncryption = array('key_storage_path' => $keyPath,  'key_name' => basename($dbpath));
        }

        $this->conn = new \Filebase\Database([
            'dir' => $dbpath,
            'backupLocation' => $backup,
            'cache'          => true,
            'safe_filename'  => true,
            'encryption' => $useEncryption
        ]);
        $this->path = $path;
    }

    public function createDocument($docName, array $data)
    {
        $docs = $this->conn->get($docName);
        $docs->save($data);
        return $docs;
    }

    public function updateDocument($docName, array $data)
    {
        $docs = $this->conn->get($docName);
        foreach ($data as $key => $value) {
            if(isset($docs->$key)){
                $docs->$key = $value;
            } else {
                $docs->__set($key, $value);
            }
        }
        $docs->save();
    }

    public function deleteData($docName, array $data)
    {
        $docs = $this->conn->get($docName);
        foreach ($data as $key => $value) {
            unset($docs->$key);
        }
        $docs->save();
    }

    public function deleteDocument($docName)
    {
        $docs = $this->conn->get($docName);
        $docs->delete();
    }

    public function truncate()
    {
        $this->conn->truncate();
        $this->conn->flush(true);
        $this->conn->flushCache();
        $this->removeJunks();
    }


    public function find($docName)
    { 
        try{
            $docs = $this->conn->get($docName);
            return $this->conn->toArray($docs);
        } catch(\Throwable $e){
            throw new \Exception($e->getMessage());
        }
    }

    public function count_document()
    {
        return $this->conn->count();
    }

    public function findAllInDb()
    {
        return $this->conn->findAll();
    }

    public function dropAllTable()
    {
        return $this->conn->flush(true);
    }

    public function backup()
    {
        $this->conn->backup()->create();
    }

    public function deleteBackup()
    {
        $this->conn->backup()->clean();
    }

    public function getBackup()
    {
        return $this->conn->backup()->find();
    }

    public function restoreBackup()
    {
        return $this->conn->backup()->rollback();
    }

    public function has($db)
    {
        return $this->conn->has($db);
    }

    public function removeJunks($path = SYSTEM_DIR.'/db'){

        if(substr($path, -1)!= DIRECTORY_SEPARATOR){
          $path .= DIRECTORY_SEPARATOR;
        }
        $d2 = array('.','..');
        $dirs = array_diff(glob($path.'*', GLOB_ONLYDIR),$d2);
        foreach($dirs as $d){
            $this->removeJunks($d);
        }
      
        if(count(array_diff(glob($path.'*'),$d2))===0){
          rmdir($path);
        }
    }
    
    public function delete_files($target, $path = SYSTEM_DIR.'/db') {
        if(!empty($path)){
            $target = $path.'/'.$target;
        }
        foreach(glob($target . '/*') as $file) {
            if(is_dir($file)){
                $this->delete_files($file, '');
            }
            else{
                unlink($file);
            }
        }
    }
	
}
