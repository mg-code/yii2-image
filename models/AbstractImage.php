<?php

namespace mgcode\image\models;

use Yii;

/**
 * This is the model class for table "image".
 *
 * @property integer $id
 * @property string $filename
 * @property string $path
 * @property string $mime_type
 * @property integer $height
 * @property integer $width
 * @property string $created_at
 */
abstract class AbstractImage extends \yii\db\ActiveRecord
{
    /** @inheritdoc */
    public static function tableName()
    {
        return 'image';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['filename', 'path', 'mime_type', 'height', 'width'], 'required'],
            [['height', 'width'], 'integer'],
            [['created_at'], 'safe'],
            [['filename', 'path'], 'string', 'max' => 255],
            [['mime_type'], 'string', 'max' => 32],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('mgcode.image', 'ID'),
            'filename' => Yii::t('mgcode.image', 'Filename'),
            'path' => Yii::t('mgcode.image', 'Path'),
            'mime_type' => Yii::t('mgcode.image', 'Mime Type'),
            'height' => Yii::t('mgcode.image', 'Height'),
            'width' => Yii::t('mgcode.image', 'Width'),
            'created_at' => Yii::t('mgcode.image', 'Created At'),
        ];
    }

    /**
     * @inheritdoc
     * @return \mgcode\image\models\queries\ImageQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \mgcode\image\models\queries\ImageQuery(get_called_class());
    }
}
