jQuery(function(a){a(".woocommerce").on("click",".lws_woorewards_add_coupon",function(f){if(a(f.currentTarget).data("reload")==undefined){a("#coupon_code").val(a(f.currentTarget).data("coupon"));a(f.currentTarget).closest(".lws_wr_cart_coupon_row").hide();a("input[name='apply_coupon'],button[name='apply_coupon']").trigger("click")}else{var g={};var e=window.location.toString();if(window.location.search.length>1){var d=window.location.search.substr(1).split("&");for(var b=0;b<d.length;++b){couple=d[b].split("=");var c=unescape(couple[0]);if(!c.startsWith("wrac_")){g[c]=couple.length>1?unescape(couple[1]):""}}}e=(e.substring(0,e.indexOf("?"))+"?");if(g.length>0){e+=(a.param(g)+"&")}document.location=(e+a(f.currentTarget).data("reload"))}return false});a(".woocommerce").on("click",".woocommerce-remove-coupon",function(){a.each(a(this).closest(".cart-discount").attr("class").split(" "),function(b,c){if(c.startsWith("coupon-")){a("#lws_woorewards_coupons").find(".lws_wr_cart_coupon_row."+c).show();return false}})})});