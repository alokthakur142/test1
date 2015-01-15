<?php

namespace backend\models;

use Yii;

/**
 * This is the model class for table "tag_assignment".
 *
 * @property integer $id
 * @property integer $tag_id
 * @property integer $user_id
 * @property integer $process_stage_from
 * @property integer $process_stage_to
 * @property integer $mandatory
 * @property integer $status
 * @property string $created_on
 */
class TagAssignment extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tag_assignment';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['tag_id', 'user_id', 'process_stage_from', 'process_stage_to', 'mandatory', 'status'], 'required'],
            [['tag_id', 'user_id', 'process_stage_from', 'process_stage_to', 'mandatory', 'status'], 'integer'],
            [['created_on'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tag_id' => 'Tag ID',
            'user_id' => 'User ID',
            'process_stage_from' => 'From Process',
            'process_stage_to' => 'To Process',
            'mandatory' => 'Mandatory',
            'status' => 'Status',
            'created_on' => 'Created On',
        ];
    }
    
    public function actDelete() {
        $this->status = 2;
        return $this->save();
    }
}
