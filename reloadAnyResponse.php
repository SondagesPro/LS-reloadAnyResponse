<?php
/**
 * Plugin helper for limesurvey : new class and function allowing to reload any survey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.4.0
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

  protected $storage = 'DbStorage';

  static protected $description = 'New class and function allowing to reload any survey.';
  static protected $name = 'reloadAnyResponse';

  /**
   * @var array[] the settings
   */
  protected $settings = array(
    'information' => array(
        'type' => 'info',
        'content' => 'The default settings for all surveys. Remind no system is set in this plugin to show or send link to user.',
    ),
    'allowAdminUser' => array(
        'type'=>'checkbox',
        'htmlOptions'=>array(
            'value'=>1,
            'uncheckValue'=>0,
        ),
        'label'=>"Allow admin user to reload any survey with response id.",
        'default'=>1,
    ),
    'allowToken' => array(
        'type'=>'checkbox',
        'htmlOptions'=>array(
            'value'=>1,
            'uncheckValue'=>0,
        ),
        'label'=>"Allow user with a valid token.",
        'default'=>1,
    ),
    'uniqueCodeCreate' => array(
        'type'=>'checkbox',
        'htmlOptions'=>array(
            'value'=>1,
            'uncheckValue'=>0,
        ),
        'label'=>"Create automatically unique code for all surveys.",
        'help'=>"If code exist, it can be always used.",
        'default'=>0,
    ),
    'uniqueCodeAccess' => array(
        'type'=>'checkbox',
        'htmlOptions'=>array(
            'value'=>1,
            'uncheckValue'=>0,
        ),
        'label'=>"Allow entering unique code for all surveys if exist.",
        'help'=>"If you set to no, this disable usage for other plugins.",
        'default'=>1,
    ),
    //~ 'uniqueCodeCode' => array(
        //~ 'type'=>'string',
        //~ 'label'=>"Code in GET params to test.",
        //~ 'default'=>'code',
    //~ ),
  );

  /** @inheritdoc **/
  public function init()
  {
    $this->subscribe('beforeActivate');
    $oPlugin = Plugin::model()->find("name = :name",array("name"=>get_class($this)));
    if($oPlugin && $oPlugin->active) {
      $this->_setConfig();
    }
    /* Managing unique code */
    $this->subscribe('afterModelSave');
    $this->subscribe('afterModelDelete');
    /* Get the survey by srid and code */
    $this->subscribe('beforeSurveyPage');
    /* Replace existing system if srid = new */
    $this->subscribe('beforeLoadResponse');
    /* Survey settings */
    $this->subscribe('beforeSurveySettings');
    $this->subscribe('newSurveySettings');
  }

  /** @inheritdoc **/
  public function getPluginSettings($getValues=true)
  {
    /* @todo translation of label and help */
    return parent::getPluginSettings($getValues);
  }

  /** @inheritdoc **/
  public function beforeSurveySettings()
  {
    $oEvent = $this->event;
    /* currentDefault translation */
    $allowAdminUserDefault = $this->get('allowAdminUser',null,null,$this->settings['allowAdminUser']['default']) ? gT('Yes') : gT('No');
    $uniqueCodeCreateDefault = $this->get('uniqueCodeCreate',null,null,$this->settings['uniqueCodeCreate']['default']) ? gT('Yes') : gT('No');
    $uniqueCodeAccessDefault = $this->get('uniqueCodeAccess',null,null,$this->settings['uniqueCodeAccess']['default']) ? gT('Yes') : gT('No');

    $oEvent->set("surveysettings.{$this->id}", array(
      'name' => get_class($this),
      'settings' => array(
        'allowAdminUser'=>array(
          'type'=>'select',
          'label'=>$this->gT("Allow admin user to reload any survey with response id."),
          'options'=>array(
            1 =>gT("Yes"),
            0 =>gT("No"),
          ),
          'htmlOptions'=>array(
            'empty' => CHtml::encode(sprintf($this->gT("Use default (%s)"),$allowAdminUserDefault)),
          ),
          'current'=>$this->get('allowAdminUser','Survey',$oEvent->get('survey'),"")
        ),
        'uniqueCodeCreate'=>array(
          'type'=>'select',
          'label'=>$this->gT("Create automatically code."),
          'options'=>array(
            1 =>gT("Yes"),
            0 =>gT("No"),
          ),
          'htmlOptions'=>array(
            'empty' => CHtml::encode(sprintf($this->gT("Use default (%s)"),$uniqueCodeCreateDefault)),
          ),
          'current'=>$this->get('uniqueCodeCreate','Survey',$oEvent->get('survey'),"")
        ),
        'uniqueCodeAccess'=>array(
          'type'=>'select',
          'label'=>$this->gT("Allow entering unique code if exist."),
          'options'=>array(
            1 =>gT("Yes"),
            0 =>gT("No"),
          ),
          'htmlOptions'=>array(
            'empty' => CHtml::encode(sprintf($this->gT("Use default (%s)"),$uniqueCodeAccessDefault)),
          ),
          'current'=>$this->get('uniqueCodeAccess','Survey',$oEvent->get('survey'),"")
        ),
      ),
    ));
  }

  /** @inheritdoc **/
  public function newSurveySettings()
  {
    $event = $this->event;
    foreach ($event->get('settings') as $name => $value) {
      $this->set($name, $value, 'Survey', $event->get('survey'));
    }
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

    /* Delete all link when set a survey to inactive (@todo : test it) */
    if($className == 'Survey') {
      $sid = isset($oModel->sid) ? $oModel->sid : null;
      if($oModel->sid && $oModel->active != 'Y') {
        $deleted = \reloadAnyResponse\models\responseLink::model()->deleteAll("sid = sid",array('sid'=>$oModel->sid));
        if($deleted>0) { // Don't log each time, can be saved for something other …
          $this->log(sprintf("%d responseLink deleted for %d",$deleted,$oModel->sid),CLogger::LEVEL_INFO);
        }
      }
    }

    /* Create responlink for survey and srid (work when start a survey) */
    if($className == 'SurveyDynamic' || $className == 'Response') {
      $sid = str_replace(array('{{survey_','}}'),array('',''),$oModel->tableName());
      /* Test for sid activation */
      if(!$this->_getIsActivated('uniqueCodeCreate',$sid)) {
        return;
      }
      $srid = isset($oModel->id) ? $oModel->id : null;
      if($sid && $srid) {
        $responseLink = \reloadAnyResponse\models\responseLink::model()->findByPk(array('sid'=>$sid,'srid'=>$srid));
        /* @todo : add a way to reset potential token ? */
        if(!$responseLink) {
          /* @todo replace by \reloadAnyResponse\models\responseLink::getResponseLink('sid'=>$sid,'srid'=>$srid,$token,false) */
          $token = isset($oModel->token) ? $oModel->token : null;
          $responseLink = \reloadAnyResponse\models\responseLink::setResponseLink($sid,$srid,$token);
          if(!$responseLink) {
            $this->log("Unable to save responseLink with following errors.",CLogger::LEVEL_ERROR);
            $this->log(CVarDumper::dumpAsString($responseLink->getErrors()),CLogger::LEVEL_ERROR);
          }
        }
      }
    }
  }

  /** @inheritdoc **/
  public function afterModelDelete()
  {
    $oModel = $this->getEvent()->get('model');
    $className = get_class($oModel);
    if($className == 'SurveyDynamic' || $className == 'Response') {
      $sid = str_replace(array('{{survey_','}}'),array('',''),$oModel->tableName());
      $srid = isset($oModel->id) ? $oModel->id : null;
      if($srid) {
        \reloadAnyResponse\models\responseLink::model()->deleteByPk(array('sid'=>$sid,'srid'=>$srid));
      }
    }
  }

  /** @See event */
  public function beforeLoadResponse()
  {
    $srid = App()->getRequest()->getQuery('srid');
    if($srid=='new') {
      $this->getEvent()->set('response',false);
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

    $token = App()->getRequest()->getParam('token');
    if($srid == "new" && $token) {
        return;
    }
    //~ $accesscode = App()->getRequest()->getQuery($this->get('uniqueCodeCode'),null,null,$this->settings['uniqueCodeCode']['default']);
    $accesscode = App()->getRequest()->getQuery('code');
    $editAllowed = false;
    if($srid && $accesscode && $this->_getIsActivated('uniqueCodeAccess',$surveyid)) {
      $responseLink = \reloadAnyResponse\models\responseLink::model()->findByPk(array('sid'=>$surveyid,'srid'=>$srid));
      if($responseLink && $responseLink->accesscode == $accesscode) {
          $editAllowed = true;
      }
      if(!$responseLink) {
            throw new CHttpException(404,$this->gT("Sorry, this response didn't exist."));
      }
      if($responseLink && $responseLink->accesscode != $accesscode) {
            throw new CHttpException(401,$this->gT("Sorry, this access code is invalid."));
      }
    }
    if(!$editAllowed && $this->_getIsActivated('allowToken',$surveyid) && !Survey::model()->findByPk($surveyid)->getIsAnonymized()) {
      $editAllowed = true;
    }
    if(!$editAllowed && $this->_getIsActivated('allowAdminUser',$surveyid) && Permission::model()->hasSurveyPermission($surveyid,'response','update')) {
      $editAllowed = true;
    }
    
    if(!$editAllowed) {
        throw new CHttpException(401,$this->gT("Sorry, something happen bad."));
        return;
    }

    $this->_loadReponse($surveyid,$srid,App()->getRequest()->getParam('token'));
  }

  /**
   * Get boolean value for setting activation
   * @param string existing $setting
   * @param integer $surveyid
   * @return boolean
   */
  private function _getIsActivated($setting,$surveyid)
  {
    $activation = $this->get($setting,'Survey',$surveyid,"");
    if($activation === '') {
      $activation = $this->get($setting,null,null,$this->settings[$setting]['default']);
    }
    return (bool) $activation;
  }
  /**
   * Create needed DB
   * @return viod
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

  /**
   * Add needed alias and put it in autoloader
   * @return void
   */
  private function _setConfig()
  {
    Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
  }

  /**
   * Create Survey and add current response in $_SESSION
   * @param integer $surveydi
   * @param integer $srid
   * @throws Error 404
   * @return void
   */
  private function _loadReponse($surveyid,$srid,$token = null)
  {

    if(isset($_SESSION['survey_'.$surveyid]['srid']) && $_SESSION['survey_'.$surveyid]['srid'] == $srid) {
      return;
    }

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
    killSurveySession($surveyid); // Is this needed ?
    LimeExpressionManager::SetDirtyFlag();
    $_SESSION['survey_'.$surveyid]['srid'] = $oResponse->id;
    if (!empty($oResponse->lastpage)) {
        $_SESSION['survey_'.$surveyid]['LEMtokenResume'] = true;
        // If the response was completed start at the beginning and not at the last page - just makes more sense
        if (empty($oResponse->submitdate)) {
            $_SESSION['survey_'.$surveyid]['step'] = $oResponse->lastpage;
        }
        if(!empty($oResponse->submitdate) && $oSurvey->alloweditaftercompletion != 'Y') {
            $oResponse->submitdate = null;
            $oResponse->save();
            // Better to set Survey to alloweditaftercompletion == 'Y', but unable at this time on afterFindSurvey event
        }
    }
    buildsurveysession($surveyid);
    if (!empty($oResponse->submitdate)) {
        $_SESSION['survey_'.$surveyid]['maxstep'] = $_SESSION['survey_'.$surveyid]['totalsteps'];
    }

    randomizationGroupsAndQuestions($surveyid);
    initFieldArray($surveyid, $_SESSION['survey_'.$surveyid]['fieldmap']);
    loadanswers();
  }

  /**
   * @inheritdoc adding string, by default current event
   * @param string
   */
  public function log($message, $level = \CLogger::LEVEL_TRACE,$logDetail = null)
  {
    if(!$logDetail && $this->getEvent()) {
      $logDetail = $this->getEvent()->getEventName();
    } // What to put if no event ?
    parent::log($message, $level);
    Yii::log($message, $level,'application.plugins.reloadAnyResponse.'.$logDetail);
  }
  
}
