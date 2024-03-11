<?php
namespace Manomite\Engine;

require_once __DIR__."/../../autoload.php";
class Pdf
{
    protected $mpdf;
    public function __construct(array $orientation = array())
    {
        //custom orientation : array('mode' => 'utf-8', 'format' => [200, 250], 'orientation' => 'L',)
        $this->mpdf = new \Mpdf\Mpdf(
			array_merge($orientation, [
			'mode' => 'utf-8',
			$orientation,
			'margin_left' => 10,
			'margin_right' => 10,
			'margin_top' => 10,
			'margin_bottom' => 15,
			'margin_header' => 5,
			'margin_footer' => 5
		]));
    }
    public function generate_pdf($content = null, $title = 'PDF File', $file = 'file', $saveAsFile = false, $water = 'asset/theme/image/logo/logo.png')
    {
        $this->mpdf->SetTitle($title);
        $this->mpdf->SetAuthor(APP_NAME);
        $this->mpdf->SetWatermarkImage($water, 1, '', array(160,10));
        $this->mpdf->showWatermarkImage = true;
        $this->mpdf->SetWatermarkImage($water, 0.15, 'F');
        $this->mpdf->watermarkTextAlpha = 0.1;
        //$this->mpdf->SetWatermarkText($title);
        $this->mpdf->showWatermarkText = false;
        $this->mpdf->watermark_font = '';
        $this->mpdf->watermarkTextAlpha = 0.1;
        $this->mpdf->SetDisplayMode('fullpage');
        $this->mpdf->list_indent_first_level = 0; // 1 or 0 - whether to indent the first level of a list
        // Load a stylesheet
        $stylesheet = file_get_contents(__DIR__ . '/../../asset/script/css/mpdfstyletables.css');
        $this->mpdf->WriteHTML($stylesheet, 1); // The parameter 1 tells that this is css/style only and no body/html/text
        $chunks = explode("<!-- chunk -->", $content);
        foreach($chunks as $key => $val) {
            $this->mpdf->WriteHTML($val);
        }
        
        if ($saveAsFile) {
            $this->mpdf->Output($file.'.pdf', \Mpdf\Output\Destination::FILE);
        } else {
            $this->mpdf->Output($file.'.pdf', "I", \Mpdf\Output\Destination::INLINE);
        }
    }
    public function image2pdf($image, $save)
    {
        $size =  getimagesize($image);
        $width = $size[0];
        $height = $size[1];
        $this->mpdf->Image($image, 60, 50, $width, $height, 'jpg', true, true);
        $this->mpdf->Output($save);
    }

    public function addWatermarksToExistingPDF($file, $image, $store){
        
        $pdf = new \setasign\Fpdi\Fpdi(); 
        if(file_exists($file)){ 
            $pagecount = $pdf->setSourceFile($file); 
        } else { 
            throw new Exception('Error: PDF File not found') ;
        } 
        
        for($i=1;$i<=$pagecount;$i++){ 
            $tpl = $pdf->importPage($i); 
            $size = $pdf->getTemplateSize($tpl); 
            $pdf->addPage(); 
            $pdf->useTemplate($tpl, 1, 1, $size['width'], $size['height'], TRUE); 
            
            //Put the watermark 
            $xxx_final = ($size['width']-200); 
            $yyy_final = ($size['height']-15); 
            $pdf->Image($image, $xxx_final, $yyy_final, 20, 0, 'png'); 
        } 
        // Output PDF with watermark 
        $pdf->Output('F', $store.'.pdf');
    }

    public function addText($file, $text, $store){
        $name = uniqid();
        $font_size = 5;
        $opacity = 100;
        $ts = explode("\n", $text);
        $width = 0;
        foreach ($ts as $k=>$string) {
            $width = max($width, strlen($string));
        }
        $width  = imagefontwidth($font_size)*$width;
        $height = imagefontheight($font_size)*count($ts);
        $el = imagefontheight($font_size);
        $em = imagefontwidth($font_size);
        $img = imagecreatetruecolor($width, $height);
    
        // Background color
        $bg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
    
        // Font color settings
        $color = imagecolorallocate($img, 0, 0, 0);
        foreach ($ts as $k=>$string) {
            $len = strlen($string);
            $ypos = 0;
            for ($i=0;$i<$len;$i++) {
                $xpos = $i * $em;
                $ypos = $k * $el;
                imagechar($img, $font_size, $xpos, $ypos, $string, $color);
                $string = substr($string, 1);
            }
        }
        imagecolortransparent($img, $bg);
        $blank = imagecreatetruecolor($width, $height);
        $tbg = imagecolorallocate($blank, 255, 255, 255);
        imagefilledrectangle($blank, 0, 0, $width, $height, $tbg);
        imagecolortransparent($blank, $tbg);
        $op = !empty($opacity)?$opacity:100;
        if (($op < 0) or ($op >100)) {
            $op = 100;
        }
    
        // Create watermark image
        imagecopymerge($blank, $img, 0, 0, 0, 0, $width, $height, $op);
        imagepng($blank, __DIR__.'/'.$name.".png");
    
        // Set source PDF file
        $pdf = new \setasign\Fpdi\Fpdi(); 
        if(file_exists($file)){ 
            $pagecount = $pdf->setSourceFile($file); 
        } else { 
            throw new Exception('Error: PDF File not found') ;
        } 
   
        // Add watermark to PDF pages
        for ($i=1;$i<=$pagecount;$i++) {

            $tpl = $pdf->importPage($i); 
            $size = $pdf->getTemplateSize($tpl); 
            $pdf->addPage(); 
            $pdf->useTemplate($tpl, 1, 1, $size['width'], $size['height'], TRUE); 
            
            //Put the watermark 
            $xxx_final = ($size['width']-90); 
            $yyy_final = ($size['height']-15); 
            $pdf->Image(__DIR__.'/'.$name.".png", $xxx_final, $yyy_final, 50, 0, 'png'); 
        }
        @unlink(__DIR__.'/'.$name.".png");
    
        // Output PDF with watermark
        $pdf->Output('F', $store.'.pdf');
    }
}