<?php

namespace mgcode\image\models\queries;

/**
 * This is the ActiveQuery class for [[\mgcode\image\models\Image]].
 *
 * @see \mgcode\image\models\Image
 */
class ImageQuery extends \yii\db\ActiveQuery
{

    /**
     * @inheritdoc
     * @return \mgcode\image\models\Image[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return \mgcode\image\models\Image|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
