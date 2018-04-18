<?php
/**
 */
class responseLink extends CActiveRecord
{
    public static function model($className=__CLASS__) {
        return parent::model($className);
    }
    /** @inheritdoc */
    public function tableName()
    {
        return '{{reloadanyresponse_responseLink}}';
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return array('sid', 'srid');
    }
}
