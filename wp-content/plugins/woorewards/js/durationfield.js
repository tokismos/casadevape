jQuery(function(b){b(".lws_wr_durationfield").on("change",".lws_wr_lifetime_master",function(){var h=b(this).closest(".lws_wr_durationfield");var f=b(this).val().trim().length>0;var a=h.find(".lws_wr_lifetime_unit").val();if(f){var g=b(this).val().trim();if(g.startsWith("P")){g=g.substr(1)}h.find(".lws_wr_lifetime_unit").val(g.slice(-1));h.find(".lws_wr_lifetime_value").val(g.slice(0,-1))}h.find(".lws_wr_lifetime_value").toggle(f);if(h.find(".lac-select-wrapper").length<=0){h.find(".lws_wr_lifetime_unit").toggle(f)}else{h.find(".lac-select-wrapper").toggle(f)}if(h.find(".lws_wr_lifetime_check").prop("checked")!=f){h.find(".lws_wr_lifetime_check").prop("checked",f).trigger("change")}if(a!=h.find(".lws_wr_lifetime_unit").val()){h.find(".lws_wr_lifetime_unit").trigger("change")}});b(".lws_wr_durationfield").on("change",".lws_wr_lifetime_check",function(){var f=b(this).closest(".lws_wr_durationfield");var e=b(this).prop("checked");f.find(".lws_wr_lifetime_value").toggle(e);if(f.find(".lac-select-wrapper").length<=0){f.find(".lws_wr_lifetime_unit").toggle(e)}else{f.find(".lac-select-wrapper").toggle(e)}if(!e){f.find(".lws_wr_lifetime_master").val("")}else{var a=parseInt(f.find(".lws_wr_lifetime_value").val());if(isNaN(a)){f.find(".lws_wr_lifetime_master").val("")}else{f.find(".lws_wr_lifetime_master").val("P"+Math.abs(a).toString()+f.find(".lws_wr_lifetime_unit").val())}}});b(".lws_wr_durationfield").on("change",".lws_wr_lifetime_value",function(){var d=b(this).closest(".lws_wr_durationfield");var a=parseInt(d.find(".lws_wr_lifetime_value").val());if(isNaN(a)){d.find(".lws_wr_lifetime_master").val("");d.find(".lws_wr_lifetime_value").val("0")}else{d.find(".lws_wr_lifetime_master").val("P"+Math.abs(a).toString()+d.find(".lws_wr_lifetime_unit").val())}});b(".lws_wr_durationfield").on("change",".lws_wr_lifetime_unit",function(){var d=b(this).closest(".lws_wr_durationfield");var a=parseInt(d.find(".lws_wr_lifetime_value").val());if(isNaN(a)){d.find(".lws_wr_lifetime_master").val("")}else{d.find(".lws_wr_lifetime_master").val("P"+Math.abs(a).toString()+d.find(".lws_wr_lifetime_unit").val())}})});