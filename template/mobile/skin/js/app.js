$(function(){
	//赞一下、踩一下
	if($(".digs").length>0)
	{
		$(".digs").click(function()
		{
			var url=$(this).attr("data-url");
			var that=this;
			$.ajax({
				   type:"post",
				   url:url,
				   dataType:"json",
				   error:function(){alert("服务器错误")},
				   success:function(d)
				   {
					   if(d.state=='success')
					   {
						   $(that).find("em").html(d.msg);
					   }
					   else
					   {
						   alert(d.msg)
					   }
				   }
			});
		});
	}
	//付款方式
	if($("#orderpay").length>0)
	{
		$("#orderpay li").click(function()
		{
			var config=$(this).find("img").attr("data-config");
			$("#payway").val(config);
			$(this).siblings().removeClass('active').end().addClass('active');
		})	
	}

})