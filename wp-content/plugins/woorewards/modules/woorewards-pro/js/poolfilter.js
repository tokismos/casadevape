jQuery(function(a){a(".lws_wre_pool_visibilities").on("change",".lws_wre_pool_visibility",function(){a('.lws-editlist .lws-editlist-cell[data-key="'+a(this).data("name")+'"]').toggleClass("lws-pool-hidden-column",!this.checked);a(".lws_wre_pool_visibilities .lws_wre_pool_visibility").each(function(){localStorage.setItem(a(this).attr("name"),a(this).prop("checked")?"Y":"N")})});a(".lws_wre_pool_visibilities .lws_wre_pool_visibility").each(function(){if(localStorage.getItem(a(this).attr("name"))){var b=(localStorage.getItem(a(this).attr("name")).startsWith("Y"));a(this).prop("checked",b).lws_checkbox("refresh");a('.lws-editlist .lws-editlist-cell[data-key="'+a(this).data("name")+'"]').toggleClass("lws-pool-hidden-column",!b)}})});