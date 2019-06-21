<?php
/**
 * This file is part of reloadAnyResponse plugin
 * Helpers for other plugin : reload or create responses
 * @version 0.1.0
 */
namespace reloadAnyResponse\helpers;
use Yii;
use LimeExpressionManager;
use Survey;
use SurveyLanguageSetting;
use SurveyDynamic;

class reloadResponse
{

    /** @var null|integer $surveyId **/
    public $surveyId = null;
    /** @var null|integer $srid **/
    public $srid = null;
    /** @var null|string $language **/
    public $language = null;

    /** @var boolean **/
    private $surveyStarted = false;

    /**
     * @param integer $surveyId
     * @param integer $srid
     * @param string|null $language
     */
    public function __construct($surveyId , $srid, $language = null)
    {
        $this->surveyId = $surveyId;
        $this->srid = $srid;
        $oSurvey = Survey::model()->findByPk($surveyId);
        if(!$language) {
            $language = Yii::app()->getLanguage();
        }
        if(!$language || !in_array($language,$oSurvey->getAllLanguages())) {
            $language = $oSurvey->language;
        }
        $this->language = $language;
    }
    /**
     * Start and load a Survey
     * No control is done for rights or token in this function
     * @param null|true|integer $step , null : 1st page, true : get current step or response , integer : to this step
     * @param boolean|null $resetSubmit, if null : reset if needed (submitted and alloweditaftercompletion disable)
     * @return void
     */
    public function startSurvey($step = true, $resetSubmit = null)
    {
        $surveyId = $this->surveyId;
        $reset = (LimeExpressionManager::getLEMsurveyId() != $surveyId); // Unsure needed
        LimeExpressionManager::SetSurveyId($surveyId);
        if(empty($_SESSION['survey_'.$surveyId]['fieldmap'])) {
            /* Warning can be deprecated , updated and removed*/
            buildsurveysession($surveyId);
            if(version_compare(Yii::app()->getConfig('versionnumber'),"3",">=")) {
                randomizationGroupsAndQuestions($surveyId);
                initFieldArray($surveyId, $_SESSION['survey_'.$surveyId]['fieldmap']);
            }
        }
        $oResponse = SurveyDynamic::model($surveyId)->findByPk($this->srid);
        if(empty($oResponse)) {
            /* Not available currently, no need */
            throw new \Exception('Reponse not found.');
        }
        $token = '';
        if(!empty($oResponse->token)) {
            $token = $_SESSION['survey_'.$surveyId]['token'] = $oResponse->token;
        }
        $_SESSION['survey_'.$surveyId]['srid'] = $oResponse->id;
        /* Warning can be deprecated , updated and removed*/
        loadanswers();
        unset($_SESSION['survey_'.$surveyId]['LEMtokenResume']);
        $oSurvey = Survey::model()->findByPk($surveyId);
        switch($oSurvey->format) {
            case "A":
                $surveyMode = "survey";
                break;
            case "Q":
                $surveyMode = "question";
                break;
            case "G":
            default:
                $surveyMode = "group";
                break;
        }
        $oLanguageSettings = SurveyLanguageSetting::model()->findByPk(array('surveyls_survey_id'=>$surveyId,'surveyls_language'=>App()->getLanguage()));
        $radix = getRadixPointData($oLanguageSettings->surveyls_numberformat);
        $radix = $radix['separator'];
        $aSurveyOtions = array(
            'active'                      => $oSurvey->active == 'Y',
            'allowsave'                   => $oSurvey->allowsave == 'Y',
            'anonymized'                  => $oSurvey->anonymized != 'N',
            'assessments'                 => $oSurvey->assessments == 'Y',
            'datestamp'                   => $oSurvey->datestamp == 'Y',
            'deletenonvalues'             => Yii::app()->getConfig('deletenonvalues'),
            'hyperlinkSyntaxHighlighting' => false,
            'ipaddr'                      => $oSurvey->ipaddr == 'Y',
            'radix'                       => $radix,
            'refurl'                      => ($oSurvey->refurl == "Y" && isset($_SESSION["survey_$surveyId"]['refurl']) ) ? $_SESSION["survey_$surveyId"]['refurl'] : null,
            'savetimings'                 => $oSurvey->savetimings == "Y",
            'surveyls_dateformat'         => $oLanguageSettings->surveyls_dateformat,
            'startlanguage'               => Yii::app()->getLanguage(),
            'target'                      => Yii::app()->getConfig('uploaddir').DIRECTORY_SEPARATOR.'surveys'.DIRECTORY_SEPARATOR.$surveyId.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR,
            'tempdir'                     => Yii::app()->getConfig('tempdir').DIRECTORY_SEPARATOR,
            'timeadjust'                  => Yii::app()->getConfig("timeadjust"),
            'token'                       => $token,
        );
        LimeExpressionManager::StartSurvey($surveyId,$surveyMode,$aSurveyOtions,false);
        $this->surveyStarted = true;
        if(!empty($oResponse->submitdate)) {
            if($resetSubmit || $oSurvey->alloweditaftercompletion != 'Y') {
                $oResponse->submitdate = null;
                $oResponse->save();
            }
        }
        if(is_null($step)) {
            if ($oSurvey->format == 'A') { /* All in one */
                LimeExpressionManager::JumpTo(1, false, false, true);
            } elseif ($oSurvey->showwelcome != 'Y') { // !$oSurvey->getIsShowWelcome if LS >= 3
                $this->aMoveResult = LimeExpressionManager::NavigateForwards();
            }
            return;
        }
        if($step === true && empty($oResponse->submitdate)) {
            $step = $oResponse->lastpage; // +1 ?
        }

        LimeExpressionManager::JumpTo($step, false, false, true);
    }

}
