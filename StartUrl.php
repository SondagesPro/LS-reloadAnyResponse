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
use CDbCriteria;
use CHttpException;
use Survey;
use Permission;

class StartUrl
{
    /* var null|integer $surveyId */
    private $surveyId;
    /* var null|string $token */
    private $token;
    /* var boolean is available */
    private $available = false;
    /* var array settings */
    private $currentSettings = array();

    /**
     * constructor
     * @param integer survey id
     * @param string token
     * @throw Exception
     */
    public function __construct($surveyId, $token = null)
    {
        $oSurvey = \Survey::model()->findByPk($surveyId);
        if(empty($oSurvey)) {
            throw new Exception(404,gT("The survey in which you are trying to participate does not seem to exist."));
        }
        $this->surveyId = $surveyId;
        $this->token = $token;
    }

    /**
     * Validate if reload is available with current information
     * @throw Exception
     */
    public function isAvailable()
    {
        if($this->available) {
            return true;
        }
        if(!$this->surveyId) {
            return false;
        }
        $oSurvey = \Survey::model()->findByPk($this->surveyId);
        if($oSurvey->active != "Y") {
            return false;
        }
        if($this->token && $this->getSetting( 'allowTokenUser')) {
            $this->available = true;
            return $this->available;
        }
        if($this->getSetting( 'uniqueCodeAccess')) {
            $this->available = true;
            return $this->available;
        }

        if(Permission::model()->hasSurveyPermission($this->surveyId, 'responses', 'update') && $this->getSetting( 'allowAdminUser')) {
            $this->available = true;
            return $this->available;
        }
        return false;
    }

    /**
     * Get the url for a srid
     * @param integer needed srid
     * @param string[] $extraParams to be added on url
     * @param boolean $createCode create code (if not exist)
     * return false|string
     */
    public function getUrl($srid, $extraParams = array(), $createCode = false)
    {
        if(!$this->isAvailable()) {
            return false;
        }

        $params = array(
            'sid' => $this->surveyId,
            'srid' => $srid,
            'lang' => Yii::app()->getLanguage()
        );
        if($this->token) {
            $params['token'] = $this->token;
        }
        $params = array_merge($params,$extraParams);
        /* Get the response information  Need only token and srid, response can be big */
        $responseCriteria = new CDbCriteria;
        $responseCriteria->select = array('id');
        $oSurvey = \Survey::model()->findByPk($this->surveyId);
        if(!Survey::model()->findByPk($this->surveyId)->getIsAnonymized()) {
            $responseCriteria->select = array('id', 'token');
        }
        $responseCriteria->compare('id',$srid);
        $oResponse = \Response::model($this->surveyId)->find($responseCriteria);
        if(!$oResponse) {
            return false;
        }

        /* Check if accesscode ? Before token ? : review process ? */
        /* Create the specific admin right */
        $haveAdminRight = false;
        if(Permission::model()->hasSurveyPermission($this->surveyId, 'responses', 'update') && $this->getSetting( 'allowAdminUser')) {
            $haveAdminRight = true;
        }
        if(!$this->token && $haveAdminRight) {
            return App()->createUrl("survey/index",$params);
        }
        /* Check token validaty according to current srid */
        if(!$oSurvey->getIsAnonymized() && $oSurvey->getHasTokensTable())
        {
            /* currently token needed (valid one) */
            if(!$this->token) {
                return false;
            }
            $allowTokenUser = $this->getSetting( 'allowTokenUser');
            if(!$allowTokenUser && !$haveAdminRight) {
                // No rights in plugin settings
                return false;
            }
            if($this->token == $oResponse->token) {
                // Same token can return true
                return App()->createUrl("survey/index",$params);
            }
            $allowTokenGroupUser = $this->getSetting( 'allowTokenGroupUser');
            if(!$allowTokenGroupUser && !$haveAdminRight) {
                // No rights in plugin settings
                return false;
            }
            if(Utilities::checkIsValidToken($this->surveyId, $this->token, $oResponse->token)) {
                return App()->createUrl("survey/index",$params);
            }
            /* What to do for admin with invalid token ? */
            return false;
        }
        $uniqueCodeAccess = $this->getSetting( 'uniqueCodeAccess');
        if($uniqueCodeAccess) {
            $responseLink = \reloadAnyResponse\models\responseLink::model()->findByPk($this->surveyId, $srid );
            if($responseLink && $responseLink->accessCode) {
                $params['code'] = $responseLink->accesscode;
                return App()->createUrl("survey/index",$params);
            }
            if($createCode || $this->getSetting( 'uniqueCodeCreate')) {
                $responseLink = \reloadAnyResponse\models\responseLink::model()->setResponseLink($this->surveyId, $srid, $this->token);
                if($responseLink && $responseLink->accessCode) {
                    $params['code'] = $responseLink->accesscode;
                    return App()->createUrl("survey/index",$params);
                }
            }
            return false;
        }
        return false;
    }

    public function getSetting($setting)
    {
        if (isset($this->settings[$setting])) {
            return $this->settings[$setting];
        }
        $this->settings[$setting] = Utilities::getReloadAnyResponseSetting($this->surveyId, $setting);
        return $this->settings[$setting];
    }
}
