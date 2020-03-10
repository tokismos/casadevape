wp.blocks.registerBlockType("woorewards/sponsorship",{title:"Sponsorship",icon:lwsWooRewardsSvgIcon,category:"woorewards",attributes:{title:{type:"string",source:"html",selector:"p.lws_woorewards_sponsorship_description"},button:{type:"string",source:"html",selector:"div.lws_woorewards_sponsorship_submit"}},edit:function(a){var b=wp.element.createElement(wp.editor.InspectorControls,null,wp.element.createElement("a",{href:lws_wr_sponsor.stygen_href,style:{display:"block","text-align":"right"}},wp.i18n.__("Style Editor","woorewards-pro")));return wp.element.createElement(wp.element.Fragment,null,b,wp.element.createElement("div",{className:"lwss_selectable lws_woorewards_sponsorship_widget"},wp.element.createElement(wp.editor.RichText,{tagName:"p",className:"lwss_selectable lwss_modify lws_woorewards_sponsorship_description",value:a.attributes.title,onChange:function(c){a.setAttributes({title:c})},placeholder:wp.i18n.__("Sponsor your friend","woorewards-pro"),keepPlaceholderOnFocus:true}),wp.element.createElement("div",{className:"lwss_selectable lws_woorewards_sponsorship_form"},wp.element.createElement("div",{className:"lwss_selectable lws_woorewards_sponsorship_input"},wp.element.createElement("input",{className:"lwss_selectable lwss_modify lws_woorewards_sponsorship_field",type:"email",disabled:true,value:wp.i18n.__("my.friend@example.com","woorewards-pro")})),wp.element.createElement(wp.editor.RichText,{tagName:"div",className:"lwss_selectable lwss_modify lws_woorewards_sponsorship_submit",value:a.attributes.button,onChange:function(c){a.setAttributes({button:c})},placeholder:wp.i18n.__("Submit","woorewards-pro"),keepPlaceholderOnFocus:true})),wp.element.createElement("p",{className:"lwss_selectable lws_woorewards_sponsorship_feedback",style:{display:"none"}},wp.i18n.__("Hidden block at start. Feedback to customer will appear here.","woorewards-pro"))))},save:function(a){return wp.element.createElement("div",{className:"lwss_selectable lws_woorewards_sponsorship_widget"},wp.element.createElement(wp.editor.RichText.Content,{tagName:"p",className:"lwss_selectable lwss_modify lws_woorewards_sponsorship_description",value:a.attributes.title}),wp.element.createElement("div",{className:"lwss_selectable lws_woorewards_sponsorship_form"},wp.element.createElement("div",{className:"lwss_selectable lws_woorewards_sponsorship_input"},wp.element.createElement(wp.element.Fragment,null,"[lws_sponsorship_nonce_input]"),wp.element.createElement("input",{className:"lwss_selectable lwss_modify lws_woorewards_sponsorship_field",type:"email",placeholder:wp.i18n.__("my.friend@example.com","woorewards-pro")})),wp.element.createElement(wp.editor.RichText.Content,{tagName:"div",className:"lwss_selectable lwss_modify lws_woorewards_sponsorship_submit",style:{cursor:"pointer"},value:a.attributes.button})),wp.element.createElement("p",{className:"lwss_selectable lws_woorewards_sponsorship_feedback",style:{display:"none"}},""))}});