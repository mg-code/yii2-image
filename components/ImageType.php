<?php

namespace mgcode\image\components;

class ImageType extends \yii\base\Object
{
    // Medium size
    const TYPE_MEDIUM = 1;
    const TYPE_SMALL = 2;
    const TYPE_LARGE = 3;

    // Parameters
    const PARAM_WIDTH = 'width';
    const PARAM_HEIGHT = 'height';
    const PARAM_JPEG_QUALITY = 'jpeg_quality';
    const PARAM_RATIO = 'ratio';
    const PARAM_BLUR = 'blur';

    const RATIO_MIN = 'min'; // any of sides is not larger than specified
    const RATIO_MAX = 'max'; // any of sides is smaller larger than specified (Images are not zoomed in)

    /** @var array Default parameters */
    public $defaultParamOptions = [
        self::PARAM_JPEG_QUALITY => 80,
    ];

    public $originalParams = [
        self::PARAM_WIDTH => 3840,
        self::PARAM_HEIGHT => 2160,
        self::PARAM_RATIO => self::RATIO_MIN,
    ];

    /** @var array Image types */
    protected $params = [
        self::TYPE_LARGE => [
            self::PARAM_WIDTH => 1200,
            self::PARAM_HEIGHT => 800,
            self::PARAM_RATIO => self::RATIO_MIN,
        ],
        self::TYPE_MEDIUM => [
            self::PARAM_WIDTH => 600,
            self::PARAM_HEIGHT => 400,
            self::PARAM_RATIO => self::RATIO_MAX,
        ],
        self::TYPE_SMALL => [
            self::PARAM_WIDTH => 300,
            self::PARAM_HEIGHT => 200,
            self::PARAM_RATIO => self::RATIO_MAX,
        ],
    ];

    private $_typeHashes = [];

    /**
     * Returns list of types
     * @return array
     * @throws \Exception
     */
    public function getTypes()
    {
        $result = [];
        foreach (array_keys($this->params) as $type) {
            $result[$type] = $this->getParams($type);
        }
        return $result;
    }

    /**
     * Returns type parameters
     * @param $type
     * @return array
     * @throws \Exception
     */
    public function getParams($type)
    {
        if (!$this->exist($type)) {
            throw new \Exception('Image type does not exists');
        }
        return array_merge($this->defaultParamOptions, $this->params[$type]);
    }

    /**
     * Returns original image parameters
     * @return array
     */
    public function getOriginalParams()
    {
        return array_merge($this->defaultParamOptions, $this->originalParams);
    }

    /**
     * Returns whether type exists
     * @param $type
     * @return bool
     */
    public function exist($type)
    {
        return array_key_exists($type, $this->params);
    }

    /**
     * Returns type hash
     * @param $type
     * @return mixed
     * @throws \Exception
     */
    public function getTypeHash($type)
    {
        if (!array_key_exists($type, $this->_typeHashes)) {
            $this->_typeHashes[$type] = md5(print_r($this->getParams($type), true));
        }
        return $this->_typeHashes[$type];
    }
}