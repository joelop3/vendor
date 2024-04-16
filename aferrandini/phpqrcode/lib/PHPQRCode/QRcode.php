<?php
/**
 * QRcode.php
 *
 * Created by arielferrandini
 */

namespace PHPQRCode;

use Exception;
use Grafika\Grafika;

class QRcode {

    public $version;
    public $width;
    public $data;

    //----------------------------------------------------------------------
    public function encodeMask(QRinput $input, $mask)
    {
        if($input->getVersion() < 0 || $input->getVersion() > Constants::QRSPEC_VERSION_MAX) {
            throw new Exception('wrong version');
        }
        if($input->getErrorCorrectionLevel() > Constants::QR_ECLEVEL_H) {
            throw new Exception('wrong level');
        }

        $raw = new QRrawcode($input);

        QRtools::markTime('after_raw');

        $version = $raw->version;
        $width = QRspec::getWidth($version);
        $frame = QRspec::newFrame($version);

        $filler = new FrameFiller($width, $frame);
        if(is_null($filler)) {
            return NULL;
        }

        // inteleaved data and ecc codes
        for($i=0; $i<$raw->dataLength + $raw->eccLength; $i++) {
            $code = $raw->getCode();
            $bit = 0x80;
            for($j=0; $j<8; $j++) {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }

        QRtools::markTime('after_filler');

        unset($raw);

        // remainder bits
        $j = QRspec::getRemainder($version);
        for($i=0; $i<$j; $i++) {
            $addr = $filler->next();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);


        // masking
        $maskObj = new QRmask();
        if($mask < 0) {

            if (Constants::QR_FIND_BEST_MASK) {
                $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
            } else {
                $masked = $maskObj->makeMask($width, $frame, (intval(Constants::QR_DEFAULT_MASK) % 8), $input->getErrorCorrectionLevel());
            }
        } else {
            $masked = $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
        }

        if($masked == NULL) {
            return NULL;
        }

        QRtools::markTime('after_mask');

        $this->version = $version;
        $this->width = $width;
        $this->data = $masked;

        return $this;
    }

    //----------------------------------------------------------------------
    public function encodeInput(QRinput $input)
    {
        return $this->encodeMask($input, -1);
    }

    //----------------------------------------------------------------------
    public function encodeString8bit($string, $version, $level)
    {
        if(string == NULL) {
            throw new Exception('empty string!');
            return NULL;
        }

        $input = new QRinput($version, $level);
        if($input == NULL) return NULL;

        $ret = $input->append($input, Constants::QR_MODE_8, strlen($string), str_split($string));
        if($ret < 0) {
            unset($input);
            return NULL;
        }
        return $this->encodeInput($input);
    }

    //----------------------------------------------------------------------
    public function encodeString($string, $version, $level, $hint, $casesensitive)
    {

        if($hint != Constants::QR_MODE_8 && $hint != Constants::QR_MODE_KANJI) {
            throw new Exception('bad hint');
            return NULL;
        }

        $input = new QRinput($version, $level);
        if($input == NULL) return NULL;

        $ret = QRsplit::splitStringToQRinput($string, $input, $hint, $casesensitive);
        if($ret < 0) {
            return NULL;
        }

        return $this->encodeInput($input);
    }

    //----------------------------------------------------------------------
    public static function png($text, $outfile = false, $level = Constants::QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false, $back_color = 0xFFFFFF, $fore_color = 0x000000) 
    {
        $enc = QRencode::factory($level, $size, $margin, $back_color, $fore_color);
        return $enc->encodePNG($text, $outfile, $saveandprint=false);
    }

    //----------------------------------------------------------------------
    public static function text($text, $outfile = false, $level = Constants::QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = QRencode::factory($level, $size, $margin);
        return $enc->encode($text, $outfile);
    }

    //----------------------------------------------------------------------
    public static function raw($text, $outfile = false, $level = Constants::QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = QRencode::factory($level, $size, $margin);
        return $enc->encodeRAW($text, $outfile);
    }

    public static function pngLogo($text, $outfile, $cod_cuestonario)
    {
        $value = $text;//QR code content
        $errorCorrectionLevel = 'H';  //Fault tolerance level
        $matrixPointSize = 6;      //Generate picture size

        //Generate QR code picture
        QRcode::png($value, $outfile, $errorCorrectionLevel, $matrixPointSize, 2);
        // $logo = 'uploads/catas/codigosQR/logos/sensesbit.png'; //logo picture ready
        $logo = '';
        $fondo = 'uploads/catas/codigosQR/logos/fondo.png'; //logo picture ready
        if (file_exists($logo)) {
            $QR = imagecreatefromstring(file_get_contents($outfile));    //Target image connection resource.
            $logo = imagecreatefromstring(file_get_contents($logo));  //Source image connection resource.
            $QR_width = imagesx($QR);      //QR code picture width
            $QR_height = imagesy($QR);     //QR code image height
            $logo_width = imagesx($logo);    //logo picture width
            $logo_height = imagesy($logo);   //logo image height
            $logo_qr_width = $QR_width / 3;   //Width of logo after combination (1 / 5 of QR code)
            $scale = $logo_width/$logo_qr_width;  //Width scaling ratio of logo (own width / combined width)
            $logo_qr_height = $logo_height/$scale; //Height of logo after combination
            $from_width = ($QR_width - $logo_qr_width) / 2;  //Coordinate point of upper left corner of logo after combination
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,$logo_qr_height, $logo_width, $logo_height);
            imagepng($QR, $outfile);
            imagedestroy($QR);
        }
        
        $editor = Grafika::createEditor(); // Instanciar
        $editor->open($image1 , $fondo ); //Marco del QR
        $editor->open( $image2 , $outfile); //Qr generado
        $editor-> blend ($image1, $image2, 'normal', 1, 'center', 0,-20); // La posición debe ajustarse según su propio proyecto
        $editor->save($image1,$outfile);
        $editor->open($image3 , $outfile);
        $editor-> text ($image3, $cod_cuestonario, 15, 24, 373, new \ Grafika \ Color ("#FFFFFF")); // Agregar marca de agua
        $editor->save($image3, $outfile);
        //unlink ($outfile); // Destruye la imagen
        //unlink ($qr_tmp_path2); // Destruye la imagen
    }
}

