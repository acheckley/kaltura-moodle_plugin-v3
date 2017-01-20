M.local_kaltura = {};

M.local_kaltura.loading_panel = {};

M.local_kaltura.show_loading = function () {
    loading_panel = new Y.YUI2.widget.Panel("wait", {width:"240px",
                                                    fixedcenter:true,
                                                    close:false,
                                                    draggable:false,
                                                    zindex:4,
                                                    modal:true,
                                                    visible:false
                                                   });

    loading_panel.setHeader("Loading, please wait..."); 
    loading_panel.setBody('<img src="http://l.yimg.com/a/i/us/per/gr/gp/rel_interstitial_loading.gif" />');
    loading_panel.render();

    loading_panel.show();
}

M.local_kaltura.hide_loading = function () {
    loading_panel.hide();
};

M.local_kaltura.dataroot = {};

M.local_kaltura.set_dataroot = function(web_location) {
    dataroot = web_location;
};

M.local_kaltura.get_thumbnail_url = function(entry_id) {
    
    YUI().use("io-base", "json-parse", "node", function (Y) {
        var location = dataroot + entry_id;

        Y.io(location);
        
        function check_conversion_status(id, o) {
            if ('' != o.responseText) {
                
                var data = Y.JSON.parse(o.responseText);
                
                img_tag = Y.one("#video_thumbnail");
                
                if (data.thumbnailUrl != img_tag.get("src")) {
                    img_tag.set("src", data.thumbnailUrl);
                    img_tag.set("alt", data.name);
                    img_tag.set("title", data.name);
                }
    
            }
        }
        
    
        Y.on('io:complete', check_conversion_status, Y);

    });
    

};

/*
 * Perform course searching with auto-complete
 */
M.local_kaltura.search_course = function() {

    YUI({filter: 'raw'}).use("autocomplete", function(Y) {
        var search_txt = Y.one('#kaltura_search_txt');
        var kaltura_search = document.getElementById("kaltura_search_txt");
        var search_btn = Y.one('#kaltura_search_btn');
        var clear_btn = Y.one('#kaltura_clear_btn');

        search_txt.plug(Y.Plugin.AutoComplete, {
            resultTextLocator: 'fullname',
            enableCache: false,
            minQueryLength: 2,
            resultListLocator: 'data.courses',
            resultFormatter: function (query, results) {
                return Y.Array.map(results, function(result) {
                    var course = result.raw;
                    if (course.shortname) {
                        return course.fullname + " (" + course.shortname + ")";
                    }
                    return course.fullname;
                });
            },
            source: 'courses.php?query={query}&action=autocomplete',
            on : {
                select : function(e) {
                    Y.io('courses.php', {
                        method: 'POST',
                        data: {course_id : e.result.raw.id, action: 'select_course'},
                        on: {
                            success: function(id, result) {
                                var data = Y.JSON.parse(result.responseText);
                                if (data.failure && data.failure == true) {
                                    alert(data.message);
                                } else {
                                    document.getElementById('resourceobject').src = decodeURIComponent(data.url);
                                }
                            }
                        }
                    });
                }
            }
        });

        kaltura_search.onkeypress = function(e) {
            // Enter is pressed
            if (e.keyCode === 13) {
                var query = search_txt.get('value');
                // Don't accept an empty search string
                if (!(/^\s*$/.test(query))) {
                    document.getElementById('resourceobject').src = 'courses.php?action=search&query='+query;
                    // Lose focus of the auto-suggest menu
                    kaltura_search.blur();
                }
            }
        }

        search_btn.on('click', function(e) {
            var query = search_txt.get('value');
            // Don't accept an empty search string
            if (!(/^\s*$/.test(query))) {
                document.getElementById('resourceobject').src = 'courses.php?action=search&query='+query;
                kaltura_search.blur();
            }
        });

        clear_btn.on('click', function(e) {
            search_txt.set("value", "");
        });

    });

};

M.local_kaltura.init_config = function (Y, test_script) {

    // Check for an instance of the Kaltura connection type element
    if (Y.DOM.byId("id_s_local_kaltura_conn_server")) {

        // Retrieve the connection type Node
        var connection_type = Y.one('#id_s_local_kaltura_conn_server');

        // Check for the selected option
        connection_type_dom = Y.Node.getDOMNode(connection_type);
        
        
        // Check if the first option is selected
        if (0 == connection_type_dom.selectedIndex) {

            // Disable the URI setting 
            Y.DOM.byId("id_s_local_kaltura_uri").disabled = true;
        } else {
            // Enable the URI setting
            Y.DOM.byId("id_s_local_kaltura_uri").disabled = false;
        }
        
        
        // Add 'change' event to the connection type selection drop down
        connection_type.on('change', function (e) {

            var connection_uri = Y.DOM.byId("id_s_local_kaltura_uri");
            
            if (connection_uri.disabled) {
                connection_uri.disabled = false;
            } else {
                connection_uri.disabled = true;
            }
            
        });
        
        
        // Add a 'change' event to the Kaltura player selection drop down
        var kaltura_player = Y.one('#id_s_local_kaltura_player');

        // Check for the selected option
        var kaltura_player_dom = Y.Node.getDOMNode(kaltura_player);
        
        var length = kaltura_player_dom.length -1;

        if (length == kaltura_player_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_player_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_player_custom').disabled = true;
        }
        
        kaltura_player.on('change', function (e) {

            var kaltura_custom_player = Y.DOM.byId("id_s_local_kaltura_player_custom");

            var kaltura_player_dom = Y.Node.getDOMNode(e.target);

            var length = kaltura_player_dom.length - 1;

            if (length == kaltura_player_dom.selectedIndex) {
                kaltura_custom_player.disabled = false;
            } else {
                kaltura_custom_player.disabled = true;
            }
        
        }); 

        // Add a 'change' event to the Video Assignment KCW selection drop down
        var assign_uploader = Y.one('#id_s_local_kaltura_assign_uploader');

        // Check for the selected option
        var assign_uploader_dom = Y.Node.getDOMNode(assign_uploader);
        
        var length = assign_uploader_dom.length - 1;

        if (length == assign_uploader_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_assign_uploader_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_assign_uploader_custom').disabled = true;
        }
        
        assign_uploader.on('change', function (e) {

            var assign_custom_uploader = Y.DOM.byId("id_s_local_kaltura_assign_uploader_custom");

            var assign_uploader_dom = Y.Node.getDOMNode(e.target);

            var length = assign_uploader_dom.length - 1;

            if (length == assign_uploader_dom.selectedIndex) {
                assign_custom_uploader.disabled = false;
            } else {
                assign_custom_uploader.disabled = true;
            }
        
        }); 

        // Add a 'change' event to the Kaltura resource player selection drop down
        var kaltura_player_resource = Y.one('#id_s_local_kaltura_player_resource');

        // Check for the selected option
        var kaltura_player_resource_dom = Y.Node.getDOMNode(kaltura_player_resource);
        
        var length = kaltura_player_resource_dom.length - 1;

        if (length == kaltura_player_resource_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_player_resource_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_player_resource_custom').disabled = true;
        }
        
        kaltura_player_resource.on('change', function (e) {

            var kaltura_custom_player_resource = Y.DOM.byId("id_s_local_kaltura_player_resource_custom");

            var kaltura_player_resource_dom = Y.Node.getDOMNode(e.target);

            var length = kaltura_player_resource_dom.length - 1;

            if (length == kaltura_player_resource_dom.selectedIndex) {
                kaltura_custom_player_resource.disabled = false;
            } else {
                kaltura_custom_player_resource.disabled = true;
            }
        
        }); 

        // Add a 'change' event to the Video resource KCW selection drop down
        var res_uploader = Y.one('#id_s_local_kaltura_res_uploader');

        // Check for the selected option
        var res_uploader_dom = Y.Node.getDOMNode(res_uploader);
        
        var length = res_uploader_dom.length - 1;

        if (length == res_uploader_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_res_uploader_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_res_uploader_custom').disabled = true;
        }
        
        res_uploader.on('change', function (e) {

            var res_custom_uploader = Y.DOM.byId("id_s_local_kaltura_res_uploader_custom");

            var res_uploader_dom = Y.Node.getDOMNode(e.target);

            var length = res_uploader_dom.length - 1;

            if (length == res_uploader_dom.selectedIndex) {
                res_custom_uploader.disabled = false;
            } else {
                res_custom_uploader.disabled = true;
            }
        
        }); 

        
        // Add a 'change' event to the Kaltura presentation video selection drop down
        var kaltura_presentation = Y.one('#id_s_local_kaltura_presentation');

        // Check for the selected option
        var kaltura_presentation_dom = Y.Node.getDOMNode(kaltura_presentation);
        
        var length = kaltura_presentation_dom.length - 1;

        if (length == kaltura_presentation_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_presentation_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_presentation_custom').disabled = true;
        }
        
        kaltura_presentation.on('change', function (e) {

            var kaltura_presentation_uploader = Y.DOM.byId("id_s_local_kaltura_presentation_custom");

            var kaltura_presentation_dom = Y.Node.getDOMNode(e.target);

            var length = kaltura_presentation_dom.length - 1;

            if (length == kaltura_presentation_dom.selectedIndex) {
                kaltura_presentation_uploader.disabled = false;
            } else {
                kaltura_presentation_uploader.disabled = true;
            }
        
        });
        
        // Add a 'change' event to the Video Presentation KCW selection drop down
        var pres_uploader = Y.one('#id_s_local_kaltura_pres_uploader');

        // Check for the selected option
        var pres_uploader_dom = Y.Node.getDOMNode(pres_uploader);
        
        var length = pres_uploader_dom.length - 1;

        if (length == pres_uploader_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_pres_uploader_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_pres_uploader_custom').disabled = true;
        }
        
        pres_uploader.on('change', function (e) {

            var pres_custom_uploader = Y.DOM.byId("id_s_local_kaltura_pres_uploader_custom");

            var pres_uploader_dom = Y.Node.getDOMNode(e.target);

            var length = pres_uploader_dom.length - 1;

            if (length == pres_uploader_dom.selectedIndex) {
                pres_custom_uploader.disabled = false;
            } else {
                pres_custom_uploader.disabled = true;
            }
        
        }); 

        // Add a 'change' event to the Video Presentation KSU selection drop down
        var pres_ksu = Y.one('#id_s_local_kaltura_simple_uploader');

        // Check for the selected option
        var pres_ksu_dom = Y.Node.getDOMNode(pres_ksu);
        
        var length = pres_ksu_dom.length - 1;

        if (length == pres_ksu_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_simple_uploader_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_simple_uploader_custom').disabled = true;
        }
        
        pres_ksu.on('change', function (e) {

            var pres_custom_ksu = Y.DOM.byId("id_s_local_kaltura_simple_uploader_custom");

            var pres_ksu_dom = Y.Node.getDOMNode(e.target);

            var length = pres_ksu_dom.length - 1;

            if (length == pres_ksu_dom.selectedIndex) {
                pres_custom_ksu.disabled = false;
            } else {
                pres_custom_ksu.disabled = true;
            }
        
        }); 

        // Add a 'change' event to the My Media KCW selection drop down
        var mymedia_uploader = Y.one('#id_s_local_kaltura_mymedia_uploader');

        // Check for the selected option
        var mymedia_uploader_dom = Y.Node.getDOMNode(mymedia_uploader);
        
        var length = mymedia_uploader_dom.length - 1;

        if (length == mymedia_uploader_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_mymedia_uploader_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_mymedia_uploader_custom').disabled = true;
        }
        
        mymedia_uploader.on('change', function (e) {

            var mymedia_custom_uploader = Y.DOM.byId("id_s_local_kaltura_mymedia_uploader_custom");

            var mymedia_uploader_dom = Y.Node.getDOMNode(e.target);

            var length = mymedia_uploader_dom.length - 1;

            if (length == mymedia_uploader_dom.selectedIndex) {
                mymedia_custom_uploader.disabled = false;
            } else {
                mymedia_custom_uploader.disabled = true;
            }
        
        }); 

        // Add a 'change' event to the My Media KSR selection drop down
        var mymedia_ksr = Y.one('#id_s_local_kaltura_mymedia_screen_recorder');

        // Check for the selected option
        var mymedia_ksr_dom = Y.Node.getDOMNode(mymedia_ksr);
        
        var length = mymedia_ksr_dom.length - 1;

        if (length == mymedia_ksr_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_mymedia_screen_recorder_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_mymedia_screen_recorder_custom').disabled = true;
        }
        
        mymedia_ksr.on('change', function (e) {

            var mymedia_custom_ksr = Y.DOM.byId("id_s_local_kaltura_mymedia_screen_recorder_custom");

            var mymedia_ksr_dom = Y.Node.getDOMNode(e.target);

            var length = mymedia_ksr_dom.length - 1;

            if (length == mymedia_ksr_dom.selectedIndex) {
                mymedia_custom_ksr.disabled = false;
            } else {
                mymedia_custom_ksr.disabled = true;
            }
        
        }); 

        // Add a 'change' event to the Kaltura filter player selection drop down
        var kaltura_filter = Y.one('#id_s_local_kaltura_player_filter');

        // Check for the selected option
        var kaltura_filter_dom = Y.Node.getDOMNode(kaltura_filter);
        
        var length = kaltura_filter_dom.length - 1;

        if (length == kaltura_filter_dom.selectedIndex) {

            Y.DOM.byId('id_s_local_kaltura_player_filter_custom').disabled = false;
        } else {

            Y.DOM.byId('id_s_local_kaltura_player_filter_custom').disabled = true;
        }

        kaltura_filter.on('change', function (e) {

            var kaltura_filter_custom_player = Y.DOM.byId("id_s_local_kaltura_player_filter_custom");

            var kaltura_filter_dom = Y.Node.getDOMNode(e.target);

            var length = kaltura_filter_dom.length - 1;

            if (length == kaltura_filter_dom.selectedIndex) {
                kaltura_filter_custom_player.disabled = false;
            } else {
                kaltura_filter_custom_player.disabled = true;
            }
        
        });


    }
    
};

M.local_kaltura.video_assignment = function (Y, conversion_script, panel_markup,
                                             video_properties, kcw_code, kaltura_session,
                                             kaltura_partner_id, script_location, modalwidth, modalheight) {

    // Adding makup to the body of the page for the kalvidassign
    if (null !== Y.one("#page-mod-kalvidassign-view")) { // Body tag for kalvidassign

        if ("" == panel_markup) {
            Y.one("#notification").setContent("The Kaltura plugin is not configured correctly.  Please contact your administrator");
            return '';
        }
        
        body_node = Y.one("#page-mod-kalvidassign-view");
        body_node.append(panel_markup);

    }
    
    var kcw_panel = null;
    var entry_element   = Y.one("#entry_id");    

    if (null !== Y.one("#add_video")) {

        kcw_panel = Y.one("#add_video");
    } else if (null !== Y.one("#replace_video")) {

        kcw_panel = Y.one("#replace_video");
    } else if (null !== Y.on("#id_add_video")) {

        kcw_panel = Y.one("#id_add_video");
    } else {
        return;
    }

    // Create panel to hold KCW
    var widget_panel = new Y.YUI2.widget.Panel("video_panel", { width: "800px",
                                                               height: "470px",
                                                               visible: false,
                                                               constraintoviewport: false,
                                                               close: false,
                                                               underlay: "shadow",
                                                               modal: true,
                                                               fixedcenter: true,
                                                               iframe: false
                                                                
                                                               });
    widget_panel.render();

    // Panel show callback.  Add CSS styles to the main div container
    // to raise it above the rest of the elments on the page
    function widget_panel_callback(e, widget_panel) {
        
        // Check if the screen recording options was checked 
        if (Y.one("#id_media_method_0").get('checked')) {
            widget_panel.setBody(kcw_code);
            widget_panel.show();
        } else {
            Y.one("#progress_bar_container").setStyle("visibility", "visible");
            Y.one("#slider_border").setStyle("borderStyle", "none");

            Y.one("#loading_text").setContent(M.util.get_string("checkingforjava", "kalvidassign"));
            kalturaScreenRecord.setDetectResultErrorMessageElementId('loading_text');
            kalturaScreenRecord.startCallBack.detection_in_progress = true;
            kalturaScreenRecord.startCallBack.detection_process = setTimeout('kalturaScreenRecord.clearDetectionFlagAndDisplayError()', 30000);

            kalturaScreenRecord.setDetectTextJavaDisabled(M.util.get_string("javanotenabled", "kalvidassign"));
            kalturaScreenRecord.setDetectTextmacLionNeedsInstall(M.util.get_string("javanotenabled", "kalvidassign"));
            kalturaScreenRecord.setDetectTextjavaNotDetected(M.util.get_string("javanotenabled", "kalvidassign"));
            kalturaScreenRecord.startKsr(kaltura_partner_id, kaltura_session, 'true');
            
            var java_disabled = function (message) {
            	Y.one('#id_media_method_0').set("checked", true);
            	Y.one('#id_media_method_1').set("disabled", true);
            	
                var progress_bar = document.getElementById('progress_bar_container');
                if (null != progress_bar) {
                    progress_bar.style.visibility = 'hidden';
                }

                alert(M.util.get_string("javanotenabled", "kalvidassign"));
            }
            
            kalturaScreenRecord.setDetectResultErrorCustomCallback(java_disabled);

        }
        
    }


    kcw_panel.on("click", widget_panel_callback, null, widget_panel);
    
    // Add a click event handler to the notifications DIV
    // This is used to close the panel window when the user clicks
    // on the X in the KCW widget
    var kcw_cancel = Y.one("#notification");
    
    // Close wiget panel callback
    function kcw_cancel_callback(e) {
        var entry_id = Y.one("#entry_id");
        if (null !== entry_id) {
            
            if ("" != entry_id.get("value")) {
                
                Y.one("#notification").setContent(M.util.get_string("upload_successful", "local_kaltura"));
            }
            
        }

        widget_panel.hide();
    }

    // Subscribe to the on click event to close the widget_panel
    kcw_cancel.on("click", kcw_cancel_callback);


    if (null !== Y.one("#id_video_preview")) {

        // Create loading panel
        var loading_panel =  new Y.YUI2.widget.Panel("wait", { width:"240px",
                                                              fixedcenter:true,
                                                              close:false,
                                                              draggable:false,
                                                              zindex:4,
                                                              modal:true,
                                                              visible:false
                                                             }
                                                   );

        loading_panel.setHeader("Loading, please wait..."); 
        loading_panel.setBody('<img src="http://l.yimg.com/a/i/us/per/gr/gp/rel_interstitial_loading.gif" />');
        loading_panel.render();

        
        // Create preview panel
        var preview_panel  = new Y.YUI2.widget.Panel("id_video_preview", {
                width: modalwidth+"px",
                height: modalheight+"px",
                fixedcenter: false,
                constraintoviewport: true,
                dragable: false,
                visible: false,
                close: true,
                modal: true,
                context: ["region-main", "tl", "tl", ["beforeShow", "windowResize"]]});

        preview_panel.render();

        // Add 'click' event to preview video button
        var preview = Y.one("#preview_video");

        // Subscribe to the hideEvent for the panel so that the flash player 
        // will be removed when the panel is closed
        preview_panel.hideEvent.subscribe(function() {
            preview_panel.setBody("");
        });

        function video_preview_callback(e) {
            var entry_id = entry_element.get("value");

            if ("" == entry_id) {
                alert("Please select/upload a video before previewing");
            } else {
                loading_panel.show();
              
                // Asynchronous call to the check conversion status script
                var width       = video_properties.width + "px";
                var height      = video_properties.height + "px";
                var uiconf_id   = video_properties.uiconf_id;
                var video_title = video_properties.video_title;
                var entry_id_2  = '';
                

                Y.io(conversion_script + entry_id + "&" +
                     "width=" + width + "&" +
                     "height=" + height + "&" +
                     "uiconf_id=" + uiconf_id + "&" +
                     "video_title=" + video_title);

            }

        }

        if (null !== preview) {
            preview.on("click", video_preview_callback, null);
        }

        function check_conversion_status (id, o) {

            if ('' == o.responseText) {

                Y.one("#notification").setContent(M.util.get_string("video_converting", "kalvidassign"));
                loading_panel.hide();

            } else {
                
                loading_panel.hide();
                
                var data = Y.JSON.parse(o.responseText);

                img_tag = Y.one("#video_thumbnail");
                
                if (data.thumbnailUrl != img_tag.get("src")) {
                    img_tag.set("src", data.thumbnailUrl);
                }

                // If the video markup property is not empty then set the body of the popup panel
                // and resize the panel to the size of the video
                if (undefined !== data.markup) {
                    // Clear notification
                    Y.one("#notification").setContent("");

                    preview_panel.setBody("<center>" + data.markup + "</center>");

                    // Resize preview
                    //preview_panel.cfg.setProperty('width', preview_panel_width + "px");
                    //preview_panel.cfg.setProperty('height', preview_panel_height + "px");

                    preview_panel.show();
                    
                    if (undefined !== data.script) {
                        eval(data.script);
                    }
                } else {
                    Y.one("#notification").setContent(M.util.get_string("video_converting", "kalvidassign"));
                }
            }
        }

                
        Y.on('io:complete', check_conversion_status, Y);

        // Set the location of conversion script because the 
        // get_thumbnail_url() (called by the KDP callback) needs
        // to know the location of the conversion script in order to display
        // the thumbnail
        M.local_kaltura.set_dataroot(script_location);
    }
    
};

M.local_kaltura.video_asignment_submission_view = function (Y, conversion_script, panel_markup, uiconf_id, modalwidth, modalheight, videowidth, videoheight) {

    // Adding makup to the body of the page for the video assignment - grade submissions page
    if (null !== Y.one("#page-mod-kalvidassign-grade_submissions")) { // Body tag for grade submissions page

        bodyNode = Y.one("#page-mod-kalvidassign-grade_submissions");
        bodyNode.append(panel_markup);
    } else {
        return '';
    }
    
    // Create loading panel
    var loading_panel =  new Y.YUI2.widget.Panel("wait", { width:"240px",
                                                          fixedcenter:true,
                                                          close:false,
                                                          draggable:false,
                                                          zindex:4,
                                                          modal:true,
                                                          visible:false
                                                         }
                                               );

    loading_panel.setHeader("Loading, please wait..."); 
    loading_panel.setBody('<img src="http://l.yimg.com/a/i/us/per/gr/gp/rel_interstitial_loading.gif" />');
    loading_panel.render();


    // Create preview panel
    var preview_panel  = new Y.YUI2.widget.Panel("id_video_preview", {
            width: modalwidth+"px",
            height: modalheight+"px",
            fixedcenter: false,
            visible: false,
            constraintoviewport: true,
            close: true,
            modal: true,
            context: ["region-main", "tl", "tl", ["beforeShow", "windowResize"]]});

    preview_panel.render();

    // Subscribe to the hideEvent for the panel so that the flash player 
    // will be removed when the panel is closed

    preview_panel.hideEvent.subscribe(function() {
        preview_panel.setBody("");
    });
  
    function complete(id, o, args) {

                          if ('' == o.responseText) {
                              alert(M.util.get_string("video_converting", "kalvidassign"));
                              loading_panel.hide();
                          } else {

                              loading_panel.hide();

                              var data = Y.JSON.parse(o.responseText);

                              // If the video markup property is not empty then set the body of the popup panel
                              // and resize the panel to the size of the video
                              
                              if (undefined !== data.markup) {

                                    preview_panel.setBody("<center>" + data.markup + "</center>");
                                    
                                    preview_panel.show();
                                    
                                    if (undefined !== data.script) {
                                        eval(data.script);
                                    }

                              } else {

                                    alert(M.util.get_string("video_converting", "kalvidassign"));

                              }

                           }

    };

    Y.on('io:complete', complete, Y);

    // Get a NodeList of image elements
    var image_nodes = Y.all(".video_thumbnail_cl");

    // Return an array of image nodes
    var image_nodes_array = Y.NodeList.getDOMNodes(image_nodes);
   
    function image_node_click_callback(e) {
        loading_panel.show();

        var hidden_id = "hidden_" + e.target.get('id');

        var entry_id = Y.one("#" + hidden_id).get("value");

        Y.io(conversion_script + entry_id  + "&" +
                "uiconf_id=" + uiconf_id + "&" +
                "height="+videoheight+"&width="+videowidth/*, vid_assign_preview_cfg*/);

    }
    
    // Subscribe all image nodes to click event
    Y.on("click", image_node_click_callback, image_nodes_array);

};

M.local_kaltura.video_resource = function (Y, conversion_script, 
                                           panel_markup, kcw_code, default_player_uiconf,
                                           kaltura_session, kaltura_partner_id, progress_bar_markup) {

    var kcw_panel               = null;
    var entry_element           = null;
    var video_property_name     = null;
    var video_hidden_name       = null;
    var video_property_player   = null;
    var video_hidden_player     = null;
    var video_property_dimen    = null;
    var video_hidden_dimen      = null;
    var video_property_size     = null;
    var video_hidden_width      = null;
    var video_hidden_height     = null;
    var video_property_width    = null;
    var video_property_height   = null;
    var preview_panel_width     = null;
    var preview_panel_height    = null;

    // Adding makup to the body of the page for the kalvidres
    if (null !== Y.one("#page-mod-kalvidres-mod")) { // Body tag for kalvidres

        if ("" == panel_markup) {
            Y.one("#notification").setContent("The Kaltura plugin is not configured correctly.  Please contact your administrator");
            return '';
        }
        
        bodyNode = Y.one("#page-mod-kalvidres-mod");
        bodyNode.append(panel_markup);
    }

    if (null !== Y.one("#id_add_video")) {

        kcw_panel       = Y.one("#id_add_video");
        entry_element   = Y.one("#entry_id");

        video_property_name     = Y.one("#vid_prop_name");
        video_hidden_name       = Y.one("#video_title");
        video_property_player   = Y.one("#vid_prop_player");
        video_hidden_player     = Y.one("#uiconf_id");
        video_property_dimen    = Y.one("#vid_prop_dimensions");
        video_hidden_dimen      = Y.one("#widescreen");
        video_property_size     = Y.one("#vid_prop_size");
        video_hidden_width      = Y.one("#width");
        video_hidden_height     = Y.one("#height");
        video_property_width    = Y.one("#vid_prop_width");
        video_property_height   = Y.one("#vid_prop_height");

        // Set width of video property drop down
        video_property_player.setStyle("width", "285");
        
        // Add padding to preview panel height and width
        preview_panel_width  = parseInt(video_hidden_width.get("value")) + 50;
        preview_panel_height = parseInt(video_hidden_height.get("value")) + 50;
        
        // Disabled save buttons until the entry_id has been populated
        submit_btn          = Y.one("#id_submitbutton");
        submit2_btn         = Y.one("#id_submitbutton2");
        
        if ("" == entry_element.get("value")) {
            submit_btn.set("disabled", "disabled");
            submit2_btn.set("disabled", "disabled");
        }
        
    } else {

        // Hide the DHTML when the connection doesn't exist
        Y.one("#video_properties_panel").setStyle("display", "none");
        Y.one("#video_preview_panel").setStyle("display", "none");
        Y.one("#video_panel").setStyle("display", "none");
        return;
    }

    // Add progress bar markup via javascript because of formslib
    var progress_bar = Y.Node.create(progress_bar_markup).getDOMNode();
    Y.one("#id_add_video").getDOMNode().parentNode.appendChild(progress_bar);
    progress_bar.style.cssFloat = 'left';

    // Create panel to hold KCW
    var widget_panel = new Y.YUI2.widget.Panel("video_panel", { width: "800px",
                                                               height: "470px",
                                                               visible: false,
                                                               constraintoviewport: false,
                                                               close: false,
                                                               underlay: "shadow",
                                                               modal: true,
                                                               fixedcenter: true,
                                                               iframe: false
                                                                
                                                               });
    widget_panel.render();

    // Panel show callback
    function widget_panel_callback(e, widget_panel) {

        // Check if the screen recording options was checked 
        if (Y.one("#id_media_method_0").get('checked')) {

            // hide WYSIWYG iframe to avoid laying issues with some versions of Chrome
            // See KALDEV-105 for details
            // Cannot initialize iframe_editor at the beginning of the script because the 
            // editor iframe seems to be the last element loaded on the page 
            var iframe_editor       = Y.one("#id_introeditor_ifr");
    
            if (null !== iframe_editor) {
                iframe_editor.setStyle("display", "none");
            }
            
            widget_panel.setBody(kcw_code);
            widget_panel.show();

        } else {

            Y.one("#progress_bar_container").setStyle("visibility", "visible");
            Y.one("#slider_border").setStyle("borderStyle", "none");

            Y.one("#loading_text").setContent(M.util.get_string("checkingforjava", "kalvidres"));
            kalturaScreenRecord.setDetectResultErrorMessageElementId('loading_text');
            kalturaScreenRecord.startCallBack.detection_in_progress = true;
            kalturaScreenRecord.startCallBack.detection_process = setTimeout('kalturaScreenRecord.clearDetectionFlagAndDisplayError()', 30000);

            kalturaScreenRecord.setDetectTextJavaDisabled(M.util.get_string("javanotenabled", "kalvidres"));
            kalturaScreenRecord.setDetectTextmacLionNeedsInstall(M.util.get_string("javanotenabled", "kalvidres"));
            kalturaScreenRecord.setDetectTextjavaNotDetected(M.util.get_string("javanotenabled", "kalvidres"));
            kalturaScreenRecord.startKsr(kaltura_partner_id, kaltura_session, "true");

            var java_disabled = function (message) {
            	Y.one('#id_media_method_0').set("checked", true);
            	Y.one('#id_media_method_1').set("disabled", true);

            	var progress_bar = document.getElementById('progress_bar_container');
            	if (null != progress_bar) {
            	    progress_bar.style.visibility = 'hidden';
            	}
            	
            	alert(M.util.get_string("javanotenabled", "kalvidres"));
            }
            
            kalturaScreenRecord.setDetectResultErrorCustomCallback(java_disabled);

        }
    }

    kcw_panel.on("click", widget_panel_callback, null, widget_panel);

    // Add a focus event handler to the notifications DIV
    // This is used to close the panel window when the user clicks
    // on the X in the KCW widget
    var kcw_cancel = Y.one("#notification");

    // Close wiget panel callback
    function kcw_cancel_callback(e, widget_panel) {
        widget_panel.hide();
    }

    // Subscribe to the on click event to close the widget_panel
    kcw_cancel.on("click", kcw_cancel_callback, null, widget_panel);

    // Video Properties block of code
    // Open a new panel to set the video properties
    var handle_submit = function() {
        
        var set_value = video_property_name.get("value");
        video_hidden_name.set("value", set_value);

        set_value = video_property_player.get("value");
        video_hidden_player.set("value", set_value);

        set_value = video_property_dimen.get("value");
        video_hidden_dimen.set("value", set_value);
        
        set_value = video_property_size.get("value");

        switch (set_value) {
            case "0":
                video_hidden_width.set("value", "400");

                if (0 == video_hidden_dimen.get("value")) {
                    video_hidden_height.set("value", "365");
                } else {
                    video_hidden_height.set("value", "290");
                }
                
                break;
            case "1":
                video_hidden_width.set("value", "260");

                if (0 == video_hidden_dimen.get("value")) {
                    video_hidden_height.set("value", "260");
                } else {
                    video_hidden_height.set("value", "211");
                }
                break;
            case "2":
                var width = video_property_width.get("value");
                var height = video_property_height.get("value");
                
                // If invalid values are used do not set any values
                if ( !isNaN(parseInt(width)) && 0 < parseInt(width) ) {
                    video_hidden_width.set("value", width);
                }

                if ( !isNaN(parseInt(height)) && 0 < parseInt(height) ) {
                    video_hidden_height.set("value", height);
                }


                break;
        }

        preview_panel_width  = parseInt(video_hidden_width.get("value")) + 50;
        preview_panel_height = parseInt(video_hidden_height.get("value")) + 50

        prop_panel.hide();
        
    }; 
    
    var handle_cancel = function() {

        var set_value = video_hidden_name.get("value");
        video_property_name.set("value", set_value);

        set_value = video_hidden_player.get("value");
        if ("" != set_value) {
            video_property_player.set("value", set_value);
        } else {
            video_property_player.set("value", default_player_uiconf);
        }
        
        set_value = video_hidden_dimen.get("value");
        video_property_dimen.set("value", set_value);

        height = video_hidden_height.get("value");
        width = video_hidden_width.get("value");
        
        // If normal dimension are selected 
        if (0 == video_hidden_dimen.get("value")) {
            if ("400" == width && "365" == height) {
                video_property_size.set("value", "0");
                video_property_width.set("value", "");
                video_property_height.set("value", "");
    
            } else if ("260" == width && "260" == height) {
                video_property_size.set("value", "1");
                video_property_width.set("value", "");
                video_property_height.set("value", "");
    
            } else {
                video_property_size.set("value", "2");
                video_property_width.set("value", width);
                video_property_height.set("value", height);
            }
            
            // Set video property size drop down text
            video_property_size.get("options").item(0).setContent("Large (400x365)");
            video_property_size.get("options").item(1).setContent("Small (260x260)");

        } else { // If widescreen is selected
            if ("400" == width && "290" == height) {
                video_property_size.set("value", "0");
                video_property_width.set("value", "");
                video_property_height.set("value", "");
    
            } else if ("260" == width && "211" == height) {
                video_property_size.set("value", "1");
                video_property_width.set("value", "");
                video_property_height.set("value", "");
    
            } else {
                video_property_size.set("value", "2");
                video_property_width.set("value", width);
                video_property_height.set("value", height);
            }

            // Set video property size drop down text
            video_property_size.get("options").item(0).setContent("Large (400x290)");
            video_property_size.get("options").item(1).setContent("Small (260x211)");

        }

        // disable custom height and width input boxes if if large or small is selected 
        switch (video_property_size.get("value")) {
            case "0":
            case "1":
                video_property_width.set("disabled", "disabled");
                video_property_height.set("disabled", "disabled");
                break;
            case "2":
                video_property_width.set("disabled", "");
                video_property_height.set("disabled", "");
                break;
        }

        prop_panel.hide();
    }; 

    // Add change event to player dimension and size properties drop down Disable
    // the custom width and height input boxes when a non custom option is selected
    // for the property size
    function vid_prop_player_callback(e) {

        if ("vid_prop_size" == e.target.get("id")) {
            switch (e.target.get("value")) {
                case "0":
                case "1":
                    video_property_width.set("disabled", "disabled");
                    video_property_height.set("disabled", "disabled");
                    break;
                case "2":
                    video_property_width.set("disabled", "");
                    video_property_height.set("disabled", "");
                    break;
            }
        }
        
        if ("vid_prop_dimensions" == e.target.get("id")) {
            // Set video property size drop down text
            if ("0" == e.target.get("value")) {
                video_property_size.get("options").item(0).setContent("Large (400x365)");
                video_property_size.get("options").item(1).setContent("Small (260x260)");
            } else {
                video_property_size.get("options").item(0).setContent("Large (400x290)");
                video_property_size.get("options").item(1).setContent("Small (260x211)");
            }

        }
    }
    
    // Listen to the 'change' for the player size and player dimensions drop down
    Y.on("change", vid_prop_player_callback,
         [Y.Node.getDOMNode(video_property_size), Y.Node.getDOMNode(video_property_dimen)]); 
    
    // Create properties panel instance
    var prop_panel  = new Y.YUI2.widget.Dialog("video_properties_panel",
                                                { width: "400px",
                                                  fixedcenter: true,
                                                  visible: false,
                                                  constraintoviewport: true,
                                                  close: true,
                                                  modal: true,
                                                  buttons : [ { text:"Save", handler:handle_submit, isDefault:true }, 
                                                              { text:"Close", handler:handle_cancel } ]
                                                });

    // Call the cancel callback to initialize the data
    handle_cancel();
    
    // Render the panel (note: configuration specifies that the panel starts off hidde)
    prop_panel.render();


    var video_prop_panel = Y.one("#id_video_properties");
    
    // Video properties button callback
    function video_prop_panel_callback(e) {
          
         if ("" != entry_element.get("value")) {
             prop_panel.show();
         } else {
             alert("Please select/upload a video before setting the properties");
         }
    }

    // Listen to the 'click' event to player properties button and load the properties panel
    video_prop_panel.on("click", video_prop_panel_callback, null);
            
    // Listen to the close event (when the X is pressed) and call the cancel call back
    prop_panel.hideEvent.subscribe(handle_cancel);

    // Create loading panel
    var loading_panel =  new Y.YUI2.widget.Panel("wait", { width:"240px",
                                                          fixedcenter:true,
                                                          close:false,
                                                          draggable:false,
                                                          zindex:4,
                                                          modal:true,
                                                          visible:false
                                                         }
                                               );

    loading_panel.setHeader("Loading, please wait..."); 
    loading_panel.setBody('<img src="http://l.yimg.com/a/i/us/per/gr/gp/rel_interstitial_loading.gif" />');
    loading_panel.render();

    // Create preview panel
    
    var preview_panel  = new Y.YUI2.widget.Panel("video_preview_panel",
                                    { width:  preview_panel_width,
                                      height: preview_panel_height,
                                      fixedcenter: false,
                                      visible: false,
                                      constraintoviewport: true,
                                      close: true,
                                      modal: true,
                                      context: ["region-main", "tl", "tl", ["beforeShow", "windowResize"]]
                                    });

    preview_panel.render();

    // Add 'click' event to preview video button
    var preview = Y.one("#id_video_preview");

    // Subscribe to the hideEvent for the panel so that the flash player 
    // will be removed when the panel is closed
    preview_panel.hideEvent.subscribe(function() {
        
        var iframe_editor = Y.one("#id_introeditor_ifr");
        
        if (null !== iframe_editor) {
            iframe_editor.setStyle("display", "");
        }
        preview_panel.setBody("");

    });

    function video_preview_callback(e) {
        var entry_id = entry_element.get("value");

        if ("" == entry_id) {
            alert("Please select/upload a video before previewing");
        } else {

            // hide WYSIWYG iframe to avoid laying issues with some versions of Chrome
          // See KALDEV-105 for details
          // Cannot initialize iframe_editor at the beginning of the script because the 
          // editor iframe seems to be the last element loaded on the page 
          var iframe_editor = Y.one("#id_introeditor_ifr");

          if (null !== iframe_editor) {
            iframe_editor.setStyle("display", "none");
          }
          loading_panel.show();
          
          // Asynchronous call to the check conversion status script
          var width       = Y.one("#width").get("value") + "px";
          var height      = Y.one("#height").get("value") + "px";
          var uiconf_id   = Y.one("#uiconf_id").get("value");
          var video_title = Y.one("#video_title").get("value");

          Y.io(conversion_script + entry_id + "&" +
               "width=" + width + "&" +
               "height=" + height + "&" +
               "uiconf_id=" + uiconf_id + "&" +
               "video_title=" + video_title);

        }
    }

    preview.on("click", video_preview_callback, null);
    
    function check_conversion_status (id, o) {
        
        if ('' == o.responseText) {

            Y.one("#notification").setContent(M.util.get_string("video_converting", "kalvidres"));
            loading_panel.hide();

        } else {
            
            // Hide loading panel
            loading_panel.hide();
            
            // Parse returned data
            var data = Y.JSON.parse(o.responseText);

           // Set the thumbnail source for the img tag
            img_tag = Y.one("#video_thumbnail");
            
            if (data.thumbnailUrl != img_tag.get("src")) {
                img_tag.set("src", data.thumbnailUrl);
            }

            // If the video markup property is not empty then set the body of the popup panel
            // and resize the panel to the size of the video
            if (undefined !== data.markup) {
                // Clear notification
                Y.one("#notification").setContent("");

                preview_panel.setBody("<center>" + data.markup + "</center>");

                // Resize preview
                preview_panel.cfg.setProperty('width', preview_panel_width + "px");
                preview_panel.cfg.setProperty('height', preview_panel_height + "px");

                preview_panel.show();
                
                if(data.script) {
                    eval(data.script);
                }

            } else {
                Y.one("#notification").setContent(M.util.get_string("video_converting", "kalvidres"));
            }
            
        }
    }

            
    Y.on('io:complete', check_conversion_status, Y);
    
    // Set the location of conversion script because the 
    // get_thumbnail_url() (called by the KDP callback) needs
    // to know the location of the conversion script in order to display
    // the thumbnail
    M.local_kaltura.set_dataroot(conversion_script);

};

M.local_kaltura.video_presentation = function (Y, conversion_script, 
                                               panel_markup, uploader_url, flashvars, 
                                               kcw_code, kaltura_session, kaltura_partner_id,
                                               progress_bar_markup) {

    // Adding makup to the body of the page for the kalvidpres
    if (null !== Y.one("#page-mod-kalvidpres-mod")) {

        if ("" == panel_markup ||
            "" == uploader_url) {
            Y.one("#notification").setContent("The Kaltura plugin is not configured correctly.  Please contact your administrator");
            return '';
        }
        
        bodyNode = Y.one("#page-mod-kalvidpres-mod");
        bodyNode.append(panel_markup);
    }

    if (null !== Y.one("#id_add_video")) {
        wwwroot             = Y.one("#wwwroot");
        kcw_panel           = Y.one("#id_add_video");
        // this element is first used to store the video entry id, it is then 
        // replaced by the entry id of the converted video and document
        pres_entry_id       = Y.one("#entry_id"); 
        document_entry_id   = Y.one("#doc_entry_id");
        video_entry_id      = Y.one("#video_entry_id");
        activity_name       = Y.one("#id_name");
        submit_btn          = Y.one("#id_submitbutton");
        submit2_btn         = Y.one("#id_submitbutton2");
        video_added         = Y.one("#id_video_added");
        
        if ("" == pres_entry_id.get("value")) {
            submit_btn.set("disabled", "disabled");
            submit2_btn.set("disabled", "disabled");

        }
        
    } else {

        // Hide the DHTML when the connection doesn't exist
        Y.one("#video_panel").setStyle("display", "none");
        Y.one("#video_preview_panel").setStyle("display", "none");
        Y.one("#wait").setStyle("display", "none");
        return;
    }

    // Add progress bar markup via javascript because of formslib
    var progress_bar = Y.Node.create(progress_bar_markup).getDOMNode();
    Y.one("#id_add_video").getDOMNode().parentNode.appendChild(progress_bar);
    progress_bar.style.cssFloat = 'left';
    
    // Create panel to hold KCW
    var widget_panel = new Y.YUI2.widget.Panel("video_panel", { width: "800px",
                                                               height: "470px",
                                                               visible: false,
                                                               constraintoviewport: false,
                                                               close: false,
                                                               underlay: "shadow",
                                                               modal: true,
                                                               fixedcenter: true,
                                                               iframe: false
                                                                
                                                               });
    widget_panel.render();

    // Panel show callback
    function widget_panel_callback(e, widget_panel) {

        // Check if the screen recording options was checked 
        if (Y.one("#id_media_method_0").get('checked')) {
            
            // hide WYSIWYG iframe to avoid laying issues with some versions of Chrome
            // See KALDEV-105 for details
            var iframe_editor = Y.one("#id_introeditor_ifr");
    
            if (null !== iframe_editor) {
                iframe_editor.setStyle("display", "none");
            }
    
            widget_panel.setBody(kcw_code);
            widget_panel.show();

        } else {
        	
            Y.one("#progress_bar_container").setStyle("visibility", "visible");
            Y.one("#slider_border").setStyle("borderStyle", "none");

            Y.one("#loading_text").setContent(M.util.get_string("checkingforjava", "kalvidpres"));
            kalturaScreenRecord.setDetectResultErrorMessageElementId('loading_text');
            kalturaScreenRecord.startCallBack.detection_in_progress = true;
            kalturaScreenRecord.startCallBack.detection_process = setTimeout('kalturaScreenRecord.clearDetectionFlagAndDisplayError()', 30000);

            kalturaScreenRecord.setDetectTextJavaDisabled(M.util.get_string("javanotenabled", "kalvidpres"));
            kalturaScreenRecord.setDetectTextmacLionNeedsInstall(M.util.get_string("javanotenabled", "kalvidpres"));
            kalturaScreenRecord.setDetectTextjavaNotDetected(M.util.get_string("javanotenabled", "kalvidpres"));
            kalturaScreenRecord.startKsr(kaltura_partner_id, kaltura_session, 'true');

            var java_disabled = function (message) {
            	Y.one('#id_media_method_0').set("checked", true);
            	Y.one('#id_media_method_1').set("disabled", true);

            	var progress_bar = document.getElementById('progress_bar_container');
                if (null != progress_bar) {
                    progress_bar.style.visibility = 'hidden';
                }

            	alert(M.util.get_string("javanotenabled", "kalvidpres"));
            }
            
            kalturaScreenRecord.setDetectResultErrorCustomCallback(java_disabled);

        }
    }
    
    kcw_panel.on("click", widget_panel_callback, null, widget_panel);

    // Add a focus event handler to the notifications DIV
    // This is used to close the panel window when the user clicks
    // on the X in the KCW widget
    var kcw_cancel = Y.one("#notification");
    
    // Close wiget panel callback
    function kcw_cancel_callback(e, widget_panel) {
        widget_panel.hide();
    }

    // Subscribe to the on click event to close the widget_panel
    kcw_cancel.on("click", kcw_cancel_callback, null, widget_panel);
    
    // Create loading panel
    var loading_panel =  new Y.YUI2.widget.Panel("wait", { width:"240px",
                                                          fixedcenter:true,
                                                          close:false,
                                                          draggable:false,
                                                          zindex:4,
                                                          modal:true,
                                                          visible:false
                                                         }
                                               );

    loading_panel.setHeader("Loading, please wait..."); 
    loading_panel.setBody('<img src="http://l.yimg.com/a/i/us/per/gr/gp/rel_interstitial_loading.gif" />');
    loading_panel.render();

    // YUI IO context and callbacks.  Also create preview panel
    
    // Create the object with the check video conversion status function
    var preview_video_context = { 
            complete: function check_conversion_status (id, o) {
        
                if ('' == o.responseText) {
        
                    Y.one("#notification").setContent("The current video is still being converted, please try again soon.");
                    loading_panel.hide();
        
                } else {
                    var entry_id     = pres_entry_id.get("value");
                    var vid_entry_id = video_entry_id.get("value");

                    loading_panel.hide();
                    
                    var data = Y.JSON.parse(o.responseText);
                    
                    // When the document finished converting we do not want to overwrite the video
                    // thumbnail image, because a converted video presentation does not contain a 
                    // thumbnail property
                    if (entry_id == vid_entry_id) {
                        img_tag = Y.one("#video_thumbnail");
                        
                        if (data.thumbnailUrl != img_tag.get("src")) {
                            img_tag.set("src", data.thumbnailUrl);
                        }
                    }
                    
                    if (undefined !== data.markup) {
        
                        // Resize preview
                        preview_panel_width = parseInt(data.width) + 50;
                        preview_panel_height = parseInt(data.height) + 50;


                        preview_panel.cfg.setProperty('width', preview_panel_width + "px");
                        preview_panel.cfg.setProperty('height', preview_panel_height + "px");
        
                        preview_panel.setBody("<center>" + data.markup + "</center>");
            
                        preview_panel.show();
                    } else {
                        Y.one("#notification").setContent(M.util.get_string("video_converting", "kalvidpres"));
                    }
                }
           }
    };
    
    // Setup a configuration object, give it the context of the preview_video_context
    // and set the "completed" event execute the preview_video_context.complete function
    var cfg = {
            on: {
                complete: preview_video_context.complete
            },
            context: preview_video_context
    };
    
    var preview_panel  = new Y.YUI2.widget.Panel("video_preview_panel",
                                    { width: "550px",
                                      height: "550px",
                                      fixedcenter: true,
                                      visible: false,
                                      constraintoviewport: true,
                                      close: true,
                                      modal: true
                                    });

    preview_panel.render();

    // Get the id_video_preview Node
    var preview = Y.one("#id_video_preview");
    
    function video_preview_callback(e) {
        var entry_id     = pres_entry_id.get("value");
        var vid_entry_id = video_entry_id.get("value");
        var flag         = video_added.get("value");
        var widget       = 'kdp';

        if ("" == entry_id ||
            "0" == flag) {
            alert("Please select/upload a video before previewing");
        } else {
            
            // hide WYSIWYG iframe to avoid laying issues with some versions of Chrome
            // See KALDEV-105 for details
            // Cannot initialize iframe_editor at the beginning of the script because the 
            // editor iframe seems to be the last element loaded on the page 
            var iframe_editor = Y.one("#id_introeditor_ifr");

            if (null !== iframe_editor) {
                iframe_editor.setStyle("display", "none");
            }
            
            loading_panel.show();
          
            // Asynchronous call to the check conversion status script
            var width       = Y.one("#width").get("value") + "px";
            var height      = Y.one("#height").get("value") + "px";
            var uiconf_id   = Y.one("#uiconf_id").get("value");
            var video_title = Y.one("#video_title").get("value");
          
            if (entry_id != vid_entry_id) {
                widget = 'kpdp';
            }
          
            Y.io(conversion_script + entry_id + "&" +
               "width=" + width + "&" +
               "height=" + height + "&" +
               "uiconf_id=" + uiconf_id + "&" +
               "video_title=" + video_title + "&" +
               "widget=" + widget + "&" +
               "admin_mode=1", cfg);
        }

    }

    // Add 'click' event to preview video button
    preview.on("click", video_preview_callback, null);

    // Subscribe to the hideEvent for the panel so that the flash player 
    // will be removed when the panel is closed
    preview_panel.hideEvent.subscribe(function() {
        var iframe_editor = Y.one("#id_introeditor_ifr");

        if (null !== iframe_editor) {
            iframe_editor.setStyle("display", "");
        }

        preview_panel.setBody("");
    });
    
    /* THE CODE BELOW DEALS WITH DOCUMENT AND SWFDOC CONVERSIONS */
    
    // Create the object with the create swfdoc function
    var swf_doc_context = {
            complete: function create_swfdoc (id, o) {
        

                if (o.responseText.substring(0,2) == "y:") {

                    // Store the entry id of the video presentation
                    pres_entry_id.set("value", o.responseText.substring(2));

                    Y.one("#document_thumbnail_container").setContent("The document has finished converting");

                    submit_btn.set("disabled", 0);
                    submit2_btn.set("disabled", 0);
                    kcw_panel.set("disabled", "disabled");
                    loading_panel.hide();
                    //alert("pres_entry_id set = " + o.responseText.substring(2));

                } else {
                    loading_panel.hide();
                    alert("Error creating SWF doc");
                }
            }
    };

    // Setup a configuration object, give it the context of the swf_doc_context
    // and set the "completed" event execute the swf_doc_context.complete function
    var swf_doc_cfg = {
            on: {
                complete: swf_doc_context.complete
            },
            context: swf_doc_context
    };

    // Create the object with the check video conversion status function
    var document_status_context = {
            complete: function check_document_conversion_status (id, o) {

                if (o.responseText.substring(0,2) == "y:") {

                    var download_url  = Y.one("#id_ppt_dnld_url");
                    var download_url2 = Y.one("#id_ppt_dnld_url2");
                    var name = activity_name.get("value");
                    
                    if ("" == name) {
                        name = "Video Presentation";
                    }


                    download_url2.set("value", o.responseText.substring(2));
                    //alert('Download URL 2 popuplated ' + o.responseText.substring(2));
                    //alert('Async call to create_sfw_doc.php(), passing video_entry_id = ' + video_entry_id.get("value") + 
                          //', doc_entry_id = ' +  document_entry_id.get("value") +
                          //', download_url = ' + download_url.get("value") +
                          //', download_url2 = ' + download_url2.get("value") +
                          //', name = ' + name);

                            Y.io("../local/kaltura/create_swf_doc.php?" +
                                                "video_entry_id=" + video_entry_id.get("value") +
                                                "&doc_entry_id=" + document_entry_id.get("value"),
                                                swf_doc_cfg);

                } else {
                    loading_panel.hide();
                    alert("The document is converting.  Please try again soon.");
                }
            }
    };
    
    // Setup a configuration object, give it the context of the document_status_context
    // and set the "completed" event execute the document_status_context.complete function
    var doc_cfg = {
            on: {
                complete: document_status_context.complete
            },
            context: document_status_context
    };

    // add a click event listener to the check document conversion status button
    var doc_check_status = Y.one("#id_check_doc_status");
    
    // Call back fired when the user tries to check the status of the document conversion
    function check_doc_conversion_status(e) {
        
        if ("0" != document_entry_id.get("value")) {
            loading_panel.show();
            
            //alert('Async call to check_document_status.php, passing entry_id = ' + document_entry_id.get("value"));
            Y.io("../local/kaltura/check_document_status.php?entry_id=" + 
                 document_entry_id.get("value"), doc_cfg);
        } else {
            alert("You must first upload a document before checking on the status of the conversion");
        }


    }

    doc_check_status.on("click", check_doc_conversion_status);

    
    // Set the location of conversion script because the 
    // get_thumbnail_url() (called by the KDP callback) needs
    // to know the location of the conversion script in order to display
    // the thumbnail
    M.local_kaltura.set_dataroot(conversion_script);

};

M.local_kaltura.video_presentation_view = function (Y, conversion_script, 
                                                    panel_markup, video_properties, admin_mode) {

    // Adding makup to the body of the page for the kalvidpres view
    if (null !== Y.one("#page-mod-kalvidpres-view")) {

        if ("" == panel_markup) {
            Y.one("#notification").setContent("The Kaltura plugin is not configured correctly.  Please contact your administrator");
            return '';
        }
        
        bodyNode = Y.one("#page-mod-kalvidpres-view");
        bodyNode.append(panel_markup);
        
        preview = Y.one("#id_pres_btn");
    }

    // Create preview panel
    var preview_panel  = new Y.YUI2.widget.Panel("video_pres_panel",
                                    { width: "870px",
                                      height: "440px",
                                      fixedcenter: true,
                                      visible: false,
                                      constraintoviewport: true,
                                      close: true,
                                      modal: true
                                    });

    preview_panel.render();

    // Subscribe to the hideEvent for the panel so that the flash player 
    // will be removed when the panel is closed
    preview_panel.hideEvent.subscribe(function() {
        preview_panel.setBody("");
    });

    function video_preview_callback(e) {
        

        M.local_kaltura.show_loading();
      
        // Asynchronous call to the check conversion status script
        var width       = video_properties.width + "px";
        var height      = video_properties.height + "px";
        var uiconf_id   = video_properties.uiconf_id;
        var video_title = video_properties.video_title;

        Y.io(conversion_script + "&" +
             "width=" + width + "&" +
             "height=" + height + "&" +
             "uiconf_id=" + uiconf_id + "&" +
             "video_title=" + video_title + "&" +
             "admin_mode=" + admin_mode);

    }

    if (null !== preview) {
        preview.on("click", video_preview_callback, null);
    }

    function check_conversion_status (id, o) {

        if ('' == o.responseText) {

            Y.one("#notification").setContent("The current video presentation is still being converted, please try again soon.");
            loading_panel.hide();

        } else {
            
            loading_panel.hide();
            
            var data = Y.JSON.parse(o.responseText);

            Y.one("#notification").setContent("");
            
            preview_panel.setBody("<center>" + data.markup + "</center>");

            preview_panel.show();
        }
    }

            
    Y.on('io:complete', check_conversion_status, Y);

};
