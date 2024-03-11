<?php
namespace Manomite\Nerves;
use \Manomite\{
    Engine\CacheAdapter,
    Exception\ManomiteException as ex
};

include __DIR__ . '/../../autoload.php';

class FileGrabber
{

    public function getImage($file, $type, $isScript = false, $user = null, $default = null){
        $dir = SYSTEM_DIR;
        if(!is_dir($dir)){
            mkdir($dir, 0600, true);
        }
        $adapter = new CacheAdapter();
        $key = strtolower(preg_replace('/[^a-zA-Z0-9-_\.]/s', '', strip_tags(str_replace(array('/', '//', '\'', '\\', chr(0), ' ', '.', '-', '_'), '', $file.$type.$isScript.$user.'images'))));
        $cache = $adapter->getCache($key);
        if($cache !== null){
            return json_decode($cache, true);
        }
        $image = false;
        if (!empty($file)) {
            $file = basename($file);
            $im = $dir.'/'.$type.'/'.$file;
            if (file_exists($im)) {
                $image = $dir.'/'.$type.'/'.$file;
                $extension = pathinfo($im, PATHINFO_EXTENSION);
                if ($isScript) {
                    $blob = @file_get_contents($im);
                    $blob = base64_encode($blob);
                    $image = 'data:image/'.$extension.';base64,'.$blob;
                }
                $adapter->cache(json_encode($image), $key, 86400);
                return $image;
            }
        }
        if($default !== null){
            $blob = @file_get_contents($default);
            $extension = pathinfo($default, PATHINFO_EXTENSION);
            $blob = base64_encode($blob);
            $image = 'data:image/'.$extension.';base64,'.$blob;
            return $image;
        }
        return null;
    }

    private function getFileFromRemote($file){
        //future update implementation
       
    }

    private function getLastPathSegment($url) {
        $path = parse_url($url, PHP_URL_PATH); // to get the path from a whole URL
        $pathTrimmed = trim($path, '/'); // normalise with no leading or trailing slash
        $pathTokens = explode('/', $pathTrimmed); // get segments delimited by a slash
    
        if (substr($path, -1) !== '/') {
            array_pop($pathTokens);
        }
        return end($pathTokens); // get the last segment
    }
}