!function(e){function t(n){if(r[n])return r[n].exports
var i=r[n]={exports:{},id:n,loaded:!1}
return e[n].call(i.exports,i,i.exports,t),i.loaded=!0,i.exports}var r={}
return t.m=e,t.c=r,t.p="",t(0)}([function(e,t,r){e.exports=r(1)},function(e,t,r){"use strict"
function n(e){return e&&e.__esModule?e:{"default":e}}function i(e){var t=document.getElementsByTagName("base")[0].href.replace("http://","").replace(/\//g,"_").replace(/\./g,"_")
return t+e}var o=r(2),u=n(o);(0,u["default"])(document).ready(function(){(0,u["default"])("#switchView a.newWindow").on("click",function(e){var t=window.open(this.href,i(this.target))
return t.focus(),!1}),(0,u["default"])("#SilverStripeNavigatorLink").on("click",function(e){return(0,u["default"])("#SilverStripeNavigatorLinkPopup").toggle(),!1}),(0,u["default"])("#SilverStripeNavigatorLinkPopup a.close").on("click",function(e){
return(0,u["default"])("#SilverStripeNavigatorLinkPopup").hide(),!1}),(0,u["default"])("#SilverStripeNavigatorLinkPopup input").on("focus",function(e){this.select()})})},function(e,t){e.exports=jQuery}])

//# sourceMappingURL=SilverStripeNavigator.js.map