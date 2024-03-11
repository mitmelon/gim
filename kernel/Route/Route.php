<?php
namespace Manomite\Route;
use Manomite\Exception\ManomiteException as ex;
use Manomite\Template\Template;
use Manomite\Template\Text;
use Manomite\Protect\PostFilter;
use \Manomite\Engine\CacheAdapter;
use Manomite\Protect\Secret;


include __DIR__ . '/../../autoload.php';

class Route 
{
    protected $cache;
    public function __construct()
    {
        $this->cache = new CacheAdapter();
    }

    public function display($header, $footer, $template, array $content, $cache = false)
    {
        $security = new PostFilter;
        $sec = new Secret();
        $nonce = $sec->randomKey;
        $content = array_merge($content, array('nonce' => $nonce));
        $sec->request_generator();
       // header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-".$nonce."'; img-src 'self' data:;font-src 'self' data:;style-src 'self' 'nonce-".$nonce."'", FALSE);
        if ($cache !== false) {
           
            if ($this->cache->getCache($template) !== null) {
                return $this->cache->getCache($template);
            } else {
                $header = new Template($header);
                $footer = new Template($footer);
                $load = new Template($template);
                
                if (is_array($content)) {
                    foreach ($content as $key => $value) {
                        $header->set($key, $value);
                        $load->set($key, $value);
                        $footer->set($key, $value);
                    }
                    $output = $header->output().$load->output().$footer->output();
                    return $this->cache->cache($output, $template, 3600);
                } else {
                    //error
                    new ex("displayRouteError", 3, "Template content must be an array");
                }
            }
        } else {
            $header = new Template($header);
            $footer = new Template($footer);
            $load = new Template($template);
            if (is_array($content)) {
                foreach ($content as $key => $value) {
                    $header->set($key, $value);
                    $load->set($key, $value);
                    $footer->set($key, $value);
                }
                $output = $header->output().$load->output().$footer->output();
                return $output;
            } else {
                //error
                new ex("displayRouteError", 3, "Template content must be an array");
            }
        }
    }
                    
    public function single($template, array $content)
    {
        $security = new PostFilter;
        $sec = new Secret();
        $nonce = $sec->randomKey;
        $content = array_merge($content, array('nonce' => $nonce));
        $sec->request_generator();
        //header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-".$nonce."'; img-src 'self' data:;font-src 'self' data:;style-src 'self' 'nonce-".$nonce."'", FALSE);
        $load = new Template($template);
        if (is_array($content)) {
            foreach ($content as $key => $value) {
                $load->set($key, $value);
            }
            return $load->output();
        } else {
            new ex("singleRouteError", 3, "Template content must be an array");
        }
    }
    public static function menu($template, array $content)
    {
        $load = new Template($template);
        
        if (is_array($content)) {
            foreach ($content as $key => $value) {
                $load->set($key, $value);
            }
            return $load->output();
        } else {
            new ex("menuRouteError", 3, "Menu content must be an array");
        }
    }
    public function textRender($template, array $content, $cache = false)
    {
        $load = new Text($template);
       
        if (is_array($content)) {
            foreach ($content as $key => $value) {
                $load->set($key, $value);
            }
            $output = $load->output();
            if ($cache !== false) {
                $c = $this->cache->getCache($cache);
                if ($c === null) {
                    return $this->cache->cache($output, $cache, 3600);
                } else {
                    return $c;
                }
            }
            return $output;
        } else {
            new ex("textRenderRouteError", 3, "Text content must be an array");
        }
    }
}
