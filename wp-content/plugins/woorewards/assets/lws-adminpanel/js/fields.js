jQuery(function(b){b("form").keypress(function(e){e=e||event;var f=/textarea/i.test((e.target||e.srcElement).tagName);var a=(f||(e.keyCode||e.which||e.charCode||0)!==13);if(!a){e.preventDefault();b(e.target||e.srcElement).trigger("change");return true}});b("select.lws-select-input").each(function(){var a=b(this);a.selectmenu({change:function(f,e){if(a.val()!=e.item.value){a.val(e.item.value)}a.trigger("change")}});a.change(function(){try{a.selectmenu("refresh")}catch(d){}});if(a.data("class")!=undefined){a.next(".ui-selectmenu-button").addClass(a.data("class"))}});b(".lws-group-descr").each(function(){if(b(this).outerHeight()>50){var a=b(this);var g=b(this).find(".lws-group-descr-text");var h=b(this).find(".lws-group-descr-button");var f=b(this).find(".lws-group-descr-shadow");a.css("height","40px");g.css("height","40px");h.css("display","flex");f.css("display","block");h.on("click",function(c){if(h.hasClass("lws-icon-chevron-down")){a.css("height","auto");g.css("height","auto");h.removeClass("lws-icon-chevron-down").addClass("lws-icon-chevron-up");f.css("display","none")}else{h.removeClass("lws-icon-chevron-up").addClass("lws-icon-chevron-down");a.css("height","40px");g.css("height","40px");f.css("display","block")}})}});b(".lws-adm-btn-trigger").click(function(){var a=b(this);var e=b(this).parents(".lws-form-div").lwsReadForm();var f={action:"lws_adminpanel_field_button",button:b(this).attr("id"),form:lwsBase64.fromObj(e)};b.ajax({dataType:"json",method:"POST",url:lws_ajax_url,data:f,success:function(c){if((0!=c)&&c.status){if(c.data!=undefined){a.next(".lws-adm-btn-trigger-response").html(c.data)}}else{alert(lws_adminpanel.triggerError)}}}).fail(function(d,c,h){a.replaceWith("<p class='lws-error'>Trigger error, status: "+c+", error: "+h+"</p>").show()});return false});b(document).click(function(a){if(b(a.target).closest(".lwss-disable-on-clic-out").length==0){if(b(a.target).closest(".lwss-hide-on-clic-out").length==0){b(".lwss-hide-on-clic-out").fadeOut()}if(b(a.target).closest(".lwss-fold-on-clic-out").length==0){b(".lwss-fold-on-clic-out").slideUp()}}});b(document).keyup(function(a){if((a.keyCode||a.which||a.charCode||0)===27){b(".lwss-hide-on-clic-out").fadeOut();b(".lwss-fold-on-clic-out").slideUp()}});b("form").on("change","input, textarea, select",function(){if((!b(this).hasClass("lws-ignore-confirm")&&b(this).is(":visible"))||b(this).hasClass("lws-force-confirm")){if(b(this).closest(".lws_editlist").length==0){window.lwsInputchanged=true}window.onbeforeunload=function(){return lws_adminpanel.confirmLeave}}});b("form").submit(function(){if(window.onbeforeunload!=undefined&&(b(".lws-editlist-btn-disabled").length>0||b(".lws_editlist .lws_editlist_modal_form:visible").length>0)){alert(lws_adminpanel.editlistOnHold);return false}if(document.lwsForceConfirm==undefined){window.onbeforeunload=undefined;document.lwsForceConfirm=undefined}});b(".lws-adminpanel-singular-delete-button").click(function(){var a=b(this);b(".lws-adminpanel-singular-delete-confirmation").dialog({autoOpen:true,height:200,width:480,modal:true,buttons:[{text:a.data("yes")!=undefined?a.data("yes"):"Confirm",icon:"ui-icon-alert",click:function(){document.location=a.attr("href")}},{text:a.data("no")!=undefined?a.data("no"):"Cancel",icon:"ui-icon-cancel",click:function(){b(this).dialog("close")}}],open:function(){b(".ui-dialog :button").blur();b(".ui-dialog :button:last").focus()}});return false});b("body").on("mouseover",".lws_tooltips_button",function(d){b(".lws_tooltips_wrapper").hide();var a=b(d.target).find(".lws_tooltips_wrapper");a.toggle(!a.is(":visible"));return false});b("body").on("mouseout",".lws_tooltips_button",function(d){b(".lws_tooltips_wrapper").hide();var a=b(d.target).find(".lws_tooltips_wrapper");a.toggle(a.is(":visible"));return false});b("body").on("click",".lws_ui_value_copy .copy",function(d){var a=b(d.target).closest(".lws_ui_value_copy").find(".content");window.getSelection().selectAllChildren(a.get(0));document.execCommand("copy")})});