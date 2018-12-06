<?php
/**
 * This file is part of reloadAnyResponse plugin
 * @version 1.0.2
 */
namespace reloadAnyResponse\models;
use Yii;
use CActiveRecord;
class responseLink extends CActiveRecord
{
    /**
     * Class surveyChaining\models\chainingResponseLink
     *
     * @property integer $sid survey
     * @property integer $srid : response id
     * @property string $token : optionnal token
     * @property string $accesscode : the access code
    */

    /** @inheritdoc */
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

    /**
     * Return (or create) self
     * @param integer $sid survey
     * @param integer $srid : response id
     * @param string $token : optionnal token, if set : always reset
     * @return self|null
     */
    public static function setResponseLink($sid,$srid,$token = null)
    {
        $oResponseLink = self::model()->findByPk(array('sid'=>$sid,'srid'=>$srid));
        if($oResponseLink && $token) {
            $oResponseLink->token = $token;
            $oResponseLink->save();
        }
        if(!$oResponseLink) {
            $oResponseLink = new self;
            $oResponseLink->sid = $sid;
            $oResponseLink->srid = $srid;
            $oResponseLink->token = $token;
            $oResponseLink->accesscode = Yii::app()->securityManager->generateRandomString(42);
            if(!$oResponseLink->save()) {
                $oResponseLink->accesscode = null;// Then if have error : we don't return an access code
            }
        }
        return $oResponseLink;
    }

    
    /**
     * Get link to the survey with params
     * $absolute boolean : get absolute url
     * @return string
     */
    public function getStartUrl($absolute = true)
    {
        /* Find a way to set code */
        $params = array(
            'srid'=>$this->srid,
            'code'=>$this->accesscode,
        );
        if($this->token) {
            $params['token'] = $this->token;
        }
        if(!$absolute) {
            return Yii::app()->getController()->createUrl(
                "/survey/index/sid/{$this->sid}",
                $params
            );
        }
        return Yii::app()->getController()->createAbsoluteUrl(
            "/survey/index/sid/{$this->sid}",
            $params
        );
    }
}
