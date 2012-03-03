<?php
 
/**
* Used to generate 3d captchas
* @author Alex@phpguide.co.il
* @version 1
* @example

	$c = new captcha3D();
	$c->draw(); // or $c->draw('file.png');

*/
class captcha3D
{
    protected $_text;
    protected $_conf;
    private $image = null;
    private $textCanvas;

    /**
     * @param string $text text to write
     * @param int $width resulting image's width
     * @param int $height  resulting image's height
     * @param int $fontSize in points (30 pt.)
     * @param array $rgbColor array(255, 255, 255) for white
     * @param string $fontFile name of the file used as font
     */
    public function __construct($text, $width = 400, $height = 200, $fontSize = 25, $fontFile = 'Arial.ttf', array $rgbColor = array(0, 228, 0), array $rgbBackground = array(255, 255, 255) )
    {
        $this->_text = $text;
        $this->_conf = array('width' => $width, 'height' => $height, 'fontSize' => $fontSize, 'color' => $rgbColor, 'font' => $fontFile, 'background' => $rgbBackground);

        if(!is_file($fontFile) || !is_readable($fontFile))
            throw new InvalidArgumentException("The specified font file could not be found");

        $this->image = $this->generateCaptchaImage();
    }

    /**
     * Either outputs or saves the image to a png file specified
     * @param $filename save path or null
     */
    public function draw($filename = null)
    {
        header("Content-type: image/png");
        imagepng($this->getImage(),$filename);
    }

    protected  function getImage()
    {
        if(null === $this->image)
            $this->GenerateCaptchaImage();

        return $this->image;
    }


    protected function GenerateCaptchaImage()
    {
        $textHeight = 29.0/255.0/(mt_rand(5000,20000)/1000);
        $zxzRotationMatrix = self::getZXZRotationMatrix ( pi()/(mt_rand(500,1600)/100) , pi()/(mt_rand(280,320)/100), 0.1);

        $this->GenerateTextCanvas();
        $this->CreateCaptchaCanvas();

        $this->CopyTextCanvasIntoCaptcha($textHeight, $zxzRotationMatrix);
    }


    protected function GenerateTextCanvas()
    {
        $this->textCanvas = imagecreatetruecolor(100, 40);
        $bgColor = $this->AllocateColorByRgbArray($this->textCanvas, $this->_conf['background']);
        imagettftext($this->textCanvas, $this->_conf['fontSize'], 0, 10, 30, $bgColor, $this->_conf['font'], $this->_text);
    }

    protected function AllocateColorByRgbArray($image, array $rgb)
    {
        if(3 !== sizeof($rgb))
            throw new InvalidArgumentException('Rgb with colors should contain array of 3 elements, each is a number between 0 and 255 (r, g, b)');

        array_unshift($rgb, $image);
        return call_user_func_array('imageColorAllocate', $rgb);
    }


    protected function CreateCaptchaCanvas()
    {
        // Create the image
        $this->image = imagecreatetruecolor($this->_conf['width'], $this->_conf['height']);
        $white = imagecolorallocate($this->image, 255, 255, 255);
        imagefilledrectangle  ( $this->image  ,0  , 0  , $this->_conf['width']  , $this->_conf['height']  , $white );
    }


    protected function CopyTextCanvasIntoCaptcha($text3dHeight, $rotationMatrix)
    {
    	$color = $this->AllocateColorByRgbArray($this->image, $this->_conf['color']);
    	
    	$textCanvasHeight = imagesy($this->textCanvas);
    	$textCanvasWidth = imagesx($this->textCanvas);
    	
    	for($y = 0; $y < $textCanvasHeight; $y++)
    		for($x = 0; $x < $textCanvasWidth; $x++)
    		{
    			$pixel = imagecolorat($this->textCanvas, $x, $y);   
                $pixelColor = (($pixel >> 16) & 0xFF) + (($pixel >> 8) & 0xFF) + ($pixel & 0xFF);
                
                // calculate new (stertched) values
                $newX = ($x/$textCanvasWidth - 0.5)*$this->_conf['width'];
                $newY = ($y/$textCanvasHeight - 0.5)*$this->_conf['height'];
                $newZ = $pixelColor * $text3dHeight;
                
    			$grid[$x][$y] = array($newX, $newY, $newZ);
		    	$grid[$x][$y] = self::MultiplyMatrices($grid[$x][$y], $rotationMatrix);
                
                // fix  position
		    	$grid[$x][$y][0] += $this->_conf['width']/2;
		    	$grid[$x][$y][1] += $this->_conf['height']/2;
                
                
                // draw vertical line
                if( $y > 0)
                    imageline($this->image,$grid[$x][$y-1][0], $grid[$x][$y-1][1], $grid[$x][$y][0], $grid[$x][$y][1],$color);
                    
                // draw horizontal lines
                if ($x > 0)
                    imageline($this->image,$grid[$x-1][$y][0], $grid[$x-1][$y][1], $grid[$x][$y][0], $grid[$x][$y][1],$color);
	    	}
    	

    }



    /**
     * Returns zxz rotation matrix for the specified angles in radians
     * Any x,y,z rotation could be expressed as z rotation, then x rotation and then z rotation again
     * @see http://en.wikipedia.org/wiki/Euler_angles
     * @static
     * @param $_1 Z rotation angle in radians
     * @param $_2 X rotation angle in radians
     * @param $_3 Z rotation angle in radians
     * @return array
     */
    private static function getZXZRotationMatrix($_1,$_2,$_3)
    {
        $zxz = Array();

        $c1 = cos($_1); $s1 = sin($_1);
        $c2 = cos($_2); $s2 = sin($_2);
        $c3 = cos($_3); $s3 = sin($_3);

        $zxz[0] = Array($c1*$c3-$c2*$s1*$s3 , -$c3*$s1-$c1*$c2*$s3 , $s2*$s3);
        $zxz[1] = Array($c2*$c3*$s1 + $c1*$s3 , $c1*$c2*$c3 - $s1*$s3, -$c3*$s2);
        $zxz[2] = Array($s1*$s2,$c1*$s2,$c2);

        return $zxz;
    }

    /**
     * Multiplies one 3x3 matrix by another 3x3 matrix
     * @static
     * @param array $v1 matrix 1
     * @param array $m2 matrix 2
     * @return array
     */
    private static function MultiplyMatrices(array $v1, array $m2)
    {
        try
        {
            $multi = Array();
            $multi[0] = $v1[0] * $m2[0][0] + $v1[1] * $m2[0][1] + $v1[2] * $m2[0][2] ;
            $multi[1] = $v1[0] * $m2[1][0] + $v1[1] * $m2[1][1] + $v1[2] * $m2[1][2] ;
            $multi[2] = $v1[0] * $m2[2][0] + $v1[1] * $m2[2][1] + $v1[2] * $m2[2][2] ;
            return $multi;
        }
        catch(Exception $e)
        {
            throw new InvalidArgumentException('Matrices provided for multiplication are not size of 3');
        }
    }


    public function __destruct()
    {
        if(null !== $this->image)
            imagedestroy($this->image);
    }
}



