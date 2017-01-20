/**
 * This function is a callback for the Kaltura Contribution Wizard widget.
 * It gets called once a video has been uploaded/selected and the user has clicked
 * through all of the dialogs
 *  
 * A number of processes need to happen when a video has been selected/uploaded depending
 * on the plug-in that is being used (video assignment, presentation, resource).
 * 
 * @param object - some form of an entry id object
 */
function onContributionWizardAfterAddEntry(param) {

    var entry_id = (param[0].uniqueID == null ? param[0].entryId : param[0].uniqueID);
    
    if (null !== document.getElementById("entry_id")) {
        document.getElementById("entry_id").value = entry_id;
    }
    
    if (null !== document.getElementById("notification")) {
        document.getElementById("notification").innerHTML = "Video added";
    }
    
    // Check if the current page has the video resource instance form loaded
    if (null != document.getElementById("page-mod-kalvidres-mod")) {
        enable_video_res_buttons();

        // Get video thumbnailURL
        M.local_kaltura.get_thumbnail_url(entry_id);

    } else if (null != document.getElementById("page-mod-kalvidpres-mod")) {
        // Check if the current page has the video presentation instance form loaded

        // Get video thumbnailURL
        M.local_kaltura.get_thumbnail_url(entry_id);

    } else if (null != document.getElementById("page-mod-kalvidassign-view")) {
        // Check if the current page has the assignment submission form loaded

        // Get video thumbnailURL
        M.local_kaltura.get_thumbnail_url(entry_id);
        
        enable_video_assign_button();
        
    }



    // The code below is specific for the video presentation.
    // Check for the existence of the KSU uploader function 
    // and shift group of document related to line up with the transparent
    // object tag.
    if (typeof add_ksu_uploader == 'function') { 
        add_ksu_uploader(); 
        
        add_doc = document.getElementById("id_add_document");
        add_doc.disabled = false;

        add_doc.style.position = "relative";
        add_doc.style.top = "-5px";
        add_doc.style.right = "105px";
        add_doc.style.zIndex = "1";
        
        check_status = document.getElementById("id_check_doc_status");
        check_status.style.position = "relative";
        check_status.style.top = "-5px";
        check_status.style.right = "105px";
        check_status.style.zIndex = "1";
        
        uploader = document.getElementById("uploader");
        uploader.style.position = "relative";
        uploader.style.zIndex = "999";
        
        // Set video added flag
        document.getElementById("id_video_added").value = "1";
        
        // Set the video entry element
        document.getElementById("video_entry_id").value = entry_id;
        
        document.getElementById("id_submitbutton2").disabled = true;
        document.getElementById("id_submitbutton").disabled = true;
        
    }

}

/**
 * A callback that is called when the Kaltura contribution wizard is manuall closed
 *  or the user has completed the upload/video selection process 
 *  
 */
function onContributionWizardClose() {

    // Check for the notification element.  Then fire off a user event to
    // denote when the widget has closed itself to notify the main Javascript function
    // to close the panel object.
    if (null !== document.getElementById("notification")) {

        if ( document.createEvent ) {

            // Fire a click event for most browsers
            var evt = document.createEvent("MouseEvent");
            evt.initEvent("click", true, true);
            document.getElementById("notification").dispatchEvent(evt);

        } else if ( document.createEventObject ) {

            // Fire click event for IE5 - IE7
            var element = document.getElementById("notification");
            element.fireEvent("onclick");
        }

    }

    if (null !== document.getElementById("block_kaltura_notification")) {

        if ( document.createEvent ) {

            // Fire a click event for most browsers
            var evt = document.createEvent("MouseEvent");
            evt.initEvent("click", true, true);
            document.getElementById("block_kaltura_notification").dispatchEvent(evt);

        } else if ( document.createEventObject ) {

            // Fire click event for IE5 - IE7
            var element = document.getElementById("block_kaltura_notification");
            element.fireEvent("onclick");
        }
    }

    // unhide WYSIWYG iframe see KALDEV-105 for details
    if (null !== document.getElementById("id_introeditor_ifr")) {
        document.getElementById("id_introeditor_ifr").style.display = '';
    }

}

/**
 * Enable video properties and preview button once the video has been uploaded/selected.
 * 
 * This function also enables the two save buttons.
 */
function enable_video_res_buttons() {
    document.getElementById("id_video_properties").style.display = '';
    document.getElementById("id_video_preview").style.display = '';
    
    document.getElementById("id_submitbutton").disabled = '';
    document.getElementById("id_submitbutton2").disabled = '';
    
}

/**
 * Enable video assignment preview and submit buttons once a video has been uploade/selected.
 */
function enable_video_assign_button() {
    
    if (null != document.getElementById("preview_video")) {
        document.getElementById("preview_video").disabled = false;    
    }
    
    if (null != document.getElementById("submit_video")) {
        document.getElementById("submit_video").disabled = false;    
    }

}

/**
 * This function is fired when the user has selected a document in the 
 * file picker widget
 */
function user_selected() {
	document.getElementById("progress_gif").style.display = "inline";
    document.getElementById("progress_gif").style.visibility = "visible";
    document.getElementById("uploader").upload();
    
}

function uploading() {
   
}

/**
 * This function is fired when the document has been uploaded and fires off the 
 * addEntries event
 */
function uploaded() {
    document.getElementById("uploader").addEntries();
}

/**
 * This function is fired when an entry has been added to the Kaltura server.
 * 
 * Makes an asynchronous call to convert the document into a SWF document
 * 
 * @param obj
 */
function entries_added(obj) {

    var txt_document = "Document is currently being converted." +
                       " Click on 'Check status' periodically.  " +
                       "The save buttons will be disabled until the document has finished converting.";
    
    var txt_error_document = "There was an error with the file submission. " +
                             "Please re-add this activity. If this error persists, " +
                             "contact your site administrator.";

    
    var doc_entry_id     = document.getElementById("doc_entry_id");
    var ppt_download_url = document.getElementById("id_ppt_dnld_url");
    var wwwroot          = document.getElementById("wwwroot");
    
    // Save the object
    var myobj = obj[0];
    
    // the entryId property is missing then log an error to the browser console
    if (null !== myobj.entryId) {
        doc_entry_id.value = myobj.entryId;

    } else {
        console.log(obj);
    }

    // Make a async call to convert_document to begin the process of converting the document
    // to a SWF doc
    $.ajax({
        type: "POST",
        url: "../local/kaltura/convert_document.php",
        data: "kaction=ppt&ppt=" + doc_entry_id.value,
        success: function(url) {

            if( url.substring(0,2) == "y:") {

                ppt_download_url.value = url.substring(2);

                check_status = document.getElementById("id_check_doc_status");
                check_status.disabled = false;
                
                document.getElementById("document_thumbnail_container").innerHTML = txt_document;


            } else {

                document.getElementById("document_thumbnail_container").innerHTML = txt_error_document;
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.log(xhr);
        }
    });

    document.getElementById("uploader").removeFiles(0,0);

    //M.local_kaltura.hide_loading();
    document.getElementById("progress_gif").style.visibility = "hidden";
    document.getElementById("progress_gif").style.display = "none";
    
    
}

//This line must be before add_swf_uploader() because it contains all the handler information
var delegate = { selectHandler: user_selected,
               progressHandler: uploading,
               allUploadsCompleteHandler: uploaded,
               entriesAddedHandler: entries_added
               };