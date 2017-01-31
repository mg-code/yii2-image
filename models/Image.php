<?php

namespace mgcode\image\models;

use mgcode\helpers\ActiveRecordHelperTrait;

/**
 * This is the model class for table "image".
 */
class Image extends AbstractImage
{
    use ActiveRecordHelperTrait;

    /**
     * Returns resized image url
     * @param $type
     */
    public function getUrl($type)
    {
        return \Yii::$app->image->getResizedUrl($type, $this->path, $this->filename);
    }
}
