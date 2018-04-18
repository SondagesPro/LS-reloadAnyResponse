<?php
/**
 * Plugin helper for limesurvey : new class and function allowing to reload any survey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class reloadAnyResponse extends PluginBase {

  static protected $description = 'New class and function allowing to reload any survey.';
  static protected $name = 'reloadAnyResponse';

  public function init()
  {
    $this->subscribe('beforeActivate');
    //$this->_updateConfig();
    $oPlugin = Plugin::model()->find("name = :name",array("name"=>get_class($this)));
    if($oPlugin && $oPlugin->active) {
      $this->_addHelpersModels();
    }
    $this->subscribe('afterModelSave');
    $this->subscribe('afterModelDelete');
    $this->subscribe('beforeSurveyPage');
  }

  /** @inheritdoc **/
  public function beforeActivate()
  {
    $this->_createDb();
  }

  /** @inheritdoc **/
  public function afterModelSave()
  {
    $oModel = $this->getEvent()->get('model');
    $className = get_class($oModel);
    if($className == 'SurveyDynamic' || $className == 'Response') {
      $sid = str_replace(array('{{survey_','}}'),array('',''),$oModel->tableName());
      $srid = isset($oModel->id) ? $oModel->id : null;
      tracevar($srid);
      if($srid) {
        $responseLink = responseLink::model()->findByPk(array('sid'=>$sid,'srid'=>$srid));
        if(!$responseLink) {
          $token = isset($oModel->token) ? $oModel->token : null;
          $accesscode = Yii::app()->securityManager->generateRandomString(42);
          $responseLink = New responseLink;
          $responseLink->sid = $sid;
          $responseLink->srid = $srid;
          $responseLink->token = $token;
          $responseLink->accesscode = $accesscode;
          if(!$responseLink->save()) {
            tracevar($responseLink->getErrors());
          } 
        }
      }
      if($className == 'Survey') {
        if($oModel->active != 'Y') {
          responseLink::model()->deleteAll("sid = sid",array('sid'=>$sid));
        }
      }
      //include("/home/sondages.pro/htdocs/clients/complets/draafbfc/LimeSurvey/plugins/reloadAnyResponse/models/responseLink.php");
      //tracevar($oModel->id);
    }
  }

  /** @inheritdoc **/
  public function afterModelDelete()
  {
    if($className == 'SurveyDynamic' || $className == 'Response') {
      $sid = str_replace(array('{{survey_','}}'),array('',''),$oModel->tableName());
      $srid = isset($oModel->id) ? $oModel->id : null;
      if($srid) {
        responseLink::model()->deleteByPk(array('sid'=>$sid,'srid'=>$srid));
      }
    }
  }

  /** @inheritdoc **/
  public function beforeSurveyPage()
  {
    $srid = App()->getRequest()->getQuery('srid');
    $surveyid = $this->getEvent()->get('surveyId');
    if(!$srid) {
        return;
    }
    $accesscode = App()->getRequest()->getQuery('code');
    if($srid && $accesscode) {
      $responseLink = responseLink::model()->findByPk(array('sid'=>$surveyid,'srid'=>$srid));
      if(!$responseLink || $responseLink->accesscode != $accesscode) {
        // @todo Throw error
        $responseLink = null;
      }
    }
    if(!$responseLink && Permission::model()->hasSurveyPermission($surveyid,'response','update')) {
      $responseLink = responseLink::model()->findByPk(array('sid'=>$surveyid,'srid'=>$srid));
      if(!$responseLink) {
        // @todo : throw 404 error
      }
    }
    if(!$responseLink) {
        return;
    }
    $this->_loadReponse($surveyid,$srid,App()->getRequest()->getParam('token'));
  }
  /**
   * Create needed DB
   * @return boolean
   */
  private function _createDb()
  {
    if (!$this->api->tableExists($this, 'responseLink'))
    {
      $this->api->createTable($this, 'responseLink', array(
          'sid'=>'int',
          'srid'=>'int',
          'token'=>'text',
          'accesscode'=>'text',
      ));
    }
  }

  private function _addHelpersModels()
  {
    Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
    Yii::import(get_class($this).".models.responseLink");
  }

  /**
   * Create Survey and oad response in $_SESSION
   * @param integer $surveydi
   * @param integer $srid
   * @throws Error 404
   * @return void
   */
  private function _loadReponse($surveyid,$srid,$token = null)
  {
    
    $oResponse = SurveyDynamic::model($surveyid)->find("id = :srid",array(':srid'=>$srid));
    if(!$oResponse) {
      throw new CHttpException(404, $this->gT('Response not found.'));
    }
    $oSurvey = Survey::model()->findByPk($surveyid);
    // Validate token
    if(!Permission::model()->hasSurveyPermission($surveyid,'response','update') && $oResponse->token) {
      if($oResponse->token != $token) {
        throw new CHttpException(401, $this->gT('Access to this response need valid token.'));
      }
    }
    LimeExpressionManager::SetDirtyFlag();
    $_SESSION['survey_'.$surveyid]['srid'] = $oResponse->id;
    if (!empty($oResponse->lastpage)) {
        $_SESSION['survey_'.$surveyid]['LEMtokenResume'] = true;
        // If the response was completed and user is allowed to edit after completion start at the beginning and not at the last page - just makes more sense
        if (empty($oResponse->submitdate)) {
            $_SESSION['survey_'.$surveyid]['step'] = $oResponse->lastpage;
        }
    }
    buildsurveysession($surveyid);
    if (!empty($oResponse->submitdate)) {
        $_SESSION['survey_'.$surveyid]['maxstep'] = $_SESSION['survey_'.$surveyid]['totalsteps'];
    }
    loadanswers();
    randomizationGroupsAndQuestions($surveyid);
    initFieldArray($surveyid, $_SESSION['survey_'.$surveyid]['fieldmap']);
  }
}
