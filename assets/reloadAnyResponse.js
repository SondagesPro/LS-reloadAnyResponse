/**
 * Javascript for reload any respopnse
 * @author Denis Chenu <https://sondages.pro
 * @license magnet:?xt=urn:btih:c80d50af7d3db9be66a4d0a86db0286e4fd33292&dn=bsd-3-clause.txt BSD 3 Clause
 */

var ReloadAnyResponse = {
    surveyTimeOut : 0,
    surveyTimeOutAlert : 0,
    idleTime : 0,
    init : function (options) {
        this.surveyTimeOut = options.multiAccessTimeOut;
        this.surveyTimeOutAlert = options.multiAccessTimeAlert;
        var context = this;
        $(function() {
            var ReloadAnyResponseIdleInterval = window.setInterval(function() {
                    context.timerIncrement(); 
                }, 60 * 100); // One minute 60 * 1000
        });
        this.AccessTimeOptOutReset();
    },
    AccessTimeOptOut: function() {

    },
    AccessTimeOptOutReset: function() {
        var context = this;
        $(document).on('click keypress mousemove scroll',function(){
            context.idleTime = 0;
            $("[data-reloadany-timecounter]").text(ReloadAnyResponse.surveyTimeOut);
            $("#modal-reloadany-timer").modal('hide');
        });
    },
    timerIncrement: function() {
        if($("button[value='movenext'],button[value='movesubmit']").length == 0) {
            this.idleTime = 0;
            return;
        }
        this.idleTime = this.idleTime + 1;
        console.warn([
            ReloadAnyResponse.idleTime,
            ReloadAnyResponse.surveyTimeOutAlert,
            ReloadAnyResponse.surveyTimeOut
        ]);
        $("[data-reloadany-timecounter]").text( this.surveyTimeOut - this.idleTime );
        if(this.surveyTimeOutAlert && this.surveyTimeOutAlert <= this.idleTime) {
            $("#modal-reloadany-timer").modal('show');
        }
        if(this.surveyTimeOut && this.surveyTimeOut <= this.idleTime) {
            $("form#limesurvey").append("<input type='hidden' name='autosave' value='1'>");
            $("form#limesurvey").append("<button type='submit' name='saveall' value='saveall' class='hidden' data-disable-check-validity=1>");
            $("form#limesurvey [name='saveall']").last().click();
        }
    }
}

/**


function timerIncrement() {
    idleTime = idleTime + 1;
    $("#timer-minutes").text( window.oecdVar.surveyTimeOut - idleTime );
    if(window.oecdVar.surveyTimeOutAlert!=="") {
        if(window.oecdVar.surveyTimeOutAlert <= idleTime) {
            $("#modal-timer").modal('show');
        } else {
            //$("#modal-timer").modal('hide');
        }
    }

}
**/
