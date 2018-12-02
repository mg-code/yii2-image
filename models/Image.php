<?php

namespace mgcode\image\models;

use mgcode\helpers\ActiveRecordHelperTrait;

/**
 * This is the model class for table "image".
 * @property string $fullPath
 */
class Image extends AbstractImage
{
    use ActiveRecordHelperTrait;

    /**
     * Builds srcset from array of sizes
     * @param array $sizes E.g. ['1600w' => ImageType::EXTRA_LARGE, '1200w' => ImageType::LARGE]
     * @return string
     */
    public function buildSrcset($sizes = [])
    {
        $result = [];
        foreach ($sizes as $size => $params) {
            $result[] = $this->getUrl($params).' '.$size;
        }
        return implode(', ', $result);
    }

    /**
     * Returns public url of image
     * @param array $params
     * @return string
     */
    public function getUrl($params)
    {
        return \Yii::$app->image->getImageUrl($this->getFullPath(), $params);
    }

    /**
     * Returns full path of image
     * @return string
     */
    public function getFullPath()
    {
        return $this->path.'/'.$this->filename;
    }
}
