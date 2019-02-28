# reloadAnyResponse plugin for LimeSurvey. #

Allow to reload any response with options

## Installation

### Via GIT
- Go to your LimeSurvey Directory
- Clone in plugins/addScriptToQuestion directory : `git clone https://gitlab.com/SondagesPro/coreAndTools/reloadAnyResponse.git`

### Via ZIP dowload
- Get the file [reloadAnyResponse.zip](https://extensions.sondages.pro/IMG/auto/reloadAnyResponse.zip)
- Extract : `unzip reloadAnyResponse.zip`
- Move the directory to plugins/ directory inside LimeSurvey

## Usage and options

### Existing options

All of this options exist globally (at plugin settings page) and by survey.

- _Allow admin user to edit response with responseid_ : if an admin user load a survey with param srid : the reponse of the survey with this id are loaded and admin user can edit it.
- _Allow participant with token to create or reload responses_ : With not anonymous survey ; allow user to load specific response. Particullary interseting with answer persistance to off.
- _Create unique code automatically_ : unique code allow any user knowing this code (and the response id) to reload this response. 
- _Allow using unique code if exist_ : If unique code exist, allow any user knowing it to edit the answer. Parameter used for the access code is `code`. for example usage of link `1234?srid=1&code=accessode` try to load the response id 1 on survey 1234 with unique access code accessode.
- _Time for disable multiple access (in minutes)_ : since editing a response by 2 person at same time can replace current response done : a time for disabling mutiple access for all response done.

Only at global plugin settings

- _Replace edit response in browse response interface_ : when an admin user edit a response via admin gui default link : the plugin lauch the survey like it was seen publicly.
- _Delete the link of response when a response is deleted._ : Delete the link with the unique acces code of each response when this one was deleted. default to no, because new response got an new number when survey is activated agaon. Remind : if you use import/export as VV, a new response can replace an old one.
- _Delete the link of all responses of a survey when it's deactivated._ : Delete the existing link when a survey is deactivated. Default to no : this allow to import form an old database.
- _Delete the link of all responses of a survey when it's deleted_ : Delete whole response access code when a survey is deleted. Default to yes.

### Usage as tool for other plugins

The plugin add 2 models for managing existing access code and the multiple access time. You can easily know if plugin exist and is activated with `Yii::getPathOfAlias('reloadAnyResponse')`.

### responseLink model

- Create or find a reponse `$responseLink = \reloadAnyResponse\models\responseLink::setResponseLink($iSurvey,$iResponse,$sToken)`.
- Get a response url with the parameters : `$responseLink->getStartUrl($absolute = true);`

Sample usage
````
    // Get the response link with access code
    $responseLink = \reloadAnyResponse\models\responseLink::setResponseLink(1234,1);
    if($responseLink->hasErrors()) {
        throw new CHttpException(500, \CHtml::$responseLink->errorSummary($responseLink));
    }
    // redirect to this link
    Yii::app()->getController()->redirect($responseLink->getStartUrl());
````

### surveySession model

- save a session time : `\reloadAnyResponse\models\surveySession::saveSessionTime($iSurvey,$iResponse=null)`
- find a current response id is currently edited : `\reloadAnyResponse\models\surveySession::getIsUsed($iSurvey,$iResponse=null)`

If `$iResponse` is null : get the current response id from `$_SESSION`. The plugin use this model in beforeSurveyPage event if _Time for disable multiple access (in minutes)_ is not empty.

## Contribute and issue

Contribution are welcome, for patch and issue : use [gitlab]( https://gitlab.com/SondagesPro/coreAndTools/reloadAnyResponse).

## Home page & Copyright
- HomePage <https://extensions.sondages.pro/rubrique33>
- Copyright © 2018-2019 Denis Chenu <http://sondages.pro>
- Licence : GNU Affero General Public License <https://www.gnu.org/licenses/agpl-3.0.html>
