
// TabControll Click Event
//$('.tabControll > .tabmenu > li').click(function () {
$(document).on('click','.tabControll>.tabmenu>li',function () {
//	alert("click!");
    var control = $(this).closest('.tabControll');
    var menu = control.children('.tabmenu').children('li');
    var cont = control.children('.tabcontents').children('li');
	var index = menu.index(this);
	cont.css('display','none');
	cont.eq(index).css('display','block');
	menu.removeClass('selected');
	$(this).addClass('selected');
});