<?php
namespace mgcode\image\components;

use Imagine\Image\ImageInterface as ImagineInterface;
use mgcode\helpers\NumberHelper;
use mgcode\helpers\StringHelper;
use mgcode\helpers\TimeHelper;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\Object;
use yii\di\Instance;
use yii\helpers\FileHelper;
use yii\httpclient\Client;
use mgcode\image\models\Image;
use yii\web\UploadedFile;

class ImageComponent extends Object
{
    /** @var string Place where all uploaded images will be stored */
    public $uploadPath;

    /** @var string Place where to put resized images */
    public $resizedPath;

    /** @var string Upload path prefix */
    public $pathPrefix;

    /** @var array List of supported mime types */
    public $validMimeTypes = [
        'image/png',
        'image/gif',
        'image/jpeg',
        'image/bmp',
        'image/x-ms-bmp',
    ];

    /** @var ImageType Image types available for resize */
    public $types = 'mgcode\image\components\ImageType';

    /** @var string $hash Hash salt for directories of resized images */
    public $hashSalt;

    /** @var string Action ID which performs resize of image */
    public $urlRoute = 'image/resize';

    /** @var string Url prefix */
    public $urlPrefix = 'resized';

    /** @var \Imagine\Imagick\Imagine */
    protected $_imagine;

    /** @inheritdoc */
    public function init()
    {
        parent::init();
        if ($this->uploadPath === null) {
            throw new InvalidConfigException('Please set `uploadPath` to path where you want to store images.');
        }

        // Normalize paths
        $this->pathPrefix = trim($this->pathPrefix, '/');
        $this->uploadPath = rtrim($this->uploadPath, '/');
        $this->types = Instance::ensure($this->types, ImageType::className());

        // Initialize hash salt
        if ($this->hashSalt === null) {
            $this->hashSalt = gethostname();
        }

        // Initializes resized images path
        if ($this->resizedPath === null) {
            $this->resizedPath = '@webroot/'.$this->urlPrefix;
        }
    }

    /**
     * Returns url for resized image
     * @param int $type
     * @param string $path
     * @param string $fileName
     * @return string
     */
    public function getResizedUrl($type, $path, $fileName)
    {
        $hash = $this->getUrlHash($type, $path, $fileName);
        $parts = [
            $this->urlPrefix,
            $type,
            $path,
            $hash,
            $fileName
        ];

        $url = '/'.implode('/', $parts);
        return $url;
    }

    /**
     * Generates hash for resized image
     * @param int $type
     * @param string $path
     * @param string $fileName
     * @return string
     */
    public function getUrlHash($type, $path, $fileName)
    {
        $parts = [$type, $path, $fileName, $this->hashSalt];
        $hash = md5(print_r($parts, true));
        $hash = substr($hash, 0, 5);
        return $hash;
    }

    /**
     * Returns resized image destination
     * @param int $type
     * @param string $path
     * @param string $fileName
     * @return string
     */
    public function getResizeDestination($type, $path, $fileName)
    {
        $directory = \Yii::getAlias($this->resizedPath);
        $hash = $this->getUrlHash($type, $path, $fileName);
        $path = implode('/', [
            $directory,
            $type,
            $path,
            $hash,
            $fileName
        ]);
        return $path;
    }

    /**
     * Resize image to specific type
     * @param int $type
     * @param string $path
     * @param string $fileName
     * @return bool
     * @throws \Exception
     */
    public function resizeImage($type, $path, $fileName)
    {
        $originalFile = $this->getOriginalFile($path, $fileName)['fullPath'];
        $destination = $this->getResizeDestination($type, $path, $fileName);
        $parameters = $this->types->getParams($type);
        return $this->resizeImageByParameters($originalFile, $destination, $parameters);
    }

    /**
     * Saves image from UploadedFile instance
     * @param UploadedFile $instance
     * @return Image
     * @throws \Exception
     */
    public function saveFromInstance(UploadedFile $instance)
    {
        // Validate mime
        $mimeType = FileHelper::getMimeType($instance->tempName);
        if (!$mimeType || !static::isMimeTypeValid($mimeType)) {
            throw new \Exception('Wrong mime type. Given: '.$mimeType);
        }

        // Move to temp destination
        $tempDestination = $this->getTempLocation();
        $save = $instance->saveAs($tempDestination);
        if (!$save) {
            throw new \Exception('Failed to save image. Destination:'.$tempDestination);
        }

        return $this->saveImage($tempDestination, $mimeType);
    }

    /**
     * Saves image from local file
     * @param $fileName
     * @return Image
     * @throws \Exception
     */
    public function saveFromFile($fileName)
    {
        if (!file_exists($fileName)) {
            throw new InvalidParamException('file not exists: '.$fileName);
        }

        $data = file_get_contents($fileName);
        $base64 = base64_encode($data);

        return $this->saveFromBase64($base64);
    }

    /**
     * Saves image from url
     * @param $url
     * @return Image
     * @throws \Exception
     */
    public function saveFromUrl($url)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('get')
            ->setUrl($url)
            ->send();

        if (!$response->isOk) {
            throw new InvalidParamException('Failed to load image contents. Code: '.$response->getStatusCode().'. Url: '.$url);
        }
        $base64 = base64_encode($response->getContent());

        return $this->saveFromBase64($base64);
    }

    /**
     * Saves image from base64 string
     * @param $data
     * @return Image
     * @throws \Exception
     */
    public function saveFromBase64($data)
    {
        $f = finfo_open();
        $mimeType = finfo_buffer($f, base64_decode($data), FILEINFO_MIME_TYPE);
        if (!$mimeType || !$this->isMimeTypeValid($mimeType)) {
            throw new \Exception('Wrong mime type. Given: '.$mimeType);
        }

        $tempLocation = $this->getTempLocation();
        $fh = fopen($tempLocation, 'w');
        stream_filter_append($fh, 'convert.base64-decode', STREAM_FILTER_WRITE);
        $size = fwrite($fh, $data);
        fclose($fh);

        if ($size === false) {
            throw new \Exception('Failed to write image. Path:'.$tempLocation);
        }

        return $this->saveImage($tempLocation, $mimeType);
    }

    /**
     * Saves image from temp location
     * @param string $tempLocation Temp image location. This file will be moved to correct location.
     * @return Image Image object
     * @throws \Exception
     */
    protected function saveImage($tempLocation, $mimeType)
    {
        $this->resizeOriginal($tempLocation);
        $size = $this->getImageSize($tempLocation);

        // Found extension by mime type
        $extension = $this->getExtensionByMimeType($mimeType);

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $image = new Image();
            $image->mime_type = $mimeType;

            // Temporary variables
            $image->path = $image->filename = 'temp';
            $image->height = $size['height'];
            $image->width = $size['width'];

            $image->saveOrFail(false);

            $image->path = $this->getBasePath($image->id);
            $image->filename = $image->id.'.'.$extension;
            $image->saveOrFail();

            $path = $this->getOriginalFile($image->path, $image->filename);

            // Create directory, directory always will not exist, because we use ID for subdirectories
            FileHelper::createDirectory($path['directory'], 0777);

            // Move file
            rename($tempLocation, $path['fullPath']);

            $transaction->commit();
            return $image;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Resizes original image
     * @param $source
     * @return bool
     * @throws \Exception
     */
    protected function resizeOriginal($source)
    {
        // Parameters
        $parameters = $this->types->getOriginalParams();
        return $this->resizeImageByParameters($source, $source, $parameters);
    }

    /**
     * Resizes image with parameters
     * @param $source
     * @param $destination
     * @param $parameters
     * @return bool
     * @throws InvalidConfigException
     * @throws \Exception
     */
    protected function resizeImageByParameters($source, $destination, $parameters)
    {
        if (!$source || !file_exists($source)) {
            throw new \Exception('Source image not found.');
        }

        /** @var \Imagine\Imagick\Image $imagine */
        $imagine = $this->getImagine()->open($source);

        // Additional options
        $options = [];
        if (isset($parameters[ImageType::PARAM_JPEG_QUALITY])) {
            $options['jpeg_quality'] = $parameters[ImageType::PARAM_JPEG_QUALITY];
        }

        // Animated gifs
        if ($imagine->layers()->count() > 1) {
            $options['animated'] = true;
            $imagine->layers()->coalesce();
            foreach ($imagine->layers() as $frame) {
                $frame->interlace(ImagineInterface::INTERLACE_LINE);
                $this->resizeLayer($frame, $parameters);
            }
        } // Standard image
        else {
            $imagine->interlace(ImagineInterface::INTERLACE_LINE);
            $this->resizeLayer($imagine, $parameters);
        }

        $directory = dirname($destination);
        if (!file_exists($directory)) {
            FileHelper::createDirectory($directory);
        }

        // Save image
        $imagine->save($destination, $options);
        return true;
    }

    /**
     * Resize image layer
     * @param \Imagine\Imagick\Image $image
     * @param $parameters
     * @return \Imagine\Image\BoxInterface
     */
    protected function resizeLayer(\Imagine\Imagick\Image $image, $parameters)
    {
        $size = new \Imagine\Image\Box($parameters[ImageType::PARAM_WIDTH], $parameters[ImageType::PARAM_HEIGHT]);
        $imageSize = $image->getSize();

        // Calculate ratio
        $ratios = [
            $size->getWidth() / $imageSize->getWidth(),
            $size->getHeight() / $imageSize->getHeight()
        ];

        if (isset($parameters[ImageType::PARAM_RATIO]) && $parameters[ImageType::PARAM_RATIO] == ImageType::RATIO_MIN) {
            $ratio = min($ratios);
        } else {
            $ratio = max($ratios);
        }

        // No zoom in
        if ($ratio >= 1 || $size->contains($imageSize)) {
            return $image;
        }

        $box = $imageSize->scale($ratio);
        return $image->resize($box);
    }

    /**
     * Returns extension by mime type
     * @param $mimeType
     * @return string
     */
    public function getExtensionByMimeType($mimeType)
    {
        // Exceptions
        if (in_array($mimeType, ['image/x-ms-bmp'])) {
            return 'bmp';
        }

        $extensions = FileHelper::getExtensionsByMimeType($mimeType);
        $extension = end($extensions); // jpg extension is after jpeg and jpe.
        return $extension;
    }

    /**
     * Checks if mime type is valid
     * @param $mimeType
     * @return bool
     */
    public function isMimeTypeValid($mimeType)
    {
        return in_array($mimeType, $this->validMimeTypes);
    }

    /**
     * Returns the `Imagine` object that supports various image manipulations.
     * @return \Imagine\Imagick\Imagine the `Imagine` object
     * @throws InvalidConfigException
     */
    protected function getImagine()
    {
        if ($this->_imagine === null) {
            if (!class_exists('Imagick', false)) {
                throw new InvalidConfigException('Your system does not support `Imagick` driver.');
            }
            $this->_imagine = new \Imagine\Imagick\Imagine();
        }
        return $this->_imagine;
    }

    /**
     * Returns directory and full path of original file
     * @param $path
     * @param $fileName
     * @return array
     */
    public function getOriginalFile($path, $fileName)
    {
        $directory = Yii::getAlias($this->uploadPath.'/'.$path);
        $fullPath = $directory.'/'.$fileName;

        return [
            'directory' => $directory,
            'fullPath' => $fullPath,
        ];
    }

    /**
     * Returns temp location path
     * @return string
     */
    protected function getTempLocation()
    {
        $dir = Yii::getAlias('@runtime/temp-image-upload');
        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir, 0777);
        }
        $tempName = time().'-'.StringHelper::generateRandomString(12, false);
        return $dir.'/'.$tempName;
    }

    /**
     * Returns base path for image
     * @param $id
     * @return string
     */
    protected function getBasePath($id)
    {
        $parentFolder = TimeHelper::getNumericMonth();

        $leadingZeros = NumberHelper::leadingZeros($id, 6);
        $folder1 = substr($leadingZeros, 0, 3);
        $folder2 = substr($leadingZeros, 3);
        $path = implode('/', [$this->pathPrefix, $parentFolder, $folder1, $folder2]);
        $path = trim($path, '/');

        return $path;
    }

    /**
     * This is native php function wrapper, native function raises error.
     * @param $fileName
     * @param null $imageInfo
     * @return array
     * @throws \yii\base\Exception
     */
    public function getImageSize($fileName, &$imageInfo = null)
    {
        $data = @getimagesize($fileName, $imageInfo);
        if ($data === false) {
            throw new \yii\base\Exception('Failed to get image size.');
        }
        return [
            'width' => (int) $data[0],
            'height' => (int) $data[1],
            'mimeType' => $data['mime'],
        ];
    }
}