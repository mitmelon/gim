<?php
namespace Manomite\Engine;
use \Symfony\Component\Filesystem\Filesystem;

require_once __DIR__."/../../autoload.php";
class File
{
    protected $adapter;
    protected $dir;
    
    public function __construct(string $dir = '')
    {
        $this->adapter = new Filesystem();
        $this->dir     = $dir;
    }
    public function mkdir(string $dir, int $mode = 0777)
    {
        return $this->adapter->mkdir($dir, $mode, true);
    }
    //use copy for single files
    public function copy($fromFile, $toFile)
    {
        return $this->adapter->copy($toFile, $fromFile, true);
    }
    //Recursive copy for all files
    public function recursiveCopy(string $source)
    {
        if (!file_exists($this->dir)) {
            $this->mkdir($this->dir);
        }
        $splFileInfoArr = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($splFileInfoArr as $fullPath => $splFileinfo) {
            //skip . ..
            if (in_array($splFileinfo->getBasename(), [".", ".."])) {
                continue;
            }
            //get relative path of source file or folder
            $path = str_replace($source, "", $splFileinfo->getPathname());
            if ($splFileinfo->isDir()) {
                $this->mkdir($this->dir . "/" . $path);
            } else {
                $this->copy($this->dir . "/" . $path, $fullPath);
            }
        }
    }
    public function chmod(string $file, int $mode, bool $modeAll = false)
    {
        return $this->adapter->chmod($file, $mode, $modeAll);
    }
    public function rename(string $from, string $to)
    {
        return $this->adapter->rename($from, $to);
    }
    //use mirror for folders
    public function mirror(string $folderFrom, string $folderTo)
    {
        return $this->adapter->mirror($folderFrom, $folderTo);
    }
    //create a temporary file
    public function temp(string $file)
    {
        return $this->adapter->tempnam($this->dir, $file);
    }
    //Dump content into a file
    public function dump(string $file, mixed $content)
    {
        return $this->adapter->dumpFile($file, $content);
    }
    public function remove(array $file)
    {
        return $this->adapter->remove($file);
    }
 
    public function fileStats(string $file)
    {
        if (file_exists($file)) {
            return array(
            'size'      => filesize($file),
            'owner'     => fileowner($file),
            'group'     => filegroup($file),
            'accessed'  => fileatime($file),
            'modified'  => filemtime($file),
            'permission'=> fileperms($file),
            'type'      => pathinfo($file, PATHINFO_EXTENSION)
        );
        }else{
            throw new \Exception('No file Exist');
        }
    }

    public function getMimeType( string $filename ) {
        $realpath = realpath( $filename );
        if ( $realpath
                && function_exists( 'finfo_file' )
                && function_exists( 'finfo_open' )
                && defined( 'FILEINFO_MIME_TYPE' )
        ) {
                // Use the Fileinfo PECL extension (PHP 5.3+)
                return finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $realpath );
        }
        if ( function_exists( 'mime_content_type' ) ) {
                // Deprecated in PHP 5.3
                return mime_content_type( $realpath );
        }
        return false;
    }

    public function readBigFile(string $path) {
        $handle = fopen($path, "r");
    
        while(!feof($handle)) {
            yield trim(fgets($handle));
        }
        fclose($handle);
    }

    public function deleteFilesThenSelf(string $folder) {
        foreach(new \DirectoryIterator($folder) as $f) {
            if($f->isDot()) continue; // skip . and ..
            if ($f->isFile()) {
                unlink($f->getPathname());
            } else if($f->isDir()) {
                $this->deleteFilesThenSelf($f->getPathname());
            }
        }
        rmdir($folder);
    }

    public function find(string $dir, string $targetFile)
    {
        $filePath = null;

        // pass function ref &$search so we can make recursive call
        // pass &$filePath ref so we can get rid of it as class param
        $search = function ($dir, $targetFile) use (&$search, &$filePath) {
            if (null !== $filePath) return; // early termination
            $files = scandir($dir);
            foreach ($files as $key => $file) {
                if ($file == "." || $file == "..") continue;
                $path = realpath($dir . DIRECTORY_SEPARATOR . $file);
                if (is_file($path)) {
                    if ($file === $targetFile) {
                        $filePath = $path;
                        break;
                    }
                } else {
                    $search($path, $targetFile);
                }
            }
        };

        $search($dir, $targetFile); // invoke the function

        return $filePath;
    }

    public function scanner(string $dir)
    {
        $files = scandir($dir);
        $allFiles = array();
        foreach ($files as $key => $file) {
            if ($file == "." || $file == ".."){
                continue;
            }
            $path = realpath($dir . DIRECTORY_SEPARATOR . $file);
            if (is_file($path)) {
                $allFiles[] = $path;
            }
        }
        return $allFiles;
    }

    public function readLine($file){
        $handle = fopen($file, "r");
        while (!feof($handle)) {
            yield trim(fgets($handle));
        }
        fclose($handle); 
    }

    public function downloadFile($srcName, $dstName, $chunkSize = 1, $returnbytes = true) {
        $chunksize = $chunkSize * (1024 * 1024);
        $data = '';
        $bytesCount = 0;
        $handle = fopen($srcName, 'rb');
        $fp = fopen($dstName, 'w');
        if ($handle === false) {
          return false;
        }
        while (!feof($handle)) {
          $data = fread($handle, $chunksize);
          fwrite($fp, $data, strlen($data));
          if ($returnbytes) {
              $bytesCount += strlen($data);
          }
        }
        $status = fclose($handle);
        fclose($fp);
        if ($returnbytes && $status) {
          return $bytesCount; // Return number of bytes delivered like readfile() does.
        }
        return $status;
      }

    public function get_file_size ($file) {
        $fp = @fopen($file, "r");
        @fseek($fp,0,SEEK_END);
        $filesize = @ftell($fp);
        fclose($fp);
        return $filesize;
     }

     public function pipe($source, $to){
        $handle1 = fopen($source, "r");
        $handle2 = fopen($to, "w");
        stream_copy_to_stream($handle1, $handle2);
        fclose($handle1);
        fclose($handle2);
     }

     public function file_get_content( $url,  $javascript_loop = 0, $timeout = 5 ) {
      $url = str_replace( "&amp;", "&", urldecode(trim($url)) );
      if(@file_exists($url)){
        return array(file_get_contents($url));
      }
      $handle = @fopen($url, 'r');
      if (!$handle) {
          return false;
      }
  
      $ch = curl_init();
      curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
      curl_setopt( $ch, CURLOPT_URL, $url );
      curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
      curl_setopt( $ch, CURLOPT_ENCODING, "" );
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
      curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );    # required for https urls
      curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
      curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
      curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
      $content = curl_exec( $ch );
      $response = curl_getinfo( $ch );
      curl_close ( $ch );
  
      if ($response['http_code'] === 301 || $response['http_code'] === 302) {
          ini_set("user_agent", "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
  
          if ( $headers = get_headers($response['url']) ) {
              foreach( $headers as $value ) {
                  if ( substr( strtolower($value), 0, 9 ) == "location:" )
                      return $this->file_get_content( trim( substr( $value, 9, strlen($value) ) ) );
              }
          }
      }
  
      if (    ( preg_match("/>[[:space:]]+window\.location\.replace\('(.*)'\)/i", $content, $value) || preg_match("/>[[:space:]]+window\.location\=\"(.*)\"/i", $content, $value) ) && $javascript_loop < 5) {
          return $this->file_get_content( $value[1], $javascript_loop+1 );
      } else {
          return array( $content, $response );
      }
  }
}