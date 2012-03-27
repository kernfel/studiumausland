/*************************************************
jquery.animate-enhanced plugin v0.75
Author: www.benbarnett.net || @benpbarnett

Copyright (c) 2011 Ben Barnett
Licensed under the MIT license
http://www.opensource.org/licenses/mit-license.php
**************************************************/
(function(b,u,q){function z(a,j,c,d){var k=A.exec(j),g=a.css(c)==="auto"?0:a.css(c),g=typeof g=="string"?m(g):g;typeof j=="string"&&m(j);var d=d===!0?0:g,i=a.is(":hidden"),h=a.translation();c=="left"&&(d=parseInt(g,10)+h.x);c=="top"&&(d=parseInt(g,10)+h.y);!k&&j=="show"?(d=1,i&&a.css({display:"block",opacity:0})):!k&&j=="hide"&&(d=0);return k?(a=parseFloat(k[2]),k[1]&&(a=(k[1]==="-="?-1:1)*a+parseInt(d,10)),a):d}function B(a,j,c,d,k,g,n,h){var e=a.data(i)?!o(a.data(i))?a.data(i):jQuery.extend(!0,
{},v):jQuery.extend(!0,{},v),f=k,w=jQuery.inArray(j,s)>-1;if(w){var p=e.meta,x=m(a.css(j))||0,b=j+"_o",f=w?k-x:k;p[j]=f;p[b]=a.css(j)=="auto"?0+f:x+f||0;e.meta=p;n&&f===0&&(f=0-p[b],p[j]=f,p[b]=0)}return a.data(i,C(e,j,c,d,f,g,n,h))}function C(a,j,i,d,k,g,b,h){a=typeof a==="undefined"?{}:a;a.secondary=typeof a.secondary==="undefined"?{}:a.secondary;for(var e=c.length-1;e>=0;e--)typeof a[c[e]+"transition-property"]==="undefined"&&(a[c[e]+"transition-property"]=""),a[c[e]+"transition-property"]+=", "+
(g===!0&&b===!0?c[e]+"transform":j),a[c[e]+"transition-duration"]=i+"ms",a[c[e]+"transition-timing-function"]=d,a.secondary[g===!0&&b===!0?c[e]+"transform":j]=g===!0&&b===!0?h===!0&&D?"translate3d("+a.meta.left+"px,"+a.meta.top+"px,0)":"translate("+a.meta.left+"px,"+a.meta.top+"px)":k;return a}function E(a){for(var c in a)if((c=="width"||c=="height")&&(a[c]=="show"||a[c]=="hide"||a[c]=="toggle"))return!0;return!1}function o(a){for(var c in a)return!1;return!0}function m(a){return parseFloat(a.replace(/px/i,
""))}function F(a,c,i){var d=jQuery.inArray(a,G)>-1;if((a=="width"||a=="height")&&c===parseFloat(i.css(a)))d=!1;return d}var G=["top","right","bottom","left","opacity","height","width"],s=["top","right","bottom","left"],c=["","-webkit-","-moz-","-o-"],H=["avoidTransforms","useTranslate3d","leaveTransforms"],A=/^([+-]=)?([\d+-.]+)(.*)$/,I=/([A-Z])/g,v={secondary:{},meta:{top:0,right:0,bottom:0,left:0}},i="jQe",b=(document.body||document.documentElement).style,r=b.WebkitTransition!==void 0?"webkitTransitionEnd":
b.OTransition!==void 0?"oTransitionEnd":"transitionend",y=b.WebkitTransition!==void 0||b.MozTransition!==void 0||b.OTransition!==void 0||b.transition!==void 0,D="WebKitCSSMatrix"in window&&"m11"in new WebKitCSSMatrix;jQuery.fn.translation=function(){if(!this[0])return null;for(var a=window.getComputedStyle(this[0],null),j={x:0,y:0},i=c.length-1;i>=0;i--){var d=a.getPropertyValue(c[i]+"transform");if(d&&/matrix/i.test(d)){a=d.replace(/^matrix\(/i,"").split(/, |\)$/g);j={x:parseInt(a[4],10),y:parseInt(a[5],
10)};break}}return j};jQuery.fn.animate=function(a,j,b,d){var a=a||{},k=!(typeof a.bottom!=="undefined"||typeof a.right!=="undefined"),g=jQuery.speed(j,b,d),n=this,h=0,e=function(){h--;h===0&&typeof g.complete==="function"&&g.complete.apply(n[0],arguments)};if(!y||o(a)||E(a)||g.duration<=0||jQuery.fn.animate.defaults.avoidTransforms===!0&&a.avoidTransforms!==!1)return u.apply(this,arguments);return this[g.queue===!0?"queue":"each"](function(){var f=jQuery(this),d=jQuery.extend({},g),j=function(){for(var d=
{},b=c.length-1;b>=0;b--)d[c[b]+"transition-property"]="none",d[c[b]+"transition-duration"]="",d[c[b]+"transition-timing-function"]="";f.unbind(r);if(!a.leaveTransforms===!0){for(var j=f.data(i)||{},g={},b=c.length-1;b>=0;b--)g[c[b]+"transform"]="";if(k&&typeof j.meta!=="undefined")for(var b=0,h;h=s[b];++b)g[h]=j.meta[h+"_o"]+"px";f.css(d).css(g)}a.opacity==="hide"&&f.css("display","none");f.data(i,null);e.call(f)},b={bounce:"cubic-bezier(0.0, 0.35, .5, 1.3)",linear:"linear",swing:"ease-in-out",easeInQuad:"cubic-bezier(0.550, 0.085, 0.680, 0.530)",
easeInCubic:"cubic-bezier(0.550, 0.055, 0.675, 0.190)",easeInQuart:"cubic-bezier(0.895, 0.030, 0.685, 0.220)",easeInQuint:"cubic-bezier(0.755, 0.050, 0.855, 0.060)",easeInSine:"cubic-bezier(0.470, 0.000, 0.745, 0.715)",easeInExpo:"cubic-bezier(0.950, 0.050, 0.795, 0.035)",easeInCirc:"cubic-bezier(0.600, 0.040, 0.980, 0.335)",easeOutQuad:"cubic-bezier(0.250, 0.460, 0.450, 0.940)",easeOutCubic:"cubic-bezier(0.215, 0.610, 0.355, 1.000)",easeOutQuart:"cubic-bezier(0.165, 0.840, 0.440, 1.000)",easeOutQuint:"cubic-bezier(0.230, 1.000, 0.320, 1.000)",
easeOutSine:"cubic-bezier(0.390, 0.575, 0.565, 1.000)",easeOutExpo:"cubic-bezier(0.190, 1.000, 0.220, 1.000)",easeOutCirc:"cubic-bezier(0.075, 0.820, 0.165, 1.000)",easeInOutQuad:"cubic-bezier(0.455, 0.030, 0.515, 0.955)",easeInOutCubic:"cubic-bezier(0.645, 0.045, 0.355, 1.000)",easeInOutQuart:"cubic-bezier(0.770, 0.000, 0.175, 1.000)",easeInOutQuint:"cubic-bezier(0.860, 0.000, 0.070, 1.000)",easeInOutSine:"cubic-bezier(0.445, 0.050, 0.550, 0.950)",easeInOutExpo:"cubic-bezier(1.000, 0.000, 0.000, 1.000)",
easeInOutCirc:"cubic-bezier(0.785, 0.135, 0.150, 0.860)"},n={},b=b[d.easing||"swing"]?b[d.easing||"swing"]:d.easing||"swing",l;for(l in a)if(jQuery.inArray(l,H)===-1){var m=jQuery.inArray(l,s)>-1,t=z(f,a[l],l,m&&a.avoidTransforms!==!0);a.avoidTransforms!==!0&&F(l,t,f)?B(f,l,d.duration,b,m&&a.avoidTransforms===!0?t+"px":t,m&&a.avoidTransforms!==!0,k,a.useTranslate3d===!0):n[l]=a[l]}l=f.data(i)||{};for(b=c.length-1;b>=0;b--)typeof l[c[b]+"transition-property"]!=="undefined"&&(l[c[b]+"transition-property"]=
l[c[b]+"transition-property"].substr(2));f.data(i,l).unbind(r);if(!o(f.data(i))&&!o(f.data(i).secondary)){h++;f.css(f.data(i));var q=f.data(i).secondary;setTimeout(function(){f.bind(r,j).css(q)})}else d.queue=!1;o(n)||(h++,u.apply(f,[n,{duration:d.duration,easing:jQuery.easing[d.easing]?d.easing:jQuery.easing.swing?"swing":"linear",complete:e,queue:d.queue}]));return!0})};jQuery.fn.animate.defaults={};jQuery.fn.stop=function(a,b,m){if(!y)return q.apply(this,[a,b]);a&&this.queue([]);for(var d={},k=
c.length-1;k>=0;k--)d[c[k]+"transition-property"]="none",d[c[k]+"transition-duration"]="",d[c[k]+"transition-timing-function"]="";this.each(function(){var g=jQuery(this),k=window.getComputedStyle(this,null),h={},e;if(!o(g.data(i))&&!o(g.data(i).secondary)){e=g.data(i);if(b){if(h=e.secondary,!m&&typeof e.meta.left_o!==void 0||typeof e.meta.top_o!==void 0){h.left=typeof e.meta.left_o!==void 0?e.meta.left_o:"auto";h.top=typeof e.meta.top_o!==void 0?e.meta.top_o:"auto";for(e=c.length-1;e>=0;e--)h[c[e]+
"transform"]=""}}else for(var f in g.data(i).secondary)if(f=f.replace(I,"-$1").toLowerCase(),h[f]=k.getPropertyValue(f),!m&&/matrix/i.test(h[f])){e=h[f].replace(/^matrix\(/i,"").split(/, |\)$/g);h.left=e[4]+"px"||"auto";h.top=e[5]+"px"||"auto";for(e=c.length-1;e>=0;e--)h[c[e]+"transform"]=""}g.unbind(r).css(d).css(h).data(i,null)}else q.apply(g,[a,b])});return this}})(jQuery,jQuery.fn.animate,jQuery.fn.stop);