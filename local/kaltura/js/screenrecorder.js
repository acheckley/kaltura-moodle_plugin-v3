// callback function that will be called by library before loading the widget
// this function removes description and tags from UI
function removeDescAndTags(objOptions) {
    console.log("object before change");
    console.log(objOptions);
    objOptions['kaltura.submit.description.enabled'] = false;
    objOptions['kaltura.submit.tags.enabled'] = false;
    console.log("object after change");
    console.log(objOptions);
    return objOptions;
}

// setting callback to override some kaltura options
kalturaScreenRecord.setModifyKalturaOptionsCallback(removeDescAndTags);

kalturaScreenRecord.UploadCompleteCallBack = function(entryId) {
    var data      = new Array();
    var media_obj = new Object();
    
    media_obj.entryId  = entryId;
    media_obj.uniqueID = null;
    
    data[0] = media_obj 
     
    onContributionWizardAfterAddEntry(data);
    
    //alert("Kaltura KSR uploadCompleteCallBack: created entry with ID ["+entryId+"]");
}

kalturaScreenRecord.downloadCallBack = function(percent) {

    var progress_bar_container = document.getElementById('progress_bar_container');
    var progress_bar = document.getElementById('progress_bar');
    var slider_border = document.getElementById('slider_border');
    var loadingtext = document.getElementById('loading_text');

    if (100 != parseInt(percent)) {

        loadingtext.innerHTML = kalturaScreenRecord.detectTexts.loadingwait;
        slider_border.style.border = "1px solid #000000";
        progress_bar_container.style.visibility = 'visible';
        
        if ('1px solid #000000' != progress_bar.style.borderRight) {
            progress_bar.style.borderRight = '1px solid #000000';
        }
        
        progress_bar.style.width = percent + '%';
    
    } else {

        progress_bar.style.width = '0%'; 
        progress_bar_container.style.visibility = 'hidden';
        slider_border.style.border = "1px solid #FFFFFF";

    }
}

/**
 * set the ID of a DOM element in your page where the error message would appear if java is not detected.
 * It's innerHTML will be set to the error message.
 * The error messages can be defined using the setDetectText* functions, or simply use the default.
 * If this is not defined and callback is not defined - error will be written to console.log
 */
kalturaScreenRecord.setDetectResultErrorMessageElementId = function(id) {
    this.detectResultError.errorMessageDomId = id /*screenrecorder_btn_container*/;
    
}

/**
 * set the text that would appear/returned if detected that java is disabled in browser
 */
kalturaScreenRecord.setDetectTextJavaDisabled = function(txt) {
    this.detectTexts.javaDisabled = txt;
    //document.getElementById('progress_bar_container').style.visibility = 'hidden';
}

/**
 * set the text that would appear/returned if detected Mac Lion which requires java to be installed
 */
kalturaScreenRecord.setDetectTextmacLionNeedsInstall = function(txt) {
    this.detectTexts.macLionNeedsInstall = txt;
    //document.getElementById('progress_bar_container').style.visibility = 'hidden';
}

/**
 * set the text that would appear/returned if no java was detected
 */
kalturaScreenRecord.setDetectTextjavaNotDetected = function(txt) {
    this.detectTexts.javaNotDetected = txt;
    //document.getElementById('progress_bar_container').style.visibility = 'hidden';
}

/**
 * set a custom callback function name to be called if detect could not find java.
 * If defined, that function will be called and other functionality will not happen (display of error message).
 * That function should expect a single string parameter with the keyword-description of the error.
 * Available keywords: javaDisabled, macLionNeedsInstall, javaNotDetected
 */
kalturaScreenRecord.setDetectResultErrorCustomCallback = function(funcName) {this.detectResultError.customCallback = funcName;}

/**
 * Callback function which gets triggered by screen recorder.
 */
kalturaScreenRecord.startCallBack = function (result) {
    var detection_in_progress = false;
    var detection_process;

    console.log("Kaltura KSR startCallBack: called " + result + ".");

    if (result.toLowerCase() != 'success') {
        console.log("Kaltura KSR startCallBack: failed to load widget.");
        kalturaScreenRecord.displayDetectError();
    } else {
        var progress_bar_container = document.getElementById('progress_bar_container');
        progress_bar_container.style.visibility = 'hidden';
    }
    clearTimeout(detection_process);
}

/**
 * Clear detection flag and display error.
 */
kalturaScreenRecord.clearDetectionFlagAndDisplayError = function() {
    console.log('Clearing detection flag - either failed detection or widget started');
    if (kalturaScreenRecord.startCallBack.detection_in_progress) {
        kalturaScreenRecord.displayDetectError();
        kalturaScreenRecord.startCallBack.detection_in_progress = false;
    }
}

/**
 * Display Java disabled error message.
 */
kalturaScreenRecord.displayDetectError = function() {
    document.getElementById('loading_text').innerHTML = kalturaScreenRecord.detectTexts.javaDisabled;
}