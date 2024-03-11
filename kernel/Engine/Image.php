<?php
namespace Manomite\Engine;

use \SapientPro\ImageComparator\ImageComparator;

require_once __DIR__ . "/../../autoload.php";
class Image
{
    private $mimetype;
    private $name;
    private $file;
    private $tmp;
    private $type;

    public function __construct($file = null)
    {
        if (is_array($file)) {
            $this->tmp = $file['tmp_name'];
            $this->name = $file['name'];
            $this->name = $this->clean_file_name();
            $this->type = $file['type'];
            $this->file = $file;
        }
    }

    public function getMime()
    {
        if (!isset($this->mimetype)) {
            $finfo = new \finfo(FILEINFO_MIME);
            $mimetype = $finfo->file($this->tmp);
            $mimetypeParts = preg_split('/\s*[;,]\s*/', $mimetype);
            $this->mimetype = strtolower($mimetypeParts[0]);
            unset($finfo);
        }
        return $this->mimetype;
    }

    public function getName()
    {
        if (!isset($this->name)) {
            $this->name = pathinfo($this->file, PATHINFO_FILENAME);
        }

        return $this->name;
    }

    public function getNameWithExtension()
    {
        return sprintf('%s.%s', $this->getName(), $this->getExtension());
    }

    public function getExtension()
    {
        if (!isset($this->extension)) {
            $this->extension = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
        }

        return $this->extension;
    }

    public function getMd5()
    {
        return md5_file($this->file);
    }

    public function getDimensions()
    {
        list($width, $height) = getimagesize($this->file);
        return array(
            'width' => $width,
            'height' => $height
        );
    }

    public function moveUploadedFile($source, $destination)
    {
        return move_uploaded_file($source, $destination);
    }

    public function transparent_background($img, $destination, $color)
    {
        $colors = explode(',', $color);
        $remove = imagecolorallocate($img, $colors[0], $colors[1], $colors[2]);
        imagecolortransparent($img, $remove);
        imagesavealpha($img, true);
        $_transColor = imagecolorallocatealpha($img, 0, 0, 0, 127);
        magefill($img, 0, 0, $_transColor);
        imagepng($img, $destination);
    }

    public function remove_transparency($pic, $destination, $color = "255, 255, 255")
    {
        list($red, $green, $blue) = explode(',', $color);
        $w = imagesx($pic);
        $h = imagesy($pic);

        $buffer = imagecreatetruecolor($w, $h);
        imagefilledrectangle(
            $buffer,
            0,
            0,
            $w,
            $h,
            imagecolorallocate($buffer, $red, $green, $blue)
        );
        imagecopy($buffer, $pic, 0, 0, 0, 0, $w, $h);
        imagepng($buffer, $destination, 0);
    }

    private function getcolors($color)
    {
        $color = str_replace('#', '', $color);
        if (strlen($color) == 3)
            $color = str_repeat(substr($color, 0, 1), 2) . str_repeat(substr($color, 1, 1), 2) . str_repeat(substr($color, 2, 1), 2);
        $r = sscanf($color, "%2x%2x%2x");
        $red = (is_array($r) && array_key_exists(0, $r) && is_numeric($r[0]) ? $r[0] : 0);
        $green = (is_array($r) && array_key_exists(1, $r) && is_numeric($r[1]) ? $r[1] : 0);
        $blue = (is_array($r) && array_key_exists(2, $r) && is_numeric($r[2]) ? $r[2] : 0);
        return array($red, $green, $blue);
    }

    public function clean_file_name()
    {
        return (new \Manomite\Protect\PostFilter)->sanitize_file_name($this->name);
    }

    public function singleSimilarImage($image1, $image2)
    {
        $imageComparator = new ImageComparator();
        return $imageComparator->compare($image1, $image2);
    }

    public function multipleSimilarImage($image1, $imagePath)
    {

        if (is_dir($imagePath)) {
            $imageComparator = new ImageComparator();
            $filers = array_diff(scandir($imagePath), array('.', '..'));
            $images = array();
            foreach ($filers as $file) {
                $filePath = $imagePath . '/' . $file;
                if (is_file($filePath)) {
                    $images[$file] = $filePath;
                }
            }
            if (count($images) > 0 and !empty($images)) {
                $compare = $imageComparator->compareArray(
                    $image1,
                    $images
                );
                $rotate = $imageComparator->detectArray(
                    $image1,
                    $images
                );
                return array(
                    'compare' => $compare,
                    'rotate' => $rotate
                );
            }
        }
        return false;
    }

}
