M.local_mymedia = {};

M.local_mymedia.init_config = function (Y, panel_markup, dialog, conversion_script, save_video_script,
                                        uiconf_id, kcw_panel_markup, kcw_markup, loading_panel, edit_meta, 
                                        edit_share_course, edit_share_site, kaltura_partner_id, kaltura_session) {
    
    var body_node = Y.one("#page-mymedia-index");
    
    body_node.append(dialog);
    body_node.append(kcw_panel_markup);
    
    var kcw_panel = Y.one("#upload_btn");
    
    var dialog = new Y.YUI2.widget.SimpleDialog("mymedia_simple_dialog", {
        width: "20em",
        effect:{
            effect: Y.YUI2.widget.ContainerEffect.FADE,
            duration: 0.30
        }, 
        fixedcenter: true,
        modal: true,
        constraintoviewport: true,
        visible: false,
        draggable: true,
        iframe: true,
        close: false,
        context: ["region-main", "tl", "tl", ["beforeShow", "windowResize"], [250, 20]]
    });
    
    dialog.render("page-mymedia-index");

    if (null !== kcw_panel) {

        // Create panel to hold KCW
        var widget_panel = new Y.YUI2.widget.Panel("kcw_panel",   { width: "800px",
                                                                   height: "470px",
                                                                   fixedcenter: false,
                                                                   constraintoviewport: true,
                                                                   dragable: false,
                                                                   visible: false,
                                                                   close: false,
                                                                   modal: true,
                                                                   zIndex: 100,
                                                                   context: ["region-main", "tl", "tl", ["beforeShow", "windowResize"], [80, 50]]
                                                                   });
        widget_panel.render();
    
        // Panel show callback.  Add CSS styles to the main div container
        // to rais it above the rest of the elments on the page
        function widget_panel_callback(e, widget_panel) {
            widget_panel.setBody(kcw_markup);
            widget_panel.show();
            
        }

        kcw_panel.on("click", widget_panel_callback, null, widget_panel);
    
        // Add a click event handler to the notifications DIV
        // This is used to close the panel window when the user clicks
        // on the X in the KCW widget
        var kcw_notification = Y.one("#notification");
        
        // Close wiget panel callback
        function kcw_notification_click(e) {
    
            widget_panel.hide();
    
            var text = document.getElementById("notification").innerHTML;
    
            if ('' !== text) {
    
                dialog.setHeader(M.util.get_string("upload_success_hdr", "local_mymedia"));
                dialog.cfg.setProperty("icon", Y.YUI2.widget.SimpleDialog.ICON_INFO);
                dialog.cfg.setProperty("text", M.util.get_string("upload_success", "local_mymedia"));
                var button = [
                                 { text: M.util.get_string("continue", "local_mymedia"),
                                   handler: function close_dialog() {  this.hide(); window.location.href = window.location.href; },
                                   isDefault: true
                                 }
                             ];
                dialog.cfg.setProperty("buttons", button);
                document.getElementById("notification").innerHTML = '';
                dialog.show();
            }
    
        }
    
        // Subscribe to the on click event to close the widget_panel
        kcw_notification.on("click", kcw_notification_click);
        
    }


    
    
    if (null == Y.one("#mymedia_vidoes")) {
        return '';
    } 

    body_node.append(panel_markup);
    body_node.append(loading_panel);

    // Create Loading panel
    var loading_panel =  new Y.YUI2.widget.Panel("wait", { width:"240px",
                                                          fixedcenter:true,  
                                                          close:false,  
                                                          draggable:false,  
                                                          zIndex:100, 
                                                          modal:true, 
                                                          visible:false 
                                                         }  
                                               ); 

    loading_panel.setHeader("Loading, please wait..."); 
    loading_panel.setBody('<img src="http://l.yimg.com/a/i/us/per/gr/gp/rel_interstitial_loading.gif" />');
    loading_panel.render();

    // Create preview panel
    var details_panel  = new Y.YUI2.widget.Panel("id_video_details",
                                    { width: "550px",
                                      height: "550px",
                                      fixedcenter: false,
                                      constraintoviewport: true,
                                      dragable: false,
                                      visible: false,
                                      close: true,
                                      modal: true,
                                      zIndex: 50,
                                      context: ["region-main", "tl", "tl", ["beforeShow", "windowResize"]]
                                    });

    details_panel.render();

    // Create the tab view 
    var tab_view = new Y.TabView({srcNode:'#id_video_details_tab',
                                  visible: false,
                                  width: "500px",
                                  height: "480px"});

    tab_view.render();

    // Subscribe to the hideEvent for the panel so that the flash player 
    // will be removed when the panel is closed
    details_panel.hideEvent.subscribe(function() {

        // Clear the enbedded player
        tab_view.item(0).set('content', '');
        
        // Clear the metadata
        var metadata = Y.one('#metadata_video_name');
        metadata.set('value', '');

        var metadata = Y.one('#metadata_video_tags');
        metadata.set('value', '');

        var metadata = Y.one('#metadata_video_desc');
        metadata.set('value', '');
        
        // Clear all of the shared courses information
        Y.all('input[type=checkbox]').each(function (input_node) {
            
            if (input_node.get('checked')) {
                input_node.set('checked', false);
            }
        });
        
        window.location.href = window.location.href;
    });


    var check_conversion_status = { 
        complete: function check_conversion_status (id, o) {

            // if the response text is empty then the video must still be converting
            if ('' == o.responseText) {
                tab_view.item(0).set('content', M.util.get_string("video_converting", "local_mymedia"));
            } else {

                // Parse the response text
                var data = Y.JSON.parse(o.responseText);

                // Set the video preview tab content to the embed markup
                if (undefined !== data.markup) {
                
                    // Set tab content
                    tab_view.item(0).set('content', '<center>' + data.markup + '</center>');

                    // Set Metadata content
                    var metadata_name = Y.one('#metadata_video_name');
                    metadata_name.set('value', data.name);
            
                    var metadata_tags = Y.one('#metadata_video_tags');
                    metadata_tags.set('value', data.tags);
            
                    var metadata_desc = Y.one('#metadata_video_desc');
                    metadata_desc.set('value', data.description);
    
                    if (undefined !== data.script) {
                        eval(data.script);
                    }

                    // Disable checkboxes depending on the user's capability
                    var share_checkboxes = Y.all('input[type=checkbox]');

                    if (1 == edit_share_site && 1 == edit_share_course) {
                        share_checkboxes.each(function (input_node) {
                            
                            input_node.set('disabled', false);
                        });
                    } else if (1 == edit_share_site) {
                        
                        // Enable site share box
                        share_checkboxes.filter('#site_share').set('disabled', false);
                        
                        // Disable the course share boxes
                        share_checkboxes.filter('input[name=enrolled_courses]').each(function (input_node) {
                            
                            input_node.set('disabled', true);
                        });
                        
                        share_checkboxes.filter('#check_all').set('disabled', true);
                        
                    } else if (1 == edit_share_course) {

                        // Enable the course share boxes
                        share_checkboxes.filter('input[name=enrolled_courses]').each(function (input_node) {
                            
                            input_node.set('disabled', false);
                        });
                        
                        share_checkboxes.filter('#check_all').set('disabled', false);
                        
                        //Disable the site share box
                        share_checkboxes.filter('#site_share').set('disabled', true);

                    } else {
                    
                        // Disable all checkboxes
                        share_checkboxes.filter('input[type=checkbox]').each(function (input_node) {
                            
                            input_node.set('disabled', true);
                        });
                        
                    }

                    // Disable edit tab if the user doesn't have the capability
                    if (1 != edit_meta) {
                        metadata_name.set('disabled', true);
                        metadata_tags.set('disabled', true);
                        metadata_desc.set('disabled', true);
                    }

                    var i            = 0;
                    var course_share = data.course_share.split(",");

                    for (i = 0; i < course_share.length; i++) {

                        if ('' != course_share[i]) {
                            share_checkbox_node = share_checkboxes.filter('input[value="' + course_share[i] + '"]');
                            share_checkbox_node.set('checked', true);
                            
                        }
                    }

                    // Check if the number of check courses is equal to the number of checkboxes
                    // If so, the pre-check the check all checkbox
                    if (course_share.length == share_checkboxes.filter('input[name=enrolled_courses]').size() &&
                        '' != course_share[0]) {
                        share_checkboxes.filter('input[name=check_all_courses]').set('checked', true);
                    }
                    
                    // Pre-check site share checkbox
                    if ("1" == data.site_share) {
                        share_checkboxes.filter('input[name=site_share]').set('checked', true);
                    }

                    // Lastly if they have no capabilities then disable the save button
                    if (1 != edit_meta && 1 != edit_share_site && 1 != edit_share_course) {
                        Y.one('#id_video_details_save').set('disabled', true);
                    } else {
                        Y.one('#id_video_details_save').set('disabled', false);
                    }

                }
            }

            loading_panel.hide();
        }
    }


    // Set configuration object for KDP asynchronous call
    var preview_cfg = {
            on: {
                complete: check_conversion_status.complete
            },
            context: check_conversion_status
    };


    var save_video_information = { 
        complete: function save_video_information (id, o) {
            
            var return_value = o.responseText.split(" ");
            
            if ('y' != return_value[0]) {

                dialog.setHeader(M.util.get_string("failure_saved_hdr", "local_mymedia"));
                dialog.cfg.setProperty("icon", Y.YUI2.widget.SimpleDialog.ICON_WARN);
                
                switch (return_value[1]) {
                    case "1":
                    case "3":
                    case "4":
                    case "5":
                    case "6":
                    case "8":
                    case "9":
                    case "10":
                        dialog.cfg.setProperty("text", M.util.get_string("error_saving", "local_mymedia") + " ERROR " + return_value[1]);
                        break;
                    case "2":
                        dialog.cfg.setProperty("text", M.util.get_string("missing_required", "local_mymedia"));
                        break;
                    case "7":
                        dialog.cfg.setProperty("text", M.util.get_string("error_not_owner", "local_mymedia"));
                        break;
                    default:
                        dialog.cfg.setProperty("text", M.util.get_string("error_saving", "local_mymedia"));
                        break;
                }
            } else {
            
                dialog.setHeader(M.util.get_string("success_saving_hdr", "local_mymedia"));
                dialog.cfg.setProperty("icon", Y.YUI2.widget.SimpleDialog.ICON_INFO);
                dialog.cfg.setProperty("text", M.util.get_string("success_saving", "local_mymedia"));
            }
            
            // Add okay button to dialog
            var button = [
                          { text: M.util.get_string("continue", "local_mymedia"),
                            handler: function close_dialog() { details_panel.hide(); this.hide(); },
                            isDefault: true
                          }
                      ];
            
            dialog.cfg.setProperty("buttons", button);
         
            // Re-enable the save button
            Y.one('#id_video_details_save').set('disabled', false);
            
            loading_panel.hide();                        
            dialog.show();
        }
    };

    // Set configuration object for saving asynchronous call
    var save_cfg = {
            on: {
                complete: save_video_information.complete
            },
            context: save_video_information
    };

    // Subscribe to the save button click
    var save_button = Y.one('#id_video_details_save');
    
    save_button.on('click', function(e) {
        
        // Disable the save button until the asynchronous calls returns
        e.target.set('disabled', true);

        loading_panel.show();
        
        // Save all of the metadata items
        var entry_id = Y.one('#metadata_entry_id').get('value');
        var name     = Y.one('#metadata_video_name').get('value');
        var tags     = Y.one('#metadata_video_tags').get('value');
        var desc     = Y.one('#metadata_video_desc').get('value');
        
        // Save all of the checkbox (course share) information
        var checked_courses = '';

        Y.all('input[name=enrolled_courses]').each(function (input_node) {
            if (input_node.get('checked')) {

                // Create a comma separated list of checked checkboxes
                checked_courses = checked_courses + input_node.get('value') + ',';
            }
        });
        
        // Remove the trailing comma from the string
        checked_courses = checked_courses.substr(0, checked_courses.length - 1);
        
        // Save the site share checkbox value
        var site_share = Y.one('input[name=site_share]:checked');
        
        if (null == site_share) {
            site_share = 0;
        } else {
            site_share = 1;
        }
        
        var url = encodeURI(save_video_script +
                            entry_id +
                            "&name=" + name +
                            "&tags=" + tags +
                            "&desc=" + desc +
                            "&gshare=" + site_share +
                            "&share=" + checked_courses
                           )
        Y.io(url, save_cfg);
    });


    // Get the course list table element
    var course_list = Y.one('#mymedia_course_list');
    
    course_list.delegate('click', function(e) {
        
        var checked_value = e.target.get('checked') || undefined;
        
        Y.one('#check_all').set('checked', false);
    
    }, 'input[name=enrolled_courses]');

    course_list.delegate('click', function(e) {
        
        var checked_value = e.target.get('checked') || undefined;

        // Set all the course check boxes to checked
        Y.all('input[name=enrolled_courses]').each(function (input_node) {
        
            input_node.set('checked', checked_value);
            
         });
                
    
    }, 'input[name=check_all_courses]');

    // Get the table element
    var video_list = Y.one('#mymedia_vidoes');
    
    // Create event delegation
    video_list.delegate('click', function(e) {

        //alert(this.ancestor('div.mymedia.video.entry').getAttribute('id'));
        //alert(this.getAttribute('class'));

        if ('mymedia video delete' == this.getAttribute('class')) {
            // Do nothing
            return '';
        }
        
        e.preventDefault();

        var entry_id = this.ancestor('div.mymedia.video.entry').getAttribute('id');        
        
        // Disable the submit button while the asynchronous call is being processed
        Y.one('#id_video_details_save').set('disabled', true);
        
        Y.io(conversion_script + entry_id + "&" +
             "width=400&" +
             "height=400&" +
             "uiconf_id=" + uiconf_id, preview_cfg);
        
        // Display the panel and display the tab
        details_panel.show();
        tab_view.show();
        
        // Display loading panel
        loading_panel.show();

        var metadata = Y.one('#metadata_entry_id');
        metadata.set('value', entry_id);
        
        // Disable the sharing checkboxes

        // Retrieve the class of the element that was clicked
        var button_class = this.getAttribute('class') 

        // Check which element was specifically clicked and select a default tab to open
        if (-1 != button_class.search("preview")) {
            tab_view.selectChild(0);
            
        } else if (-1 != button_class.search("edit")) {
            tab_view.selectChild(1);
            
        } else if (-1 != button_class.search("share")) {
            tab_view.selectChild(2);
            
        }

    }, 'a');

    var mymediasort = Y.one("#mymediasort");
    var mymediasortoptions = mymediasort.get("options");
    Y.on("change", function () {
        var index = mymediasort.get("selectedIndex");
        var url = mymediasortoptions.item(index).get("value");
        window.location.href = url;
     }, mymediasort);
    
};
