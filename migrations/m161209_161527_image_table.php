<?php

use yii\db\Migration;

class m161209_161527_image_table extends Migration
{
    public function up()
    {
        $strOptions = null;
        if ($this->db->driverName === 'mysql') {
            $strOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';
        }
        $this->createTable('{{%image}}', [
            'id' => $this->primaryKey()->unsigned(),
            'filename' => $this->string(255)->notNull(),
            'path' => $this->string(255)->notNull(),
            'mime_type' => $this->string(32)->notNull(),
            'height' => $this->smallInteger(4)->notNull(),
            'width' => $this->smallInteger(4)->notNull(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $strOptions);
    }

    public function down()
    {
        $this->dropTable('{{%image}}');
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}
