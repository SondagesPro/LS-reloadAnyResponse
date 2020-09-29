<?php
/**
 * Some Utilities
 * 
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2020 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
namespace reloadAnyResponse;

use App;
use Yii;
use CHttpException;
class Utilities
{
    CONST DefaultSettings = array(
        'allowAdminUser' => 1,
        'allowTokenUser' => 0,
        'uniqueCodeAccess' => 1,
    );
  /**
   * Create Survey and add current response in $_SESSION
   * @param integer $surveydi
   * @param integer $srid
   * @param string $token
   * @param string $accesscode
   * @throws Errors
   * @return void
   */
    public static function loadReponse($surveyid, $srid, $token = null, $accesscode = null)
    {
        if(self::getCurrentSrid($surveyid) == $srid) {
            return;
        }
        $oResponse = \SurveyDynamic::model($surveyid)->find("id = :srid",array(':srid'=>$srid));
        $language = App()->getLanguage();
        if(!$oResponse) {
            throw new CHttpException(404, self::translate('Response not found.'));
        }
        $oSurvey = \Survey::model()->findByPk($surveyid);
        /* @var boolean, did edition is allowed with current params and settings */
        $editAllowed = false;
        /* we check usage by usage : accesscode , token, admin */
        if ($accesscode
            && self::getReloadAnyResponseSetting($surveyid, 'uniqueCodeAccess')
        ) {
            $responseLink = \reloadAnyResponse\models\responseLink::model()->findByPk(array('sid'=>$surveyid,'srid'=>$srid));
            if(!$responseLink) {
                throw new CHttpException(401, self::translate('Sorry, this access code is not valid.'));
            }
            if($responseLink && $responseLink->accesscode != $accesscode) {
                throw new CHttpException(401, self::translate('Sorry, this access code is not valid.'));
            }
            $editAllowed = true;
        }
        if (!$editAllowed
            && $token
            && $oResponse->token
            && self::getReloadAnyResponseSetting($surveyid, 'allowTokenUser')
        ) {
            /* Check the list of token with reponseListAndManage */
            // $oSurvey->anonymized != "Y" && tableExists("{{tokens_".$surveyid."}}"); ?
            if(!self:checkIsValidToken($surveyid, $token, $oResponse->token)) {

            }
            if($token && $oResponse->token) {
                throw new CHttpException(401, self::translate('Sorry, this token is not valid.'));
            }
            $editAllowed = true;
        }
        if (!$editAllowed) {
            $havePermission = self::getReloadAnyResponseSetting($surveyid, 'allowAdminUser') && \Permission::model()->hasSurveyPermission($surveyid,'response','update');
            if (!$havePermission) {
                throw new CHttpException(401, self::translate('Sorry, you don‘t have access to this response.'));
            }
        }
        killSurveySession($surveyid);
        \LimeExpressionManager::SetDirtyFlag();
        $_SESSION['survey_'.$surveyid]['srid'] = $oResponse->id;
        if (!empty($oResponse->lastpage)) {
            $_SESSION['survey_'.$surveyid]['LEMtokenResume'] = true;
            // If the response was completed start at the beginning and not at the last page - just makes more sense
            if (empty($oResponse->submitdate)) {
                $_SESSION['survey_'.$surveyid]['step'] = $oResponse->lastpage;
            }
            /*
            Move it to beforeSurveyPage only if POST value

            */
        }
        $_SESSION['survey_'.$surveyid]['s_lang'] = $language; /* buildsurveysession use session lang … , send a notic if not set */
        buildsurveysession($surveyid);
        if (!empty($oResponse->submitdate)) {
            $_SESSION['survey_'.$surveyid]['maxstep'] = $_SESSION['survey_'.$surveyid]['totalsteps'];
        }
        if (tableExists('tokens_'.$surveyid) && !empty($oResponse->token)) {
            $_SESSION['survey_'.$surveyid]['token'] = $oResponse->token;
        }
        randomizationGroupsAndQuestions($surveyid);
        initFieldArray($surveyid, $_SESSION['survey_'.$surveyid]['fieldmap']);
        loadanswers();
        models\surveySession::saveSessionTime($surveyid,$oResponse->id);
    }

    /**
     * get current srid for a survey
     * @param $surveyid integer
     * @return integer|null
     */
    public static function getCurrentSrid($surveyid)
    {
        if ( empty($_SESSION['survey_'.$surveyid]['srid']) ) {
            return null;
        }
        return $_SESSION['survey_'.$surveyid]['srid'];
    }

    /**
     * get current srid for a survey
     * @param $surveyid integer
     * @return integer|null
     */
    public static function getCurrentReloadedSrid($surveyid)
    {
        if ( empty($_SESSION['survey_'.$surveyid]['reloadAnyResponse']) ) {
            return null;
        }
        return $_SESSION['survey_'.$surveyid]['srid'];
    }

    /**
     * get current srid for a survey
     * @param $surveyid integer
     * @return integer|null
     */
    public static function setCurrentReloadedSrid($surveyid, $srid)
    {
        $_SESSION['survey_'.$surveyid]['reloadAnyResponse'] = $srid;
    }

    /**
     * Translate by this plugin
     * @see reloadAnyResponse->_setConfig
     * @param string $string to translate
     * @param string $language for translation
     * @return string
     */
    public static function translate($string, $language = null)
    {
        return Yii::t('', $string, array(), 'ReloadAnyResponseMessages', $language);
    }

    /**
     * Get a DB setting from a plugin
     * @param integer survey id
     * @param string setting name
     * @return mixed
     */
    public static function getReloadAnyResponseSetting($surveyId, $sSetting) {
        $oPlugin = \Plugin::model()->find(
            "name = :name",
            array(":name" => 'reloadAnyResponse')
        );
        if(!$oPlugin || !$oPlugin->active) {
            return $default;
        }
        $oSetting = \PluginSetting::model()->find(
            'plugin_id = :pluginid AND '.App()->getDb()->quoteColumnName('key').' = :key AND model = :model AND model_id = :surveyid',
            array(
                ':pluginid' => $oPlugin->id,
                ':key' => $sSetting,
                ':model' => 'Survey',
                ':surveyid' => $surveyId,
            )
        );
        if(!empty($oSetting)) {
            $value = json_decode($oSetting->value);
            if($value !== '') {
                return $value;
            }
        }

        $oSetting = \PluginSetting::model()->find(
            'plugin_id = :pluginid AND '.App()->getDb()->quoteColumnName('key').' = :key AND model = :model AND model_id = :surveyid',
            array(
                ':pluginid' => $oPlugin->id,
                ':key' => $sSetting,
                ':model' => null,
                ':surveyid' => null,
            )
        );
        if(!empty($oSetting)) {
            $value = json_decode($oSetting->value);
            if($value !== '') {
                return $value;
            }
        }
        if (isset(self::DefaultSettings[$sSetting])) {
            return self::DefaultSettings[$sSetting];
        }
        return null;
    }

  /**
   * Reset a survey 
   * @param integer $surveydi
   * @param integer $srid
   * @param string $token
   * @param boolean $forced
   * @return void
   */
    public static function resetLoadedReponse($surveyid, $srid, $token = null, $forced = false)
    {
        if(self::getCurrentReloadedSrid($surveyid) == $srid) {
            if($forced || \Survey::model()->findByPk($surveyid)->alloweditaftercompletion != 'Y') {
                $oResponse = \SurveyDynamic::model($surveyid)->updateByPk($srid, array('submitdate'=>null));
            }
            if($token && \Survey::model()->findByPk($surveyid)->anonymized != 'Y') {
                $oResponse = \SurveyDynamic::model($surveyid)->updateByPk($srid, array('token'=>$token));
            }
        }
    }

  /**
   * Check if a token is valid with another one
   * @param integer $surveyd
   * @param string $token to be validated
   * @param string $token for control
   * @return boolean
   */
    public static function checkIsValidToken($surveyid, $token, $validtoken)
    {
        if(empty($validtoken)) {
            return true;
        }
        if(empty($token)) {
            return false;
        }
        if($token == $validtoken)) {
            return true;
        }
        if(App()->getPathOfAlias('responseListAndManage')) {
            if(in_array($token, \responseListAndManage\helpers\getTokensList($surveyid,$validtoken))) {
                return true;
            }
        }
        return false;
    }

}
