/**
 * @package CG BDay Plugin for Joomla 4.X/5x
 * @copyright (c) 2023 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
 * using https://animate.style/
 */
var lefttime;
var animate_effects = []; // animate 2 to animate 3
animate_effects["fadeIn"] = "fadeIn";
animate_effects["flipXIn"] = "flipInX";
animate_effects["flipYIn"] = "flipInY";
animate_effects["swoopIn"] = "rollIn";
animate_effects["whirlIn"] ="rotateIn";
animate_effects["slideUpIn"] ="slideInUp";
animate_effects["slideUpBigIn"] = "slideInUp";
animate_effects["slideDownBigIn"] ="slideInDown";
animate_effects["slideLeftBigIn"] = "slideInLeft";
animate_effects["slideRightBigIn"] ="slideInRight"
animate_effects["perspectiveUpIn"] ="fadeInUpBig";
animate_effects["perspectiveDownIn"] = "fadeInDownBig";
animate_effects["perspectiveLeftIn"] ="fadeInLeftBig";
animate_effects["perspectiveRightIn"] = "fadeInRightBig";

	
if (typeof Joomla === 'undefined' || typeof Joomla.getoptions === 'undefined') {
	console.log('Joomla.getoptions not found! The Joomla core.js file is not being loaded.');
}
document.addEventListener('DOMContentLoaded', function() {
	options_bday = Joomla.getOptions('plg_system_bday');
	if (typeof options_bday === 'undefined' ) { // cache Joomla problem
		return false;
	};
	if (typeof options_bday !== 'undefined' ) {
		go_popup();
	}
});

function go_popup() {
	sp_popup = document.querySelector('#plg_bday');
	sp_button = document.querySelector('#btn_plg_bday');
	close_popup = document.querySelector('#plg_bday .plg-close-popup');
	sp_wrap = document.querySelector('#plg_bday_scroll .str_wrap');
	sp_move = document.querySelector('#plg_bday_scroll .str_move');
	sp_popup.style.width = options_bday.width+"%";
	sp_popup.style.backgroundColor = options_bday.background;
	sp_popup.style.margin = options_bday.margin ;
	sp_popup.style.opacity = 0; // hide popup
	sp_popup.style.display = 'none'; // hide popup
	sp_popup.style.setProperty('--animate-duration', '800ms');
	sp_wrap.style.width = options_bday.scroll_width;
	if (options_bday.scroll_height.substr(-2) == "em") {
		sp_wrap.style.height = (parseInt(options_bday.scroll_height) * 12)+"px";
	} else {
		sp_wrap.style.height = options_bday.scroll_height;
	}
	if (sp_button) {
		sp_button.style.backgroundColor = options_bday.background;
		sp_button.style.position = "fixed";
		sp_button.style.display = 'none';
		sp_button.style.opacity = 0; 
		sp_button.style.setProperty('--animate-duration', '800ms');
	}
	$cookieName = 'plg_bday';
	if (!options_bday.test) { // mode normal
		setCookie('plg_bday',1,1);
	}
	if ((getCookie($cookieName) != "")  ) { // affichage bouton
		show_button();
	} else {  // on cache le bouton
		hide_button();
	}
	if (options_bday.pos == 'left') {
		sp_popup.style.left = '0%';
		if (sp_button) sp_button.style.left = '0%';
	}
	if (options_bday.pos == 'right') {
		sp_popup.style.right = '0%';
		if (sp_button) sp_button.style.right = '0%';
	}
	if (options_bday.pos == 'center') {
		$center = (100 - options_bday.width) / 2;
		sp_popup.style.left = $center+'%';
		if (sp_button) sp_button.style.left = $center+'%';
	}
	if (close_popup) {
		close_popup.addEventListener("click",function(e){ // close button
			$cookieName = 'plg_bday';
			setCookie($cookieName,"test",options_bday.duration);
			hide_popup();
			if (options_bday.title_button_popup == 1) { // show title button 
				show_button();
			};
		});
	}
	if (sp_button) {
		sp_button.addEventListener("click",function(e){ // title button
		$cookieName = 'plg_bday';
		setCookie($cookieName,"test",options_bday.duration);
		hide_button();
		show_popup();
		e.stopPropagation();
		});
	}
	if  (getCookie("plg_bday") == "") {// pas de cookie: on affiche la popup 
		if (options_bday.delay > 0) {
			setTimeout(function(){
				show_popup();
			}, options_bday.delay); 
		} else {
			show_popup();
		}
	} 

	// from https://www.w3docs.com/snippets/javascript/how-to-detect-a-click-outside-an-element.html
	document.addEventListener("click", function(evt) {
		show_button(); 
	});
	if (options_bday.multi) { // more than 1 user : let's scroll
		goscroll();
	}
}
// up/down scroll
function scrollmarquee(){
	if (parseInt(sp_wrap.style.top) > -parseInt(sp_move.clientHeight)) {
		sp_wrap.style.top = (parseInt(sp_wrap.style.top)-(parseInt(options_bday.scroll_speed)/10))+"px";
	} else {
		sp_wrap.style.top = actualheight+"px";
	}
}
// right/left scroll
function rightmarquee(){
	if (parseInt(sp_wrap.style.left) > -parseInt(sp_move.clientWidth)) { 
		sp_wrap.style.left = (parseInt(sp_wrap.style.left)-(parseInt(options_bday.scroll_speed)/10))+"px"
	} else {
		sp_wrap.style.left = (sp_move.clientWidth * 2)+"px";;
	} 
}

function goscroll() {
	if (!options_bday.multi) return; // one user : exit
	if (options_bday.scroll_direction == "up") {
		clearInterval(lefttime);
		actualheight=parseInt(sp_wrap.style.height);
		lefttime = setInterval(function() { scrollmarquee(); },70)
	}
	if (options_bday.scroll_direction == "left") {
		clearInterval(lefttime);
		actualwidth=parseInt(sp_wrap.style.width);
		lefttime = setInterval(function() { rightmarquee(); },70)
	}
}
function stopscroll() {
	clearInterval(lefttime);
}
function show_popup() {
	sp_popup = document.querySelector('#plg_bday');
	sp_popup.style.opacity = options_bday.opacity;
	sp_popup.style.display = 'block';
	sp_popup.classList.add('animate__animated', 'animate__'+animate_effects[options_bday.speffect]);	
}
function hide_popup() {
	sp_popup = document.querySelector('#plg_bday');
	sp_popup.style.opacity = 0;
	sp_popup.style.display = "none";
	sp_popup.classList.remove('animate__animated','animate__'+animate_effects[options_bday.speffect]);
}
function show_button(myid) {
	sp_button = document.querySelector('#btn_plg_bday');
	if (!sp_button) return; // not defined : exit
	sp_button.style.opacity = options_bday.opacity;
	sp_button.style.display = 'block';
	sp_button.classList.add('animate__animated', 'animate__'+animate_effects[options_bday.speffect]);
}
function hide_button(myid) {
	sp_button = document.querySelector('#btn_plg_bday');
	if (!sp_button) return; // not defined : exit
	sp_button.style.opacity = 0; // hide button
	sp_button.style.display = 'none'; 
	sp_button.classList.remove('animate__animated','animate__'+animate_effects[options_bday.speffect]);
}
function getCookie(name) { 
  let matches = document.cookie.match(new RegExp(
    "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
  ));
  return matches ? decodeURIComponent(matches[1]) : '';
}	
function pad(s) { return (s < 10) ? '0' + s : s; }
function setCookie(cname, cvalue, exdays) {
    if (options_bday.test) return; // mode test : no cookie
	var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+ d.toUTCString();
	var now = new Date().getFullYear() + "-" + pad(new Date().getMonth() + 1) + "-" + pad(new Date().getDate());
	$secure = "";
	if (window.location.protocol == "https:") $secure="secure;"; 
    document.cookie = cname + "=" + now + ";" + expires + ";path=/; samesite=lax;"+$secure;
}
function deleteCookie(cname) {
	document.cookie = cname + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
}
