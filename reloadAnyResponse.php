<?php
/**
 * Plugin helper for limesurvey : new class and function allowing to reload any survey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.5.1
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
    'allowTokenUser' => array(
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
    'disableMultiAccess' => array(
        'type'=>'info',
        'content'=>"<div class='alert alert-info'>You need renderMessage for disabling multiple access.</div>",
        'default'=>1,
    ),
    'multiAccessTime'=>array(
        'type'=>'int',
        'label' => 'Time for disable multiple access (in minutes) (config.php settings replace it of not exist)',
        'help' => 'Before save value or entering survey : test if someone else edit response in this last minutes. Disable save and show a message if yes. Set to empty disable system but then answer of user can be deleted by another user without any information…',
        'htmlOptions'=>array(
            'min'=>1,
        ),
        'default' => 20,
    ),
    //~ 'multiAccessTimeOptOut'=>array(
        //~ 'type'=>'int',
        //~ 'label' => 'Auto save and close current responses (with a javascript solution) in minutes.',
        //~ 'help' => 'If user didn‘t do any action on his browser during access time, save and close the windows. Set to an empty disable this feature.',
        //~ 'htmlOptions'=>array(
            //~ 'min'=>1,
        //~ ),
        //~ 'default' => 20,
    //~ ),
    //~ 'multiAccessTimeAlert'=>array(
        //~ 'type'=>'int',
        //~ 'label' => 'Time for alert shown for optout of survey',
        //~ 'help' => 'Set to empty to disable. This alert is shown after X minutes, where X is the number here.',
        //~ 'htmlOptions'=>array(
            //~ 'min'=>1,
        //~ ),
        //~ 'default' => 18,
    //~ ),
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
    /* Save current session */
    $this->subscribe('beforeSurveyPage');
    /* Replace existing system if srid = new */
    $this->subscribe('beforeLoadResponse');
    /* Survey settings */
    $this->subscribe('beforeSurveySettings');
    $this->subscribe('newSurveySettings');
    /* delete current session*/
    $this->subscribe("afterSurveyComplete",'deleteSurveySession');
    $this->subscribe("afterSurveyQuota",'deleteSurveySession');

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
    $allowTokenDefault = $this->get('allowTokenUser',null,null,$this->settings['allowTokenUser']['default']) ? gT('Yes') : gT('No');
    $uniqueCodeCreateDefault = $this->get('uniqueCodeCreate',null,null,$this->settings['uniqueCodeCreate']['default']) ? gT('Yes') : gT('No');
    $uniqueCodeAccessDefault = $this->get('uniqueCodeAccess',null,null,$this->settings['uniqueCodeAccess']['default']) ? gT('Yes') : gT('No');

    $oEvent->set("surveysettings.{$this->id}", array(
      'name' => get_class($this),
      'settings' => array(
        'allowAdminUser'=>array(
          'type'=>'select',
          'label'=>$this->_translate("Allow admin user to reload any response with response id."),
          'options'=>array(
            1 =>gT("Yes"),
            0 =>gT("No"),
          ),
          'htmlOptions'=>array(
            'empty' => CHtml::encode(sprintf($this->_translate("Use default (%s)"),$allowAdminUserDefault)),
          ),
          'current'=>$this->get('allowAdminUser','Survey',$oEvent->get('survey'),"")
        ),
        'allowTokenUser'=>array(
          'type' => 'select',
          'label' => $this->_translate("Allow participant with token to create or reload responses."),
          'help' => $this->_translate("Related to “Enable token-based response persistence” and “Allow multiple responses or update responses” survey settings."),
          'options'=>array(
            1 =>gT("Yes"),
            0 =>gT("No"),
          ),
          'htmlOptions'=>array(
            'empty' => CHtml::encode(sprintf($this->_translate("Use default (%s)"),$allowTokenDefault)),
          ),
          'current'=>$this->get('allowTokenUser','Survey',$oEvent->get('survey'),"")
        ),
        'uniqueCodeCreate'=>array(
          'type'=>'select',
          'label'=>$this->_translate("Create unique code automatically."),
          'options'=>array(
            1 =>gT("Yes"),
            0 =>gT("No"),
          ),
          'htmlOptions'=>array(
            'empty' => CHtml::encode(sprintf($this->_translate("Use default (%s)"),$uniqueCodeCreateDefault)),
          ),
          'current'=>$this->get('uniqueCodeCreate','Survey',$oEvent->get('survey'),"")
        ),
        'uniqueCodeAccess'=>array(
          'type'=>'select',
          'label'=>$this->_translate("Allow using unique code if exist."),
          'options'=>array(
            1 =>gT("Yes"),
            0 =>gT("No"),
          ),
          'htmlOptions'=>array(
            'empty' => CHtml::encode(sprintf($this->_translate("Use default (%s)"),$uniqueCodeAccessDefault)),
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
    $surveyId = $this->getEvent()->get('surveyId');
    $token = App()->getRequest()->getParam('token');
    if($srid=='new' && $token && $this->_getIsActivated('allowTokenUser',$surveyid)) {
        $this->getEvent()->set('response',
            $this->_createNewResponse($surveyId,$token)
        );
    }
    /* control multi access to token with token and allow edit reponse */
    $oSurvey = Survey::model()->findByPk($surveyId);
    if($oSurvey && $oSurvey->alloweditaftercompletion == "Y" && $oSurvey->tokenanswerspersistence == "Y") {
        /* Get like limesurvey (in this situation) : get the last srid with this token (without plugin …) */
        $oResponse = Response::model($surveyId)->find(array(
            'select' => 'id',
            'condition' => 'token=:token',
            'order' => 'id DESC',
            'params' => array('token' => $token)
        ));
        if($this->_getCurrentSetting('disableMultiAccess',$surveyId) && $oResponse && ($since = \reloadAnyResponse\models\surveySession::getIsUsed($surveyId,$oResponse->id))) {
            $this->_endWithEditionMessage($since);
        }
        \reloadAnyResponse\models\surveySession::saveSessionTime($surveyId,$oResponse->id);
    }
    /* @todo : control what happen with useleft > 1 and tokenanswerspersistence != "Y" */

  }

    /** @inheritdoc **/
    public function beforeSurveyPage()
    {
        /* Save current session Id to allow same user to reload survey in same browser */
        /* resetAllSessionVariables regenerate session id */
        /* Keep previous session id, if user reload start url it reset the sessionId, need to leav access */
        $previousSessionId = Yii::app()->session['previousSessionId'];
        if(empty($previousSessionId)) {
            $previousSessionId = array(
                Yii::app()->getSession()->getSessionID(),
                Yii::app()->getSession()->getSessionID(),
            );
        }
        $previousSessionId = array(
            Yii::app()->getSession()->getSessionID(),
            $previousSessionId[0],
        );
        Yii::app()->session['previousSessionId'] = $previousSessionId;
        $surveyid = $this->getEvent()->get('surveyId');
        if(!empty($this->get('multiAccessTime','Survey',$surveyid))) {
            Yii::app()->setConfig('surveysessiontime_limit',$this->get('multiAccessTime','Survey',$surveyid));
        }
        $disableMultiAccess = $this->_getCurrentSetting('disableMultiAccess',$surveyid);
        /* For token : @todo in beforeReloadReponse */
        /* @todo : delete surveySession is save or clearall action */
        if($disableMultiAccess && ($since = \reloadAnyResponse\models\surveySession::getIsUsed($surveyid))) {
            /* This one is done with current session : maybe allow to keep srid in session and reload it ? */
            $this->saveCurrentSrid($surveyid);
            killSurveySession($surveyid);
            $this->_endWithEditionMessage($since,array(
                'comment' => $this->_translate('We save your current session, you can try to reload the survey in some minutes.'),
                'class'=>'alert alert-info',
            ));
        }
        $srid = App()->getRequest()->getQuery('srid');
        if(!$srid && $disableMultiAccess) {
            /* Always save current srid if needed , only reload can disable this */
            \reloadAnyResponse\models\surveySession::saveSessionTime($surveyid);
            return;
        }
        $oSurvey = Survey::model()->findByPk($surveyid);
        $token = App()->getRequest()->getParam('token');
        if($srid == "new") {
            // Done in beforeLoadResponse
          return;
        }
        if(!$srid) {
            $srid = $this->getCurrentSrid($surveyid);
        }
        if(!$srid) {
            return;
        }
        //~ $accesscode = App()->getRequest()->getQuery($this->get('uniqueCodeCode'),null,null,$this->settings['uniqueCodeCode']['default']);
        $accesscode = App()->getRequest()->getQuery('code');
        $editAllowed = false;
        if($accesscode && $this->_getIsActivated('uniqueCodeAccess',$surveyid)) {
            $responseLink = \reloadAnyResponse\models\responseLink::model()->findByPk(array('sid'=>$surveyid,'srid'=>$srid));
            if($responseLink && $responseLink->accesscode == $accesscode) {
                $editAllowed = true;
            }
            if(!$responseLink) {
                throw new CHttpException(404,$this->_translate("Sorry, this response didn't exist."));
            }
            if($responseLink && $responseLink->accesscode != $accesscode) {
                throw new CHttpException(401,$this->_translate("Sorry, this access code is invalid."));
            }
        }
        if(!$editAllowed && $this->_getIsActivated('allowTokenUser',$surveyid) && !$oSurvey->getIsAnonymized()) {
            $editAllowed = true;
        }
        if(!$editAllowed && $this->_getIsActivated('allowAdminUser',$surveyid) && Permission::model()->hasSurveyPermission($surveyid,'response','update')) {
            $editAllowed = true;
        }

        if(!$editAllowed) {
            throw new CHttpException(401,$this->_translate("Sorry, something happen bad."));
            return;
        }
        if($since = \reloadAnyResponse\models\surveySession::getIsUsed($surveyid,$srid)) {
            $this->_endWithEditionMessage($since);
        }
        $this->_loadReponse($surveyid,$srid,App()->getRequest()->getParam('token'));
  }

    /**
     * Delete SurveySession for this event srid
     */
    public function deleteSurveySession()
    {
        $surveyId= $this->getEvent()->get('surveyId');
        $responseId= $this->getEvent()->get('responseId');
        \reloadAnyResponse\models\surveySession::model()->deleteByPk(array('sid'=>$surveyId,'srid'=>$responseId));
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
    * @return void
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
        if (!$this->api->tableExists($this, 'surveySession'))
        {
            $this->api->createTable($this, 'surveySession', array(
                'sid' => 'int',
                'srid' => 'int',
                'token' => 'string(55)',
                'session' => 'text',
                'lastaction' => 'datetime'
            ));
        }
    }

  /**
   * Add needed alias and put it in autoloader,
   * add surveysessiontime_limit to global config
   * @return void
   */
  private function _setConfig()
  {
    Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
    $this->_createDb();
    if(!empty(Yii::app()->getConfig('surveysessiontime_limit')) ) {
        /* Allow to force surveysessiontime_limit in config.php , to do : show it to admin */
        Yii::app()->setConfig('surveysessiontimeDisable',true);
    }
    if(empty(Yii::app()->getConfig('surveysessiontime_limit')) ) {
        Yii::app()->setConfig('surveysessiontime_limit',$this->get('multiAccessTime',null,null,$this->settings['multiAccessTime']['default']));
    }
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
      throw new CHttpException(404, $this->_translate('Response not found.'));
    }
    $oSurvey = Survey::model()->findByPk($surveyid);
    // Validate token
    if(!Permission::model()->hasSurveyPermission($surveyid,'response','update') && $oResponse->token) {
      if($oResponse->token != $token) {
        throw new CHttpException(401, $this->_translate('Access to this response need a valid token.'));
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
   * Create a new response for token
   * @param int $surveyid
   * @param string $token
   * @return null|\Response
   */
  private function _createNewResponse($surveyid,$token) {
      $oSurvey = Survey::model()->findByPk($surveyid);
      if(!$oSurvey->getHasTokensTable()) {
        return;
      }
      if($oSurvey->getIsAnonymized()) {
        return;
      }
      if(!$oSurvey->tokenanswerspersistence) {
        return;
      }
      if(!$oSurvey->alloweditaftercompletion) {
        return;
      }
    /* some control */
    if(!(
      ($this->_getIsActivated('allowAdminUser',$surveyid) && Permission::model()->hasSurveyPermission($surveyid,'response','create'))
      ||
      ($this->_getIsActivated('allowTokenUser',$surveyid))
      )) {
      // Disable here
      $this->log("Try to create a new reponse with token but without valid rights",'warning');
      return;
    }
    $oToken = Token::model($surveyid)->findByAttributes(array('token' => $token));
    if(empty($oToken)) {
      return;
    }
    $oResponse = Response::create($surveyid);
    $oResponse->token = $oToken->token;
    $oResponse->startlanguage = Yii::app()->getLanguage();
    /* @todo generate if not set */
    $oResponse->seed = isset($_SESSION['survey_'.$surveyid]['startingValues']['seed']) ? $_SESSION['survey_'.$surveyid]['startingValues']['seed'] : null;
    $oResponse->lastpage=-1;
    if($oSurvey->datestamp == 'Y') {
        $date = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig('timeadjust'));
        $oResponse->datestamp = $date;
        $oResponse->startdate = $date;
    }
    $oResponse->save();
    return $oResponse;
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

    /**
     * Save current srid if exist in specific session
     * @return void
     */
    public function saveCurrentSrid($surveyId)
    {
        $currentSrid = isset($_SESSION['survey_'.$surveyId]['srid']) ? $_SESSION['survey_'.$surveyId]['srid'] : null;
        if(!$currentSrid) {
            return;
        }
        $sessionCurrentSrid = Yii::app()->session['reloadAnyResponsecurrentSrid'];
        if(empty($sessionCurrentSrid)) {
            $sessionCurrentSrid = array();
        }
        $sessionCurrentSrid[$surveyId] = $currentSrid;
        Yii::app()->session['reloadAnyResponsecurrentSrid'] = $sessionCurrentSrid[$surveyId];
    }

    /**
     * Get current srid if exist in specific session
     * @return integer|null
     */
    public function getCurrentSrid($surveyId)
    {
        $sessionCurrentSrid = Yii::app()->session['reloadAnyResponsecurrentSrid'];
        if(empty($sessionCurrentSrid) || empty($sessionCurrentSrid[$surveyId])) {
            return;
        }
        return $sessionCurrentSrid[$surveyId];
    }
    /**
     * Ending with the delay if renderMessage is available (if not : log as error …)
     * @param float $since last edit
     * @param string|string[] $comment array with 'comment' and 'class'
     * @return void
     */
    private function _endWithEditionMessage($since,$comment=null)
    {
        if(!Yii::getPathOfAlias('renderMessage')) {
            /* plugin log */
            $this->log("You need to download and activate renderMessage plugin for disableMultiAccess",'error');
            /* Yii log as vardump if debug>0 to be shown to user (with red part) */
            Yii::log("reloadAnyReponse plugin : You need to download and activate renderMessage plugin for disableMultiAccess", 'error', 'vardump');
            return;
        }
        $message = CHtml::tag("div",array("class"=>'alert alert-danger'),sprintf($this->_translate("Sorry, someone update this response to the questionnaire a short time ago. The last action was made less than %s minutes ago."),ceil($since)));
        if($comment) {
            if(is_string($comment)) {
                $comment = array(
                    'comment'=> $comment,
                    'class'=>'alert alert-info',
                );
            }
            $message .= CHtml::tag("div",array("class"=>$comment['class']),$comment['comment']);
        }
        \renderMessage\messageHelper::renderContent($message);

    }

    /**
     * Get current setting for current survey (use empty string as null value)
     * @param string setting to get
     * @param integer survey id
     * @return string|array|null
     */
    private function _getCurrentSetting($setting,$surveyId = null)
    {
        if($surveyId) {
            $value = $this->get($setting,'Survey',$surveyId,'');
            if($value !== '') {
                return $value;
            }
        }
        $default = (isset($this->settings[$setting]['default'])) ? $this->settings[$setting]['default'] : null;
        return $this->get($setting,null,null,$default);
    }

    /**
     * get translation
     * @param string
     * @return string
     */
    private function _translate($string){
        return Yii::t('',$string,array(),get_class($this));
    }

    /**
     * Add this translation just after loaded all plugins
     * @see event afterPluginLoad
     */
    public function afterPluginLoad(){
        // messageSource for this plugin:
        $messageSource=array(
            'class' => 'CGettextMessageSource',
            'cacheID' => get_class($this).'Lang',
            'cachingDuration'=>3600,
            'forceTranslation' => true,
            'useMoFile' => true,
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR.'locale',
            'catalog'=>'messages',// default from Yii
        );
        Yii::app()->setComponent(get_class($this),$messageSource);
    }

}
