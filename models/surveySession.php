<?php
/**
 * This file is part of reloadAnyResponse plugin
 * @version 1.0.1
 */
namespace reloadAnyResponse\models;
use Yii;
use CActiveRecord;
use Response;
use Survey;

class surveySession extends CActiveRecord
{
    /**
     * Class reloadAnyResponse\models\surveySession
     *
     * @property integer $sid survey
     * @property integer $srid : response id
     * @property string $token : optionnal token
     * @property string $session : the curent session id
     * @property datetime $lastaction : the last action done 
    */

    /* @const integer The max time for session, if it's not set in session or by server (0 in session_time_limit) */
    const maxSessionTime = 30;

    /** @inheritdoc */
    public static function model($className=__CLASS__) {
        return parent::model($className);
    }

    /**
     * init to set default
     *
     */
    public function init()
    {
        $this->sid = 0;
        $this->srid = 0;
        $this->token = "";
        $this->session = "";
        $this->lastaction = null;
    }

    /** @inheritdoc */
    public function tableName()
    {
        return '{{reloadanyresponse_surveySession}}';
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return array('sid', 'srid');
    }

    /** @inheritdoc */
    /** @todo */
    //~ public function rules()
    //~ {
    //~ }

    /**
     * Return (or create) self
     * @param integer $sid survey
     * @param integer $srid response id, if not set : find current session one, return if not 
     * @param string $token : optionnal token, if set : always reset
     * @return self|null
     */
    public static function saveSessionTime($sid,$srid=null,$token=null)
    {
        if(!$srid) {
            $srid = isset($_SESSION['survey_'.$sid]['srid']) ? $_SESSION['survey_'.$sid]['srid'] : null;
        }
        if(!$srid && !$token) {
            return;
        }
        if(Survey::model()->findByPk($sid)->getHasTokensTable()) {
            if(!$token) {
                $token = isset($_SESSION['survey_'.$sid]['token']) ? $_SESSION['survey_'.$sid]['token'] : null;
            }
            if(!$token) {
                $token = Yii::app()->getRequest()->getParam('token');
            }
            if(!$token) {
                $oResponse = Response::model($sid)->findByPk($srid);
                if($oResponse && !empty($oResponse->token)) {
                    $token = $oResponse->token;
                }
            }
        }
        $oSessionSurvey = self::model()->find("sid = :sid and srid = :srid",array(':sid'=>$sid,':srid'=>$srid));
        if(!$oSessionSurvey) {
            $oSessionSurvey = new self;
            $oSessionSurvey->sid = $sid;
            $oSessionSurvey->srid = $srid;
        }
        if($token) {
            $oSessionSurvey->token = $token;
        }
        $oSessionSurvey->lastaction = date('Y-m-d H:i:s');
        $oSessionSurvey->session = Yii::app()->getSession()->getSessionID();
        $oSessionSurvey->save();
        return $oSessionSurvey;
    }

    /**
     * Find if access is OK, delete old access for this srid
     * @param integer $sid survey
     * @param integer $srid response id, if not set : find current session one, return if not
     * @return float|null : time (in minutes) since last action is done
     */
    public static function getIsUsed($sid,$srid=null)
    {
        if(!$srid) {
            $srid = isset($_SESSION['survey_'.$sid]['srid']) ? $_SESSION['survey_'.$sid]['srid'] : null;
        }
        if(!$srid) {
            return;
        }
        $delay = self::_getSessionTimeLimit();
        if($delay === "0") {
            return null;
        }
        $maxDateTime=date('Y-m-d H:i:s', strtotime("{$delay} minutes ago"));
        self::model()->deleteAll("sid = :sid and lastaction < :lastaction",
            array(":sid"=>$sid,":lastaction"=>$maxDateTime)
        );
        $oSessionSurvey = self::model()->findByPk(array('sid'=>$sid,'srid'=>$srid));
        /* No current session save it and can quit */
        if(!$oSessionSurvey) {
            self::saveSessionTime($sid,$srid);
            return null;
        }
        /* Same session : save time and quit */
        if($oSessionSurvey->session == Yii::app()->getSession()->getSessionID()) {
            $oSessionSurvey->lastaction = date('Y-m-d H:i:s');
            $oSessionSurvey->save();
            return null;
        }
        /* In previous sessions : save time and quit */
        $previousSessionId = (array) Yii::app()->session['previousSessionId'];
        if(in_array($oSessionSurvey->session,$previousSessionId)) {
            $oSessionSurvey->session = Yii::app()->getSession()->getSessionID();
            $oSessionSurvey->lastaction = date('Y-m-d H:i:s');
            $oSessionSurvey->save();
            return null;
        }
        tracevar([
            $oSessionSurvey->session,
            Yii::app()->getSession()->getSessionID(),
            $previousSessionId,
        ]);
        $lastaction = strtotime($oSessionSurvey->lastaction);
        $now = strtotime("now");
        $sinceTime = abs($lastaction - $now) / 60;
        return $sinceTime;

    }

    /**
     * Get session time limit
     */
    private static function  _getSessionTimeLimit() {
        $sessionTimeLimit = intval(Yii::app()->getConfig('surveysessiontime_limit',self::maxSessionTime));
        return $sessionTimeLimit;
    }
}
