(function(b){b.fn.fontselector=function(a){var k=null;var q=null;var l=null;var r=null;var p={ajax:lws_ajax_url,action:"lws_adminpanel_fontlist",api:"https://fonts.googleapis.com/css?family=",interval:500};var o=(a!=undefined)?a:"";var n=function(d,c){return function(){return d.apply(c,arguments)}};var m=(function(){function c(d,f,e){this.$original=b(d);this.options=f;this.cssOptions=e;this.setupHtml();this.loadFonts(this);this.bindEvents()}c.prototype.setupHtml=function(){this.$family=this.$original.find(".lwss-fontselect-family").hide();this.$weight=this.$original.find(".lwss-fontselect-weight").hide();this.main=this.$original.find(".lwss-fontname-select");this.main.attr("tabindex","0");this.$select=b("<div>",{"class":"lwss-fontname-select-text"}).html(this.fontDescription());this.addFontLink(this.$family.val(),this.$weight.val());this.setFontStyle(this.$select,this.$family.val(),this.$weight.val());this.$arrow=b("<div>",{"class":"lwss-fontname-select-btn lws-icon-select_arrow_down"+((this.cssOptions.cssColor!=undefined)?" "+this.cssOptions.cssColor:"")});this.$list=b("<div>",{"class":"lwss-font-list lwss-hide-on-clic-out",style:"display:none;"});var d=b("<div>",{"class":"lwss-font-list-toggle"}).appendTo(this.$list);this.$toggleMore=b("<div>",{"class":"lwss-font-list-toggle-more"}).appendTo(d).html(lws_adminpanel.fontToggleMore);this.$toggleLess=b("<div>",{"class":"lwss-font-list-toggle-less"}).appendTo(d).html(lws_adminpanel.fontToggleLess).hide();this.main.append(this.$select).append(this.$arrow);this.$original.remove(".lwss-font-list").append(this.$list)};c.prototype.fontDescription=function(){return(this.$family.val().length>0?(this.$family.val()+(this.$weight.val().length>0?(" : "+this.weightTr(this.$weight.val())):"")):lws_adminpanel.fontPlaceHolder)};c.prototype.weightTr=function(d){if(lws_adminpanel.fontWeightTr[d.toLowerCase()]!=undefined){return lws_adminpanel.fontWeightTr[d.toLowerCase()]}return d};c.prototype.toggleFontList=function(){if(this.$list.is(":visible")){if(this.$toggleMore.is(":visible")){this.$toggleMore.hide();this.$toggleLess.show();this.loadAllFonts(this)}else{this.$toggleLess.hide();this.$toggleMore.show();this.loadFonts(this)}}};c.prototype.createFontList=function(d,w){var e=b("<div>",{"class":"lwss-font-list-content"});for(var h=0;h<d.length;++h){var g=d[h];var v=b("<div>",{"class":"lwss-font-list-line"});if(w!=undefined&&h<w){k.append(v)}else{e.append(v)}var x=b("<div>",{"class":"lwss-font-list-line-family lwss-font-list-item-to-style"}).html(g.family).attr("data-family",g.family).appendTo(v);var f=[];for(var i=0;i<g.variants.length;++i){if(g.variants[i].indexOf("italic")<0){f.push(g.variants[i])}}if(f.length>1){var j=b("<div>",{"class":"lwss-font-list-line-btn lws-icon-select_arrow_right "+((this.cssOptions.cssColor!=undefined)?" "+this.cssOptions.cssColor:""),"data-family":g.family,"data-variants":f.join(",")}).appendTo(v);j.click(n(this.showVariants,this))}x.click(n(this.selectFamily,this))}return e};c.prototype.setFonts=function(d,e){b(".lwss-font-list-variant").remove();this.$list.find(".lwss-font-list-content").detach();this.$list.find(".lwss-font-list-last-used").detach();if(e==undefined){if(l==null){l=this.createFontList(d,e)}this.$list.prepend(l)}else{if(k==null){k=b("<div>",{"class":"lwss-font-list-last-used"})}if(q==null){q=this.createFontList(d,e)}this.$list.prepend(q);this.$list.prepend(k);if(k.children().length<=0){k.hide()}}this.scrollToSelection()};c.prototype.selectFamily=function(d){if(r!=null){var e=b(d.target).closest("div.lwss-font-list-line-family");this.$list.hide();r.applyFont.call(r,e.data("family"))}};c.prototype.showVariants=function(f){var g=b(f.target).closest("div[data-variants]");b(".lwss-font-list-variant").remove();var h=b("<div>",{"class":"lwss-font-list-variant",style:"display:none;"}).appendTo(g);h.offset({left:(g.offset().left+g.outerWidth()),top:g.offset().top});var e=g.data("variants").split(",");for(var i=0;i<e.length;++i){var d=b("<div>",{"class":"lwss-font-list-line lwss-font-list-item-to-style"}).appendTo(h);b("<span>",{"class":"lwss-font-list-line-variant"}).appendTo(d).html(this.weightTr(e[i]));d.data("family",g.data("family")).data("weight",e[i]);d.click(n(this.selectVariant,this))}h.fadeIn()};c.prototype.selectVariant=function(d){if(r!=null){var e=b(d.target).closest("div.lwss-font-list-line");this.$list.hide();r.applyFont.call(r,e.data("family"),e.data("weight"))}};c.prototype.applyFont=function(e,d){this.$family.val(e);if(d==undefined){d=""}this.$weight.val(d);this.$family.trigger("change");this.$weight.trigger("change")};c.prototype.upUsedFont=function(f){if(k!=null){k.show();var e="div.lwss-font-list-line-family[data-family='"+f+"']";if(l==null){var d=q.find(e).parent().detach();if(d.length<=0){d=k.find(e).parent().detach()}k.prepend(d)}else{q.find(e).parent().remove();k.find(e).parent().remove();k.prepend(l.find(e).parent().clone(true,true))}}};c.prototype.addFontLink=function(e,f){var d=this.options.api+e;if(f!=undefined&&f.length>0){d+=(":"+f)}if(b("link[href='"+d+"']").length===0){b("link:last").after('<link href="'+d+'" rel="stylesheet" type="text/css">')}};c.prototype.setFontStyle=function(f,d,g){var e="font-family:"+d;if(g!=undefined&&g.length>0){e+=(";font-weight:"+g)}f.attr("style",e)};c.prototype.loadFonts=(function(){var f=null;var d=0;var e=[];return function(g){if(f==null){e.push(g);if(e.length==1){b(".lwss-font-list-toggle").addClass("ui-autocomplete-loading");b.getJSON(lws_ajax_url,{action:"lws_adminpanel_fontlist"},function(h){if(null==h){console.log("Font ressource error.")}else{if(0==h){console.log("Ajax action not found.")}else{f=h.items;d=h.lastUsedCount;e.forEach(function(i){i.setFonts.call(i,f,d)});e=[];b(".lwss-font-list-toggle").removeClass("ui-autocomplete-loading")}}}).fail(function(i,h,j){console.log("Ajax failed with status "+h+", error: "+j)})}}else{g.setFonts.call(g,f,d)}return f}})();c.prototype.loadAllFonts=(function(){var d=null;var e=[];return function(f){if(d==null){e.push(f);if(e.length==1){b(".lwss-font-list-toggle").addClass("ui-autocomplete-loading");b.getJSON(lws_ajax_url,{action:"lws_adminpanel_fontlist",all:1},function(g){if(null==g){console.log("Font ressource error.")}else{if(0==g){console.log("Ajax action not found.")}else{d=g.items;e.forEach(function(h){h.setFonts(d)});e=[];b(".lwss-font-list-toggle").removeClass("ui-autocomplete-loading")}}}).fail(function(h,g,i){console.log("Ajax failed with status "+g+", error: "+i)})}}else{f.setFonts(d)}return d}})();c.prototype._externalChange=function(){this.$select.html(this.fontDescription());this.addFontLink(this.$family.val(),this.$weight.val());this.setFontStyle(this.$select,this.$family.val(),this.$weight.val());this.upUsedFont(this.$family.val())};c.prototype.bindEvents=function(){this.$arrow.parent().click(n(this.dropDown,this));this.$toggleMore.click(n(this.toggleFontList,this));this.$toggleLess.click(n(this.toggleFontList,this));this.$family.change(n(this._externalChange,this));this.$weight.change(n(this._externalChange,this))};c.prototype.dropDown=function(){b(".lwss-hide-on-clic-out").fadeOut();b(".lwss-fold-on-clic-out").slideUp();r=this;this.loadFonts(this);this.$list.fadeIn();this.$toggleLess.hide();this.$toggleMore.show();this.$list.offset({left:this.$original.offset().left,top:(this.$original.offset().top+this.$select.outerHeight())});this.scrollToSelection();this.testVisibilityInterval=setInterval(n(this.styleVisibleFonts,this),this.options.interval);return false};c.prototype.scrollToSelection=function(){var e=this.$list.find("[data-family='"+this.$family.val()+"']:first");if(e.length>0){var d=e.closest(".lwss-font-list-line").position().top-this.$list.find(".lwss-font-list-line:first").position().top;this.$list.find(".lwss-font-list-content").scrollTop(d)}};c.prototype.dropUp=function(){this.$list.fadeOut();if(this.testVisibilityInterval!=undefined){clearInterval(this.testVisibilityInterval);this.testVisibilityInterval=undefined}};c.prototype.styleVisibleFonts=function(){if(this.$list.is(":hidden")){if(this.testVisibilityInterval!=undefined){clearInterval(this.testVisibilityInterval);this.testVisibilityInterval=undefined}}else{var f=this.$list.find(".lwss-font-list-content");var g=f.scrollTop();var e=g+f.height();var d=this;this.$list.find(".lwss-font-list-item-to-style").each(function(){if(!this.hasAttribute("style")){var i=b(this).closest(".lwss-font-list-line");var j=i.position().top+g;var h=j+b(this).height();if((h>=g)&&(j<=e)){d.addFontLink(b(this).data("family"),b(this).data("weight"));d.setFontStyle(b(this),b(this).data("family"),b(this).data("weight"))}}})}};return c})();return this.each(function(c){if(c){b.extend(p,c)}return new m(this,p,o)})}})(jQuery);jQuery(function(b){b(".lwss-fontname-group").fontselector()});